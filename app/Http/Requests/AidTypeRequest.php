<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AidTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                // The table's unique key is (organization_id, name), so the rule has to
                // match it or the database rejects what validation just accepted.
                Rule::unique('aid_types', 'name')
                    ->where(fn ($query) => $query->where('organization_id', $this->input('organization_id')))
                    ->ignore($this->route('aidType')),
            ],
            'unit' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'نوع المساعدة مسجل بالفعل لهذه الجهة الداعمة.'];
    }
}
