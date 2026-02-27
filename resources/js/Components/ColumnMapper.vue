<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    headers: { type: Array, required: true },
    fields: { type: Object, required: true },
    modelValue: { type: Object, required: true },
});

const emit = defineEmits(['update:modelValue']);

function headerKey(header, index) {
    // Use index-prefixed key to avoid collisions on empty/duplicate headers
    return `__col${index}__${header}`;
}

function updateMapping(header, index, field) {
    const key = headerKey(header, index);
    const newMapping = { ...props.modelValue, [key]: field };
    emit('update:modelValue', newMapping);
}

function getValue(header, index) {
    return props.modelValue[headerKey(header, index)] || '';
}
</script>

<template>
    <div class="space-y-3">
        <div class="grid grid-cols-2 gap-4 font-medium text-sm text-slate-700 pb-2 border-b border-slate-200">
            <div>{{ t('import.csvColumn') }}</div>
            <div>{{ t('import.contactField') }}</div>
        </div>
        <div v-for="(header, index) in headers" :key="index" class="grid grid-cols-2 gap-4 items-center">
            <div class="text-sm text-slate-600 truncate" :title="header || `(Column ${index + 1})`">
                {{ header || `(Column ${index + 1})` }}
            </div>
            <select
                :value="getValue(header, index)"
                @change="updateMapping(header, index, $event.target.value)"
                class="rounded-lg border-slate-300 bg-slate-50 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
            >
                <option v-for="(label, value) in fields" :key="value" :value="value">{{ label }}</option>
            </select>
        </div>
    </div>
</template>
