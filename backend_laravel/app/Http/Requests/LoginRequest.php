<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Validate and authorize the request before the controller calls its service.
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
