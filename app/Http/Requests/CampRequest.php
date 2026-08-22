<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('camps', 'name')->ignore($this->route('camp'))],
            'location' => ['nullable', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
