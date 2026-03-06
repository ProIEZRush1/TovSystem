<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { usePermissions } from '@/composables/usePermissions';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const { t } = useI18n();
const { can } = usePermissions();

defineProps({ labels: Array });

const showForm = ref(false);
const editingId = ref(null);
const showDeleteModal = ref(false);
const deleteId = ref(null);

const form = useForm({
    name: '',
    color: '#6B7280',
    sort_order: 0,
});

function openCreate() {
    editingId.value = null;
    form.reset();
    form.color = '#6B7280';
    showForm.value = true;
}

function openEdit(label) {
    editingId.value = label.id;
    form.name = label.name;
    form.color = label.color;
    form.sort_order = label.sort_order;
    showForm.value = true;
}

function submit() {
    if (editingId.value) {
        form.put(route('labels.update', editingId.value), {
            onSuccess: () => { showForm.value = false; },
        });
    } else {
        form.post(route('labels.store'), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
}

function confirmDelete(id) {
    deleteId.value = id;
    showDeleteModal.value = true;
}

function deleteLabel() {
    router.delete(route('labels.destroy', deleteId.value), {
        onSuccess: () => { showDeleteModal.value = false; },
    });
}
</script>

<template>
    <Head :title="t('labels.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-900">{{ t('labels.title') }}</h2>
                <PrimaryButton v-if="can('labels.manage')" @click="openCreate">
                    <svg class="h-4 w-4 mr-1.5 -ml-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ t('labels.add') }}
                </PrimaryButton>
            </div>
        </template>

        <div class="max-w-5xl space-y-6">
            <!-- Form -->
            <div v-if="showForm" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                <h3 class="text-base font-semibold text-slate-900 mb-4">{{ editingId ? t('labels.edit') : t('labels.add') }}</h3>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <InputLabel :value="t('labels.name')" />
                            <TextInput v-model="form.name" class="mt-1.5 block w-full" />
                            <InputError :message="form.errors.name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel :value="t('labels.color')" />
                            <div class="mt-1.5 flex items-center gap-2">
                                <input type="color" v-model="form.color" class="h-[38px] w-14 rounded-lg border border-slate-300 cursor-pointer" />
                                <TextInput v-model="form.color" class="block w-full" maxlength="7" />
                            </div>
                            <InputError :message="form.errors.color" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel :value="t('labels.sortOrder')" />
                            <TextInput v-model.number="form.sort_order" type="number" class="mt-1.5 block w-full" />
                        </div>
                    </div>
                    <div class="flex gap-2 justify-end pt-2">
                        <SecondaryButton @click="showForm = false">{{ t('common.cancel') }}</SecondaryButton>
                        <PrimaryButton :disabled="form.processing">{{ t('common.save') }}</PrimaryButton>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 overflow-hidden">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-slate-100">
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('labels.color') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('labels.name') }}</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('labels.sortOrder') }}</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('labels.contacts') }}</th>
                            <th v-if="can('labels.manage')" class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="label in labels" :key="label.id" class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-3.5">
                                <div class="h-6 w-6 rounded-full ring-2 ring-white shadow-sm" :style="{ backgroundColor: label.color }"></div>
                            </td>
                            <td class="px-6 py-3.5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :style="{ backgroundColor: label.color + '20', color: label.color }">
                                    {{ label.name }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5 text-center text-sm text-slate-600 tabular-nums">{{ label.sort_order }}</td>
                            <td class="px-6 py-3.5 text-center text-sm font-semibold text-slate-700 tabular-nums">{{ label.contacts_count?.toLocaleString() }}</td>
                            <td v-if="can('labels.manage')" class="px-6 py-3.5 text-right text-sm space-x-3">
                                <button @click="openEdit(label)" class="font-medium text-brand-600 hover:text-brand-500 transition">{{ t('labels.edit') }}</button>
                                <button @click="confirmDelete(label.id)" class="font-medium text-red-600 hover:text-red-500 transition">{{ t('labels.delete') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!labels?.length" class="p-8 text-center text-sm text-slate-400">{{ t('common.noResults') }}</div>
            </div>
        </div>

        <ConfirmModal
            :show="showDeleteModal"
            :title="t('labels.delete')"
            :message="t('labels.confirmDelete')"
            @confirm="deleteLabel"
            @cancel="showDeleteModal = false"
        />
    </AuthenticatedLayout>
</template>
