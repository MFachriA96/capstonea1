<?php

namespace App\Services;

use App\Models\Inbound;
use App\Models\InboundDetail;
use App\Models\OutboundDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InboundService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function createInboundFromQr(string $qr_token, array $data, User $officer): array
    {
        return DB::transaction(function () use ($qr_token, $data, $officer) {
            $outboundDetail = OutboundDetail::where('qr_token', $qr_token)->with('outbound')->firstOrFail();
            $outbound = $outboundDetail->outbound;

            if (!in_array($outbound->status, ['submitted', 'in_transit'])) {
                abort(400, 'Outbound is already arrived or not submitted.');
            }

            if ($outboundDetail->sudah_discan) {
                abort(400, 'This box QR has already been scanned.');
            }

            $inbound = Inbound::where('ID_outbound', $outbound->ID_outbound)->first();

            if (!$inbound) {
                $totalQrExpected = $outbound->details()->count();
                $totalBoxExpected = $outbound->details->sum('jumlah_box');

                $inbound = Inbound::create([
                    'ID_outbound' => $outbound->ID_outbound,
                    'ID_gudang' => $data['ID_gudang'],
                    'ID_vendor' => $outbound->ID_vendor,
                    'timestamp_terima' => now(),
                    'nama_penerima' => $data['nama_penerima'],
                    'diterima_oleh' => $officer->ID_user,
                    'qr_scan_result' => $qr_token, // First scanned token
                    'lokasi_terakhir' => $data['lokasi_terakhir'] ?? null,
                    'total_box_expected' => $totalBoxExpected,
                    'total_box_sudah_discan' => 0,
                    'total_qr_expected' => $totalQrExpected,
                    'total_qr_sudah_discan' => 0,
                    'status_scan' => 'menunggu',
                ]);
            }

            // Mark this specific QR as scanned
            $outboundDetail->update([
                'sudah_discan' => true,
                'waktu_discan' => now(),
                'discan_oleh' => $officer->ID_user,
            ]);

            // Increment inbound scanned counter atomically
            DB::table('tabel_inbound')->where('ID_inbound', $inbound->ID_inbound)->increment('total_qr_sudah_discan');
            $inbound->refresh();

            // Create InboundDetail mapping for this specific box
            InboundDetail::create([
                'ID_inbound' => $inbound->ID_inbound,
                'ID_barang' => $outboundDetail->ID_barang,
                'ID_outbound_detail' => $outboundDetail->ID_outbound_detail,
                'quantity_cv_detect' => null,
                'quantity_inbound' => null,
                'ada_cacat' => false,
            ]);

            if ($inbound->total_qr_sudah_discan >= $inbound->total_qr_expected) {
                $outbound->update(['status' => 'arrived']);
                $inbound->update(['status_scan' => 'menunggu']); // Ready for CV

                // Notify manager
                $managers = User::where('role', 'manager')->get();
                foreach ($managers as $manager) {
                    $this->notificationService->send(
                        $manager->ID_user,
                        'Barang Tiba',
                        'Barang dari vendor ' . $outbound->vendor->nama_vendor . ' telah tiba.',
                        'inbound',
                        $inbound->ID_inbound
                    );
                }

                return [
                    'completed' => true,
                    'inbound' => $inbound->load(['outbound', 'details']),
                    'message' => 'All QRs scanned. Shipment arrived. Proceed to photo scan.',
                ];
            }

            return [
                'completed' => false,
                'progress' => [
                    'scanned' => $inbound->total_qr_sudah_discan,
                    'total' => $inbound->total_qr_expected
                ],
                'message' => "{$inbound->total_qr_sudah_discan} of {$inbound->total_qr_expected} boxes scanned. Continue scanning.",
            ];
        });
    }
}
