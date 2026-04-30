<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ID_user' => $this->ID_user,
            'nama' => $this->nama,
            'email' => $this->email,
            'role' => $this->role,
            'ID_vendor' => $this->ID_vendor,
            'created_at' => $this->created_at,
            'vendor' => $this->whenLoaded('vendor', function () {
                return [
                    'ID_vendor' => $this->vendor->ID_vendor,
                    'nama_vendor' => $this->vendor->nama_vendor,
                    'aktif' => $this->vendor->aktif,
                ];
            }),
        ];
    }
}
