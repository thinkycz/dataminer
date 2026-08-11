<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';

interface Run {
    id: string;
    recipe_name: string | null;
    kind: string;
    status: string;
    progress: number;
    rows: number;
    finished_at: string | null;
}
interface Paginator<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
}
defineProps<{ runs: Paginator<Run> }>();
const { t } = useI18n();
</script>

<template>
    <AppLayout :title="t('runs.title')">
        <header class="mb-6">
            <h1 class="text-2xl font-bold">{{ t('runs.title') }}</h1>
            <p class="mt-1 text-sm text-on-surface-variant">
                {{ t('runs.subtitle') }}
            </p>
        </header>
        <div
            class="overflow-hidden rounded-2xl border border-outline-glass bg-surface-container"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-surface-container-low">
                        <tr>
                            <th class="p-4">{{ t('runs.recipe') }}</th>
                            <th class="p-4">{{ t('runs.kind') }}</th>
                            <th class="p-4">{{ t('runs.status') }}</th>
                            <th class="p-4">{{ t('runs.progress') }}</th>
                            <th class="p-4">{{ t('runs.rows') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="run in runs.data"
                            :key="run.id"
                            class="border-t border-outline-glass"
                        >
                            <td class="p-4">
                                <Link
                                    :href="`/scrape-runs/${run.id}`"
                                    class="font-semibold text-primary"
                                    >{{ run.recipe_name ?? run.id }}</Link
                                >
                            </td>
                            <td class="p-4">{{ run.kind }}</td>
                            <td class="p-4">{{ run.status }}</td>
                            <td class="p-4">{{ run.progress }}%</td>
                            <td class="p-4">{{ run.rows }}</td>
                        </tr>
                        <tr v-if="runs.data.length === 0">
                            <td
                                colspan="5"
                                class="p-10 text-center text-on-surface-variant"
                            >
                                {{ t('runs.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <nav class="mt-4 flex gap-2">
            <Link
                v-for="link in runs.links"
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
    </AppLayout>
</template>
