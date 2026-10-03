<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDraftGuard } from '@/composables/useDraftGuard';
import { suggestedCollectorName } from '@/lib/collector-workflow';
import { Globe2, Braces, FileSpreadsheet, Rss } from '@lucide/vue';
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
useDraftGuard(() => JSON.stringify(form));
let lastSuggestion = '';
watch(
    () => form.start_url,
    (url) => {
        const suggested = suggestedCollectorName(url);
        if (!form.name || form.name === lastSuggestion) {
            form.name = suggested;
            lastSuggestion = suggested;
        }
    },
);
const icons: Record<string, typeof Globe2> = {
    website: Globe2,
    json: Braces,
    csv: FileSpreadsheet,
    xml: Rss,
};
const tones: Record<string, string> = {
    website: 'bg-lavender',
    json: 'bg-peach',
    csv: 'bg-mint',
    xml: 'bg-sky',
};
const formElement = ref<HTMLFormElement | null>(null);
function submit(): void {
    if (props.processing || !formElement.value?.reportValidity()) return;
    emit('submit', { ...form });
}
</script>
<template>
    <form
        ref="formElement"
        class="panel space-y-6 p-5 sm:p-8"
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
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <label
                    v-for="source in sourceTypes"
                    :key="source"
                    class="relative block min-w-0 cursor-pointer rounded-2xl border p-3 transition-colors focus-within:ring-2 focus-within:ring-primary sm:p-4"
                    :class="
                        form.source_type === source
                            ? 'border-primary bg-lavender/50 ring-1 ring-primary'
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
                        class="absolute top-4 right-4 accent-primary"
                    />
                    <span class="flex-1"
                        ><span
                            class="pastel-icon mb-3 h-10 w-10"
                            :class="tones[source]"
                            ><component
                                :is="icons[source]"
                                :size="22"
                                aria-hidden="true"
                        /></span>
                        <span class="block font-semibold">{{
                            t(`builder.${source}`)
                        }}</span>
                        <span
                            class="mt-1 hidden text-xs leading-relaxed text-on-surface-variant sm:block sm:text-sm"
                            >{{ t(`setup.${source}_description`) }}</span
                        >
                    </span>
                </label>
            </div>
            <p class="mt-3 text-sm text-on-surface-variant sm:hidden">
                {{ t(`setup.${form.source_type}_description`) }}
            </p>
            <FieldError id="source-error" :message="errors.source_type" />
        </fieldset>
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
