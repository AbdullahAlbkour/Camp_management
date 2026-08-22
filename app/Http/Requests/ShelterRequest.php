<?php

namespace App\Http\Requests;

use App\Models\Refugee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShelterRequest extends FormRequest
{
    public function rules(): array
    {
        $shelter = $this->route('shelter');

        return [
            'camp_id' => ['required', 'exists:camps,id'],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('shelters', 'code')
                    ->where(fn ($query) => $query->where('camp_id', $this->input('camp_id')))
                    ->ignore($shelter),
            ],
            'type' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'status' => ['required', 'in:active,maintenance,inactive'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $shelter = $this->route('shelter');

                if ($shelter === null || $validator->errors()->isNotEmpty()) {
                    return;
                }

                // Shrinking a unit below the number of people already living in it would
                // silently leave it over capacity, which every later check would then trip on.
                $occupied = Refugee::where('current_shelter_id', $shelter->id)
                    ->where('status', 'active')
                    ->count();

                if ((int) $this->input('capacity') < $occupied) {
                    $validator->errors()->add(
                        'capacity',
                        'لا يمكن تقليل السعة إلى أقل من عدد الساكنين الحالي ('.$occupied.').'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return ['code.unique' => 'رمز الوحدة مستخدم بالفعل داخل هذا المخيم.'];
    }
}
