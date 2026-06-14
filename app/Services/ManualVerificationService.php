<?php

namespace App\Services;

use App\Models\Foto;
use App\Models\Inbound;
use App\Models\InboundDetail;
use App\Models\ScanSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ManualVerificationService
{
    public function __construct(protected DiscrepancyService $discrepancyService)
    {
    }

    public function updateDetail(int $inboundId, int $detailId, array $data): InboundDetail
    {
        return DB::transaction(function () use ($inboundId, $detailId, $data) {
            $inbound = Inbound::findOrFail($inboundId);
            $this->ensureManualVerificationCanContinue($inbound);

            $detail = InboundDetail::where('ID_inbound', $inboundId)->findOrFail($detailId);

            $detail->update([
                'quantity_inbound' => $data['quantity_inbound'],
                'ada_cacat' => $data['ada_cacat'] ?? $detail->ada_cacat,
                'catatan_cacat' => $data['catatan_cacat'] ?? $detail->catatan_cacat,
            ]);

            return $detail->refresh();
        });
    }

    public function uploadConditionPhoto(int $inboundId, int $detailId, UploadedFile $file, User $user): Foto
    {
        return DB::transaction(function () use ($inboundId, $detailId, $file, $user) {
            $inbound = Inbound::findOrFail($inboundId);
            $this->ensureManualVerificationCanContinue($inbound);

            $detail = InboundDetail::where('ID_inbound', $inboundId)->findOrFail($detailId);
            $session = $this->getOrCreateManualPhotoSession($detail, $user);

            $disk = config('filesystems.manual_verification_disk', 'public');
            $path = $file->store("manual-verification/{$inboundId}", $disk);

            return Foto::create([
                'ID_session' => $session->ID_session,
                'ID_inbound' => $inboundId,
                'file_url' => Storage::disk($disk)->url($path),
                'uploaded_by' => $user->ID_user,
                'related_type' => 'manual_condition',
            ]);
        });
    }

    public function finalize(int $inboundId): Inbound
    {
        return DB::transaction(function () use ($inboundId) {
            $inbound = Inbound::with('details.outboundDetail')->findOrFail($inboundId);

            if ($inbound->status_scan === 'selesai') {
                return $inbound->load(['outbound', 'details.barang', 'details.auditPhotos']);
            }

            if ($inbound->total_qr_sudah_discan < $inbound->total_qr_expected) {
                abort(400, 'Cannot finalize manual verification until all QRs are scanned.');
            }

            $missingManualInput = $inbound->details
                ->filter(fn (InboundDetail $detail) => $detail->quantity_inbound === null)
                ->values();

            if ($missingManualInput->isNotEmpty()) {
                abort(400, 'All inbound details must have manual quantity input before finalizing.');
            }

            $inbound->update([
                'total_box_sudah_discan' => $inbound->total_box_expected,
                'status_scan' => 'selesai',
            ]);

            $this->discrepancyService->generateDiscrepancies($inbound->ID_inbound);

            return $inbound->refresh()->load(['outbound', 'details.barang', 'details.auditPhotos']);
        });
    }

    protected function ensureManualVerificationCanContinue(Inbound $inbound): void
    {
        if ($inbound->status_scan === 'selesai') {
            abort(400, 'Manual verification has already been finalized for this inbound.');
        }

        if ($inbound->total_qr_sudah_discan < $inbound->total_qr_expected) {
            abort(400, 'Manual verification can only start after all QRs are scanned.');
        }
    }

    protected function getOrCreateManualPhotoSession(InboundDetail $detail, User $user): ScanSession
    {
        $session = ScanSession::where('ID_inbound', $detail->ID_inbound)
            ->where('ID_outbound_detail', $detail->ID_outbound_detail)
            ->first();

        if ($session) {
            return $session;
        }

        $nextOrder = ScanSession::where('ID_inbound', $detail->ID_inbound)->max('urutan_scan') + 1;

        return ScanSession::create([
            'ID_inbound' => $detail->ID_inbound,
            'ID_barang' => $detail->ID_barang,
            'ID_outbound_detail' => $detail->ID_outbound_detail,
            'urutan_scan' => $nextOrder,
            'waktu_mulai' => now(),
            'waktu_selesai' => now(),
            'status_sesi' => 'selesai',
            'ID_user' => $user->ID_user,
        ]);
    }
}
