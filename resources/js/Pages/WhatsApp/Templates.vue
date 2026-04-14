<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import axios from 'axios';

const { t } = useI18n();

const props = defineProps({
    account: Object,
    templates: Array,
});

const templates = ref([...props.templates]);
const showForm = ref(false);
const submitting = ref(false);
const error = ref('');

// Form state
const form = ref({
    name: '',
    category: 'MARKETING',
    language: 'es',
    header_text: '',
    body_text: '',
    footer_text: '',
    button_type: 'none',
    button_text: '',
    button_url: '',
    button_phone: '',
});

const detectedParams = computed(() => {
    const matches = (form.value.body_text || '').match(/\{\{([a-z0-9_]+)\}\}/gi) || [];
    return [...new Set(matches.map(m => m.replace(/[{}]/g, '')))];
});

const bodyExample = computed(() => {
    if (!detectedParams.value.length) return null;
    return {
        body_text_named_params: detectedParams.value.map((p, i) => ({
            param_name: p,
            example: `Sample${i + 1}`,
        })),
    };
});

function buildComponents() {
    const components = [];
    if (form.value.header_text.trim()) {
        components.push({
            type: 'HEADER',
            format: 'TEXT',
            text: form.value.header_text.trim(),
        });
    }
    const body = {
        type: 'BODY',
        text: form.value.body_text.trim(),
    };
    if (detectedParams.value.length) {
        body.example = bodyExample.value;
    }
    components.push(body);

    if (form.value.footer_text.trim()) {
        components.push({
            type: 'FOOTER',
            text: form.value.footer_text.trim(),
        });
    }

    if (form.value.button_type !== 'none') {
        const btn = { text: form.value.button_text.trim() };
        if (form.value.button_type === 'URL') {
            btn.type = 'URL';
            btn.url = form.value.button_url.trim();
        } else if (form.value.button_type === 'PHONE_NUMBER') {
            btn.type = 'PHONE_NUMBER';
            btn.phone_number = form.value.button_phone.trim();
        } else if (form.value.button_type === 'QUICK_REPLY') {
            btn.type = 'QUICK_REPLY';
        }
        components.push({
            type: 'BUTTONS',
            buttons: [btn],
        });
    }

    return components;
}

async function submit() {
    if (!form.value.name || !form.value.body_text) return;
    submitting.value = true;
    error.value = '';
    try {
        const payload = {
            name: form.value.name.toLowerCase().replace(/[^a-z0-9_]/g, '_'),
            category: form.value.category,
            language: form.value.language,
            components: buildComponents(),
        };
        const response = await axios.post(route('whatsapp.templates.create', props.account.id), payload);
        // Reload templates list
        router.reload({ only: ['templates'] });
        resetForm();
        showForm.value = false;
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to create template';
    } finally {
        submitting.value = false;
    }
}

function resetForm() {
    form.value = {
        name: '',
        category: 'MARKETING',
        language: 'es',
        header_text: '',
        body_text: '',
        footer_text: '',
        button_type: 'none',
        button_text: '',
        button_url: '',
        button_phone: '',
    };
}

// Delete
const showDelete = ref(false);
const deleteName = ref(null);

function confirmDelete(name) {
    deleteName.value = name;
    showDelete.value = true;
}

async function doDelete() {
    if (!deleteName.value) return;
    try {
        await axios.delete(route('whatsapp.templates.delete', [props.account.id, deleteName.value]));
        templates.value = templates.value.filter(t => t.name !== deleteName.value);
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to delete';
    } finally {
        showDelete.value = false;
        deleteName.value = null;
    }
}

function statusColor(status) {
    return {
        APPROVED: 'bg-green-50 text-green-700 ring-green-200',
        PENDING: 'bg-amber-50 text-amber-700 ring-amber-200',
        REJECTED: 'bg-red-50 text-red-700 ring-red-200',
        IN_APPEAL: 'bg-blue-50 text-blue-700 ring-blue-200',
        PAUSED: 'bg-slate-50 text-slate-600 ring-slate-200',
    }[status] || 'bg-slate-50 text-slate-600 ring-slate-200';
}

function categoryColor(cat) {
    return {
        MARKETING: 'bg-pink-50 text-pink-700',
        UTILITY: 'bg-indigo-50 text-indigo-700',
        AUTHENTICATION: 'bg-amber-50 text-amber-700',
    }[cat] || 'bg-slate-50 text-slate-700';
}

function paramLabel(p) {
    return '{{' + p + '}}';
}

function getBodyText(tpl) {
    const body = (tpl.components || []).find(c => c.type === 'BODY');
    return body?.text || '';
}
</script>

