<script setup lang="ts">
import { useI18n } from 'vue-i18n';
defineProps<{ status: string }>();
const { t, te } = useI18n();
</script>
<template>
    <span
        :class="[
            'inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-medium',
            ['ready', 'approved', 'completed'].includes(status)
                ? 'bg-emerald-50 text-emerald-800'
                : ['failed', 'rejected'].includes(status)
                  ? 'bg-red-50 text-red-800'
                  : ['pending_approval', 'tested'].includes(status)
                    ? 'bg-amber-50 text-amber-900'
                    : 'bg-blue-50 text-blue-800',
        ]"
        ><span
            class="h-1.5 w-1.5 rounded-full bg-current"
            aria-hidden="true"
        />{{
            te(`status.${status}`) ? t(`status.${status}`) : t('status.unknown')
        }}</span
    >
</template>
