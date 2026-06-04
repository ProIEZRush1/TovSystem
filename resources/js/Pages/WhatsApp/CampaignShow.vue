<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const props = defineProps({ account: Object, campaign: Object, statusCounts: Object, recentMessages: Array });

const liveStatus = ref(props.campaign.status);
const liveSent = ref(props.campaign.sent_count);
const liveFailed = ref(props.campaign.failed_count);
const liveDelivered = ref(props.campaign.delivered_count);
const liveRead = ref(props.campaign.read_count);
const liveProgress = ref(props.campaign.progress);

let pollTimer = null;

async function pollStatus() {
    try {
        const r = await axios.get(route('whatsapp.campaigns.status', [props.account.id, props.campaign.id]));
        liveStatus.value = r.data.status;
        liveSent.value = r.data.sent_count;
        liveFailed.value = r.data.failed_count;
        liveDelivered.value = r.data.delivered_count;
        liveRead.value = r.data.read_count;
        liveProgress.value = r.data.progress;
        if (r.data.status === 'completed' || r.data.status === 'failed' || r.data.status === 'cancelled') {
            clearInterval(pollTimer);
        }
    } catch (_) {}
}

onMounted(() => {
    if (['sending', 'completed'].includes(props.campaign.status)) {
        pollTimer = setInterval(pollStatus, props.campaign.status === 'sending' ? 5000 : 30000);
    }
});
onUnmounted(() => { if (pollTimer) clearInterval(pollTimer); });

function sendCampaign() {
    if (!confirm('Enviar esta campana a ' + props.campaign.total_recipients + ' contactos?')) return;
    router.post(route('whatsapp.campaigns.send', [props.account.id, props.campaign.id]));
}
function cancelCampaign() {
    if (!confirm('Cancelar esta campana?')) return;
    router.post(route('whatsapp.campaigns.cancel', [props.account.id, props.campaign.id]));
}

function statusColor(s) {
    return { pending: 'text-slate-500', sent: 'text-blue-600', delivered: 'text-green-600', read: 'text-emerald-600', failed: 'text-red-600' }[s] || 'text-slate-500';
}
</script>

<template>
    <Head :title="campaign.name" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('whatsapp.campaigns.index', account.id)" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                </Link>
                <div>
                    <h2 class="text-xl font-bold text-slate-900">{{ campaign.name }}</h2>
                    <p class="text-sm text-slate-500">Template: {{ campaign.template_name }} ({{ campaign.template_language }})</p>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <span :class="['inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold', liveStatus === 'draft' ? 'bg-amber-100 text-amber-700' : liveStatus === 'sending' ? 'bg-blue-100 text-blue-700' : liveStatus === 'completed' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600']">{{ liveStatus === 'draft' ? 'Borrador' : liveStatus === 'sending' ? 'Enviando...' : liveStatus === 'completed' ? 'Completada' : liveStatus === 'cancelled' ? 'Cancelada' : liveStatus }}</span>
                    <button v-if="liveStatus === 'sending'" @click="cancelCampaign" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 transition">
                        Cancelar envio
                    </button>
                </div>
            </div>
        </template>

        <!-- Stats cards -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5 text-center">
                <p class="text-2xl font-bold text-slate-900">{{ campaign.total_recipients.toLocaleString() }}</p>
                <p class="text-xs text-slate-500">Total</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ liveSent.toLocaleString() }}</p>
                <p class="text-xs text-slate-500">Enviados</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5 text-center">
                <p class="text-2xl font-bold text-green-600">{{ liveDelivered.toLocaleString() }}</p>
                <p class="text-xs text-slate-500">Entregados</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5 text-center">
                <p class="text-2xl font-bold text-emerald-600">{{ liveRead.toLocaleString() }}</p>
                <p class="text-xs text-slate-500">Leidos</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5 text-center">
                <p class="text-2xl font-bold text-red-600">{{ liveFailed.toLocaleString() }}</p>
                <p class="text-xs text-slate-500">Fallidos</p>
            </div>
        </div>

        <!-- Draft: big send button -->
        <div v-if="liveStatus === 'draft'" class="mb-6 rounded-xl bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 p-6 text-center">
            <p class="text-lg font-semibold text-green-800 mb-2">Campana lista para enviar</p>
            <p class="text-sm text-green-600 mb-4">{{ campaign.total_recipients.toLocaleString() }} contactos recibiran el template "{{ campaign.template_name }}"</p>
            <button @click="sendCampaign" class="rounded-xl bg-green-600 px-8 py-3 text-base font-bold text-white hover:bg-green-500 shadow-lg shadow-green-200 transition">
                Iniciar envio a {{ campaign.total_recipients.toLocaleString() }} contactos
            </button>
        </div>

        <!-- Progress bar -->
        <div v-if="liveStatus === 'sending'" class="mb-6 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-medium text-slate-700">Enviando...</p>
                <p class="text-sm font-bold text-brand-600">{{ liveProgress }}%</p>
            </div>
            <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-brand-600 rounded-full transition-all duration-500" :style="{ width: liveProgress + '%' }"></div>
            </div>
        </div>

        <!-- Recent messages -->
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-700">Ultimos mensajes</h3>
            </div>
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="px-4 py-2 text-left text-xs font-medium text-slate-500">Telefono</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-slate-500">Nombre</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-slate-500">Estado</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-slate-500">Error</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-slate-500">Enviado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="m in recentMessages" :key="m.id">
                        <td class="px-4 py-2 text-sm font-mono text-slate-700">{{ m.phone }}</td>
                        <td class="px-4 py-2 text-sm text-slate-600">{{ m.contact_name || '-' }}</td>
                        <td class="px-4 py-2"><span :class="['text-xs font-medium', statusColor(m.status)]">{{ m.status }}</span></td>
                        <td class="px-4 py-2 text-xs text-red-600 max-w-xs truncate">{{ m.error_message || '-' }}</td>
                        <td class="px-4 py-2 text-xs text-slate-400">{{ m.sent_at ? new Date(m.sent_at).toLocaleString() : '-' }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-if="!recentMessages.length" class="px-6 py-8 text-center text-sm text-slate-400">
                {{ liveStatus === 'draft' ? 'Aun no se ha enviado. Presiona "Enviar" para comenzar.' : 'Sin mensajes aun.' }}
            </div>
        </div>
    </AuthenticatedLayout>
</template>
