<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import { computed, reactive, ref, toRaw } from 'vue';
import { ScanSearch } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import ActionErrors from '@/components/ui/ActionErrors.vue';
import {
    serializeManualDefinition,
    type RecipeDefinitionDraft,
    type RecipeFieldDefinition,
} from '@/lib/recipe-definition';

type Field = RecipeFieldDefinition;
type Definition = RecipeDefinitionDraft;
interface Connection {
    id: number;
    name: string;
    origin: string;
    kind: string;
    status: string;
}
const props = defineProps<{
    recipe: { id: number; name: string; start_url: string };
    definition: Definition | null;
    connections: Connection[];
}>();
const { t } = useI18n();
const processing = ref(false);
const selected = ref('');
type PickMode = 'records' | 'fields' | 'login' | 'detail' | 'pagination';
const pickMode = ref<PickMode>('records');
const sampleData = ref<unknown>(null);
const sampleError = ref('');
const definitionError = ref('');
const browserError = ref('');
const browserBusy = ref(false);
const browserReady = ref(false);
const visualSection = ref<HTMLElement | null>(null);
const browser = reactive({
    sessionId: '',
    screenshot: '',
    accessChallenge: false,
    viewport: { width: 1, height: 1 },
    candidates: [] as Array<{
        selector: string;
        tag: string;
        text: string;
        count: number;
    }>,
    matches: [] as Array<{
        index: number;
        x: number;
        y: number;
        width: number;
        height: number;
    }>,
});
const pickLabels: Record<
    PickMode,
    { title: string; hint: string; action: string }
> = {
    records: {
        title: 'builder.pick_records_step',
        hint: 'builder.click_record_hint',
        action: 'builder.use_for_record',
    },
    fields: {
        title: 'builder.pick_columns_step',
        hint: 'builder.click_field_hint',
        action: 'builder.add_as_column',
    },
    login: {
        title: 'builder.login_pick_step',
        hint: 'builder.click_login_hint',
        action: 'builder.use_for_login',
    },
    detail: {
        title: 'builder.detail_pick_step',
        hint: 'builder.click_detail_hint',
        action: 'builder.use_for_detail',
    },
    pagination: {
        title: 'builder.pagination_pick_step',
        hint: 'builder.click_pagination_hint',
        action: 'builder.use_for_pagination',
    },
};
const pickTitle = computed(() =>
    t(
        pickMode.value === 'fields' && selected.value
            ? 'builder.field_selected'
            : pickLabels[pickMode.value].title,
    ),
);
const pickHint = computed(() =>
    t(
        browser.candidates.length
            ? 'builder.choose_element'
            : pickLabels[pickMode.value].hint,
    ),
);
const pickAction = computed(() =>
    t(
        pickMode.value === 'fields' && selected.value
            ? 'builder.use_for_field'
            : pickLabels[pickMode.value].action,
    ),
);
const visibleCandidates = computed(() => {
    if (pickMode.value !== 'fields' || browser.matches.length === 0)
        return browser.candidates;
    const recordCount = browser.matches.length;
    return [...browser.candidates].sort(
        (left, right) =>
            Number(right.count === recordCount) -
            Number(left.count === recordCount),
    );
});
const connectionForm = reactive({
    kind: 'bearer',
    token: '',
    header: 'Authorization',
    username: '',
    password: '',
});
const form = reactive<Definition>(
    props.definition
        ? structuredClone(toRaw(props.definition))
        : {
              schema_version: 1,
              source_type: 'json',
              url: props.recipe.start_url,
              connection_id: null,
              records_path: '',
              fields: [
                  {
                      name: 'name',
                      path: 'name',
                      type: 'string',
                      required: true,
                      transforms: [{ op: 'trim' }],
                  },
              ],
              pagination: { mode: 'none' },
              limits: {
                  rows: 10000,
                  bytes: 50000000,
                  requests: 100,
                  pages: 100,
                  seconds: 600,
              },
              validation: { allow_empty: false },
              comparison: { identity: [], fields: [] },
              csv: { delimiter: ',', encoding: 'UTF-8' },
              xml: { namespaces: {} },
              website: {
                  record_selector: 'body',
                  detail_fields: [],
                  signed_in_selector: '',
              },
          },
);
if (
    form.connection_id !== null &&
    !props.connections.some(
        (connection) =>
            connection.id === form.connection_id &&
            connection.status === 'ready',
    )
)
    form.connection_id = null;
if (form.source_type === 'csv')
    form.csv ??= { delimiter: ',', encoding: 'UTF-8' };
if (form.source_type === 'xml') form.xml ??= { namespaces: {} };
if (form.source_type === 'website')
    form.website ??= {
        record_selector: 'body',
        detail_fields: [],
        signed_in_selector: '',
    };
if (
    form.source_type === 'website' &&
    form.website?.record_selector &&
    form.website.record_selector !== 'body'
)
    pickMode.value = 'fields';
