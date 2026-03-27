<?php

declare(strict_types=1);

namespace App\Engine;

use Ulid\Ulid as UlidLib;

/**
 * ULID generation wrapper.
 *
 * All primary keys in VoxelBooking are ULIDs stored as CHAR(26) ascii.
 * Crockford Base32, timestamp-sortable, globally unique.
 */
final class Ulid
{
    public static function generate(): string
    {
        return strtoupper((string) UlidLib::generate());
    }
}
