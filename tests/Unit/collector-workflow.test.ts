import { describe, expect, it } from 'vitest';
import { collectorStage, resultEmptyState } from '@/lib/collector-workflow';

describe('collector next step', () => {
    it('distinguishes code approval from sample approval', () => {
        expect(collectorStage('pending_approval', true, [], null)).toBe(
            'approval',
        );
        expect(
            collectorStage(
                'pending_approval',
                false,
                [{ id: 2, status: 'tested', sample_run_id: 'sample' }],
                null,
            ),
        ).toBe('sample');
    });
    it('does not offer an old tested version as the next step when an active version exists', () => {
        expect(
            collectorStage(
                'ready',
                false,
                [
                    { id: 2, status: 'approved', sample_run_id: 'two' },
                    { id: 1, status: 'tested', sample_run_id: 'one' },
                ],
                2,
            ),
        ).toBe('ready');
    });
    it('shows a repaired sample for review even with an older active version', () => {
        expect(
            collectorStage(
                'pending_approval',
                false,
                [
                    { id: 2, status: 'tested', sample_run_id: 'two' },
                    { id: 1, status: 'approved', sample_run_id: 'one' },
                ],
                1,
            ),
        ).toBe('sample');
    });
    it.each(['generating', 'testing'] as const)(
        'keeps %s work visible after reload',
        (status) => {
            expect(collectorStage(status, false, [], 1)).toBe(status);
        },
    );
    it('recovers from rejected and failed preparation without pretending it is ready', () => {
        expect(collectorStage('failed', false, [], null)).toBe('failed');
        expect(collectorStage('pending_approval', false, [], null)).toBe(
            'rejected',
        );
        expect(
            collectorStage(
                'pending_approval',
                false,
                [{ id: 1, status: 'rejected', sample_run_id: null }],
                null,
            ),
        ).toBe('rejected');
    });
});
describe('empty results', () => {
    it.each([
        ['queued', 'waiting'],
        ['running', 'waiting'],
        ['completed', 'empty'],
        ['failed', 'failed'],
        ['cancelled', 'cancelled'],
    ])(
        'explains %s without suggesting a filter problem',
        (status, expected) => {
            expect(resultEmptyState(status!, false)).toBe(expected);
        },
    );
    it('offers filter recovery when no rows match', () => {
        expect(resultEmptyState('completed', true)).toBe('filtered');
    });
});
