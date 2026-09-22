<script setup lang="ts">
withDefaults(
    defineProps<{
        id?: string;
        errorId?: string | null;
        message?: string | null | undefined;
    }>(),
    {
        id: undefined,
        errorId: undefined,
        message: undefined,
    },
);
</script>

<template>
    <p
        v-if="message"
        :id="$props.id ?? $props.errorId ?? undefined"
        class="text-sm font-semibold text-error-red"
        role="alert"
    >
        {{ message }}
    </p>
</template>
