<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    Globe2,
    Plus,
    Search,
    Clock,
    FileSpreadsheet,
    Braces,
    Rss,
} from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Pagination from '@/components/ui/Pagination.vue';
import CollectorIllustration from '@/components/ui/CollectorIllustration.vue';
import type { CollectorCard } from '@/types/collector';
import { collectorNextAction } from '@/lib/collector-workflow';
const props = defineProps<{
    recipes: {
        data: CollectorCard[];
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search: string };
}>();
const { t, locale } = useI18n();
const search = ref(props.filters.search);
function applySearch(): void {
    router.get(
        '/collectors',
        { search: search.value },
        { preserveState: true, replace: true },
    );
}
function date(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat(locale.value, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : t('common.not_set');
}
const sourceIcons: Record<string, typeof Globe2> = {
    website: Globe2,
    json: Braces,
    csv: FileSpreadsheet,
    xml: Rss,
};
const sourceColors: Record<string, string> = {
    website: 'bg-lavender text-primary',
    json: 'bg-peach text-orange-900',
    csv: 'bg-mint text-emerald-900',
    xml: 'bg-sky text-blue-900',
};
</script>
<template>
    <AppLayout :title="t('recipes.title')">
        <PageHeader
            :title="t('recipes.title')"
            :description="t('redesign.home_help')"
            ><Link
                v-if="recipes.data.length || filters.search"
                href="/collectors/create"
                class="button button-primary"
                ><Plus :size="18" />{{ t('recipes.new') }}</Link
            ></PageHeader
        >
        <div
            v-if="recipes.data.length || filters.search"
            class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-outline-glass pb-5"
        >
            <div>
                <p class="text-sm font-semibold text-on-surface">
                    {{
                        t('home.collection_count', recipes.total, {
                            named: { count: recipes.total },
                        })
                    }}
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
            class="grid gap-5 md:grid-cols-2"
            :class="recipes.data.length > 2 ? '2xl:grid-cols-3' : ''"
        >
            <article
                v-for="recipe in recipes.data"
                :key="recipe.id"
                class="collector-card panel relative flex min-w-0 flex-col overflow-hidden p-6 transition-shadow hover:shadow-md"
            >
                <div class="mb-5 flex items-center justify-between gap-3">
                    <span
                        class="pastel-icon"
                        :class="sourceColors[recipe.source_type ?? 'website']"
                        ><component
                            :is="
                                sourceIcons[recipe.source_type ?? 'website'] ??
                                Globe2
                            "
                            :size="23"
                            aria-hidden="true" /></span
                    ><StatusBadge :status="recipe.status" />
                </div>
                <Link
                    :href="`/collectors/${recipe.id}`"
                    class="font-heading text-xl font-bold break-words hover:text-primary"
                    >{{ recipe.name }}</Link
                >
                <p
                    class="mt-1 truncate text-sm text-on-surface-variant"
                    :title="recipe.start_url"
                >
                    {{ recipe.start_url }}
                </p>
                <div
                    class="my-5 flex-1 space-y-3 rounded-2xl bg-surface-bg p-4 text-sm"
                >
                    <div v-if="recipe.last_run">
                        <p class="mb-2 text-on-surface-variant">
                            {{ t('redesign.last_collection') }}
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <StatusBadge
                                :status="recipe.last_run.status"
                            /><span>{{
                                t('runs.row_count', {
                                    count: recipe.last_run.rows,
                                })
                            }}</span>
                        </div>
                        <p
                            v-if="recipe.last_run.finished_at"
                            class="mt-2 text-xs text-on-surface-variant"
                        >
                            {{ date(recipe.last_run.finished_at) }}
                        </p>
                    </div>
                    <div v-else>
                        <p class="font-medium">
                            {{
                                t(
                                    recipe.review_ready
                                        ? 'redesign.preview_waiting'
                                        : recipe.active_version === null
                                          ? 'redesign.draft_waiting'
                                          : 'redesign.first_run_waiting',
                                )
                            }}
                        </p>
                        <p class="mt-1 text-on-surface-variant">
                            {{
                                t(
                                    recipe.review_ready
                                        ? 'redesign.preview_waiting_help'
                                        : recipe.active_version === null
                                          ? 'redesign.draft_waiting_help'
                                          : 'redesign.first_run_help',
                                )
                            }}
                        </p>
                    </div>
                    <p
                        v-if="recipe.schedule"
                        class="flex items-start gap-2 text-on-surface-variant"
                    >
                        <Clock :size="16" class="mt-1 shrink-0" /><span
                            >{{
                                recipe.schedule.status === 'active'
                                    ? t('redesign.next_collection')
                                    : t('builder.schedule_paused')
                            }}<span
                                v-if="
                                    recipe.schedule.status === 'active' &&
                                    recipe.schedule.next_run_at
                                "
                                class="block text-on-surface"
                                >{{ date(recipe.schedule.next_run_at) }}</span
                            ></span
                        >
                    </p>
                </div>
                <Link
                    :href="collectorNextAction(recipe).href"
                    class="button button-primary self-start"
                    >{{ t(collectorNextAction(recipe).label)
                    }}<ArrowRight :size="17"
                /></Link>
            </article>
            <Link
                v-if="!filters.search"
                href="/collectors/create"
                class="group flex min-h-60 flex-col items-center justify-center rounded-3xl border border-dashed border-primary/25 bg-lavender/30 p-8 text-center transition-colors hover:border-primary hover:bg-lavender/60"
            >
                <span
                    class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-primary shadow-sm transition-transform group-hover:-translate-y-1"
                    ><Plus :size="25"
                /></span>
                <span class="font-heading text-xl font-bold">{{
                    t('redesign.add_source')
                }}</span>
                <span class="mt-2 max-w-64 text-sm text-on-surface-variant">{{
                    t('redesign.add_source_help')
                }}</span>
                <span
                    class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-primary-container"
                    >{{ t('redesign.start_fresh') }} <ArrowRight :size="16"
                /></span>
            </Link>
        </div>
        <EmptyState
            v-else-if="filters.search"
            :title="t('home.no_matches')"
            :description="t('home.search_help')"
            ><Button
                variant="secondary"
                @click="
                    search = '';
                    applySearch();
                "
                >{{ t('dataset.clear') }}</Button
            ></EmptyState
        >
        <section v-else class="panel overflow-hidden">
            <div
                class="flex flex-col-reverse items-center gap-5 bg-lavender/55 px-6 py-8 text-center sm:px-10 sm:py-12 lg:flex-row-reverse lg:justify-between lg:text-left"
            >
                <CollectorIllustration class="w-32 shrink-0 sm:w-56 lg:w-64" />
                <div>
                    <h2
                        class="max-w-md text-3xl font-bold leading-tight sm:text-4xl"
                    >
                        {{ t('redesign.empty_title') }}
                    </h2>
                    <p class="mt-3 max-w-xl text-on-surface-variant">
                        {{ t('redesign.empty_help') }}
                    </p>
                    <Link
                        href="/collectors/create"
                        class="button button-primary mt-6"
                        ><Plus :size="18" />{{ t('redesign.first') }}</Link
                    >
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3 p-6 sm:gap-6 sm:p-8">
                <div
                    v-for="(step, i) in ['source', 'choose', 'preview']"
                    :key="step"
                >
                    <span
                        class="mb-3 flex h-9 w-9 items-center justify-center rounded-xl font-semibold"
                        :class="['bg-lavender', 'bg-mint', 'bg-peach'][i]"
                        >{{ i + 1 }}</span
                    >
                    <h3 class="font-semibold">{{ t(`redesign.${step}`) }}</h3>
                    <p
                        class="mt-2 hidden text-sm text-on-surface-variant sm:block"
                    >
                        {{ t(`redesign.intro_${step}`) }}
                    </p>
                </div>
            </div>
            <div
                class="border-t border-outline-glass px-8 py-5 text-sm text-on-surface-variant"
            >
                <p>{{ t('redesign.examples') }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span
                        v-for="example in ['prices', 'listings', 'catalogs']"
                        :key="example"
                        class="rounded-full bg-surface-container-low px-3 py-1"
                        >{{ t(`redesign.${example}`) }}</span
                    >
                </div>
            </div>
        </section>
        <Pagination :links="recipes.links" />
    </AppLayout>
</template>
