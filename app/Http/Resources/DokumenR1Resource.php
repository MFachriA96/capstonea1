<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DokumenR1Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ID_dokumen' => $this->ID_dokumen,
            'ID_discrepancy' => $this->ID_discrepancy,
            'no_dokumen_r1' => $this->no_dokumen_r1,
            'status_dokumen' => $this->status_dokumen,
            'dibuat_oleh' => $this->dibuat_oleh,
            'dibuat_at' => $this->dibuat_at,
            'keterangan' => $this->keterangan,
            'pembuat' => $this->whenLoaded('pembuat', function () {
                return [
                    'ID_user' => $this->pembuat->ID_user,
                    'nama' => $this->pembuat->nama,
                    'email' => $this->pembuat->email,
                ];
            }),
            'discrepancy' => $this->whenLoaded('discrepancy', function () {
                $outboundDetail = $this->discrepancy->outboundDetail;
                $outbound = $outboundDetail?->outbound;

                return [
                    'ID_discrepancy' => $this->discrepancy->ID_discrepancy,
                    'status' => $this->discrepancy->status,
                    'quantity_outbound' => $this->discrepancy->quantity_outbound,
                    'quantity_inbound' => $this->discrepancy->quantity_inbound,
                    'selisih' => $this->discrepancy->selisih,
                    'detected_at' => $this->discrepancy->detected_at,
                    'item' => [
                        'ID_barang' => $outboundDetail?->ID_barang,
                        'nama_barang' => $outboundDetail?->barang?->nama_barang,
                        'quantity_per_box' => $outboundDetail?->quantity_per_box,
                        'jumlah_box' => $outboundDetail?->jumlah_box,
                    ],
                    'shipment' => [
                        'ID_outbound' => $outbound?->ID_outbound,
                        'no_pengiriman' => $outbound?->no_pengiriman,
                        'lokasi_asal' => $outbound?->lokasi_asal,
                        'waktu_kirim' => $outbound?->waktu_kirim,
                        'estimasi_tiba' => $outbound?->estimasi_tiba,
                        'status' => $outbound?->status,
                        'vendor' => $outbound?->vendor ? [
                            'ID_vendor' => $outbound->vendor->ID_vendor,
                            'nama_vendor' => $outbound->vendor->nama_vendor,
                        ] : null,
                    ],
                ];
            }),
        ];
    }
}
