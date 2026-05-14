<script setup>
import { ref } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { usePermissions } from '@/composables/usePermissions';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import axios from 'axios';

const { t } = useI18n();
const { can } = usePermissions();

const props = defineProps({
    accounts: Array,
});

const showForm = ref(false);
const editingId = ref(null);
const showDeleteModal = ref(false);
const deleteId = ref(null);
const refreshingId = ref(null);
const refreshError = ref('');

const form = useForm({
    name: '',
    phone_number: '',
    waba_id: '',
    access_token: '',
    is_active: true,
});

function openCreate() {
    editingId.value = null;
    form.reset();
    form.is_active = true;
    showForm.value = true;
}

function openEdit(account) {
    editingId.value = account.id;
    form.name = account.name;
    form.phone_number = account.phone_number;
    form.waba_id = account.waba_id || '';
    form.access_token = '';
    form.is_active = account.is_active;
    showForm.value = true;
}

function submit() {
    if (editingId.value) {
        form.put(route('whatsapp.update', editingId.value), {
            onSuccess: () => { showForm.value = false; },
        });
    } else {
        form.post(route('whatsapp.store'), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
}

function confirmDelete(id) {
    deleteId.value = id;
    showDeleteModal.value = true;
}

function deleteAccount() {
    if (!deleteId.value) return;
    form.delete(route('whatsapp.destroy', deleteId.value), {
        onSuccess: () => {
            showDeleteModal.value = false;
            deleteId.value = null;
        },
    });
}

async function refreshAccount(id) {
    refreshingId.value = id;
    refreshError.value = '';
    try {
        const r = await axios.post(route('whatsapp.refresh', id));
        if (r.data.error) {
            refreshError.value = r.data.error;
        } else {
            window.location.reload();
        }
    } catch (e) {
        refreshError.value = e.response?.data?.message || 'Error al conectar con Meta API';
    } finally {
        refreshingId.value = null;
    }
}
</script>

<template>
    <Head :title="t('whatsapp.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-900">{{ t('whatsapp.title') }}</h2>
                <button v-if="can('whatsapp.manage')" @click="openCreate" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500 transition">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ t('whatsapp.addAccount') }}
                </button>
            </div>
        </template>

        <!-- Add/Edit Form -->
        <div v-if="showForm" class="mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h3 class="text-base font-semibold text-slate-900 mb-4">{{ editingId ? t('whatsapp.editAccount') : t('whatsapp.addAccount') }}</h3>
            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.name') }}</label>
                        <input v-model="form.name" type="text" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Mi negocio" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.phoneNumber') }}</label>
                        <input v-model="form.phone_number" type="text" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="+5215512345678" />
                        <p v-if="form.errors.phone_number" class="mt-1 text-sm text-red-600">{{ form.errors.phone_number }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.wabaId') }}</label>
                        <input v-model="form.waba_id" type="text" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="123456789012345" />
                        <p class="text-xs text-slate-400 mt-1">{{ t('whatsapp.wabaHint') }}</p>
                        <p v-if="form.errors.waba_id" class="mt-1 text-sm text-red-600">{{ form.errors.waba_id }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.accessToken') }}</label>
                        <input v-model="form.access_token" type="password" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" :placeholder="editingId ? '(sin cambios)' : ''" />
                        <p class="text-xs text-slate-400 mt-1">{{ t('whatsapp.tokenHint') }}</p>
                        <p v-if="form.errors.access_token" class="mt-1 text-sm text-red-600">{{ form.errors.access_token }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50 transition">
                        {{ t('common.save') }}
                    </button>
                    <button type="button" @click="showForm = false" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 transition">
                        {{ t('common.cancel') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Accounts List -->
        <div class="grid gap-4">
            <div v-for="account in accounts" :key="account.id" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-3">
                            <h3 class="text-lg font-semibold text-slate-900">{{ account.name }}</h3>
                            <span :class="[
                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                account.is_active ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-500'
                            ]">
                                {{ account.is_active ? t('whatsapp.active') : t('whatsapp.inactive') }}
                            </span>
                            <span v-if="account.phone_number_id" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                {{ t('whatsapp.connected') }}
                            </span>
                            <span v-else class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700" :title="t('whatsapp.notConnectedHint')">
                                {{ t('whatsapp.notConnected') }}
                            </span>
                        </div>
                        <div class="mt-2 text-sm text-slate-500 space-y-1">
                            <p><span class="font-medium">{{ t('whatsapp.phoneNumber') }}:</span> {{ account.phone_number }}</p>
                            <p v-if="account.phone_number_id"><span class="font-medium">{{ t('whatsapp.phoneNumberId') }}:</span> {{ account.phone_number_id }}</p>
                            <p v-if="account.verified_name"><span class="font-medium">{{ t('whatsapp.verifiedName') }}:</span> {{ account.verified_name }}</p>
                            <p v-if="account.quality_rating"><span class="font-medium">{{ t('whatsapp.qualityRating') }}:</span> {{ account.quality_rating }}</p>
                            <p><span class="font-medium">{{ t('whatsapp.messages') }}:</span> {{ account.messages_count }}</p>
                        </div>
                        <!-- Not connected help -->
                        <div v-if="!account.phone_number_id" class="mt-3 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                            <p class="font-medium">No se pudo conectar. Haz clic en "Actualizar" para reintentar, o verifica:</p>
                            <ul class="mt-1 list-disc list-inside space-y-0.5 text-amber-700">
                                <li>Que el WABA ID sea correcto (se ve en Meta Business Settings)</li>
                                <li>Que el Access Token sea valido y no haya expirado</li>
                                <li>Que el telefono coincida con uno registrado en la WABA</li>
                            </ul>
                        </div>
                        <!-- Refresh error -->
                        <div v-if="refreshError" class="mt-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700">
                            <p class="font-medium">Error de Meta API:</p>
                            <p class="mt-0.5">{{ refreshError }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link v-if="account.phone_number_id" :href="route('whatsapp.inbox', account.id)" class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-green-500 transition">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                            </svg>
                            Inbox
                        </Link>
                        <Link v-if="account.phone_number_id" :href="route('whatsapp.campaigns.index', account.id)" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-500 transition">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                            </svg>
                            Campanas
                        </Link>
                        <Link v-if="account.phone_number_id" :href="route('whatsapp.templates-page', account.id)" class="inline-flex items-center gap-1.5 rounded-lg border border-brand-300 bg-white px-3 py-1.5 text-sm font-semibold text-brand-700 hover:bg-brand-50 transition">
                            {{ t('whatsapp.templatesTitle') }}
                        </Link>
                        <button v-if="can('whatsapp.manage')" @click="refreshAccount(account.id)" :disabled="refreshingId === account.id" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50 transition">
                            <svg :class="['h-4 w-4', refreshingId === account.id ? 'animate-spin' : '']" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                            </svg>
                            {{ t('whatsapp.refresh') }}
                        </button>
                        <button v-if="can('whatsapp.manage')" @click="openEdit(account)" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 transition">
                            {{ t('contacts.edit') }}
                        </button>
                        <button v-if="can('whatsapp.manage')" @click="confirmDelete(account.id)" class="rounded-lg border border-red-200 bg-white px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 transition">
                            {{ t('common.delete') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!accounts.length && !showForm" class="rounded-xl bg-white px-6 py-12 shadow-sm ring-1 ring-slate-900/5 text-center">
            <svg class="mx-auto h-10 w-10 text-slate-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
            </svg>
            <p class="mt-2 text-sm text-slate-500">{{ t('whatsapp.noConversations') }}</p>
        </div>

        <ConfirmModal
            :show="showDeleteModal"
            :title="t('common.delete')"
            :message="t('whatsapp.confirmDelete')"
            @confirm="deleteAccount"
            @cancel="showDeleteModal = false"
        />
    </AuthenticatedLayout>
</template>
