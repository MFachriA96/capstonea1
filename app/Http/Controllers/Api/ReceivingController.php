<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discrepancy;
use App\Models\Foto;
use App\Models\Inbound;
use App\Models\InboundDetail;
use App\Models\Outbound;
use App\Models\OutboundDetail;
use App\Models\ScanSession;
use App\Services\InboundService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReceivingController extends Controller
{
    use ApiResponse;

    public function __construct(protected InboundService $inboundService)
    {
    }

    public function queue(Request $request)
    {
        $query = Outbound::with(['vendor', 'gudangTujuan', 'details.barang', 'inbound.details.discrepancies', 'inbound.details.auditPhotos'])
            ->whereIn('status', ['submitted', 'in_transit', 'arrived'])
            ->orderByDesc('created_at')
            ->orderByDesc('ID_outbound');

        $this->applyReceivingWarehouseScope($query, $request);
        $this->applyOpenReceivingScope($query);

        return $this->success(
            $query->paginate(15)->through(fn (Outbound $outbound) => $this->shipmentPayload($outbound))
        );
    }

    public function show(Request $request, string $outboundId)
    {
        $outbound = Outbound::with(['vendor', 'gudangTujuan', 'details.barang', 'inbound.details.discrepancies', 'inbound.details.auditPhotos'])
            ->findOrFail($outboundId);

        $this->authorizeReceivingWarehouse($outbound, $request);

        return $this->success($this->shipmentPayload($outbound));
    }

    public function scanBox(Request $request)
    {
        $validated = $request->validate([
            'qr_token' => ['required', 'string'],
            'ID_gudang' => ['required', 'integer', 'exists:tabel_gudang,ID_gudang'],
            'nama_penerima' => ['required', 'string', 'max:100'],
            'lokasi_terakhir' => ['nullable', 'string', 'max:200'],
        ]);

        $outboundDetail = OutboundDetail::with('outbound')->where('qr_token', $validated['qr_token'])->firstOrFail();
        $this->authorizeReceivingWarehouse($outboundDetail->outbound, $request, (int) $validated['ID_gudang']);

        $result = $this->inboundService->createInboundFromQr($validated['qr_token'], $validated, $request->user());
        $inbound = ($result['inbound'] ?? Inbound::where('ID_outbound', $outboundDetail->ID_outbound)->firstOrFail())
            ->load(['details.discrepancies', 'details.auditPhotos']);
        $outbound = Outbound::with(['vendor', 'gudangTujuan', 'details.barang', 'inbound.details.discrepancies', 'inbound.details.auditPhotos'])
            ->findOrFail($outboundDetail->ID_outbound);

        return $this->success([
            'shipment' => $this->shipmentPayload($outbound),
            'inbound' => $this->inboundPayload($inbound),
            'box' => $this->boxPayload($outboundDetail->refresh(), $inbound),
            'progress' => $result['progress'] ?? null,
        ], $result['message'] ?? 'Box scanned successfully.');
    }

    public function verifyBox(Request $request)
    {
        $validated = $request->validate([
            'ID_inbound' => ['required', 'integer', 'exists:tabel_inbound,ID_inbound'],
            'ID_outbound_box' => ['required', 'integer', 'exists:tabel_outbound_detail,ID_outbound_detail'],
            'actual_qty' => ['required', 'integer', 'min:0'],
            'condition_status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $inbound = Inbound::with('outbound')->findOrFail($validated['ID_inbound']);
        $outboundDetail = OutboundDetail::findOrFail($validated['ID_outbound_box']);
        abort_if($inbound->status_scan === 'selesai', 409, 'Shipment receiving is already completed.');

        if ((int) $outboundDetail->ID_outbound !== (int) $inbound->ID_outbound) {
            abort(422, 'Box does not belong to this inbound shipment.');
        }

        $this->authorizeReceivingWarehouse($inbound->outbound, $request, (int) $inbound->ID_gudang);

        $conditionStatus = $validated['condition_status'] ?? 'normal';
        $actualQty = (int) $validated['actual_qty'];
        $expectedQty = (int) $outboundDetail->quantity_outbound;
        $status = $this->resolveDiscrepancyStatus($expectedQty, $actualQty, $conditionStatus);
        $selisih = $actualQty - $expectedQty;

        $result = DB::transaction(function () use ($validated, $outboundDetail, $actualQty, $conditionStatus, $expectedQty, $status, $selisih) {
            $inboundDetail = InboundDetail::updateOrCreate(
                [
                    'ID_inbound' => $validated['ID_inbound'],
                    'ID_outbound_detail' => $outboundDetail->ID_outbound_detail,
                ],
                [
                    'ID_barang' => $outboundDetail->ID_barang,
                    'quantity_inbound' => $actualQty,
                    'ada_cacat' => $conditionStatus !== 'normal',
                    'catatan_cacat' => $validated['notes'] ?? null,
                ]
            );

            $discrepancy = Discrepancy::updateOrCreate(
                [
                    'ID_outbound_detail' => $outboundDetail->ID_outbound_detail,
                    'ID_inbound_detail' => $inboundDetail->ID_inbound_detail,
                ],
                [
                    'quantity_outbound' => $expectedQty,
                    'quantity_inbound' => $actualQty,
                    'selisih' => $selisih,
                    'status' => $status,
                    'keterangan' => $validated['notes'] ?? ($conditionStatus !== 'normal' ? $conditionStatus : null),
                    'detected_at' => now(),
                ]
            );

            return [$inboundDetail->load('auditPhotos'), $discrepancy];
        });

        return $this->success([
            'inbound_detail' => $result[0],
            'discrepancy' => $result[1],
            'verification_status' => $status === 'match' ? 'verified' : 'issue_flagged',
        ], 'Box verification saved.');
    }

    public function uploadBoxPhoto(Request $request)
    {
        $validated = $request->validate([
            'ID_inbound' => ['required', 'integer', 'exists:tabel_inbound,ID_inbound'],
            'ID_outbound_box' => ['required', 'integer', 'exists:tabel_outbound_detail,ID_outbound_detail'],
            'foto' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        $inbound = Inbound::with('outbound')->findOrFail($validated['ID_inbound']);
        $outboundDetail = OutboundDetail::findOrFail($validated['ID_outbound_box']);
        abort_if($inbound->status_scan === 'selesai', 409, 'Shipment receiving is already completed.');

        if ((int) $outboundDetail->ID_outbound !== (int) $inbound->ID_outbound) {
            abort(422, 'Box does not belong to this inbound shipment.');
        }

        $this->authorizeReceivingWarehouse($inbound->outbound, $request, (int) $inbound->ID_gudang);

        $result = DB::transaction(function () use ($request, $validated, $inbound, $outboundDetail) {
            $inboundDetail = InboundDetail::firstOrCreate(
                [
                    'ID_inbound' => $inbound->ID_inbound,
                    'ID_outbound_detail' => $outboundDetail->ID_outbound_detail,
                ],
                [
                    'ID_barang' => $outboundDetail->ID_barang,
                ]
            );

            $session = ScanSession::where('ID_inbound', $inbound->ID_inbound)
                ->where('ID_outbound_detail', $outboundDetail->ID_outbound_detail)
                ->first();

            if (!$session) {
                $nextOrder = ((int) ScanSession::where('ID_inbound', $inbound->ID_inbound)->max('urutan_scan')) + 1;

                $session = ScanSession::create([
                    'ID_inbound' => $inbound->ID_inbound,
                    'ID_barang' => $outboundDetail->ID_barang,
                    'ID_outbound_detail' => $outboundDetail->ID_outbound_detail,
                    'urutan_scan' => $nextOrder,
                    'waktu_mulai' => now(),
                    'waktu_selesai' => now(),
                    'status_sesi' => 'selesai',
                    'ID_user' => $request->user()->ID_user,
                ]);
            }

            $disk = config('filesystems.manual_verification_disk', 'public');
            $path = $validated['foto']->store("manual-verification/{$inbound->ID_inbound}", $disk);

            $foto = Foto::create([
                'ID_session' => $session->ID_session,
                'ID_inbound' => $inbound->ID_inbound,
                'file_url' => Storage::disk($disk)->url($path),
                'uploaded_by' => $request->user()->ID_user,
                'timestamp' => now(),
                'related_type' => 'manual_condition',
            ]);

            return [
                'foto' => $foto,
                'inbound_detail' => $inboundDetail->load('auditPhotos'),
            ];
        });

        $inbound->refresh()->load(['details.discrepancies', 'details.auditPhotos']);

        return $this->success([
            'foto' => $result['foto'],
            'inbound_detail' => $result['inbound_detail'],
            'box' => $this->boxPayload($outboundDetail->refresh(), $inbound),
        ], 'Photo uploaded successfully.');
    }

    public function finalize(Request $request, string $inboundId)
    {
        $inbound = Inbound::with(['outbound.details', 'details.discrepancies'])->findOrFail($inboundId);
        $this->authorizeReceivingWarehouse($inbound->outbound, $request, (int) $inbound->ID_gudang);

        DB::transaction(function () use ($inbound) {
            foreach ($inbound->outbound->details as $outboundDetail) {
                $inboundDetail = $inbound->details->firstWhere('ID_outbound_detail', $outboundDetail->ID_outbound_detail);

                if (!$inboundDetail || $inboundDetail->quantity_inbound === null) {
                    $inboundDetail = InboundDetail::updateOrCreate(
                        [
                            'ID_inbound' => $inbound->ID_inbound,
                            'ID_outbound_detail' => $outboundDetail->ID_outbound_detail,
                        ],
                        [
                            'ID_barang' => $outboundDetail->ID_barang,
                            'quantity_inbound' => 0,
                            'ada_cacat' => false,
                            'catatan_cacat' => 'Not verified before finalization.',
                        ]
                    );

                    Discrepancy::updateOrCreate(
                        [
                            'ID_outbound_detail' => $outboundDetail->ID_outbound_detail,
                            'ID_inbound_detail' => $inboundDetail->ID_inbound_detail,
                        ],
                        [
                            'quantity_outbound' => $outboundDetail->quantity_outbound,
                            'quantity_inbound' => 0,
                            'selisih' => -1 * (int) $outboundDetail->quantity_outbound,
                            'status' => 'missing',
                            'keterangan' => 'Box was not verified before receiving finalization.',
                            'detected_at' => now(),
                        ]
                    );
                }
            }

            $inbound->update(['status_scan' => 'selesai']);
            $inbound->outbound->update(['status' => 'verified']);
        });

        $inbound->refresh()->load(['outbound.details', 'details.discrepancies', 'details.auditPhotos']);
        $issueBoxes = $inbound->details
            ->filter(fn (InboundDetail $detail) => $detail->discrepancies->where('status', '!=', 'match')->isNotEmpty())
            ->count();

        return $this->success([
            'inbound' => $this->inboundPayload($inbound),
            'summary' => [
                'total_boxes' => $inbound->outbound->details->count(),
                'issue_boxes' => $issueBoxes,
            ],
        ], 'Receiving finalized.');
    }

    protected function applyReceivingWarehouseScope(Builder $query, Request $request): void
    {
        if ($request->query('warehouse_scope') === 'all') {
            return;
        }

        $warehouseId = $request->integer('ID_gudang') ?: ($request->user()->ID_gudang ?? null);

        if ($warehouseId) {
            $query->where(function (Builder $scopeQuery) use ($warehouseId) {
                $scopeQuery->where('ID_gudang_tujuan', $warehouseId)
                    ->orWhereNull('ID_gudang_tujuan');
            });
        }
    }

    protected function applyOpenReceivingScope(Builder $query): void
    {
        $query
            ->whereDoesntHave('inbound', fn (Builder $inboundQuery) => $inboundQuery->where('status_scan', 'selesai'))
            ->whereHas('details', function (Builder $detailQuery) {
                $detailQuery->where(function (Builder $openDetailQuery) {
                    $openDetailQuery->whereNull('sudah_discan')
                        ->orWhere('sudah_discan', false)
                        ->orWhereDoesntHave('inboundDetails')
                        ->orWhereHas('inboundDetails', fn (Builder $inboundDetailQuery) => $inboundDetailQuery->whereNull('quantity_inbound'));
                });
            });
    }

    protected function abortIfReceivingCompleted(Outbound $outbound): void
    {
        if ($outbound->status === 'verified' || $outbound->inbound?->status_scan === 'selesai') {
            abort(409, 'Shipment receiving is already completed.');
        }

        $details = $outbound->relationLoaded('details') ? $outbound->details : $outbound->details()->with('inboundDetails')->get();
        $hasOpenBox = $details->contains(function (OutboundDetail $detail) {
            if (!$detail->sudah_discan) {
                return true;
            }

            $inboundDetails = $detail->relationLoaded('inboundDetails') ? $detail->inboundDetails : $detail->inboundDetails()->get();

            return $inboundDetails->isEmpty()
                || $inboundDetails->contains(fn (InboundDetail $inboundDetail) => $inboundDetail->quantity_inbound === null);
        });

        if (!$hasOpenBox) {
            abort(409, 'Shipment receiving is already completed.');
        }
    }

    protected function authorizeReceivingWarehouse(Outbound $outbound, Request $request, ?int $warehouseId = null): void
    {
        $requestedWarehouseId = $warehouseId ?: ($request->user()->ID_gudang ?? null);

        if (
            $outbound->ID_gudang_tujuan
            && $requestedWarehouseId
            && (int) $outbound->ID_gudang_tujuan !== (int) $requestedWarehouseId
        ) {
            abort(403, 'Shipment belongs to a different warehouse.');
        }
    }

    protected function shipmentPayload(Outbound $outbound): array
    {
        $inbound = $outbound->inbound;

        return [
            'ID_outbound' => $outbound->ID_outbound,
            'no_pengiriman' => $outbound->no_pengiriman,
            'ID_vendor' => $outbound->ID_vendor,
            'ID_gudang_tujuan' => $outbound->ID_gudang_tujuan,
            'status' => $outbound->status,
            'waktu_kirim' => $outbound->waktu_kirim,
            'estimasi_tiba' => $outbound->estimasi_tiba,
            'lokasi_asal' => $outbound->lokasi_asal,
            'vendor' => $outbound->vendor,
            'warehouse' => $outbound->gudangTujuan,
            'inbound' => $inbound ? $this->inboundPayload($inbound) : null,
            'details' => $outbound->details->map(function (OutboundDetail $detail) use ($inbound) {
                return [
                    'ID_outbound_detail' => $detail->ID_outbound_detail,
                    'ID_barang' => $detail->ID_barang,
                    'nama_barang' => $detail->barang?->nama_barang,
                    'quantity_outbound' => $detail->quantity_outbound,
                    'quantity_per_box' => $detail->quantity_per_box,
                    'jumlah_box' => $detail->jumlah_box,
                    'boxes' => [$this->boxPayload($detail, $inbound)],
                ];
            })->values(),
        ];
    }

    protected function inboundPayload(Inbound $inbound): array
    {
        return [
            'ID_inbound' => $inbound->ID_inbound,
            'ID_outbound' => $inbound->ID_outbound,
            'ID_gudang' => $inbound->ID_gudang,
            'ID_vendor' => $inbound->ID_vendor,
            'timestamp_terima' => $inbound->timestamp_terima,
            'nama_penerima' => $inbound->nama_penerima,
            'total_box_expected' => $inbound->total_box_expected,
            'total_box_sudah_discan' => $inbound->total_box_sudah_discan,
            'total_qr_expected' => $inbound->total_qr_expected,
            'total_qr_sudah_discan' => $inbound->total_qr_sudah_discan,
            'status_scan' => $inbound->status_scan,
        ];
    }

    protected function boxPayload(OutboundDetail $detail, ?Inbound $inbound): array
    {
        $inboundDetail = $inbound?->details?->firstWhere('ID_outbound_detail', $detail->ID_outbound_detail);
        $hasIssue = $inboundDetail?->discrepancies?->where('status', '!=', 'match')->isNotEmpty() ?? false;
        $isVerified = $inboundDetail && $inboundDetail->quantity_inbound !== null;
        $photos = $this->photoPayload($inboundDetail);

        return [
            'ID_outbound_box' => $detail->ID_outbound_detail,
            'ID_outbound_detail' => $detail->ID_outbound_detail,
            'box_code' => 'BOX-' . $detail->ID_outbound_detail,
            'qr_token' => $detail->qr_token,
            'expected_qty_in_box' => $detail->quantity_outbound,
            'actual_qty' => $inboundDetail?->quantity_inbound,
            'scan_status' => $hasIssue ? 'issue_flagged' : ($isVerified ? 'verified' : ($detail->sudah_discan ? 'scanned' : 'pending')),
            'photos' => $photos,
            'photo_count' => $photos->count(),
        ];
    }

    protected function photoPayload(?InboundDetail $inboundDetail)
    {
        if (!$inboundDetail || !$inboundDetail->relationLoaded('auditPhotos')) {
            return collect();
        }

        return $inboundDetail->auditPhotos->map(fn (Foto $foto) => [
            'ID_foto' => $foto->ID_foto,
            'file_url' => $foto->file_url,
            'timestamp' => $foto->timestamp,
            'related_type' => $foto->related_type,
        ])->values();
    }

    protected function resolveDiscrepancyStatus(int $expectedQty, int $actualQty, string $conditionStatus): string
    {
        if ($actualQty < $expectedQty) {
            return 'missing';
        }

        if ($actualQty > $expectedQty) {
            return 'over';
        }

        return $conditionStatus === 'normal' ? 'match' : 'mismatch';
    }
}
