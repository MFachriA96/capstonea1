<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OutboundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ID_vendor' => ['nullable', 'integer', 'exists:tabel_vendor,ID_vendor'],
            'waktu_kirim' => ['required', 'date'],
            'estimasi_tiba' => ['nullable', 'date'],
            'lokasi_asal' => ['required', 'string', 'max:200'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.ID_barang' => ['nullable', 'integer', 'exists:tabel_barang,ID_barang'],
            'details.*.nama_barang' => ['nullable', 'string', 'max:150'],
            'details.*.satuan' => ['nullable', 'string', 'max:20'],
            'details.*.quantity_outbound' => ['required', 'integer', 'min:1'],
            'details.*.quantity_per_box' => ['required', 'integer', 'min:1'],
            'details.*.jumlah_box' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('details', []) as $index => $detail) {
                $hasBarangId = !empty($detail['ID_barang']);
                $hasBarangName = filled($detail['nama_barang'] ?? null);

                if (!$hasBarangId && !$hasBarangName) {
                    $validator->errors()->add(
                        "details.$index.nama_barang",
                        'Each detail must include either ID_barang or nama_barang.'
                    );
                }
            }
        });
    }
}
