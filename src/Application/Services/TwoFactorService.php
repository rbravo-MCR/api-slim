<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Database\Connection;
use PDO;

class TwoFactorService
{
    public function __construct(
        private readonly Connection $connection
    ) {}

    public function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    public function storeCode(int $userId, string $code, int $ttlMinutes = 5): void
    {
        $expiresAt = (new \DateTimeImmutable("+{$ttlMinutes} minutes"))
            ->format('Y-m-d H:i:s');

        $sql = 'INSERT INTO two_factor_codes (user_id, code, expires_at, used)
                VALUES (:user_id, :code, :expires_at, 0)';

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute([
            'user_id'    => $userId,
            'code'       => $code,
            'expires_at' => $expiresAt,
        ]);
    }

    public function verifyCode(int $userId, string $code): bool
    {
        $sql = 'SELECT id, code, expires_at, used
                FROM two_factor_codes
                WHERE user_id = :user_id
                  AND code = :code
                ORDER BY id DESC
                LIMIT 1';

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'code'    => $code,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        if ((bool) $row['used']) {
            return false;
        }

        if (new \DateTimeImmutable($row['expires_at']) < new \DateTimeImmutable()) {
            return false;
        }

        // Marcar como usado (idempotencia simple)
        $update = $this->connection->pdo()->prepare(
            'UPDATE two_factor_codes SET used = 1 WHERE id = :id'
        );
        $update->execute(['id' => $row['id']]);

        return true;
    }
}
