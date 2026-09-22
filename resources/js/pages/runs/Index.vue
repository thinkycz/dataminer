<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Pagination from '@/components/ui/Pagination.vue';
interface Run {
    id: string;
    recipe_name: string | null;
    kind: string;
    status: string;
    progress: number;
    rows: number;
    finished_at: string | null;
}
defineProps<{
    runs: {
        data: Run[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();
const { t, locale } = useI18n();
</script>
<template>
    <AppLayout :title="t('runs.title')">
        <PageHeader
            :title="t('runs.title')"
            :description="t('runs.subtitle')"
        />
        <div
            v-if="runs.data.length"
            class="panel divide-y divide-outline-glass"
        >
            <article
                v-for="run in runs.data"
                :key="run.id"
                class="flex flex-col justify-between gap-4 p-5 sm:flex-row sm:items-center sm:p-6"
            >
                <div class="min-w-0">
                    <Link
                        :href="`/runs/${run.id}`"
                        class="text-lg font-semibold break-words hover:text-primary"
                        >{{ run.recipe_name ?? t('runs.detail_title') }}</Link
                    >
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t(`runs.${run.kind}`)
                        }}<span v-if="run.finished_at">
                            ·
                            {{
                                new Date(run.finished_at).toLocaleString(locale)
                            }}</span
                        >
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <span class="text-sm">{{
                        t('runs.row_count', { count: run.rows })
                    }}</span
                    ><StatusBadge :status="run.status" /><Link
                        :href="`/runs/${run.id}`"
                        class="button button-secondary"
                        >{{ t('home.view_results') }}</Link
                    >
                </div>
            </article>
        </div>
        <EmptyState
            v-else
            :title="t('runs.empty')"
            :description="t('runs.empty_help')"
            ><Link href="/collectors" class="button button-primary">{{
                t('recipes.back')
            }}</Link></EmptyState
        ><Pagination :links="runs.links" />
    </AppLayout>
</template>
