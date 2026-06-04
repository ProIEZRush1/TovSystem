<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import axios from 'axios';
import * as XLSX from 'xlsx';

const { t } = useI18n();
const props = defineProps({ account: Object, templates: Array, statuses: Array, countries: Array, labels: Array, totalContacts: Number, health: Object });

const quality = computed(() => (props.health?.quality_rating || '').toUpperCase());
const qualityWarn = computed(() => ['YELLOW', 'RED'].includes(quality.value));

const audienceSource = ref('contacts');
const audienceFile = ref(null);
const pastePhones = ref('');

// Excel preview state
const excelRows = ref([]);
const excelSelected = ref(new Set());
const excelFilter = ref('');
const excelCountryFilter = ref('');
const excelParsing = ref(false);
const excelPage = ref(1);
const excelPerPage = 100;

const excelCountries = computed(() => {
    const set = new Set();
    excelRows.value.forEach(r => { if (r.country) set.add(r.country); });
    return [...set].sort();
});

const filteredExcelRows = computed(() => {
    let rows = excelRows.value;
    if (excelCountryFilter.value) {
        rows = rows.filter(r => r.country === excelCountryFilter.value);
    }
    if (excelFilter.value) {
        const q = excelFilter.value.toLowerCase();
        rows = rows.filter(r => (r.phone || '').toLowerCase().includes(q) || (r.name || '').toLowerCase().includes(q));
    }
    return rows;
});

const excelTotalPages = computed(() => Math.ceil(filteredExcelRows.value.length / excelPerPage));
const paginatedExcelRows = computed(() => {
    const start = (excelPage.value - 1) * excelPerPage;
    return filteredExcelRows.value.slice(start, start + excelPerPage);
});

const allFilteredSelected = computed(() => {
    return filteredExcelRows.value.length > 0 && filteredExcelRows.value.every(r => excelSelected.value.has(r.idx));
});

function toggleSelectAll() {
    if (allFilteredSelected.value) {
        filteredExcelRows.value.forEach(r => excelSelected.value.delete(r.idx));
    } else {
        filteredExcelRows.value.forEach(r => excelSelected.value.add(r.idx));
    }
    excelSelected.value = new Set(excelSelected.value);
}

function selectOnlyFiltered() {
    excelSelected.value = new Set(filteredExcelRows.value.map(r => r.idx));
}

function toggleRow(idx) {
    if (excelSelected.value.has(idx)) {
        excelSelected.value.delete(idx);
    } else {
        excelSelected.value.add(idx);
    }
    excelSelected.value = new Set(excelSelected.value);
}

let filterTimer = null;
function onFilterInput(e) {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => { excelFilter.value = e.target.value; excelPage.value = 1; }, 200);
}

function normalizePhone(raw) {
    if (!raw) return null;
    let phone = String(raw).replace(/[\s\-()]/g, '');
    if (!phone.startsWith('+')) phone = '+' + phone;
    const digits = phone.replace(/[^0-9]/g, '');
    if (digits.length < 7) return null;
    if (digits.length === 10) return '+521' + digits;
    if (digits.length === 12 && digits.startsWith('52') && !digits.startsWith('521')) {
        return '+521' + digits.slice(2);
    }
    return '+' + digits;
}

