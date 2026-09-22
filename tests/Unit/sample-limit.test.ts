import { describe, expect, it } from 'vitest';
import { isCompletedSample } from '../../resources/scraping/sample-limit.mjs';

describe('bounded test samples', () => {
    const rowLimit = new Error('row limit');
    const blockedRequest = new Error('page.goto: net::ERR_BLOCKED_BY_CLIENT');

    it('preserves a nonempty sample at its configured row or request limit', () => {
        expect(isCompletedSample('test', 100, false, rowLimit, rowLimit)).toBe(
            true,
        );
        expect(
            isCompletedSample('test', 24, true, blockedRequest, rowLimit),
        ).toBe(true);
    });

    it('does not claim an empty sample or a truncated full run is complete', () => {
        expect(
            isCompletedSample('test', 0, true, blockedRequest, rowLimit),
        ).toBe(false);
        expect(
            isCompletedSample('full', 24, true, blockedRequest, rowLimit),
        ).toBe(false);
        expect(isCompletedSample('full', 100, false, rowLimit, rowLimit)).toBe(
            false,
        );
    });

    it('does not hide page failures or unrelated errors', () => {
        expect(
            isCompletedSample('test', 24, false, blockedRequest, rowLimit),
        ).toBe(false);
        expect(
            isCompletedSample('test', 24, true, new Error('Timeout'), rowLimit),
        ).toBe(false);
        expect(isCompletedSample('test', 24, true, null, rowLimit)).toBe(false);
    });
});
