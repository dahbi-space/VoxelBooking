<?php

declare(strict_types=1);

/**
 * Migration 007: Create service_staff pivot table.
 *
 * Links services to the staff members who can perform them.
 */
return [
    "CREATE TABLE IF NOT EXISTS `service_staff` (
        `service_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `staff_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        PRIMARY KEY (`service_id`, `staff_id`),
        CONSTRAINT `service_staff_service_fk` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
        CONSTRAINT `service_staff_staff_fk` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
