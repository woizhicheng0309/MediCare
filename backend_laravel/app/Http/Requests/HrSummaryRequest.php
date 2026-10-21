<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HrSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('api');

        return $user?->role === 'hr'
            && $user->company_id !== null;
    }

    public function rules(): array
    {
        return [
            'year' => [
                'sometimes',
                'integer',
                'between:2000,'.now()->year,
            ],
        ];
    }
}