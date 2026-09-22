<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';

const processing = ref(false);
const { t } = useI18n();
const { user } = useSharedProps();

useBoundLocale();

function submit(): void {
    if (processing.value) return;
    processing.value = true;
    router.post(
        '/verify-email',
        {},
        {
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>
<template>
    <AppLayout :title="t('auth.verify.title')">
        <section
            class="max-w-xl rounded-2xl border border-outline-glass bg-surface-container-lowest p-6"
        >
            <h1 class="text-2xl font-bold">
                {{
                    t(
                        user?.email_verified_at
                            ? 'auth.verify.complete_title'
                            : 'auth.verify.title',
                    )
                }}
            </h1>
            <p
                class="mt-2 max-w-xl text-sm font-medium leading-relaxed text-on-surface-variant"
            >
                {{
                    t(
                        user?.email_verified_at
                            ? 'auth.verify.complete_description'
                            : 'auth.verify.description',
                    )
                }}
            </p>

            <Button
                v-if="!user?.email_verified_at"
                class="mt-5"
                :disabled="processing"
                @click="submit"
                >{{ t('auth.verify.submit') }}</Button
            >
        </section>
    </AppLayout>
</template>
