<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import CollectorSetup from '@/components/ui/CollectorSetup.vue';
import type { SharedProps } from '@/types';
const { t } = useI18n();
const page = usePage<SharedProps>();
const processing = ref(false);
function submit(form: {
    name: string;
    start_url: string;
    instructions: string;
}): void {
    if (processing.value) return;
    processing.value = true;
    router.post('/recipes', form, {
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>
<template>
    <AppLayout :title="t('recipes.create_title')"
        ><div class="mx-auto max-w-3xl">
            <Link href="/recipes" class="text-link mb-6 inline-block"
                >← {{ t('recipes.back') }}</Link
            ><PageHeader
                :title="t('recipes.create_title')"
                :description="t('recipes.create_help')"
            /><CollectorSetup
                :errors="page.props.errors"
                :processing="processing"
                @submit="submit"
            /></div
    ></AppLayout>
</template>
