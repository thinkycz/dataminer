import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

let activeHistoryGuard: ((event: PopStateEvent) => void) | undefined;

/** Install before Inertia so a cancelled history visit never swaps the page. */
export function installDraftNavigationGuard(): void {
    window.addEventListener('popstate', (event) => activeHistoryGuard?.(event));
}

/** Track only collector configuration, never credentials or browser sessions. */
export function useDraftGuard(snapshot: () => string) {
    const { t } = useI18n();
    const saved = ref(snapshot());
    const saveFailed = ref(false);
    const dirty = computed(() => snapshot() !== saved.value);
    let removeBefore: (() => void) | undefined;
    let removeNavigate: (() => void) | undefined;
    let currentUrl = '';
    let currentHistoryState: unknown;
    function rememberLocation(): void {
        currentUrl = window.location.href;
        currentHistoryState = window.history.state;
    }
    function beforeHistoryNavigation(event: PopStateEvent): void {
        if (!dirty.value || window.confirm(t('redesign.leave'))) return;
        // Inertia's history navigation cannot be cancelled through its before event.
        // Keep the current component and restore its history entry before Inertia handles the pop.
        event.stopImmediatePropagation();
        window.history.pushState(currentHistoryState, '', currentUrl);
    }
    function markSaved(value: string = snapshot()): void {
        saved.value = value;
        saveFailed.value = false;
    }
    function beforeUnload(event: BeforeUnloadEvent): void {
        if (!dirty.value) return;
        event.preventDefault();
        event.returnValue = '';
    }
    onMounted(() => {
        rememberLocation();
        window.addEventListener('beforeunload', beforeUnload);
        activeHistoryGuard = beforeHistoryNavigation;
        removeNavigate = router.on('navigate', rememberLocation);
        removeBefore = router.on('before', (event) => {
            if (
                (event.detail.visit.method === 'get' ||
                    event.detail.visit.url.pathname === '/logout') &&
                dirty.value &&
                !window.confirm(t('redesign.leave'))
            )
                event.preventDefault();
        });
    });
    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', beforeUnload);
        if (activeHistoryGuard === beforeHistoryNavigation)
            activeHistoryGuard = undefined;
        removeBefore?.();
        removeNavigate?.();
    });
    return { dirty, saveFailed, markSaved };
}
