<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DiscrepancyResource;
use App\Models\Discrepancy;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DiscrepancyController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Discrepancy::with([
            'outboundDetail.barang',
            'outboundDetail.outbound.vendor',
            'inboundDetail',
            'latestAction',
            'dokumenR1',
        ]);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('pending_review')) {
            $this->applyPendingReviewFilter($query, $request->query('pending_review'));
        }

        if ($request->user()->role === 'vendor') {
            $query->whereHas('outboundDetail.outbound', function ($q) use ($request) {
                $q->where('ID_vendor', $request->user()->ID_vendor);
            });
        }

        if (($warehouseId = $this->resolveEffectiveWarehouseId($request)) !== null) {
            $query->whereHas('outboundDetail.outbound', function ($q) use ($warehouseId) {
                $q->where('ID_gudang_tujuan', $warehouseId);
            });
        }

        return $this->success(DiscrepancyResource::collection($query->paginate(15))->response()->getData(true));
    }

    public function show(Request $request, string $id)
    {
        $discrepancy = Discrepancy::with([
            'outboundDetail.barang',
            'outboundDetail.outbound.vendor',
            'inboundDetail.auditPhotos',
            'latestAction',
            'dokumenR1',
        ])->findOrFail($id);

        if ($request->user()->role === 'vendor' && $discrepancy->outboundDetail->outbound->ID_vendor !== $request->user()->ID_vendor) {
            abort(403, 'Unauthorized');
        }

        return $this->success(new DiscrepancyResource($discrepancy));
    }

    protected function applyPendingReviewFilter(Builder $query, mixed $rawValue): void
    {
        $pendingReview = filter_var($rawValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($pendingReview === null) {
            return;
        }

        $query->where('status', '!=', 'match');

        if ($pendingReview) {
            $query->where(function (Builder $pendingQuery) {
                $pendingQuery->whereDoesntHave('actions')
                    ->orWhereHas('actions', fn (Builder $actionQuery) => $actionQuery->where('status_action', 'pending'));
            });

            return;
        }

        $query->whereHas('actions', fn (Builder $actionQuery) => $actionQuery->whereIn('status_action', ['done', 'cancelled']));
    }

    protected function resolveEffectiveWarehouseId(Request $request): ?int
    {
        if ($request->filled('ID_gudang')) {
            return $request->integer('ID_gudang');
        }

        if ($request->user()->role === 'manager' && $request->user()->ID_gudang) {
            return (int) $request->user()->ID_gudang;
        }

        return null;
    }
}
