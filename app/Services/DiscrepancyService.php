<?php

namespace App\Services;

use App\Models\Discrepancy;
use App\Models\Inbound;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DiscrepancyService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function generateDiscrepancies(int $inboundId): void
    {
        DB::transaction(function () use ($inboundId) {
            $inbound = Inbound::with(['details.outboundDetail', 'outbound'])->findOrFail($inboundId);

            foreach ($inbound->details as $inboundDetail) {
                $outboundDetail = $inboundDetail->outboundDetail;

                if (!$outboundDetail) {
                    continue;
                }

                $quantityInbound = $inboundDetail->quantity_inbound !== null
                    ? $inboundDetail->quantity_inbound
                    : ($inboundDetail->quantity_cv_detect ?? 0);
                $quantityOutbound = $outboundDetail->quantity_outbound;

                $selisih = $quantityInbound - $quantityOutbound;

                if ($selisih === 0) {
                    $status = 'match';
                } elseif ($quantityInbound === 0) {
                    $status = 'missing';
                } elseif ($selisih > 0) {
                    $status = 'over';
                } else {
                    $status = 'mismatch';
                }

                Discrepancy::updateOrCreate(
                    [
                        'ID_outbound_detail' => $outboundDetail->ID_outbound_detail,
                        'ID_inbound_detail' => $inboundDetail->ID_inbound_detail,
                    ],
                    [
                        'quantity_outbound' => $quantityOutbound,
                        'quantity_inbound' => $quantityInbound,
                        'selisih' => $selisih,
                        'status' => $status,
                        'detected_at' => now(),
                    ]
                );
            }

            $inbound->outbound->update(['status' => 'verified']);

            // Notify manager and vendor
            $managers = User::where('role', 'manager')->get();
            foreach ($managers as $manager) {
                $this->notificationService->send(
                    $manager->ID_user,
                    'Discrepancy Generated',
                    'Discrepancy report untuk pengiriman ' . $inbound->outbound->no_pengiriman . ' telah dibuat.',
                    'outbound',
                    $inbound->outbound->ID_outbound
                );
            }

            if ($inbound->outbound->ID_vendor) {
                $vendors = User::where('role', 'vendor')->where('ID_vendor', $inbound->outbound->ID_vendor)->get();
                foreach ($vendors as $vendorUser) {
                    $this->notificationService->send(
                        $vendorUser->ID_user,
                        'Discrepancy Verification Completed',
                        'Verifikasi untuk pengiriman ' . $inbound->outbound->no_pengiriman . ' telah selesai. Silakan cek detail discrepancy (jika ada).',
                        'outbound',
                        $inbound->outbound->ID_outbound
                    );
                }
            }
        });
    }
}
