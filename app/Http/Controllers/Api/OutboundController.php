<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OutboundRequest;
use App\Http\Resources\OutboundResource;
use App\Models\Outbound;
use App\Services\OutboundService;
use App\Traits\ApiResponse;
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
        $query = Outbound::with(['vendor', 'pembuatOutbound']);

        if ($request->user()->role === 'vendor') {
            $query->where('ID_vendor', $request->user()->ID_vendor);
        }

        return $this->success(OutboundResource::collection($query->paginate(15))->response()->getData(true));
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

        $qrTokens = $outbound->details->map(function ($detail) {
            return [
                'ID_outbound_detail' => $detail->ID_outbound_detail,
                'ID_barang' => $detail->ID_barang,
                'qr_token' => $detail->qr_token,
            ];
        });

        return $this->success(['qr_tokens' => $qrTokens]);
    }
}
