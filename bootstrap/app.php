<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\BodyParsingMiddleware;

use App\Infrastructure\Database\Connection;
use App\Infrastructure\Database\UserRepository;

use App\Application\Services\JwtService;
use App\Application\Services\MailService;
use App\Application\Services\PasswordResetService;
use App\Application\Services\TwoFactorService;
use App\Application\Services\UserService;

use App\Application\Controllers\AuthController;
use App\Application\Middleware\JwtAuthMiddleware;

require __DIR__ . '/../vendor/autoload.php';

// -------------------------------------------------------------
// Crear contenedor
// -------------------------------------------------------------
$container = new Container();

// -------------------------------------------------------------
// Cargar settings.php (soporta ambas formas: con o sin 'settings')
// -------------------------------------------------------------
$rawSettings = require __DIR__ . '/../config/settings.php';
$settings = $rawSettings['settings'] ?? $rawSettings;

// Guardar settings "plano" en el contenedor
$container->set('settings', $settings);

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();
// -------------------------------------------------------------
// Registrar conexión a la BD
// -------------------------------------------------------------
$container->set(Connection::class, function ($c) {
    $settings = $c->get('settings');
    return new Connection($settings['db']);
});

// -------------------------------------------------------------
// Registrar repositorios
// -------------------------------------------------------------
$container->set(UserRepository::class, function ($c) {
    return new UserRepository($c->get(Connection::class));
});

// -------------------------------------------------------------
// Registrar servicios
// -------------------------------------------------------------
$container->set(UserService::class, function ($c) {
    return new UserService($c->get(UserRepository::class));
});

$container->set(TwoFactorService::class, function ($c) {
    return new TwoFactorService(
        $c->get(Connection::class)
    );
});

$container->set(PasswordResetService::class, function ($c) {
    return new PasswordResetService(
        $c->get(Connection::class)
    );
});



$container->set(MailService::class, function ($c) {
    $mail = $c->get('settings')['mail'];

    return new MailService(
        host:        $mail['host'],
        port:        $mail['port'],
        username:    $mail['username'],
        password:    $mail['password'],
        fromAddress: $mail['fromAddress'],
        fromName:    $mail['fromName'],
        encryption:  $mail['encryption'],
        baseUrl:     $mail['baseUrl']
    );
});

// -------------------------------------------------------------
// Registrar controlador y middleware (para las rutas)
// -------------------------------------------------------------
$container->set(AuthController::class, function ($c) {
    return new AuthController(
         $c->get(UserService::class),         
        $c->get(TwoFactorService::class),     
        $c->get(JwtService::class),           
        $c->get(MailService::class),         
        $c->get(PasswordResetService::class), 
    );
});

$container->set(JwtService::class, function ($c) {
    $settings = $c->get('settings');
    $jwt      = $settings['jwt'];

    return new JwtService(
        $jwt['secret'],        // string
        $jwt['issuer'],        // string
        $jwt['audience'],      // string
        $jwt['ttl'],           // int
        $jwt['refresh_ttl']    // int
    );
});


// ----------------------------------------------------------
// Middleware de error
// -----------------------------------------------------------
$errorMiddleware = $app->addErrorMiddleware(
    $settings['app']['debug'] ?? false,
    true,
    true
);

// -------------------------------------------------------------
// Cargar rutas
// -------------------------------------------------------------
(require __DIR__ . '/../routes/api.php')($app);

return $app;