function parseExcelFile(file) {
    excelParsing.value = true;
    const reader = new FileReader();
    reader.onload = (e) => {
        const wb = XLSX.read(e.target.result, { type: 'array' });
        const ws = wb.Sheets[wb.SheetNames[0]];
        const json = XLSX.utils.sheet_to_json(ws, { defval: '' });

        const phoneKeys = ['telefono', 'teléfono', 'phone', 'tel', 'celular', 'numero'];
        const nameKeys = ['nombre', 'name', 'contacto', 'contact'];
        const countryKeys = ['pais', 'país', 'country'];
        const statusKeys = ['estado', 'status'];
        const headers = Object.keys(json[0] || {});

        let phoneCol = headers.find(h => phoneKeys.includes(h.toLowerCase()));
        let nameCol = headers.find(h => nameKeys.includes(h.toLowerCase()));
        let countryCol = headers.find(h => countryKeys.includes(h.toLowerCase()));
        let statusCol = headers.find(h => statusKeys.includes(h.toLowerCase()));

        if (!phoneCol) {
            phoneCol = headers.find(h => {
                const sample = String(json[0]?.[h] || '');
                return /^\+?\d{7,}$/.test(sample.replace(/[\s\-()]/g, ''));
            });
        }

        if (!phoneCol) {
            excelRows.value = [];
            excelParsing.value = false;
            return;
        }

        const seen = new Set();
        const rows = [];
        json.forEach((row, i) => {
            const rawPhone = String(row[phoneCol] || '').trim();
            const phone = normalizePhone(rawPhone);
            if (!phone || seen.has(phone)) return;
            seen.add(phone);
            rows.push({
                idx: i,
                phone,
                name: nameCol ? String(row[nameCol] || '').trim() : '',
                country: countryCol ? String(row[countryCol] || '').trim() : '',
                status: statusCol ? String(row[statusCol] || '').trim() : '',
            });
        });

        excelRows.value = rows;
        excelSelected.value = new Set(rows.map(r => r.idx));
        excelParsing.value = false;
    };
    reader.readAsArrayBuffer(file);
}

const form = useForm({
    name: '',
    template_name: '',
    template_language: '',
    template_components: [],
    audience_source: 'contacts',
    audience_file: null,
    audience_rows: null,
    audience_filters: {
        status_ids: [],
        countries: [],
        label_ids: [],
        date_from: '',
        date_to: '',
        search: '',
    },
});

// Live contact count for "Contactos del sistema"
const contactCount = ref(props.totalContacts ?? 0);
const countLoading = ref(false);
let countTimer = null;

function hasAnyFilter() {
    const f = form.audience_filters;
    return f.status_ids.length || f.countries.length || f.label_ids.length || f.date_from || f.date_to || f.search;
}

function fetchContactCount() {
    clearTimeout(countTimer);
    countTimer = setTimeout(async () => {
        countLoading.value = true;
        try {
            const f = form.audience_filters;
            const query = new URLSearchParams();
            f.status_ids.forEach(v => query.append('status_ids[]', v));
            f.countries.forEach(v => query.append('countries[]', v));
            f.label_ids.forEach(v => query.append('label_ids[]', v));
            if (f.date_from) query.append('date_from', f.date_from);
            if (f.date_to) query.append('date_to', f.date_to);
            if (f.search) query.append('search', f.search);

            const r = await axios.get(route('whatsapp.campaigns.contact-count', props.account.id) + '?' + query.toString());
            contactCount.value = r.data.count;
        } catch (e) {
            // silently ignore
        } finally {
            countLoading.value = false;
        }
    }, 300);
}

watch(
    () => [
        form.audience_filters.status_ids,
        form.audience_filters.countries,
        form.audience_filters.label_ids,
        form.audience_filters.date_from,
        form.audience_filters.date_to,
        form.audience_filters.search,
    ],
    () => { if (audienceSource.value === 'contacts') fetchContactCount(); },
    { deep: true }
);

const templateParams = ref({});
const paramSources = ref({});

const selectedTemplate = computed(() =>
    props.templates.find(t => t.name === form.template_name)
);

const bodyText = computed(() => {
    if (!selectedTemplate.value) return '';
    const body = (selectedTemplate.value.components || []).find(c => c.type === 'BODY');
    return body?.text || '';
});

const detectedParams = computed(() => {
    const matches = bodyText.value.match(/\{\{([a-z0-9_]+)\}\}/gi) || [];
    return [...new Set(matches.map(m => m.replace(/[{}]/g, '')))];
});

// Detect if template has IMAGE or VIDEO header
const headerType = computed(() => {
    if (!selectedTemplate.value) return null;
    const header = (selectedTemplate.value.components || []).find(c => c.type === 'HEADER');
    if (!header) return null;
    return header.format; // TEXT, IMAGE, VIDEO
});

const needsMediaUpload = computed(() => ['IMAGE', 'VIDEO'].includes(headerType.value));
const mediaReady = computed(() => !needsMediaUpload.value || !!uploadedMediaId.value);

const mediaFile = ref(null);
const mediaPreview = ref(null);
const uploadingMedia = ref(false);
const uploadedMediaId = ref(null);
const uploadError = ref('');

