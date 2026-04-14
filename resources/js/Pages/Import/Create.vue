<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FileUpload from '@/Components/FileUpload.vue';
import ColumnMapper from '@/Components/ColumnMapper.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import axios from 'axios';

const { t } = useI18n();

const step = ref(1);
const loading = ref(false);
const error = ref('');

const uploadData = ref(null);
const columnMapping = ref({});
const globalDate = ref('');

async function handleFileSelected(file) {
    loading.value = true;
    error.value = '';

    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await axios.post(route('import.preview'), formData);
        uploadData.value = response.data;

        // Country-related columns are skipped (auto-detected from phone code)
        const countryAliases = ['country', 'pais', 'país', 'pais_code', 'country_code'];
        const filteredHeaders = response.data.headers.filter(
            h => !countryAliases.includes(h.toLowerCase().trim())
        );
        uploadData.value.headers = filteredHeaders;

        const autoMapping = {};
        const fieldNames = Object.keys(response.data.contact_fields);
        filteredHeaders.forEach((header, index) => {
            const key = `__col${index}__${header}`;
            const headerLower = header.toLowerCase().trim();
            const match = fieldNames.find(f => f === headerLower || headerLower.includes(f));
            autoMapping[key] = match || '';
        });
        columnMapping.value = autoMapping;
        step.value = 2;
    } catch (e) {
        error.value = e.response?.data?.message || 'Upload failed';
    } finally {
        loading.value = false;
    }
}

function startImport() {
    loading.value = true;
    // Convert indexed keys back to real header names for the backend
    const realMapping = {};
    for (const [key, value] of Object.entries(columnMapping.value)) {
        // Key format: __col{index}__{headerName}
        const headerName = key.replace(/^__col\d+__/, '');
        if (value) {
            realMapping[headerName] = value;
        }
    }
    router.post(route('import.store'), {
        path: uploadData.value.path,
        original_name: uploadData.value.original_name,
        total_rows: uploadData.value.total_rows,
        column_mapping: realMapping,
        global_date: globalDate.value || null,
    });
}
</script>

<template>
    <Head :title="t('import.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-slate-900">{{ t('import.title') }}</h2>
        </template>

        <div class="max-w-3xl">
            <!-- Step indicator -->
            <div class="flex items-center justify-center mb-6 gap-3">
                <div :class="['flex items-center justify-center w-9 h-9 rounded-full text-sm font-bold transition', step >= 1 ? 'bg-brand-600 text-white shadow-sm' : 'bg-slate-200 text-slate-400']">1</div>
                <div class="w-16 h-0.5 rounded" :class="step >= 2 ? 'bg-brand-600' : 'bg-slate-200'"></div>
                <div :class="['flex items-center justify-center w-9 h-9 rounded-full text-sm font-bold transition', step >= 2 ? 'bg-brand-600 text-white shadow-sm' : 'bg-slate-200 text-slate-400']">2</div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                <!-- Step 1: Upload -->
                <div v-if="step === 1">
                    <h3 class="text-base font-semibold text-slate-900 mb-4">{{ t('import.upload') }}</h3>
                    <FileUpload @file-selected="handleFileSelected" />
                    <div v-if="loading" class="mt-4 text-center text-sm text-slate-500 flex items-center justify-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-brand-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ t('common.loading') }}
                    </div>
                    <div v-if="error" class="mt-4 text-center text-sm text-red-600">{{ error }}</div>
                </div>

                <!-- Step 2: Mapping -->
                <div v-if="step === 2 && uploadData">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-semibold text-slate-900">{{ t('import.mapping') }}</h3>
                        <span class="inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-700">
                            {{ uploadData.total_rows?.toLocaleString() }} {{ t('import.totalRows').toLowerCase() }}
                        </span>
                    </div>

                    <!-- Preview table -->
                    <div class="overflow-x-auto mb-6 rounded-lg border border-slate-200">
                        <table class="min-w-full text-xs">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th v-for="(h, i) in uploadData.headers" :key="i" class="px-3 py-2 text-left font-medium text-slate-500">{{ h || `(Column ${i + 1})` }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(row, i) in uploadData.preview_rows" :key="i" class="hover:bg-slate-50/50">
                                    <td v-for="(cell, j) in row" :key="j" class="px-3 py-1.5 text-slate-700 whitespace-nowrap max-w-[200px] truncate">{{ cell }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <ColumnMapper
                        :headers="uploadData.headers"
                        :fields="uploadData.contact_fields"
                        v-model="columnMapping"
                    />

                    <!-- Global date -->
                    <div class="mt-4 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('import.globalDate') }}</label>
                        <input type="date" v-model="globalDate" class="block w-full max-w-xs rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" />
                        <p class="mt-1 text-xs text-slate-500">{{ t('import.globalDateHint') }}</p>
                    </div>

                    <div class="mt-4 flex items-start gap-2 rounded-lg bg-blue-50 border border-blue-200 px-4 py-3">
                        <svg class="h-5 w-5 text-blue-500 mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                        <div class="text-sm text-blue-700">
                            <p class="font-medium">{{ t('import.countryAutoNote') }}</p>
                            <p class="mt-0.5 text-blue-600 text-xs">{{ t('contacts.noCountryCode') }}</p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-between pt-4 border-t border-slate-100">
                        <SecondaryButton @click="step = 1">{{ t('common.previous') }}</SecondaryButton>
                        <PrimaryButton @click="startImport" :disabled="loading">{{ t('import.confirm') }}</PrimaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
