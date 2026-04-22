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
            'qr_token' => ['required', 'string', 'exists:tabel_outbound_detail,qr_token'],
        ];
    }
}
