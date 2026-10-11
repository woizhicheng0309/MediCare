<?php

namespace App\Services;

use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthService
{
    // Keep JWT issuance and revocation behind one authentication service.
    public function login(array $credentials): string|false
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');

        return $guard->attempt($credentials);
    }

    public function logout(): void
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');
        $guard->logout();
    }
}
