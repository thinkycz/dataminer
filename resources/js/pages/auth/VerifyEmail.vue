<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';

const processing = ref(false);
const { t } = useI18n();

useBoundLocale();

function submit(): void {
    if (processing.value) return;
    processing.value = true;
    router.post(
        '/verify-email',
        {},
        {
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>
<template>
    <AppLayout :title="t('auth.verify.title')">
        <section
            class="max-w-xl rounded-2xl border border-outline-glass bg-surface-container-lowest p-6"
        >
            <h1 class="text-2xl font-bold">{{ t('auth.verify.title') }}</h1>
            <p
                class="mt-2 max-w-xl text-sm font-medium leading-relaxed text-on-surface-variant"
            >
                {{ t('auth.verify.description') }}
            </p>

            <Button class="mt-5" :disabled="processing" @click="submit">{{
                t('auth.verify.submit')
            }}</Button>
        </section>
    </AppLayout>
</template>
