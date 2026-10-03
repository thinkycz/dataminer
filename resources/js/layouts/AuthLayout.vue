<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ArrowDown, Check, Globe2, Table2 } from '@lucide/vue';
import Brand from '@/components/ui/Brand.vue';
import FlashAlerts from '@/components/ui/FlashAlerts.vue';
defineProps<{ title: string; subtitle: string }>();
const { t } = useI18n();
</script>
<template>
    <main
        class="flex min-h-svh flex-col items-center justify-center bg-surface-bg px-4 py-10 relative overflow-hidden"
    >
        <Head :title="title" />
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -top-24 -left-28 h-80 w-80 rounded-full bg-lavender/70"
        />
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -right-24 bottom-0 h-64 w-64 rounded-full bg-peach/70"
        />
        <div
            class="relative z-10 flex w-full max-w-5xl flex-col items-center gap-10 lg:flex-row lg:gap-20"
        >
            <section class="hidden min-w-0 flex-1 lg:block">
                <p class="mb-4 text-sm font-semibold text-primary-container">
                    {{ t('redesign.auth_eyebrow') }}
                </p>
                <h2
                    class="max-w-md text-5xl font-bold leading-[1.08] tracking-tight"
                >
                    {{ t('redesign.auth_title') }}
                </h2>
                <p class="mt-5 max-w-sm text-on-surface-variant">
                    {{ t('redesign.auth_help') }}
                </p>
                <div class="mt-9" aria-hidden="true">
                    <div
                        class="flex items-center gap-3 rounded-2xl border border-outline-glass bg-white p-4 shadow-sm"
                    >
                        <span class="pastel-icon bg-peach"
                            ><Globe2 :size="22"
                        /></span>
                        <div>
                            <p class="text-sm font-semibold">
                                {{ t('redesign.example_source') }}
                            </p>
                            <p class="text-xs text-on-surface-variant">
                                example.com/products
                            </p>
                        </div>
                    </div>
                    <div
                        class="flex items-center justify-center gap-3 py-3 text-primary-container"
                    >
                        <ArrowDown :size="20" /><span
                            class="text-xs font-semibold"
                            >{{ t('redesign.example_selection') }}</span
                        >
                    </div>
                    <div
                        class="overflow-hidden rounded-2xl border border-outline-glass bg-white shadow-[0_12px_30px_rgba(48,43,66,0.06)]"
                    >
                        <div
                            class="flex items-center justify-between bg-lavender/70 px-4 py-3"
                        >
                            <span
                                class="flex items-center gap-2 text-sm font-semibold"
                                ><Table2 :size="17" />{{
                                    t('redesign.example_table')
                                }}</span
                            ><span
                                class="rounded-full bg-white px-2 py-1 text-xs"
                                >{{ t('redesign.example') }}</span
                            >
                        </div>
                        <div
                            v-for="(item, index) in [
                                'notebook',
                                'pencil',
                                'planner',
                            ]"
                            :key="item"
                            class="flex items-center gap-3 border-t border-outline-glass px-4 py-3 text-sm"
                        >
                            <span class="w-5 text-xs text-on-surface-variant">{{
                                index + 1
                            }}</span
                            ><span class="flex-1">{{
                                t(`redesign.${item}`)
                            }}</span
                            ><span
                                class="h-2 w-12 rounded-full"
                                :class="
                                    ['bg-peach', 'bg-lavender', 'bg-sky'][index]
                                "
                            ></span
                            ><Check :size="16" class="ml-3 text-success" />
                        </div>
                    </div>
                </div>
            </section>
            <div class="w-full max-w-md">
                <Brand href="/login" />
                <p class="mt-3 text-sm text-on-surface-variant">
                    {{ t('brand.subtitle') }}
                </p>
                <div class="panel mt-8 w-full max-w-md p-6 sm:p-8">
                    <header class="mb-7">
                        <h1 class="text-2xl font-semibold tracking-tight">
                            {{ title }}
                        </h1>
                        <p class="mt-2 text-on-surface-variant">
                            {{ subtitle }}
                        </p>
                    </header>
                    <FlashAlerts /><slot />
                </div>
            </div>
        </div>
    </main>
</template>
