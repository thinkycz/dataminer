<script setup lang="ts">
import CollectorIllustration from '@/components/ui/CollectorIllustration.vue';
defineProps<{ title: string; description: string }>();
</script>
<template>
    <div class="panel px-6 py-14 text-center">
        <CollectorIllustration class="mx-auto mb-6" />
        <h2 class="text-xl font-semibold">{{ title }}</h2>
        <p class="mx-auto mt-3 max-w-lg text-on-surface-variant">
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-6 flex justify-center gap-3">
            <slot />
        </div>
    </div>
</template>
