<?php

declare(strict_types=1);

namespace App\Application\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class MailService
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly string $encryption = 'tls',
        private readonly string $baseUrl = ''
    ) {}

    /**
     * Enviar correo genérico.
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): void
    {
        $mail = new PHPMailer(true);

        try {
            // Servidor SMTP
            $mail->isSMTP();
            $mail->Host       = $this->host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->username;
            $mail->Password   = $this->password;
            $mail->SMTPSecure = $this->encryption;
            $mail->Port       = $this->port;

            // Remitente y destinatario
            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($toEmail, $toName ?: $toEmail);

            // Contenido
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;

            $mail->send();
        } catch (MailException $e) {
            // En producción: loguear
            throw new \RuntimeException('No se pudo enviar el correo: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Enviar el código 2FA por correo.
     */
    public function sendTwoFactorCode(string $toEmail, ?string $toName, string $code): void
    {
        $subject = 'Tu código de verificación (2FA)';
        $body = sprintf(
            '<p>Hola %s,</p>
             <p>Tu código de verificación es:</p>
             <p style="font-size: 24px; font-weight: bold;">%s</p>
             <p>Este código expirará en unos minutos.</p>',
            htmlspecialchars($toName ?: $toEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );

        $this->send($toEmail, $toName ?? '', $subject, $body);
    }

    /**
     * Enviar correo de reset de password con token.
     */
    public function sendPasswordReset(string $toEmail, ?string $toName, string $token): void
    {
        $subject = 'Restablecer tu contraseña';

        $resetLink = rtrim($this->baseUrl, '/') . '/reset-password?token=' . urlencode($token);

        $body = sprintf(
            '<p>Hola %s,</p>
             <p>Hemos recibido una solicitud para restablecer tu contraseña.</p>
             <p>Puedes hacerlo usando este enlace:</p>
             <p><a href="%s">%s</a></p>
             <p>Si tú no solicitaste esto, puedes ignorar este correo.</p>',
            htmlspecialchars($toName ?: $toEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($resetLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($resetLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );

        $this->send($toEmail, $toName ?? '', $subject, $body);
    }
}
