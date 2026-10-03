<script setup lang="ts">
import { Columns3, MousePointer2, Plus } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import type { RecipeFieldDefinition } from '@/lib/recipe-definition';
const fields = defineModel<RecipeFieldDefinition[]>({ required: true });
defineProps<{ sourceType: string }>();
const emit = defineEmits<{
    add: [];
    remove: [index: number];
    pick: [name: string];
    transform: [field: RecipeFieldDefinition, value: string];
    boolean: [
        field: RecipeFieldDefinition,
        key: 'boolean_true' | 'boolean_false',
        value: string,
    ];
}>();
const { t } = useI18n();
</script>
<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="flex items-center gap-2 text-xl font-semibold">
                    <Columns3 :size="20" class="text-primary" />
                    {{ t('builder.fields') }}
                </h2>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('builder.fields_help') }}
                </p>
            </div>
            <Button variant="secondary" class="w-full" @click="emit('add')"
                ><Plus :size="16" />{{ t('builder.add_field') }}</Button
            >
        </div>
        <div
            v-for="(field, index) in fields"
            :key="index"
            class="grid gap-3 border-t border-outline-glass pt-5"
        >
            <div>
                <Label :for="`field-name-${index}`"
                    ><span
                        class="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-lg bg-lavender text-xs text-primary-container"
                        >{{ index + 1 }}</span
                    >{{ t('builder.field_name') }}</Label
                ><Input :id="`field-name-${index}`" v-model="field.name" />
            </div>
            <div v-if="sourceType === 'website'" class="col-span-full">
                <Button
                    class="w-full"
                    variant="secondary"
                    @click="emit('pick', field.name)"
                    ><MousePointer2 :size="16" />{{
                        t('builder.pick_visual')
                    }}</Button
                >
                <details class="mt-2 text-sm">
                    <summary class="text-on-surface-variant">
                        {{ t('builder.advanced_selectors') }}
                    </summary>
                    <Label :for="`field-path-${index}`">{{
                        t('builder.field_path')
                    }}</Label>
                    <Input :id="`field-path-${index}`" v-model="field.path" />
                </details>
            </div>
            <details class="border-t border-outline-glass/60 pt-1">
                <summary class="text-sm font-medium text-on-surface-variant">
                    {{ t('redesign.advanced_fields') }}
                </summary>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div v-if="sourceType !== 'website'" class="col-span-full">
                        <Label :for="`field-path-${index}`">{{
                            t('builder.field_path')
                        }}</Label>
                        <Input
                            :id="`field-path-${index}`"
                            v-model="field.path"
                        />
                    </div>

                    <div>
                        <Label :for="`field-type-${index}`">{{
                            t('builder.field_type')
                        }}</Label
                        ><select
                            :id="`field-type-${index}`"
                            v-model="field.type"
                            class="h-12 w-full rounded-lg border border-outline-glass bg-white px-3"
                        >
                            <option
                                v-for="type in [
                                    'string',
                                    'number',
                                    'boolean',
                                    'date',
                                    'url',
                                ]"
                                :key="type"
                                :value="type"
                            >
                                {{ t(`builder.type_${type}`) }}
                            </option>
                        </select>
                    </div>
                    <label class="flex min-h-12 items-center gap-2 pt-5 text-sm"
                        ><input
                            v-model="field.required"
                            type="checkbox"
                            class="h-4 w-4 accent-primary"
                        />{{ t('builder.required') }}</label
                    ><Button
                        variant="danger"
                        class="self-end"
                        :disabled="fields.length < 2"
                        @click="emit('remove', index)"
                        >{{ t('builder.remove_field') }}</Button
                    >
                    <div v-if="field.type === 'number'" class="col-span-full">
                        <Label :for="`field-number-locale-${index}`">{{
                            t('builder.number_format')
                        }}</Label
                        ><select
                            :id="`field-number-locale-${index}`"
                            v-model="field.number_locale"
                            class="h-11 rounded-lg border border-outline-glass bg-white px-3"
                        >
                            <option value="dot">1,234.56</option>
                            <option value="comma">1.234,56</option>
                        </select>
                    </div>
                    <div v-if="field.type === 'date'" class="col-span-full">
                        <Label :for="`field-date-format-${index}`">{{
                            t('builder.date_format')
                        }}</Label
                        ><Input
                            :id="`field-date-format-${index}`"
                            v-model="field.date_format"
                            placeholder="Y-m-d"
                        />
                    </div>
                    <div
                        v-if="field.type === 'boolean'"
                        class="col-span-full grid gap-3 sm:grid-cols-2"
                    >
                        <div>
                            <Label :for="`field-true-${index}`">{{
                                t('builder.true_values')
                            }}</Label
                            ><Input
                                :id="`field-true-${index}`"
                                :model-value="
                                    field.boolean_true?.join(', ') ?? ''
                                "
                                @update:model-value="
                                    emit(
                                        'boolean',
                                        field,
                                        'boolean_true',
                                        String($event ?? ''),
                                    )
                                "
                            />
                        </div>
                        <div>
                            <Label :for="`field-false-${index}`">{{
                                t('builder.false_values')
                            }}</Label
                            ><Input
                                :id="`field-false-${index}`"
                                :model-value="
                                    field.boolean_false?.join(', ') ?? ''
                                "
                                @update:model-value="
                                    emit(
                                        'boolean',
                                        field,
                                        'boolean_false',
                                        String($event ?? ''),
                                    )
                                "
                            />
                        </div>
                    </div>
                    <div class="col-span-full">
                        <Label :for="`field-transform-${index}`">{{
                            t('builder.transform')
                        }}</Label
                        ><select
                            :id="`field-transform-${index}`"
                            :value="field.transforms?.[0]?.op ?? 'trim'"
                            class="h-11 rounded-lg border border-outline-glass bg-white px-3"
                            @change="
                                emit(
                                    'transform',
                                    field,
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="trim">
                                {{ t('builder.trim') }}
                            </option>
                            <option value="lowercase">
                                {{ t('builder.lowercase') }}
                            </option>
                            <option value="uppercase">
                                {{ t('builder.uppercase') }}
                            </option>
                            <option value="replace">
                                {{ t('builder.replace') }}
                            </option>
                            <option value="prefix">
                                {{ t('builder.prefix') }}
                            </option>
                            <option value="suffix">
                                {{ t('builder.suffix') }}
                            </option>
                        </select>
                        <div
                            v-if="field.transforms?.[0]?.op === 'replace'"
                            class="mt-2 grid gap-3 sm:grid-cols-2"
                        >
                            <Input
                                :value="field.transforms[0]?.search ?? ''"
                                :placeholder="t('builder.find_text')"
                                @input="
                                    field.transforms![0]!.search = (
                                        $event.target as HTMLInputElement
                                    ).value
                                "
                            /><Input
                                :value="field.transforms[0]?.value ?? ''"
                                :placeholder="t('builder.replacement_text')"
                                @input="
                                    field.transforms![0]!.value = (
                                        $event.target as HTMLInputElement
                                    ).value
                                "
                            />
                        </div>
                        <Input
                            v-else-if="
                                ['prefix', 'suffix'].includes(
                                    field.transforms?.[0]?.op ?? '',
                                )
                            "
                            class="mt-2"
                            :value="field.transforms?.[0]?.value ?? ''"
                            :placeholder="t('builder.transform_value')"
                            @input="
                                field.transforms![0]!.value = (
                                    $event.target as HTMLInputElement
                                ).value
                            "
                        />
                    </div>
                </div>
            </details>
        </div>
    </div>
</template>
