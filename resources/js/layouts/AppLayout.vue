<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Database, LogOut, Settings, Table2 } from '@lucide/vue';
import { computed, ref } from 'vue';
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
const current = computed(() =>
    activeUrl.value.startsWith('/scrape-runs')
        ? 'runs'
        : activeUrl.value.startsWith('/settings')
          ? 'settings'
          : 'recipes',
);
const signingOut = ref(false);
function logout(): void {
    if (signingOut.value) return;
    signingOut.value = true;
    router.post(
        '/logout',
        {},
        {
            onFinish: () => {
                signingOut.value = false;
            },
        },
    );
}
</script>
<template>
    <div class="min-h-screen bg-surface-bg text-on-surface lg:flex">
        <Head :title="title" />
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:bg-white focus:p-3"
            >{{ t('nav.skip_to_main') }}</a
        >
        <aside
            class="border-b border-outline-glass bg-white p-4 lg:sticky lg:top-0 lg:flex lg:h-svh lg:w-60 lg:shrink-0 lg:flex-col lg:border-r lg:border-b-0 lg:p-5"
        >
            <div class="flex items-center justify-between">
                <Brand href="/recipes" /><button
                    type="button"
                    class="button button-secondary lg:hidden"
                    :disabled="signingOut"
                    :aria-label="t('nav.logout')"
                    @click="logout"
                >
                    <LogOut :size="18" />
                </button>
            </div>
            <nav
                :aria-label="t('nav.main')"
                class="mt-5 flex gap-1 lg:mt-10 lg:flex-col lg:gap-2"
            >
                <Link
                    v-for="item in [
                        { key: 'recipes', href: '/recipes', icon: Database },
                        { key: 'runs', href: '/scrape-runs', icon: Table2 },
                        { key: 'settings', href: '/settings', icon: Settings },
                    ]"
                    :key="item.key"
                    :href="item.href"
                    :aria-current="current === item.key ? 'page' : undefined"
                    :class="[
                        'flex min-h-11 flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium lg:justify-start',
                        current === item.key
                            ? 'bg-blue-50 text-primary'
                            : 'text-on-surface-variant hover:bg-surface-container-low',
                    ]"
                    ><component
                        :is="item.icon"
                        :size="18"
                        class="hidden shrink-0 sm:block"
                        aria-hidden="true"
                    />{{ t(`nav.${item.key}`) }}</Link
                >
            </nav>
            <div
                class="mt-auto hidden border-t border-outline-glass pt-5 lg:block"
            >
                <p class="truncate text-sm text-on-surface-variant">
                    {{ auth.user?.email }}
                </p>
                <button
                    type="button"
                    class="mt-3 flex min-h-11 items-center gap-2 text-sm text-on-surface-variant"
                    :disabled="signingOut"
                    @click="logout"
                >
                    <LogOut :size="16" />{{ t('nav.logout') }}
                </button>
            </div>
        </aside>
        <main
            id="main-content"
            tabindex="-1"
            class="min-w-0 flex-1 px-4 py-8 sm:px-8 lg:p-10"
        >
            <div class="mx-auto max-w-6xl">
                <FlashAlerts /><ConfirmDialog /><slot />
            </div>
        </main>
    </div>
</template>
