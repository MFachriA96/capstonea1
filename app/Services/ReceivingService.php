<?php

namespace App\Services;

use App\Models\Inbound;
use App\Models\InboundDetail;
use App\Models\Outbound;
use App\Models\OutboundBox;
use App\Models\OutboundDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReceivingService
{
    public function __construct(
        protected DiscrepancyService $discrepancyService
    ) {
    }

    public function getQueue(?int $warehouseId = null, ?User $user = null)
    {
        $warehouseId = $this->resolveWarehouseScope($warehouseId, $user);
        $query = Outbound::with('vendor');

        if ($warehouseId !== null) {
            $query->where('ID_gudang_tujuan', $warehouseId);
        }

        return $query->whereIn('status', ['submitted', 'arrived'])->get();
    }

    public function getShipmentContext(int $outboundId, ?User $user = null): Outbound
    {
        $outbound = Outbound::with(['vendor', 'details.boxes', 'inbound.details'])->findOrFail($outboundId);

        $this->assertWarehouseAccess($user, $outbound->ID_gudang_tujuan);

        return $outbound;
    }

    public function scanBox(string $qrToken, array $data, User $officer): array
    {
        return DB::transaction(function () use ($qrToken, $data, $officer) {
            $box = $this->resolveBoxFromToken($qrToken);
            $warehouseId = $this->resolveWarehouseScope($data['ID_gudang'] ?? null, $officer, true);

            $detail = $box->outboundDetail;
            $outbound = $detail->outbound;

            $this->assertWarehouseAccess($officer, $outbound->ID_gudang_tujuan);

            if ($outbound->ID_gudang_tujuan !== null && (int) $outbound->ID_gudang_tujuan !== (int) $warehouseId) {
                abort(403, 'QR belongs to another warehouse.');
            }

            if ($box->scan_status !== 'pending') {
                abort(422, 'This box has already been scanned.');
            }

            $totalExpected = (int) $outbound->details()->sum('jumlah_box');

            $inbound = Inbound::firstOrCreate(
                ['ID_outbound' => $outbound->ID_outbound],
                [
                    'ID_gudang' => $warehouseId,
                    'ID_vendor' => $outbound->ID_vendor,
                    'timestamp_terima' => now(),
                    'nama_penerima' => $data['nama_penerima'],
                    'diterima_oleh' => $officer->ID_user,
                    'qr_scan_result' => $qrToken,
                    'lokasi_terakhir' => $data['lokasi_terakhir'] ?? null,
                    'total_box_expected' => $totalExpected,
                    'total_box_sudah_discan' => 0,
                    'total_qr_expected' => $totalExpected,
                    'total_qr_sudah_discan' => 0,
                    'status_scan' => 'menunggu',
                ]
            );

            if ($outbound->ID_gudang_tujuan === null) {
                $outbound->update(['ID_gudang_tujuan' => $warehouseId]);
            }

            $box->update([
                'scan_status' => 'scanned',
                'scanned_at' => now(),
                'scanned_by' => $officer->ID_user,
                'updated_at' => now(),
            ]);

            InboundDetail::firstOrCreate(
                [
                    'ID_inbound' => $inbound->ID_inbound,
                    'ID_outbound_detail' => $detail->ID_outbound_detail,
                ],
                [
                    'ID_barang' => $detail->ID_barang,
                    'quantity_cv_detect' => null,
                    'quantity_inbound' => null,
                    'ada_cacat' => false,
                ]
            );

            $inbound->increment('total_qr_sudah_discan');
            $outbound->update(['status' => 'arrived']);

            return [
                'inbound' => $inbound->fresh(),
                'shipment' => [
                    'ID_outbound' => $outbound->ID_outbound,
                    'status' => $outbound->fresh()->status,
                ],
                'box' => [
                    'ID_outbound_box' => $box->ID_outbound_box,
                    'box_code' => $box->box_code,
                    'expected_qty_in_box' => $box->expected_qty_in_box,
                ],
            ];
        });
    }

    protected function resolveBoxFromToken(string $qrToken): OutboundBox
    {
        $box = OutboundBox::with('outboundDetail.outbound.vendor')
            ->where('qr_token', $qrToken)
            ->first();

        if ($box) {
            return $box;
        }

        $detail = OutboundDetail::with('outbound.vendor')
            ->where('qr_token', $qrToken)
            ->firstOrFail();

        $existingBox = $detail->boxes()->where('qr_token', $qrToken)->first();
        if ($existingBox) {
            return $existingBox->load('outboundDetail.outbound.vendor');
        }

        $expectedQty = $detail->jumlah_box > 1
            ? (int) $detail->quantity_per_box
            : (int) $detail->quantity_outbound;

        return $detail->boxes()->create([
            'box_sequence' => 1,
            'box_code' => sprintf('LEGACY-BOX-%d-001', $detail->ID_outbound_detail),
            'expected_qty_in_box' => $expectedQty,
            'qr_token' => $qrToken,
            'scan_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ])->load('outboundDetail.outbound.vendor');
    }

    public function verifyBox(array $data, User $officer): array
    {
        return DB::transaction(function () use ($data, $officer) {
            $box = OutboundBox::with('outboundDetail')->findOrFail($data['ID_outbound_box']);
            $inbound = Inbound::with('outbound')->findOrFail($data['ID_inbound']);

            $this->assertWarehouseAccess($officer, $inbound->ID_gudang);

            $status = (int) $data['actual_qty'] === (int) $box->expected_qty_in_box
                ? 'match'
                : ((int) $data['actual_qty'] > (int) $box->expected_qty_in_box ? 'over' : 'mismatch');

            $detail = InboundDetail::firstOrCreate(
                [
                    'ID_inbound' => $inbound->ID_inbound,
                    'ID_outbound_detail' => $box->ID_outbound_detail,
                ],
                [
                    'ID_barang' => $box->outboundDetail->ID_barang,
                    'quantity_inbound' => 0,
                    'quantity_cv_detect' => null,
                    'ada_cacat' => false,
                ]
            );

            $detail->update([
                'quantity_inbound' => (int) $data['actual_qty'],
                'ada_cacat' => $data['condition_status'] !== 'normal',
                'catatan_cacat' => $data['notes'] ?? null,
            ]);

            $box->update([
                'scan_status' => $status === 'match' ? 'verified' : 'issue_flagged',
                'verified_at' => now(),
                'verified_by' => $officer->ID_user,
                'updated_at' => now(),
            ]);

            $inbound->update([
                'total_box_sudah_discan' => OutboundBox::whereHas(
                    'outboundDetail',
                    fn ($query) => $query->where('ID_outbound', $inbound->ID_outbound)
                )->whereIn('scan_status', ['verified', 'issue_flagged'])->count(),
                'status_scan' => 'sedang_diproses',
            ]);

            return [
                'verification_status' => $status,
                'expected_qty' => $box->expected_qty_in_box,
                'actual_qty' => (int) $data['actual_qty'],
                'shipment_progress' => [
                    'expected_boxes' => $inbound->fresh()->total_box_expected,
                    'verified_boxes' => $inbound->fresh()->total_box_sudah_discan,
                ],
            ];
        });
    }

    public function finalizeInbound(int $inboundId, ?User $officer = null): array
    {
        return DB::transaction(function () use ($inboundId, $officer) {
            $inbound = Inbound::with(['outbound', 'details.outboundDetail'])->findOrFail($inboundId);

            $this->assertWarehouseAccess($officer, $inbound->ID_gudang);

            if ((int) $inbound->total_qr_sudah_discan < (int) $inbound->total_qr_expected) {
                abort(422, 'Cannot finalize receiving before all boxes are scanned.');
            }

            $inbound->update([
                'status_scan' => 'selesai',
                'total_box_sudah_discan' => $inbound->total_box_expected,
            ]);

            $this->discrepancyService->generateDiscrepancies($inbound->ID_inbound);

            $issueBoxes = OutboundBox::whereHas(
                'outboundDetail',
                fn ($query) => $query->where('ID_outbound', $inbound->ID_outbound)
            )->where('scan_status', 'issue_flagged')->count();

            return [
                'ID_inbound' => $inbound->ID_inbound,
                'shipment_status' => $inbound->outbound->fresh()->status,
                'finalized_by' => $officer?->ID_user,
                'summary' => [
                    'expected_boxes' => (int) $inbound->total_box_expected,
                    'scanned_boxes' => (int) $inbound->total_qr_sudah_discan,
                    'issue_boxes' => $issueBoxes,
                ],
            ];
        });
    }

    protected function resolveWarehouseScope(?int $requestedWarehouseId, ?User $user = null, bool $required = false): ?int
    {
        if ($user && $user->role === 'petugas') {
            if ($user->ID_gudang === null) {
                abort(403, 'Assigned warehouse is required for receiving officers.');
            }

            return (int) $user->ID_gudang;
        }

        if ($required && $requestedWarehouseId === null) {
            abort(422, 'Warehouse is required.');
        }

        return $requestedWarehouseId;
    }

    protected function assertWarehouseAccess(?User $user, ?int $warehouseId): void
    {
        if (! $user || $user->role !== 'petugas') {
            return;
        }

        if ($user->ID_gudang === null) {
            abort(403, 'Assigned warehouse is required for receiving officers.');
        }

        if ($warehouseId !== null && (int) $user->ID_gudang !== (int) $warehouseId) {
            abort(403, 'You are not allowed to access another warehouse.');
        }
    }
}
