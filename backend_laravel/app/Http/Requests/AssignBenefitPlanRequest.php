<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Validate and authorize the request before the controller calls its service.
class AssignBenefitPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->role === 'hr' && $this->user('api')?->company_id !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'benefit_plan_id' => ['required', 'integer'],
        ];
    }
}
