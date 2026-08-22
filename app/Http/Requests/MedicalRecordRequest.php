<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedicalRecordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'refugee_id' => ['required', 'exists:refugees,id'],
            'medical_service_id' => ['required', 'exists:medical_services,id'],
            'record_date' => ['required', 'date', 'before_or_equal:today'],
            'diagnosis' => ['required', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'needs_follow_up' => ['nullable', 'boolean'],
            // A follow-up scheduled before the visit it follows up on is a data-entry slip.
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:record_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'record_date.before_or_equal' => 'تاريخ السجل الطبي لا يمكن أن يكون في المستقبل.',
            'follow_up_date.after_or_equal' => 'تاريخ المتابعة يجب أن يكون في تاريخ الزيارة أو بعده.',
        ];
    }
}
