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

        $email    = isset($data['email']) ? trim($data['email']) : '';
        $password = isset($data['password']) ? trim($data['password']) : '';
        $name     = isset($data['name']) ? trim($data['name']) : '';

        $errors = [];

        if ($email === '') {
            $errors['email'][] = 'El email es obligatorio';
        }elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'El formato del email es inválido';
        }
        if ($password === '') {
            $errors['password'][] = 'La contraseña es obligatoria';
        } elseif (strlen($password) < 6) {
            $errors['password'][] = 'La contraseña debe tener al menos 6 caracteres';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'message' => 'Errores de validación',
                'errors'  => $errors,
            ], JSON_UNESCAPED_UNICODE));
            return $response
                ->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }

        // Verificar si el email ya está registrado
        $existingEmail = $this->userRepository->findByEmail($email);
        if ($existingEmail) {
            $response->getBody()->write(json_encode([
                'message'=>'El email ya está registrado',
            ], JSON_UNESCAPED_UNICODE));
            return $response
                ->withStatus(409)
                ->withHeader('Content-Type', 'application/json');

        }
        // Hash Contraseña 
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        // Crear usuario
        $userId = $this->userService->createUser($email, $hashedPassword, $name);

        //reponse de exito
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

        if($email === ''){
            $response->getBody()->write(json_encode([
                'message' => 'El email es obligatorio',
            ], JSON_UNESCAPED_UNICODE));
            return $response
                ->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }
        if($password === ''){
            $response->getBody()->write(json_encode([
                'message' => 'La contraseña es obligatoria',
            ], JSON_UNESCAPED_UNICODE));
            return $response
                ->withStatus(422)
                ->withHeader('Content-Type', 'application/json');

        }

        $userId = $this->userService->authenticate($email, $password);

        if (!$userId) {
            $response->getBody()->write(json_encode([
                'message' => 'Credenciales inválidas',
            ], JSON_UNESCAPED_UNICODE));
            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        //obtener datos del usuario para el nombre
        $user = $this->userService->findById($userId);
        $name = $user['name'] ?? null;

        //Generar y guardar el código 2FA
        $code = $this->twoFactorService->generateCode();
        $this->twoFactorService->storeCode($userId, $code);

        // TODO: enviar por correo (SES) ó SMTP
        try {
             $this->mailService->sendTwoFactorCode($email, $name, $code);
             $response->getBody()->write(json_encode([
                 'message' => 'Código 2FA enviado',
                 'userId'  => $userId,
             ], JSON_UNESCAPED_UNICODE));
             return $response
                 ->withStatus(200)
                 ->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            // Si no se puede enviar, mejor no dejar al usuario a medias
            $response->getBody()->write(json_encode([
                'message' => 'No se pudo enviar el código de verificación. Intenta más tarde.',
            ], JSON_UNESCAPED_UNICODE));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }


    }

    // 🔹 Olvidé mi password
    public function forgotPassword(Request $request, Response $response): Response
{
    $data  = (array) $request->getParsedBody();
    $email = trim($data['email'] ?? '');

    // 1. Validación
    if ($email === '') {
        return $this->json(
            $response,
            ['message' => 'El email es obligatorio'],
            422
        );
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $this->json(
            $response,
            ['message' => 'Formato de email inválido'],
            422
        );
    }

    // 2. Buscar usuario
    $user = $this->userService->findByEmail($email);

    // 3. Respuesta neutral (por seguridad)
    if (!$user) {
        return $this->json($response, [
            'message' => 'Si el correo existe, se enviará un enlace de recuperación',
        ]);
    }

    // 4. Crear token
    $token = $this->passwordResetService->createToken((int)$user['id']);

    // 5. Enviar correo
    try {
        $this->mailService->sendPasswordReset(
            $email,
            $user['name'] ?? null,
            $token
        );
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
