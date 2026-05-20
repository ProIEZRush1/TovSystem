<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({ account: Object, stats: Object, dailyStats: Array, filters: Object });

const from = ref(props.filters.from);
const to = ref(props.filters.to);

function applyFilter() {
    router.get(route('whatsapp.billing', props.account.id), { from: from.value, to: to.value }, { preserveState: true });
}

const dailyGrouped = (() => {
    const map = {};
    (props.dailyStats || []).forEach(d => {
        if (!map[d.date]) map[d.date] = { date: d.date, templates: 0, text: 0, delivered: 0, failed: 0, total: 0 };
        map[d.date].total += d.cnt;
        if (d.type === 'template') map[d.date].templates += d.cnt;
        if (d.type === 'text') map[d.date].text += d.cnt;
        if (['delivered', 'read'].includes(d.status)) map[d.date].delivered += d.cnt;
        if (d.status === 'failed') map[d.date].failed += d.cnt;
    });
    return Object.values(map).sort((a, b) => b.date.localeCompare(a.date));
})();
</script>

<template>
    <Head title="Facturacion" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('whatsapp.index')" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                </Link>
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Facturacion — {{ account.name }}</h2>
                    <p class="text-sm text-slate-500">{{ account.phone_number }}</p>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Date filter -->
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
                <div class="flex items-end gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Desde</label>
                        <input v-model="from" type="date" class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Hasta</label>
                        <input v-model="to" type="date" class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" />
                    </div>
                    <button @click="applyFilter" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500 transition">Filtrar</button>
                </div>
            </div>

            <!-- Cost summary -->
            <div class="rounded-xl bg-gradient-to-r from-brand-600 to-brand-700 p-6 text-white shadow-lg">
                <p class="text-sm font-medium text-brand-200">Costo estimado del periodo</p>
                <p class="text-4xl font-bold mt-1">${{ stats.estimated_cost_mxn.toLocaleString('es-MX', { minimumFractionDigits: 2 }) }} MXN</p>
                <p class="text-xs text-brand-300 mt-2">Basado en {{ stats.templates_delivered.toLocaleString() }} templates entregados x ${{ stats.marketing_rate }} MXN (tarifa Marketing)</p>
            </div>

            <!-- Stats grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5 text-center">
                    <p class="text-2xl font-bold text-slate-900">{{ stats.total_outbound.toLocaleString() }}</p>
                    <p class="text-xs text-slate-500 mt-1">Total enviados</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5 text-center">
                    <p class="text-2xl font-bold text-blue-600">{{ stats.templates_sent.toLocaleString() }}</p>
                    <p class="text-xs text-slate-500 mt-1">Templates enviados</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5 text-center">
                    <p class="text-2xl font-bold text-green-600">{{ stats.templates_delivered.toLocaleString() }}</p>
                    <p class="text-xs text-slate-500 mt-1">Templates entregados</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5 text-center">
                    <p class="text-2xl font-bold text-red-600">{{ stats.templates_failed.toLocaleString() }}</p>
                    <p class="text-xs text-slate-500 mt-1">Templates fallidos</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5 text-center">
                    <p class="text-2xl font-bold text-slate-700">{{ stats.text_sent.toLocaleString() }}</p>
                    <p class="text-xs text-slate-500 mt-1">Textos enviados</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5 text-center">
                    <p class="text-2xl font-bold text-emerald-600">{{ stats.total_inbound.toLocaleString() }}</p>
                    <p class="text-xs text-slate-500 mt-1">Recibidos</p>
                </div>
            </div>

            <!-- Pricing info -->
            <div class="rounded-xl bg-amber-50 border border-amber-200 p-4">
                <p class="text-sm font-semibold text-amber-800 mb-2">Tarifas de WhatsApp Business (Mexico)</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                    <div class="bg-white rounded-lg p-3 border border-amber-100">
                        <p class="font-bold text-amber-700">${{ stats.marketing_rate }} MXN</p>
                        <p class="text-xs text-slate-500">Marketing (templates)</p>
                    </div>
                    <div class="bg-white rounded-lg p-3 border border-amber-100">
                        <p class="font-bold text-amber-700">${{ stats.utility_rate }} MXN</p>
                        <p class="text-xs text-slate-500">Utilidad (templates)</p>
                    </div>
                    <div class="bg-white rounded-lg p-3 border border-amber-100">
                        <p class="font-bold text-amber-700">Gratis</p>
                        <p class="text-xs text-slate-500">Servicio (ventana 24h)</p>
                    </div>
                    <div class="bg-white rounded-lg p-3 border border-amber-100">
                        <p class="font-bold text-amber-700">1,000/mes</p>
                        <p class="text-xs text-slate-500">Conversaciones gratis</p>
                    </div>
                </div>
            </div>

            <!-- Daily breakdown -->
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100">
                    <h3 class="text-sm font-semibold text-slate-700">Desglose diario</h3>
                </div>
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-slate-100">
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500">Fecha</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-slate-500">Templates</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-slate-500">Textos</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-slate-500">Entregados</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-slate-500">Fallidos</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-slate-500">Total</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-slate-500">Costo est.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="d in dailyGrouped" :key="d.date" class="hover:bg-slate-50">
                            <td class="px-4 py-2 text-sm font-medium text-slate-700">{{ d.date }}</td>
                            <td class="px-4 py-2 text-sm text-right text-blue-600">{{ d.templates.toLocaleString() }}</td>
                            <td class="px-4 py-2 text-sm text-right text-slate-600">{{ d.text.toLocaleString() }}</td>
                            <td class="px-4 py-2 text-sm text-right text-green-600">{{ d.delivered.toLocaleString() }}</td>
                            <td class="px-4 py-2 text-sm text-right text-red-600">{{ d.failed.toLocaleString() }}</td>
                            <td class="px-4 py-2 text-sm text-right font-semibold text-slate-900">{{ d.total.toLocaleString() }}</td>
                            <td class="px-4 py-2 text-sm text-right font-semibold text-brand-600">${{ (d.delivered * 0.53).toFixed(2) }}</td>
                        </tr>
                        <tr v-if="!dailyGrouped.length">
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">No hay datos para el periodo seleccionado</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
