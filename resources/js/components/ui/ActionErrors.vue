<script setup lang="ts">
import { useSharedProps } from '@/composables/useSharedProps';
import Alert from '@/components/ui/Alert.vue';
const { errors } = useSharedProps();
</script>
<template>
    <div v-if="Object.keys(errors ?? {}).length" class="mb-6">
        <Alert variant="error"
            ><ul class="space-y-1">
                <li v-for="(message, key) in errors" :key="key">
                    {{ message }}
                </li>
            </ul></Alert
        >
    </div>
</template>
