<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

return [

    'settings' => [

        // ---------------------------------------------------------
        // APP
        // ---------------------------------------------------------
        'app' => [
            'env' => $_ENV['APP_ENV'] ?? 'local',
            'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
            'baseUrl' => $_ENV['APP_BASE_URL'] ?? '',
        ],

        // ---------------------------------------------------------
        // DATABASE
        // ---------------------------------------------------------
        'db' => [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => (int)($_ENV['DB_PORT'] ?? 3306),
            'database' => $_ENV['DB_DATABASE'] ?? '',
            'username' => $_ENV['DB_USERNAME'] ?? '',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
        ],

        // ---------------------------------------------------------
        // JWT
        // ---------------------------------------------------------
        'jwt' => [
            'secret' => $_ENV['JWT_SECRET'] ?? '',
            'ttl' => (int)($_ENV['JWT_TTL'] ?? 3600),
            'refresh_ttl' => (int)($_ENV['JWT_REFRESH_TTL'] ?? 604800),
        ],

        // ---------------------------------------------------------
        // MAIL (secure.emailsrvr.com)
        // ---------------------------------------------------------
        'mail' => [
            'host'        => $_ENV['MAIL_HOST'],
            'port'        => (int) $_ENV['MAIL_PORT'],
            'username'    => $_ENV['MAIL_USERNAME'],
            'password'    => $_ENV['MAIL_PASSWORD'],
            'fromAddress' => $_ENV['MAIL_FROM_ADDRESS'],
            'fromName'    => $_ENV['MAIL_FROM_NAME'],
            'encryption'  => $_ENV['MAIL_ENCRYPTION'],   // ssl
            'baseUrl'     => $_ENV['APP_BASE_URL'],
            'default_to'  => $_ENV['MAIL_DEFAULT_TO'] ?? ''
        ],

    ],

];
