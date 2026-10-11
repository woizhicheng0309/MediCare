<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Validate and authorize the request before the controller calls its service.
class ReviewVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->role === 'helpdesk';
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:approved,rejected'],
        ];
    }
}
