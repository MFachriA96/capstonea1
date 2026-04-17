<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ID_inbound' => ['required', 'integer', 'exists:tabel_inbound,ID_inbound'],
            'ID_barang' => ['required', 'integer', 'exists:tabel_barang,ID_barang'],
        ];
    }
}
