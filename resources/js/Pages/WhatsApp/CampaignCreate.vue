<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const { t } = useI18n();
const props = defineProps({ account: Object, templates: Array });

const audienceSource = ref('contacts');
const audienceFile = ref(null);

const form = useForm({
    name: '',
    template_name: '',
    template_language: '',
    template_components: [],
    audience_source: 'contacts',
    audience_file: null,
    audience_filters: {
        status_ids: [],
        countries: [],
        label_ids: [],
        date_from: '',
        date_to: '',
        search: '',
    },
});

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
    }
}

function submit() {
    form.template_components = buildComponents();
    form.audience_source = audienceSource.value;
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
                    <div class="flex gap-3">
                        <label :class="['flex-1 cursor-pointer rounded-lg border-2 p-4 text-center transition', audienceSource === 'contacts' ? 'border-brand-500 bg-brand-50' : 'border-slate-200 hover:border-slate-300']" @click="audienceSource = 'contacts'">
                            <p class="text-sm font-semibold" :class="audienceSource === 'contacts' ? 'text-brand-700' : 'text-slate-700'">Contactos del sistema</p>
                            <p class="text-xs text-slate-500 mt-1">Filtra por estado, pais, etiqueta, fecha</p>
                        </label>
                        <label :class="['flex-1 cursor-pointer rounded-lg border-2 p-4 text-center transition', audienceSource === 'file' ? 'border-brand-500 bg-brand-50' : 'border-slate-200 hover:border-slate-300']" @click="audienceSource = 'file'">
                            <p class="text-sm font-semibold" :class="audienceSource === 'file' ? 'text-brand-700' : 'text-slate-700'">Subir archivo Excel/CSV</p>
                            <p class="text-xs text-slate-500 mt-1">Sube un archivo con telefonos y nombres</p>
                        </label>
                    </div>

                    <!-- File upload -->
                    <div v-if="audienceSource === 'file'" class="rounded-lg bg-slate-50 border border-slate-200 p-4 space-y-3">
                        <input type="file" accept=".xlsx,.xls,.csv" @change="onFileSelected" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100" />
                        <p v-if="audienceFile" class="text-sm text-green-700 font-medium">Archivo: {{ audienceFile.name }} ({{ Math.round(audienceFile.size / 1024) }}KB)</p>
                        <p class="text-xs text-slate-500">El archivo debe tener una columna de telefono. El sistema detecta automaticamente las columnas de telefono y nombre.</p>
                    </div>

                    <!-- Contact filters (only when source = contacts) -->
                    <template v-if="audienceSource === 'contacts'">
                    <p class="text-sm text-slate-500">Filtra que contactos recibiran el mensaje. Deja vacio para enviar a todos.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Estado(s)</label>
                            <input v-model="form.audience_filters.status_ids" type="text" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="IDs separados por coma: 22,23,35" />
                            <p class="text-xs text-slate-400 mt-1">IDs de estado separados por coma</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Pais(es)</label>
                            <input v-model="form.audience_filters.countries" type="text" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Mexico,Argentina" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Etiqueta(s)</label>
                            <input v-model="form.audience_filters.label_ids" type="text" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="IDs: 1,2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Buscar</label>
                            <input v-model="form.audience_filters.search" type="text" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="nombre o telefono" />
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
                    <button type="submit" :disabled="form.processing || !form.name || !form.template_name" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50 transition">
                        {{ form.processing ? 'Creando...' : 'Crear Campana' }}
                    </button>
                    <Link :href="route('whatsapp.campaigns.index', account.id)" class="rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 transition">Cancelar</Link>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
