<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicalServiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('medical_services', 'name')->ignore($this->route('medicalService')),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
