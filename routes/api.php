<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use App\Application\Controllers\AuthController;
use App\Application\Middleware\JwtAuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (App $app): void {

    // =========================================================
    // OPTIONS global para CORS (preflight)
    // =========================================================
    $app->options('/{routes:.+}', function (Request $request, Response $response) {
        // Aquí normalmente solo devolvemos 200 con headers de CORS
        return $response;
    });

    // =========================================================
    // Rutas de autenticación (públicas)
    // =========================================================
    $app->group('/auth', function (RouteCollectorProxy $group) {

        // Registro
        $group->post('/register', [AuthController::class, 'register']);

        // Login (envía OTP)
        $group->post('/login', [AuthController::class, 'login']);

        // Verificar código OTP
        $group->post('/verify-otp', [AuthController::class, 'verifyOtp']);

        // Refresh token
        $group->post('/refresh', [AuthController::class, 'refreshToken']);

        // Olvidé mi contraseña (envía correo con enlace/token)
        $group->post('/forgot-password', [AuthController::class, 'forgotPassword']);

        // Reset de contraseña (con token)
        $group->post('/reset-password', [AuthController::class, 'resetPassword']);

    });

    // =========================================================
    // Rutas protegidas con JWT
    // =========================================================
    $app->group('/api', function (RouteCollectorProxy $group) {

        // Ejemplo: obtener datos del usuario autenticado
        $group->get('/me', [AuthController::class, 'me']);

        // aquí puedes agregar más rutas protegidas, ej:
        // $group->get('/profile', [ProfileController::class, 'show']);

    // Slim + PHP-DI resolverá JwtAuthMiddleware desde el contenedor
    })->add(JwtAuthMiddleware::class);

    // =========================================================
    // Healthcheck
    // =========================================================
    $app->get('/health', function (Request $request, Response $response): Response {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    });
};
