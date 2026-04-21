<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, required: true }, // [{value, label}]
    placeholder: { type: String, default: 'Select...' },
    searchable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const search = ref('');
const root = ref(null);

const selected = computed(() => {
    const set = new Set(props.modelValue.map(String));
    return props.options.filter(o => set.has(String(o.value)));
});

const filteredOptions = computed(() => {
    if (!search.value) return props.options;
    const q = search.value.toLowerCase();
    return props.options.filter(o => String(o.label).toLowerCase().includes(q));
});

function toggle(value) {
    const strValue = String(value);
    const current = props.modelValue.map(String);
    const idx = current.indexOf(strValue);
    const next = [...props.modelValue];
    if (idx >= 0) {
        next.splice(idx, 1);
    } else {
        next.push(value);
    }
    emit('update:modelValue', next);
}

function isSelected(value) {
    return props.modelValue.map(String).includes(String(value));
}

function clearAll() {
    emit('update:modelValue', []);
}

function handleOutsideClick(e) {
    if (root.value && !root.value.contains(e.target)) {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('mousedown', handleOutsideClick);
});
onUnmounted(() => {
    document.removeEventListener('mousedown', handleOutsideClick);
});

const displayLabel = computed(() => {
    if (selected.value.length === 0) return props.placeholder;
    if (selected.value.length === 1) return selected.value[0].label;
    if (selected.value.length <= 2) return selected.value.map(o => o.label).join(', ');
    return `${selected.value.length} seleccionados`;
});
</script>

<template>
    <div ref="root" class="relative inline-block">
        <button
            type="button"
            @click="open = !open"
            :class="[
                'flex items-center justify-between gap-2 rounded-lg border bg-white px-3 py-2 text-sm transition min-w-44',
                selected.length ? 'border-brand-400 text-brand-700 font-medium' : 'border-slate-300 text-slate-700',
                open ? 'ring-2 ring-brand-200 border-brand-500' : 'hover:bg-slate-50'
            ]"
        >
            <span class="truncate">{{ displayLabel }}</span>
            <span class="flex items-center gap-1 shrink-0">
                <span v-if="selected.length" class="inline-flex items-center rounded-full bg-brand-100 text-brand-700 text-xs font-bold px-1.5">{{ selected.length }}</span>
                <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </span>
        </button>

        <div v-if="open" class="absolute z-40 mt-1 w-72 rounded-lg bg-white shadow-xl ring-1 ring-slate-900/10 overflow-hidden">
            <div v-if="searchable" class="p-2 border-b border-slate-100">
                <input
                    v-model="search"
                    type="text"
                    class="block w-full rounded-md border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500"
                    placeholder="Buscar..."
                    @keydown.esc="open = false"
                />
            </div>
            <div class="max-h-64 overflow-y-auto">
                <button
                    v-for="opt in filteredOptions"
                    :key="opt.value"
                    type="button"
                    @click="toggle(opt.value)"
                    :class="[
                        'w-full flex items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 transition',
                        isSelected(opt.value) ? 'bg-brand-50/60 text-brand-700' : 'text-slate-700'
                    ]"
                >
                    <div :class="[
                        'flex h-4 w-4 shrink-0 items-center justify-center rounded border-2',
                        isSelected(opt.value) ? 'bg-brand-600 border-brand-600' : 'border-slate-300'
                    ]">
                        <svg v-if="isSelected(opt.value)" class="h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                    <span class="truncate">{{ opt.label }}</span>
                </button>
                <p v-if="!filteredOptions.length" class="px-3 py-4 text-sm text-slate-400 text-center">Sin resultados</p>
            </div>
            <div v-if="selected.length" class="flex items-center justify-between border-t border-slate-100 px-3 py-2">
                <span class="text-xs text-slate-500">{{ selected.length }} seleccionados</span>
                <button @click="clearAll" class="text-xs text-brand-600 hover:text-brand-500 font-medium">Limpiar</button>
            </div>
        </div>
    </div>
</template>
