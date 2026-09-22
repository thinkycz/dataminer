<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import type { RequestPayload } from '@inertiajs/core';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ActionErrors from '@/components/ui/ActionErrors.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

interface Recipe {
    id: number;
    name: string;
    start_url: string;
    instructions: string;
    status: string;
    active_version_id: number | null;
}
interface Version {
    id: number;
    version: number;
    definition_format: 'legacy_js' | 'definition';
    definition: Record<string, unknown> | null;
    source: string;
    status: string;
    sample_run_id: string | null;
    summary: string | null;
}
interface Run {
    id: string;
    kind: string;
    status: string;
    rows: number;
}
interface Schedule {
    id: number;
    cadence: string;
    timezone: string;
    local_time: string;
    weekday: number | null;
    cron_expression: string | null;
    status: string;
    next_run_at: string | null;
}
interface EventEntry {
    kind: string;
    created_at: string;
    run_id: string | null;
}
const props = defineProps<{
    recipe: Recipe;
    setupDraft: Record<string, unknown> | null;
    assistanceAvailable: boolean;
    schedule: Schedule | null;
    events: EventEntry[];
    emailNotifications: boolean;
    pendingApproval: unknown;
    versions: Version[];
    runs: Run[];
}>();
const { t, locale, tm, rt } = useI18n();
const processing = ref(false);
const latest = computed(() => props.versions[0] ?? null);
const weekdays = computed(() => {
    const translated = tm('common.weekdays');
    return Array.isArray(translated) ? translated.map((day) => rt(day)) : [];
});
const testedVersion = computed(
    () =>
        props.versions.find(
            (version) =>
                version.status === 'tested' &&
                version.definition_format === 'definition',
        ) ?? null,
);
const form = reactive({
    cadence:
        props.schedule?.cadence === 'advanced'
            ? 'cron'
            : (props.schedule?.cadence ?? 'daily'),
    timezone: props.schedule?.timezone ?? 'Europe/Prague',
    local_time: props.schedule?.local_time ?? '09:00',
    weekday: props.schedule?.weekday ?? 1,
    cron_expression: props.schedule?.cron_expression ?? '',
});
function post(path: string, data: Record<string, unknown> = {}): void {
    if (processing.value) return;
    processing.value = true;
    router.post(path, data as unknown as RequestPayload, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
}
function saveSchedule(): void {
    post(`/collectors/${props.recipe.id}/schedule`, {
        ...form,
        cadence: form.cadence === 'cron' ? 'advanced' : form.cadence,
        weekday: form.cadence === 'weekly' ? Number(form.weekday) : null,
        cron_expression: form.cadence === 'cron' ? form.cron_expression : null,
    });
}
function setNotifications(event: globalThis.Event): void {
    post(`/collectors/${props.recipe.id}/notifications`, {
        enabled: (event.target as HTMLInputElement).checked,
    });
}
function approveVersion(): void {
    if (testedVersion.value)
        post(
            `/collectors/${props.recipe.id}/versions/${testedVersion.value.id}/approve`,
        );
}
function formatDate(value: string | null): string {
    if (!value) return t('common.not_set');
    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
</script>

<template>
    <AppLayout :title="recipe.name">
        <div class="mx-auto max-w-5xl">
            <Link href="/collectors" class="text-link mb-6 inline-block"
                >← {{ t('recipes.back') }}</Link
            >
            <PageHeader :title="recipe.name" :description="recipe.start_url"
                ><template #context
                    ><StatusBadge :status="recipe.status" /></template
            ></PageHeader>
            <ActionErrors />

            <section class="panel mb-6 p-5 sm:p-7">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold">
                            {{
                                testedVersion
                                    ? t('builder.tested')
                                    : t('builder.title')
                            }}
                        </h2>
                        <p
                            class="mt-2 max-w-2xl text-sm text-on-surface-variant"
                        >
                            {{
                                testedVersion
                                    ? t('builder.test_preview_help')
                                    : t('builder.help')
                            }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="`/collectors/${recipe.id}/setup`"
                            class="button button-secondary"
                            >{{ t('builder.setup') }}</Link
                        ><Button
                            v-if="testedVersion"
                            :disabled="processing"
                            @click="approveVersion"
                            >{{ t('builder.activate_ready') }}</Button
                        ><Button
                            v-else-if="recipe.active_version_id !== null"
                            :disabled="processing"
                            @click="post(`/collectors/${recipe.id}/runs/start`)"
                            >{{ t('recipes.run') }}</Button
                        ><Link
                            v-else-if="latest?.sample_run_id"
                            :href="`/runs/${latest.sample_run_id}`"
                            class="button button-primary"
                            >{{ t('flow.view_sample') }}</Link
                        >
                    </div>
                </div>
                <div
                    v-if="setupDraft"
                    class="mt-5 rounded-lg bg-surface-container-low p-4"
                >
                    <p class="text-sm font-medium">{{ t('builder.source') }}</p>
                    <p class="mt-1 break-all text-sm">
                        {{ String(setupDraft.source_type) }} ·
                        {{ String(setupDraft.url) }}
                    </p>
                    <p class="mt-3 text-sm font-medium">
                        {{ t('builder.fields') }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-for="field in setupDraft.fields as Array<{
                                name: string;
                            }>"
                            :key="field.name"
                            class="rounded-full border border-outline-glass bg-white px-3 py-1 text-sm"
                            >{{ field.name }}</span
                        >
                    </div>
                </div>
            </section>

            <section class="panel mb-6 space-y-4 p-5 sm:p-7">
                <div>
                    <h2 class="text-xl font-semibold">
                        {{ t('builder.schedule') }}
                    </h2>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('builder.schedule_help') }}
                    </p>
                </div>
                <div
                    v-if="!recipe.active_version_id"
                    class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm"
                >
                    {{ t('builder.test_preview_help') }}
                </div>
                <fieldset
                    :disabled="!recipe.active_version_id"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div>
                        <Label for="cadence">{{
                            t('builder.schedule_cadence')
                        }}</Label
                        ><select
                            id="cadence"
                            v-model="form.cadence"
                            class="h-12 w-full rounded-lg border border-outline-glass bg-white px-3"
                        >
                            <option
                                v-for="value in [
                                    'every_15_minutes',
                                    'hourly',
                                    'daily',
                                    'weekly',
                                    'cron',
                                ]"
                                :key="value"
                                :value="value"
                            >
                                {{ t(`builder.cadence_${value}`) }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label for="timezone">{{
                            t('builder.schedule_timezone')
                        }}</Label
                        ><Input
                            id="timezone"
                            v-model="form.timezone"
                            placeholder="Europe/Prague"
                        />
                    </div>
                    <div
                        v-if="
                            ['daily', 'weekly', 'cron'].includes(form.cadence)
                        "
                    >
                        <Label for="local_time">{{
                            t('builder.schedule_time')
                        }}</Label
                        ><Input
                            id="local_time"
                            v-model="form.local_time"
                            type="time"
                        />
                    </div>
                    <div v-if="form.cadence === 'weekly'">
                        <Label for="weekday">{{
                            t('builder.schedule_weekday')
                        }}</Label
                        ><select
                            id="weekday"
                            v-model.number="form.weekday"
                            class="h-12 w-full rounded-lg border border-outline-glass bg-white px-3"
                        >
                            <option
                                v-for="(day, index) in weekdays"
                                :key="index"
                                :value="index + 1"
                            >
                                {{ day }}
                            </option>
                        </select>
                    </div>
                    <div v-if="form.cadence === 'cron'" class="sm:col-span-2">
                        <Label for="cron_expression">{{
                            t('builder.schedule_cron')
                        }}</Label
                        ><Input
                            id="cron_expression"
                            v-model="form.cron_expression"
                            placeholder="0 9 * * 1-5"
                        />
                    </div>
                </fieldset>
                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        :disabled="processing || !recipe.active_version_id"
                        @click="saveSchedule"
                        >{{ t('builder.save_schedule') }}</Button
                    ><Button
                        v-if="schedule?.status === 'active'"
                        variant="secondary"
                        :disabled="processing"
                        @click="post(`/collectors/${recipe.id}/schedule/pause`)"
                        >{{ t('builder.pause_schedule') }}</Button
                    ><Button
                        v-else-if="schedule?.status === 'paused'"
                        variant="secondary"
                        :disabled="processing"
                        @click="
                            post(`/collectors/${recipe.id}/schedule/resume`)
                        "
                        >{{ t('builder.resume_schedule') }}</Button
                    ><span
                        v-if="schedule"
                        class="text-sm text-on-surface-variant"
                        >{{ t(`builder.schedule_${schedule.status}`) }} ·
                        {{ t('builder.schedule_next') }}:
                        {{ formatDate(schedule.next_run_at) }}</span
                    >
                </div>
            </section>

            <section class="panel mb-6 p-5 sm:p-7">
                <h2 class="text-lg font-semibold">
                    {{ t('builder.notifications') }}
                </h2>
                <label class="mt-4 flex min-h-11 items-center gap-3 text-sm"
                    ><input
                        type="checkbox"
                        :checked="emailNotifications"
                        :disabled="processing"
                        class="h-4 w-4 accent-primary"
                        @change="setNotifications"
                    />{{ t('builder.notifications') }}</label
                >
            </section>

            <section class="mb-8">
                <h2 class="mb-4 text-lg font-semibold">
                    {{ t('recipes.recent_runs') }}
                </h2>
                <div class="panel divide-y divide-outline-glass">
                    <div
                        v-for="run in runs"
                        :key="run.id"
                        class="flex flex-wrap items-center justify-between gap-3 p-4"
                    >
                        <Link :href="`/runs/${run.id}`" class="text-link">{{
                            t(`runs.${run.kind}`)
                        }}</Link>
                        <div class="flex items-center gap-4 text-sm">
                            <span class="text-on-surface-variant">{{
                                t('runs.row_count', { count: run.rows })
                            }}</span
                            ><StatusBadge :status="run.status" />
                        </div>
                    </div>
                    <p v-if="!runs.length" class="p-6 text-on-surface-variant">
                        {{ t('runs.empty') }}
                    </p>
                </div>
            </section>
            <section class="panel p-5 sm:p-7">
                <h2 class="text-lg font-semibold">{{ t('builder.events') }}</h2>
                <div
                    v-if="events.length"
                    class="mt-4 divide-y divide-outline-glass"
                >
                    <div
                        v-for="(event, index) in events"
                        :key="`${event.created_at}-${index}`"
                        class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm"
                    >
                        <Link
                            v-if="event.run_id"
                            :href="`/runs/${event.run_id}`"
                            class="text-link"
                            >{{ event.kind }}</Link
                        ><span v-else>{{ event.kind }}</span
                        ><time class="text-on-surface-variant">{{
                            formatDate(event.created_at)
                        }}</time>
                    </div>
                </div>
                <p v-else class="mt-3 text-sm text-on-surface-variant">
                    {{ t('builder.no_events') }}
                </p>
            </section>

            <details
                v-if="
                    assistanceAvailable &&
                    versions.some(
                        (version) => version.definition_format === 'legacy_js',
                    )
                "
                class="panel mt-6 p-5"
            >
                <summary class="font-semibold">
                    {{ t('flow.advanced') }}
                </summary>
                <pre
                    v-for="version in versions.filter(
                        (item) => item.definition_format === 'legacy_js',
                    )"
                    :key="version.id"
                    class="mt-4 max-h-80 overflow-auto rounded-lg bg-slate-950 p-4 text-sm text-slate-100"
                    >{{ version.source }}</pre>
            </details>
        </div>
    </AppLayout>
</template>
