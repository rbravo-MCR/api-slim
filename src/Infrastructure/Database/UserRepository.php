<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;

class UserRepository
{
    public function __construct(
        private readonly Connection $connection
    ) {}

    /**
     * Busca un usuario por email.
     * Devuelve array asociativo o null.
     */
    public function findByEmail(string $email): ?array
    {
        $sql = 'SELECT id, email, password, name, created_at, updated_at
                FROM users
                WHERE email = :email
                LIMIT 1';

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute(['email' => $email]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Busca un usuario por ID.
     * Devuelve array asociativo o null.
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT id, email, password, name, created_at, updated_at
                FROM users
                WHERE id = :id
                LIMIT 1';

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Crea un usuario y devuelve su ID.
     */
    public function create(array $data): int
    {
        $sql = 'INSERT INTO users (email, password, name)
                VALUES (:email, :password, :name)';

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute([
            'email'    => $data['email'],
            'password' => $data['password'],
            'name'     => $data['name'] ?? null,
        ]);

        return (int) $this->connection->pdo()->lastInsertId();
    }

    /**
     * Actualiza la contraseña de un usuario.
     */
    public function updatePassword(int $userId, string $hashedPassword): void
    {
        $sql = 'UPDATE users
                SET password = :password, updated_at = NOW()
                WHERE id = :id';

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute([
            'password' => $hashedPassword,
            'id'       => $userId,
        ]);
    }
}
