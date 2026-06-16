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
        $relations = [
            'outboundDetail.barang',
            'outboundDetail.outbound.vendor',
            'inboundDetail',
            'latestAction',
            'dokumenR1',
        ];

        if ($request->boolean('include_photos')) {
            $relations[2] = 'inboundDetail.auditPhotos';
        }

        $query = Discrepancy::with($relations);

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

        if ($request->filled('ID_gudang') && $request->query('warehouse_scope') !== 'all') {
            $warehouseId = $request->integer('ID_gudang');

            $query->where(function (Builder $warehouseQuery) use ($warehouseId) {
                $warehouseQuery->whereHas('inboundDetail.inbound', function (Builder $inboundQuery) use ($warehouseId) {
                    $inboundQuery->where('ID_gudang', $warehouseId);
                })->orWhereHas('outboundDetail.outbound', function (Builder $outboundQuery) use ($warehouseId) {
                    $outboundQuery->where('ID_gudang_tujuan', $warehouseId);
                });
            });
        }

        $query->orderByDesc('detected_at')
            ->orderByDesc('ID_discrepancy');

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
}
