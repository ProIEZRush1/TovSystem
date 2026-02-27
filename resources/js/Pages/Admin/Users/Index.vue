<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { usePermissions } from '@/composables/usePermissions';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const { t } = useI18n();
const { can } = usePermissions();

defineProps({ users: Object });

const showDeleteModal = ref(false);
const deleteUserId = ref(null);

function confirmDelete(id) {
    deleteUserId.value = id;
    showDeleteModal.value = true;
}

function deleteUser() {
    router.delete(route('admin.users.destroy', deleteUserId.value), {
        onSuccess: () => { showDeleteModal.value = false; },
    });
}
</script>

<template>
    <Head :title="t('admin.users.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-900">{{ t('admin.users.title') }}</h2>
                <div class="flex gap-2">
                    <Link v-if="can('admin.roles.view')" :href="route('admin.roles.index')" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                        {{ t('nav.roles') }}
                    </Link>
                    <Link v-if="can('admin.users.create')" :href="route('admin.users.create')">
                        <PrimaryButton>
                            <svg class="h-4 w-4 mr-1.5 -ml-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            {{ t('admin.users.create') }}
                        </PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 overflow-hidden">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('admin.users.name') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('admin.users.email') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('admin.users.roles') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('admin.users.superAdmin') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('admin.users.active') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-slate-50/50 transition">
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-brand-700 text-xs font-bold">
                                    {{ user.name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <span class="text-sm font-medium text-slate-900">{{ user.name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-3.5 text-sm text-slate-500">{{ user.email }}</td>
                        <td class="px-6 py-3.5">
                            <span v-for="role in user.roles" :key="role.id" class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 mr-1">
                                {{ role.name }}
                            </span>
                            <span v-if="!user.roles?.length" class="text-sm text-slate-300">-</span>
                        </td>
                        <td class="px-6 py-3.5 text-center">
                            <span v-if="user.is_super_admin" class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-green-100">
                                <svg class="h-3.5 w-3.5 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-center">
                            <span :class="[
                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                user.is_active ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'
                            ]">
                                {{ user.is_active ? t('common.yes') : t('common.no') }}
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-right text-sm space-x-3">
                            <Link v-if="can('admin.users.update')" :href="route('admin.users.edit', user.id)" class="font-medium text-brand-600 hover:text-brand-500 transition">{{ t('contacts.edit') }}</Link>
                            <button v-if="can('admin.users.delete')" @click="confirmDelete(user.id)" class="font-medium text-red-600 hover:text-red-500 transition">{{ t('common.delete') }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="!users.data?.length" class="p-8 text-center text-sm text-slate-400">{{ t('common.noResults') }}</div>

            <div v-if="users.data.length && users.last_page > 1" class="border-t border-slate-100 px-4 py-3 flex items-center justify-between">
                <p class="text-sm text-slate-500">{{ t('common.showing', { from: users.from, to: users.to, total: users.total }) }}</p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in users.links" :key="link.label" :href="link.url || ''"
                        :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition', link.active ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100', !link.url ? 'opacity-40 pointer-events-none' : '']"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <ConfirmModal :show="showDeleteModal" :title="t('admin.users.delete')" :message="t('admin.users.confirmDelete')" @confirm="deleteUser" @cancel="showDeleteModal = false" />
    </AuthenticatedLayout>
</template>
