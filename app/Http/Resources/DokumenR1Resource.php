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
        ];
    }
}
