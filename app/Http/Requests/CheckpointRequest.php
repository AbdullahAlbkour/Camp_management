<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckpointRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'camp_id' => ['required', 'exists:camps,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('checkpoints', 'name')
                    ->where(fn ($query) => $query->where('camp_id', $this->input('camp_id')))
                    ->ignore($this->route('checkpoint')),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'اسم نقطة التفتيش مستخدم بالفعل في هذا المخيم.'];
    }
}
