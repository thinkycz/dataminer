<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>
<template>
    <header
        class="mb-8 flex flex-col justify-between gap-5 sm:flex-row sm:items-start"
    >
        <div class="min-w-0">
            <h1 class="text-3xl font-semibold tracking-tight break-words">
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="mt-2 max-w-2xl text-on-surface-variant"
            >
                {{ description }}
            </p>
            <slot name="context" />
        </div>
        <div v-if="$slots.default" class="flex shrink-0 flex-wrap gap-3">
            <slot />
        </div>
    </header>
</template>
