<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FinalizeReceivingRequest;
use App\Http\Requests\ScanBoxRequest;
use App\Http\Requests\VerifyBoxRequest;
use App\Services\ReceivingService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ReceivingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReceivingService $receivingService
    ) {
    }

    public function queue(Request $request)
    {
        return $this->success(
            $this->receivingService->getQueue($request->integer('ID_gudang'), $request->user())
        );
    }

    public function show(string $outboundId, Request $request)
    {
        return $this->success(
            $this->receivingService->getShipmentContext((int) $outboundId, $request->user())
        );
    }

    public function scanBox(ScanBoxRequest $request)
    {
        return $this->success(
            $this->receivingService->scanBox($request->validated('qr_token'), $request->validated(), $request->user())
        );
    }

    public function verifyBox(VerifyBoxRequest $request)
    {
        return $this->success(
            $this->receivingService->verifyBox($request->validated(), $request->user())
        );
    }

    public function finalize(string $inboundId, FinalizeReceivingRequest $request)
    {
        return $this->success(
            $this->receivingService->finalizeInbound((int) $inboundId, $request->user())
        );
    }
}
