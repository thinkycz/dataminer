<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
defineProps<{
    links: Array<{ url: string | null; label: string; active: boolean }>;
}>();
const { t } = useI18n();
</script>
<template>
    <nav
        v-if="links.length > 3"
        :aria-label="t('common.pagination')"
        class="mt-6 flex flex-wrap gap-2"
    >
        <template v-for="(link, index) in links" :key="index">
            <Link
                v-if="link.url"
                :href="link.url"
                :aria-current="link.active ? 'page' : undefined"
                :class="[
                    'button',
                    link.active ? 'button-primary' : 'button-secondary',
                ]"
                preserve-scroll
                >{{
                    index === 0
                        ? t('common.previous')
                        : index === links.length - 1
                          ? t('common.next')
                          : link.label
                }}</Link
            >
            <span
                v-else
                class="button button-secondary opacity-50"
                aria-disabled="true"
                >{{
                    index === 0
                        ? t('common.previous')
                        : index === links.length - 1
                          ? t('common.next')
                          : link.label
                }}</span
            >
        </template>
    </nav>
</template>
