<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;

use App\Infrastructure\Database\Connection;
use App\Infrastructure\Database\UserRepository;

use App\Application\Services\UserService;
use App\Application\Services\TwoFactorService;
use App\Application\Services\PasswordResetService;
use App\Application\Services\JwtService;
use App\Application\Services\MailService;



use App\Application\Controllers\AuthController;
use App\Application\Middleware\JwtAuthMiddleware;

require __DIR__ . '/../vendor/autoload.php';

// .env
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// config
$settings = require __DIR__ . '/../config/settings.php';

$dbConfig  = $settings['db'];
$jwtConfig = $settings['jwt'];

// Infra
$dbConnection    = new Connection($dbConfig);
$userRepository  = new UserRepository($dbConnection);

// Services
$userService          = new UserService($userRepository);
$twoFactorService     = new TwoFactorService($dbConnection);
$passwordResetService = new PasswordResetService($dbConnection);

$jwtService = new JwtService(
    $jwtConfig['secret'],
    $jwtConfig['issuer'],
    $jwtConfig['audience'],
    $jwtConfig['ttl']
);

// MailService desde .env
$mailService = new MailService(
    host:        $_ENV['MAIL_HOST']         ?? 'localhost',
    port:        (int) ($_ENV['MAIL_PORT']  ?? 25),
    username:    $_ENV['MAIL_USERNAME']     ?? '',
    password:    $_ENV['MAIL_PASSWORD']     ?? '',
    fromAddress: $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@example.com',
    fromName:    $_ENV['MAIL_FROM_NAME']    ?? 'API',
    encryption:  $_ENV['MAIL_ENCRYPTION']   ?? 'tls',
    baseUrl:     $_ENV['MAIL_BASE_URL']     ?? 'http://localhost:8080'
);

$jwtAuthMiddleware = new JwtAuthMiddleware($jwtService);

// Controller
$authController = new AuthController(
    $userService,
    $twoFactorService,
    $passwordResetService,
    $jwtService,
    $mailService,
);

// Slim app
$app = AppFactory::create();

// CORS + middlewares aquí...

$app->addBodyParsingMiddleware();  // 👈 IMPORTANTE
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Rutas
(require __DIR__ . '/../routes/api.php')($app, $authController, $jwtAuthMiddleware);

return $app;
