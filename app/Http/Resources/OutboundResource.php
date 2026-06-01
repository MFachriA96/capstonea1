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
            'total_qr' => $this->when(isset($this->total_qr), (int) $this->total_qr),
            'ready_qr' => $this->when(isset($this->ready_qr), (int) $this->ready_qr),
            'qr_ready' => $this->when(
                isset($this->total_qr, $this->ready_qr),
                fn () => $this->status !== 'draft' && (int) $this->total_qr > 0 && (int) $this->ready_qr === (int) $this->total_qr
            ),
            'has_discrepancy' => $this->when(isset($this->has_discrepancy), fn () => (bool) $this->has_discrepancy),
            'vendor' => $this->whenLoaded('vendor', function () {
                return [
                    'ID_vendor' => $this->vendor->ID_vendor,
                    'nama_vendor' => $this->vendor->nama_vendor,
                ];
            }),
            'creator' => $this->whenLoaded('pembuatOutbound', function () {
                return [
                    'ID_user' => $this->pembuatOutbound->ID_user,
                    'nama' => $this->pembuatOutbound->nama,
                    'email' => $this->pembuatOutbound->email,
                ];
            }),
            'details' => OutboundDetailResource::collection($this->whenLoaded('details')),
        ];
    }
}
