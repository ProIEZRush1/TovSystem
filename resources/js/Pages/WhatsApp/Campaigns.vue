<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const { t } = useI18n();
const props = defineProps({ account: Object, campaigns: Array });

function statusColor(s) {
    return { draft: 'bg-slate-100 text-slate-600', sending: 'bg-amber-50 text-amber-700', completed: 'bg-green-50 text-green-700', failed: 'bg-red-50 text-red-700', cancelled: 'bg-slate-50 text-slate-500' }[s] || 'bg-slate-100 text-slate-600';
}
function statusLabel(s) {
    return { draft: 'Borrador', sending: 'Enviando...', completed: 'Completada', failed: 'Fallida', cancelled: 'Cancelada' }[s] || s;
}
</script>

<template>
    <Head :title="'Campanas — ' + account.name" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('whatsapp.index')" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                </Link>
                <h2 class="text-xl font-bold text-slate-900">Campanas — {{ account.name }}</h2>
                <div class="ml-auto">
                    <Link :href="route('whatsapp.campaigns.create', account.id)" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500 transition">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Nueva Campana
                    </Link>
                </div>
            </div>
        </template>

        <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 overflow-hidden">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Nombre</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Template</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Progreso</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Destinatarios</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Fecha</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="c in campaigns" :key="c.id">
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ c.name }}</td>
                        <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ c.template_name }}</td>
                        <td class="px-4 py-3"><span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', statusColor(c.status)]">{{ statusLabel(c.status) }}</span></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-brand-600 rounded-full transition-all" :style="{ width: c.progress + '%' }"></div>
                                </div>
                                <span class="text-xs text-slate-500">{{ c.progress }}%</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ c.total_recipients.toLocaleString() }}</td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ new Date(c.created_at).toLocaleDateString() }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="route('whatsapp.campaigns.show', [account.id, c.id])" class="text-sm font-medium text-brand-600 hover:text-brand-500">Ver</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="!campaigns.length" class="px-6 py-12 text-center text-sm text-slate-400">
                No hay campanas aun. Crea la primera.
            </div>
        </div>
    </AuthenticatedLayout>
</template>
