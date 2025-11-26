<?php declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Database\EmailLogRepository;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

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
        private readonly string $baseUrl = '',
        private readonly ?EmailLogRepository $emailLogRepository = null,
    ) {}

    /**
     * Enviar correo genérico.
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): void
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->username;
            $mail->Password = $this->password;
            $mail->Port = $this->port;
            // $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL 465
            $mail->SMTPSecure = $this->encryption;

            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            $mail->setFrom($this->fromAddress, $this->fromName);

            // 👇 Aceptar uno o varios correos
            if (is_array($toEmail)) {
                foreach ($toEmail as $email) {
                    $mail->addAddress($email, $toName ?: $email);
                }
            } else {
                $mail->addAddress($toEmail, $toName ?: $toEmail);
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            $mail->send();

            // log de exito
            if ($this->emailLogRepository) {
                $this->emailLogRepository->log($toEmail, $subject, $htmlBody, 'sent', null);
            }
        } catch (MailException $e) {
            // log de error
            if ($this->emailLogRepository) {
                $this->emailLogRepository->log($toEmail, $subject, $htmlBody, 'failed', $e->getMessage());
            }

            throw new \RuntimeException('Error al enviar el correo: ' . $e->getMessage());
        }
    }

    /**
     * Enviar el código 2FA por correo.
     */
    public function sendTwoFactorCode(string $toEmail, ?string $toName, string $code): void
    {
        $subject = 'Código de verificación';
        $body = sprintf(
            '<p style="font-size: 16px; background-color: #27A6BA; padding: 12px;">Hola %s,</p>
             <p style="font-size: 16px; background-color: #27A6BA; padding: 12px;">Tu código de verificación es: </p>
             <p style="font-size:28px; font-weight: bold; ">%s</p>
             <p style="font-size: 16px; background-color: #27A6BA; color: #6e6e6eff; padding: 12px;">Este código expirará en 5 minutos.</p>',
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
        $subject = 'Restablecer contraseña';

        $resetLink = rtrim($this->baseUrl, '/') . '/auth/reset-password?token=' . urlencode($token);

        $body = sprintf(
            '<p style="font-size: 18px; background-color: #27A6BA; padding: 12px;">
             Hola %s,
            Hemos recibido una solicitud para restablecer tu contraseña.</p>
            Puedes hacerlo usando este enlace:</p>
             <p style="font-size: 18px; padding: 12px;"><a href="%s">%s</a></p>
             <p style="font-size: 16px; padding: 12px;">Si tú no solicitaste esto, puedes ignorar este correo.</p>',
            htmlspecialchars($toName ?: $toEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($resetLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($resetLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );

        $this->send($toEmail, $toName ?? '', $subject, $body);
    }
}
