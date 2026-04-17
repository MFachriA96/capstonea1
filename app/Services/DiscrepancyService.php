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
            $inbound = Inbound::with(['details', 'outbound.details'])->findOrFail($inboundId);

            foreach ($inbound->details as $inboundDetail) {
                // Find matching outbound detail
                $outboundDetail = $inbound->outbound->details->firstWhere('ID_barang', $inboundDetail->ID_barang);

                if (!$outboundDetail) {
                    continue; // Skip if no matching outbound detail (shouldn't happen in normal flow)
                }

                $quantityInbound = $inboundDetail->quantity_inbound !== null ? $inboundDetail->quantity_inbound : $inboundDetail->quantity_cv_detect;
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

                Discrepancy::create([
                    'ID_outbound_detail' => $outboundDetail->ID_outbound_detail,
                    'ID_inbound_detail' => $inboundDetail->ID_inbound_detail,
                    'quantity_outbound' => $quantityOutbound,
                    'quantity_inbound' => $quantityInbound,
                    'selisih' => $selisih,
                    'status' => $status,
                ]);
            }

            $inbound->outbound->update(['status' => 'verified']);

            // Notify supervisor and vendor
            $supervisors = User::where('role', 'supervisor')->get();
            foreach ($supervisors as $supervisor) {
                $this->notificationService->send(
                    $supervisor->ID_user,
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
