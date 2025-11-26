<?php declare(strict_types=1);

namespace App\Application\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly string $issuer,
        private readonly string $audience,
        private int $ttl,
        private int $refreshTtl
    ) {}

    public function generateToken(int $userId, ?string $email = null, ?string $role = null): string
    {
        $now = time();
        $exp = $now + $this->ttl;

        $payload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $exp,
            'sub' => $userId,
            'email' => $email,
            'role' => $role,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function generateAuthTokens(int $userId, string $email, ?string $role = null): array
    {
        $now = time();

        // Access Token
        $accessPayload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->ttl,
            'sub' => $userId,
            'email' => $email,
            'role' => $role,
            'type' => 'access'
        ];
        $accessToken = JWT::encode($accessPayload, $this->secret, 'HS256');

        // Refresh Token
        $refreshPayload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->refreshTtl,
            'sub' => $userId,
            'type' => 'refresh'
        ];
        $refreshToken = JWT::encode($refreshPayload, $this->secret, 'HS256');

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }

    public function decodeToken(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));

        return (array) $decoded;
    }
}
