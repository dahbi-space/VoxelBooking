<?php

declare(strict_types=1);

namespace App\Engine;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * SMTP email engine wrapping PHPMailer.
 *
 * Per PRD §IV and .ai/23-VoxelBooking-Legal-Logging.md:
 * - Loads SMTP config from settings table
 * - Sends and logs all results to email_log table
 * - SMTP credentials are never logged (audit redaction)
 * - Email dispatch must never hold a database lock
 * - If email fails, the triggering action still succeeds; failure is logged
 *
 * Self-hosted posture: the only outbound connection is operator-configured SMTP.
 * No telemetry, no external services, no CDN. If SMTP is unconfigured, no
 * outbound connections are made.
 */
final class Mailer
{
    private static ?array $configCache = null;

    /**
     * Send an email and log the result.
     *
     * @param string      $to        Recipient email address
     * @param string      $subject   Email subject
     * @param string      $htmlBody  HTML body content
     * @param string      $type      Email type (for email_log: confirmation, reminder, etc.)
     * @param string|null $tenantId  Associated tenant (optional)
     * @param string|null $bookingId Associated booking (optional)
     * @param string|null $plainBody Plain text fallback (auto-generated if null)
     *
     * @return array{sent: bool, error: string|null, log_id: string}
     */
    public static function send(
        string $to,
        string $subject,
        string $htmlBody,
        string $type = 'confirmation',
        ?string $tenantId = null,
        ?string $bookingId = null,
        ?string $plainBody = null,
    ): array {
        $config = self::loadConfig();
        $logId = Ulid::generate();

        // If SMTP is not configured, log the failure and return gracefully
        if (empty($config['smtp_host'])) {
            self::logEmail($logId, $tenantId, $bookingId, $type, $to, $subject, 'failed', 'SMTP not configured');
            Logger::warning('Email not sent: SMTP not configured', ['type' => $type]);
            return ['sent' => false, 'error' => 'SMTP not configured', 'log_id' => $logId];
        }

        $mail = new PHPMailer(true);

        try {
            // SMTP configuration
            $mail->isSMTP();
            $mail->Host       = $config['smtp_host'];
            $mail->Port       = (int) ($config['smtp_port'] ?: 587);
            $mail->SMTPSecure = self::resolveEncryption($config['smtp_encryption'] ?? 'tls');
            $mail->SMTPAuth   = !empty($config['smtp_username']);

            if ($mail->SMTPAuth) {
                $mail->Username = $config['smtp_username'];
                $mail->Password = $config['smtp_password'];
            }

            // Timeouts — never hold a database lock waiting for SMTP
            $mail->Timeout    = 10;
            $mail->SMTPDebug  = 0;

            // Sender
            $fromAddress = $config['mail_from_address'] ?: 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $fromName    = $config['mail_from_name'] ?: 'VoxelBooking';
            $mail->setFrom($fromAddress, $fromName);

            // Recipient
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $plainBody ?? self::htmlToPlainText($htmlBody);

            $mail->send();

            self::logEmail($logId, $tenantId, $bookingId, $type, $to, $subject, 'sent');

            return ['sent' => true, 'error' => null, 'log_id' => $logId];
        } catch (PHPMailerException $e) {
            $errorMessage = $e->getMessage();

            // Never log SMTP credentials in the error
            $errorMessage = self::redactCredentials($errorMessage, $config);

            self::logEmail($logId, $tenantId, $bookingId, $type, $to, $subject, 'failed', $errorMessage);
            Logger::error('Email send failed', [
                'type'  => $type,
                'error' => $errorMessage,
            ]);

            return ['sent' => false, 'error' => $errorMessage, 'log_id' => $logId];
        }
    }

    /**
     * Send a test email to verify SMTP configuration.
     *
     * @return array{sent: bool, error: string|null}
     */
    public static function sendTest(string $to): array
    {
        $result = self::send(
            $to,
            'VoxelBooking — SMTP Test',
            '<html><body>'
            . '<h2 style="font-family: -apple-system, sans-serif;">SMTP Configuration Verified</h2>'
            . '<p style="font-family: -apple-system, sans-serif; color: #666;">This test email confirms your SMTP settings are working correctly.</p>'
            . '<p style="font-family: -apple-system, sans-serif; color: #999; font-size: 12px;">Sent by VoxelBooking at ' . date('Y-m-d H:i:s T') . '</p>'
            . '</body></html>',
            'test',
        );

        return ['sent' => $result['sent'], 'error' => $result['error']];
    }

    /**
     * Check if SMTP is configured (host is set).
     */
    public static function isConfigured(): bool
    {
        $config = self::loadConfig();
        return !empty($config['smtp_host']);
    }

    /**
     * Clear the cached configuration (for testing or after settings change).
     */
    public static function clearConfigCache(): void
    {
        self::$configCache = null;
    }

    // ── Privacy-specific email methods ──

    /**
     * Send a privacy export acknowledgment to the customer.
     */
    public static function sendExportAcknowledgment(string $to, string $tenantName, ?string $tenantId = null): array
    {
        $subject = "Your data export from {$tenantName}";
        $html = self::renderPrivacyEmail(
            'Data Export Completed',
            "Your personal data has been exported from <strong>{$tenantName}</strong>. "
            . 'The export file was downloaded to your device during your session.',
            'If you did not request this export, please contact the business directly.',
        );

        return self::send($to, $subject, $html, 'privacy_export', $tenantId);
    }

