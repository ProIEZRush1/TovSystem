<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Search...' },
});

const emit = defineEmits(['update:modelValue']);
const input = ref(props.modelValue);
let debounceTimer = null;

watch(input, (val) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        emit('update:modelValue', val);
    }, 300);
});

watch(() => props.modelValue, (val) => {
    input.value = val;
});
</script>

<template>
    <div class="relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
        </svg>
        <input
            v-model="input"
            type="text"
            :placeholder="placeholder"
            class="w-full rounded-lg border-slate-300 bg-slate-50 pl-10 pr-4 py-2 text-sm shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500 transition"
        />
    </div>
</template>
