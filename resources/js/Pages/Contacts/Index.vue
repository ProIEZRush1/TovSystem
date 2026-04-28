<script setup>
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { usePermissions } from '@/composables/usePermissions';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchInput from '@/Components/SearchInput.vue';
import FilterDropdown from '@/Components/FilterDropdown.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import axios from 'axios';

const { t } = useI18n();
const { can } = usePermissions();

const props = defineProps({
    contacts: Object,
    statuses: Array,
    labels: Array,
    countries: Array,
    filters: Object,
});

const filters = Array.isArray(props.filters) ? {} : (props.filters || {});
const search = ref(filters.search || '');
// Status/Country filters are now multi-select arrays.
// Accept both legacy "5" and new "5,7,9" URL params on load.
function parseFilterList(raw) {
    if (!raw) return [];
    if (Array.isArray(raw)) return raw;
    return String(raw).split(',').map(s => s.trim()).filter(Boolean);
}
const statusFilter = ref(parseFilterList(filters.status_id).map(v => parseInt(v) || v));
const countryFilter = ref(parseFilterList(filters.country));
const labelFilter = ref(parseFilterList(filters.label_id).map(v => parseInt(v) || v));
const sortField = ref(typeof filters.sort === 'string' ? filters.sort : '');
const sortDir = ref(typeof filters.direction === 'string' ? filters.direction : 'asc');
const selectedIds = ref([]);
const bulkStatusId = ref('');
const bulkLabelId = ref('');
const bulkLabelAction = ref('attach');
const bulkDateValue = ref('');
const dateFrom = ref(filters.date_from || '');
const dateTo = ref(filters.date_to || '');
const showDeleteModal = ref(false);
const deleteContactId = ref(null);
const showQuickAdd = ref(false);
const quickAddPhones = ref('');
const quickAddStatusId = ref('');
const quickAddDate = ref('');
const quickAddLabelIds = ref([]);
const quickAddSource = ref('');
const quickAddMode = ref('only_new'); // only_new | fill_empty | overwrite
const quickAddLoading = ref(false);
const quickAddResult = ref(null);

function toggleQuickAddLabel(id) {
    const i = quickAddLabelIds.value.indexOf(id);
    if (i >= 0) quickAddLabelIds.value.splice(i, 1);
    else quickAddLabelIds.value.push(id);
}

// Drag-to-select state
let isDragging = false;
let dragStartIndex = -1;
let dragSelectMode = true; // true = selecting, false = deselecting
let lastClickedIndex = -1;

function getRowIndex(contactId) {
    return allContacts.value.findIndex(c => c.id === contactId);
}

function selectRange(startIdx, endIdx) {
    const from = Math.min(startIdx, endIdx);
    const to = Math.max(startIdx, endIdx);
    const idsInRange = allContacts.value.slice(from, to + 1).map(c => c.id);

    if (dragSelectMode) {
        const idSet = new Set(selectedIds.value);
        idsInRange.forEach(id => idSet.add(id));
        selectedIds.value = [...idSet];
    } else {
        const removeSet = new Set(idsInRange);
        selectedIds.value = selectedIds.value.filter(id => !removeSet.has(id));
    }
}

function onRowMousedown(event, contactId) {
    if (!can('contacts.bulk_status')) return;
    // Don't interfere with links, buttons, or checkbox clicks
    const tag = event.target.tagName;
    if (tag === 'A' || tag === 'BUTTON' || tag === 'INPUT') return;

    const idx = getRowIndex(contactId);
    if (idx === -1) return;

    // Shift+click = range select from last clicked
    if (event.shiftKey && lastClickedIndex !== -1) {
        event.preventDefault();
        dragSelectMode = true;
        selectRange(lastClickedIndex, idx);
        lastClickedIndex = idx;
        return;
    }

    isDragging = true;
    dragStartIndex = idx;
    lastClickedIndex = idx;
    // Determine mode: if row is already selected, we deselect on drag; otherwise select
    dragSelectMode = !selectedIds.value.includes(contactId);

    // Toggle the clicked row
    if (dragSelectMode) {
        if (!selectedIds.value.includes(contactId)) {
            selectedIds.value.push(contactId);
        }
    } else {
        selectedIds.value = selectedIds.value.filter(id => id !== contactId);
    }

    // Prevent text selection during drag
    event.preventDefault();
}

