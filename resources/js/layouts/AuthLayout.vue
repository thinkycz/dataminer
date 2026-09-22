<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Brand from '@/components/ui/Brand.vue';
import FlashAlerts from '@/components/ui/FlashAlerts.vue';
defineProps<{ title: string; subtitle: string }>();
const { t } = useI18n();
</script>
<template>
    <main
        class="flex min-h-svh flex-col items-center justify-center bg-surface-bg px-4 py-10"
    >
        <Head :title="title" />
        <Brand href="/login" />
        <p class="mt-3 text-center text-sm text-on-surface-variant">
            {{ t('brand.subtitle') }}
        </p>
        <div class="panel mt-8 w-full max-w-md p-6 sm:p-8">
            <header class="mb-7">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ title }}
                </h1>
                <p class="mt-2 text-on-surface-variant">{{ subtitle }}</p>
            </header>
            <FlashAlerts /><slot />
        </div>
    </main>
</template>
