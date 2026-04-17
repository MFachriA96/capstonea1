<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscrepancyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ID_discrepancy' => $this->ID_discrepancy,
            'ID_outbound_detail' => $this->ID_outbound_detail,
            'ID_inbound_detail' => $this->ID_inbound_detail,
            'quantity_outbound' => $this->quantity_outbound,
            'quantity_inbound' => $this->quantity_inbound,
            'selisih' => $this->selisih,
            'status' => $this->status,
            'keterangan' => $this->keterangan,
            'detected_at' => $this->detected_at,
        ];
    }
}
