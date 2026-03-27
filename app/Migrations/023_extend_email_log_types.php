<?php

declare(strict_types=1);

/**
 * Migration 023: Extend email_log type ENUM for privacy and operator notifications.
 *
 * Adds: 'privacy_export', 'privacy_deletion', 'operator_notification', 'test'
 */
return [
    "ALTER TABLE `email_log` MODIFY COLUMN `type` ENUM(
        'confirmation','reminder','cancellation','reschedule','staff_notification',
        'privacy_export','privacy_deletion','operator_notification','test'
    ) NOT NULL",
];
