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
    activeUrl.value.startsWith('/runs')
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
            class="border-b border-[#21423b] bg-[#19352f] p-4 text-white lg:sticky lg:top-0 lg:flex lg:h-svh lg:w-64 lg:shrink-0 lg:flex-col lg:border-r-0 lg:border-b-0 lg:px-5 lg:py-7"
        >
            <div class="flex items-center justify-between">
                <div
                    class="[&_a]:text-white [&_a_span:first-child]:bg-[#d2e9bd] [&_a_span:first-child]:text-[#19352f]"
                >
                    <Brand href="/collectors" />
                </div>
                <button
                    type="button"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-white/20 text-white lg:hidden"
                    :disabled="signingOut"
                    :aria-label="t('nav.logout')"
                    @click="logout"
                >
                    <LogOut :size="18" />
                </button>
            </div>
            <p
                class="mt-10 hidden px-3 text-[11px] font-semibold tracking-[0.16em] text-[#a8c2b5] uppercase lg:block"
            >
                {{ t('nav.workspace') }}
            </p>
            <nav
                :aria-label="t('nav.main')"
                class="mt-5 flex gap-1 lg:mt-3 lg:flex-col lg:gap-1"
            >
                <Link
                    v-for="item in [
                        { key: 'recipes', href: '/collectors', icon: Database },
                        { key: 'runs', href: '/runs', icon: Table2 },
                        { key: 'settings', href: '/settings', icon: Settings },
                    ]"
                    :key="item.key"
                    :href="item.href"
                    :aria-current="current === item.key ? 'page' : undefined"
                    :class="[
                        'flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-medium lg:justify-start',
                        current === item.key
                            ? 'bg-[#d2e9bd] text-[#19352f]'
                            : 'text-[#c7d9ce] hover:bg-white/10 hover:text-white',
                    ]"
                    ><component
                        :is="item.icon"
                        :size="18"
                        class="hidden shrink-0 sm:block"
                        aria-hidden="true"
                    />{{ t(`nav.${item.key}`) }}</Link
                >
            </nav>
            <div class="mt-auto hidden border-t border-white/15 pt-5 lg:block">
                <p class="truncate text-sm text-[#c7d9ce]">
                    {{ auth.user?.email }}
                </p>
                <button
                    type="button"
                    class="mt-3 flex min-h-11 items-center gap-2 text-sm text-white/85 hover:text-white"
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
            class="min-w-0 flex-1 px-4 py-7 sm:px-8 lg:px-12 lg:py-10"
        >
            <div class="mx-auto max-w-7xl">
                <FlashAlerts /><ConfirmDialog /><slot />
            </div>
        </main>
    </div>
</template>
