export interface RecipeFieldDefinition {
    name: string;
    path: string;
    type: string;
    required: boolean;
    transforms?: Array<Record<string, string>>;
    number_locale?: string;
    date_format?: string;
    boolean_true?: string[];
    boolean_false?: string[];
}

export interface RecipeDefinitionDraft {
    [key: string]: unknown;
    schema_version: number;
    source_type: string;
    url: string;
    connection_id: number | null;
    records_path: string;
    fields: RecipeFieldDefinition[];
    pagination: Record<string, string | number>;
    limits: Record<string, number>;
    validation: { allow_empty: boolean };
    comparison: { identity: string[]; fields: string[] };
    website?: Record<string, unknown>;
    csv?: Record<string, unknown>;
    xml?: Record<string, unknown>;
}

const paginationKeys: Record<string, string[]> = {
    none: [],
    page: ['page_param', 'start', 'step'],
    offset: ['offset_param', 'start', 'step'],
    next_link: ['next_path'],
    next_page: ['next_path'],
    cursor: ['cursor_param', 'cursor_path'],
    load_more: ['next_path', 'max_actions'],
    scroll: ['max_actions'],
};

export function serializeManualDefinition(
    source: RecipeDefinitionDraft,
): RecipeDefinitionDraft {
    const definition = structuredClone(source);
    const mode = String(definition.pagination.mode);
    definition.pagination = Object.fromEntries([
        ['mode', mode],
        ...(paginationKeys[mode] ?? []).map((key) => [
            key,
            ['start', 'step', 'max_actions'].includes(key)
                ? Number(definition.pagination[key])
                : definition.pagination[key],
        ]),
    ]);

    for (const field of definition.fields) {
        if (field.type !== 'number') delete field.number_locale;
        if (field.type !== 'date' || !field.date_format)
            delete field.date_format;
        if (field.type !== 'boolean') {
            delete field.boolean_true;
            delete field.boolean_false;
        } else {
            if (!field.boolean_true?.length) delete field.boolean_true;
            if (!field.boolean_false?.length) delete field.boolean_false;
        }
    }

    if (definition.source_type !== 'website') {
        delete definition.website;
    } else if (definition.website) {
        for (const key of ['signed_in_selector', 'detail_url_selector']) {
            if (definition.website[key] === '') delete definition.website[key];
        }
    }
    if (definition.source_type !== 'csv') delete definition.csv;
    if (definition.source_type !== 'xml') delete definition.xml;

    return definition;
}