function onMediaSelected(e) {
    const file = e.target.files[0];
    if (!file) return;
    mediaFile.value = file;
    mediaPreview.value = URL.createObjectURL(file);
    uploadedMediaId.value = null;
    uploadError.value = '';
}

async function uploadMediaToMeta() {
    if (!mediaFile.value) return;
    uploadingMedia.value = true;
    uploadError.value = '';
    try {
        const formData = new FormData();
        formData.append('file', mediaFile.value);
        const r = await axios.post(route('whatsapp.upload-media', props.account.id), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        uploadedMediaId.value = r.data.media_id;
    } catch (e) {
        uploadError.value = 'Error al subir: ' + (e.response?.data?.error || e.message);
    } finally {
        uploadingMedia.value = false;
    }
}

const hasFlowButton = computed(() => {
    if (!selectedTemplate.value) return false;
    const btn = (selectedTemplate.value.components || []).find(c => c.type === 'BUTTONS');
    return !!(btn?.buttons || []).find(b => b.type === 'FLOW');
});

function onTemplateSelect() {
    const tpl = selectedTemplate.value;
    if (tpl) {
        form.template_language = tpl.language;
        templateParams.value = {};
        paramSources.value = {};
        detectedParams.value.forEach(p => {
            paramSources.value[p] = 'contact_name';
            templateParams.value[p] = '';
        });
    }
}

function buildComponents() {
    const components = [];
    // Media header (image/video)
    if (needsMediaUpload.value && uploadedMediaId.value) {
        const mediaType = headerType.value === 'VIDEO' ? 'video' : 'image';
        components.push({
            type: 'header',
            parameters: [{ type: mediaType, [mediaType]: { id: uploadedMediaId.value } }],
        });
    }
    if (detectedParams.value.length) {
        components.push({
            type: 'body',
            parameters: detectedParams.value.map(p => {
                const source = paramSources.value[p] || 'contact_name';
                if (source === 'fixed') {
                    return { type: 'text', parameter_name: p, text: templateParams.value[p] || '' };
                }
                return { type: 'text', parameter_name: p, text: '{{' + source + '}}' };
            }),
        });
    }
    if (hasFlowButton.value) {
        components.push({
            type: 'button',
            sub_type: 'flow',
            index: '0',
            parameters: [{ type: 'action', action: { flow_token: 'tov_campaign_' + Date.now() } }],
        });
    }
    return components;
}

function onFileSelected(e) {
    const file = e.target.files[0];
    if (file) {
        audienceFile.value = file;
        form.audience_file = file;
        parseExcelFile(file);
    }
}

function parsePastedPhones() {
    const seen = new Set();
    return pastePhones.value.split('\n')
        .map(line => {
            const trimmed = line.trim();
            if (!trimmed) return null;
            const parts = trimmed.split(/[,\t]+/);
            let phone = null, name = null;
            for (const p of parts) {
                const cleaned = p.trim().replace(/[\s\-()]/g, '');
                if (/^\+?\d{7,}$/.test(cleaned)) {
                    phone = normalizePhone(cleaned);
                } else if (p.trim().length > 1 && !phone) {
                    name = p.trim();
                }
            }
            if (!phone) {
                const match = trimmed.match(/\+?\d{7,}/);
                if (match) phone = normalizePhone(match[0]);
            }
            if (!phone || seen.has(phone)) return null;
            seen.add(phone);
            return { phone, name: name || '' };
        })
        .filter(Boolean);
}

const pasteStats = computed(() => {
    const raw = pastePhones.value.split('\n').map(l => l.trim()).filter(Boolean).length;
    const unique = parsePastedPhones().length;
    return { raw, unique, ignored: Math.max(0, raw - unique) };
});

function clearPaste() {
    pastePhones.value = '';
}

function submit() {
    if (!mediaReady.value) {
        uploadError.value = 'Debes subir y confirmar la ' + (headerType.value === 'VIDEO' ? 'video' : 'imagen') + ' de encabezado antes de crear la campana.';
        return;
    }
    form.template_components = buildComponents();
    form.audience_source = audienceSource.value;
    if (audienceSource.value === 'file' && excelRows.value.length > 0) {
        const selected = excelRows.value.filter(r => excelSelected.value.has(r.idx));
        form.audience_rows = selected.map(r => ({ phone: r.phone, name: r.name }));
        form.audience_file = null;
    } else if (audienceSource.value === 'paste') {
        form.audience_rows = parsePastedPhones();
        form.audience_source = 'file';
        form.audience_file = null;
    }
    form.post(route('whatsapp.campaigns.store', props.account.id), {
        forceFormData: true,
    });
}

function paramLabel(p) { return '{{' + p + '}}'; }
</script>

<template>
    <Head title="Nueva Campana" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('whatsapp.campaigns.index', account.id)" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                </Link>
                <h2 class="text-xl font-bold text-slate-900">Nueva Campana — {{ account.name }}</h2>
            </div>
        </template>

        <div class="max-w-4xl">
            <!-- Delivery health warning -->
            <div v-if="qualityWarn" :class="['mb-6 rounded-xl border p-4', quality === 'RED' ? 'bg-red-50 border-red-300' : 'bg-amber-50 border-amber-300']">
                <div class="flex items-start gap-3">
                    <svg class="h-5 w-5 shrink-0 mt-0.5" :class="quality === 'RED' ? 'text-red-600' : 'text-amber-600'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                    <div class="text-sm">
                        <p class="font-bold" :class="quality === 'RED' ? 'text-red-800' : 'text-amber-800'">
                            Calidad del numero: {{ quality === 'RED' ? 'ROJA' : 'AMARILLA' }} — WhatsApp esta limitando las entregas
                        </p>
                        <p class="mt-1" :class="quality === 'RED' ? 'text-red-700' : 'text-amber-700'">
                            Cuando se envia el mismo mensaje de marketing a las mismas personas muchas veces, WhatsApp baja la calidad del numero y <strong>deja de entregar</strong> los mensajes (aunque digan "enviado"). Por eso pueden aparecer 0 entregados y 0 respuestas.
                        </p>
                        <ul class="mt-2 list-disc list-inside" :class="quality === 'RED' ? 'text-red-700' : 'text-amber-700'">
                            <li>No reenvies a los mismos contactos seguido — deja pasar varios dias.</li>
                            <li>Envia a menos personas por dia para que la calidad se recupere.</li>
                            <li>Evita mandar a numeros que no responden o que ya recibieron el mensaje.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Step 1: Name + Template -->
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5 space-y-4">
                    <h3 class="text-base font-semibold text-slate-900">1. Campana y Plantilla</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre de la campana</label>
                            <input v-model="form.name" type="text" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Envio Rifa Mayo 2026" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Plantilla aprobada</label>
                            <select v-model="form.template_name" @change="onTemplateSelect" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">-- Selecciona --</option>
                                <option v-for="tpl in templates" :key="tpl.id" :value="tpl.name">{{ tpl.name }} ({{ tpl.language }}) — {{ tpl.category }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Template preview -->
                    <div v-if="selectedTemplate" class="rounded-lg bg-green-50 border border-green-200 p-4">
                        <p class="text-xs font-medium text-green-700 mb-1">Vista previa del cuerpo:</p>
                        <p class="text-sm text-green-900 whitespace-pre-wrap">{{ bodyText }}</p>
                    </div>

                    <!-- Media upload for IMAGE/VIDEO headers -->
                    <div v-if="needsMediaUpload" class="rounded-lg bg-blue-50 border border-blue-200 p-4 space-y-3">
                        <p class="text-sm font-medium text-blue-800">
                            Este template requiere {{ headerType === 'VIDEO' ? 'un video' : 'una imagen' }} de encabezado
                        </p>
                        <input type="file" :accept="headerType === 'VIDEO' ? 'video/mp4,video/3gpp' : 'image/jpeg,image/png'" @change="onMediaSelected" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100" />
                        <!-- Preview -->
                        <div v-if="mediaPreview">
                            <video v-if="headerType === 'VIDEO'" :src="mediaPreview" controls class="max-h-32 rounded-lg" />
                            <img v-else :src="mediaPreview" class="max-h-32 rounded-lg" />
                        </div>
                        <!-- Upload button -->
                        <button v-if="mediaFile && !uploadedMediaId" @click="uploadMediaToMeta" :disabled="uploadingMedia" type="button" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50 transition">
                            {{ uploadingMedia ? 'Subiendo...' : 'Subir a WhatsApp' }}
                        </button>
                        <p v-if="uploadedMediaId" class="text-sm text-green-700 font-medium">Subido (ID: {{ uploadedMediaId }})</p>
                        <p v-if="!uploadedMediaId" class="text-sm text-amber-700 font-semibold">Sube y confirma el {{ headerType === 'VIDEO' ? 'video' : 'imagen' }} antes de crear la campana — sin esto los mensajes fallan.</p>
                        <p v-if="uploadError" class="text-sm text-red-700">{{ uploadError }}</p>
                    </div>

                    <!-- Variable inputs with dynamic source selector -->
                    <div v-if="detectedParams.length" class="space-y-3">
                        <p class="text-sm font-semibold text-slate-700">Variables del template:</p>
                        <div v-for="p in detectedParams" :key="p" class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-brand-100 text-brand-700 px-2.5 py-0.5 text-xs font-mono font-medium shrink-0">{{ paramLabel(p) }}</span>
                                <span class="text-xs text-slate-500">se reemplaza con:</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <select v-model="paramSources[p]" class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 w-56 shrink-0">
                                    <option value="contact_name">Nombre del contacto</option>
                                    <option value="phone">Telefono del contacto</option>
                                    <option value="country">Pais del contacto</option>
                                    <option value="status">Estado del contacto</option>
                                    <option value="fixed">Texto fijo (igual para todos)</option>
                                </select>
                                <input v-if="paramSources[p] === 'fixed'" v-model="templateParams[p]" type="text" class="flex-1 rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Escribe el texto fijo..." />
                                <span v-else class="text-xs text-slate-400 italic">Se toma automaticamente de cada contacto</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Audience -->
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5 space-y-4">
                    <h3 class="text-base font-semibold text-slate-900">2. Audiencia</h3>

                    <!-- Source selector -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label :class="['cursor-pointer rounded-lg border-2 p-4 text-center transition', audienceSource === 'contacts' ? 'border-brand-500 bg-brand-50' : 'border-slate-200 hover:border-slate-300']" @click="audienceSource = 'contacts'">
                            <svg class="h-6 w-6 mx-auto mb-1" :class="audienceSource === 'contacts' ? 'text-brand-600' : 'text-slate-400'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                            <p class="text-sm font-semibold" :class="audienceSource === 'contacts' ? 'text-brand-700' : 'text-slate-700'">Contactos del sistema</p>
                            <p class="text-xs text-slate-500 mt-1">Filtra por estado, pais, etiqueta</p>
                        </label>
                        <label :class="['cursor-pointer rounded-lg border-2 p-4 text-center transition', audienceSource === 'file' ? 'border-brand-500 bg-brand-50' : 'border-slate-200 hover:border-slate-300']" @click="audienceSource = 'file'">
                            <svg class="h-6 w-6 mx-auto mb-1" :class="audienceSource === 'file' ? 'text-brand-600' : 'text-slate-400'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12H9.75m3 0H9.75m0 0H7.5m2.25 0v3m0-3v-3m9-1.5V18a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18V6.75A2.25 2.25 0 0 1 5.25 4.5h6.879a2.25 2.25 0 0 1 1.591.659l4.621 4.621c.36.36.56.849.56 1.357Z" /></svg>
                            <p class="text-sm font-semibold" :class="audienceSource === 'file' ? 'text-brand-700' : 'text-slate-700'">Subir Excel/CSV</p>
                            <p class="text-xs text-slate-500 mt-1">Sube archivo con telefonos</p>
                        </label>
                        <label :class="['cursor-pointer rounded-lg border-2 p-4 text-center transition', audienceSource === 'paste' ? 'border-brand-500 bg-brand-50' : 'border-slate-200 hover:border-slate-300']" @click="audienceSource = 'paste'">
                            <svg class="h-6 w-6 mx-auto mb-1" :class="audienceSource === 'paste' ? 'text-brand-600' : 'text-slate-400'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h14.25c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" /></svg>
                            <p class="text-sm font-semibold" :class="audienceSource === 'paste' ? 'text-brand-700' : 'text-slate-700'">Pegar lista</p>
                            <p class="text-xs text-slate-500 mt-1">Pega telefonos directamente</p>
                        </label>
                    </div>

                    <!-- File upload + preview -->
                    <div v-if="audienceSource === 'file'" class="space-y-3">
                        <div class="rounded-lg bg-slate-50 border border-slate-200 p-4">
                            <input type="file" accept=".xlsx,.xls,.csv" @change="onFileSelected" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100" />
                            <p v-if="excelParsing" class="text-sm text-brand-600 mt-2">Leyendo archivo...</p>
                        </div>

                        <!-- Excel preview table -->
                        <div v-if="excelRows.length > 0" class="rounded-lg border border-slate-200 overflow-hidden">
                            <!-- Toolbar -->
                            <div class="bg-slate-50 px-4 py-3 space-y-2 border-b border-slate-200">
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span class="text-sm font-bold text-brand-700">{{ excelSelected.size.toLocaleString() }}</span>
                                    <span class="text-sm text-slate-500">de {{ excelRows.length.toLocaleString() }} seleccionados</span>
                                    <button type="button" @click="toggleSelectAll" class="text-xs font-semibold text-brand-600 hover:text-brand-500">{{ allFilteredSelected ? 'Deseleccionar filtrados' : 'Seleccionar filtrados' }}</button>
                                    <button v-if="filteredExcelRows.length !== excelRows.length" type="button" @click="selectOnlyFiltered" class="text-xs font-semibold text-amber-600 hover:text-amber-500">Solo estos {{ filteredExcelRows.length.toLocaleString() }}</button>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input :value="excelFilter" @input="onFilterInput" type="text" class="flex-1 rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 py-1.5" placeholder="Buscar telefono o nombre..." />
                                    <select v-if="excelCountries.length > 1" v-model="excelCountryFilter" @change="excelPage = 1" class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 py-1.5 w-40">
                                        <option value="">Todos los paises</option>
                                        <option v-for="c in excelCountries" :key="c" :value="c">{{ c }}</option>
                                    </select>
                                </div>
                                <p class="text-xs text-slate-400">Mostrando {{ paginatedExcelRows.length }} de {{ filteredExcelRows.length.toLocaleString() }} (pag. {{ excelPage }}/{{ excelTotalPages }})</p>
                            </div>
                            <!-- Table -->
                            <div class="max-h-80 overflow-y-auto">
                                <table class="min-w-full">
                                    <thead class="bg-slate-100 sticky top-0 z-10">
                                        <tr>
                                            <th class="px-3 py-2 text-left w-10">
                                                <input type="checkbox" :checked="allFilteredSelected" @change="toggleSelectAll" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                                            </th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">Telefono</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">Nombre</th>
                                            <th v-if="excelCountries.length" class="px-3 py-2 text-left text-xs font-medium text-slate-500">Pais</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="row in paginatedExcelRows" :key="row.idx" :class="excelSelected.has(row.idx) ? 'bg-brand-50' : ''" class="hover:bg-slate-50 cursor-pointer" @click="toggleRow(row.idx)">
                                            <td class="px-3 py-1.5">
                                                <input type="checkbox" :checked="excelSelected.has(row.idx)" @click.stop="toggleRow(row.idx)" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                                            </td>
                                            <td class="px-3 py-1.5 text-sm font-mono text-slate-700">{{ row.phone }}</td>
                                            <td class="px-3 py-1.5 text-sm text-slate-600">{{ row.name || '—' }}</td>
                                            <td v-if="excelCountries.length" class="px-3 py-1.5 text-xs text-slate-500">{{ row.country || '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Pagination -->
                            <div v-if="excelTotalPages > 1" class="bg-slate-50 px-4 py-2 border-t border-slate-200 flex items-center justify-between">
                                <button type="button" @click="excelPage = Math.max(1, excelPage - 1)" :disabled="excelPage <= 1" class="rounded px-3 py-1 text-xs font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 disabled:opacity-40">Anterior</button>
                                <div class="flex items-center gap-1">
                                    <button v-for="p in Math.min(excelTotalPages, 7)" :key="p" type="button" @click="excelPage = p" :class="excelPage === p ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="rounded w-7 h-7 text-xs font-medium border border-slate-200">{{ p }}</button>
                                    <span v-if="excelTotalPages > 7" class="text-xs text-slate-400">... {{ excelTotalPages }}</span>
                                </div>
                                <button type="button" @click="excelPage = Math.min(excelTotalPages, excelPage + 1)" :disabled="excelPage >= excelTotalPages" class="rounded px-3 py-1 text-xs font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 disabled:opacity-40">Siguiente</button>
                            </div>
                        </div>
                    </div>

                    <!-- Paste phones -->
                    <div v-if="audienceSource === 'paste'" class="space-y-3">
                        <div class="rounded-lg bg-slate-50 border border-slate-200 p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-medium text-slate-700">Pega los telefonos (uno por linea)</label>
                                <button v-if="pastePhones" type="button" @click="clearPaste" class="text-xs font-semibold text-red-600 hover:text-red-500">Limpiar</button>
                            </div>
                            <textarea v-model="pastePhones" rows="8" class="block w-full rounded-lg border-slate-300 text-sm font-mono focus:border-brand-500 focus:ring-brand-500" placeholder="Nombre, +5215512345678&#10;+5215587654321&#10;Maria Lopez, 5529316009"></textarea>
                            <p class="text-xs text-slate-500">Formatos: solo telefono, Nombre + telefono, o telefono + Nombre. Uno por linea. <strong>Pegar agrega a lo que ya esta escrito</strong> — usa "Limpiar" para empezar de cero.</p>
                            <p v-if="pastePhones" class="text-sm font-medium text-brand-700">
                                {{ pasteStats.unique.toLocaleString() }} telefonos se enviaran
                                <span v-if="pasteStats.ignored" class="text-amber-600 font-normal">({{ pasteStats.ignored.toLocaleString() }} repetidos o invalidos ignorados de {{ pasteStats.raw.toLocaleString() }} lineas)</span>
                            </p>
                        </div>
                    </div>

                    <!-- Contact filters (only when source = contacts) -->
                    <template v-if="audienceSource === 'contacts'">
                    <p class="text-sm text-slate-500">Filtra que contactos recibiran el mensaje. Deja vacio para enviar a todos.</p>

                    <!-- Contact count badge -->
                    <div class="rounded-lg bg-brand-50 border border-brand-200 px-4 py-3 flex items-center gap-3">
                        <svg class="h-5 w-5 text-brand-600 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                        <div>
                            <p class="text-sm font-bold text-brand-700">
                                <span v-if="countLoading" class="animate-pulse">Contando...</span>
                                <span v-else>{{ contactCount.toLocaleString() }} contactos</span>
                            </p>
                            <p class="text-xs text-brand-600">{{ hasAnyFilter() ? 'coinciden con los filtros seleccionados' : 'en total (sin filtros aplicados)' }}</p>
                        </div>
                    </div>

                    <!-- Filter dropdowns -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Estado(s)</label>
                            <MultiSelectDropdown v-model="form.audience_filters.status_ids" :options="statuses || []" placeholder="Todos los estados" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Pais(es)</label>
                            <MultiSelectDropdown v-model="form.audience_filters.countries" :options="countries || []" placeholder="Todos los paises" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Etiqueta(s)</label>
                            <MultiSelectDropdown v-model="form.audience_filters.label_ids" :options="labels || []" placeholder="Todas las etiquetas" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Buscar</label>
                            <input v-model="form.audience_filters.search" type="text" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Nombre o telefono..." />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Fecha desde</label>
                            <input v-model="form.audience_filters.date_from" type="date" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Fecha hasta</label>
                            <input v-model="form.audience_filters.date_to" type="date" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" />
                        </div>
                    </div>
                    </template>
                </div>

                <!-- Submit -->
                <div class="flex items-center gap-3">
                    <button type="submit" :disabled="form.processing || !form.name || !form.template_name || !mediaReady" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50 transition">
                        {{ form.processing ? 'Creando...' : 'Crear Campana' }}
                    </button>
                    <Link :href="route('whatsapp.campaigns.index', account.id)" class="rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 transition">Cancelar</Link>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
