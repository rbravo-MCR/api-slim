<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Services\JwtService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

class JwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly JwtService $jwtService
    ) {}

    public function process(Request $request, RequestHandler $handler): ResponseInterface
    {
        // No molestamos los preflight CORS
        if ($request->getMethod() === 'OPTIONS') {
            return $handler->handle($request);
        }

        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorized('Token de autorización requerido');
        }

        $token = trim(substr($authHeader, 7));

        if ($token === '') {
            return $this->unauthorized('Token vacío');
        }

        try {
            $payload = $this->jwtService->decodeToken($token);
        } catch (\Throwable $e) {
            return $this->unauthorized('Token inválido o expirado');
        }

        // Inyectamos datos del usuario en el request
        $request = $request
            ->withAttribute('userId',   $payload['sub']   ?? null)
            ->withAttribute('userEmail',$payload['email'] ?? null)
            ->withAttribute('userRole', $payload['role']  ?? null);

        return $handler->handle($request);
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'message' => $message,
        ]));

        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
}
