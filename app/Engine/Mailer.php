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
        ?string $replyToEmail = null,
        ?string $replyToName = null,
        ?string $fromName = null,
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

        // Apply transport-specific config overrides (e.g. mailpit → localhost:1025)
        $config = self::resolveEffectiveConfig($config);

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

            // Sender — tenant-scoped emails override the display name
            $fromAddress   = $config['mail_from_address'] ?: 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $effectiveName = self::resolveEffectiveFromName($fromName, $config);
            $mail->setFrom($fromAddress, $effectiveName);

            // Reply-To: tenant contact email so customer replies reach the business
            if ($replyToEmail !== null && $replyToEmail !== '') {
                $mail->addReplyTo($replyToEmail, $replyToName ?? '');
            }

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
     * Send a booking confirmation email to the customer.
     *
     * Uses all five `email.booking_confirmation.*` translation keys and renders
     * via `renderConfirmationEmail()` (branded layout) with an explicit
     * plain-text fallback via `renderConfirmationPlainText()`.
     *
     * Brand color is sanitized via BrandColorHelper::derive() before injection.
     *
     * @param string      $to             Customer email
     * @param string      $customerName   Customer display name (for greeting)
     * @param array       $booking        Booking data (date, formatted_date, time, end_time)
     * @param string|null $serviceName    Service name
     * @param string|null $staffName      Staff name
     * @param string      $tenantName     Tenant/business display name
     * @param string      $tenantId       Tenant ULID
     * @param string      $bookingId      Booking ULID
     * @param string      $brandColor     Tenant brand_color hex (sanitized internally)
     *
     * @return array{sent: bool, error: string|null, log_id: string}
     */
    public static function sendBookingConfirmation(
        string $to,
        string $customerName,
        array $booking,
        ?string $serviceName,
        ?string $staffName,
        string $tenantName,
        string $tenantId,
        string $bookingId,
        string $brandColor = '#2563EB',
    ): array {
        // Sanitize brand color — rejects non-hex input, falls back to default blue
        $brandTokens = BrandColorHelper::derive($brandColor);
        $safeBrandColor = $brandTokens['brand'];

        $subject = __('email.booking_confirmation.subject', [
            'service' => $serviceName ?? $tenantName,
            'date'    => $booking['date'],
        ]);

        $heading        = __('email.booking_confirmation.body');
        $greeting       = __('email.booking_confirmation.greeting', ['name' => $customerName]);
        $bodyText       = __('email.booking_confirmation.body');
        $detailsHeading = __('email.booking_confirmation.details');
        $footer         = __('email.booking_confirmation.footer');

        // Build ordered detail rows for the summary card
        $displayDate = $booking['formatted_date'] ?? $booking['date'];
        $details = [];
        $details[__('email.common.date')] = $displayDate;
        $details[__('email.common.time')] = $booking['time'] . "\xE2\x80\x93" . $booking['end_time'];
        if ($serviceName) {
            $details[__('email.common.service')] = $serviceName;
        }
        if ($staffName) {
            $details[__('email.common.staff')] = $staffName;
        }

        $html = self::renderConfirmationEmail(
            $safeBrandColor, $heading, $greeting, $bodyText,
            $detailsHeading, $details, $footer, $tenantName, app_name(),
        );

        $poweredBy = __('email.common.powered_by', ['app_name' => app_name()]);
        $plainBody = self::renderConfirmationPlainText(
            $heading, $greeting, $bodyText, $detailsHeading,
            $details, $footer, $tenantName, $poweredBy,
        );

        // Resolve tenant Reply-To and From name: customer sees the business name
        $replyTo = self::resolveTenantReplyTo($tenantId);

        return self::send($to, $subject, $html, 'confirmation', $tenantId, $bookingId, $plainBody, $replyTo['email'], $replyTo['name'], $tenantName);
    }

    /**
     * Send a waitlist notification email to the customer.
     *
     * Same branded layout as booking confirmation, but with waitlist-specific
     * subject, heading, and body text so the customer knows they are on the
     * waitlist rather than confirmed.
     */
    public static function sendWaitlistConfirmation(
        string $to,
        string $customerName,
        array $booking,
        ?string $eventName,
        string $tenantName,
        string $tenantId,
        string $bookingId,
        string $brandColor = '#2563EB',
    ): array {
        $brandTokens = BrandColorHelper::derive($brandColor);
        $safeBrandColor = $brandTokens['brand'];

        $subject = __('email.waitlist_confirmation.subject', [
            'event' => $eventName ?? $tenantName,
        ]);

        $heading        = __('email.waitlist_confirmation.heading');
        $greeting       = __('email.waitlist_confirmation.greeting', ['name' => $customerName]);
        $bodyText       = __('email.waitlist_confirmation.body');
        $detailsHeading = __('email.booking_confirmation.details');
        $footer         = __('email.waitlist_confirmation.footer');

        $displayDate = $booking['formatted_date'] ?? $booking['date'];
        $details = [];
        $details[__('email.common.date')] = $displayDate;
        $details[__('email.common.time')] = $booking['time'] . "\xE2\x80\x93" . $booking['end_time'];
        if ($eventName) {
            $details[__('email.common.service')] = $eventName;
        }

        $html = self::renderConfirmationEmail(
            $safeBrandColor, $heading, $greeting, $bodyText,
            $detailsHeading, $details, $footer, $tenantName, app_name(),
        );

        $poweredBy = __('email.common.powered_by', ['app_name' => app_name()]);
        $plainBody = self::renderConfirmationPlainText(
            $heading, $greeting, $bodyText, $detailsHeading,
            $details, $footer, $tenantName, $poweredBy,
        );

        $replyTo = self::resolveTenantReplyTo($tenantId);

        return self::send($to, $subject, $html, 'waitlist', $tenantId, $bookingId, $plainBody, $replyTo['email'], $replyTo['name'], $tenantName);
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
     * Whether the active transport delivers email to a real customer inbox.
     *
     * Only 'smtp' with a configured host qualifies. 'mailpit' is a local dev
     * capture tool (localhost:1025) and 'log' records without sending.
     * Neither reaches the customer.
     */
    public static function isProductionSmtp(): bool
    {
        $config = self::loadConfig();
        $transport = strtolower(trim($config['mail_transport'] ?? 'smtp'));

        return $transport === 'smtp' && !empty($config['smtp_host']);
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

        $replyTo = self::resolveTenantReplyTo($tenantId);

        return self::send($to, $subject, $html, 'privacy_export', $tenantId, null, null, $replyTo['email'], $replyTo['name'], $tenantName);
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

        $replyTo = self::resolveTenantReplyTo($tenantId);

        return self::send($to, $subject, $html, 'privacy_deletion', $tenantId, null, null, $replyTo['email'], $replyTo['name'], $tenantName);
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

        $replyTo = self::resolveTenantReplyTo($tenantId);

        return self::send($operatorEmail, $subject, $html, 'operator_notification', $tenantId, null, null, $replyTo['email'], $replyTo['name'], $tenantName);
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

        $replyTo = self::resolveTenantReplyTo($tenantId);

        return self::send($to, $subject, $html, 'business_user_welcome', $tenantId, null, null, $replyTo['email'], $replyTo['name'], $tenantName);
    }

    // ── Passwordless login emails ──

    /**
     * Send a 6-digit OTP login code.
     *
     * @return array{sent: bool, error: string|null, log_id: string}
     */
    public static function sendLoginCode(string $to, string $code): array
    {
        $appName = app_name();
        $subject = __('auth.otp_email_subject', ['app_name' => $appName]);

        $body = __('auth.otp_email_body') . '<br><br>'
            . '<div style="font-size: 32px; font-weight: 700; letter-spacing: 0.2em; text-align: center; color: #111827; padding: 16px 0;">'
            . htmlspecialchars($code, ENT_QUOTES, 'UTF-8')
            . '</div><br>'
            . '<em>' . __('auth.otp_email_expiry') . '</em><br><br>'
            . __('auth.otp_email_ignore');

        $html = self::renderPrivacyEmail(
            __('auth.otp_email_subject', ['app_name' => $appName]),
            $body,
            __('auth.footer', ['app_name' => $appName]),
        );

        return self::send($to, $subject, $html, 'login_code');
    }

    /**
     * Send a magic login link.
     *
     * @return array{sent: bool, error: string|null, log_id: string}
     */
    public static function sendMagicLink(string $to, string $link): array
    {
        $appName = app_name();
        $subject = __('auth.magic_link_email_subject', ['app_name' => $appName]);

        $body = __('auth.magic_link_email_body') . '<br><br>'
            . '<div style="text-align: center; padding: 16px 0;">'
            . '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display: inline-block; padding: 12px 32px; background: #2563EB; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px;">'
            . __('auth.magic_link_email_cta', ['app_name' => $appName])
            . '</a></div><br>'
            . '<em>' . __('auth.magic_link_email_expiry') . '</em><br><br>'
            . __('auth.magic_link_email_ignore');

        $html = self::renderPrivacyEmail(
            __('auth.magic_link_email_subject', ['app_name' => $appName]),
            $body,
            __('auth.footer', ['app_name' => $appName]),
        );

        return self::send($to, $subject, $html, 'magic_link');
    }

    // ── Internal helpers ──

    /**
     * Resolve tenant email and name for Reply-To header.
     *
     * Looks up the tenant's contact email (notification_email or email) and
     * business name. Returns null values if tenant not found or tenantId is null.
     *
     * @return array{email: string|null, name: string|null}
     */
    private static function resolveTenantReplyTo(?string $tenantId): array
    {
        if ($tenantId === null) {
            return ['email' => null, 'name' => null];
        }

        try {
            $rows = Database::query(
                'SELECT `email`, `name`, `notification_email` FROM `tenants` WHERE `id` = ? LIMIT 1',
                [$tenantId]
            );

            if (empty($rows)) {
                return ['email' => null, 'name' => null];
            }

            $tenant = $rows[0];
            // Prefer notification_email (explicit contact address), fall back to tenant email
            $email = !empty($tenant['notification_email']) ? $tenant['notification_email'] : $tenant['email'];

            return ['email' => $email, 'name' => $tenant['name']];
        } catch (\Throwable) {
            // Database not available — skip Reply-To silently
            return ['email' => null, 'name' => null];
        }
    }

    /**
     * Resolve the effective From display name.
     *
     * Priority: explicit $fromName (tenant business name for tenant-scoped emails)
     * → global mail_from_name setting → app_name() fallback.
     *
     * Public so the unit test suite can verify resolution without requiring
     * a live SMTP connection.
     *
     * @param string|null         $fromName Explicit override (tenant name), or null for global
     * @param array<string,string>|null $config  SMTP config array; loaded from settings if null
     */
    public static function resolveEffectiveFromName(?string $fromName, ?array $config = null): string
    {
        if ($fromName !== null && $fromName !== '') {
            return $fromName;
        }

        $config ??= self::loadConfig();
        $globalName = $config['mail_from_name'] ?? '';

        return $globalName !== '' ? $globalName : app_name();
    }

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
     * Apply transport-specific config overrides.
     *
     * For 'mailpit': overrides SMTP host/port/auth/encryption to localhost:1025.
     * For 'log': no overrides (log transport early-returns before config is used).
     * For 'smtp'/default: no overrides (uses operator-configured values).
     *
     * @param array<string, string> $config Raw config from loadConfig()
     * @return array<string, string> Effective config with transport overrides applied
     */
    private static function resolveEffectiveConfig(array $config): array
    {
        $transport = strtolower(trim($config['mail_transport'] ?? 'smtp'));

        if ($transport === 'mailpit') {
            $config['smtp_host']       = '127.0.0.1';
            $config['smtp_port']       = '1025';
            $config['smtp_username']   = '';
            $config['smtp_password']   = '';
            $config['smtp_encryption'] = 'none';
        }

        return $config;
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
     * Render a branded booking confirmation email.
     *
     * System-owned layout: branded header bar, status check, summary card, footer.
     * All CSS is inline for email client compatibility.
     *
     * @param string $brandColor      Sanitized hex color (e.g. "#2563EB")
     * @param string $heading         Confirmation heading text
     * @param string $greeting        Customer greeting (e.g. "Hi Emma,")
     * @param string $bodyText        Confirmation body copy
     * @param string $detailsHeading  Section label above summary card (e.g. "Booking details")
     * @param array  $details         Ordered label→value pairs for summary card
     * @param string $footerText      Contact/change instruction text
     * @param string $tenantName      Business display name for footer
     * @param string $appName         App name for "Powered by" line
     */
    private static function renderConfirmationEmail(
        string $brandColor,
        string $heading,
        string $greeting,
        string $bodyText,
        string $detailsHeading,
        array $details,
        string $footerText,
        string $tenantName,
        string $appName,
    ): string {
        $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $font = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";

        // Build detail rows
        $detailRows = '';
        $i = 0;
        foreach ($details as $label => $value) {
            $topPad = $i > 0 ? '16px' : '0';
            $detailRows .= '<tr><td style="padding-top: ' . $topPad . '; font-size: 13px; color: #6B7280; font-weight: 500; font-family: ' . $font . '; vertical-align: top; width: 100px;">' . $h($label) . '</td>'
                . '<td style="padding-top: ' . $topPad . '; font-size: 15px; color: #111827; font-weight: 500; font-family: ' . $font . '; vertical-align: top;">' . $h($value) . '</td></tr>';
            $i++;
        }

        $poweredBy = __('email.common.powered_by', ['app_name' => $appName]);

        return <<<HTML
        <html>
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
        <body style="margin: 0; padding: 0; font-family: {$font}; background: #F3F4F6;">
            <table width="100%" cellpadding="0" cellspacing="0" style="padding: 32px 16px;">
                <tr><td align="center">
                    <table width="560" cellpadding="0" cellspacing="0" style="background: #FFFFFF; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                        <!-- Branded header bar -->
                        <tr><td style="height: 40px; background: {$brandColor};"></td></tr>

                        <!-- Status + Heading -->
                        <tr><td style="padding: 32px 32px 0; text-align: center;">
                            <div style="display: inline-block; width: 40px; height: 40px; line-height: 40px; border-radius: 50%; background: #ECFDF5; color: #059669; font-size: 20px; font-weight: 700; text-align: center;">✓</div>
                            <h1 style="margin: 16px 0 0; font-size: 22px; font-weight: 700; color: #111827; line-height: 1.3; font-family: {$font};">{$h($heading)}</h1>
                        </td></tr>

                        <!-- Greeting + Body -->
                        <tr><td style="padding: 24px 32px 0; text-align: center;">
                            <p style="margin: 0; font-size: 15px; color: #374151; line-height: 1.5; font-family: {$font};">{$h($greeting)}<br>{$h($bodyText)}</p>
                        </td></tr>

                        <!-- Summary card -->
                        <tr><td style="padding: 24px 32px 0;">
                            <p style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #6B7280; text-transform: uppercase; letter-spacing: 0.05em; font-family: {$font};">{$h($detailsHeading)}</p>
                        </td></tr>
                        <tr><td style="padding: 0 32px 24px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px;">
                                <tr><td style="padding: 20px 24px;">
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        {$detailRows}
                                    </table>
                                </td></tr>
                            </table>
                        </td></tr>

                        <!-- Footer text -->
                        <tr><td style="padding: 0 32px 24px; text-align: center;">
                            <p style="margin: 0; font-size: 14px; color: #6B7280; line-height: 1.5; font-family: {$font};">{$h($footerText)}</p>
                        </td></tr>

                        <!-- Business footer -->
                        <tr><td style="padding: 16px 32px; border-top: 1px solid #E5E7EB; text-align: center;">
                            <p style="margin: 0 0 4px; font-size: 13px; color: #6B7280; font-family: {$font};">{$h($tenantName)}</p>
                            <p style="margin: 0; font-size: 11px; color: #9CA3AF; font-family: {$font};">{$h($poweredBy)}</p>
                        </td></tr>
                    </table>
                </td></tr>
            </table>
        </body>
        </html>
        HTML;
    }

    /**
     * Render the plain-text version of a booking confirmation email.
     *
     * Used as the AltBody for email clients that strip HTML.
     * Tested via reflection to ensure label:value rows don't collapse.
     */
    private static function renderConfirmationPlainText(
        string $heading,
        string $greeting,
        string $bodyText,
        string $detailsHeading,
        array $details,
        string $footerText,
        string $tenantName,
        string $poweredBy,
    ): string {
        $lines = [];
        $lines[] = mb_strtoupper($heading);
        $lines[] = '';
        $lines[] = $greeting;
        $lines[] = $bodyText;
        $lines[] = '';
        $lines[] = $detailsHeading;
        foreach ($details as $label => $value) {
            $lines[] = $label . ': ' . $value;
        }
        $lines[] = '';
        $lines[] = $footerText;
        $lines[] = '';
        $lines[] = '—';
        $lines[] = $tenantName;
        $lines[] = $poweredBy;

        return implode("\n", $lines);
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
