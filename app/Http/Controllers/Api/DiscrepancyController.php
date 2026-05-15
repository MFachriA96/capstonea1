<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DiscrepancyResource;
use App\Models\Discrepancy;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class DiscrepancyController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Discrepancy::with([
            'outboundDetail.barang',
            'outboundDetail.outbound.vendor',
            'inboundDetail.auditPhotos',
            'actions',
            'dokumenR1',
        ]);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->user()->role === 'vendor') {
            $query->whereHas('outboundDetail.outbound', function ($q) use ($request) {
                $q->where('ID_vendor', $request->user()->ID_vendor);
            });
        }

        return $this->success(DiscrepancyResource::collection($query->paginate(15))->response()->getData(true));
    }

    public function show(Request $request, string $id)
    {
        $discrepancy = Discrepancy::with(['outboundDetail.barang', 'outboundDetail.outbound', 'inboundDetail.auditPhotos', 'actions', 'dokumenR1'])->findOrFail($id);

        if ($request->user()->role === 'vendor' && $discrepancy->outboundDetail->outbound->ID_vendor !== $request->user()->ID_vendor) {
            abort(403, 'Unauthorized');
        }

        return $this->success(new DiscrepancyResource($discrepancy));
    }
}
