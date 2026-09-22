<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
const props = defineProps<{
    errors: Record<string, string>;
    processing: boolean;
}>();
const emit = defineEmits<{
    submit: [
        form: {
            name: string;
            start_url: string;
            source_type: string;
            instructions: string;
        },
    ];
}>();
const { t } = useI18n();
const sourceTypes = ['website', 'json', 'csv', 'xml'];
const form = reactive({
    name: '',
    start_url: '',
    source_type: 'website',
    instructions: '',
});
const formElement = ref<HTMLFormElement | null>(null);
function submit(): void {
    if (props.processing || !formElement.value?.reportValidity()) return;
    emit('submit', { ...form });
}
</script>
<template>
    <form
        ref="formElement"
        class="panel space-y-6 p-6 sm:p-8"
        @submit.prevent="submit"
    >
        <fieldset
            :disabled="processing"
            aria-describedby="source-help source-error"
        >
            <legend class="text-lg font-semibold">
                {{ t('builder.source_type') }}
            </legend>
            <p id="source-help" class="mt-1 text-sm text-on-surface-variant">
                {{ t('setup.source_help') }}
            </p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label
                    v-for="source in sourceTypes"
                    :key="source"
                    class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors focus-within:ring-2 focus-within:ring-primary"
                    :class="
                        form.source_type === source
                            ? 'border-primary bg-primary/5'
                            : 'border-outline-glass bg-white'
                    "
                >
                    <input
                        v-model="form.source_type"
                        type="radio"
                        name="source_type"
                        :value="source"
                        :aria-label="t(`builder.${source}`)"
                        :aria-invalid="!!errors.source_type"
                        class="mt-1 accent-primary"
                    />
                    <span>
                        <span class="block font-semibold">{{
                            t(`builder.${source}`)
                        }}</span>
                        <span
                            class="mt-1 block text-sm text-on-surface-variant"
                            >{{ t(`setup.${source}_description`) }}</span
                        >
                    </span>
                </label>
            </div>
            <FieldError id="source-error" :message="errors.source_type" />
        </fieldset>
        <div>
            <Label for="name">{{ t('recipes.name') }}</Label>
            <Input
                id="name"
                v-model="form.name"
                required
                minlength="2"
                maxlength="160"
                :placeholder="t('setup.name_example')"
                :invalid="!!errors.name"
                described-by="name-error"
            />
            <FieldError id="name-error" :message="errors.name" />
        </div>
        <div>
            <Label for="start_url">{{ t('setup.source_url') }}</Label>
            <Input
                id="start_url"
                v-model="form.start_url"
                type="url"
                required
                maxlength="2048"
                placeholder="https://example.com/products"
                :invalid="!!errors.start_url"
                described-by="url-error"
            />
            <FieldError id="url-error" :message="errors.start_url" />
        </div>
        <details :open="!!errors.instructions">
            <summary class="cursor-pointer text-sm font-medium">
                {{ t('setup.optional_notes') }}
            </summary>
            <div class="mt-3">
                <Label for="instructions">{{
                    t('recipes.instructions')
                }}</Label>
                <textarea
                    id="instructions"
                    v-model="form.instructions"
                    rows="3"
                    class="w-full rounded-lg border border-outline-glass bg-white p-3"
                    :placeholder="t('setup.example')"
                    :aria-invalid="!!errors.instructions"
                    aria-describedby="instructions-error"
                />
                <FieldError
                    id="instructions-error"
                    :message="errors.instructions"
                />
            </div>
        </details>
        <div
            class="flex flex-wrap items-center justify-between gap-4 border-t border-outline-glass pt-6"
        >
            <p class="max-w-sm text-sm text-on-surface-variant">
                {{ t('setup.mapping_next') }}
            </p>
            <Button type="submit" :disabled="processing">{{
                processing ? t('common.saving') : t('setup.continue_mapping')
            }}</Button>
        </div>
    </form>
</template>