function onRowMouseenter(contactId) {
    if (!isDragging) return;
    const idx = getRowIndex(contactId);
    if (idx === -1) return;
    // Reset to only the drag range (not cumulative)
    const from = Math.min(dragStartIndex, idx);
    const to = Math.max(dragStartIndex, idx);
    const idsInRange = allContacts.value.slice(from, to + 1).map(c => c.id);

    if (dragSelectMode) {
        const idSet = new Set(selectedIds.value);
        idsInRange.forEach(id => idSet.add(id));
        selectedIds.value = [...idSet];
    } else {
        const removeSet = new Set(idsInRange);
        selectedIds.value = selectedIds.value.filter(id => !removeSet.has(id));
    }
}

function onMouseUp() {
    isDragging = false;
}

// Infinite scroll state
const allContacts = ref([...props.contacts.data]);
const currentPage = ref(props.contacts.current_page);
const lastPage = ref(props.contacts.last_page);
const nextPageUrl = ref(props.contacts.last_page > 1 ? 'pending' : null);
const totalContacts = ref(props.contacts.total);
const loadingMore = ref(false);
const sentinel = ref(null);
let observer = null;

// Reset accumulated contacts when Inertia delivers new page props (filter change)
watch(() => props.contacts, (newContacts) => {
    allContacts.value = [...newContacts.data];
    currentPage.value = newContacts.current_page;
    lastPage.value = newContacts.last_page;
    nextPageUrl.value = newContacts.last_page > 1 ? 'pending' : null;
    totalContacts.value = newContacts.total;
    selectedIds.value = [];
});

const selectAll = computed({
    get: () => allContacts.value.length > 0 && selectedIds.value.length === allContacts.value.length,
    set: (val) => {
        selectedIds.value = val ? allContacts.value.map(c => c.id) : [];
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

function joinArr(a) {
    return (Array.isArray(a) && a.length) ? a.join(',') : undefined;
}

function formatContactDate(d) {
    if (!d) return '-';
    // Laravel returns ISO like "2026-04-15T00:00:00.000000Z" or "2026-04-15"
    const s = String(d).slice(0, 10);
    const parts = s.split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
    return s;
}

function applyFilters() {
    router.get(route('contacts.index'), {
        search: search.value || undefined,
        status_id: joinArr(statusFilter.value),
        country: joinArr(countryFilter.value),
        label_id: joinArr(labelFilter.value),
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        sort: sortField.value || undefined,
        direction: sortDir.value || undefined,
    }, { preserveState: true, replace: true });
}

watch([search, statusFilter, countryFilter, labelFilter, dateFrom, dateTo], () => applyFilters());

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

function buildPageUrl(page) {
    const params = new URLSearchParams();
    params.set('page', page);
    if (search.value) params.set('search', search.value);
    const _sid = joinArr(statusFilter.value);
    if (_sid) params.set('status_id', _sid);
    const _cnt = joinArr(countryFilter.value);
    if (_cnt) params.set('country', _cnt);
    const _lid = joinArr(labelFilter.value);
    if (_lid) params.set('label_id', _lid);
    if (dateFrom.value) params.set('date_from', dateFrom.value);
    if (dateTo.value) params.set('date_to', dateTo.value);
    if (sortField.value) params.set('sort', sortField.value);
    if (sortDir.value) params.set('direction', sortDir.value);
    return route('contacts.page') + '?' + params.toString();
}

async function loadMore() {
    if (loadingMore.value || !nextPageUrl.value) return;
    loadingMore.value = true;
    const url = buildPageUrl(currentPage.value + 1);
    try {
        const response = await axios.get(url);
        const page = response.data;
        allContacts.value.push(...page.data);
        currentPage.value = page.current_page;
        nextPageUrl.value = page.current_page < page.last_page ? 'pending' : null;
        totalContacts.value = page.total;
    } catch (e) {
        console.error('Failed to load more contacts', e);
    } finally {
        loadingMore.value = false;
    }
}

function setupObserver() {
    if (observer) observer.disconnect();
    observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && nextPageUrl.value && !loadingMore.value) {
            loadMore();
        }
    }, { rootMargin: '200px' });

    if (sentinel.value) {
        observer.observe(sentinel.value);
    }
}

