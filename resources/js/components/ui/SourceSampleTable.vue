<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    sampleCsvRows,
    samplePath,
    sampleColumnLabel,
} from '@/lib/source-sample';
const props = defineProps<{
    sample: unknown;
    sourceType: string;
    recordsPath: string;
    paths: string[];
    selectedPaths: string[];
    delimiter: string;
    namespaces: Record<string, string>;
}>();
const emit = defineEmits<{ toggleColumn: [path: string] }>();
const { t } = useI18n();
const columns = computed(() => props.paths.slice(0, 50));
const rows = computed(() => {
    if (props.sourceType === 'csv' && typeof props.sample === 'string') {
        const parsed = sampleCsvRows(props.sample, props.delimiter);
        return parsed
            .slice(1)
            .map((row) =>
                columns.value.map(
                    (column) =>
                        row[
                            (parsed[0] ?? []).findIndex(
                                (header) =>
                                    header.trim().replace(/^\uFEFF/, '') ===
                                    column,
                            )
                        ] ?? '',
                ),
            );
    }
    if (props.sourceType === 'xml' && typeof props.sample === 'string') {
        try {
            const document = new DOMParser().parseFromString(
                props.sample,
                'application/xml',
            );
            const resolver = (prefix: string | null) =>
                prefix ? (props.namespaces[prefix] ?? null) : null;
            const records = document.evaluate(
                props.recordsPath || '/*',
                document,
                resolver,
                XPathResult.ORDERED_NODE_SNAPSHOT_TYPE,
                null,
            );
            return Array.from(
                { length: Math.min(records.snapshotLength, 5) },
                (_, i) =>
                    columns.value.map(
                        (path) =>
                            document.evaluate(
                                path,
                                records.snapshotItem(i)!,
                                resolver,
                                XPathResult.STRING_TYPE,
                                null,
                            ).stringValue,
                    ),
            );
        } catch {
            return [];
        }
    }
    const records = samplePath(props.sample, props.recordsPath);
    return (
        Array.isArray(records) ? records.slice(0, 5) : records ? [records] : []
    ).map((record) =>
        columns.value.map((path) => {
            const value = samplePath(record, path);
            return value === null || value === undefined
                ? ''
                : typeof value === 'object'
                  ? JSON.stringify(value)
                  : String(value);
        }),
    );
});
</script>
<template>
    <div
        v-if="columns.length && rows.length"
        class="my-4 max-w-full overflow-x-auto rounded-2xl border border-outline-glass"
        tabindex="0"
        :aria-label="t('builder.sample_title')"
    >
        <table class="w-full text-left text-sm">
            <caption class="sr-only">
                {{
                    t('builder.sample_title')
                }}
            </caption>
            <thead class="bg-lavender/50">
                <tr>
                    <th
                        v-for="column in columns"
                        :key="column"
                        scope="col"
                        class="min-w-36 max-w-60 px-4 py-1"
                        :title="column"
                    >
                        <label
                            class="flex min-h-11 cursor-pointer items-center gap-3"
                        >
                            <input
                                type="checkbox"
                                :checked="selectedPaths.includes(column)"
                                :disabled="
                                    selectedPaths.length === 1 &&
                                    selectedPaths.includes(column)
                                "
                                :aria-label="
                                    t('redesign.keep_column', {
                                        name:
                                            sourceType === 'csv'
                                                ? column
                                                : sampleColumnLabel(
                                                      column,
                                                      paths,
                                                  ),
                                    })
                                "
                                @change="emit('toggleColumn', column)"
                                class="h-4 w-4"
                            />
                            <span>{{
                                sourceType === 'csv'
                                    ? column
                                    : sampleColumnLabel(column, paths)
                            }}</span>
                        </label>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(row, index) in rows"
                    :key="index"
                    class="border-t border-outline-glass"
                >
                    <td
                        v-for="(value, cell) in row"
                        :key="cell"
                        class="max-w-60 truncate px-4 py-3"
                        :title="value"
                    >
                        {{ value }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
