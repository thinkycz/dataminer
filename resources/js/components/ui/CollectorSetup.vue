<script setup lang="ts">
import { nextTick, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import WorkflowSteps from '@/components/ui/WorkflowSteps.vue';
const props = defineProps<{
    errors: Record<string, string>;
    processing: boolean;
}>();
const emit = defineEmits<{
    submit: [form: { name: string; start_url: string; instructions: string }];
}>();
const { t } = useI18n();
const form = reactive({ name: '', start_url: '', instructions: '' });
const step = ref(0);
const heading = ref<HTMLElement | null>(null);
const formElement = ref<HTMLFormElement | null>(null);
async function goTo(index: number): Promise<void> {
    step.value = index;
    await nextTick();
    heading.value?.focus();
}
function submit(): void {
    if (props.processing || !formElement.value?.reportValidity()) return;
    if (step.value < 2) {
        void goTo(step.value + 1);
        return;
    }
    emit('submit', { ...form });
}
watch(
    () => props.errors,
    (errors) => {
        if (errors.start_url) void goTo(0);
        else if (errors.instructions) void goTo(1);
        else if (errors.name) void goTo(2);
    },
);
</script>
<template>
    <div>
        <WorkflowSteps
            :steps="[t('setup.website'), t('setup.data'), t('setup.review')]"
            :current="step"
        />
        <form
            ref="formElement"
            class="panel p-6 sm:p-8"
            @submit.prevent="submit"
        >
            <h2 ref="heading" tabindex="-1" class="text-xl font-semibold">
                {{
                    t(
                        [
                            'setup.website_title',
                            'setup.data_title',
                            'setup.review_title',
                        ][step]!,
                    )
                }}
            </h2>
            <p class="mt-2 mb-6 text-on-surface-variant">
                {{
                    t(
                        [
                            'setup.website_help',
                            'setup.data_help',
                            'setup.review_help',
                        ][step]!,
                    )
                }}
            </p>
            <div v-if="step === 0">
                <Label for="start_url">{{ t('recipes.start_url') }}</Label>
                <Input
                    id="start_url"
                    v-model="form.start_url"
                    type="url"
                    required
                    placeholder="https://example.com/products"
                    :invalid="!!errors.start_url"
                    described-by="url-error"
                />
                <FieldError id="url-error" :message="errors.start_url" />
            </div>
            <div v-else-if="step === 1">
                <Label for="instructions">{{
                    t('recipes.instructions')
                }}</Label>
                <textarea
                    id="instructions"
                    v-model="form.instructions"
                    rows="6"
                    class="w-full rounded-lg border border-outline-glass bg-white p-3"
                    :placeholder="t('setup.example')"
                    :aria-invalid="!!errors.instructions"
                    aria-describedby="instructions-help instructions-error"
                />
                <p
                    id="instructions-help"
                    class="mt-2 text-sm text-on-surface-variant"
                >
                    {{ t('setup.example') }}
                </p>
                <FieldError
                    id="instructions-error"
                    :message="errors.instructions"
                />
            </div>
            <div v-else class="space-y-6">
                <dl class="space-y-4 rounded-lg bg-surface-container-low p-4">
                    <div>
                        <dt class="text-sm font-medium text-on-surface-variant">
                            {{ t('recipes.start_url') }}
                        </dt>
                        <dd class="break-all">{{ form.start_url }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-on-surface-variant">
                            {{ t('recipes.instructions') }}
                        </dt>
                        <dd class="whitespace-pre-wrap break-words">
                            {{ form.instructions }}
                        </dd>
                    </div>
                </dl>
                <div>
                    <Label for="name">{{ t('recipes.name') }}</Label
                    ><Input
                        id="name"
                        v-model="form.name"
                        required
                        :placeholder="t('setup.name_example')"
                        :invalid="!!errors.name"
                        described-by="name-error"
                    /><FieldError id="name-error" :message="errors.name" />
                </div>
            </div>
            <div
                class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-outline-glass pt-6"
            >
                <Button
                    v-if="step > 0"
                    variant="secondary"
                    :disabled="processing"
                    @click="goTo(step - 1)"
                    >{{ t('common.previous') }}</Button
                ><span v-else class="text-sm text-on-surface-variant">{{
                    t('setup.step', { current: step + 1, total: 3 })
                }}</span>
                <Button type="submit" :disabled="processing">{{
                    processing
                        ? t('common.saving')
                        : step < 2
                          ? t('common.next')
                          : t('recipes.create')
                }}</Button>
            </div>
        </form>
    </div>
</template>
