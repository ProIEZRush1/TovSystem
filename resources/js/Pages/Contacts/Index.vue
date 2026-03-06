<script setup>
import { ref, watch, computed } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { usePermissions } from '@/composables/usePermissions';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchInput from '@/Components/SearchInput.vue';
import FilterDropdown from '@/Components/FilterDropdown.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const { t } = useI18n();
const { can } = usePermissions();

const props = defineProps({
    contacts: Object,
    statuses: Array,
    labels: Array,
    countries: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status_id || '');
const countryFilter = ref(props.filters?.country || '');
const labelFilter = ref(props.filters?.label_id || '');
const sortField = ref(props.filters?.sort || '');
const sortDir = ref(props.filters?.direction || 'asc');
const selectedIds = ref([]);
const bulkStatusId = ref('');
const bulkLabelId = ref('');
const bulkLabelAction = ref('attach');
const showDeleteModal = ref(false);
const deleteContactId = ref(null);

const selectAll = computed({
    get: () => props.contacts.data.length > 0 && selectedIds.value.length === props.contacts.data.length,
    set: (val) => {
        selectedIds.value = val ? props.contacts.data.map(c => c.id) : [];
    },
});

const statusOptions = computed(() =>
    props.statuses.map(s => ({ value: s.id, label: s.name }))
);

const labelOptions = computed(() =>
    props.labels.map(l => ({ value: l.id, label: l.name }))
);

const countryOptions = computed(() =>
    props.countries.map(c => ({ value: c, label: c }))
);

function applyFilters() {
    router.get(route('contacts.index'), {
        search: search.value || undefined,
        status_id: statusFilter.value || undefined,
        country: countryFilter.value || undefined,
        label_id: labelFilter.value || undefined,
        sort: sortField.value || undefined,
        direction: sortDir.value || undefined,
    }, { preserveState: true, replace: true });
}

watch([search, statusFilter, countryFilter, labelFilter], () => applyFilters());

function toggleSort(field) {
    if (sortField.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDir.value = 'asc';
    }
    applyFilters();
}

function sortIcon(field) {
    if (sortField.value !== field) return '';
    return sortDir.value === 'asc' ? ' \u2191' : ' \u2193';
}

function bulkUpdateStatus() {
    if (!selectedIds.value.length || !bulkStatusId.value) return;
    router.post(route('contacts.bulk-status'), {
        ids: selectedIds.value,
        status_id: bulkStatusId.value,
    }, {
        onSuccess: () => {
            selectedIds.value = [];
            bulkStatusId.value = '';
        },
    });
}

function bulkUpdateLabels() {
    if (!selectedIds.value.length || !bulkLabelId.value) return;
    router.post(route('contacts.bulk-labels'), {
        ids: selectedIds.value,
        label_ids: [parseInt(bulkLabelId.value)],
        action: bulkLabelAction.value,
    }, {
        onSuccess: () => {
            selectedIds.value = [];
            bulkLabelId.value = '';
        },
    });
}

function confirmDelete(id) {
    deleteContactId.value = id;
    showDeleteModal.value = true;
}

function deleteContact() {
    router.delete(route('contacts.destroy', deleteContactId.value), {
        onSuccess: () => {
            showDeleteModal.value = false;
            deleteContactId.value = null;
        },
    });
}

function exportCsv() {
    const params = new URLSearchParams();
    if (search.value) params.set('search', search.value);
    if (statusFilter.value) params.set('status_id', statusFilter.value);
    if (countryFilter.value) params.set('country', countryFilter.value);
    window.location.href = route('contacts.export') + '?' + params.toString();
}
</script>

<template>
    <Head :title="t('contacts.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-900">{{ t('contacts.title') }}</h2>
                <button v-if="can('contacts.export')" @click="exportCsv" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                    <svg class="h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    {{ t('contacts.export') }}
                </button>
            </div>
        </template>

        <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 overflow-hidden">
            <!-- Filters -->
            <div class="border-b border-slate-100 p-4">
                <div class="flex flex-wrap gap-3 items-center">
                    <div class="w-full sm:w-64">
                        <SearchInput v-model="search" :placeholder="t('contacts.search')" />
                    </div>
                    <FilterDropdown v-model="statusFilter" :options="statusOptions" :placeholder="t('contacts.allStatuses')" />
                    <FilterDropdown v-model="countryFilter" :options="countryOptions" :placeholder="t('contacts.allCountries')" />
                    <FilterDropdown v-model="labelFilter" :options="labelOptions" :placeholder="t('labels.allLabels')" />
                </div>

                <!-- Bulk actions bar -->
                <div v-if="selectedIds.length && can('contacts.bulk_status')" class="mt-3 flex flex-wrap items-center gap-3 rounded-lg bg-brand-50 border border-brand-200 px-4 py-3">
                    <span class="text-sm font-medium text-brand-700">{{ t('contacts.selected', { count: selectedIds.length }) }}</span>

                    <div class="h-5 w-px bg-brand-200"></div>

                    <!-- Bulk status -->
                    <div class="flex items-center gap-2">
                        <select v-model="bulkStatusId" class="rounded-lg border-slate-300 text-sm bg-white focus:border-brand-500 focus:ring-brand-500">
                            <option value="">{{ t('contacts.bulkStatus') }}</option>
                            <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <button @click="bulkUpdateStatus" :disabled="!bulkStatusId" class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50 transition">
                            {{ t('common.save') }}
                        </button>
                    </div>

                    <div class="h-5 w-px bg-brand-200"></div>

                    <!-- Bulk labels -->
                    <div class="flex items-center gap-2">
                        <select v-model="bulkLabelAction" class="rounded-lg border-slate-300 text-sm bg-white focus:border-brand-500 focus:ring-brand-500">
                            <option value="attach">{{ t('labels.attach') }}</option>
                            <option value="detach">{{ t('labels.detach') }}</option>
                        </select>
                        <select v-model="bulkLabelId" class="rounded-lg border-slate-300 text-sm bg-white focus:border-brand-500 focus:ring-brand-500">
                            <option value="">{{ t('labels.bulkLabels') }}</option>
                            <option v-for="l in labels" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                        <button @click="bulkUpdateLabels" :disabled="!bulkLabelId" class="rounded-lg bg-slate-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-600 disabled:opacity-50 transition">
                            {{ t('common.save') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-slate-100">
                            <th v-if="can('contacts.bulk_status')" class="w-10 px-4 py-3">
                                <input type="checkbox" v-model="selectAll" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                            </th>
                            <th @click="toggleSort('phone')" class="cursor-pointer px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hover:text-slate-700 transition">
                                {{ t('contacts.phone') }}{{ sortIcon('phone') }}
                            </th>
                            <th @click="toggleSort('name')" class="cursor-pointer px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hover:text-slate-700 transition">
                                {{ t('contacts.name') }}{{ sortIcon('name') }}
                            </th>
                            <th @click="toggleSort('country')" class="cursor-pointer px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hover:text-slate-700 transition">
                                {{ t('contacts.country') }}{{ sortIcon('country') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('contacts.status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('labels.title') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('contacts.source') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('contacts.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="contact in contacts.data" :key="contact.id" class="hover:bg-slate-50/50 transition">
                            <td v-if="can('contacts.bulk_status')" class="px-4 py-3">
                                <input type="checkbox" :value="contact.id" v-model="selectedIds" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-900 whitespace-nowrap font-mono">{{ contact.phone }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ contact.name || '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ contact.country || '-' }}</td>
                            <td class="px-4 py-3">
                                <StatusBadge v-if="contact.status" :name="contact.status.name" :color="contact.status.color" />
                                <span v-else class="text-sm text-slate-300">-</span>
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="contact.labels?.length" class="flex flex-wrap gap-1">
                                    <span
                                        v-for="label in contact.labels"
                                        :key="label.id"
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                        :style="{ backgroundColor: label.color + '20', color: label.color }"
                                    >
                                        {{ label.name }}
                                    </span>
                                </div>
                                <span v-else class="text-sm text-slate-300">-</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ contact.source || '-' }}</td>
                            <td class="px-4 py-3 text-right text-sm space-x-3 whitespace-nowrap">
                                <Link v-if="can('contacts.update')" :href="route('contacts.show', contact.id)" class="font-medium text-brand-600 hover:text-brand-500 transition">
                                    {{ t('contacts.edit') }}
                                </Link>
                                <Link v-else-if="can('contacts.view')" :href="route('contacts.show', contact.id)" class="font-medium text-slate-600 hover:text-slate-500 transition">
                                    {{ t('contacts.details') }}
                                </Link>
                                <button v-if="can('contacts.delete')" @click="confirmDelete(contact.id)" class="font-medium text-red-600 hover:text-red-500 transition">
                                    {{ t('contacts.delete') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty -->
            <div v-if="!contacts.data.length" class="px-6 py-12 text-center">
                <svg class="mx-auto h-10 w-10 text-slate-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
                <p class="mt-2 text-sm text-slate-500">{{ t('contacts.noContacts') }}</p>
            </div>

            <!-- Pagination -->
            <div v-if="contacts.data.length" class="border-t border-slate-100 px-4 py-3 flex items-center justify-between">
                <p class="text-sm text-slate-500">
                    {{ t('common.showing', { from: contacts.from, to: contacts.to, total: contacts.total }) }}
                </p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in contacts.links"
                        :key="link.label"
                        :href="link.url || ''"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-sm font-medium transition',
                            link.active ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100',
                            !link.url ? 'opacity-40 pointer-events-none' : ''
                        ]"
                        v-html="link.label"
                        preserve-state
                    />
                </div>
            </div>
        </div>

        <ConfirmModal
            :show="showDeleteModal"
            :title="t('contacts.delete')"
            :message="t('contacts.confirmDelete')"
            @confirm="deleteContact"
            @cancel="showDeleteModal = false"
        />
    </AuthenticatedLayout>
</template>
