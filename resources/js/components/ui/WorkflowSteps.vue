<script setup lang="ts">
import { useI18n } from 'vue-i18n';
defineProps<{ steps: string[]; current: number }>();
const { t } = useI18n();
</script>
<template>
    <ol
        :aria-label="t('flow.progress')"
        class="mb-8 flex flex-wrap gap-x-6 gap-y-3"
    >
        <li
            v-for="(step, index) in steps"
            :key="step"
            :aria-current="index === current ? 'step' : undefined"
            :class="[
                'flex items-center gap-2 text-sm',
                index === current
                    ? 'font-semibold text-primary'
                    : 'text-on-surface-variant',
            ]"
        >
            <span
                :class="[
                    'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-sm',
                    index <= current
                        ? 'bg-primary text-white'
                        : 'bg-surface-container-low',
                ]"
                >{{ index + 1 }}</span
            >{{ step }}
        </li>
    </ol>
</template>
