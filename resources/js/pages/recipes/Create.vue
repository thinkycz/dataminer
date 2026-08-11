<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import FieldError from '@/components/ui/FieldError.vue';
import type { SharedProps } from '@/types';

const { t } = useI18n();
const page = usePage<SharedProps>();
const form = reactive({ name: '', start_url: '', instructions: '' });

function submit(): void {
    router.post('/recipes', form);
}
</script>

<template>
    <AppLayout :title="t('recipes.create_title')">
        <div class="mx-auto max-w-2xl">
            <Link href="/recipes" class="text-xs font-semibold text-primary"
                >← {{ t('recipes.back') }}</Link
            >
            <div
                class="mt-4 rounded-2xl border border-outline-glass bg-surface-container p-6 shadow-sm"
            >
                <h1 class="text-2xl font-bold">
                    {{ t('recipes.create_title') }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('recipes.create_help') }}
                </p>
                <p
                    class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs leading-relaxed text-amber-900"
                >
                    {{ t('recipes.security_notice') }}
                </p>
                <form class="mt-6 space-y-5" @submit.prevent="submit">
                    <div>
                        <Label for="name">{{ t('recipes.name') }}</Label
                        ><Input
                            id="name"
                            v-model="form.name"
                            required
                        /><FieldError :message="page.props.errors.name" />
                    </div>
                    <div>
                        <Label for="start_url">{{
                            t('recipes.start_url')
                        }}</Label
                        ><Input
                            id="start_url"
                            v-model="form.start_url"
                            type="url"
                            required
                        /><FieldError :message="page.props.errors.start_url" />
                    </div>
                    <div>
                        <Label for="instructions">{{
                            t('recipes.instructions')
                        }}</Label
                        ><textarea
                            id="instructions"
                            v-model="form.instructions"
                            required
                            rows="9"
                            class="mt-1 w-full rounded-xl border border-outline-glass bg-white p-3 text-xs outline-none focus:border-primary"
                        ></textarea
                        ><FieldError
                            :message="page.props.errors.instructions"
                        />
                    </div>
                    <div class="flex justify-end">
                        <Button type="submit">{{ t('recipes.create') }}</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
