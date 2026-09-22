<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Layers } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
withDefaults(defineProps<{ href?: string }>(), { href: '/' });
const { t } = useI18n();
</script>
<template>
    <Link :href="href" class="inline-flex items-center gap-3"
        ><span
            class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-white"
            ><Layers :size="22" aria-hidden="true" /></span
        ><span class="text-lg font-semibold tracking-tight">{{
            t('app.name')
        }}</span></Link
    >
</template>
