<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DokumenR1Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ID_discrepancy' => ['required', 'integer', 'exists:tabel_discrepancy,ID_discrepancy'],
            'keterangan' => ['nullable', 'string'],
        ];
    }
}
