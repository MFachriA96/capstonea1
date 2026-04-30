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
            'outbound_detail' => $this->whenLoaded('outboundDetail', function () {
                return [
                    'ID_outbound_detail' => $this->outboundDetail->ID_outbound_detail,
                    'barang' => $this->outboundDetail->relationLoaded('barang') && $this->outboundDetail->barang
                        ? [
                            'ID_barang' => $this->outboundDetail->barang->ID_barang,
                            'nama_barang' => $this->outboundDetail->barang->nama_barang,
                        ]
                        : null,
                ];
            }),
            'inbound_detail' => $this->whenLoaded('inboundDetail', function () {
                return [
                    'ID_inbound_detail' => $this->inboundDetail->ID_inbound_detail,
                    'quantity_inbound' => $this->inboundDetail->quantity_inbound,
                    'ada_cacat' => $this->inboundDetail->ada_cacat,
                    'catatan_cacat' => $this->inboundDetail->catatan_cacat,
                    'audit_photos' => $this->inboundDetail->relationLoaded('auditPhotos')
                        ? $this->inboundDetail->auditPhotos
                        : [],
                ];
            }),
        ];
    }
}
