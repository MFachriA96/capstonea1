<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScanSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ID_session' => $this->ID_session,
            'ID_inbound' => $this->ID_inbound,
            'ID_barang' => $this->ID_barang,
            'urutan_scan' => $this->urutan_scan,
            'waktu_mulai' => $this->waktu_mulai,
            'waktu_selesai' => $this->waktu_selesai,
            'status_sesi' => $this->status_sesi,
            'ID_user' => $this->ID_user,
        ];
    }
}
