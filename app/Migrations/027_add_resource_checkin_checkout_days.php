<?php

declare(strict_types=1);

/**
 * Migration 027: Add check-in/check-out day restrictions to resources.
 *
 * Allows resource owners to restrict which days of the week guests
 * can check in and check out (e.g. Friday check-in only, Sunday check-out only).
 *
 * Both columns store JSON arrays of integers (0=Sunday … 6=Saturday).
 * NULL means no restriction (any day allowed).
 */
return [
    "ALTER TABLE `resources`
        ADD COLUMN `check_in_days` JSON NULL DEFAULT NULL COMMENT 'Allowed check-in days (0=Sun…6=Sat), NULL=any' AFTER `max_stay_nights`,
        ADD COLUMN `check_out_days` JSON NULL DEFAULT NULL COMMENT 'Allowed check-out days (0=Sun…6=Sat), NULL=any' AFTER `check_in_days`",
];
