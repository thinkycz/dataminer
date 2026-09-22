<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowRight, Globe, Plus, Search } from '@lucide/vue';
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
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search: string };
}>();
const { t } = useI18n();
const search = ref(props.filters.search);
function applySearch(): void {
    router.get(
        '/recipes',
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
                href="/recipes/create"
                class="button button-primary"
                ><Plus :size="18" />{{ t('recipes.new') }}</Link
            ></PageHeader
        >
        <div
            class="mb-8 rounded-xl border border-blue-100 bg-blue-50/60 p-5 sm:p-6"
        >
            <h2 class="font-semibold">{{ t('home.how_title') }}</h2>
            <p class="mt-1 text-sm text-on-surface-variant">
                {{ t('home.how_help') }}
            </p>
        </div>
        <form class="mb-6 flex max-w-lg gap-2" @submit.prevent="applySearch">
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
        <div v-if="recipes.data.length" class="grid gap-4">
            <article
                v-for="recipe in recipes.data"
                :key="recipe.id"
                class="panel flex flex-col justify-between gap-5 p-5 sm:flex-row sm:items-center sm:p-6"
            >
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            :href="`/recipes/${recipe.id}`"
                            class="text-lg font-semibold break-words hover:text-primary"
                            >{{ recipe.name }}</Link
                        ><StatusBadge :status="recipe.status" />
                    </div>
                    <p
                        class="mt-2 flex items-start gap-2 text-sm text-on-surface-variant"
                    >
                        <Globe :size="16" class="mt-1 shrink-0" /><span
                            class="break-all"
                            >{{ recipe.start_url }}</span
                        >
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-3">
                    <Link
                        v-if="recipe.last_run"
                        :href="`/scrape-runs/${recipe.last_run.id}`"
                        class="button button-secondary"
                        >{{ t('home.view_results') }}</Link
                    ><Link
                        :href="`/recipes/${recipe.id}`"
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
                href="/recipes/create"
                class="button button-primary"
                >{{ t('recipes.new') }}</Link
            ></EmptyState
        >
        <Pagination :links="recipes.links" />
    </AppLayout>
</template>
