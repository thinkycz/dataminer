<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Bot, Check, Play, RefreshCw, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';

interface Recipe {
    id: number;
    name: string;
    start_url: string;
    instructions: string;
    status: string;
    active_version_id: number | null;
}
interface PendingApproval {
    id: string;
    tool: string;
    reason: string | null;
    arguments: Record<string, unknown>;
}
interface Version {
    id: number;
    version: number;
    source: string;
    checksum: string;
    status: string;
    summary: string | null;
    reason: string;
    columns: Array<Record<string, unknown>>;
    test_summary: Record<string, unknown> | null;
    approved_at: string | null;
}
interface Run {
    id: string;
    kind: string;
    status: string;
    progress: number;
    rows: number;
}

const props = defineProps<{
    recipe: Recipe;
    pendingApproval: PendingApproval | null;
    versions: Version[];
    runs: Run[];
}>();
const { t } = useI18n();
let refreshTimer: ReturnType<typeof setInterval> | null = null;

const candidateSource = computed(() =>
    typeof props.pendingApproval?.arguments.source === 'string'
        ? props.pendingApproval.arguments.source
        : '',
);
const candidateColumns = computed(() =>
    Array.isArray(props.pendingApproval?.arguments.proposed_columns)
        ? props.pendingApproval.arguments.proposed_columns
        : [],
);
const isBusy = computed(() =>
    ['generating', 'testing'].includes(props.recipe.status),
);

function decide(decision: 'approve' | 'reject'): void {
    if (!props.pendingApproval) return;
    router.post(
        `/recipes/${props.recipe.id}/approvals/${props.pendingApproval.id}/decide`,
        { decision },
    );
}

onMounted(() => {
    refreshTimer = setInterval(() => {
        if (isBusy.value || props.recipe.status === 'pending_approval') {
            router.reload({
                only: ['recipe', 'pendingApproval', 'versions', 'runs'],
            });
        }
    }, 2500);
});
onBeforeUnmount(() => {
    if (refreshTimer) clearInterval(refreshTimer);
});
</script>

