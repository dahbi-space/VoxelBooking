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
        $transport = strtolower(trim($config['mail_transport'] ?? 'smtp'));

        // Log-only transport: record the email without making any outbound connection
        if ($transport === 'log') {
            self::logEmail($logId, $tenantId, $bookingId, $type, $to, $subject, 'sent', null);
            Logger::info('Email logged (log transport)', ['type' => $type, 'to' => $to]);
            return ['sent' => true, 'error' => null, 'log_id' => $logId];
        }

        // Mailpit transport: SMTP to localhost:1025, no auth, no encryption
        // Overrides operator SMTP settings so captured emails always appear in the Mailpit UI
        if ($transport === 'mailpit') {
            $config['smtp_host'] = '127.0.0.1';
            $config['smtp_port'] = '1025';
            $config['smtp_username'] = '';
            $config['smtp_password'] = '';
            $config['smtp_encryption'] = 'none';
        }

        // If SMTP is not configured (and not mailpit), log the failure and return gracefully
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
            $fromName    = $config['mail_from_name'] ?: app_name();
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
            __('email.test.subject', ['app_name' => app_name()]),
            '<html><body>'
            . '<h2 style="font-family: -apple-system, sans-serif;">' . __('email.test.title') . '</h2>'
            . '<p style="font-family: -apple-system, sans-serif; color: #666;">' . __('email.test.body') . '</p>'
            . '<p style="font-family: -apple-system, sans-serif; color: #999; font-size: 12px;">' . __('email.common.powered_by', ['app_name' => app_name()]) . ' — ' . Locale::datetime(new \DateTimeImmutable()) . '</p>'
            . '</body></html>',
            'test',
        );

        return ['sent' => $result['sent'], 'error' => $result['error']];
    }

    /**
     * Check if the mailer is configured and ready to send.
     *
     * - log: always configured (no outbound connection needed)
     * - mailpit: always configured (hardcoded to localhost:1025)
     * - smtp: configured only when smtp_host is set
     */
    public static function isConfigured(): bool
    {
        $config = self::loadConfig();
        $transport = strtolower(trim($config['mail_transport'] ?? 'smtp'));

        return match ($transport) {
            'log', 'mailpit' => true,
            default          => !empty($config['smtp_host']),
        };
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
        $subject = __('email.export_acknowledgment.subject', ['tenant' => $tenantName]);
        $html = self::renderPrivacyEmail(
            __('email.export_acknowledgment.title'),
            __('email.export_acknowledgment.body', ['tenant' => '<strong>' . $tenantName . '</strong>']),
            __('email.export_acknowledgment.footer'),
        );

        return self::send($to, $subject, $html, 'privacy_export', $tenantId);
    }

    /**
     * Send a deletion request acknowledgment to the customer.
     */
    public static function sendDeletionAcknowledgment(string $to, string $tenantName, ?string $tenantId = null): array
    {
        $subject = __('email.deletion_acknowledgment.subject', ['tenant' => $tenantName]);
        $html = self::renderPrivacyEmail(
            __('email.deletion_acknowledgment.title'),
            __('email.deletion_acknowledgment.body', ['tenant' => '<strong>' . $tenantName . '</strong>']),
            __('email.deletion_acknowledgment.footer'),
        );

        return self::send($to, $subject, $html, 'privacy_deletion', $tenantId);
    }

    /**
     * Notify the operator about a new deletion request.
     *
     * @param string      $operatorEmail  The operator's real email (from tenant.notification_email or tenant.email)
     * @param string      $customerName   Customer display name
     * @param string      $customerEmail  Customer email (will be hashed in body)
     * @param string      $tenantName     Tenant display name
     * @param string|null $tenantId       Associated tenant ID
     */
    public static function notifyOperatorDeletionRequest(
        string $operatorEmail,
        string $customerName,
        string $customerEmail,
        string $tenantName,
        ?string $tenantId = null,
    ): array {
        if (empty($operatorEmail)) {
            return ['sent' => false, 'error' => 'No operator email provided', 'log_id' => ''];
        }

        $subject = __('email.operator_deletion.subject', ['customer' => $customerName]);
        $html = self::renderPrivacyEmail(
            __('email.operator_deletion.title'),
            __('email.operator_deletion.body') . "<br><br>"
            . "<strong>" . __('email.operator_deletion.detail_customer') . "</strong> {$customerName}<br>"
            . "<strong>" . __('email.operator_deletion.detail_email') . "</strong> " . AuditLog::hashEmail($customerEmail) . " " . __('email.operator_deletion.detail_hashed') . "<br>"
            . "<strong>" . __('email.operator_deletion.detail_tenant') . "</strong> {$tenantName}",
            __('email.operator_deletion.footer', ['app_name' => app_name()]),
        );

        return self::send($operatorEmail, $subject, $html, 'operator_notification', $tenantId);
    }

    // ── Business user onboarding ──

    /**
     * Send a welcome email to a newly created business user with their login credentials.
     *
     * @param string $to           Business user email
     * @param string $name         Business user display name
     * @param string $tempPassword The temporary plaintext password
     * @param string $loginUrl     Full URL to the login page (e.g. https://app.test/admin/login)
     * @param string $tenantName   Tenant display name
     * @param string $tenantId     Associated tenant ID
     *
     * @return array{sent: bool, error: string|null, log_id: string}
     */
    public static function sendBusinessUserWelcome(
        string $to,
        string $name,
        string $tempPassword,
        string $loginUrl,
        string $tenantName,
        string $tenantId,
    ): array {
        $subject = __('email.business_user_welcome.subject', ['tenant' => $tenantName]);

        $body = __('email.business_user_welcome.greeting', ['name' => $name]) . '<br><br>'
            . __('email.business_user_welcome.body', ['tenant' => '<strong>' . htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') . '</strong>']) . '<br><br>'
            . '<strong>' . __('email.business_user_welcome.detail_email') . '</strong> ' . htmlspecialchars($to, ENT_QUOTES, 'UTF-8') . '<br>'
            . '<strong>' . __('email.business_user_welcome.detail_password') . '</strong> <code style="background:#f4f4f5;padding:2px 6px;border-radius:4px;font-family:monospace;">' . htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8') . '</code><br>'
            . '<strong>' . __('email.business_user_welcome.detail_login') . '</strong> <a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '</a><br><br>'
            . '<em>' . __('email.business_user_welcome.change_password') . '</em>';

        $footer = __('email.business_user_welcome.footer', [
            'app_name' => app_name(),
            'tenant'   => $tenantName,
        ]);

        $html = self::renderPrivacyEmail(
            __('email.business_user_welcome.title', ['tenant' => $tenantName]),
            $body,
            $footer,
        );

        return self::send($to, $subject, $html, 'business_user_welcome', $tenantId);
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

        $keys = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'mail_from_address', 'mail_from_name', 'mail_transport'];
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
        $poweredBy = __('email.common.powered_by', ['app_name' => app_name()]);

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
                    <p style="margin-top: 1rem; font-size: 11px; color: #a1a1aa;">{$poweredBy}</p>
                </td></tr>
            </table>
        </body>
        </html>
        HTML;
    }
}
