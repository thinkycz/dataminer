<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Plus, Search, Play } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

interface RecipeItem {
    id: number;
    name: string;
    start_url: string;
    status: string;
    active_version: number | null;
    last_run: { id: string; status: string } | null;
}

interface Paginator<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    recipes: Paginator<RecipeItem>;
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
        <header
            class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-bold">{{ t('recipes.title') }}</h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('recipes.subtitle') }}
                </p>
            </div>
            <Link href="/recipes/create"
                ><Button
                    ><Plus :size="16" />{{ t('recipes.new') }}</Button
                ></Link
            >
        </header>
        <form class="mb-5 flex max-w-lg gap-2" @submit.prevent="applySearch">
            <Input v-model="search" :placeholder="t('recipes.search')" />
            <Button type="submit"><Search :size="16" /></Button>
        </form>
        <div
            class="overflow-hidden rounded-2xl border border-outline-glass bg-surface-container shadow-sm"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead
                        class="bg-surface-container-low text-on-surface-variant"
                    >
                        <tr>
                            <th class="p-4">{{ t('recipes.name') }}</th>
                            <th class="p-4">{{ t('recipes.status') }}</th>
                            <th class="p-4">
                                {{ t('recipes.active_version') }}
                            </th>
                            <th class="p-4">{{ t('recipes.last_run') }}</th>
                            <th class="p-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-glass">
                        <tr v-for="recipe in recipes.data" :key="recipe.id">
                            <td class="p-4">
                                <Link
                                    :href="`/recipes/${recipe.id}`"
                                    class="font-semibold text-primary hover:underline"
                                    >{{ recipe.name }}</Link
                                >
                                <div
                                    class="mt-1 max-w-md truncate text-on-surface-variant"
                                >
                                    {{ recipe.start_url }}
                                </div>
                            </td>
                            <td class="p-4">
                                <span
                                    class="rounded-full bg-primary/10 px-2.5 py-1 font-semibold text-primary"
                                    >{{ recipe.status }}</span
                                >
                            </td>
                            <td class="p-4">
                                {{ recipe.active_version ?? '—' }}
                            </td>
                            <td class="p-4">
                                {{ recipe.last_run?.status ?? '—' }}
                            </td>
                            <td class="p-4 text-right">
                                <Button
                                    v-if="recipe.active_version"
                                    class="h-8"
                                    @click="
                                        router.post(
                                            `/recipes/${recipe.id}/runs/start`,
                                        )
                                    "
                                    ><Play :size="14" />{{
                                        t('recipes.run')
                                    }}</Button
                                >
                            </td>
                        </tr>
                        <tr v-if="recipes.data.length === 0">
                            <td
                                colspan="5"
                                class="p-10 text-center text-on-surface-variant"
                            >
                                {{ t('recipes.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <nav class="mt-4 flex flex-wrap gap-2">
            <Link
                v-for="link in recipes.links"
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
