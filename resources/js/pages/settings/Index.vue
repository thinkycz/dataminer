<script setup lang="ts">
import WebForm from '@/components/ui/WebForm.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Select from '@/components/ui/Select.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';
import { fieldError } from '@/composables/useFieldError';

const { user, app } = useSharedProps();
const { t, te } = useI18n();

useBoundLocale();

const localeOptions = computed(() =>
    app.value.locales.map((value: string) => ({
        value,
        label: te(`locale.${value}`) ? (t(`locale.${value}`) as string) : value,
    })),
);
</script>

<template>
    <AppLayout :title="t('settings.title')">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
            <header>
                <h1
                    class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                >
                    {{ t('settings.title') }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('settings.subtitle') }}
                </p>
            </header>

            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-6"
            >
                <h2
                    class="font-heading mb-4 text-lg font-semibold text-on-surface"
                >
                    {{ t('settings.profile.title') }}
                </h2>
                <WebForm
                    v-slot="{ errors, processing }"
                    endpoint="/settings/profile"
                    class="space-y-5"
                >
                    <div class="space-y-2">
                        <Label for="email">{{ t('fields.email') }}</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            autocomplete="email"
                            :default-value="user?.email ?? ''"
                            :invalid="
                                fieldError(errors, 'email', 'profile').invalid
                            "
                            :described-by="
                                fieldError(errors, 'email', 'profile')
                                    .describedBy
                            "
                            required
                        />
                        <FieldError
                            v-bind="fieldError(errors, 'email', 'profile')"
                        />
                        <p
                            v-if="user?.email_verified_at"
                            class="text-sm text-primary"
                        >
                            {{ t('auth.verify.complete_title') }}
                        </p>
                        <Link
                            v-else
                            href="/verify-email"
                            class="inline-block text-sm font-semibold text-primary underline underline-offset-4"
                        >
                            {{ t('auth.verify.title') }}
                        </Link>
                    </div>

                    <div class="space-y-2">
                        <Label for="locale">{{ t('fields.locale') }}</Label>
                        <Select
                            id="locale"
                            name="locale"
                            :options="localeOptions"
                            :default-value="user?.locale ?? app.locale"
                            :invalid="
                                fieldError(errors, 'locale', 'profile').invalid
                            "
                            :described-by="
                                fieldError(errors, 'locale', 'profile')
                                    .describedBy
                            "
                            required
                        />
                        <FieldError
                            v-bind="fieldError(errors, 'locale', 'profile')"
                        />
                    </div>

                    <div
                        class="flex items-center justify-end border-t border-outline-glass pt-4"
                    >
                        <Button type="submit" :disabled="processing">
                            {{ t('settings.profile.submit') }}
                        </Button>
                    </div>
                </WebForm>
            </section>

            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-6"
            >
                <h2
                    class="font-heading mb-4 text-lg font-semibold text-on-surface"
                >
                    {{ t('settings.password.title') }}
                </h2>
                <WebForm
                    v-slot="{ errors, processing }"
                    endpoint="/settings/password"
                    :reset-on-success="['password', 'new_password']"
                    class="space-y-5"
                >
                    <div class="space-y-2">
                        <Label for="password">{{
                            t('fields.current_password')
                        }}</Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            :invalid="
                                fieldError(errors, 'password', 'password')
                                    .invalid
                            "
                            :described-by="
                                fieldError(errors, 'password', 'password')
                                    .describedBy
                            "
                            required
                        />
                        <FieldError
                            v-bind="fieldError(errors, 'password', 'password')"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="new_password">{{
                            t('fields.new_password')
                        }}</Label>
                        <Input
                            id="new_password"
                            name="new_password"
                            type="password"
                            autocomplete="new-password"
                            :invalid="
                                fieldError(errors, 'new_password', 'password')
                                    .invalid
                            "
                            :described-by="
                                fieldError(errors, 'new_password', 'password')
                                    .describedBy
                            "
                            required
                        />
                        <FieldError
                            v-bind="
                                fieldError(errors, 'new_password', 'password')
                            "
                        />
                    </div>

                    <div
                        class="flex items-center justify-end border-t border-outline-glass pt-4"
                    >
                        <Button type="submit" :disabled="processing">
                            {{ t('settings.password.submit') }}
                        </Button>
                    </div>
                </WebForm>
            </section>
        </div>
    </AppLayout>
</template>
