<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, LogOut, Settings } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{ email?: string; signingOut: boolean; active: boolean }>();
defineEmits<{ logout: [] }>();
const { t } = useI18n();
const open = ref(false);
const container = ref<HTMLElement | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);
function closeOutside(event: PointerEvent): void {
    if (
        event.target instanceof Node &&
        !container.value?.contains(event.target)
    )
        open.value = false;
}
function closeOnFocusOut(event: FocusEvent): void {
    if (
        !(event.relatedTarget instanceof Node) ||
        !container.value?.contains(event.relatedTarget)
    )
        open.value = false;
}
function closeWithKeyboard(): void {
    if (!open.value) return;
    open.value = false;
    trigger.value?.focus();
}
onMounted(() => document.addEventListener('pointerdown', closeOutside));
onBeforeUnmount(() =>
    document.removeEventListener('pointerdown', closeOutside),
);
</script>
<template>
    <div
        ref="container"
        class="relative"
        @keydown.esc.stop.prevent="closeWithKeyboard"
        @focusout="closeOnFocusOut"
    >
        <button
            ref="trigger"
            type="button"
            class="flex min-h-11 items-center gap-2 rounded-full p-1.5 pr-3 text-sm font-semibold hover:bg-lavender"
            :class="active ? 'bg-lavender text-primary-container' : ''"
            :aria-expanded="open"
            aria-controls="account-panel"
            @click="open = !open"
        >
            <span
                aria-hidden="true"
                class="flex h-8 w-8 items-center justify-center rounded-full bg-peach text-sm font-bold uppercase"
                >{{ email?.charAt(0) }}</span
            >
            {{ t('nav.account')
            }}<ChevronDown
                :size="15"
                aria-hidden="true"
                :class="open ? 'rotate-180' : ''"
            />
        </button>
        <div
            v-if="open"
            id="account-panel"
            class="absolute right-0 z-40 mt-3 w-64 max-w-[calc(100vw-2rem)] rounded-2xl border border-outline-glass bg-white p-2 shadow-xl shadow-on-surface/10"
        >
            <p
                class="truncate border-b border-outline-glass px-3 py-3 text-sm text-on-surface-variant"
                :title="email"
            >
                {{ email }}
            </p>
            <Link
                href="/settings"
                :aria-current="active ? 'page' : undefined"
                class="mt-2 flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium hover:bg-lavender"
                @click="open = false"
                ><Settings :size="18" aria-hidden="true" />{{
                    t('nav.settings')
                }}</Link
            >
            <button
                type="button"
                class="flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-sm text-on-surface-variant hover:bg-surface-container-low"
                :disabled="signingOut"
                @click="$emit('logout')"
            >
                <LogOut :size="18" aria-hidden="true" />{{ t('nav.logout') }}
            </button>
        </div>
    </div>
</template>
