<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OutboundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ID_vendor' => ['required', 'integer', 'exists:tabel_vendor,ID_vendor'],
            'waktu_kirim' => ['required', 'date'],
            'estimasi_tiba' => ['nullable', 'date'],
            'lokasi_asal' => ['required', 'string', 'max:200'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.ID_barang' => ['required', 'integer', 'exists:tabel_barang,ID_barang'],
            'details.*.quantity_outbound' => ['required', 'integer', 'min:1'],
            'details.*.quantity_per_box' => ['required', 'integer', 'min:1'],
            'details.*.jumlah_box' => ['required', 'integer', 'min:1'],
        ];
    }
}
