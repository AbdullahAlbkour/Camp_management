<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AidDistributionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'aid_type_id' => ['required', 'exists:aid_types,id'],
            'refugee_id' => ['nullable', 'exists:refugees,id'],
            'household_id' => ['nullable', 'exists:households,id'],
            'camp_id' => ['nullable', 'exists:camps,id'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            // Aid cannot have been handed out on a day that has not happened yet.
            'distribution_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return ['distribution_date.before_or_equal' => 'تاريخ التوزيع لا يمكن أن يكون في المستقبل.'];
    }
}
