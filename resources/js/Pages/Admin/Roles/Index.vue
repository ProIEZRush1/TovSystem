<script setup>
import { ref } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
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

const props = defineProps({
    roles: Array,
    availablePermissions: Array,
});

const showForm = ref(false);
const editingId = ref(null);
const showDeleteModal = ref(false);
const deleteId = ref(null);

const form = useForm({ name: '', description: '', permissions: [] });

function openCreate() {
    editingId.value = null;
    form.reset();
    form.permissions = [];
    showForm.value = true;
}

function openEdit(role) {
    editingId.value = role.id;
    form.name = role.name;
    form.description = role.description || '';
    form.permissions = role.permissions ? [...role.permissions] : [];
    showForm.value = true;
}

function submit() {
    if (editingId.value) {
        form.put(route('admin.roles.update', editingId.value), { onSuccess: () => { showForm.value = false; } });
    } else {
        form.post(route('admin.roles.store'), { onSuccess: () => { showForm.value = false; form.reset(); } });
    }
}

function isGroupFullySelected(group) {
    return group.scopes.every(scope => form.permissions.includes(scope));
}

function isGroupPartiallySelected(group) {
    const selected = group.scopes.filter(scope => form.permissions.includes(scope));
    return selected.length > 0 && selected.length < group.scopes.length;
}

function toggleGroup(group) {
    if (isGroupFullySelected(group)) {
        form.permissions = form.permissions.filter(p => !group.scopes.includes(p));
    } else {
        const newPerms = new Set([...form.permissions, ...group.scopes]);
        form.permissions = [...newPerms];
    }
}

function toggleScope(scope) {
    const idx = form.permissions.indexOf(scope);
    if (idx > -1) {
        form.permissions.splice(idx, 1);
    } else {
        form.permissions.push(scope);
    }
}

function permissionSummary(role) {
    if (!role.permissions?.length) return '-';
    if (role.permissions.includes('*')) return t('permissions.allPermissions');
    return role.permissions.length + ' ' + t('admin.roles.permissions').toLowerCase();
}

function confirmDelete(id) { deleteId.value = id; showDeleteModal.value = true; }

function deleteRole() {
    router.delete(route('admin.roles.destroy', deleteId.value), { onSuccess: () => { showDeleteModal.value = false; } });
}
</script>

<template>
    <Head :title="t('admin.roles.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.users.index')" class="text-slate-400 hover:text-slate-600 transition">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                    </Link>
                    <h2 class="text-xl font-bold text-slate-900">{{ t('admin.roles.title') }}</h2>
                </div>
                <PrimaryButton v-if="can('admin.roles.manage')" @click="openCreate">
                    <svg class="h-4 w-4 mr-1.5 -ml-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ t('admin.roles.create') }}
                </PrimaryButton>
            </div>
        </template>

        <div class="max-w-5xl space-y-6">
            <!-- Form -->
            <div v-if="showForm" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                <h3 class="text-base font-semibold text-slate-900 mb-4">{{ editingId ? t('admin.roles.name') : t('admin.roles.create') }}</h3>
                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel :value="t('admin.roles.name')" />
                            <TextInput v-model="form.name" class="mt-1.5 block w-full" required />
                            <InputError :message="form.errors.name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel :value="t('admin.roles.description')" />
                            <TextInput v-model="form.description" class="mt-1.5 block w-full" />
                        </div>
                    </div>

                    <!-- Permissions -->
                    <div>
                        <InputLabel :value="t('admin.roles.permissions')" class="mb-3" />
                        <div class="space-y-3">
                            <div v-for="group in availablePermissions" :key="group.group"
                                 class="rounded-lg border border-slate-200 p-4">
                                <!-- Group header -->
                                <label class="flex items-center gap-2.5 cursor-pointer">
                                    <input type="checkbox"
                                           :checked="isGroupFullySelected(group)"
                                           :indeterminate.prop="isGroupPartiallySelected(group)"
                                           @change="toggleGroup(group)"
                                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                                    <span class="text-sm font-semibold text-slate-700 capitalize">
                                        {{ t('permissions.groups.' + group.group) }}
                                    </span>
                                </label>
                                <!-- Scopes -->
                                <div class="mt-2.5 ml-7 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                    <label v-for="scope in group.scopes" :key="scope"
                                           class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox"
                                               :checked="form.permissions.includes(scope)"
                                               @change="toggleScope(scope)"
                                               class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                                        <span class="text-sm text-slate-600">
                                            {{ t('permissions.scopes.' + scope) }}
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <InputError :message="form.errors.permissions" class="mt-1" />
                    </div>

                    <div class="flex gap-2 justify-end pt-2">
                        <SecondaryButton @click="showForm = false">{{ t('common.cancel') }}</SecondaryButton>
                        <PrimaryButton :disabled="form.processing">{{ t('admin.roles.save') }}</PrimaryButton>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 overflow-hidden">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-slate-100">
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('admin.roles.name') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('admin.roles.description') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('admin.roles.permissions') }}</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('admin.roles.users') }}</th>
                            <th v-if="can('admin.roles.manage')" class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="role in roles" :key="role.id" class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-3.5">
                                <div>
                                    <span class="text-sm font-medium text-slate-900">{{ role.name }}</span>
                                    <span class="ml-2 text-xs text-slate-400 font-mono">{{ role.slug }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-3.5 text-sm text-slate-500">{{ role.description || '-' }}</td>
                            <td class="px-6 py-3.5 text-sm text-slate-500">{{ permissionSummary(role) }}</td>
                            <td class="px-6 py-3.5 text-center text-sm font-semibold text-slate-700">{{ role.users_count }}</td>
                            <td v-if="can('admin.roles.manage')" class="px-6 py-3.5 text-right text-sm space-x-3">
                                <button @click="openEdit(role)" class="font-medium text-brand-600 hover:text-brand-500 transition">{{ t('contacts.edit') }}</button>
                                <button @click="confirmDelete(role.id)" class="font-medium text-red-600 hover:text-red-500 transition">{{ t('common.delete') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!roles?.length" class="p-8 text-center text-sm text-slate-400">{{ t('common.noResults') }}</div>
            </div>
        </div>

        <ConfirmModal :show="showDeleteModal" :title="t('admin.roles.delete')" :message="t('admin.roles.confirmDelete')" @confirm="deleteRole" @cancel="showDeleteModal = false" />
    </AuthenticatedLayout>
</template>
