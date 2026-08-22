<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HouseholdMemberRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'refugee_id' => ['required', 'exists:refugees,id'],
            'relation_to_head' => ['required', 'string', 'max:255'],
        ];
    }
}
