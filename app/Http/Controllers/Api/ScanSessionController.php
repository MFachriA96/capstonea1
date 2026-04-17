<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScanSessionRequest;
use App\Http\Resources\InboundDetailResource;
use App\Http\Resources\InboundResource;
use App\Http\Resources\ScanSessionResource;
use App\Models\ScanSession;
use App\Services\ScanSessionService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ScanSessionController extends Controller
{
    use ApiResponse;

    protected $scanSessionService;

    public function __construct(ScanSessionService $scanSessionService)
    {
        $this->scanSessionService = $scanSessionService;
    }

    public function index()
    {
        return $this->success(ScanSessionResource::collection(ScanSession::paginate(15))->response()->getData(true));
    }

    public function store(ScanSessionRequest $request)
    {
        $session = $this->scanSessionService->startSession($request->ID_inbound, $request->ID_barang, $request->user());
        return $this->success(new ScanSessionResource($session), 'Scan session started', 201);
    }

    public function show(string $id)
    {
        $session = ScanSession::with(['fotos', 'cvResults'])->findOrFail($id);
        return $this->success(new ScanSessionResource($session));
    }

    public function uploadFoto(Request $request, string $id)
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:10240',
        ]);

        $result = $this->scanSessionService->uploadFoto($id, $request->file('foto'), $request->user());
        return $this->success($result, 'Foto uploaded and processed');
    }

    public function complete(string $id)
    {
        $inbound = $this->scanSessionService->completeSession($id);
        return $this->success(new InboundResource($inbound), 'Session completed');
    }

    public function updateInboundDetail(Request $request, string $id)
    {
        $request->validate([
            'ID_barang' => 'required|integer|exists:tabel_barang,ID_barang',
            'quantity_inbound' => 'required|integer|min:0',
        ]);

        $detail = $this->scanSessionService->updateInboundDetail($id, $request->ID_barang, $request->quantity_inbound);
        return $this->success(new InboundDetailResource($detail), 'Inbound detail updated');
    }
}
