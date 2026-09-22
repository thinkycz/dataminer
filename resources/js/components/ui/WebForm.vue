<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
const props = withDefaults(
    defineProps<{
        endpoint: string;
        resetOnSuccess?: string[];
        resetOnError?: string[];
    }>(),
    { resetOnSuccess: () => [], resetOnError: () => [] },
);
const element = ref<HTMLFormElement | null>(null);
const errors = ref<Record<string, string>>({});
const processing = ref(false);
function resetFields(names: string[]): void {
    for (const name of names) {
        const input = element.value?.elements.namedItem(name);
        if (input instanceof HTMLInputElement) {
            input.value = '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
}
function submit(): void {
    if (!element.value || processing.value) return;
    processing.value = true;
    errors.value = {};
    router.post(props.endpoint, new FormData(element.value), {
        preserveScroll: true,
        onSuccess: () => resetFields(props.resetOnSuccess),
        onError: async (value) => {
            errors.value = value;
            resetFields(props.resetOnError);
            await nextTick();
            element.value
                ?.querySelector<HTMLElement>('[aria-invalid="true"]')
                ?.focus();
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>
<template>
    <form ref="element" @submit.prevent="submit">
        <slot :errors="errors" :processing="processing" />
    </form>
</template>