onMounted(() => {
    nextTick(() => setupObserver());
    document.addEventListener('mouseup', onMouseUp);
});

watch(sentinel, (el) => {
    if (el && observer) observer.observe(el);
});

onUnmounted(() => {
    if (observer) observer.disconnect();
    document.removeEventListener('mouseup', onMouseUp);
});

function bulkUpdateStatus() {
    if (!selectedIds.value.length || !bulkStatusId.value) return;
    router.post(route('contacts.bulk-status'), {
        ids: selectedIds.value,
        status_id: bulkStatusId.value,
    }, {
        onSuccess: () => {
            selectedIds.value = [];
            bulkStatusId.value = '';
            loadRecentOps();
        },
    });
}

function bulkUpdateDate() {
    if (!selectedIds.value.length) return;
    router.post(route('contacts.bulk-date'), {
        ids: selectedIds.value,
        date: bulkDateValue.value || null,
    }, {
        onSuccess: () => {
            selectedIds.value = [];
            bulkDateValue.value = '';
            loadRecentOps();
        },
    });
}

const showBulkDeleteModal = ref(false);

function confirmBulkDelete() {
    if (!selectedIds.value.length) return;
    showBulkDeleteModal.value = true;
}

function bulkDelete() {
    router.post(route('contacts.bulk-delete'), {
        ids: selectedIds.value,
    }, {
        onSuccess: () => {
            showBulkDeleteModal.value = false;
            selectedIds.value = [];
            loadRecentOps();
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
            loadRecentOps();
        },
    });
}

const bulkApplyHasAnything = computed(() =>
    !!bulkStatusId.value || !!bulkDateValue.value || !!bulkLabelId.value
);

function bulkApply() {
    if (!selectedIds.value.length || !bulkApplyHasAnything.value) return;
    const payload = { ids: selectedIds.value };
    if (bulkStatusId.value) payload.status_id = bulkStatusId.value;
    if (bulkDateValue.value) payload.date = bulkDateValue.value;
    if (bulkLabelId.value) {
        payload.label_ids = [parseInt(bulkLabelId.value)];
        payload.label_action = bulkLabelAction.value;
    }
    router.post(route('contacts.bulk-apply'), payload, {
        onSuccess: () => {
            selectedIds.value = [];
            bulkStatusId.value = '';
            bulkDateValue.value = '';
            bulkLabelId.value = '';
            loadRecentOps();
        },
    });
}

const copySuccess = ref(false);
const copiedCount = ref(0);

async function copySelectedPhones() {
    const lines = allContacts.value
        .filter(c => selectedIds.value.includes(c.id))
        .map(c => {
            const name = c.name ? c.name.trim() : '';
            return name ? `${name}, ${c.phone}` : `, ${c.phone}`;
        })
        .join('\n');
    await navigator.clipboard.writeText(lines);
    copiedCount.value = selectedIds.value.length;
    copySuccess.value = true;
    setTimeout(() => { copySuccess.value = false; }, 2000);
}

