<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Database, Activity } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Brand from '@/components/ui/Brand.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import FlashAlerts from '@/components/ui/FlashAlerts.vue';
import AccountMenu from '@/components/ui/AccountMenu.vue';
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
const items = [
    { key: 'recipes', href: '/collectors', icon: Database },
    { key: 'runs', href: '/runs', icon: Activity },
];
const signingOut = ref(false);
function logout(): void {
    if (signingOut.value) return;
    router.post(
        '/logout',
        {},
        {
            onStart: () => {
                signingOut.value = true;
            },
            onFinish: () => {
                signingOut.value = false;
            },
        },
    );
}
</script>
<template>
    <div class="min-h-screen bg-surface-bg text-on-surface">
        <Head :title="title" />
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-xl focus:bg-white focus:p-3"
            >{{ t('nav.skip_to_main') }}</a
        >
        <header class="border-b border-outline-glass bg-white/90">
            <div
                class="mx-auto grid max-w-[1240px] grid-cols-[1fr_auto] items-center gap-x-6 px-4 pt-4 sm:px-8 md:grid-cols-[1fr_auto_1fr] md:py-4"
            >
                <Brand href="/collectors" />
                <nav
                    :aria-label="t('nav.main')"
                    class="col-span-2 col-start-1 row-start-2 mt-3 flex w-full gap-2 pb-3 md:col-span-1 md:col-start-2 md:row-start-1 md:mt-0 md:pb-0"
                >
                    <Link
                        v-for="item in items"
                        :key="item.key"
                        :href="item.href"
                        :aria-current="
                            current === item.key ? 'page' : undefined
                        "
                        :class="[
                            'flex min-h-11 flex-1 items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold whitespace-nowrap md:flex-none',
                            current === item.key
                                ? 'bg-lavender text-primary-container'
                                : 'text-on-surface-variant hover:bg-surface-container-low',
                        ]"
                        ><component
                            :is="item.icon"
                            :size="18"
                            aria-hidden="true"
                        />{{ t(`nav.${item.key}`) }}</Link
                    >
                </nav>
                <div
                    class="col-start-2 row-start-1 justify-self-end md:col-start-3"
                >
                    <AccountMenu
                        :email="auth.user?.email"
                        :signing-out="signingOut"
                        :active="current === 'settings'"
                        @logout="logout"
                    />
                </div>
            </div>
        </header>
        <main
            id="main-content"
            tabindex="-1"
            class="min-w-0 px-4 py-6 sm:px-8 lg:py-10"
        >
            <div class="mx-auto max-w-[1160px]">
                <FlashAlerts /><ConfirmDialog /><slot />
            </div>
        </main>
    </div>
</template>
