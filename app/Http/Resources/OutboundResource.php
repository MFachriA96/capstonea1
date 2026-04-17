<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutboundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ID_outbound' => $this->ID_outbound,
            'no_pengiriman' => $this->no_pengiriman,
            'ID_vendor' => $this->ID_vendor,
            'waktu_kirim' => $this->waktu_kirim,
            'estimasi_tiba' => $this->estimasi_tiba,
            'lokasi_asal' => $this->lokasi_asal,
            'status' => $this->status,
            'dibuat_oleh' => $this->dibuat_oleh,
            'created_at' => $this->created_at,
            'details' => OutboundDetailResource::collection($this->whenLoaded('details')),
        ];
    }
}
