<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SecurityReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'refugee_id' => ['required', 'exists:refugees,id'],
            'incident_type' => ['required', 'string', 'max:255'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:5000'],
            'action_taken' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return ['report_date.before_or_equal' => 'تاريخ التقرير لا يمكن أن يكون في المستقبل.'];
    }
}