const detailFields = ref<Field[]>(
    Array.isArray(form.website?.detail_fields)
        ? (form.website.detail_fields as Field[])
        : [],
);
const xmlNamespacesText = computed({
    get: () => JSON.stringify(form.xml?.namespaces ?? {}, null, 2),
    set: (value: string) => {
        try {
            const namespaces: unknown = JSON.parse(value || '{}');
            if (
                !namespaces ||
                typeof namespaces !== 'object' ||
                Array.isArray(namespaces)
            )
                throw new Error('shape');
            form.xml = {
                ...(form.xml ?? {}),
                namespaces: namespaces as Record<string, string>,
            };
            definitionError.value = '';
        } catch {
            definitionError.value = t('builder.invalid_namespaces');
        }
    },
});
const sourceOptions = ['json', 'csv', 'xml', 'website'];
const canSave = computed(
    () =>
        form.fields.length > 0 &&
        !!form.url &&
        (form.source_type !== 'website' ||
            !!(form.website?.record_selector as string | undefined)?.trim()),
);
const canPreview = computed(
    () =>
        canSave.value &&
        (form.source_type !== 'website' ||
            form.fields.every(
                (field) =>
                    field.path.trim() !== '' &&
                    !(field.name === 'name' && field.path === 'name'),
            )),
);
function parseXmlSample(): Document | null {
    if (
        form.source_type !== 'xml' ||
        typeof sampleData.value !== 'string' ||
        /<!\s*(?:DOCTYPE|ENTITY)/i.test(sampleData.value)
    )
        return null;
    const document = new DOMParser().parseFromString(
        sampleData.value,
        'application/xml',
    );
    return document.querySelector('parsererror') ? null : document;
}
const sampleRecordsPaths = computed(() => {
    if (form.source_type === 'xml') {
        const document = parseXmlSample();
        if (!document?.documentElement) return [];
        const paths: string[] = [];
        const visit = (element: Element, path: string, depth: number): void => {
            if (depth > 10) return;
            const children = [...element.children];
            for (const child of children) {
                const childPath = `${path}/${child.tagName}`;
                if (
                    children.filter((item) => item.tagName === child.tagName)
                        .length > 1
                )
                    paths.push(childPath);
                visit(child, childPath, depth + 1);
            }
        };
        const root = document.documentElement;
        visit(root, `/${root.tagName}`, 0);
        return [...new Set(paths)];
    }
    const paths: string[] = [];
    const visit = (value: unknown, path: string, depth: number): void => {
        if (depth > 4 || value === null || typeof value !== 'object') return;
        if (Array.isArray(value)) {
            if (
                value.length &&
                typeof value[0] === 'object' &&
                value[0] !== null &&
                !Array.isArray(value[0])
            )
                paths.push(path);
            return;
        }
        for (const [key, child] of Object.entries(value))
            visit(child, path ? `${path}.${key}` : key, depth + 1);
    };
    visit(sampleData.value, '', 0);
    return paths;
});
function csvHeaders(sample: string, delimiter: string): string[] {
    if (delimiter.length !== 1) return [];
    const headers: string[] = [];
    let value = '';
    let quoted = false;
    let ended = false;
    for (let index = 0; index < Math.min(sample.length, 50_000); index++) {
        const character = sample[index];
        if (character === '"') {
            if (quoted && sample[index + 1] === '"') {
                value += '"';
                index++;
            } else quoted = !quoted;
        } else if (character === delimiter && !quoted) {
            headers.push(value.trim());
            value = '';
        } else if ((character === '\n' || character === '\r') && !quoted) {
            headers.push(value.trim());
            ended = true;
            break;
        } else value += character;
    }
    if (!ended) headers.push(value.trim());
    return headers
        .map((header) => header.replace(/^\uFEFF/, ''))
        .filter(Boolean);
}
const sampleFieldPaths = computed(() => {
    if (form.source_type === 'csv')
        return typeof sampleData.value === 'string'
            ? csvHeaders(sampleData.value, String(form.csv?.delimiter ?? ','))
            : [];
    if (form.source_type === 'xml') {
        const document = parseXmlSample();
        if (!document) return [];
        try {
            const namespaces = (form.xml?.namespaces ?? {}) as Record<
                string,
                string
            >;
            const resolver = (prefix: string | null): string | null =>
                prefix ? (namespaces[prefix] ?? null) : null;
            const records = document.evaluate(
                form.records_path || `/${document.documentElement.tagName}`,
                document,
                resolver,
                XPathResult.ORDERED_NODE_SNAPSHOT_TYPE,
                null,
            );
            const record = records.snapshotItem(0);
            if (!record) return [];
            const paths = [...record.childNodes]
                .filter((node) => node.nodeType === Node.ELEMENT_NODE)
                .map((node) => `./${(node as Element).tagName}`);
            for (const attribute of [...(record as Element).attributes])
                paths.push(`./@${attribute.name}`);
            return paths;
        } catch {
            return [];
        }
    }
    if (!sampleData.value || typeof sampleData.value !== 'object') return [];
    let record: unknown = sampleData.value;
    for (const segment of form.records_path.split('.').filter(Boolean)) {
        if (record === null || typeof record !== 'object') return [];
        record = (record as Record<string, unknown>)[segment];
    }
    const row = Array.isArray(record) ? record[0] : record;
    return row && typeof row === 'object' ? Object.keys(row) : [];
});
const paginationKeys = computed(() => {
    const map: Record<string, string[]> = {
        page: ['page_param', 'start', 'step'],
        offset: ['offset_param', 'start', 'step'],
        next_link: ['next_path'],
        next_page: ['next_path'],
        cursor: ['cursor_param', 'cursor_path'],
        load_more: ['next_path', 'max_actions'],
        scroll: ['max_actions'],
    };
    return map[String(form.pagination.mode)] ?? [];
});
const loginForm = reactive({ selector: '', value: '' });
function addField(): void {
    form.fields.push({
        name: `field_${form.fields.length + 1}`,
        path: '',
        type: 'string',
        required: false,
        transforms: [{ op: 'trim' }],
    });
}
function addDetailField(): void {
    detailFields.value.push({
        name: `detail_${detailFields.value.length + 1}`,
        path: '',
        type: 'string',
        required: false,
        transforms: [{ op: 'trim' }],
    });
}
function removeField(index: number): void {
    if (form.fields.length > 1) form.fields.splice(index, 1);
}
function changeSource(sourceType: string): void {
    form.source_type = sourceType;
    if (sourceType === 'website')
        pickMode.value =
            form.website?.record_selector &&
            form.website.record_selector !== 'body'
                ? 'fields'
                : 'records';
    form.records_path =
        sourceType === 'csv' || sourceType === 'website'
            ? ''
            : form.records_path;
    form.pagination = { mode: 'none' };
    if (sourceType === 'website')
        form.website = {
            ...(form.website ?? {}),
            record_selector:
                (form.website?.record_selector as string | undefined) || 'body',
        };
    if (sourceType === 'csv')
        form.csv ??= { delimiter: ',', encoding: 'UTF-8' };
    if (sourceType === 'xml') form.xml ??= { namespaces: {} };
}
function updateComparison(
    list: string[],
    key: 'identity' | 'fields',
    name: string,
    enabled: boolean,
): void {
    form.comparison[key] = enabled
        ? [...new Set([...list, name])]
        : list.filter((item) => item !== name);
}
function setTransform(field: Field, operation: string): void {
    field.transforms = [{ op: operation }];
}
function setBooleanMap(
    field: Field,
    key: 'boolean_true' | 'boolean_false',
    value: string,
): void {
    field[key] = value
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean);
}
function postSetup(): void {
    if (processing.value) return;
    processing.value = true;
    router.post(
        `/collectors/${props.recipe.id}/setup`,
        {
            definition:
                serializableDefinition() as unknown as FormDataConvertible,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
function openWebsite(): void {
    if (processing.value) return;
    processing.value = true;
    router.post(
        `/collectors/${props.recipe.id}/setup`,
        {
            definition:
                serializableDefinition() as unknown as FormDataConvertible,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                void browserAction('open');
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
function serializableDefinition(): Definition {
    const definition = serializeManualDefinition(
        JSON.parse(JSON.stringify(form)) as Definition,
    );
    if (definition.source_type === 'website' && definition.website)
        definition.website.detail_fields = JSON.parse(
            JSON.stringify(detailFields.value),
        ) as Field[];
    return definition;
}
function activatePreview(): void {
    if (processing.value) return;
    processing.value = true;
    router.post(
        `/collectors/${props.recipe.id}/setup`,
        {
            definition:
                serializableDefinition() as unknown as FormDataConvertible,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.post(
                    `/collectors/${props.recipe.id}/preview`,
                    {},
                    {
                        onFinish: () => {
                            processing.value = false;
                        },
                    },
                );
            },
            onError: () => {
                processing.value = false;
            },
        },
    );
}
function xsrf(): string {
    const cookie = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=')[1] ?? '') : '';
}
async function jsonPost(
    path: string,
    data: Record<string, unknown>,
): Promise<Record<string, unknown> | null> {
    const response = await fetch(path, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrf(),
        },
        body: JSON.stringify(data),
    });
    const body = (await response.json()) as Record<string, unknown>;
    if (!response.ok)
        throw new Error(
            typeof body.message === 'string'
                ? body.message
                : t('builder.request_failed'),
        );
    return body;
}
async function loadSample(): Promise<void> {
    sampleError.value = '';
    try {
        const body = await jsonPost(`/collectors/${props.recipe.id}/sample`, {
            url: form.url,
        });
        if (body) {
            sampleData.value = body.sample ?? body.data ?? body;
        }
    } catch (error) {
        sampleError.value =
            error instanceof Error
                ? error.message
                : t('builder.request_failed');
    }
}
function pickSamplePath(path: string): void {
    form.records_path = path;
}
function useSampleField(path: string): void {
    const field = form.fields.find((item) => item.name === selected.value);
    if (field) field.path = path;
    else {
        const base =
            path
                .normalize('NFKD')
                .replace(/\p{M}/gu, '')
                .replace(/[^A-Za-z0-9_.-]/g, '_')
                .replace(/^[^A-Za-z]+/, '')
                .slice(0, 110) || 'column';
        const names = new Set(form.fields.map((item) => item.name));
        let name = base;
        for (let suffix = 2; names.has(name); suffix++)
            name = `${base}_${suffix}`;
        form.fields.push({
            name,
            path,
            type: 'string',
            required: false,
            transforms: [{ op: 'trim' }],
        });
    }
}
async function browserAction(
    action: string,
    data: Record<string, unknown> = {},
): Promise<void> {
    browserBusy.value = true;
    browserError.value = '';
    try {
        const body = await jsonPost(`/collectors/${props.recipe.id}/browser`, {
            action,
            ...(action === 'open' ? { url: form.url } : {}),
            ...data,
        });
        if (!body) return;
        if (body.opened === true) browserReady.value = true;
        if (body.opened === true) {
            browser.candidates = [];
            browser.matches = [];
        }
        if (typeof body.screenshot === 'string')
            browser.screenshot = body.screenshot;
        if (typeof body.image === 'string') browser.screenshot = body.image;
        const metadata =
            typeof body.metadata === 'object' && body.metadata
                ? (body.metadata as Record<string, unknown>)
                : {};
        if (typeof metadata.viewport === 'object' && metadata.viewport)
            browser.viewport = metadata.viewport as typeof browser.viewport;
        else if (typeof body.viewport === 'object' && body.viewport)
            browser.viewport = body.viewport as typeof browser.viewport;
        if (Array.isArray(metadata.matches))
            browser.matches = metadata.matches as typeof browser.matches;
        if (typeof metadata.accessChallenge === 'boolean')
            browser.accessChallenge = metadata.accessChallenge;
        if (Array.isArray(body.candidates))
            browser.candidates = body.candidates as typeof browser.candidates;
        if (body.saved === true) {
            browserReady.value = false;
            router.reload({
                only: ['definition', 'connections'],
                onSuccess: () => {
                    form.connection_id =
                        props.definition?.connection_id ?? null;
                },
            });
        }
    } catch (error) {
        browserError.value =
            error instanceof Error
                ? error.message
                : t('builder.request_failed');
    } finally {
        browserBusy.value = false;
    }
}
function inspectPoint(event: MouseEvent): void {
    if (browserBusy.value || browser.accessChallenge) return;
    const target = event.currentTarget as HTMLImageElement;
    const bounds = target.getBoundingClientRect();
    void browserAction('inspect', {
        input: {
            x: Math.round(
                ((event.clientX - bounds.left) * browser.viewport.width) /
                    bounds.width,
            ),
            y: Math.round(
                ((event.clientY - bounds.top) * browser.viewport.height) /
                    bounds.height,
            ),
        },
    });
}
function saveConnection(): void {
    if (browserBusy.value) return;
    browserBusy.value = true;
    router.post(
        `/collectors/${props.recipe.id}/connections`,
        { ...connectionForm, origin: form.url },
        {
            preserveScroll: true,
            onSuccess: () => {
                const kind = connectionForm.kind;
                const saved = [...props.connections]
                    .reverse()
                    .find(
                        (connection) =>
                            connection.kind === kind &&
                            connection.status === 'ready',
                    );
                if (saved) form.connection_id = saved.id;
                connectionForm.token = '';
                connectionForm.password = '';
            },
            onFinish: () => {
                browserBusy.value = false;
            },
        },
    );
}
async function sendLoginValue(): Promise<void> {
    const value = loginForm.value;
    loginForm.value = '';
    if (!value || !loginForm.selector) return;
    await browserAction('act', {
        input: { action: 'type', selector: loginForm.selector, value },
    });
}
function chooseRecord(candidate: { selector: string }): void {
    form.website = {
        ...(form.website ?? {}),
        record_selector: candidate.selector,
    };
    pickMode.value = 'fields';
    browser.candidates = [];
    void browserAction('snapshot', { selector: candidate.selector });
}
function chooseField(candidate: { selector: string }): void {
    const field = selected.value.startsWith('detail:')
        ? detailFields.value.find(
              (item) => item.name === selected.value.slice(7),
          )
        : form.fields.find((item) => item.name === selected.value);
    if (field) field.path = candidate.selector;
    selected.value = '';
    browser.candidates = [];
}
function chooseLoginInput(candidate: { selector: string }): void {
    loginForm.selector = candidate.selector;
    browser.candidates = [];
}
function chooseDetailLink(candidate: { selector: string }): void {
    form.website = {
        ...(form.website ?? {}),
        detail_url_selector: candidate.selector,
    };
    browser.candidates = [];
}
function choosePaginationLink(candidate: { selector: string }): void {
    form.pagination.next_path = candidate.selector;
    browser.candidates = [];
}
function chooseCandidate(candidate: { selector: string; tag: string }): void {
    if (pickMode.value === 'records') chooseRecord(candidate);
    else if (pickMode.value === 'login') chooseLoginInput(candidate);
    else if (pickMode.value === 'detail') chooseDetailLink(candidate);
    else if (pickMode.value === 'pagination') choosePaginationLink(candidate);
    else if (selected.value) chooseField(candidate);
    else addFieldFromCandidate(candidate);
}
function addFieldFromCandidate(candidate: {
    selector: string;
    tag: string;
}): void {
    const selector = candidate.selector.toLowerCase();
    const semanticNames = [
        'price',
        'title',
        'name',
        'date',
        'rating',
        'availability',
        'stock',
        'category',
        'description',
    ];
    const semanticName = semanticNames.find((name) =>
        new RegExp(`(?:^|[^a-z])${name}(?:$|[^a-z])`).test(selector),
    );
    const className = candidate.selector
        .match(/\.([A-Za-z][A-Za-z0-9_-]*)/g)
        ?.at(-1)
        ?.slice(1)
        .replace(/[-_]+/g, '_');
    const base =
        semanticName ??
        (['h1', 'h2', 'h3', 'h4'].includes(candidate.tag)
            ? 'title'
            : (className ??
              (candidate.tag === 'a'
                  ? 'link'
                  : candidate.tag === 'img'
                    ? 'image'
                    : 'column')));
    const existing = new Set(form.fields.map((field) => field.name));
    let name = base;
    for (let suffix = 2; existing.has(name); suffix++)
        name = `${base}_${suffix}`;
    const field = form.fields.find(
        (item) =>
            item.path.trim() === '' ||
            (item.name === 'name' && item.path === 'name'),
    );
    if (field) {
        field.name = name;
        field.path = candidate.selector;
    } else {
        form.fields.push({
            name,
            path: candidate.selector,
            type: candidate.tag === 'a' ? 'url' : 'string',
            required: false,
            transforms: [{ op: 'trim' }],
        });
    }
    browser.candidates = [];
}
function startFieldSelection(name: string): void {
    selected.value = name;
    pickMode.value = 'fields';
    visualSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
function setPickMode(mode: PickMode): void {
    pickMode.value = mode;
    browser.candidates = [];
    if (!['records', 'fields'].includes(mode))
        visualSection.value?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
}
</script>

<template>
    <AppLayout :title="t('builder.title')">
        <div class="mx-auto max-w-5xl">
            <Link
                :href="`/collectors/${recipe.id}`"
                class="text-link mb-6 inline-block"
                >← {{ t('recipes.back') }}</Link
            >
            <PageHeader
                :title="t('builder.title')"
                :description="t('builder.help')"
            />
            <ActionErrors />
            <section class="panel mb-6 space-y-5 p-5 sm:p-7">
                <h2 class="text-xl font-semibold">{{ t('builder.source') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label for="source_type">{{
                            t('builder.source_type')
                        }}</Label
                        ><select
                            id="source_type"
                            v-model="form.source_type"
                            @change="
                                changeSource(
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                            class="h-12 w-full rounded-lg border border-outline-glass bg-white px-3"
                        >
                            <option
                                v-for="type in sourceOptions"
                                :key="type"
                                :value="type"
                            >
                                {{ t(`builder.${type}`) }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label for="source_url">{{
                            t('recipes.start_url')
                        }}</Label
                        ><Input
                            id="source_url"
                            v-model="form.url"
                            type="url"
                            required
                        />
                    </div>
                    <div>
                        <Label for="connection">{{
                            t('builder.connection')
                        }}</Label
                        ><select
                            id="connection"
                            v-model="form.connection_id"
                            class="h-12 w-full rounded-lg border border-outline-glass bg-white px-3"
                        >
                            <option :value="null">
                                {{ t('builder.no_connection') }}
                            </option>
                            <option
                                v-for="connection in connections.filter(
                                    (item) => item.status === 'ready',
                                )"
                                :key="connection.id"
                                :value="connection.id"
                            >
                                {{ connection.name }} ·
                                {{ connection.origin }} ({{
                                    connection.status
                                }})
                            </option>
                        </select>
                    </div>
                    <div v-if="form.source_type !== 'website'">
                        <Label for="records_path">{{
                            t('builder.records_path')
                        }}</Label
                        ><Input
                            id="records_path"
                            v-model="form.records_path"
                            :placeholder="
                                form.source_type === 'json'
                                    ? 'data.items'
                                    : form.source_type === 'xml'
                                      ? '/catalog/item'
                                      : ''
                            "
                        />
                        <p
                            v-if="form.source_type === 'csv'"
                            class="mt-1 text-xs text-on-surface-variant"
                        >
                            {{ t('builder.csv_path_help') }}
                        </p>
                    </div>
                </div>
                <div
                    v-if="form.source_type === 'csv'"
                    class="grid gap-4 sm:grid-cols-2"
                >
                    <div>
                        <Label for="delimiter">{{
                            t('builder.delimiter')
                        }}</Label
                        ><Input
                            id="delimiter"
                            v-model="
                                (form.csv as Record<string, string>).delimiter
                            "
                            maxlength="1"
                        />
                    </div>
                    <div>
                        <Label for="encoding">{{ t('builder.encoding') }}</Label
                        ><select
                            id="encoding"
                            v-model="
                                (form.csv as Record<string, string>).encoding
                            "
                            class="h-12 w-full rounded-lg border border-outline-glass bg-white px-3"
                        >
                            <option>UTF-8</option>
                            <option>ISO-8859-1</option>
                            <option>Windows-1252</option>
                        </select>
                    </div>
                </div>
                <div v-if="form.source_type === 'xml'" class="space-y-2">
                    <Label for="namespaces">{{
                        t('builder.xml_namespaces')
                    }}</Label
                    ><textarea
                        id="namespaces"
                        :value="xmlNamespacesText"
                        rows="3"
                        class="w-full rounded-lg border border-outline-glass p-3 font-mono text-sm"
                        @change="
                            xmlNamespacesText = (
                                $event.target as HTMLTextAreaElement
                            ).value
                        "
                    />
                </div>
            </section>
            <section
                v-if="form.source_type !== 'website'"
                class="panel mb-6 space-y-4 p-5 sm:p-7"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{ t('builder.sample_title') }}
                        </h2>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            {{ t('builder.sample_help') }}
                        </p>
                    </div>
                    <Button
                        variant="secondary"
                        :disabled="browserBusy"
                        @click="loadSample"
                        >{{ t('builder.load_sample') }}</Button
                    >
                </div>

                <p
                    v-if="sampleError"
                    role="alert"
                    class="text-sm text-error-red"
                >
                    {{ sampleError }}
                </p>
                <div v-if="sampleData" class="grid gap-5 lg:grid-cols-2">
                    <div>
                        <p class="mb-2 text-sm font-medium">
                            {{ t('builder.sample_tree') }}
                        </p>
                        <pre
                            class="max-h-72 overflow-auto rounded-lg bg-slate-950 p-4 text-xs text-slate-100"
                            >{{
                                typeof sampleData === 'string'
                                    ? sampleData
                                    : JSON.stringify(sampleData, null, 2)
                            }}</pre>
                    </div>
                    <div class="space-y-4">
                        <div v-if="form.source_type !== 'csv'">
                            <p class="mb-2 text-sm font-medium">
                                {{ t('builder.choose_records') }}
                            </p>
                            <div
                                v-if="!sampleRecordsPaths.length"
                                class="rounded border border-outline-glass p-3 text-sm text-on-surface-variant"
                            >
                                {{ t('builder.records_path_manual') }}
                            </div>
                            <div
                                v-for="path in sampleRecordsPaths"
                                :key="path"
                                class="flex items-center justify-between gap-3 rounded border border-outline-glass p-2 text-sm"
                            >
                                <code>{{ path }}</code
                                ><Button
                                    variant="secondary"
                                    @click="pickSamplePath(path)"
                                    >{{
                                        form.records_path === path
                                            ? t('builder.selected_path')
                                            : t('builder.select_path')
                                    }}</Button
                                >
                            </div>
                        </div>
                        <div v-if="sampleFieldPaths.length">
                            <Label for="sample_target">{{
                                t('builder.sample_field_target')
                            }}</Label
                            ><select
                                id="sample_target"
                                v-model="selected"
                                class="mb-2 h-11 w-full rounded-lg border border-outline-glass bg-white px-3"
                            >
                                <option value="">
                                    {{ t('builder.add_sample_field') }}
                                </option>
                                <option
                                    v-for="field in form.fields"
                                    :key="field.name"
                                    :value="field.name"
                                >
                                    {{ field.name }}
                                </option>
                            </select>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    v-for="path in sampleFieldPaths"
                                    :key="path"
                                    variant="secondary"
                                    @click="useSampleField(path)"
                                    >{{ path }}</Button
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <section
                v-if="form.source_type === 'website'"
                ref="visualSection"
                class="panel mb-6 scroll-mt-6 space-y-5 overflow-hidden p-5 sm:p-7"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{ t('builder.visual_title') }}
                        </h2>
                        <p
                            class="mt-1 max-w-2xl text-sm text-on-surface-variant"
                        >
                            {{ t('builder.visual_help') }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button :disabled="browserBusy" @click="openWebsite">{{
                            t('builder.open_browser')
                        }}</Button
                        ><Button
                            variant="secondary"
                            :disabled="browserBusy || !browserReady"
                            @click="browserAction('snapshot')"
                            >{{ t('builder.refresh_snapshot') }}</Button
                        >
                    </div>
                </div>
                <p
                    v-if="browserError"
                    role="alert"
                    class="text-sm text-error-red"
                >
                    {{ browserError }}
                </p>
                <p
                    v-if="browser.accessChallenge"
                    role="alert"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                >
                    {{ t('builder.access_challenge') }}
                </p>
                <div
                    v-if="!browser.screenshot"
                    class="flex min-h-56 flex-col items-center justify-center rounded-xl border border-dashed border-outline-glass bg-surface-container-low px-6 py-8 text-center"
                >
                    <span
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-primary"
                        ><ScanSearch :size="24" aria-hidden="true"
                    /></span>
                    <p class="mt-3 font-semibold">
                        {{ t('builder.preview_empty') }}
                    </p>
                    <p class="mt-1 max-w-md text-sm text-on-surface-variant">
                        {{ t('builder.preview_empty_help') }}
                    </p>
                </div>
                <div
                    v-if="browser.screenshot && !browser.accessChallenge"
                    class="flex flex-wrap items-center gap-2 text-sm"
                >
                    <Button
                        :variant="
                            pickMode === 'records' ? 'primary' : 'secondary'
                        "
                        @click="setPickMode('records')"
                        >{{ t('builder.pick_records_step') }}</Button
                    >
                    <Button
                        :variant="
                            pickMode === 'fields' ? 'primary' : 'secondary'
                        "
                        :disabled="
                            !form.website?.record_selector ||
                            form.website.record_selector === 'body'
                        "
                        @click="setPickMode('fields')"
                        >{{ t('builder.pick_columns_step') }}</Button
                    >
                    <span
                        v-if="browser.matches.length"
                        class="rounded-full bg-success/10 px-3 py-1 font-medium text-success"
                        >{{
                            t('builder.matched_records', {
                                count: browser.matches.length,
                            })
                        }}</span
                    >
                </div>
                <div
                    v-if="browser.screenshot"
                    class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]"
                >
                    <div
                        class="relative w-fit max-w-full overflow-hidden rounded-xl border border-outline-glass bg-slate-100 shadow-sm"
                    >
                        <button
                            type="button"
                            class="relative block max-w-full cursor-crosshair text-left disabled:cursor-not-allowed"
                            :disabled="browserBusy || browser.accessChallenge"
                            :aria-label="t('builder.inspect_page')"
                            @click="inspectPoint"
                        >
                            <img
                                :src="
                                    browser.screenshot.startsWith('data:')
                                        ? browser.screenshot
                                        : `data:image/jpeg;base64,${browser.screenshot}`
                                "
                                :alt="t('builder.snapshot_alt')"
                                class="block max-h-[65vh] max-w-full object-contain"
                            />
                            <div
                                v-for="match in browser.matches"
                                :key="match.index"
                                class="pointer-events-none absolute border-2 border-fuchsia-500 bg-fuchsia-300/20"
                                :style="{
                                    left: `${(match.x / browser.viewport.width) * 100}%`,
                                    top: `${(match.y / browser.viewport.height) * 100}%`,
                                    width: `${(match.width / browser.viewport.width) * 100}%`,
                                    height: `${(match.height / browser.viewport.height) * 100}%`,
                                }"
                            />
                        </button>
                    </div>
                    <div
                        v-if="!browser.accessChallenge"
                        class="rounded-xl border border-outline-glass bg-surface-container-low p-4"
                    >
                        <h3 class="font-semibold">{{ pickTitle }}</h3>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            {{ pickHint }}
                        </p>
                        <div
                            v-if="browser.candidates.length"
                            class="mt-4 max-h-[56vh] space-y-2 overflow-y-auto"
                        >
                            <div
                                v-for="candidate in visibleCandidates"
                                :key="candidate.selector"
                                class="rounded-lg border border-outline-glass bg-white p-3 text-sm"
                            >
                                <p class="line-clamp-2 font-medium">
                                    {{ candidate.text || candidate.tag }}
                                </p>
                                <p class="mt-1 text-xs text-on-surface-variant">
                                    {{
                                        t('builder.match_count', {
                                            count: candidate.count,
                                        })
                                    }}
                                </p>
                                <Button
                                    class="mt-3 w-full"
                                    :disabled="browserBusy"
                                    @click="chooseCandidate(candidate)"
                                    >{{ pickAction }}</Button
                                >
                                <details
                                    class="mt-2 text-xs text-on-surface-variant"
                                >
                                    <summary class="cursor-pointer">
                                        {{ t('builder.advanced_selectors') }}
                                    </summary>
                                    <code class="mt-1 block break-all">{{
                                        candidate.selector
                                    }}</code>
                                    <Button
                                        class="mt-2"
                                        variant="secondary"
                                        :disabled="browserBusy"
                                        @click="
                                            browserAction('act', {
                                                input: {
                                                    action: 'click',
                                                    selector:
                                                        candidate.selector,
                                                },
                                            })
                                        "
                                        >{{
                                            t('builder.click_element')
                                        }}</Button
                                    >
                                </details>
                            </div>
                        </div>
                    </div>
                </div>
                <details class="rounded-lg border border-outline-glass p-4">
                    <summary class="font-medium">
                        {{ t('builder.advanced_selectors') }}
                    </summary>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="record_selector">{{
                                t('builder.record_selector')
                            }}</Label
                            ><Input
                                id="record_selector"
                                v-model="
                                    (form.website as Record<string, string>)
                                        .record_selector
                                "
                                placeholder="article.product"
                            />
                        </div>
                        <div>
                            <Label for="detail_url_selector">{{
                                t('builder.detail_url_selector')
                            }}</Label
                            ><Input
                                id="detail_url_selector"
                                v-model="
                                    (form.website as Record<string, string>)
                                        .detail_url_selector
                                "
                                placeholder="a.product-link"
                            />
                        </div>
                        <div>
                            <Label for="signed_in_selector">{{
                                t('builder.signed_in_selector')
                            }}</Label
                            ><Input
                                id="signed_in_selector"
                                v-model="
                                    (form.website as Record<string, string>)
                                        .signed_in_selector
                                "
                                :placeholder="
                                    t('builder.signed_in_placeholder')
                                "
                            />
                        </div>
                    </div>
                </details>
                <details class="rounded-lg border border-outline-glass p-4">
                    <summary class="cursor-pointer font-medium">
                        {{ t('builder.login_title') }}
                    </summary>
                    <p class="my-3 text-sm text-on-surface-variant">
                        {{ t('builder.login_help') }}
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <p class="text-sm font-medium">
                                {{ t('builder.login_selector') }}
                            </p>
                            <p
                                class="mt-1 truncate rounded-lg bg-surface-container-low px-3 py-2 text-sm text-on-surface-variant"
                            >
                                {{
                                    loginForm.selector ||
                                    t('builder.no_element_selected')
                                }}
                            </p>
                            <Button
                                class="mt-2"
                                variant="secondary"
                                :disabled="!browserReady || browserBusy"
                                @click="setPickMode('login')"
                                >{{ t('builder.pick_visual') }}</Button
                            >
                            <details class="mt-2 text-sm">
                                <summary
                                    class="cursor-pointer text-on-surface-variant"
                                >
                                    {{ t('builder.advanced_selectors') }}
                                </summary>
                                <Label for="login-selector">{{
                                    t('builder.login_selector')
                                }}</Label
                                ><Input
                                    id="login-selector"
                                    v-model="loginForm.selector"
                                    :placeholder="
                                        t('builder.login_selector_placeholder')
                                    "
                                />
                            </details>
                        </div>
                        <div>
                            <Label for="login-value">{{
                                t('builder.login_value')
                            }}</Label
                            ><Input
                                id="login-value"
                                v-model="loginForm.value"
                                type="password"
                                autocomplete="off"
                            />
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Button
                            variant="secondary"
                            :disabled="
                                browserBusy ||
                                !browserReady ||
                                !loginForm.selector ||
                                !loginForm.value
                            "
                            @click="sendLoginValue"
                            >{{ t('builder.type_login_value') }}</Button
                        ><Button
                            variant="secondary"
                            :disabled="browserBusy || !browserReady"
                            @click="
                                browserAction('act', {
                                    input: { action: 'press', key: 'Enter' },
                                })
                            "
                            >{{ t('builder.submit_login') }}</Button
                        ><Button
                            variant="secondary"
                            :disabled="browserBusy || !browserReady"
                            @click="browserAction('save')"
                            >{{ t('builder.save_login') }}</Button
                        ><Button
                            variant="secondary"
                            :disabled="browserBusy || !browserReady"
                            @click="
                                browserAction('act', {
                                    input: { action: 'scroll', deltaY: 650 },
                                })
                            "
                            >{{ t('builder.scroll_page') }}</Button
                        >
                    </div>
                </details>
                <details class="rounded-lg border border-outline-glass p-4">
                    <summary class="cursor-pointer font-medium">
                        {{ t('builder.detail_fields') }}
                    </summary>
                    <p class="my-3 text-sm text-on-surface-variant">
                        {{ t('builder.detail_fields_help') }}
                    </p>
                    <div
                        class="mb-4 rounded-lg bg-surface-container-low p-3 text-sm"
                    >
                        <p class="font-medium">
                            {{ t('builder.detail_url_selector') }}
                        </p>
                        <p class="mt-1 break-all text-on-surface-variant">
                            {{
                                form.website?.detail_url_selector ||
                                t('builder.no_element_selected')
                            }}
                        </p>
                        <Button
                            class="mt-2"
                            variant="secondary"
                            :disabled="!browserReady || browserBusy"
                            @click="setPickMode('detail')"
                            >{{ t('builder.pick_visual') }}</Button
                        >
                    </div>
                    <Button variant="secondary" @click="addDetailField">{{
                        t('builder.add_field')
                    }}</Button>
                    <div
                        v-for="(field, index) in detailFields"
                        :key="index"
                        class="mt-3 grid gap-3 rounded-lg border border-outline-glass p-3 sm:grid-cols-4"
                    >
                        <div>
                            <Label :for="`detail-name-${index}`">{{
                                t('builder.field_name')
                            }}</Label
                            ><Input
                                :id="`detail-name-${index}`"
                                v-model="field.name"
                            />
                        </div>
                        <div>
                            <Label :for="`detail-path-${index}`">{{
                                t('builder.field_path')
                            }}</Label
                            ><Input
                                :id="`detail-path-${index}`"
                                v-model="field.path"
                            /><Button
                                v-if="browser.candidates.length"
                                class="mt-2"
                                variant="secondary"
                                @click="selected = `detail:${field.name}`"
                                >{{
                                    selected === `detail:${field.name}`
                                        ? t('builder.field_selected')
                                        : t('builder.pick_visual')
                                }}</Button
                            >
                        </div>
                        <div>
                            <Label :for="`detail-type-${index}`">{{
                                t('builder.field_type')
                            }}</Label
                            ><select
                                :id="`detail-type-${index}`"
                                v-model="field.type"
                                class="h-12 w-full rounded-lg border border-outline-glass bg-white px-3"
                            >
                                <option
                                    v-for="type in [
                                        'string',
                                        'number',
                                        'boolean',
                                        'date',
                                        'url',
                                    ]"
                                    :key="type"
                                    :value="type"
                                >
                                    {{ t(`builder.type_${type}`) }}
                                </option>
                            </select>
                        </div>
                        <label
                            class="flex min-h-12 items-center gap-2 pt-5 text-sm"
                            ><input
                                v-model="field.required"
                                type="checkbox"
                                class="h-4 w-4 accent-primary"
                            />{{ t('builder.required') }}</label
                        >
                    </div>
                </details>
            </section>
            <section class="panel mb-6 space-y-4 p-5 sm:p-7">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{ t('builder.fields') }}
                        </h2>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            {{ t('builder.fields_help') }}
                        </p>
                    </div>
                    <Button variant="secondary" @click="addField">{{
                        t('builder.add_field')
                    }}</Button>
                </div>
                <div
                    v-for="(field, index) in form.fields"
                    :key="index"
                    class="grid gap-3 rounded-lg border border-outline-glass p-4 sm:grid-cols-2 xl:grid-cols-5"
                >
                    <div>
                        <Label :for="`field-name-${index}`">{{
                            t('builder.field_name')
                        }}</Label
                        ><Input
                            :id="`field-name-${index}`"
                            v-model="field.name"
                        />
                    </div>
                    <div
                        v-if="form.source_type === 'website'"
                        class="sm:col-span-2"
                    >
                        <p class="text-sm font-medium">
                            {{ t('builder.source_element') }}
                        </p>
                        <p
                            class="mt-1 truncate rounded-lg bg-surface-container-low px-3 py-2 text-sm text-on-surface-variant"
                        >
                            {{
                                field.name === 'name' && field.path === 'name'
                                    ? t('builder.no_element_selected')
                                    : field.path ||
                                      t('builder.no_element_selected')
                            }}
                        </p>
                        <Button
                            class="mt-2"
                            variant="secondary"
                            @click="startFieldSelection(field.name)"
                            >{{ t('builder.pick_visual') }}</Button
                        >
                        <details class="mt-2 text-sm">
                            <summary class="text-on-surface-variant">
                                {{ t('builder.advanced_selectors') }}
                            </summary>
                            <Label :for="`field-path-${index}`">{{
                                t('builder.field_path')
                            }}</Label>
                            <Input
                                :id="`field-path-${index}`"
                                v-model="field.path"
                            />
                        </details>
                    </div>
                    <div v-else>
                        <Label :for="`field-path-${index}`">{{
                            t('builder.field_path')
                        }}</Label
                        ><Input
                            :id="`field-path-${index}`"
                            v-model="field.path"
                        />
                    </div>
                    <div>
                        <Label :for="`field-type-${index}`">{{
                            t('builder.field_type')
                        }}</Label
                        ><select
                            :id="`field-type-${index}`"
                            v-model="field.type"
                            class="h-12 w-full rounded-lg border border-outline-glass bg-white px-3"
                        >
                            <option
                                v-for="type in [
                                    'string',
                                    'number',
                                    'boolean',
                                    'date',
                                    'url',
                                ]"
                                :key="type"
                                :value="type"
                            >
                                {{ t(`builder.type_${type}`) }}
                            </option>
                        </select>
                    </div>
                    <label class="flex min-h-12 items-center gap-2 pt-5 text-sm"
                        ><input
                            v-model="field.required"
                            type="checkbox"
                            class="h-4 w-4 accent-primary"
                        />{{ t('builder.required') }}</label
                    ><Button
                        variant="danger"
                        class="self-end"
                        :disabled="form.fields.length < 2"
                        @click="removeField(index)"
                        >{{ t('builder.remove_field') }}</Button
                    >
                    <div v-if="field.type === 'number'" class="sm:col-span-2">
                        <Label :for="`field-number-locale-${index}`">{{
                            t('builder.number_format')
                        }}</Label
                        ><select
                            :id="`field-number-locale-${index}`"
                            v-model="field.number_locale"
                            class="h-11 rounded-lg border border-outline-glass bg-white px-3"
                        >
                            <option value="dot">1,234.56</option>
                            <option value="comma">1.234,56</option>
                        </select>
                    </div>
                    <div v-if="field.type === 'date'" class="sm:col-span-2">
                        <Label :for="`field-date-format-${index}`">{{
                            t('builder.date_format')
                        }}</Label
                        ><Input
                            :id="`field-date-format-${index}`"
                            v-model="field.date_format"
                            placeholder="Y-m-d"
                        />
                    </div>
                    <div
                        v-if="field.type === 'boolean'"
                        class="sm:col-span-2 xl:col-span-5 grid gap-3 sm:grid-cols-2"
                    >
                        <div>
                            <Label :for="`field-true-${index}`">{{
                                t('builder.true_values')
                            }}</Label
                            ><Input
                                :id="`field-true-${index}`"
                                :model-value="
                                    field.boolean_true?.join(', ') ?? ''
                                "
                                @update:model-value="
                                    setBooleanMap(
                                        field,
                                        'boolean_true',
                                        String($event ?? ''),
                                    )
                                "
                            />
                        </div>
                        <div>
                            <Label :for="`field-false-${index}`">{{
                                t('builder.false_values')
                            }}</Label
                            ><Input
                                :id="`field-false-${index}`"
                                :model-value="
                                    field.boolean_false?.join(', ') ?? ''
                                "
                                @update:model-value="
                                    setBooleanMap(
                                        field,
                                        'boolean_false',
                                        String($event ?? ''),
                                    )
                                "
                            />
                        </div>
                    </div>
                    <div class="sm:col-span-2 xl:col-span-5">
                        <Label :for="`field-transform-${index}`">{{
                            t('builder.transform')
                        }}</Label
                        ><select
                            :id="`field-transform-${index}`"
                            :value="field.transforms?.[0]?.op ?? 'trim'"
                            class="h-11 rounded-lg border border-outline-glass bg-white px-3"
                            @change="
                                setTransform(
                                    field,
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="trim">
                                {{ t('builder.trim') }}
                            </option>
                            <option value="lowercase">
                                {{ t('builder.lowercase') }}
                            </option>
                            <option value="uppercase">
                                {{ t('builder.uppercase') }}
                            </option>
                            <option value="replace">
                                {{ t('builder.replace') }}
                            </option>
                            <option value="prefix">
                                {{ t('builder.prefix') }}
                            </option>
                            <option value="suffix">
                                {{ t('builder.suffix') }}
                            </option>
                        </select>
                        <div
                            v-if="field.transforms?.[0]?.op === 'replace'"
                            class="mt-2 grid gap-3 sm:grid-cols-2"
                        >
                            <Input
                                :value="field.transforms[0]?.search ?? ''"
                                :placeholder="t('builder.find_text')"
                                @input="
                                    field.transforms![0]!.search = (
                                        $event.target as HTMLInputElement
                                    ).value
                                "
                            /><Input
                                :value="field.transforms[0]?.value ?? ''"
                                :placeholder="t('builder.replacement_text')"
                                @input="
                                    field.transforms![0]!.value = (
                                        $event.target as HTMLInputElement
                                    ).value
                                "
                            />
                        </div>
                        <Input
                            v-else-if="
                                ['prefix', 'suffix'].includes(
                                    field.transforms?.[0]?.op ?? '',
                                )
                            "
                            class="mt-2"
                            :value="field.transforms?.[0]?.value ?? ''"
                            :placeholder="t('builder.transform_value')"
                            @input="
                                field.transforms![0]!.value = (
                                    $event.target as HTMLInputElement
                                ).value
                            "
                        />
                    </div>
                </div>
                <fieldset class="space-y-3">
                    <legend class="font-semibold">
                        {{ t('builder.comparison') }}
                    </legend>
                    <p class="text-sm text-on-surface-variant">
                        {{ t('builder.comparison_help') }}
                    </p>
                    <div
                        v-for="field in form.fields"
                        :key="field.name"
                        class="flex flex-wrap gap-5 text-sm"
                    >
                        <span class="min-w-32 font-medium">{{
                            field.name
                        }}</span
                        ><label class="flex items-center gap-2"
                            ><input
                                type="checkbox"
                                :checked="
                                    form.comparison.identity.includes(
                                        field.name,
                                    )
                                "
                                @change="
                                    updateComparison(
                                        form.comparison.identity,
                                        'identity',
                                        field.name,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />{{ t('builder.identity') }}</label
                        ><label class="flex items-center gap-2"
                            ><input
                                type="checkbox"
                                :checked="
                                    form.comparison.fields.includes(field.name)
                                "
                                @change="
                                    updateComparison(
                                        form.comparison.fields,
                                        'fields',
                                        field.name,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />{{ t('builder.compare') }}</label
                        >
                    </div>
                </fieldset>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div
                        v-for="key in [
                            'rows',
                            'bytes',
                            'requests',
                            'pages',
                            'seconds',
                        ]"
                        :key="key"
                    >
                        <Label :for="`limit-${key}`">{{
                            t(`builder.limit_${key}`)
                        }}</Label
                        ><Input
                            :id="`limit-${key}`"
                            v-model.number="form.limits[key]"
                            type="number"
                            min="1"
                        />
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="form.validation.allow_empty"
                        type="checkbox"
                        class="h-4 w-4 accent-primary"
                    />{{ t('builder.allow_empty') }}</label
                >
            </section>
            <section class="panel mb-6 space-y-4 p-5 sm:p-7">
                <h2 class="text-xl font-semibold">
                    {{ t('builder.pagination') }}
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label for="pagination_mode">{{
                            t('builder.pagination_mode')
                        }}</Label
                        ><select
                            id="pagination_mode"
                            v-model="form.pagination.mode"
                            class="h-12 w-full rounded-lg border border-outline-glass bg-white px-3"
                        >
                            <option
                                v-for="mode in [
                                    'none',
                                    'page',
                                    'offset',
                                    'next_link',
                                    'cursor',
                                    'next_page',
                                    'load_more',
                                    'scroll',
                                ]"
                                :key="mode"
                                :value="mode"
                                :disabled="
                                    form.source_type === 'website'
                                        ? ![
                                              'none',
                                              'next_page',
                                              'load_more',
                                              'scroll',
                                          ].includes(mode)
                                        : [
                                              'next_page',
                                              'load_more',
                                              'scroll',
                                          ].includes(mode) ||
                                          (form.source_type !== 'json' &&
                                              ['next_link', 'cursor'].includes(
                                                  mode,
                                              ))
                                "
                            >
                                {{ t(`builder.pagination_${mode}`) }}
                            </option>
                        </select>
                    </div>
                    <div v-for="key in paginationKeys" :key="key">
                        <Label :for="`pagination-${key}`">{{
                            t(`builder.pagination_${key}`)
                        }}</Label
                        ><Input
                            :id="`pagination-${key}`"
                            v-model="form.pagination[key]"
                            :type="
                                ['start', 'step', 'max_actions'].includes(key)
                                    ? 'number'
                                    : 'text'
                            "
                        />
                        <Button
                            v-if="
                                form.source_type === 'website' &&
                                key === 'next_path'
                            "
                            class="mt-2"
                            variant="secondary"
                            :disabled="!browserReady || browserBusy"
                            @click="setPickMode('pagination')"
                            >{{ t('builder.pick_visual') }}</Button
                        >
                    </div>
                </div>
            </section>
            <section class="panel mb-6 space-y-4 p-5 sm:p-7">
                <h2 class="text-xl font-semibold">
                    {{ t('builder.credentials_title') }}
                </h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <Label for="connection-kind">{{
                            t('builder.connection_kind')
                        }}</Label
                        ><select
                            id="connection-kind"
                            v-model="connectionForm.kind"
                            class="h-12 w-full rounded-lg border border-outline-glass bg-white px-3"
                        >
                            <option value="bearer">
                                {{ t('builder.token') }}
                            </option>
                            <option value="api_key">
                                {{ t('builder.api_key') }}
                            </option>
                            <option value="basic">
                                {{ t('builder.basic_auth') }}
                            </option>
                        </select>
                    </div>
                    <div v-if="connectionForm.kind === 'basic'">
                        <Label for="connection-username">{{
                            t('builder.username')
                        }}</Label
                        ><Input
                            id="connection-username"
                            v-model="connectionForm.username"
                            autocomplete="username"
                        />
                    </div>
                    <div v-if="connectionForm.kind === 'basic'">
                        <Label for="connection-password">{{
                            t('fields.password')
                        }}</Label
                        ><Input
                            id="connection-password"
                            v-model="connectionForm.password"
                            type="password"
                            autocomplete="new-password"
                        />
                    </div>
                    <div v-if="connectionForm.kind === 'api_key'">
                        <Label for="connection-header">{{
                            t('builder.header_name')
                        }}</Label
                        ><Input
                            id="connection-header"
                            v-model="connectionForm.header"
                        />
                    </div>
                    <div v-if="connectionForm.kind !== 'basic'">
                        <Label for="connection-secret">{{
                            t('builder.secret')
                        }}</Label
                        ><Input
                            id="connection-secret"
                            v-model="connectionForm.token"
                            type="password"
                            autocomplete="new-password"
                        />
                    </div>
                </div>
                <p class="text-sm text-on-surface-variant">
                    {{ t('builder.credential_help') }}
                </p>
                <Button
                    variant="secondary"
                    :disabled="
                        browserBusy ||
                        (connectionForm.kind === 'basic'
                            ? !connectionForm.username ||
                              !connectionForm.password
                            : !connectionForm.token)
                    "
                    @click="saveConnection"
                    >{{ t('builder.save_connection') }}</Button
                >
                <div v-if="connections.length" class="flex flex-wrap gap-2">
                    <span
                        v-for="connection in connections"
                        :key="connection.id"
                        class="rounded-full bg-surface-container-low px-3 py-1 text-sm"
                        >{{ connection.name }} · {{ connection.origin }} ·
                        {{ connection.status
                        }}<button
                            v-if="connection.status === 'ready'"
                            class="ml-2 text-link"
                            type="button"
                            @click="
                                router.post(
                                    `/collectors/${recipe.id}/connections/${connection.id}/revoke`,
                                    {},
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            if (
                                                form.connection_id ===
                                                connection.id
                                            )
                                                form.connection_id = null;
                                        },
                                    },
                                )
                            "
                        >
                            {{ t('builder.revoke') }}
                        </button></span
                    >
                </div>
            </section>
            <div
                class="sticky bottom-3 z-10 flex flex-wrap justify-end gap-3 rounded-xl border border-outline-glass bg-white/95 p-3 shadow-lg backdrop-blur"
            >
                <Button
                    variant="secondary"
                    :disabled="processing || !canSave"
                    @click="postSetup"
                    >{{
                        processing
                            ? t('common.saving')
                            : t('builder.save_draft')
                    }}</Button
                ><Button
                    variant="secondary"
                    :disabled="processing || !canPreview"
                    @click="activatePreview"
                    >{{ t('builder.preview') }}</Button
                >
            </div>
        </div>
    </AppLayout>
</template>
