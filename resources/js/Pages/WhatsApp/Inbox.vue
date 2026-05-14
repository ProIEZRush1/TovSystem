<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const props = defineProps({ account: Object, conversations: Array });

const search = ref('');
const activeConv = ref(null);
const messages = ref([]);
const loadingMessages = ref(false);
const newMessage = ref('');
const sending = ref(false);
const sendError = ref('');
const messagesContainer = ref(null);
const windowOpen = ref(false);

// Template state for closed window
const showTemplateForm = ref(false);
const availableTemplates = ref([]);
const templateName = ref('');
const templateLang = ref('es_MX');
const templateParams = ref({});
const sendingTemplate = ref(false);

let pollInterval = null;

const filteredConversations = computed(() => {
    if (!search.value) return props.conversations;
    const q = search.value.toLowerCase();
    return props.conversations.filter(c =>
        (c.contact_name || '').toLowerCase().includes(q) ||
        c.remote_phone.includes(q)
    );
});

function selectConversation(conv) {
    activeConv.value = conv;
    loadMessages();
    startPolling();
}

async function loadMessages() {
    if (!activeConv.value) return;
    loadingMessages.value = true;
    try {
        const r = await axios.get(route('whatsapp.inbox.messages', [props.account.id, activeConv.value.id]));
        messages.value = r.data.messages;
        windowOpen.value = r.data.window_open;
        showTemplateForm.value = false;
        await nextTick();
        scrollToBottom();
    } catch (e) {
        console.error('Failed to load messages', e);
    } finally {
        loadingMessages.value = false;
    }
}

function scrollToBottom() {
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
}

async function sendMessage() {
    if (!newMessage.value.trim() || !activeConv.value || sending.value) return;

    if (!windowOpen.value) {
        showTemplateForm.value = true;
        return;
    }

    sending.value = true;
    sendError.value = '';
    try {
        const r = await axios.post(route('whatsapp.inbox.send', [props.account.id, activeConv.value.id]), {
            message: newMessage.value,
            type: 'text',
        });
        messages.value.push(r.data);
        newMessage.value = '';
        await nextTick();
        scrollToBottom();
    } catch (e) {
        sendError.value = e.response?.data?.error || 'Error al enviar';
        setTimeout(() => { sendError.value = ''; }, 5000);
    } finally {
        sending.value = false;
    }
}

async function sendTemplateMsg() {
    if (!templateName.value || !activeConv.value) return;
    sendingTemplate.value = true;
    try {
        const components = [];
        const paramValues = Object.entries(templateParams.value).filter(([,v]) => v);
        if (paramValues.length) {
            components.push({
                type: 'body',
                parameters: paramValues.map(([k, v]) => ({ type: 'text', parameter_name: k, text: v })),
            });
        }
        const r = await axios.post(route('whatsapp.inbox.send-template', [props.account.id, activeConv.value.id]), {
            template_name: templateName.value,
            language_code: templateLang.value,
            components,
        });
        messages.value.push(r.data);
        showTemplateForm.value = false;
        templateName.value = '';
        templateParams.value = {};
        await nextTick();
        scrollToBottom();
    } catch (e) {
        sendError.value = e.response?.data?.error || 'Error al enviar template';
        setTimeout(() => { sendError.value = ''; }, 5000);
    } finally {
        sendingTemplate.value = false;
    }
}

async function loadTemplates() {
    try {
        const r = await axios.get(route('whatsapp.templates', props.account.id));
        availableTemplates.value = (r.data || []).filter(t => t.status === 'APPROVED');
    } catch (_) {}
}

function startPolling() {
    stopPolling();
    pollInterval = setInterval(async () => {
        if (!activeConv.value) return;
        try {
            const r = await axios.get(route('whatsapp.inbox.messages', [props.account.id, activeConv.value.id]));
            if (r.data.messages.length > messages.value.length) {
                messages.value = r.data.messages;
                windowOpen.value = r.data.window_open;
                await nextTick();
                scrollToBottom();
            }
        } catch (_) {}
    }, 5000);
}

function stopPolling() {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
}

onMounted(() => {
    loadTemplates();
    if (props.conversations.length) selectConversation(props.conversations[0]);
});
onUnmounted(() => stopPolling());

