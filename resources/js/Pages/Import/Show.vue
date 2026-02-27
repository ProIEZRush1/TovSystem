<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const { t } = useI18n();

const props = defineProps({ import: Object });

const importData = ref({ ...props.import });
let pollTimer = null;

const isProcessing = computed(() => ['pending', 'processing'].includes(importData.value.status));

const statusConfig = computed(() => {
    switch (importData.value.status) {
        case 'completed': return { color: 'text-green-700 bg-green-50', label: t('import.completed') };
        case 'failed': return { color: 'text-red-700 bg-red-50', label: t('import.failed') };
        case 'completed_with_errors': return { color: 'text-amber-700 bg-amber-50', label: 'Completed with errors' };
        default: return { color: 'text-blue-700 bg-blue-50', label: t('import.processing') };
    }
});

async function pollStatus() {
    try {
        const { data } = await axios.get(route('import.status', importData.value.id));
        importData.value = { ...importData.value, ...data };
        if (!isProcessing.value) clearInterval(pollTimer);
    } catch {}
}

onMounted(() => {
    if (isProcessing.value) pollTimer = setInterval(pollStatus, 2000);
});
onUnmounted(() => clearInterval(pollTimer));
</script>

<template>
    <Head :title="t('import.progress')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-slate-900">{{ t('import.progress') }}</h2>
        </template>

        <div class="max-w-3xl">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5 space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">{{ importData.original_filename }}</h3>
                        <span :class="['mt-1 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', statusConfig.color]">
                            {{ statusConfig.label }}
                        </span>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div>
                    <div class="flex justify-between text-sm text-slate-600 mb-2">
                        <span class="tabular-nums">{{ importData.rows_imported?.toLocaleString() }} / {{ importData.total_rows?.toLocaleString() }}</span>
                        <span class="font-semibold">{{ importData.progress_percentage }}%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
                        <div
                            class="h-3 rounded-full transition-all duration-700 ease-out"
                            :class="importData.status === 'failed' ? 'bg-red-500' : 'bg-brand-600'"
                            :style="{ width: importData.progress_percentage + '%' }"
                        ></div>
                    </div>
                </div>

                <!-- Processing animation -->
                <div v-if="isProcessing" class="flex items-center gap-2.5 text-sm text-brand-600 bg-brand-50 rounded-lg px-4 py-3">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="font-medium">{{ t('import.processing') }}</span>
                </div>

                <!-- Errors -->
                <div v-if="importData.errors?.length" class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <h4 class="text-sm font-semibold text-red-800 mb-2">{{ t('import.errors') }} ({{ importData.errors.length }})</h4>
                    <ul class="text-sm text-red-700 space-y-1 max-h-48 overflow-y-auto">
                        <li v-for="(err, i) in importData.errors" :key="i">{{ err }}</li>
                    </ul>
                </div>

                <div class="pt-4 border-t border-slate-100">
                    <Link :href="route('contacts.index')" class="inline-flex items-center gap-2 text-sm font-medium text-brand-600 hover:text-brand-500 transition">
                        {{ t('nav.contacts') }}
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
