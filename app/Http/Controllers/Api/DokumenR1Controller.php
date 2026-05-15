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
        $dokumen->update(['status_dokumen' => $request->status_dokumen]);

        $vendorId = $dokumen->discrepancy->outboundDetail->outbound->ID_vendor;
        if ($vendorId && $request->status_dokumen === 'dikirim_ke_vendor') {
            $vendors = \App\Models\User::where('role', 'vendor')->where('ID_vendor', $vendorId)->get();
            foreach ($vendors as $vendorUser) {
                $this->notificationService->send(
                    $vendorUser->ID_user,
                    'Dokumen R1 Baru',
                    'Status R1 dokumen ' . $dokumen->no_dokumen_r1 . ' diperbarui menjadi ' . $request->status_dokumen,
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
