<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HousingTransferRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'camp_id' => ['required', 'exists:camps,id'],
            'shelter_id' => ['nullable', 'exists:shelters,id'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
