export function resolveInitialPartySize(minPartySize = 1, maxPartySize = 8) {
    const min = Math.max(1, Number.parseInt(minPartySize, 10) || 1);
    const max = Math.max(min, Number.parseInt(maxPartySize, 10) || min);
    return Math.min(max, Math.max(min, 2));
}
