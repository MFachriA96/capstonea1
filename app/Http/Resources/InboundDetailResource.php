<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InboundDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ID_inbound_detail' => $this->ID_inbound_detail,
            'ID_inbound' => $this->ID_inbound,
            'ID_barang' => $this->ID_barang,
            'quantity_cv_detect' => $this->quantity_cv_detect,
            'quantity_inbound' => $this->quantity_inbound,
            'ada_cacat' => $this->ada_cacat,
            'catatan_cacat' => $this->catatan_cacat,
        ];
    }
}
