<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InboundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ID_inbound' => $this->ID_inbound,
            'ID_outbound' => $this->ID_outbound,
            'ID_gudang' => $this->ID_gudang,
            'ID_vendor' => $this->ID_vendor,
            'timestamp_terima' => $this->timestamp_terima,
            'nama_penerima' => $this->nama_penerima,
            'diterima_oleh' => $this->diterima_oleh,
            'qr_scan_result' => $this->qr_scan_result,
            'lokasi_terakhir' => $this->lokasi_terakhir,
            'total_box_expected' => $this->total_box_expected,
            'total_box_sudah_discan' => $this->total_box_sudah_discan,
            'status_scan' => $this->status_scan,
            'created_at' => $this->created_at,
            'details' => InboundDetailResource::collection($this->whenLoaded('details')),
        ];
    }
}