function exportSelectedCsv() {
    const selected = allContacts.value.filter(c => selectedIds.value.includes(c.id));
    const header = 'Nombre,Telefono,Estado,Pais';
    const rows = selected.map(c => {
        const name = (c.name || '').replace(/"/g, '""');
        const phone = (c.phone || '');
        const status = (c.status?.name || '').replace(/"/g, '""');
        const country = (c.country || '').replace(/"/g, '""');
        return `"${name}","${phone}","${status}","${country}"`;
    });
    const csv = [header, ...rows].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `contactos-seleccionados-${selected.length}.csv`;
    a.click();
    URL.revokeObjectURL(url);
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
    const sid = joinArr(statusFilter.value);
    if (sid) params.set('status_id', sid);
    const cnt = joinArr(countryFilter.value);
    if (cnt) params.set('country', cnt);
    window.location.href = route('contacts.export') + '?' + params.toString();
}

async function submitQuickAdd() {
    if (!quickAddPhones.value.trim()) return;
    quickAddLoading.value = true;
    quickAddResult.value = null;
    try {
        const response = await axios.post(route('contacts.quick-add'), {
            phones: quickAddPhones.value,
            status_id: quickAddStatusId.value || null,
            date: quickAddDate.value || null,
            label_ids: quickAddLabelIds.value,
            source: quickAddSource.value || null,
            mode: quickAddMode.value,
        });
        quickAddResult.value = response.data;
        quickAddPhones.value = '';
        // Reload contacts list
        router.reload({ only: ['contacts'] });
        loadRecentOps();
    } catch (e) {
        console.error('Quick add failed', e);
    } finally {
        quickAddLoading.value = false;
    }
}

// Undo system
const recentOps = ref([]);
const showOpsMenu = ref(false);
const undoingId = ref(null);

async function loadRecentOps() {
    try {
        const r = await axios.get(route('contacts.recent-operations'));
        recentOps.value = r.data || [];
    } catch (_) {}
}

async function undoOp(op) {
    if (!confirm(t('contacts.confirmUndo', { description: op.description }))) return;
    undoingId.value = op.id;
    try {
        await axios.post(route('contacts.undo-operation', op.id));
        await loadRecentOps();
        router.reload({ only: ['contacts'] });
    } catch (e) {
        console.error('Undo failed', e);
    } finally {
        undoingId.value = null;
    }
}

function formatOpTime(t) {
    if (!t) return '';
    const d = new Date(t);
    const diffMin = Math.round((Date.now() - d.getTime()) / 60000);
    if (diffMin < 1) return 'ahora';
    if (diffMin < 60) return `hace ${diffMin} min`;
    const h = Math.round(diffMin / 60);
    if (h < 24) return `hace ${h} h`;
    return d.toLocaleDateString();
}

onMounted(() => { loadRecentOps(); });

// Refresh recent ops whenever bulk buttons succeed. Simpler: poll every 30s.
let opsInterval = null;
onMounted(() => {
    opsInterval = setInterval(loadRecentOps, 30000);
});
onUnmounted(() => { if (opsInterval) clearInterval(opsInterval); });
</script>

<template>
    <Head :title="t('contacts.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-900">{{ t('contacts.title') }}</h2>
                <div class="flex items-center gap-2">
                    <!-- Undo dropdown -->
                    <div v-if="recentOps.length" class="relative">
                        <button @click="showOpsMenu = !showOpsMenu" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                            <svg class="h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                            </svg>
                            {{ t('contacts.undo') }}
                            <span class="inline-flex items-center justify-center rounded-full bg-brand-50 text-brand-700 px-1.5 text-xs font-bold">{{ recentOps.length }}</span>
                        </button>
                        <div v-if="showOpsMenu" @click.self="showOpsMenu = false" class="fixed inset-0 z-40" />
                        <div v-if="showOpsMenu" class="absolute right-0 mt-1 z-50 w-96 rounded-lg bg-white shadow-xl ring-1 ring-slate-900/10 overflow-hidden">
                            <div class="px-4 py-2 border-b border-slate-100 text-xs font-medium text-slate-500 uppercase">{{ t('contacts.recentOperations') }}</div>
                            <div class="max-h-80 overflow-y-auto divide-y divide-slate-50">
                                <div v-for="op in recentOps" :key="op.id" class="px-4 py-3 hover:bg-slate-50 flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-slate-900 truncate">{{ op.description }}</p>
                                        <p class="text-xs text-slate-400 mt-0.5">{{ formatOpTime(op.created_at) }}</p>
                                    </div>
                                    <button @click="undoOp(op)" :disabled="undoingId === op.id" class="shrink-0 rounded-md bg-red-50 border border-red-200 text-red-700 px-2.5 py-1 text-xs font-semibold hover:bg-red-100 disabled:opacity-50 transition">
                                        {{ undoingId === op.id ? t('common.loading') : t('contacts.undoAction') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button v-if="can('import.manage')" @click="showQuickAdd = true" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500 transition">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        {{ t('contacts.quickAdd') }}
                    </button>
                    <button v-if="can('contacts.export')" @click="exportCsv" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                        <svg class="h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        {{ t('contacts.export') }}
                    </button>
                </div>
            </div>
        </template>

        <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 overflow-hidden">
            <!-- Filters -->
            <div class="border-b border-slate-100 p-4">
                <div class="flex flex-wrap gap-3 items-center">
                    <div class="w-full sm:w-64">
                        <SearchInput v-model="search" :placeholder="t('contacts.search')" />
                    </div>
                    <MultiSelectDropdown v-model="statusFilter" :options="statusOptions" :placeholder="t('contacts.allStatuses')" />
                    <MultiSelectDropdown v-model="countryFilter" :options="countryOptions" :placeholder="t('contacts.allCountries')" />
                    <MultiSelectDropdown v-model="labelFilter" :options="labelOptions" :placeholder="t('labels.allLabels')" />
                    <div class="flex items-center gap-1.5">
                        <input type="date" v-model="dateFrom" class="rounded-lg border-slate-300 text-sm bg-white focus:border-brand-500 focus:ring-brand-500 w-36" :title="t('contacts.dateFrom')" />
                        <span class="text-slate-400 text-xs">-</span>
                        <input type="date" v-model="dateTo" class="rounded-lg border-slate-300 text-sm bg-white focus:border-brand-500 focus:ring-brand-500 w-36" :title="t('contacts.dateTo')" />
                    </div>
                    <span class="text-sm text-slate-500 ml-auto">{{ t('common.total') }}: {{ totalContacts.toLocaleString() }}</span>
                </div>

                <!-- Bulk actions bar -->
                <div v-if="selectedIds.length && can('contacts.bulk_status')" class="mt-3 flex flex-wrap items-center gap-3 rounded-lg bg-brand-50 border border-brand-200 px-4 py-3">
                    <span class="text-sm font-bold text-brand-700">{{ t('contacts.selected', { count: selectedIds.length }) }}</span>

                    <!-- Deselect all -->
                    <button @click="selectedIds = []" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 transition">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                        {{ t('contacts.deselectAll') }}
                    </button>

                    <div class="h-5 w-px bg-brand-200"></div>

                    <!-- Copy phones -->
                    <button @click="copySelectedPhones" class="inline-flex items-center gap-1.5 rounded-lg border border-brand-300 bg-white px-3 py-1.5 text-sm font-semibold text-brand-700 hover:bg-brand-50 transition">
                        <svg v-if="!copySuccess" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                        </svg>
                        <svg v-else class="h-4 w-4 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        {{ copySuccess ? t('contacts.copied', { count: copiedCount }) : t('contacts.copyPhones') }}
                    </button>

                    <!-- Export selected CSV -->
                    <button @click="exportSelectedCsv" class="inline-flex items-center gap-1.5 rounded-lg border border-brand-300 bg-white px-3 py-1.5 text-sm font-semibold text-brand-700 hover:bg-brand-50 transition">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        {{ t('contacts.exportSelected') }}
                    </button>

                    <div class="h-5 w-px bg-brand-200"></div>

                    <!-- Bulk status -->
                    <div class="flex items-center gap-2">
                        <select v-model="bulkStatusId" class="rounded-lg border-slate-300 text-sm bg-white focus:border-brand-500 focus:ring-brand-500">
                            <option value="">{{ t('contacts.bulkStatus') }}</option>
                            <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>

                    <!-- Bulk date -->
                    <div class="flex items-center gap-2">
                        <input type="date" v-model="bulkDateValue" class="rounded-lg border-slate-300 text-sm bg-white focus:border-brand-500 focus:ring-brand-500 w-36" :title="t('contacts.bulkDate')" />
                    </div>

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
                    </div>

                    <!-- Unified Apply button -->
                    <button @click="bulkApply" :disabled="!bulkApplyHasAnything" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50 transition">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        {{ t('contacts.applyAll') }}
                    </button>

                    <div v-if="can('contacts.delete')" class="h-5 w-px bg-brand-200"></div>

                    <!-- Bulk delete -->
                    <button v-if="can('contacts.delete')" @click="confirmBulkDelete" class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-white px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-50 transition">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        {{ t('contacts.deleteSelected') }}
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-slate-100">
                            <th v-if="can('contacts.bulk_status')" class="w-10 px-4 py-3">
                                <input type="checkbox" v-model="selectAll" class="h-5 w-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500 cursor-pointer" />
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
                            <th @click="toggleSort('date')" class="cursor-pointer px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hover:text-slate-700 transition">
                                {{ t('contacts.date') }}{{ sortIcon('date') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('contacts.status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('labels.title') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('contacts.source') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('contacts.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="contact in allContacts" :key="contact.id"
                            @mousedown="onRowMousedown($event, contact.id)"
                            @mouseenter="onRowMouseenter(contact.id)"
                            :class="[
                                'transition select-none',
                                selectedIds.includes(contact.id) ? 'bg-brand-50/60' : 'hover:bg-slate-50/50'
                            ]">
                            <td v-if="can('contacts.bulk_status')" class="px-4 py-3">
                                <input type="checkbox" :value="contact.id" v-model="selectedIds" class="h-5 w-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500 cursor-pointer" />
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-900 whitespace-nowrap font-mono">{{ contact.phone }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ contact.name || '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ contact.country || '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500 whitespace-nowrap">{{ formatContactDate(contact.date) }}</td>
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
            <div v-if="!allContacts.length" class="px-6 py-12 text-center">
                <svg class="mx-auto h-10 w-10 text-slate-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
                <p class="mt-2 text-sm text-slate-500">{{ t('contacts.noContacts') }}</p>
            </div>

            <!-- Infinite scroll sentinel + loading indicator -->
            <div v-if="allContacts.length" class="border-t border-slate-100 px-4 py-3 flex items-center justify-between">
                <p class="text-sm text-slate-500">
                    {{ t('common.showing', { from: 1, to: allContacts.length, total: totalContacts }) }}
                </p>
            </div>
            <div ref="sentinel" class="h-1"></div>
            <div v-if="loadingMore" class="flex justify-center py-4">
                <svg class="animate-spin h-6 w-6 text-brand-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>

        <ConfirmModal
            :show="showDeleteModal"
            :title="t('contacts.delete')"
            :message="t('contacts.confirmDelete')"
            @confirm="deleteContact"
            @cancel="showDeleteModal = false"
        />

        <ConfirmModal
            :show="showBulkDeleteModal"
            :title="t('contacts.deleteSelected')"
            :message="t('contacts.confirmBulkDelete', { count: selectedIds.length })"
            @confirm="bulkDelete"
            @cancel="showBulkDeleteModal = false"
        />

        <!-- Quick Add Modal -->
        <teleport to="body">
            <transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showQuickAdd" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4" @click.self="showQuickAdd = false">
                    <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                        <div class="shrink-0 border-b border-slate-100 px-6 py-4 flex items-center justify-between">
                            <h3 class="text-lg font-bold text-slate-900">{{ t('contacts.quickAdd') }}</h3>
                            <button @click="showQuickAdd = false" class="text-slate-400 hover:text-slate-600 transition">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('contacts.quickAddPhones') }}</label>
                                <textarea
                                    v-model="quickAddPhones"
                                    rows="8"
                                    class="block w-full rounded-lg border-slate-300 bg-slate-50 text-sm font-mono shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500 transition"
                                    :placeholder="t('contacts.quickAddPlaceholder')"
                                ></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('contacts.status') }}</label>
                                    <select v-model="quickAddStatusId" class="block w-full rounded-lg border-slate-300 text-sm bg-white focus:border-brand-500 focus:ring-brand-500">
                                        <option value="">-</option>
                                        <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('contacts.date') }}</label>
                                    <input type="date" v-model="quickAddDate" class="block w-full rounded-lg border-slate-300 text-sm bg-white focus:border-brand-500 focus:ring-brand-500" />
                                </div>
                            </div>

                            <!-- Labels (Etiquetas) -->
                            <div v-if="labels?.length">
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('labels.title') }}</label>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="l in labels"
                                        :key="l.id"
                                        type="button"
                                        @click="toggleQuickAddLabel(l.id)"
                                        :class="[
                                            'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium border-2 transition cursor-pointer',
                                            quickAddLabelIds.includes(l.id) ? 'border-current shadow-sm' : 'border-transparent opacity-50 hover:opacity-75'
                                        ]"
                                        :style="{ backgroundColor: l.color + '20', color: l.color }"
                                    >
                                        <svg v-if="quickAddLabelIds.includes(l.id)" class="h-3 w-3 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        {{ l.name }}
                                    </button>
                                </div>
                            </div>

                            <!-- Source (Fuente) -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('contacts.source') }}</label>
                                <input type="text" v-model="quickAddSource" maxlength="100" class="block w-full rounded-lg border-slate-300 text-sm bg-white focus:border-brand-500 focus:ring-brand-500" :placeholder="t('contacts.sourcePlaceholder')" />
                            </div>

                            <!-- Mode selector -->
                            <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3">
                                <p class="text-sm font-medium text-slate-900 mb-2">{{ t('contacts.modeTitle') }}</p>
                                <div class="space-y-2">
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input type="radio" v-model="quickAddMode" value="only_new" class="mt-1 h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500" />
                                        <div>
                                            <p class="text-sm font-medium text-slate-800">{{ t('contacts.modeOnlyNew') }}</p>
                                            <p class="text-xs text-slate-600">{{ t('contacts.modeOnlyNewHint') }}</p>
                                        </div>
                                    </label>
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input type="radio" v-model="quickAddMode" value="fill_empty" class="mt-1 h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500" />
                                        <div>
                                            <p class="text-sm font-medium text-slate-800">{{ t('contacts.modeFillEmpty') }}</p>
                                            <p class="text-xs text-slate-600">{{ t('contacts.modeFillEmptyHint') }}</p>
                                        </div>
                                    </label>
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input type="radio" v-model="quickAddMode" value="overwrite" class="mt-1 h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500" />
                                        <div>
                                            <p class="text-sm font-medium text-slate-800">{{ t('contacts.modeOverwrite') }}</p>
                                            <p class="text-xs text-slate-600">{{ t('contacts.modeOverwriteHint') }}</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Result feedback -->
                            <div v-if="quickAddResult" class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm">
                                <p class="font-medium text-green-800">{{ t('contacts.quickAddDone') }}</p>
                                <p class="text-green-700 mt-1">
                                    {{ t('contacts.quickAddCreated', { count: quickAddResult.created }) }},
                                    {{ t('contacts.quickAddUpdated', { count: quickAddResult.updated }) }},
                                    {{ t('contacts.quickAddSkipped', { count: quickAddResult.skipped }) }}
                                </p>
                            </div>
                        </div>
                        <div class="shrink-0 border-t border-slate-100 px-6 py-4 flex justify-end gap-3 bg-white">
                            <button @click="showQuickAdd = false" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 transition">
                                {{ t('common.close') }}
                            </button>
                            <button @click="submitQuickAdd" :disabled="quickAddLoading || !quickAddPhones.trim()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50 transition">
                                {{ quickAddLoading ? t('common.loading') : t('contacts.quickAddSubmit') }}
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </teleport>
    </AuthenticatedLayout>
</template>
