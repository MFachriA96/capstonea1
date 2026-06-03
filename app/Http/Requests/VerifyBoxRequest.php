<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyBoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ID_inbound' => ['required', 'integer', 'exists:tabel_inbound,ID_inbound'],
            'ID_outbound_box' => ['required', 'integer', 'exists:tabel_outbound_box,ID_outbound_box'],
            'actual_qty' => ['required', 'integer', 'min:0'],
            'condition_status' => ['required', 'in:normal,damaged,suspect'],
            'notes' => ['nullable', 'string', 'max:500'],
            'photo_ids' => ['nullable', 'array'],
        ];
    }
}
