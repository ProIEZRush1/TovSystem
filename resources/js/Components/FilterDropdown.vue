<script setup>
defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    options: { type: Array, required: true },
    placeholder: { type: String, default: 'All' },
    valueKey: { type: String, default: 'value' },
    labelKey: { type: String, default: 'label' },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <select
        :value="modelValue"
        @change="emit('update:modelValue', $event.target.value)"
        class="rounded-lg border-slate-300 bg-slate-50 py-2 text-sm shadow-sm focus:border-brand-500 focus:bg-white focus:ring-brand-500 transition"
    >
        <option value="">{{ placeholder }}</option>
        <option
            v-for="option in options"
            :key="option[valueKey] ?? option"
            :value="option[valueKey] ?? option"
        >
            {{ option[labelKey] ?? option }}
        </option>
    </select>
</template>
