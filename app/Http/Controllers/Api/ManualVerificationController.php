<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InboundDetailResource;
use App\Http\Resources\InboundResource;
use App\Services\ManualVerificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ManualVerificationController extends Controller
{
    use ApiResponse;

    public function __construct(protected ManualVerificationService $manualVerificationService)
    {
    }

    public function updateDetail(Request $request, string $inboundId, string $detailId)
    {
        $data = $request->validate([
            'quantity_inbound' => ['required', 'integer', 'min:0'],
            'ada_cacat' => ['sometimes', 'boolean'],
            'catatan_cacat' => ['nullable', 'string'],
        ]);

        $detail = $this->manualVerificationService->updateDetail((int) $inboundId, (int) $detailId, $data);

        return $this->success(new InboundDetailResource($detail), 'Manual verification detail updated');
    }

    public function uploadPhoto(Request $request, string $inboundId, string $detailId)
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:10240'],
        ]);

        $foto = $this->manualVerificationService->uploadConditionPhoto(
            (int) $inboundId,
            (int) $detailId,
            $request->file('foto'),
            $request->user()
        );

        return $this->success($foto, 'Manual condition photo uploaded', 201);
    }

    public function finalize(string $inboundId)
    {
        $inbound = $this->manualVerificationService->finalize((int) $inboundId);

        return $this->success(new InboundResource($inbound), 'Manual verification finalized');
    }
}
