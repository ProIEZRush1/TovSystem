<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import StatusBadge from '@/Components/StatusBadge.vue';

const { t } = useI18n();

defineProps({
    stats: Object,
});
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-slate-900">{{ t('dashboard.title') }}</h2>
        </template>

        <div class="space-y-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50">
                            <svg class="h-6 w-6 text-brand-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-500">{{ t('dashboard.totalContacts') }}</p>
                            <p class="mt-1 text-3xl font-bold text-slate-900">{{ stats.totalContacts?.toLocaleString() }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50">
                            <svg class="h-6 w-6 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-500">{{ t('dashboard.totalStatuses') }}</p>
                            <p class="mt-1 text-3xl font-bold text-slate-900">{{ stats.totalStatuses }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Breakdown -->
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                <h3 class="text-base font-semibold text-slate-900 mb-4">{{ t('dashboard.byStatus') }}</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    <div v-for="status in stats.byStatus" :key="status.name" class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50/50 p-3">
                        <StatusBadge :name="status.name" :color="status.color" />
                        <span class="text-sm font-semibold text-slate-700 tabular-nums">{{ status.count?.toLocaleString() }}</span>
                    </div>
                </div>
                <p v-if="!stats.byStatus?.length" class="text-sm text-slate-400">{{ t('common.noResults') }}</p>
            </div>

            <!-- Country Breakdown -->
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                <h3 class="text-base font-semibold text-slate-900 mb-4">{{ t('dashboard.byCountry') }}</h3>
                <div class="divide-y divide-slate-100">
                    <div v-for="item in stats.byCountry" :key="item.country" class="flex items-center justify-between py-2.5">
                        <span class="text-sm text-slate-700">{{ item.country || 'Unknown' }}</span>
                        <span class="text-sm font-semibold text-slate-900 tabular-nums">{{ item.count?.toLocaleString() }}</span>
                    </div>
                </div>
                <p v-if="!stats.byCountry?.length" class="text-sm text-slate-400 mt-2">{{ t('common.noResults') }}</p>
            </div>

            <!-- Recent Imports -->
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="text-base font-semibold text-slate-900">{{ t('dashboard.recentImports') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table v-if="stats.recentImports?.length" class="min-w-full">
                        <thead>
                            <tr class="border-b border-slate-100">
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">File</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('contacts.status') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ t('import.rowsImported') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">User</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="imp in stats.recentImports" :key="imp.id" class="hover:bg-slate-50/50">
                                <td class="px-6 py-3.5 text-sm">
                                    <Link :href="route('import.show', imp.id)" class="font-medium text-brand-600 hover:text-brand-500">
                                        {{ imp.original_filename }}
                                    </Link>
                                </td>
                                <td class="px-6 py-3.5">
                                    <span :class="[
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                        imp.status === 'completed' ? 'bg-green-50 text-green-700' :
                                        imp.status === 'failed' ? 'bg-red-50 text-red-700' :
                                        imp.status === 'processing' ? 'bg-blue-50 text-blue-700' :
                                        'bg-slate-100 text-slate-700'
                                    ]">
                                        {{ imp.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-3.5 text-sm text-slate-600 tabular-nums">{{ imp.rows_imported?.toLocaleString() }} / {{ imp.total_rows?.toLocaleString() }}</td>
                                <td class="px-6 py-3.5 text-sm text-slate-500">{{ imp.user?.name }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="px-6 py-8 text-center text-sm text-slate-400">{{ t('common.noResults') }}</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
