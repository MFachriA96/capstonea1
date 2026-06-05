<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DokumenR1Request;
use App\Http\Resources\DokumenR1Resource;
use App\Models\DokumenR1;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class DokumenR1Controller extends Controller
{
    use ApiResponse;

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        $query = DokumenR1::with([
            'discrepancy.outboundDetail.barang',
            'discrepancy.outboundDetail.outbound.vendor',
            'pembuat',
        ]);

        if ($request->user()->role === 'vendor') {
            $query->whereHas('discrepancy.outboundDetail.outbound', function ($q) use ($request) {
                $q->where('ID_vendor', $request->user()->ID_vendor);
            });
        }

        return $this->success(DokumenR1Resource::collection($query->orderByDesc('ID_dokumen')->paginate(15))->response()->getData(true));
    }

    public function store(DokumenR1Request $request)
    {
        // Simple logic for R1 document creation
        $noDokumen = 'R1-' . date('Ymd') . '-' . str_pad(DokumenR1::count() + 1, 4, '0', STR_PAD_LEFT);

        $dokumen = DokumenR1::create([
            'ID_discrepancy' => $request->ID_discrepancy,
            'no_dokumen_r1' => $noDokumen,
            'dibuat_oleh' => $request->user()->ID_user,
            'keterangan' => $request->keterangan,
        ]);

        return $this->success(new DokumenR1Resource($dokumen->load([
            'discrepancy.outboundDetail.barang',
            'discrepancy.outboundDetail.outbound.vendor',
            'pembuat',
        ])), 'R1 Document created', 201);
    }

    public function show(Request $request, string $id)
    {
        $dokumen = DokumenR1::with([
            'discrepancy.outboundDetail.barang',
            'discrepancy.outboundDetail.outbound.vendor',
            'pembuat',
        ])->findOrFail($id);

        if ($request->user()->role === 'vendor' && $dokumen->discrepancy->outboundDetail->outbound->ID_vendor !== $request->user()->ID_vendor) {
            abort(403, 'Unauthorized');
        }

        return $this->success(new DokumenR1Resource($dokumen));
    }

    public function updateStatus(Request $request, string $id)
    {
        $request->validate([
            'status_dokumen' => 'required|in:draft,dikirim_ke_vendor,diproses_vendor,closing',
        ]);

        $dokumen = DokumenR1::with('discrepancy.outboundDetail.outbound')->findOrFail($id);
        $user = $request->user();
        $nextStatus = $request->status_dokumen;
        $outbound = $dokumen->discrepancy->outboundDetail->outbound;
        $vendorId = $outbound->ID_vendor;

        if ($user->role === 'vendor') {
            if ((int) $user->ID_vendor !== (int) $vendorId) {
                abort(403, 'Unauthorized');
            }

            if ($nextStatus !== 'diproses_vendor') {
                return $this->error('Vendor can only mark the document as diproses_vendor', 403);
            }
        }

        $dokumen->update(['status_dokumen' => $nextStatus]);

        if ($vendorId && in_array($nextStatus, ['dikirim_ke_vendor', 'closing'], true)) {
            $vendors = \App\Models\User::where('role', 'vendor')->where('ID_vendor', $vendorId)->get();
            foreach ($vendors as $vendorUser) {
                $title = $nextStatus === 'closing' ? 'Dokumen R1 Ditutup' : 'Dokumen R1 Baru';
                $message = $nextStatus === 'closing'
                    ? 'Dokumen ' . $dokumen->no_dokumen_r1 . ' telah ditutup oleh manager.'
                    : 'Status R1 dokumen ' . $dokumen->no_dokumen_r1 . ' diperbarui menjadi ' . $nextStatus;
                $this->notificationService->send(
                    $vendorUser->ID_user,
                    $title,
                    $message,
                    'dokumen_r1',
                    $dokumen->ID_dokumen
                );
            }
        }

        if ($nextStatus === 'diproses_vendor') {
            $managers = \App\Models\User::whereIn('role', ['manager', 'admin'])->get();
            foreach ($managers as $managerUser) {
                $this->notificationService->send(
                    $managerUser->ID_user,
                    'Vendor menindaklanjuti dokumen R1',
                    'Dokumen ' . $dokumen->no_dokumen_r1 . ' sedang diproses oleh vendor.',
                    'dokumen_r1',
                    $dokumen->ID_dokumen
                );
            }
        }

        return $this->success(new DokumenR1Resource($dokumen->load([
            'discrepancy.outboundDetail.barang',
            'discrepancy.outboundDetail.outbound.vendor',
            'pembuat',
        ])), 'R1 Document status updated');
    }
}
