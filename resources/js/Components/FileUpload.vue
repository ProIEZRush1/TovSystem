<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    accept: { type: String, default: '.csv' },
});

const emit = defineEmits(['file-selected']);
const isDragging = ref(false);
const fileInput = ref(null);

function handleDrop(e) {
    isDragging.value = false;
    const file = e.dataTransfer.files[0];
    if (file) emit('file-selected', file);
}

function handleSelect(e) {
    const file = e.target.files[0];
    if (file) emit('file-selected', file);
}

function openPicker() {
    fileInput.value?.click();
}
</script>

<template>
    <div
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="handleDrop"
        @click="openPicker"
        :class="[
            'flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 cursor-pointer transition',
            isDragging ? 'border-brand-500 bg-brand-50' : 'border-slate-300 hover:border-slate-400'
        ]"
    >
        <svg class="h-10 w-10 text-slate-400 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
        </svg>
        <p class="text-sm font-medium text-slate-700">{{ t('import.selectFile') }}</p>
        <p class="text-xs text-slate-500 mt-1">{{ t('import.dragDrop') }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ t('import.csvOnly') }}</p>
        <input
            ref="fileInput"
            type="file"
            :accept="accept"
            class="hidden"
            @change="handleSelect"
        />
    </div>
</template>