function formatTime(d) {
    if (!d) return '';
    return new Date(d).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function isMedia(type) {
    return ['image', 'video', 'audio', 'document'].includes(type);
}
</script>

<template>
    <Head :title="'Inbox — ' + account.name" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('whatsapp.index')" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                </Link>
                <h2 class="text-xl font-bold text-slate-900">Inbox — {{ account.name }}</h2>
                <div class="ml-auto flex gap-2">
                    <Link :href="route('whatsapp.campaigns.index', account.id)" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">Campanas</Link>
                    <Link :href="route('whatsapp.templates-page', account.id)" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">Plantillas</Link>
                </div>
            </div>
        </template>

        <div class="flex gap-4 h-[calc(100vh-12rem)]">
            <!-- Conversations sidebar -->
            <div class="w-80 flex-shrink-0 rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 flex flex-col overflow-hidden">
                <div class="p-3 border-b border-slate-100">
                    <input v-model="search" type="text" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Buscar..." />
                </div>
                <div class="flex-1 overflow-y-auto">
                    <button
                        v-for="conv in filteredConversations" :key="conv.id"
                        @click="selectConversation(conv)"
                        :class="['w-full text-left px-4 py-3 border-b border-slate-50 hover:bg-slate-50 transition', activeConv?.id === conv.id ? 'bg-brand-50' : '']"
                    >
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-900 truncate">{{ conv.contact_name || '+' + conv.remote_phone }}</p>
                            <div class="flex items-center gap-1.5">
                                <span v-if="conv.unread_count" class="inline-flex items-center justify-center h-5 min-w-5 rounded-full bg-green-600 text-white text-xs font-bold px-1">{{ conv.unread_count }}</span>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500 truncate mt-0.5">{{ conv.last_message_preview || '+' + conv.remote_phone }}</p>
                    </button>
                    <p v-if="!filteredConversations.length" class="px-4 py-8 text-center text-sm text-slate-400">Sin conversaciones</p>
                </div>
            </div>

            <!-- Chat area -->
            <div class="flex-1 rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 flex flex-col overflow-hidden">
                <template v-if="activeConv">
                    <!-- Header -->
                    <div class="shrink-0 px-4 py-3 border-b border-slate-100 flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-green-100 text-green-700 text-sm font-bold">WA</div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900 truncate">{{ activeConv.contact_name || '+' + activeConv.remote_phone }}</p>
                            <p class="text-xs text-slate-500">+{{ activeConv.remote_phone }}</p>
                        </div>
                        <span v-if="windowOpen" class="text-xs text-green-600 font-medium">Ventana abierta</span>
                        <span v-else class="text-xs text-amber-600 font-medium">Solo plantillas</span>
                    </div>

                    <!-- Messages -->
                    <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50">
                        <div v-if="loadingMessages" class="flex justify-center py-8">
                            <svg class="animate-spin h-6 w-6 text-brand-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
                        </div>
                        <div v-for="msg in messages" :key="msg.id" :class="['max-w-[70%] rounded-2xl px-4 py-2 text-sm', msg.direction === 'outbound' ? 'ml-auto bg-green-600 text-white rounded-br-md' : 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-900/5 rounded-bl-md']">
                            <!-- Media -->
                            <div v-if="msg.media_url" class="mb-2">
                                <img v-if="msg.type === 'image'" :src="msg.media_url" class="rounded-lg max-w-full max-h-48 object-cover" />
                                <video v-else-if="msg.type === 'video'" :src="msg.media_url" controls class="rounded-lg max-w-full max-h-48" />
                                <audio v-else-if="msg.type === 'audio'" :src="msg.media_url" controls class="max-w-full" />
                                <a v-else-if="msg.type === 'document'" :href="msg.media_url" target="_blank" class="inline-flex items-center gap-1 text-xs underline">{{ msg.media_filename || 'Documento' }}</a>
                            </div>
                            <p class="whitespace-pre-wrap">{{ msg.content }}</p>
                            <p v-if="msg.template_name" class="text-xs opacity-75 mt-1">{{ msg.template_name }}</p>
                            <p v-if="msg.status === 'failed' && msg.error_message" :class="['text-xs mt-1 rounded px-2 py-1 font-medium', msg.direction === 'outbound' ? 'bg-red-500/20' : 'bg-red-50 text-red-700']">{{ msg.error_message }}</p>
                            <div class="flex items-center justify-end gap-1 mt-1">
                                <span :class="['text-xs', msg.direction === 'outbound' ? 'text-green-200' : 'text-slate-400']">{{ formatTime(msg.sent_at || msg.created_at) }}</span>
                                <span v-if="msg.direction === 'outbound'" class="inline-flex items-center">
                                    <svg v-if="msg.status === 'failed'" class="h-3.5 w-3.5 text-red-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                    <svg v-else-if="msg.status === 'read'" class="h-3.5 w-3.5 text-blue-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18" fill="currentColor"><path d="M17.394 5.035l-.57-.444a.434.434 0 00-.609.076l-6.39 8.198a.38.38 0 01-.577.039l-.427-.388a.381.381 0 00-.578.038l-.451.576a.497.497 0 00.043.645l1.575 1.51a.38.38 0 00.577-.039l7.483-9.602a.436.436 0 00-.076-.609zm-4.892 0l-.57-.444a.434.434 0 00-.609.076l-6.39 8.198a.38.38 0 01-.577.039L2.614 9.8a.436.436 0 00-.614.074l-.452.595a.436.436 0 00.075.615l3.265 3.13a.38.38 0 00.577-.038l7.483-9.602a.436.436 0 00-.076-.609z"/></svg>
                                    <svg v-else-if="msg.status === 'delivered'" class="h-3.5 w-3.5 text-green-200" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18" fill="currentColor"><path d="M17.394 5.035l-.57-.444a.434.434 0 00-.609.076l-6.39 8.198a.38.38 0 01-.577.039l-.427-.388a.381.381 0 00-.578.038l-.451.576a.497.497 0 00.043.645l1.575 1.51a.38.38 0 00.577-.039l7.483-9.602a.436.436 0 00-.076-.609zm-4.892 0l-.57-.444a.434.434 0 00-.609.076l-6.39 8.198a.38.38 0 01-.577.039L2.614 9.8a.436.436 0 00-.614.074l-.452.595a.436.436 0 00.075.615l3.265 3.13a.38.38 0 00.577-.038l7.483-9.602a.436.436 0 00-.076-.609z"/></svg>
                                    <svg v-else class="h-3.5 w-3.5 text-green-200" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18" fill="currentColor"><path d="M15.01 3.316l-.478-.372a.365.365 0 00-.51.063L8.666 9.879a.32.32 0 01-.484.033l-.358-.325a.319.319 0 00-.484.032l-.378.483a.418.418 0 00.036.541l1.32 1.266a.32.32 0 00.484-.033l6.272-8.048a.366.366 0 00-.064-.512z"/></svg>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Error banner -->
                    <div v-if="sendError" class="mx-3 mt-2 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">{{ sendError }}</div>

                    <!-- Template form (when window closed) -->
                    <div v-if="showTemplateForm && !windowOpen" class="shrink-0 border-t border-slate-100 p-3 bg-amber-50 space-y-2">
                        <p class="text-xs text-amber-700 font-medium">Ventana de 24h cerrada. Solo puedes enviar plantillas aprobadas.</p>
                        <select v-model="templateName" class="block w-full rounded-lg border-slate-300 text-sm">
                            <option value="">-- Selecciona plantilla --</option>
                            <option v-for="t in availableTemplates" :key="t.id" :value="t.name">{{ t.name }} ({{ t.language }})</option>
                        </select>
                        <button @click="sendTemplateMsg" :disabled="!templateName || sendingTemplate" class="rounded-lg bg-brand-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50 transition">
                            {{ sendingTemplate ? 'Enviando...' : 'Enviar plantilla' }}
                        </button>
                    </div>

                    <!-- Input -->
                    <div class="shrink-0 p-3 border-t border-slate-100 flex gap-2">
                        <input v-model="newMessage" type="text" class="flex-1 rounded-full border-slate-300 text-sm px-4 focus:border-brand-500 focus:ring-brand-500" placeholder="Escribe un mensaje..." @keyup.enter="sendMessage" :disabled="sending" />
                        <button @click="sendMessage" :disabled="sending || !newMessage.trim()" class="rounded-full bg-green-600 p-2.5 text-white hover:bg-green-500 disabled:opacity-50 transition">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" /></svg>
                        </button>
                    </div>
                </template>
                <div v-else class="flex-1 flex items-center justify-center">
                    <p class="text-sm text-slate-400">Selecciona una conversacion</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
