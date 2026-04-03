import { describe, expect, it } from 'vitest';
import { resolveInitialPartySize } from '../../resources/js/booking/party-size.js';

describe('resolveInitialPartySize', () => {
    it('clamps the default to the tenant max party size', () => {
        expect(resolveInitialPartySize(1, 1)).toBe(1);
    });

    it('respects a minimum that is higher than the default seed', () => {
        expect(resolveInitialPartySize(3, 6)).toBe(3);
    });

    it('keeps the familiar default of 2 when it is within bounds', () => {
        expect(resolveInitialPartySize(1, 8)).toBe(2);
    });
});
