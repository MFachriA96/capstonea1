<?php

namespace App\Services;

use App\Models\Inbound;
use App\Models\User;

class InboundService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function createInboundFromQr(string $qr_token, array $data, User $officer): array
    {
        $result = app(ReceivingService::class)->scanBox($qr_token, $data, $officer);

        $inbound = Inbound::with(['outbound', 'details'])->findOrFail($result['inbound']['ID_inbound']);

        $completed = (int) $inbound->total_qr_sudah_discan >= (int) $inbound->total_qr_expected;

        if ($completed) {
            return [
                'completed' => true,
                'inbound' => $inbound,
                'message' => 'All QRs scanned. Shipment arrived. Proceed to manual verification.',
            ];
        }

        return [
            'completed' => false,
            'progress' => [
                'scanned' => $inbound->total_qr_sudah_discan,
                'total' => $inbound->total_qr_expected,
            ],
            'message' => "{$inbound->total_qr_sudah_discan} of {$inbound->total_qr_expected} boxes scanned. Continue scanning.",
        ];
    }
}
