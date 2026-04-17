<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotifikasiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ID_notif' => $this->ID_notif,
            'ID_user' => $this->ID_user,
            'judul' => $this->judul,
            'pesan' => $this->pesan,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'sudah_dibaca' => $this->sudah_dibaca,
            'created_at' => $this->created_at,
        ];
    }
}