<template>
    <Head :title="t('whatsapp.templatesTitle') + ' - ' + account.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('whatsapp.index')" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                </Link>
                <h2 class="text-xl font-bold text-slate-900">{{ t('whatsapp.templatesTitle') }} — {{ account.name }}</h2>
                <div class="ml-auto flex gap-2">
                    <Link :href="route('whatsapp.chat', account.id)" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                        {{ t('whatsapp.chat') }}
                    </Link>
                    <button @click="showForm = !showForm" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500 transition">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        {{ showForm ? t('common.cancel') : t('whatsapp.newTemplate') }}
                    </button>
                </div>
            </div>
        </template>

        <!-- Create form -->
        <div v-if="showForm" class="mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h3 class="text-base font-semibold text-slate-900 mb-4">{{ t('whatsapp.newTemplate') }}</h3>
            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.tplName') }}</label>
                        <input v-model="form.name" type="text" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="welcome_discount" />
                        <p class="text-xs text-slate-400 mt-1">{{ t('whatsapp.tplNameHint') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.tplCategory') }}</label>
                        <select v-model="form.category" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="MARKETING">MARKETING</option>
                            <option value="UTILITY">UTILITY</option>
                            <option value="AUTHENTICATION">AUTHENTICATION</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.tplLanguage') }}</label>
                        <select v-model="form.language" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="es">es</option>
                            <option value="es_MX">es_MX</option>
                            <option value="en">en</option>
                            <option value="en_US">en_US</option>
                            <option value="he">he</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.tplHeader') }} <span class="text-slate-400 font-normal">({{ t('common.optional') }})</span></label>
                    <input v-model="form.header_text" type="text" maxlength="60" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" :placeholder="t('whatsapp.tplHeaderPlaceholder')" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.tplBody') }}</label>
                    <textarea v-model="form.body_text" rows="5" maxlength="1024" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" :placeholder="t('whatsapp.tplBodyPlaceholder')"></textarea>
                    <p class="text-xs text-slate-500 mt-1">{{ t('whatsapp.tplBodyHint') }}</p>
                    <div v-if="detectedParams.length" class="mt-2 flex flex-wrap gap-1.5">
                        <span class="text-xs text-slate-500">{{ t('whatsapp.tplDetectedParams') }}:</span>
                        <span v-for="p in detectedParams" :key="p" class="inline-flex items-center rounded-full bg-brand-50 text-brand-700 px-2 py-0.5 text-xs font-medium">
                            {{ paramLabel(p) }}
                        </span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.tplFooter') }} <span class="text-slate-400 font-normal">({{ t('common.optional') }})</span></label>
                    <input v-model="form.footer_text" type="text" maxlength="60" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" :placeholder="t('whatsapp.tplFooterPlaceholder')" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.tplButton') }}</label>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <select v-model="form.button_type" class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="none">{{ t('whatsapp.tplButtonNone') }}</option>
                            <option value="URL">URL</option>
                            <option value="PHONE_NUMBER">{{ t('whatsapp.tplButtonPhone') }}</option>
                            <option value="QUICK_REPLY">{{ t('whatsapp.tplButtonQuickReply') }}</option>
                        </select>
                        <input v-if="form.button_type !== 'none'" v-model="form.button_text" type="text" maxlength="25" class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" :placeholder="t('whatsapp.tplButtonText')" />
                        <input v-if="form.button_type === 'URL'" v-model="form.button_url" type="text" class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-2" placeholder="https://example.com" />
                        <input v-if="form.button_type === 'PHONE_NUMBER'" v-model="form.button_phone" type="text" class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-2" placeholder="+5215512345678" />
                    </div>
                </div>

                <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ error }}</div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" :disabled="submitting" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50 transition">
                        {{ submitting ? t('common.loading') : t('whatsapp.tplSubmit') }}
                    </button>
                    <button type="button" @click="showForm = false" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 transition">{{ t('common.cancel') }}</button>
                    <p class="text-xs text-slate-500 ml-auto">{{ t('whatsapp.tplReviewNote') }}</p>
                </div>
            </form>
        </div>

        <!-- Templates list -->
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-slate-100">
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('whatsapp.tplName') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('whatsapp.tplCategory') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('whatsapp.tplLanguage') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('whatsapp.tplStatus') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('whatsapp.tplPreview') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('contacts.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="tpl in templates" :key="tpl.id">
                            <td class="px-4 py-3 text-sm font-medium text-slate-900 font-mono">{{ tpl.name }}</td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', categoryColor(tpl.category)]">{{ tpl.category }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ tpl.language }}</td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1', statusColor(tpl.status)]">{{ tpl.status }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600 max-w-md truncate">{{ getBodyText(tpl) }}</td>
                            <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                <button @click="confirmDelete(tpl.name)" class="font-medium text-red-600 hover:text-red-500 transition">{{ t('common.delete') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="!templates.length" class="px-6 py-12 text-center text-sm text-slate-400">
                {{ t('whatsapp.tplEmpty') }}
            </div>
        </div>

        <ConfirmModal
            :show="showDelete"
            :title="t('common.delete')"
            :message="t('whatsapp.tplConfirmDelete')"
            @confirm="doDelete"
            @cancel="showDelete = false"
        />
    </AuthenticatedLayout>
</template>
