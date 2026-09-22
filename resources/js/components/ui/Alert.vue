<script setup lang="ts">
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        variant?: 'success' | 'error' | 'info';
    }>(),
    {
        variant: 'info',
    },
);

const variants = {
    success: 'border-success/30 bg-success/10 text-success',
    error: 'border-error-red/30 bg-error-red/10 text-error-red',
    info: 'border-outline-glass bg-surface-container-low text-on-surface-variant',
};
</script>

<template>
    <div
        role="alert"
        :class="
            cn(
                'rounded-xl border px-4 py-3 text-sm font-medium',
                variants[props.variant],
            )
        "
    >
        <slot />
    </div>
</template>
