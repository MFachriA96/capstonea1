<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DiscrepancyResource;
use App\Models\Discrepancy;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DiscrepancyController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Discrepancy::with([
            'outboundDetail.barang:ID_barang,nama_barang',
            'outboundDetail.outbound:ID_outbound,no_pengiriman,lokasi_asal,waktu_kirim,estimasi_tiba,ID_vendor',
            'outboundDetail.outbound.vendor:ID_vendor,nama_vendor',
            'inboundDetail',
            'latestAction',
            'dokumenR1',
        ])->select([
            'ID_discrepancy',
            'ID_outbound_detail',
            'ID_inbound_detail',
            'quantity_outbound',
            'quantity_inbound',
            'selisih',
            'status',
            'keterangan',
            'detected_at',
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

        $cacheKey = $this->buildIndexCacheKey($request);
        $payload = Cache::remember($cacheKey, now()->addSeconds(60), function () use ($query) {
            return DiscrepancyResource::collection($query->paginate(15))->response()->getData(true);
        });

        return $this->success($payload);
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
        if ((string) $request->query('warehouse_scope') === 'all') {
            return null;
        }

        if ($request->filled('ID_gudang')) {
            return $request->integer('ID_gudang');
        }

        if ($request->user()->role === 'manager' && $request->user()->ID_gudang) {
            return (int) $request->user()->ID_gudang;
        }

        return null;
    }

    protected function buildIndexCacheKey(Request $request): string
    {
        return implode(':', [
            'discrepancy',
            'index',
            $request->user()->role,
            $request->user()->ID_user,
            $request->user()->ID_vendor ?? 'none',
            $request->query('status', 'all'),
            $request->query('pending_review', 'all'),
            $request->query('warehouse_scope', 'default'),
            $request->query('ID_gudang', 'none'),
            $request->query('page', 1),
        ]);
    }
}
