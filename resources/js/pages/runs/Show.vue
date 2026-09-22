<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Download, Search } from '@lucide/vue';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import { useI18n } from 'vue-i18n';
import ActionErrors from '@/components/ui/ActionErrors.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import Pagination from '@/components/ui/Pagination.vue';
import { resultEmptyState } from '@/lib/collector-workflow';
interface Run {
    id: string;
    kind: string;
    status: string;
    progress: number;
    row_count: number;
    byte_count: number;
    request_count: number;
    logs: string | null;
    error: string | null;
    has_json: boolean;
    has_csv: boolean;
    complete: boolean;
    diagnostics: string[];
    comparison: {
        status: string;
        added: number;
        changed: number;
        missing: number;
    } | null;
}
interface Column {
    key: string;
    label: string;
    type: string;
}
interface Row {
    sequence: number;
    payload: Record<string, unknown>;
}
interface Filters {
    search: string;
    column: Record<string, string>;
    sort: string;
    direction: string;
    visible: string[];
}
const props = defineProps<{
    recipe: { id: number; name: string } | null;
    run: Run;
    columns: Column[];
    rows: {
        data: Row[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    filters: Filters;
}>();
const { t, locale } = useI18n();
const search = ref(props.filters.search);
const columnFilters = reactive<Record<string, string>>({
    ...props.filters.column,
});
const visible = ref([...props.filters.visible]);
const advanced = ref(false);
const processing = ref(false);
const active = computed(() => ['queued', 'running'].includes(props.run.status));
const displayedColumns = computed(() =>
    props.columns.filter((column) => visible.value.includes(column.key)),
);
const filtered = computed(
    () =>
        !!props.filters.search ||
        Object.values(props.filters.column).some(Boolean),
);
const emptyState = computed(() =>
    resultEmptyState(props.run.status, filtered.value),
);
watch(
    () => props.columns,
    (columns, previous) => {
        for (const column of columns)
            if (!previous.some((old) => old.key === column.key))
                visible.value.push(column.key);
    },
);
function apply(
    sort = props.filters.sort,
    direction = props.filters.direction,
): void {
    router.get(
        `/runs/${props.run.id}`,
        {
            search: search.value,
            filter: columnFilters,
            sort,
            direction,
            visible: visible.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
function clearFilters(): void {
    search.value = '';
    for (const key of Object.keys(columnFilters)) delete columnFilters[key];
    visible.value = props.columns.map((column) => column.key);
    router.get(
        `/runs/${props.run.id}`,
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
function sortBy(key: string): void {
    apply(
        key,
        props.filters.sort === key && props.filters.direction === 'asc'
            ? 'desc'
            : 'asc',
    );
}
function cancel(): void {
    if (processing.value) return;
    processing.value = true;
    router.post(
        `/runs/${props.run.id}/cancel`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
function display(value: unknown): string {
    return value === null || value === undefined
        ? '—'
        : typeof value === 'object'
          ? JSON.stringify(value)
          : String(value);
}
let events: EventSource | null = null;
onMounted(() => {
    if (active.value) {
        events = new EventSource(`/runs/${props.run.id}/stream`);
        events.onmessage = () =>
            router.reload({ only: ['run', 'rows', 'columns'] });
    }
});
watch(active, (value) => {
    if (!value) events?.close();
});
onBeforeUnmount(() => events?.close());
</script>
<template>
    <AppLayout :title="recipe?.name ?? t('runs.detail_title')">
        <ActionErrors />
        <Link
            :href="recipe ? `/collectors/${recipe.id}` : '/runs'"
            class="text-link mb-6 inline-block"
            >← {{ recipe ? t('home.open_collector') : t('runs.back') }}</Link
        >
        <PageHeader
            :title="recipe?.name ?? t('runs.detail_title')"
            :description="t(`runs.${run.kind}`)"
        >
            <a
                v-if="run.has_csv"
                :href="`/runs/${run.id}/download/csv`"
                download
                class="button button-primary"
                ><Download :size="18" />{{ t('runs.download_csv') }}</a
            >
            <a
                v-if="run.has_json"
                :href="`/runs/${run.id}/download/json`"
                download
                class="button button-secondary"
                >{{ t('runs.download_json') }}</a
            >
            <Button
                v-if="active"
                variant="secondary"
                :disabled="processing"
                @click="cancel"
                >{{ t('runs.cancel') }}</Button
            >
        </PageHeader>
        <section class="panel mb-6 p-6" role="status" aria-live="polite">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-on-surface-variant">
                        {{ t('runs.rows') }}
                    </p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums">
                        {{ run.row_count.toLocaleString(locale) }}
                    </p>
                </div>
                <StatusBadge :status="run.status" />
            </div>
            <template v-if="active"
                ><p class="mt-4 text-sm text-on-surface-variant">
                    {{ t('runs.progress_help') }}
                </p>
                <progress
                    class="mt-3 h-2 w-full accent-primary"
                    :value="run.progress"
                    max="100"
                    :aria-label="t('runs.progress')"
            /></template>
            <p
                v-else-if="['failed', 'cancelled'].includes(run.status)"
                class="mt-4 text-on-surface-variant"
            >
                {{ t(`result_state.${run.status}_help`) }}
            </p>
            <p
                v-else-if="run.status === 'completed' && !run.complete"
                class="mt-4 text-sm font-medium text-amber-800"
            >
                {{ t('builder.incomplete') }}
            </p>
        </section>
        <section v-if="run.comparison" class="panel mb-6 p-5">
            <h2 class="font-semibold">{{ t('builder.comparison_status') }}</h2>
            <p class="mt-2 text-sm text-on-surface-variant">
                {{
                    run.comparison.status === 'baseline'
                        ? t('builder.comparison_empty')
                        : run.comparison.status
                }}
            </p>
            <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
                <div>
                    <dt class="text-on-surface-variant">
                        {{ t('builder.added') }}
                    </dt>
                    <dd class="mt-1 text-xl font-semibold">
                        {{ run.comparison.added }}
                    </dd>
                </div>
                <div>
                    <dt class="text-on-surface-variant">
                        {{ t('builder.changed') }}
                    </dt>
                    <dd class="mt-1 text-xl font-semibold">
                        {{ run.comparison.changed }}
                    </dd>
                </div>
                <div>
                    <dt class="text-on-surface-variant">
                        {{ t('builder.missing') }}
                    </dt>
                    <dd class="mt-1 text-xl font-semibold">
                        {{ run.comparison.missing }}
                    </dd>
                </div>
            </dl>
        </section>
        <details v-if="run.diagnostics.length" class="panel mb-6 p-5">
            <summary class="cursor-pointer font-semibold">
                {{ t('builder.diagnostics') }}
            </summary>
            <ul
                class="mt-3 list-inside list-disc space-y-2 text-sm text-on-surface-variant"
            >
                <li v-for="(diagnostic, index) in run.diagnostics" :key="index">
                    {{ diagnostic }}
                </li>
            </ul>
        </details>
        <div
            v-if="run.kind === 'test' && run.status === 'completed' && recipe"
            class="mb-6 rounded-xl border border-blue-100 bg-blue-50 p-5"
        >
            <h2 class="font-semibold">{{ t('runs.sample_title') }}</h2>
            <p class="mt-1 text-sm text-on-surface-variant">
                {{ t('runs.sample_help') }}
            </p>
            <Link
                :href="`/collectors/${recipe.id}`"
                class="text-link mt-3 inline-block"
                >{{ t('runs.back_to_review') }} →</Link
            >
        </div>
        <section>
            <div
                class="mb-4 flex flex-col justify-between gap-4 xl:flex-row xl:items-end"
            >
                <div>
                    <h2 class="text-xl font-semibold">
                        {{ t('dataset.title') }}
                    </h2>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('runs.row_count', { count: rows.total }) }}
                    </p>
                </div>
                <form class="flex flex-wrap gap-2" @submit.prevent="apply()">
                    <Input
                        v-model="search"
                        class="w-auto min-w-0 flex-1"
                        :placeholder="t('dataset.search')"
                        :aria-label="t('dataset.search')"
                    /><Button
                        type="submit"
                        variant="secondary"
                        :aria-label="t('common.search')"
                        ><Search :size="18" /></Button
                    ><Button variant="secondary" @click="clearFilters">{{
                        t('dataset.clear')
                    }}</Button>
                </form>
            </div>
            <details
                class="panel mb-4 p-4"
                @toggle="advanced = ($event.target as HTMLDetailsElement).open"
            >
                <summary class="font-medium">
                    {{ t('dataset.options') }}
                </summary>
                <div class="mt-4">
                    <fieldset>
                        <legend class="mb-3 text-sm font-medium">
                            {{ t('dataset.columns') }}
                        </legend>
                        <div class="flex flex-wrap gap-3">
                            <label
                                v-for="column in columns"
                                :key="column.key"
                                class="flex min-h-11 items-center gap-2 rounded-lg border border-outline-glass px-3 text-sm"
                                ><input
                                    v-model="visible"
                                    type="checkbox"
                                    :value="column.key"
                                    :disabled="
                                        visible.length === 1 &&
                                        visible.includes(column.key)
                                    "
                                    class="h-4 w-4 accent-primary"
                                />{{ column.label }}</label
                            >
                        </div>
                    </fieldset>
                    <p class="my-3 text-sm text-on-surface-variant">
                        {{ t('dataset.filter_help') }}
                    </p>
                    <Button variant="secondary" @click="apply()">{{
                        t('dataset.apply')
                    }}</Button>
                </div>
            </details>
            <div
                class="panel max-h-[65vh] overflow-auto"
                tabindex="0"
                role="region"
                :aria-label="t('dataset.title')"
            >
                <table
                    class="min-w-full border-separate border-spacing-0 text-left text-sm"
                >
                    <caption class="sr-only">
                        {{
                            t('dataset.title')
                        }}
                    </caption>
                    <thead class="sticky top-0 z-10 bg-surface-container-low">
                        <tr>
                            <th
                                scope="col"
                                class="border-b border-outline-glass p-4"
                            >
                                #
                            </th>
                            <th
                                v-for="column in displayedColumns"
                                :key="column.key"
                                scope="col"
                                class="min-w-44 border-b border-outline-glass p-4"
                                :aria-sort="
                                    filters.sort === column.key
                                        ? filters.direction === 'asc'
                                            ? 'ascending'
                                            : 'descending'
                                        : 'none'
                                "
                            >
                                <button
                                    type="button"
                                    class="flex min-h-8 items-center gap-2 font-semibold"
                                    @click="sortBy(column.key)"
                                >
                                    {{ column.label
                                    }}<component
                                        :is="
                                            filters.direction === 'asc'
                                                ? ArrowUp
                                                : ArrowDown
                                        "
                                        v-if="filters.sort === column.key"
                                        :size="14"
                                    /></button
                                ><Input
                                    v-if="advanced"
                                    v-model="columnFilters[column.key]"
                                    class="mt-2"
                                    :aria-label="
                                        t('dataset.filter_column', {
                                            column: column.label,
                                        })
                                    "
                                    :placeholder="t('dataset.filter')"
                                    @keyup.enter="apply()"
                                />
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows.data"
                            :key="row.sequence"
                            class="hover:bg-blue-50/40"
                        >
                            <td
                                class="border-b border-outline-glass p-4 text-on-surface-variant"
                            >
                                {{ row.sequence }}
                            </td>
                            <td
                                v-for="column in displayedColumns"
                                :key="column.key"
                                class="max-w-md border-b border-outline-glass p-4"
                            >
                                <div
                                    class="max-h-32 overflow-auto whitespace-pre-wrap break-words"
                                >
                                    {{ display(row.payload[column.key]) }}
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!rows.data.length">
                            <td
                                :colspan="displayedColumns.length + 1"
                                class="p-10 text-center"
                            >
                                <p class="font-semibold">
                                    {{ t(`result_state.${emptyState}_title`) }}
                                </p>
                                <p
                                    class="mx-auto mt-2 max-w-lg text-on-surface-variant"
                                >
                                    {{ t(`result_state.${emptyState}_help`) }}
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="rows.links" />
            <p
                v-if="run.has_csv || run.has_json"
                class="mt-4 text-sm text-on-surface-variant"
            >
                {{ t('runs.download_help') }}
            </p>
        </section>
        <details class="panel mt-8 p-5">
            <summary class="font-medium">{{ t('runs.diagnostics') }}</summary>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-on-surface-variant">
                        {{ t('runs.bytes') }}
                    </dt>
                    <dd>{{ run.byte_count.toLocaleString(locale) }}</dd>
                </div>
                <div>
                    <dt class="text-on-surface-variant">
                        {{ t('runs.requests') }}
                    </dt>
                    <dd>{{ run.request_count.toLocaleString(locale) }}</dd>
                </div>
                <div>
                    <dt class="text-on-surface-variant">
                        {{ t('runs.identifier') }}
                    </dt>
                    <dd class="break-all">{{ run.id }}</dd>
                </div>
            </dl>
            <p
                v-if="run.error"
                class="mt-4 whitespace-pre-wrap break-words text-sm text-error-red"
            >
                {{ run.error }}
            </p>
            <pre
                v-if="run.logs"
                class="mt-4 max-h-64 overflow-auto whitespace-pre-wrap rounded-lg bg-surface-container-low p-4 text-sm"
                >{{ run.logs }}</pre>
        </details>
    </AppLayout>
</template>
