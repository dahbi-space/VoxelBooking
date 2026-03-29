// ── Timezone conversion engine ──
// Extracted from app.js for testability.
// All functions are pure: no DOM, no Alpine, no side effects.

/**
 * Convert a date+time from one IANA timezone to another.
 * Returns { date: 'YYYY-MM-DD', time: 'HH:MM' } in the target timezone.
 *
 * @param {string} date - 'YYYY-MM-DD' in fromTz
 * @param {string} time - 'HH:MM' in fromTz
 * @param {string} fromTz - IANA timezone identifier (source)
 * @param {string} toTz - IANA timezone identifier (target)
 * @returns {{ date: string, time: string }}
 */
export function convertTime(date, time, fromTz, toTz) {
    if (fromTz === toTz) return { date, time };

    // Build ISO-like string and parse in source timezone
    // We use Intl.DateTimeFormat to resolve the UTC offset, then convert
    const srcParts = new Date(`${date}T${time}:00`);
    // Get the source and target offsets
    const srcOffset = getTimezoneOffsetMinutes(date, time, fromTz);
    const tgtOffset = getTimezoneOffsetMinutes(date, time, toTz);

    // Convert to UTC, then to target
    const utcMs = srcParts.getTime() + srcOffset * 60000;
    const tgtMs = utcMs - tgtOffset * 60000;
    const tgtDate = new Date(tgtMs);

    return {
        date: `${tgtDate.getFullYear()}-${String(tgtDate.getMonth() + 1).padStart(2, '0')}-${String(tgtDate.getDate()).padStart(2, '0')}`,
        time: `${String(tgtDate.getHours()).padStart(2, '0')}:${String(tgtDate.getMinutes()).padStart(2, '0')}`,
    };
}

/**
 * Get the UTC offset in minutes for a given date/time in a timezone.
 * Positive = behind UTC (e.g. +60 for CET = UTC+1).
 *
 * Uses Intl.DateTimeFormat to resolve the offset without any library.
 *
 * @param {string} date - 'YYYY-MM-DD'
 * @param {string} time - 'HH:MM'
 * @param {string} tz - IANA timezone identifier
 * @returns {number} Offset in minutes
 */
export function getTimezoneOffsetMinutes(date, time, tz) {
    const dtStr = `${date}T${time}:00`;
    const dt = new Date(dtStr);

    // Format in the target timezone to get the local representation
    const formatter = new Intl.DateTimeFormat('en-US', {
        timeZone: tz,
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
        hour12: false,
    });

    const parts = {};
    for (const p of formatter.formatToParts(dt)) {
        parts[p.type] = p.value;
    }

    // Reconstruct what the local time is in that timezone for this UTC instant
    const localStr = `${parts.year}-${parts.month}-${parts.day}T${parts.hour === '24' ? '00' : parts.hour}:${parts.minute}:${parts.second}`;
    const localDt = new Date(localStr);

    // The offset = local interpretation - UTC interpretation
    return (dt.getTime() - localDt.getTime()) / 60000;
}

/**
 * Format a slot time for display in the customer's timezone.
 *
 * @param {string} slotTime - 'HH:MM' in tenant timezone
 * @param {string} slotDate - 'YYYY-MM-DD' in tenant timezone
 * @param {string} tenantTz - IANA timezone identifier
 * @param {string} customerTz - IANA timezone identifier
 * @returns {string} 'HH:MM' in customer timezone
 */
export function formatSlotDisplay(slotTime, slotDate, tenantTz, customerTz) {
    if (tenantTz === customerTz) return slotTime;
    const converted = convertTime(slotDate, slotTime, tenantTz, customerTz);
    return converted.time;
}
