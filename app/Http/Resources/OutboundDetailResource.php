<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutboundDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ID_outbound_detail' => $this->ID_outbound_detail,
            'ID_outbound' => $this->ID_outbound,
            'ID_barang' => $this->ID_barang,
            'quantity_outbound' => $this->quantity_outbound,
            'quantity_per_box' => $this->quantity_per_box,
            'jumlah_box' => $this->jumlah_box,
            'qr_token' => $this->qr_token,
        ];
    }
}
