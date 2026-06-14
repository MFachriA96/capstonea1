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
use Illuminate\Support\Str;

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
        $query = Outbound::with(['vendor', 'pembuatOutbound', 'gudangTujuan'])
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

        $this->applyWarehouseScope($query, $request);

        if ($request->filled('status_bucket')) {
            $this->applyStatusBucketFilter($query, (string) $request->query('status_bucket'));
        }

        if ($request->has('has_discrepancy')) {
            $this->applyHasDiscrepancyFilter($query, $request->query('has_discrepancy'));
        }

        $query->orderByDesc('created_at')
            ->orderByDesc('ID_outbound');

        return $this->success(OutboundResource::collection($query->paginate(15))->response()->getData(true));
    }

    public function store(OutboundRequest $request)
    {
        $outbound = $this->outboundService->createOutbound($request->validated(), $request->user());

        if ($request->boolean('submit_now')) {
            $outbound = $this->outboundService->submitOutbound($outbound->ID_outbound, $request->user());

            return $this->success(
                $this->buildOutboundWithQrPayload($request, $outbound),
                'Outbound created and submitted successfully',
                201
            );
        }

        return $this->success(new OutboundResource($outbound), 'Outbound created successfully', 201);
    }

    public function show(Request $request, string $id)
    {
        $outbound = Outbound::with(['vendor', 'pembuatOutbound', 'gudangTujuan', 'details.barang'])->findOrFail($id);

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
        return $this->success($this->buildOutboundWithQrPayload($request, $outbound), 'Outbound submitted successfully');
    }

    public function getQrToken(Request $request, string $id)
    {
        $outbound = Outbound::with('details')->findOrFail($id);

        if ($request->user()->role === 'vendor' && $outbound->ID_vendor !== $request->user()->ID_vendor) {
            abort(403, 'Unauthorized');
        }

        // Backfill missing QR tokens for previously submitted records so older data
        // can still be used in the current QR-per-box flow.
        if ($outbound->status !== 'draft') {
            foreach ($outbound->details as $detail) {
                if (empty($detail->qr_token)) {
                    $detail->update([
                        'qr_token' => Str::uuid()->toString(),
                    ]);
                }
            }

            $outbound->load('details');
        }

        return $this->success($this->buildQrTokenPayload($outbound));
    }

    protected function buildOutboundWithQrPayload(Request $request, Outbound $outbound): array
    {
        $outbound->loadMissing(['vendor', 'pembuatOutbound', 'gudangTujuan', 'details.barang']);

        return array_merge(
            (new OutboundResource($outbound))->resolve($request),
            $this->buildQrTokenPayload($outbound)
        );
    }

    protected function buildQrTokenPayload(Outbound $outbound): array
    {
        $outbound->loadMissing('details.barang');

        $qrTokens = $outbound->details->map(function ($detail) {
            return [
                'ID_outbound_detail' => $detail->ID_outbound_detail,
                'ID_barang' => $detail->ID_barang,
                'nama_barang' => $detail->barang?->nama_barang,
                'qr_token' => $detail->qr_token,
            ];
        });

        $totalQr = $qrTokens->count();
        $readyQr = $qrTokens->filter(fn (array $detail) => !empty($detail['qr_token']))->count();

        return [
            'shipment_status' => $outbound->status,
            'qr_ready' => $outbound->status !== 'draft' && $totalQr > 0 && $readyQr === $totalQr,
            'total_qr' => $totalQr,
            'ready_qr' => $readyQr,
            'qr_tokens' => $qrTokens,
        ];
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

    protected function applyWarehouseScope(Builder $query, Request $request): void
    {
        if ($request->query('warehouse_scope') === 'all') {
            return;
        }

        if ($request->filled('ID_gudang')) {
            $query->where('ID_gudang_tujuan', $request->integer('ID_gudang'));

            return;
        }

        $userWarehouseId = $request->user()->ID_gudang ?? null;

        if ($request->user()->role === 'petugas' && $userWarehouseId) {
            $query->where('ID_gudang_tujuan', $userWarehouseId);
        }
    }
}
