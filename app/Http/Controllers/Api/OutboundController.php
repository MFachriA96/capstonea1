<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OutboundRequest;
use App\Http\Resources\OutboundResource;
use App\Models\Outbound;
use App\Services\OutboundService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OutboundController extends Controller
{
    use ApiResponse;

    protected $outboundService;

    public function __construct(OutboundService $outboundService)
    {
        $this->outboundService = $outboundService;
    }

    public function index(Request $request)
    {
        $query = Outbound::query()
            ->select([
                'ID_outbound',
                'no_pengiriman',
                'ID_vendor',
                'waktu_kirim',
                'estimasi_tiba',
                'lokasi_asal',
                'status',
                'dibuat_oleh',
                'created_at',
            ])
            ->with(['vendor'])
            ->withCount([
                'details as total_qr',
                'details as ready_qr' => fn (Builder $detailQuery) => $detailQuery->whereNotNull('qr_token'),
            ])
            ->withExists([
                'details as has_discrepancy' => fn (Builder $detailQuery) => $detailQuery->whereHas(
                    'discrepancies',
                    fn (Builder $discrepancyQuery) => $discrepancyQuery->where('status', '!=', 'match')
                ),
            ]);

        if ($request->user()->role === 'vendor') {
            $query->where('ID_vendor', $request->user()->ID_vendor);
        }

        if (($warehouseId = $this->resolveEffectiveWarehouseId($request)) !== null) {
            $query->where('ID_gudang_tujuan', $warehouseId);
        }

        if ($request->filled('status_bucket')) {
            $this->applyStatusBucketFilter($query, (string) $request->query('status_bucket'));
        }

        if ($request->has('has_discrepancy')) {
            $this->applyHasDiscrepancyFilter($query, $request->query('has_discrepancy'));
        }

        $cacheKey = $this->buildIndexCacheKey($request);
        $payload = Cache::remember($cacheKey, now()->addSeconds(60), function () use ($query) {
            return OutboundResource::collection($query->paginate(15))->response()->getData(true);
        });

        return $this->success($payload);
    }

    public function store(OutboundRequest $request)
    {
        $outbound = $this->outboundService->createOutbound($request->validated(), $request->user());
        return $this->success(new OutboundResource($outbound), 'Outbound created successfully', 201);
    }

    public function show(Request $request, string $id)
    {
        $outbound = Outbound::with(['vendor', 'pembuatOutbound', 'details.barang'])->findOrFail($id);

        if ($request->user()->role === 'vendor' && $outbound->ID_vendor !== $request->user()->ID_vendor) {
            abort(403, 'Unauthorized');
        }

        return $this->success(new OutboundResource($outbound));
    }

    public function update(OutboundRequest $request, string $id)
    {
        $outbound = Outbound::findOrFail($id);

        if ($outbound->status !== 'draft') {
            return $this->error('Cannot modify a submitted outbound', 403);
        }

        if ($request->user()->role === 'vendor' && $outbound->ID_vendor !== $request->user()->ID_vendor) {
            abort(403, 'Unauthorized');
        }

        $outbound = $this->outboundService->updateOutbound($outbound, $request->validated(), $request->user());

        return $this->success(new OutboundResource($outbound), 'Outbound updated successfully');
    }

    public function destroy(Request $request, string $id)
    {
        $outbound = Outbound::findOrFail($id);

        if ($outbound->status !== 'draft') {
            return $this->error('Cannot modify a submitted outbound', 403);
        }

        if ($request->user()->role === 'vendor' && $outbound->ID_vendor !== $request->user()->ID_vendor) {
            abort(403, 'Unauthorized');
        }

        $outbound->delete();
        return $this->success(null, 'Outbound deleted successfully');
    }

    public function submit(Request $request, string $id)
    {
        $outbound = $this->outboundService->submitOutbound($id, $request->user());
        return $this->success(new OutboundResource($outbound), 'Outbound submitted successfully');
    }

    public function getQrToken(Request $request, string $id)
    {
        $outbound = Outbound::with('details.boxes', 'details.barang')->findOrFail($id);

        if ($request->user()->role === 'vendor' && $outbound->ID_vendor !== $request->user()->ID_vendor) {
            abort(403, 'Unauthorized');
        }

        if ($outbound->status !== 'draft') {
            $outbound = $this->outboundService->ensureBoxesGenerated($outbound);
        }

        $qrTokens = $outbound->details
            ->flatMap(fn ($detail) => $detail->boxes->map(fn ($box) => [
                'ID_outbound_box' => $box->ID_outbound_box,
                'ID_outbound_detail' => $box->ID_outbound_detail,
                'ID_barang' => $detail->ID_barang,
                'nama_barang' => $detail->barang?->nama_barang,
                'box_sequence' => $box->box_sequence,
                'box_code' => $box->box_code,
                'expected_qty_in_box' => $box->expected_qty_in_box,
                'qr_token' => $box->qr_token,
            ]))
            ->values();

        $totalQr = $qrTokens->count();
        $readyQr = $qrTokens->filter(fn (array $box) => !empty($box['qr_token']))->count();

        return $this->success([
            'shipment_status' => $outbound->status,
            'qr_ready' => $outbound->status !== 'draft' && $totalQr > 0 && $readyQr === $totalQr,
            'total_qr' => $totalQr,
            'ready_qr' => $readyQr,
            'qr_tokens' => $qrTokens,
        ]);
    }

    protected function applyStatusBucketFilter(Builder $query, string $statusBucket): void
    {
        match ($statusBucket) {
            'draft' => $query->where('status', 'draft'),
            'shipping' => $query->whereIn('status', ['submitted', 'in_transit']),
            'delivered' => $query->whereIn('status', ['arrived', 'verified']),
            default => null,
        };
    }

    protected function applyHasDiscrepancyFilter(Builder $query, mixed $rawValue): void
    {
        $hasDiscrepancy = filter_var($rawValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($hasDiscrepancy === null) {
            return;
        }

        $constraint = fn (Builder $discrepancyQuery) => $discrepancyQuery->where('status', '!=', 'match');

        if ($hasDiscrepancy) {
            $query->whereHas('details.discrepancies', $constraint);

            return;
        }

        $query->whereDoesntHave('details.discrepancies', $constraint);
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
            'outbound',
            'index',
            $request->user()->role,
            $request->user()->ID_user,
            $request->user()->ID_vendor ?? 'none',
            $request->query('status_bucket', 'all'),
            $request->query('has_discrepancy', 'all'),
            $request->query('warehouse_scope', 'default'),
            $request->query('ID_gudang', 'none'),
            $request->query('page', 1),
        ]);
    }
}
