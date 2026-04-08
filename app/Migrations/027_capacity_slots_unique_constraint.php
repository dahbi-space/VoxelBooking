<?php

declare(strict_types=1);

/**
 * Migration 027: Add unique constraint to capacity_slots.
 *
 * Prevents exact duplicate slots (same tenant + day + start + end).
 * Overlapping but non-identical slots are allowed by design — venues
 * may offer "Early Dinner" (18:00–19:30) and "Full Evening" (18:00–22:00)
 * as separate capacity windows.
 */
return [
    "ALTER TABLE `capacity_slots`
     ADD UNIQUE INDEX `uq_capacity_slots_window` (`tenant_id`, `day_of_week`, `start_time`, `end_time`)"
];
