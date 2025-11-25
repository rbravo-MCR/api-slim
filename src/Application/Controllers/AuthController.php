<?php

declare(strict_types=1);

namespace App\Application\Controllers;

use App\Application\Services\UserService;
use App\Application\Services\TwoFactorService;
use App\Application\Services\PasswordResetService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly TwoFactorService $twoFactorService,
        private readonly PasswordResetService $passwordResetService,
        private readonly MailService $mailService,
        private readonly JwtService $jwtService,
    ) {}

    // 🔹 Registro
    public function register(Request $request, Response $response): Response
    {
        $data     = (array) $request->getParsedBody();
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $name     = trim($data['name'] ?? '');

        if ($email === '' || $password === '') {
            return $this->json(
                $response,
                ['message' => 'email y password son obligatorios'],
                422
            );
        }

        // Podrías validar formato de email, longitud de password, etc.
        $userId = $this->userService->createUser($email, $password, $name);

        return $this->json($response, [
            'message' => 'Usuario registrado correctamente',
            'userId'  => $userId,
        ], 201);
    }

    // 🔹 Login + 2FA (ya lo tenías)
    public function login(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $email    = trim($data['email'])    ?? '';
        $password = $data['password'] ?? '';

        if($email === '' || $password === '') {
            return $this->json(
                $response,
                ['message' => 'email y password son obligatorios'],
                422
            );
        }


        $userId = $this->userService->authenticate($email, $password);

        if (!$userId) {
            return $this->json(
                $response,
                ['message' => 'Credenciales inválidas'],
                401
            );
        }

        //obtener datos del usuario para el nombre
        $user = $this->userService->findById($userId);
        $name = $user['name'] ?? null;

        //Generar y guardar el código 2FA
        $code = $this->twoFactorService->generateCode();
        $this->twoFactorService->storeCode($userId, $code);

        // TODO: enviar por correo (SES)
        try {
             $this->mailService->sendTwoFactorCode($email, $name, $code);
        } catch (\Throwable $e) {
            // Si no se puede enviar, mejor no dejar al usuario a medias
            return $this->json(
                $response,
                ['message' => 'No se pudo enviar el código de verificación. Intenta más tarde.'],
                500
            );
        }


        return $this->json($response, [
            'message' => 'Código 2FA enviado',
            'userId'  => $userId,
        ]);
    }

    // 🔹 Olvidé mi password
    public function forgotPassword(Request $request, Response $response): Response
    {
        $data  = (array) $request->getParsedBody();
        $email = trim($data['email'] ?? '');

        if ($email === '') {
            return $this->json(
                $response,
                ['message' => 'email es obligatorio'],
                422
            );
        }

        $user = $this->userService->findByEmail($email);
        if (!$user) {
            // Por seguridad, responder igual aunque no exista
            return $this->json($response, [
                'message' => 'Si el correo existe, se enviará un enlace de recuperación',
            ]);
        }

        $userId = (int) $user['id'];

        $token = $this->passwordResetService->createToken((int) $user['id']);

        // Enviar correo con link/token
        try {
            $this->mailService->sendPasswordReset($email, $user['name'] ?? null, $token);
        } catch (\Throwable $e) {
            return $this->json(
                $response,
                ['message' => 'No se pudo enviar el correo de recuperación. Intenta más tarde.'],
                500
            );
        }

        return $this->json($response, [
            'message' => 'Si el correo existe, se enviará un enlace de recuperación',
        ]);
    }

    // 🔹 Reset de password con token
    public function resetPassword(Request $request, Response $response): Response
    {
        $data        = (array) $request->getParsedBody();
        $token       = $data['token']       ?? '';
        $newPassword = $data['newPassword'] ?? '';

        if ($token === '' || $newPassword === '') {
            return $this->json(
                $response,
                ['message' => 'token y newPassword son obligatorios'],
                422
            );
        }

        $userId = $this->passwordResetService->consumeToken($token);
        if (!$userId) {
            return $this->json(
                $response,
                ['message' => 'Token inválido o expirado'],
                400
            );
        }

        $this->userService->updatePassword($userId, $newPassword);

        return $this->json($response, [
            'message' => 'Password actualizado correctamente',
        ]);
    }

    // 🔹 Verificar 2FA (ya lo tenías)
    public function verifyCode(Request $request, Response $response): Response
    {
        $data   = (array) $request->getParsedBody();
        $userId = isset($data['userId']) ? (int) $data['userId'] : 0;
        $code   = $data['code'] ?? '';
    
        $isValid = $this->twoFactorService->verifyCode($userId, $code);
    
        if (! $isValid) {
            return $this->json(
                $response,
                ['message' => 'Código inválido o expirado'],
                400
            );
        }
    
        // Recuperar datos básicos del usuario para el token
        $user = $this->userService->findById($userId);
        if (!$user) {
            return $this->json(
                $response,
                ['message' => 'Usuario no encontrado'],
                404
            );
        }
    
        // Aquí generamos el JWT REAL
        $token = $this->jwtService->generateToken(
            (int) $user['id'],
            $user['email'] ?? null,
            $user['role']  ?? null, // si tienes roles
        );
    
        return $this->json($response, [
            'message' => '2FA verificado correctamente',
            'token'   => $token,
            'type'    => 'Bearer',
            'expiresIn' => $this->getJwtTtl(), // opcional
        ]);
    }
    
    // helper opcional para exponer el TTL
    private function getJwtTtl(): int
    {
        return (int) ($_ENV['JWT_TTL'] ?? 3600);
    }
    

    // 🔹 Helper para respuestas JSON limpias
    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($data));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
