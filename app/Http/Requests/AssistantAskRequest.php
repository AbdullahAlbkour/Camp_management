<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssistantAskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        // The ceiling is a sentence, not an essay: nothing the assistant can
        // answer needs more, and a bounded field keeps the folding cheap.
        return [
            'question' => ['required', 'string', 'min:2', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required' => 'اكتب سؤالك أولًا.',
            'question.min' => 'السؤال قصير جدًا.',
            'question.max' => 'السؤال طويل جدًا، اختصره في جملة واحدة.',
        ];
    }
}
