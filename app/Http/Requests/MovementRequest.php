<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MovementRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'refugee_id' => ['required', 'exists:refugees,id'],
            'checkpoint_id' => ['required', 'exists:checkpoints,id'],
            'movement_type' => ['required', 'in:entry,exit'],
            // A movement is a record of something observed, so it cannot be in the future.
            'movement_datetime' => ['required', 'date', 'before_or_equal:now'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return ['movement_datetime.before_or_equal' => 'وقت الحركة لا يمكن أن يكون في المستقبل.'];
    }
}
