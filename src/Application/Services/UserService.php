<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Database\UserRepository;

class UserService
{
    public function __construct(
        private readonly UserRepository $users
    ) {}

    public function authenticate(string $email, string $password): ?int
    {
        $user = $this->users->findByEmail($email);

        if (!$user) {
            return null;
        }

        // Validación de contraseña
        if (!password_verify($password, $user['password'])) {
            return null;
        }

        return (int) $user['id'];
    }

    public function createUser(string $email, string $password,string $name): int
    {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        return $this->users->create([
            'email'    => $email,
            'password' => $hashed,
            'name'     => $name
        ]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->users->findByEmail($email);
    }
}
