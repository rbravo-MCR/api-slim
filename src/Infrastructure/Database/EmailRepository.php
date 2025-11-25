<?php
namespace App\Infrastructure\Database;

use PDO;

class EmailLogRepository
{
    public function __construct(
        private readonly Connection $connection
    ) {}

    public function log(string $toEmail, string $subject, string $body, string $status, ?string $errorMessage = null): void
    {
        $sql = 'INSERT INTO email_logs (to_email, subject, body, status, error_message)
                VALUES (:to_email, :subject, :body, :status, :error_message)';

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute([
            'to_email'      => $toEmail,
            'subject'       => $subject,
            'body'          => $body,
            'status'        => $status,
            'error_message' => $errorMessage,
        ]);
    }
}
