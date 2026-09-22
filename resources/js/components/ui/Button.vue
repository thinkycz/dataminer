<script setup lang="ts">
import { cn } from '@/lib/utils';
withDefaults(
    defineProps<{
        type?: 'button' | 'submit';
        class?: string;
        disabled?: boolean;
        variant?: 'primary' | 'secondary' | 'danger';
    }>(),
    { type: 'button', class: '', disabled: false, variant: 'primary' },
);
</script>
<template>
    <button
        :type="type"
        :disabled="disabled"
        :class="
            cn(
                'button',
                variant === 'primary'
                    ? 'button-primary'
                    : variant === 'danger'
                      ? 'button-danger'
                      : 'button-secondary',
                $props.class,
            )
        "
    >
        <slot />
    </button>
</template>
