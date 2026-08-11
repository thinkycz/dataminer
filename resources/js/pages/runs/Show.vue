<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Download, Search, XCircle } from '@lucide/vue';
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

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
interface Paginator<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    total: number;
}
interface Filters {
    search: string;
    column: Record<string, string>;
    sort: string;
    direction: string;
    visible: string[];
}

const props = defineProps<{
    run: Run;
    columns: Column[];
    rows: Paginator<Row>;
    filters: Filters;
}>();
const { t } = useI18n();
const search = ref(props.filters.search);
const columnFilters = reactive<Record<string, string>>({
    ...props.filters.column,
});
const visible = ref<string[]>([...props.filters.visible]);
let events: EventSource | null = null;

function apply(): void {
    router.get(
        `/scrape-runs/${props.run.id}`,
        {
            search: search.value,
            filter: columnFilters,
            sort: props.filters.sort,
            direction: props.filters.direction,
            visible: visible.value,
        },
        { preserveState: true, replace: true },
    );
}

function clearFilters(): void {
    search.value = '';
    for (const key of Object.keys(columnFilters)) delete columnFilters[key];
    visible.value = props.columns.map((column) => column.key);
    router.get(
        `/scrape-runs/${props.run.id}`,
        {},
        { preserveState: true, replace: true },
    );
}

function sortBy(key: string): void {
    const direction =
        props.filters.sort === key && props.filters.direction === 'asc'
            ? 'desc'
            : 'asc';
    router.get(
        `/scrape-runs/${props.run.id}`,
        {
            search: search.value,
            filter: columnFilters,
            sort: key,
            direction,
            visible: visible.value,
        },
        { preserveState: true, replace: true },
    );
}

function toggleColumn(key: string): void {
    visible.value = visible.value.includes(key)
        ? visible.value.filter((item) => item !== key)
        : [...visible.value, key];
    apply();
}

