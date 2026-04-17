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
        ];
    }
}
