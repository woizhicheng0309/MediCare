<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    // Laravel injects the service; controllers coordinate HTTP input and output.
    public function __construct(private readonly AuthService $service) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $token = $this->service->login($request->validated());
        if ($token === false) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return response()->json(['access_token' => $token, 'token_type' => 'bearer']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user('api');

        return response()->json(['id' => $user->id, 'name' => $user->name, 'role' => $user->role, 'company_id' => $user->company_id]);
    }

    public function logout(): JsonResponse
    {
        $this->service->logout();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