function display(value: unknown): string {
    if (value === null || value === undefined) return '';
    return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

onMounted(() => {
    if (['queued', 'running'].includes(props.run.status)) {
        events = new EventSource(`/scrape-runs/${props.run.id}/stream`);
        events.onmessage = (event) => {
            const state = JSON.parse(event.data) as { status: string };
            router.reload({ only: ['run', 'rows', 'columns'] });
            if (!['queued', 'running'].includes(state.status)) events?.close();
        };
    }
});
onBeforeUnmount(() => events?.close());
</script>

<template>
    <AppLayout :title="t('runs.detail_title')">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <Link
                    href="/scrape-runs"
                    class="text-xs font-semibold text-primary"
                    >← {{ t('runs.back') }}</Link
                >
                <h1 class="mt-3 text-2xl font-bold">
                    {{ t('runs.detail_title') }}
                </h1>
                <p class="mt-1 font-mono text-xs text-on-surface-variant">
                    {{ run.id }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="['queued', 'running'].includes(run.status)"
                    class="bg-red-600 from-red-600 to-red-700"
                    @click="router.post(`/scrape-runs/${run.id}/cancel`)"
                    ><XCircle :size="15" />{{ t('runs.cancel') }}</Button
                ><Link
                    v-if="run.has_json"
                    :href="`/scrape-runs/${run.id}/download/json`"
                    ><Button><Download :size="15" />JSON</Button></Link
                ><Link
                    v-if="run.has_csv"
                    :href="`/scrape-runs/${run.id}/download/csv`"
                    ><Button><Download :size="15" />CSV</Button></Link
                >
            </div>
        </div>

        <section class="mt-6 grid gap-3 sm:grid-cols-4">
            <div
                class="rounded-xl border border-outline-glass bg-surface-container p-4"
            >
                <p class="text-[10px] uppercase text-on-surface-variant">
                    {{ t('runs.status') }}
                </p>
                <p class="mt-1 font-bold">
                    {{ run.status }} · {{ run.progress }}%
                </p>
            </div>
            <div
                class="rounded-xl border border-outline-glass bg-surface-container p-4"
            >
                <p class="text-[10px] uppercase text-on-surface-variant">
                    {{ t('runs.rows') }}
                </p>
                <p class="mt-1 font-bold">
                    {{ run.row_count.toLocaleString() }}
                </p>
            </div>
            <div
                class="rounded-xl border border-outline-glass bg-surface-container p-4"
            >
                <p class="text-[10px] uppercase text-on-surface-variant">
                    {{ t('runs.bytes') }}
                </p>
                <p class="mt-1 font-bold">
                    {{ run.byte_count.toLocaleString() }}
                </p>
            </div>
            <div
                class="rounded-xl border border-outline-glass bg-surface-container p-4"
            >
                <p class="text-[10px] uppercase text-on-surface-variant">
                    {{ t('runs.requests') }}
                </p>
                <p class="mt-1 font-bold">
                    {{ run.request_count.toLocaleString() }}
                </p>
            </div>
        </section>
        <div
            v-if="run.error"
            class="mt-4 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-800"
        >
            {{ run.error }}
        </div>
        <details
            v-if="run.logs"
            class="mt-4 rounded-xl border border-outline-glass bg-surface-container p-4"
        >
            <summary class="cursor-pointer text-xs font-bold">
                {{ t('runs.logs') }}
            </summary>
            <pre
                class="mt-3 max-h-64 overflow-auto whitespace-pre-wrap text-xs"
                >{{ run.logs }}</pre>
        </details>

        <section class="mt-8">
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <h2 class="text-lg font-bold">{{ t('dataset.title') }}</h2>
                    <p class="text-xs text-on-surface-variant">
                        {{ rows.total.toLocaleString() }}
                        {{ t('dataset.rows') }}
                    </p>
                </div>
                <form class="flex gap-2" @submit.prevent="apply">
                    <Input
                        v-model="search"
                        :placeholder="t('dataset.search')"
                    /><Button type="submit"><Search :size="15" /></Button
                    ><Button
                        class="bg-surface-container-low from-surface-container-low to-surface-container-low text-on-surface"
                        @click="clearFilters"
                        >{{ t('dataset.clear') }}</Button
                    >
                </form>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <label
                    v-for="column in columns"
                    :key="column.key"
                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-outline-glass bg-surface-container px-3 py-2 text-xs"
                    ><input
                        type="checkbox"
                        :checked="visible.includes(column.key)"
                        @change="toggleColumn(column.key)"
                    />{{ column.label }}</label
                >
            </div>
            <div
                class="mt-4 max-h-[65vh] overflow-auto rounded-2xl border border-outline-glass bg-surface-container"
            >
                <table
                    class="min-w-full border-separate border-spacing-0 text-left text-xs"
                >
                    <thead class="sticky top-0 z-10 bg-surface-container-low">
                        <tr>
                            <th class="border-b border-outline-glass p-3">#</th>
                            <th
                                v-for="column in columns.filter((item) =>
                                    visible.includes(item.key),
                                )"
                                :key="column.key"
                                class="min-w-40 border-b border-outline-glass p-3"
                            >
                                <button
                                    type="button"
                                    class="font-bold hover:text-primary"
                                    @click="sortBy(column.key)"
                                >
                                    {{ column.label }}
                                    <span
                                        class="font-normal text-on-surface-variant"
                                        >{{ column.type }}</span
                                    ></button
                                ><Input
                                    v-model="columnFilters[column.key]"
                                    class="mt-2 h-8"
                                    :placeholder="t('dataset.filter')"
                                    @keyup.enter="apply"
                                />
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows.data"
                            :key="row.sequence"
                            class="odd:bg-white/30"
                        >
                            <td
                                class="border-b border-outline-glass p-3 text-on-surface-variant"
                            >
                                {{ row.sequence }}
                            </td>
                            <td
                                v-for="column in columns.filter((item) =>
                                    visible.includes(item.key),
                                )"
                                :key="column.key"
                                class="max-w-md border-b border-outline-glass p-3"
                            >
                                <div
                                    class="max-h-24 overflow-auto whitespace-pre-wrap break-words"
                                >
                                    {{ display(row.payload[column.key]) }}
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.data.length === 0">
                            <td
                                :colspan="visible.length + 1"
                                class="p-10 text-center text-on-surface-variant"
                            >
                                {{ t('dataset.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <nav class="mt-4 flex flex-wrap gap-2">
                <Link
                    v-for="link in rows.links"
                    :key="link.label"
                    :href="link.url ?? ''"
                    :class="[
                        'rounded-lg border px-3 py-2 text-xs',
                        link.active
                            ? 'border-primary bg-primary text-white'
                            : 'border-outline-glass',
                        !link.url ? 'pointer-events-none opacity-40' : '',
                    ]"
                    v-html="link.label"
                />
            </nav>
        </section>
    </AppLayout>
</template>
