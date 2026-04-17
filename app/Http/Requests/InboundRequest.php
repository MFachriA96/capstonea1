<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InboundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qr_token' => ['required', 'string'],
            'ID_gudang' => ['required', 'integer', 'exists:tabel_gudang,ID_gudang'],
            'nama_penerima' => ['required', 'string', 'max:100'],
            'lokasi_terakhir' => ['nullable', 'string', 'max:200'],
        ];
    }
}
