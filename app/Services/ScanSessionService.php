<?php

namespace App\Services;

use App\Models\CvResult;
use App\Models\Foto;
use App\Models\Inbound;
use App\Models\InboundDetail;
use App\Models\ScanSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ScanSessionService
{
    protected $cvService;
    protected $discrepancyService;

    public function __construct(CVService $cvService, DiscrepancyService $discrepancyService)
    {
        $this->cvService = $cvService;
        $this->discrepancyService = $discrepancyService;
    }

    public function startSession(int $inboundId, int $barangId, User $user): ScanSession
    {
        return DB::transaction(function () use ($inboundId, $barangId, $user) {
            $inbound = Inbound::findOrFail($inboundId);
            
            $existingSessionsCount = ScanSession::where('ID_inbound', $inboundId)->count();
            $urutanScan = $existingSessionsCount + 1;

            $session = ScanSession::create([
                'ID_inbound' => $inboundId,
                'ID_barang' => $barangId,
                'urutan_scan' => $urutanScan,
                'waktu_mulai' => now(),
                'status_sesi' => 'berlangsung',
                'ID_user' => $user->ID_user,
            ]);

            $inbound->update(['status_scan' => 'sedang_diproses']);

            return $session;
        });
    }

    public function uploadFoto(int $sessionId, UploadedFile $file, User $user): array
    {
        return DB::transaction(function () use ($sessionId, $file, $user) {
            $session = ScanSession::with('inbound')->findOrFail($sessionId);

            // Store to Supabase Storage
            $path = $file->store('fotos', 's3');
            // Assuming S3 driver returns the path, and we can get URL like this:
            $fileUrl = Storage::disk('s3')->url($path);

            $foto = Foto::create([
                'ID_session' => $session->ID_session,
                'ID_inbound' => $session->ID_inbound,
                'file_url' => $fileUrl,
                'uploaded_by' => $user->ID_user,
                'related_type' => 'scan_session',
            ]);

            $cvData = $this->cvService->processPhoto($fileUrl);

            $cvResult = CvResult::create([
                'ID_foto' => $foto->ID_foto,
                'ID_session' => $session->ID_session,
                'jumlah_terdeteksi' => $cvData['jumlah_terdeteksi'],
                'cacat_terdeteksi' => $cvData['cacat_terdeteksi'],
                'confidence_score' => $cvData['confidence_score'],
                'model_version' => $cvData['model_version'],
            ]);

            $inboundDetail = InboundDetail::where('ID_inbound', $session->ID_inbound)
                ->where('ID_barang', $session->ID_barang)
                ->first();

            if ($inboundDetail) {
                $inboundDetail->quantity_cv_detect = ($inboundDetail->quantity_cv_detect ?? 0) + ($cvData['jumlah_terdeteksi'] ?? 0);
                if ($cvData['cacat_terdeteksi']) {
                    $inboundDetail->ada_cacat = true;
                }
                $inboundDetail->save();
            }

            return ['foto' => $foto, 'cv_result' => $cvResult];
        });
    }

    public function completeSession(int $sessionId): Inbound
    {
        return DB::transaction(function () use ($sessionId) {
            $session = ScanSession::findOrFail($sessionId);
            $session->update([
                'status_sesi' => 'selesai',
                'waktu_selesai' => now(),
            ]);

            $inbound = Inbound::findOrFail($session->ID_inbound);
            DB::table('tabel_inbound')->where('ID_inbound', $inbound->ID_inbound)->increment('total_box_sudah_discan');
            
            $inbound->refresh(); // Refresh to get the updated incremented value

            if ($inbound->total_box_sudah_discan >= $inbound->total_box_expected) {
                $inbound->update(['status_scan' => 'selesai']);
                $this->discrepancyService->generateDiscrepancies($inbound->ID_inbound);
            }

            return $inbound;
        });
    }

    public function updateInboundDetail(int $sessionId, int $barangId, int $quantityInbound): InboundDetail
    {
        $session = ScanSession::findOrFail($sessionId);
        $inbound = Inbound::findOrFail($session->ID_inbound);

        if ($inbound->status_scan === 'selesai') {
            abort(400, 'Cannot update details for a completed inbound.');
        }

        $detail = InboundDetail::where('ID_inbound', $inbound->ID_inbound)
            ->where('ID_barang', $barangId)
            ->firstOrFail();

        $detail->update(['quantity_inbound' => $quantityInbound]);

        return $detail;
    }
}
