<?php

namespace App\Services;

use App\Models\Inbound;
use App\Models\InboundDetail;
use App\Models\Outbound;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InboundService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function createInboundFromQr(string $qr_token, array $data, User $officer): Inbound
    {
        return DB::transaction(function () use ($qr_token, $data, $officer) {
            $outboundDetail = OutboundDetail::where('qr_token', $qr_token)->with('outbound.details.barang')->firstOrFail();
            $outbound = $outboundDetail->outbound;

            if (!in_array($outbound->status, ['submitted', 'in_transit'])) {
                abort(400, 'Outbound is already arrived or not submitted.');
            }

            $totalBoxExpected = $outbound->details->sum('jumlah_box');

            $inbound = Inbound::create([
                'ID_outbound' => $outbound->ID_outbound,
                'ID_gudang' => $data['ID_gudang'],
                'ID_vendor' => $outbound->ID_vendor,
                'timestamp_terima' => now(),
                'nama_penerima' => $data['nama_penerima'],
                'diterima_oleh' => $officer->ID_user,
                'qr_scan_result' => $qr_token,
                'lokasi_terakhir' => $data['lokasi_terakhir'] ?? null,
                'total_box_expected' => $totalBoxExpected,
                'total_box_sudah_discan' => 0,
                'status_scan' => 'menunggu',
            ]);

            foreach ($outbound->details as $detail) {
                InboundDetail::create([
                    'ID_inbound' => $inbound->ID_inbound,
                    'ID_barang' => $detail->ID_barang,
                    'quantity_cv_detect' => null,
                    'quantity_inbound' => null,
                    'ada_cacat' => false,
                ]);
            }

            $outbound->update(['status' => 'arrived']);

            // Notify supervisor
            $supervisors = User::where('role', 'supervisor')->get();
            foreach ($supervisors as $supervisor) {
                $this->notificationService->send(
                    $supervisor->ID_user,
                    'Barang Tiba',
                    'Barang dari vendor ' . $outbound->vendor->nama_vendor . ' telah tiba.',
                    'inbound',
                    $inbound->ID_inbound
                );
            }

            return $inbound->load(['outbound', 'details']);
        });
    }
}
