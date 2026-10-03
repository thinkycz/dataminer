<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
const panel = ref<HTMLElement | null>(null);
let previousFocus: HTMLElement | null = null;
let previousOverflow = '';
const props = withDefaults(
    defineProps<{
        open: boolean;
        variant?: 'dialog' | 'drawer';
        labelledBy?: string;
        panelClass?: string;
    }>(),
    {
        variant: 'dialog',
        labelledBy: undefined,
        panelClass: '',
    },
);

const emit = defineEmits<{
    close: [];
}>();
function keydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        emit('close');
    }
    if (event.key !== 'Tab' || !panel.value) return;
    const targets = Array.from(
        panel.value.querySelectorAll<HTMLElement>(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex="0"]',
        ),
    ).filter((element) => element.getClientRects().length);
    const first = targets[0];
    const last = targets.at(-1);
    if (!first || !last) {
        event.preventDefault();
        panel.value.focus();
        return;
    }
    if (
        event.shiftKey &&
        (document.activeElement === first ||
            document.activeElement === panel.value)
    ) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}
function restore(): void {
    document.removeEventListener('keydown', keydown);
    document.body.style.overflow = previousOverflow;
    previousFocus?.focus();
}
watch(
    () => props.open,
    async (open) => {
        if (!open) {
            restore();
            return;
        }
        previousFocus =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', keydown);
        await nextTick();
        panel.value?.focus();
    },
);
onBeforeUnmount(() => {
    if (props.open) restore();
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex bg-on-surface/25 backdrop-blur-sm"
            :class="
                variant === 'drawer'
                    ? 'justify-end'
                    : 'items-center justify-center p-4'
            "
            @click.self="emit('close')"
        >
            <div
                ref="panel"
                tabindex="-1"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="labelledBy"
                :class="[
                    variant === 'drawer'
                        ? 'flex h-screen max-h-screen w-full max-w-md flex-col overflow-y-auto bg-white p-6 shadow-xl'
                        : 'max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto rounded-3xl border border-outline-glass bg-white p-5 shadow-xl',
                    panelClass,
                ]"
            >
                <slot />
            </div>
        </div>
    </Teleport>
</template>
