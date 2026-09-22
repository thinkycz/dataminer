import { describe, expect, it } from 'vitest';
import {
    serializeManualDefinition,
    type RecipeDefinitionDraft,
} from '@/lib/recipe-definition';

function definition(
    overrides: Partial<RecipeDefinitionDraft> = {},
): RecipeDefinitionDraft {
    return {
        schema_version: 1,
        source_type: 'json',
        url: 'https://example.test/data.json',
        connection_id: null,
        records_path: 'data.items',
        fields: [
            { name: 'title', path: 'title', type: 'string', required: true },
        ],
        pagination: { mode: 'none' },
        limits: {
            rows: 100,
            bytes: 10000,
            requests: 10,
            pages: 5,
            seconds: 60,
        },
        validation: { allow_empty: false },
        comparison: { identity: [], fields: [] },
        ...overrides,
    };
}

describe('manual recipe definition serialization', () => {
    it('removes settings for other source formats and stale pagination fields', () => {
        const result = serializeManualDefinition(
            definition({
                pagination: {
                    mode: 'page',
                    page_param: 'page',
                    start: '1',
                    step: '2',
                    cursor_path: 'old',
                },
                csv: { delimiter: ';' },
                xml: { namespaces: {} },
                website: { record_selector: 'article' },
            }),
        );

        expect(result.pagination).toEqual({
            mode: 'page',
            page_param: 'page',
            start: 1,
            step: 2,
        });
        expect(result).not.toHaveProperty('csv');
        expect(result).not.toHaveProperty('xml');
        expect(result).not.toHaveProperty('website');
    });

    it('retains the website detail fields while omitting blank optional selectors', () => {
        const detailField = {
            name: 'sku',
            path: '.sku',
            type: 'string',
            required: true,
        };
        const result = serializeManualDefinition(
            definition({
                source_type: 'website',
                website: {
                    record_selector: 'article.product',
                    detail_url_selector: '',
                    signed_in_selector: '',
                    detail_fields: [detailField],
                },
                pagination: {
                    mode: 'load_more',
                    max_actions: '3',
                    next_path: 'next',
                },
            }),
        );

        expect(result.website).toEqual({
            record_selector: 'article.product',
            detail_fields: [detailField],
        });
        expect(result.pagination).toEqual({
            mode: 'load_more',
            max_actions: 3,
            next_path: 'next',
        });
    });
});
