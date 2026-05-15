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
            'latest_action' => $this->whenLoaded('actions', function () {
                $action = $this->actions->sortByDesc('action_time')->first();

                if (!$action) {
                    return null;
                }

                return [
                    'ID_action' => $action->ID_action,
                    'action_type' => $action->action_type,
                    'status_action' => $action->status_action,
                    'notes' => $action->notes,
                    'action_time' => $action->action_time,
                ];
            }),
            'dokumen_r1' => $this->whenLoaded('dokumenR1', function () {
                if (!$this->dokumenR1) {
                    return null;
                }

                return [
                    'ID_dokumen' => $this->dokumenR1->ID_dokumen,
                    'no_dokumen_r1' => $this->dokumenR1->no_dokumen_r1,
                    'status_dokumen' => $this->dokumenR1->status_dokumen,
                    'dibuat_at' => $this->dokumenR1->dibuat_at,
                ];
            }),
            'outbound_detail' => $this->whenLoaded('outboundDetail', function () {
                $outbound = $this->outboundDetail->outbound;

                return [
                    'ID_outbound_detail' => $this->outboundDetail->ID_outbound_detail,
                    'barang' => $this->outboundDetail->relationLoaded('barang') && $this->outboundDetail->barang
                        ? [
                            'ID_barang' => $this->outboundDetail->barang->ID_barang,
                            'nama_barang' => $this->outboundDetail->barang->nama_barang,
                        ]
                        : null,
                    'outbound' => $this->outboundDetail->relationLoaded('outbound') && $outbound
                        ? [
                            'ID_outbound' => $outbound->ID_outbound,
                            'no_pengiriman' => $outbound->no_pengiriman,
                            'lokasi_asal' => $outbound->lokasi_asal,
                            'waktu_kirim' => $outbound->waktu_kirim,
                            'estimasi_tiba' => $outbound->estimasi_tiba,
                            'vendor' => $outbound->relationLoaded('vendor') && $outbound->vendor
                                ? [
                                    'ID_vendor' => $outbound->vendor->ID_vendor,
                                    'nama_vendor' => $outbound->vendor->nama_vendor,
                                ]
                                : null,
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
