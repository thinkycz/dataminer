<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowRight, Globe2, Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Pagination from '@/components/ui/Pagination.vue';
interface RecipeItem {
    id: number;
    name: string;
    start_url: string;
    status: string;
    active_version: number | null;
    last_run: { id: string; status: string } | null;
}
const props = defineProps<{
    recipes: {
        data: RecipeItem[];
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search: string };
}>();
const { t } = useI18n();
const search = ref(props.filters.search);
function applySearch(): void {
    router.get(
        '/collectors',
        { search: search.value },
        { preserveState: true, replace: true },
    );
}
</script>
<template>
    <AppLayout :title="t('recipes.title')">
        <PageHeader
            :title="t('recipes.title')"
            :description="t('recipes.subtitle')"
            ><Link
                v-if="recipes.data.length || filters.search"
                href="/collectors/create"
                class="button button-primary"
                ><Plus :size="18" />{{ t('recipes.new') }}</Link
            ></PageHeader
        >
        <div
            class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-outline-glass pb-5"
        >
            <div>
                <p class="text-sm font-semibold text-on-surface">
                    {{ t('home.collection_count', { count: recipes.total }) }}
                </p>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('home.workspace_help') }}
                </p>
            </div>
            <form
                class="flex w-full max-w-sm gap-2 sm:w-auto"
                @submit.prevent="applySearch"
            >
                <Input
                    v-model="search"
                    :aria-label="t('recipes.search')"
                    :placeholder="t('recipes.search')"
                /><Button
                    type="submit"
                    variant="secondary"
                    :aria-label="t('common.search')"
                    ><Search :size="18"
                /></Button>
            </form>
        </div>
        <div
            v-if="recipes.data.length"
            class="overflow-hidden rounded-2xl border border-outline-glass bg-white"
        >
            <article
                v-for="recipe in recipes.data"
                :key="recipe.id"
                class="flex flex-col justify-between gap-4 border-b border-outline-glass p-5 last:border-b-0 sm:flex-row sm:items-center sm:px-6 sm:py-5"
            >
                <div class="flex min-w-0 items-start gap-4">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#e5f0e8] text-primary"
                        ><Globe2 :size="19" aria-hidden="true"
                    /></span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <Link
                                :href="`/collectors/${recipe.id}`"
                                class="text-base font-semibold break-words hover:text-primary"
                                >{{ recipe.name }}</Link
                            ><StatusBadge :status="recipe.status" />
                        </div>
                        <p
                            class="mt-1 truncate text-sm text-on-surface-variant"
                        >
                            {{ recipe.start_url }}
                        </p>
                    </div>
                </div>
                <div
                    class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end"
                >
                    <Link
                        v-if="recipe.last_run"
                        :href="`/runs/${recipe.last_run.id}`"
                        class="button button-secondary"
                        >{{ t('home.view_results') }}</Link
                    ><Link
                        :href="`/collectors/${recipe.id}`"
                        class="button button-primary"
                        >{{
                            recipe.active_version && recipe.status === 'ready'
                                ? t('home.open_collector')
                                : t('home.continue_setup')
                        }}<ArrowRight :size="16"
                    /></Link>
                </div>
            </article>
        </div>
        <EmptyState
            v-else
            :title="filters.search ? t('home.no_matches') : t('recipes.empty')"
            :description="
                filters.search ? t('home.search_help') : t('home.empty_help')
            "
            ><Button
                v-if="filters.search"
                variant="secondary"
                @click="
                    search = '';
                    applySearch();
                "
                >{{ t('dataset.clear') }}</Button
            ><Link
                v-else
                href="/collectors/create"
                class="button button-primary"
                >{{ t('recipes.new') }}</Link
            ></EmptyState
        >
        <Pagination :links="recipes.links" />
    </AppLayout>
</template>
