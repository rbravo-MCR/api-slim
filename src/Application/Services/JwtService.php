<?php

declare(strict_types=1);

namespace App\Application\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly string $issuer,
        private readonly string $audience,
        private readonly int $ttlInSeconds
    ) {}

    public function generateToken(int $userId, ?string $email = null, ?string $role = null): string
    {
        $now = time();
        $exp = $now + $this->ttlInSeconds;

        $payload = [
            'iss'   => $this->issuer,
            'aud'   => $this->audience,
            'iat'   => $now,
            'nbf'   => $now,
            'exp'   => $exp,
            'sub'   => $userId,
            'email'=> $email,
            'role' => $role,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function decodeToken(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));

        return (array) $decoded;
    }
}
