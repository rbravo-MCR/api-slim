<?php declare(strict_types=1);

namespace App\Application\Controllers;

use App\Application\Services\JwtService;
use App\Application\Services\MailService;
use App\Application\Services\PasswordResetService;
use App\Application\Services\TwoFactorService;
use App\Application\Services\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    public function __construct(
        private UserService $userService,
        private TwoFactorService $twoFactorService,
        private JwtService $jwtService,
        private MailService $mailService,
        private PasswordResetService $passwordResetService,
    ) {}

    // 🔹 Registro
    public function register(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $email = trim(strtolower($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $name = trim($data['name'] ?? '');

        $errors = [];

        if ($email === '') {
            $errors['email'][] = 'El email es obligatorio';
        }
        if ($password === '') {
            $errors['password'][] = 'La contraseña es obligatoria';
        }

        if (!empty($errors)) {
            return $this->json($response, [
                'message' => 'Errores de validación',
                'errors' => $errors,
            ], 422);
        }

        // Verificar si el email ya está registrado
        $existingEmail = $this->userService->findByEmail($email);
        if ($existingEmail) {
            return $this->json($response, [
                'message' => 'El email ya está registrado',
            ], 409);
        }

        // Hash contraseña
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Crear usuario
        $userId = $this->userService->createUser($email, $hashedPassword, $name);

        // Generar y almacenar código 2FA
        $code = $this->twoFactorService->generateCode();
        $this->twoFactorService->storeCode($userId, $code);

        // Enviar correo con código
        $this->mailService->sendTwoFactorCode($email, $name, $code);

        // Respuesta de éxito
        return $this->json($response, [
            'message' => 'Usuario registrado. Revisa tu correo para verificar el código de verificación.',
        ], 201);
    }

    // 🔹 Login + 2FA
    public function login(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $email = trim(strtolower($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($email === '') {
            return $this->json($response, [
                'message' => 'El email es obligatorio',
            ], 422);
        }

        if ($password === '') {
            return $this->json($response, [
                'message' => 'La contraseña es obligatoria',
            ], 422);
        }

        $userId = $this->userService->authenticate($email, $password);

        if (!$userId) {
            return $this->json($response, [
                'message' => 'Credenciales inválidas',
            ], 401);
        }

        // Obtener datos del usuario para el nombre
        $user = $this->userService->findById($userId);
        $name = $user['name'] ?? null;

        // Generar y guardar el código 2FA
        $code = $this->twoFactorService->generateCode();
        $this->twoFactorService->storeCode($userId, $code);

        try {
            $this->mailService->sendTwoFactorCode($email, $name, $code);

            return $this->json($response, [
                'message' => 'Código 2FA enviado',
                'userId' => $userId,
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, [
                'message' => 'No se pudo enviar el código de verificación. Intenta más tarde.',
            ], 500);
        }
    }

    // 🔹 Olvidé mi password
    public function forgotPassword(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $email = trim($data['email'] ?? '');

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

        $user = $this->userService->findByEmail($email);

        // Respuesta neutral (por seguridad)
        if (!$user) {
            return $this->json($response, [
                'message' => 'Si el correo existe, se enviará un enlace de recuperación',
            ]);
        }

        $token = $this->passwordResetService->createToken((int) $user['id']);

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
        $data = (array) $request->getParsedBody();
        $token = trim($data['token'] ?? '');
        $newPassword = trim($data['newPassword'] ?? '');

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

    // 🔹 Verificar 2FA
    public function verifyOtp(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $email = trim(strtolower($data['email'] ?? ''));
        $code = trim($data['code'] ?? '');

        if ($email === '' || $code === '') {
            return $this->json($response, [
                'message' => 'email y code son obligatorios',
            ], 422);
        }

        $user = $this->userService->findByEmail($email);

        if (!$user) {
            return $this->json($response, ['message' => 'Código o usuario inválido'], 400);
        }

        if (!$this->twoFactorService->verifyCode((int) $user['id'], $code)) {
            return $this->json($response, ['message' => 'Código incorrecto o expirado'], 400);
        }

        $tokens = $this->jwtService->generateAuthTokens((int) $user['id'], $user['email']);

        return $this->json($response, [
            'message' => '2FA verificado correctamente',
            'token' => $tokens['access_token'] ?? null,
            'refresh_token' => $tokens['refresh_token'] ?? null,
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
        $response->getBody()->write(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE)
        );

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
