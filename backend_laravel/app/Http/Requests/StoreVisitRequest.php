<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Validate and authorize the request before the controller calls its service.
class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->role === 'employee';
    }

    public function rules(): array
    {
        return [
            'clinic' => ['required', 'string', 'max:255'],
            'visited_on' => ['required', 'date'],

            // Whole TWD amounts must fit the unsigned integer column after conversion.
            'covered_amount' => ['required', 'integer', 'min:0', 'max:42949672'],

            'diagnosis' => ['nullable', 'string', 'max:255'],
            'visited_time' => ['nullable', 'date_format:H:i'],
            'doctor_name' => ['nullable', 'string', 'max:255'],
            'doctor_title' => ['nullable', 'string', 'max:255'],

            // Sick leave requires both dates in chronological order.
            'sick_leave_started_on' => [
                'nullable',
                'required_with:sick_leave_ended_on',
                'date_format:Y-m-d',
            ],
            'sick_leave_ended_on' => [
                'nullable',
                'required_with:sick_leave_started_on',
                'date_format:Y-m-d',
                'after_or_equal:sick_leave_started_on',
            ],

            // Each medication is a breakdown already included in the visit amount.
            'medications' => ['sometimes', 'array', 'max:50'],
            'medications.*' => ['array:name,dosage,instructions,covered_amount'],
            'medications.*.name' => ['required', 'string', 'max:255'],
            'medications.*.dosage' => ['nullable', 'string', 'max:255'],
            'medications.*.instructions' => ['nullable', 'string', 'max:2000'],
            'medications.*.covered_amount' => [
                'required',
                'integer',
                'min:0',
                'max:42949672',
            ],
        ];
    }
}
