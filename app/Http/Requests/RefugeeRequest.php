<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefugeeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female,other'],
            // A birth date in the future, or implying an age past any recorded lifespan,
            // is a typo rather than a fact.
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today', 'after:'.now()->subYears(120)->toDateString()],
            'nationality' => ['nullable', 'string', 'max:255'],
            'document_number' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('refugees', 'document_number')->ignore($this->route('refugee')),
            ],
            'phone' => ['nullable', 'string', 'max:255'],
            'marital_status' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'current_camp_id' => ['required', 'exists:camps,id'],
            'current_shelter_id' => ['nullable', 'exists:shelters,id'],
            'presence_status' => ['nullable', 'in:inside,outside'],
            'household_id' => ['nullable', 'exists:households,id'],
            'relation_to_head' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_number.unique' => 'رقم الوثيقة مسجل مسبقًا للاجئ آخر.',
            'date_of_birth.before_or_equal' => 'تاريخ الميلاد لا يمكن أن يكون في المستقبل.',
            'date_of_birth.after' => 'تاريخ الميلاد غير منطقي. راجع القيمة المدخلة.',
        ];
    }
}