    /**
     * Send a deletion request acknowledgment to the customer.
     */
    public static function sendDeletionAcknowledgment(string $to, string $tenantName, ?string $tenantId = null): array
    {
        $subject = "Deletion request received — {$tenantName}";
        $html = self::renderPrivacyEmail(
            'Deletion Request Received',
            "Your data deletion request has been submitted to <strong>{$tenantName}</strong>. "
            . 'The business will review your request and process it in accordance with data protection regulations.',
            'Under GDPR, the business must respond within 30 days. '
            . 'Your personal data will be anonymized once the request is confirmed.',
        );

        return self::send($to, $subject, $html, 'privacy_deletion', $tenantId);
    }

    /**
     * Notify the operator about a new deletion request.
     */
    public static function notifyOperatorDeletionRequest(
        string $customerName,
        string $customerEmail,
        string $tenantName,
        ?string $tenantId = null,
    ): array {
        $config = self::loadConfig();
        $operatorEmail = $config['mail_from_address'] ?: '';

        if (empty($operatorEmail)) {
            return ['sent' => false, 'error' => 'No operator email configured', 'log_id' => ''];
        }

        $subject = "New deletion request — {$customerName}";
        $html = self::renderPrivacyEmail(
            'New Deletion Request',
            "A customer has requested data deletion:<br><br>"
            . "<strong>Customer:</strong> {$customerName}<br>"
            . "<strong>Email:</strong> " . AuditLog::hashEmail($customerEmail) . " (hashed)<br>"
            . "<strong>Tenant:</strong> {$tenantName}<br><br>"
            . 'Please review this request in the admin deletion queue.',
            'Log in to VoxelBooking and navigate to the Deletion Queue to process this request.',
        );

        return self::send($operatorEmail, $subject, $html, 'operator_notification', $tenantId);
    }

    // ── Internal helpers ──

    /**
     * Load SMTP configuration from the settings table.
     *
     * @return array<string, string>
     */
    private static function loadConfig(): array
    {
        if (self::$configCache !== null) {
            return self::$configCache;
        }

        $keys = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'mail_from_address', 'mail_from_name'];
        $config = [];

        try {
            foreach ($keys as $key) {
                $rows = Database::query(
                    'SELECT `value` FROM `settings` WHERE `key` = ? LIMIT 1',
                    [$key]
                );
                $config[$key] = $rows[0]['value'] ?? '';
            }
        } catch (\Throwable) {
            // Database not available — return empty config
            return array_fill_keys($keys, '');
        }

        self::$configCache = $config;
        return $config;
    }

    /**
     * Resolve the PHPMailer encryption constant from the setting value.
     */
    private static function resolveEncryption(string $value): string
    {
        return match (strtolower(trim($value))) {
            'ssl'       => PHPMailer::ENCRYPTION_SMTPS,
            'tls'       => PHPMailer::ENCRYPTION_STARTTLS,
            'none', ''  => '',
            default     => PHPMailer::ENCRYPTION_STARTTLS,
        };
    }

    /**
     * Log an email send attempt to the email_log table.
     */
    private static function logEmail(
        string $id,
        ?string $tenantId,
        ?string $bookingId,
        string $type,
        string $to,
        string $subject,
        string $status,
        ?string $error = null,
    ): void {
        try {
            Database::execute(
                'INSERT INTO `email_log` (`id`, `tenant_id`, `booking_id`, `type`, `to_email`, `subject`, `status`, `error`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $tenantId, $bookingId, $type, $to, $subject, $status, $error]
            );
        } catch (\Throwable $e) {
            Logger::error('Failed to log email', [
                'type'   => $type,
                'status' => $status,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Strip HTML tags for plain text email fallback.
     */
    private static function htmlToPlainText(string $html): string
    {
        // Replace common block-level tags with newlines
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\/(p|div|h[1-6]|li|tr)>/i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Redact SMTP credentials from error messages.
     *
     * Per .ai/23 §3: SMTP credentials must never appear in logs.
     */
    private static function redactCredentials(string $message, array $config): string
    {
        $sensitive = array_filter([
            $config['smtp_password'] ?? '',
            $config['smtp_username'] ?? '',
        ]);

        foreach ($sensitive as $secret) {
            if ($secret !== '') {
                $message = str_replace($secret, '[REDACTED]', $message);
            }
        }

        return $message;
    }

    /**
     * Render a simple privacy-specific email template.
     */
    private static function renderPrivacyEmail(string $title, string $body, string $footer): string
    {
        return <<<HTML
        <html>
        <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f4f4f5;">
            <table width="100%" cellpadding="0" cellspacing="0" style="padding: 2rem 1rem;">
                <tr><td align="center">
                    <table width="560" cellpadding="0" cellspacing="0" style="background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <tr><td style="padding: 2rem; text-align: center;">
                            <h1 style="margin: 0 0 1rem; font-size: 20px; color: #18181b;">{$title}</h1>
                            <p style="margin: 0; font-size: 14px; color: #3f3f46; line-height: 1.6;">{$body}</p>
                        </td></tr>
                        <tr><td style="padding: 1rem 2rem; background: #fafafa; border-top: 1px solid #e4e4e7;">
                            <p style="margin: 0; font-size: 12px; color: #71717a; line-height: 1.5;">{$footer}</p>
                        </td></tr>
                    </table>
                    <p style="margin-top: 1rem; font-size: 11px; color: #a1a1aa;">Powered by VoxelBooking</p>
                </td></tr>
            </table>
        </body>
        </html>
        HTML;
    }
}
