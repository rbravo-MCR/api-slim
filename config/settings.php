<?php

declare(strict_types=1);

return [
    'db' => [
        'host'     => getenv('DB_HOST')     ?: 'car-rental-outlet.cqno6yuaulrd.us-east-1.rds.amazonaws.com',
        'port'     => getenv('DB_PORT')     ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'car_rental_outlet',
        'username' => getenv('DB_USERNAME') ?: 'admin',
        'password' => getenv('DB_PASSWORD') ?: '2gexxdfc',
        'charset'  => 'utf8mb4',
    ],
     'jwt' => [
        'secret'   => $_ENV['JWT_SECRET']   ?? 'changeme',
        'issuer'   => $_ENV['JWT_ISS']      ?? 'slim-api',
        'audience' => $_ENV['JWT_AUD']      ?? 'slim-client',
        'ttl'      => (int) ($_ENV['JWT_TTL'] ?? 3600),
    ],
];

?>