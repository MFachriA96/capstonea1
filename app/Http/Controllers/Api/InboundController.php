<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InboundRequest;
use App\Http\Resources\InboundResource;
use App\Models\Inbound;
use App\Services\InboundService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class InboundController extends Controller
{
    use ApiResponse;

    protected $inboundService;

    public function __construct(InboundService $inboundService)
    {
        $this->inboundService = $inboundService;
    }

    public function index(Request $request)
    {
        $query = Inbound::with(['gudang', 'vendor', 'penerima']);

        if ($request->user()->role === 'vendor') {
            $query->where('ID_vendor', $request->user()->ID_vendor);
        }

        return $this->success(InboundResource::collection($query->paginate(15))->response()->getData(true));
    }

    public function scanQr(InboundRequest $request)
    {
        $inbound = $this->inboundService->createInboundFromQr($request->qr_token, $request->validated(), $request->user());
        return $this->success(new InboundResource($inbound), 'Inbound created from QR successfully', 201);
    }

    public function show(Request $request, string $id)
    {
        $inbound = Inbound::with(['outbound', 'gudang', 'vendor', 'penerima', 'details.barang', 'scanSessions'])->findOrFail($id);

        if ($request->user()->role === 'vendor' && $inbound->ID_vendor !== $request->user()->ID_vendor) {
            abort(403, 'Unauthorized');
        }

        return $this->success(new InboundResource($inbound));
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'lokasi_terakhir' => 'nullable|string',
            'nama_penerima' => 'nullable|string',
        ]);

        $inbound = Inbound::findOrFail($id);
        $inbound->update($request->only('lokasi_terakhir', 'nama_penerima'));

        return $this->success(new InboundResource($inbound), 'Inbound updated successfully');
    }

    public function progress(string $id)
    {
        $inbound = Inbound::findOrFail($id);
        return $this->success([
            'total_box_expected' => $inbound->total_box_expected,
            'total_box_sudah_discan' => $inbound->total_box_sudah_discan,
            'status_scan' => $inbound->status_scan,
        ]);
    }
}
