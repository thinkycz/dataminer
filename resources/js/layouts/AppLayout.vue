<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Database, LogOut, Settings, PlaySquare } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Brand from '@/components/ui/Brand.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import FlashAlerts from '@/components/ui/FlashAlerts.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';

defineProps<{ title: string }>();

const { activeUrl, auth } = useSharedProps();
const { t } = useI18n();
useBoundLocale();

const current = computed(() => {
    if (activeUrl.value.startsWith('/scrape-runs')) return 'runs';
    if (activeUrl.value.startsWith('/settings')) return 'settings';
    return 'recipes';
});

function logout(): void {
    router.post('/logout');
}
</script>

<template>
    <Head :title="title" />
    <a href="#main-content" class="sr-only focus:not-sr-only">{{
        t('nav.skip_to_main')
    }}</a>
    <div class="min-h-screen bg-surface-bg text-on-surface md:flex">
        <aside
            class="border-b border-outline-glass bg-surface-container p-4 md:sticky md:top-0 md:h-screen md:w-64 md:border-r md:border-b-0 md:p-6"
        >
            <Brand href="/recipes" />
            <nav class="mt-8 grid grid-cols-3 gap-2 md:grid-cols-1">
                <Link
                    href="/recipes"
                    :class="[
                        'flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-semibold',
                        current === 'recipes'
                            ? 'bg-primary/10 text-primary'
                            : 'text-on-surface-variant hover:bg-surface-container-low',
                    ]"
                >
                    <Database :size="17" /> {{ t('nav.recipes') }}
                </Link>
                <Link
                    href="/scrape-runs"
                    :class="[
                        'flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-semibold',
                        current === 'runs'
                            ? 'bg-primary/10 text-primary'
                            : 'text-on-surface-variant hover:bg-surface-container-low',
                    ]"
                >
                    <PlaySquare :size="17" /> {{ t('nav.runs') }}
                </Link>
                <Link
                    href="/settings"
                    :class="[
                        'flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-semibold',
                        current === 'settings'
                            ? 'bg-primary/10 text-primary'
                            : 'text-on-surface-variant hover:bg-surface-container-low',
                    ]"
                >
                    <Settings :size="17" /> {{ t('nav.settings') }}
                </Link>
            </nav>
            <div
                class="mt-8 border-t border-outline-glass pt-4 md:absolute md:bottom-6 md:left-6 md:right-6"
            >
                <p class="truncate text-xs text-on-surface-variant">
                    {{ auth.user?.email }}
                </p>
                <button
                    type="button"
                    class="mt-3 flex items-center gap-2 text-xs font-semibold text-on-surface-variant hover:text-error-red"
                    @click="logout"
                >
                    <LogOut :size="15" /> {{ t('nav.logout') }}
                </button>
            </div>
        </aside>
        <main id="main-content" class="min-w-0 flex-1 p-4 md:p-8">
            <div class="mx-auto max-w-7xl">
                <FlashAlerts />
                <ConfirmDialog />
                <slot />
            </div>
        </main>
    </div>
</template>
