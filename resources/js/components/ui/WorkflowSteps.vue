<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
withDefaults(
    defineProps<{
        steps?: string[];
        current: number;
        available?: number[];
        links?: Record<number, string>;
    }>(),
    {
        steps: () => [
            'redesign.source',
            'redesign.choose',
            'redesign.preview',
            'redesign.ready',
        ],
        available: () => [],
        links: () => ({}),
    },
);
defineEmits<{ select: [step: number] }>();
const { t } = useI18n();
</script>
<template>
    <ol
        :aria-label="t('flow.progress')"
        class="workflow-steps mb-6 grid grid-cols-4 gap-1 rounded-2xl border border-outline-glass bg-white p-1.5"
    >
        <li
            v-for="(step, index) in steps"
            :key="step"
            :aria-current="index === current ? 'step' : undefined"
            class="min-w-0"
        >
            <component
                :is="
                    links[index]
                        ? Link
                        : available.includes(index)
                          ? 'button'
                          : 'span'
                "
                :href="links[index]"
                :type="available.includes(index) ? 'button' : undefined"
                :aria-label="t(step)"
                :class="[
                    'flex min-h-11 w-full flex-col items-center justify-center gap-1.5 rounded-xl px-1 py-2 text-center text-xs sm:flex-row sm:gap-3 sm:text-sm',
                    index === current
                        ? 'bg-lavender font-semibold text-primary-container'
                        : 'text-on-surface-variant',
                    (links[index] || available.includes(index)) &&
                    index !== current
                        ? 'hover:bg-surface-container-low'
                        : '',
                ]"
                @click="available.includes(index) && $emit('select', index)"
            >
                <span
                    aria-hidden="true"
                    :class="[
                        'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                        index < current
                            ? 'bg-mint text-emerald-800'
                            : index === current
                              ? 'bg-primary text-white'
                              : 'bg-surface-container-low',
                    ]"
                    ><Check v-if="index < current" :size="14" /><template
                        v-else
                        >{{ index + 1 }}</template
                    ></span
                >
                {{ t(step) }}
            </component>
        </li>
    </ol>
</template>
