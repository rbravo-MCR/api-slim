<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Database\Connection;
use PDO;

class PasswordResetService
{
    public function __construct(
        private readonly Connection $connection
    ) {}

    public function createToken(int $userId, int $ttlMinutes = 30): string
    {
        $token = bin2hex(random_bytes(32)); // 64 chars

        $expiresAt = (new \DateTimeImmutable("+{$ttlMinutes} minutes"))
            ->format('Y-m-d H:i:s');

        $sql = 'INSERT INTO password_resets (user_id, token, expires_at, used)
                VALUES (:user_id, :token, :expires_at, 0)';

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute([
            'user_id'    => $userId,
            'token'      => $token,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    public function consumeToken(string $token): ?int
    {
        $sql = 'SELECT id, user_id, expires_at, used
                FROM password_resets
                WHERE token = :token
                ORDER BY id DESC
                LIMIT 1';

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute(['token' => $token]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        if ((bool) $row['used']) {
            return null;
        }

        if (new \DateTimeImmutable($row['expires_at']) < new \DateTimeImmutable()) {
            return null;
        }

        // Marcar como usado
        $update = $this->connection->pdo()->prepare(
            'UPDATE password_resets SET used = 1 WHERE id = :id'
        );
        $update->execute(['id' => $row['id']]);

        return (int) $row['user_id'];
    }
}