<template>
    <AppLayout :title="recipe.name">
        <Link href="/recipes" class="text-xs font-semibold text-primary"
            >← {{ t('recipes.back') }}</Link
        >
        <header
            class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold">{{ recipe.name }}</h1>
                    <span
                        class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary"
                        >{{ recipe.status }}</span
                    >
                </div>
                <a
                    :href="recipe.start_url"
                    target="_blank"
                    rel="noreferrer"
                    class="mt-2 block break-all text-xs text-on-surface-variant hover:text-primary"
                    >{{ recipe.start_url }}</a
                >
                <p
                    class="mt-3 max-w-3xl whitespace-pre-wrap text-sm text-on-surface-variant"
                >
                    {{ recipe.instructions }}
                </p>
            </div>
            <div class="flex shrink-0 gap-2">
                <Button
                    v-if="recipe.active_version_id"
                    @click="router.post(`/recipes/${recipe.id}/runs/start`)"
                    ><Play :size="15" />{{ t('recipes.run') }}</Button
                ><Button
                    v-if="versions.length === 0 && !isBusy"
                    @click="router.post(`/recipes/${recipe.id}/generate`)"
                    ><Bot :size="15" />{{ t('recipes.generate') }}</Button
                >
            </div>
        </header>

        <p
            class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs leading-relaxed text-amber-900"
        >
            {{ t('recipes.security_notice') }}
        </p>

        <section
            v-if="isBusy"
            class="mt-6 rounded-2xl border border-primary/30 bg-primary/5 p-5"
        >
            <div
                class="flex items-center gap-3 text-sm font-semibold text-primary"
            >
                <RefreshCw :size="18" class="animate-spin" />{{
                    t('recipes.processing')
                }}
            </div>
        </section>

        <section
            v-if="pendingApproval"
            class="mt-6 rounded-2xl border-2 border-amber-400/60 bg-amber-50 p-5 text-slate-900"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-bold uppercase tracking-wide text-amber-700"
                    >
                        {{ t('recipes.approval_required') }}
                    </p>
                    <h2 class="mt-1 text-lg font-bold">
                        {{ pendingApproval.tool }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ pendingApproval.reason ?? t('recipes.test_reason') }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button
                        class="bg-red-600 from-red-600 to-red-700"
                        @click="decide('reject')"
                        ><X :size="15" />{{ t('recipes.reject') }}</Button
                    ><Button @click="decide('approve')"
                        ><Check :size="15" />{{
                            t('recipes.approve_test')
                        }}</Button
                    >
                </div>
            </div>
            <div
                class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(260px,1fr)]"
            >
                <div>
                    <h3 class="mb-2 text-xs font-bold uppercase">
                        {{ t('recipes.source') }}
                    </h3>
                    <pre
                        class="max-h-[32rem] overflow-auto rounded-xl bg-slate-950 p-4 text-xs leading-5 text-slate-100"
                    ><code>{{ candidateSource }}</code></pre>
                </div>
                <div>
                    <h3 class="mb-2 text-xs font-bold uppercase">
                        {{ t('recipes.proposed_columns') }}
                    </h3>
                    <pre
                        class="overflow-auto rounded-xl bg-white p-4 text-xs"
                        >{{ JSON.stringify(candidateColumns, null, 2) }}</pre>
                </div>
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-lg font-bold">{{ t('recipes.versions') }}</h2>
            <div class="mt-3 space-y-4">
                <article
                    v-for="version in versions"
                    :key="version.id"
                    class="rounded-2xl border border-outline-glass bg-surface-container p-5 shadow-sm"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <h3 class="font-bold">
                                {{ t('recipes.version') }} {{ version.version }}
                                <span
                                    class="ml-2 rounded-full bg-surface-container-low px-2 py-1 text-[10px] text-on-surface-variant"
                                    >{{ version.status }}</span
                                >
                            </h3>
                            <p class="mt-1 text-xs text-on-surface-variant">
                                {{ version.reason }} ·
                                {{ version.checksum.slice(0, 12) }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                v-if="version.status === 'tested'"
                                class="h-8"
                                @click="
                                    router.post(
                                        `/recipes/${recipe.id}/versions/${version.id}/approve`,
                                    )
                                "
                                >{{ t('recipes.approve_version') }}</Button
                            ><Button
                                v-if="
                                    !['approved', 'rejected'].includes(
                                        version.status,
                                    )
                                "
                                class="h-8 bg-red-600 from-red-600 to-red-700"
                                @click="
                                    router.post(
                                        `/recipes/${recipe.id}/versions/${version.id}/reject`,
                                    )
                                "
                                >{{ t('recipes.reject') }}</Button
                            ><Button
                                v-if="version.status !== 'testing'"
                                class="h-8"
                                @click="
                                    router.post(
                                        `/recipes/${recipe.id}/versions/${version.id}/repair`,
                                    )
                                "
                                >{{ t('recipes.generate_repair') }}</Button
                            >
                        </div>
                    </div>
                    <p
                        v-if="version.summary"
                        class="mt-3 text-sm text-on-surface-variant"
                    >
                        {{ version.summary }}
                    </p>
                    <pre
                        class="mt-4 max-h-80 overflow-auto rounded-xl bg-slate-950 p-4 text-xs leading-5 text-slate-100"
                    ><code>{{ version.source }}</code></pre>
                    <pre
                        v-if="version.test_summary"
                        class="mt-3 overflow-auto rounded-xl bg-surface-container-low p-3 text-xs"
                        >{{
                            JSON.stringify(version.test_summary, null, 2)
                        }}</pre>
                </article>
                <p
                    v-if="versions.length === 0"
                    class="rounded-xl border border-dashed border-outline-glass p-8 text-center text-sm text-on-surface-variant"
                >
                    {{ t('recipes.no_versions') }}
                </p>
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-lg font-bold">{{ t('recipes.recent_runs') }}</h2>
            <div
                class="mt-3 overflow-hidden rounded-2xl border border-outline-glass bg-surface-container"
            >
                <table class="w-full text-left text-xs">
                    <thead class="bg-surface-container-low">
                        <tr>
                            <th class="p-3">{{ t('runs.kind') }}</th>
                            <th class="p-3">{{ t('runs.status') }}</th>
                            <th class="p-3">{{ t('runs.rows') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="run in runs"
                            :key="run.id"
                            class="border-t border-outline-glass"
                        >
                            <td class="p-3">
                                <Link
                                    :href="`/scrape-runs/${run.id}`"
                                    class="font-semibold text-primary"
                                    >{{ run.kind }}</Link
                                >
                            </td>
                            <td class="p-3">
                                {{ run.status }} · {{ run.progress }}%
                            </td>
                            <td class="p-3">{{ run.rows }}</td>
                        </tr>
                        <tr v-if="runs.length === 0">
                            <td
                                colspan="3"
                                class="p-6 text-center text-on-surface-variant"
                            >
                                {{ t('runs.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
