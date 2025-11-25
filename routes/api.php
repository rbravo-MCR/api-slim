<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use App\Application\Controllers\AuthController;
use App\Application\Middleware\JwtAuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (App $app, AuthController $authController, JwtAuthMiddleware $jwtAuthMiddleware): void {

    // OPTIONS global para CORS (preflight)
    $app->options('/{routes:.+}', function (Request $request, Response $response) {
        return $response;
    });

    // 🔓 Rutas públicas
    $app->post('/auth/register',        [$authController, 'register']);
    $app->post('/auth/login',           [$authController, 'login']);
    $app->post('/auth/verify-2fa',      [$authController, 'verifyCode']);
    $app->post('/auth/forgot-password', [$authController, 'forgotPassword']);
    $app->post('/auth/reset-password',  [$authController, 'resetPassword']);

    // 🛡 Grupo de rutas protegidas con JWT
    $app->group('/me', function (RouteCollectorProxy $group): void {

        // GET /me
        $group->get('', function (Request $request, Response $response): Response {
            $userId    = $request->getAttribute('userId');
            $userEmail = $request->getAttribute('userEmail');
            $userRole  = $request->getAttribute('userRole');

            $response->getBody()->write(json_encode([
                'userId' => $userId,
                'email'  => $userEmail,
                'role'   => $userRole,
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        });

        // aquí puedes agregar más rutas protegidas, ej:
        // $group->get('/profile', ...);

    })->add($jwtAuthMiddleware);

    // Healthcheck
    $app->get('/health', function (Request $request, Response $response): Response {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    });
};
