<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const { t } = useI18n();
const props = defineProps({ account: Object, templates: Array });

const form = useForm({
    name: '',
    template_name: '',
    template_language: '',
    template_components: [],
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
        detectedParams.value.forEach(p => { templateParams.value[p] = ''; });
    }
}

function buildComponents() {
    const components = [];
    if (detectedParams.value.length) {
        components.push({
            type: 'body',
            parameters: detectedParams.value.map(p => ({
                type: 'text',
                parameter_name: p,
                text: templateParams.value[p] || '{{' + p + '}}',
            })),
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

function submit() {
    form.template_components = buildComponents();
    form.post(route('whatsapp.campaigns.store', props.account.id));
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

                    <!-- Variable inputs -->
                    <div v-if="detectedParams.length" class="space-y-2">
                        <p class="text-sm font-medium text-slate-700">Variables del template:</p>
                        <div v-for="p in detectedParams" :key="p" class="flex items-center gap-3">
                            <label class="text-xs font-mono text-slate-500 w-32 shrink-0">{{ paramLabel(p) }}</label>
                            <input v-model="templateParams[p]" type="text" class="flex-1 rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" :placeholder="'ej. nombre del contacto, texto fijo...'" />
                        </div>
                        <p class="text-xs text-slate-500">Tip: escribe el valor fijo (ej. "Kolel Tov") o deja vacio para auto-resolver nombre del contacto.</p>
                    </div>
                </div>

                <!-- Step 2: Audience Filters -->
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5 space-y-4">
                    <h3 class="text-base font-semibold text-slate-900">2. Audiencia</h3>
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
