import {
    sampleCsvRows,
    samplePath,
    sampleColumnLabel,
} from '@/lib/source-sample';
import { describe, expect, it } from 'vitest';
import {
    collectorNextAction,
    suggestedCollectorName,
    collectorStage,
    resultEmptyState,
} from '@/lib/collector-workflow';

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
        ['running', 'running'],
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

describe('collector card actions', () => {
    it('prioritizes a tested current draft over the previous collection', () => {
        expect(
            collectorNextAction({
                id: 4,
                review_ready: true,
                active_version: 1,
                last_run: { id: 'old' },
            }),
        ).toEqual({ label: 'redesign.review', href: '/collectors/4' });
    });
    it('resumes an unfinished collector and opens actual results for active collectors', () => {
        expect(
            collectorNextAction({
                id: 4,
                review_ready: false,
                active_version: null,
                last_run: null,
            }).href,
        ).toBe('/collectors/4/setup');
        expect(
            collectorNextAction({
                id: 4,
                review_ready: false,
                active_version: 1,
                last_run: { id: 'actual' },
            }).href,
        ).toBe('/runs/actual');
    });
    it('does not invent a result before the first collection', () => {
        expect(
            collectorNextAction({
                id: 4,
                review_ready: false,
                active_version: 1,
                last_run: null,
            }).href,
        ).toBe('/collectors/4');
    });
});
describe('source samples', () => {
    it('suggests a name only from a valid hostname', () => {
        expect(suggestedCollectorName('https://www.example.com/catalog')).toBe(
            'example.com',
        );
        expect(suggestedCollectorName('not a URL')).toBe('');
    });
    it('reads nested JSON values without treating falsy values as missing', () => {
        expect(
            samplePath(
                { data: [{ price: 0, active: false }] },
                'data[0].price',
            ),
        ).toBe(0);
        expect(
            samplePath({ data: [{ active: false }] }, '$.data[0].active'),
        ).toBe(false);
        expect(samplePath(null, 'data.items')).toBeUndefined();
    });
    it('keeps quoted CSV cells and line breaks intact', () => {
        expect(
            sampleCsvRows(
                'name,price\r\n"Notebook, blue",12\r\n"two\nlines",0',
            ),
        ).toEqual([
            ['name', 'price'],
            ['Notebook, blue', '12'],
            ['two\nlines', '0'],
        ]);
        expect(sampleCsvRows('name;price\n"A ""quote""";3', ';')[1]).toEqual([
            'A "quote"',
            '3',
        ]);
    });
});

it('shows friendly sample headings while distinguishing duplicate nested names', () => {
    expect(sampleColumnLabel('details.title', ['details.title', 'price'])).toBe(
        'title',
    );
    expect(sampleColumnLabel('./ns:price', ['./ns:price'])).toBe('price');
    expect(
        sampleColumnLabel('seller.name', ['seller.name', 'buyer.name']),
    ).toBe('seller › name');
    expect(sampleColumnLabel('Product name', ['Product name'])).toBe(
        'Product name',
    );
});
