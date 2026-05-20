<script setup>
import { ref, watch, nextTick, onMounted, onUnmounted, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { usePermissions } from '@/composables/usePermissions';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const { t } = useI18n();
const { can } = usePermissions();

const props = defineProps({
    account: Object,
    conversations: Array,
});

const activePhone = ref(null);
const messages = ref([]);
const loadingMessages = ref(false);
const newMessage = ref('');
const sending = ref(false);
const messagesContainer = ref(null);
const searchConv = ref('');

// Bulk send state
const showBulkSend = ref(false);
const bulkPhones = ref('');
const bulkType = ref('text');
const bulkMessage = ref('');
const bulkTemplateName = ref('');
const bulkLanguage = ref('es_MX');
const bulkSending = ref(false);
const bulkResult = ref(null);

// Templates
const availableTemplates = ref([]);
const templatesLoading = ref(false);
const templateParams = ref({}); // param_name => value
const selectedTemplate = computed(() => availableTemplates.value.find(t => t.name === bulkTemplateName.value));
const selectedBodyParams = computed(() => {
    if (!selectedTemplate.value) return [];
    const body = (selectedTemplate.value.components || []).find(c => c.type === 'BODY');
    if (!body) return [];
    const matches = (body.text || '').match(/\{\{([a-z0-9_]+)\}\}/gi) || [];
    return [...new Set(matches.map(m => m.replace(/[{}]/g, '')))];
});
const selectedHasFlowButton = computed(() => {
    if (!selectedTemplate.value) return false;
    const buttonsComp = (selectedTemplate.value.components || []).find(c => c.type === 'BUTTONS');
    return !!(buttonsComp?.buttons || []).find(b => b.type === 'FLOW');
});

const selectedHeaderType = computed(() => {
    if (!selectedTemplate.value) return null;
    const header = (selectedTemplate.value.components || []).find(c => c.type === 'HEADER');
    return header?.format || null;
});
const needsHeaderMedia = computed(() => ['IMAGE', 'VIDEO'].includes(selectedHeaderType.value));
const bulkMediaFile = ref(null);
const bulkMediaPreview = ref(null);
const bulkMediaUploading = ref(false);
const bulkMediaId = ref(null);
const bulkMediaError = ref('');

function onBulkMediaSelected(e) {
    const file = e.target.files[0];
    if (!file) return;
    bulkMediaFile.value = file;
    bulkMediaPreview.value = URL.createObjectURL(file);
    bulkMediaId.value = null;
    bulkMediaError.value = '';
}
async function uploadBulkMedia() {
    if (!bulkMediaFile.value) return;
    bulkMediaUploading.value = true;
    bulkMediaError.value = '';
    try {
        const fd = new FormData();
        fd.append('file', bulkMediaFile.value);
        const r = await axios.post(route('whatsapp.upload-media', props.account.id), fd, { headers: { 'Content-Type': 'multipart/form-data' } });
        bulkMediaId.value = r.data.media_id;
    } catch (e) {
        bulkMediaError.value = e.response?.data?.error || 'Upload failed';
    } finally {
        bulkMediaUploading.value = false;
    }
}

async function loadTemplates() {
    if (templatesLoading.value) return;
    templatesLoading.value = true;
    try {
        const r = await axios.get(route('whatsapp.templates', props.account.id));
        availableTemplates.value = (r.data || []).filter(t => t.status === 'APPROVED');
    } catch (e) {
        console.error('Failed to load templates', e);
    } finally {
        templatesLoading.value = false;
    }
}

function paramLabel(p) {
    return '{{' + p + '}}';
}

function buildTemplateComponents() {
    const components = [];
    if (needsHeaderMedia.value && bulkMediaId.value) {
        const mediaType = selectedHeaderType.value === 'VIDEO' ? 'video' : 'image';
        components.push({
            type: 'header',
            parameters: [{ type: mediaType, [mediaType]: { id: bulkMediaId.value } }],
        });
    }
    if (selectedBodyParams.value.length) {
        components.push({
            type: 'body',
            parameters: selectedBodyParams.value.map(p => ({
                type: 'text',
                parameter_name: p,
                text: templateParams.value[p] || '',
            })),
        });
    }
    if (selectedHasFlowButton.value) {
        components.push({
            type: 'button',
            sub_type: 'flow',
            index: '0',
            parameters: [{ type: 'action', action: { flow_token: 'tov_' + Date.now() } }],
        });
    }
    return components;
}

// Polling for new messages
let pollInterval = null;

const filteredConversations = ref([...props.conversations]);

watch(searchConv, (val) => {
    if (!val) {
        filteredConversations.value = [...props.conversations];
    } else {
        const q = val.toLowerCase();
        filteredConversations.value = props.conversations.filter(c =>
            c.remote_phone.includes(q) || (c.contact_name && c.contact_name.toLowerCase().includes(q))
        );
    }
});

async function selectConversation(phone) {
    activePhone.value = phone;
    await loadMessages();
    startPolling();
}

async function loadMessages() {
    if (!activePhone.value) return;
    loadingMessages.value = true;
    try {
        const response = await axios.get(route('whatsapp.messages', props.account.id), {
            params: { phone: activePhone.value },
        });
        messages.value = response.data;
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

const sendError = ref('');

async function sendMessage() {
    if (!newMessage.value.trim() || !activePhone.value || sending.value) return;
    sending.value = true;
    sendError.value = '';
    try {
        const response = await axios.post(route('whatsapp.send', props.account.id), {
            phone: activePhone.value,
            message: newMessage.value,
        });
        messages.value.push(response.data);
        newMessage.value = '';
        await nextTick();
        scrollToBottom();
    } catch (e) {
        sendError.value = e.response?.data?.error || 'Failed to send message';
        setTimeout(() => { sendError.value = ''; }, 5000);
    } finally {
        sending.value = false;
    }
}

function startPolling() {
    stopPolling();
    pollInterval = setInterval(async () => {
        if (!activePhone.value) return;
        try {
            const response = await axios.get(route('whatsapp.messages', props.account.id), {
                params: { phone: activePhone.value },
            });
            if (response.data.length > messages.value.length) {
                messages.value = response.data;
                await nextTick();
                scrollToBottom();
            }
        } catch (_) {}
    }, 5000);
}

function stopPolling() {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

onMounted(() => {
    if (props.conversations.length > 0) {
        selectConversation(props.conversations[0].remote_phone);
    }
});

onUnmounted(() => stopPolling());

// Start new chat
const newChatPhone = ref('');
const showNewChat = ref(false);

function startNewChat() {
    if (!newChatPhone.value.trim()) return;
    const phone = newChatPhone.value.replace(/[^0-9]/g, '');
    activePhone.value = phone;
    messages.value = [];
    newChatPhone.value = '';
    showNewChat.value = false;
    startPolling();
}

// Bulk send
async function submitBulkSend() {
    if (bulkSending.value) return;
    const phones = bulkPhones.value.split('\n').map(p => p.trim()).filter(Boolean);
    if (!phones.length) return;
    bulkSending.value = true;
    bulkResult.value = null;
    try {
        const payload = {
            phones,
            type: bulkType.value,
            message: bulkMessage.value || null,
            template_name: bulkTemplateName.value || null,
            language_code: bulkLanguage.value,
        };
        if (bulkType.value === 'template') {
            payload.components = buildTemplateComponents();
            payload.language_code = selectedTemplate.value?.language || bulkLanguage.value;
        }
        const response = await axios.post(route('whatsapp.bulk-send', props.account.id), payload);
        bulkResult.value = response.data;
    } catch (e) {
        bulkResult.value = { error: e.response?.data?.error || 'Bulk send failed' };
    } finally {
        bulkSending.value = false;
    }
}

// Load templates when switching to template mode or opening modal
watch(bulkType, (val) => {
    if (val === 'template' && !availableTemplates.value.length) loadTemplates();
});
watch(showBulkSend, (val) => {
    if (val && bulkType.value === 'template' && !availableTemplates.value.length) loadTemplates();
});

// Reset params when template changes
watch(bulkTemplateName, () => {
    templateParams.value = {};
});

function formatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString();
}
</script>

<template>
    <Head :title="t('whatsapp.chat') + ' - ' + account.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('whatsapp.index')" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                </Link>
                <h2 class="text-xl font-bold text-slate-900">{{ account.name }}</h2>
                <span class="text-sm text-slate-500">{{ account.phone_number }}</span>
                <div class="ml-auto flex items-center gap-2">
                    <button v-if="can('whatsapp.manage')" @click="showBulkSend = true" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-500 transition">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                        </svg>
                        {{ t('whatsapp.bulkSend') }}
                    </button>
                </div>
            </div>
        </template>

        <div class="flex gap-4 h-[calc(100vh-12rem)]">
            <!-- Conversations sidebar -->
            <div class="w-80 flex-shrink-0 rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 flex flex-col overflow-hidden">
                <div class="p-3 border-b border-slate-100">
                    <div class="flex gap-2">
                        <input v-model="searchConv" type="text" class="flex-1 rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" :placeholder="t('common.search')" />
                        <button @click="showNewChat = !showNewChat" class="rounded-lg bg-brand-600 p-2 text-white hover:bg-brand-500 transition">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>
                    </div>
                    <div v-if="showNewChat" class="mt-2 flex gap-2">
                        <input v-model="newChatPhone" type="text" class="flex-1 rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="+5215512345678" @keyup.enter="startNewChat" />
                        <button @click="startNewChat" class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-500 transition">OK</button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto">
                    <button
                        v-for="conv in filteredConversations"
                        :key="conv.remote_phone"
                        @click="selectConversation(conv.remote_phone)"
                        :class="[
                            'w-full text-left px-4 py-3 border-b border-slate-50 hover:bg-slate-50 transition',
                            activePhone === conv.remote_phone ? 'bg-brand-50' : ''
                        ]"
                    >
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-900 truncate">{{ conv.contact_name || '+' + conv.remote_phone }}</p>
                            <span class="text-xs text-slate-400">{{ conv.message_count }}</span>
                        </div>
                        <p class="text-xs text-slate-500 truncate">+{{ conv.remote_phone }}</p>
                    </button>
                    <p v-if="!filteredConversations.length" class="px-4 py-8 text-center text-sm text-slate-400">{{ t('whatsapp.noConversations') }}</p>
                </div>
            </div>

            <!-- Chat area -->
            <div class="flex-1 rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 flex flex-col overflow-hidden">
                <template v-if="activePhone">
                    <!-- Chat header -->
                    <div class="px-4 py-3 border-b border-slate-100 flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 text-green-700 text-xs font-bold">WA</div>
                        <div>
                            <p class="text-sm font-medium text-slate-900">+{{ activePhone }}</p>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50">
                        <div v-if="loadingMessages" class="flex justify-center py-8">
                            <svg class="animate-spin h-6 w-6 text-brand-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <div
                            v-for="msg in messages"
                            :key="msg.id"
                            :class="[
                                'max-w-[70%] rounded-2xl px-4 py-2 text-sm',
                                msg.direction === 'outbound'
                                    ? 'ml-auto bg-green-600 text-white rounded-br-md'
                                    : 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-900/5 rounded-bl-md'
                            ]"
                        >
                            <p class="whitespace-pre-wrap">{{ msg.content }}</p>
                            <p v-if="msg.template_name" class="text-xs opacity-75 mt-1">{{ msg.template_name }}</p>
                            <p v-if="msg.status === 'failed' && msg.error_message" class="text-xs mt-1 rounded bg-red-500/20 px-2 py-1 font-medium">
                                ⚠️ {{ msg.error_message }}
                            </p>
                            <div class="flex items-center justify-end gap-1 mt-1">
                                <span :class="['text-xs', msg.direction === 'outbound' ? 'text-green-200' : 'text-slate-400']">
                                    {{ formatTime(msg.sent_at || msg.created_at) }}
                                </span>
                                <!-- WhatsApp-style ticks -->
                                <span v-if="msg.direction === 'outbound'" class="inline-flex items-center">
                                    <!-- Failed: red X -->
                                    <svg v-if="msg.status === 'failed'" class="h-3.5 w-3.5 text-red-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                    <!-- Read: double check blue -->
                                    <svg v-else-if="msg.status === 'read'" class="h-3.5 w-3.5 text-blue-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18" fill="currentColor">
                                        <path d="M17.394 5.035l-.57-.444a.434.434 0 00-.609.076l-6.39 8.198a.38.38 0 01-.577.039l-.427-.388a.381.381 0 00-.578.038l-.451.576a.497.497 0 00.043.645l1.575 1.51a.38.38 0 00.577-.039l7.483-9.602a.436.436 0 00-.076-.609zm-4.892 0l-.57-.444a.434.434 0 00-.609.076l-6.39 8.198a.38.38 0 01-.577.039L2.614 9.8a.436.436 0 00-.614.074l-.452.595a.436.436 0 00.075.615l3.265 3.13a.38.38 0 00.577-.038l7.483-9.602a.436.436 0 00-.076-.609z"/>
                                    </svg>
                                    <!-- Delivered: double check gray -->
                                    <svg v-else-if="msg.status === 'delivered'" class="h-3.5 w-3.5 text-green-200" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18" fill="currentColor">
                                        <path d="M17.394 5.035l-.57-.444a.434.434 0 00-.609.076l-6.39 8.198a.38.38 0 01-.577.039l-.427-.388a.381.381 0 00-.578.038l-.451.576a.497.497 0 00.043.645l1.575 1.51a.38.38 0 00.577-.039l7.483-9.602a.436.436 0 00-.076-.609zm-4.892 0l-.57-.444a.434.434 0 00-.609.076l-6.39 8.198a.38.38 0 01-.577.039L2.614 9.8a.436.436 0 00-.614.074l-.452.595a.436.436 0 00.075.615l3.265 3.13a.38.38 0 00.577-.038l7.483-9.602a.436.436 0 00-.076-.609z"/>
                                    </svg>
                                    <!-- Sent: single check -->
                                    <svg v-else class="h-3.5 w-3.5 text-green-200" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18" fill="currentColor">
                                        <path d="M15.01 3.316l-.478-.372a.365.365 0 00-.51.063L8.666 9.879a.32.32 0 01-.484.033l-.358-.325a.319.319 0 00-.484.032l-.378.483a.418.418 0 00.036.541l1.32 1.266a.32.32 0 00.484-.033l6.272-8.048a.366.366 0 00-.064-.512z"/>
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Error banner -->
                    <div v-if="sendError" class="mx-3 mt-2 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
                        {{ sendError }}
                    </div>

                    <!-- Input -->
                    <div class="p-3 border-t border-slate-100 flex gap-2">
                        <input
                            v-model="newMessage"
                            type="text"
                            class="flex-1 rounded-full border-slate-300 text-sm px-4 focus:border-brand-500 focus:ring-brand-500"
                            :placeholder="t('whatsapp.typeMessage')"
                            @keyup.enter="sendMessage"
                            :disabled="sending"
                        />
                        <button @click="sendMessage" :disabled="sending || !newMessage.trim()" class="rounded-full bg-green-600 p-2.5 text-white hover:bg-green-500 disabled:opacity-50 transition">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                            </svg>
                        </button>
                    </div>
                </template>

                <div v-else class="flex-1 flex items-center justify-center">
                    <p class="text-sm text-slate-400">{{ t('whatsapp.noConversations') }}</p>
                </div>
            </div>
        </div>

        <!-- Bulk Send Modal -->
        <teleport to="body">
            <transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showBulkSend" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4" @click.self="showBulkSend = false">
                    <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                        <div class="shrink-0 border-b border-slate-100 px-6 py-4 flex items-center justify-between">
                            <h3 class="text-lg font-bold text-slate-900">{{ t('whatsapp.bulkSendTitle') }}</h3>
                            <button @click="showBulkSend = false" class="text-slate-400 hover:text-slate-600">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.messageType') }}</label>
                                <select v-model="bulkType" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="text">{{ t('whatsapp.textMessage') }}</option>
                                    <option value="template">{{ t('whatsapp.templateMessage') }}</option>
                                </select>
                            </div>
                            <div v-if="bulkType === 'text'">
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.typeMessage') }}</label>
                                <textarea v-model="bulkMessage" rows="3" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                            </div>
                            <div v-if="bulkType === 'template'">
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.templateName') }}</label>
                                <select v-model="bulkTemplateName" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">{{ templatesLoading ? t('common.loading') : t('whatsapp.tplSelect') }}</option>
                                    <option v-for="tpl in availableTemplates" :key="tpl.id" :value="tpl.name">
                                        {{ tpl.name }} ({{ tpl.language }}) — {{ tpl.category }}
                                    </option>
                                </select>
                                <div class="mt-1 flex items-center justify-between">
                                    <p class="text-xs text-slate-500">{{ t('whatsapp.tplSelectHint') }}</p>
                                    <Link :href="route('whatsapp.templates-page', account.id)" class="text-xs text-brand-600 hover:text-brand-500">{{ t('whatsapp.tplManage') }} →</Link>
                                </div>

                                <!-- Media upload for IMAGE/VIDEO headers -->
                                <div v-if="needsHeaderMedia" class="mt-3 rounded-lg bg-blue-50 border border-blue-200 p-3 space-y-2">
                                    <p class="text-xs font-medium text-blue-800">Este template requiere {{ selectedHeaderType === 'VIDEO' ? 'un video' : 'una imagen' }}</p>
                                    <input type="file" :accept="selectedHeaderType === 'VIDEO' ? 'video/mp4' : 'image/jpeg,image/png'" @change="onBulkMediaSelected" class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100" />
                                    <div v-if="bulkMediaPreview">
                                        <video v-if="selectedHeaderType === 'VIDEO'" :src="bulkMediaPreview" controls class="max-h-24 rounded" />
                                        <img v-else :src="bulkMediaPreview" class="max-h-24 rounded" />
                                    </div>
                                    <button v-if="bulkMediaFile && !bulkMediaId" type="button" @click="uploadBulkMedia" :disabled="bulkMediaUploading" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-500 disabled:opacity-50">
                                        {{ bulkMediaUploading ? 'Subiendo...' : 'Subir a WhatsApp' }}
                                    </button>
                                    <p v-if="bulkMediaId" class="text-xs text-green-700 font-medium">Subido correctamente</p>
                                    <p v-if="bulkMediaError" class="text-xs text-red-700">{{ bulkMediaError }}</p>
                                </div>

                                <!-- Dynamic parameter inputs -->
                                <div v-if="selectedBodyParams.length" class="mt-3 space-y-2 rounded-lg bg-slate-50 border border-slate-200 p-3">
                                    <p class="text-xs font-medium text-slate-600">{{ t('whatsapp.tplFillParams') }}</p>
                                    <div v-for="p in selectedBodyParams" :key="p" class="flex items-center gap-2">
                                        <label class="text-xs font-mono text-slate-500 w-32 shrink-0">{{ paramLabel(p) }}</label>
                                        <input v-model="templateParams[p]" type="text" class="flex-1 rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" />
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.selectContacts') }}</label>
                                <textarea v-model="bulkPhones" rows="6" class="block w-full rounded-lg border-slate-300 text-sm font-mono focus:border-brand-500 focus:ring-brand-500" placeholder="+5215512345678&#10;+5215587654321"></textarea>
                            </div>
                            <div v-if="bulkResult && !bulkResult.error" class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm space-y-1">
                                <p class="font-medium text-green-800">{{ bulkResult.sent }} enviados, {{ bulkResult.failed }} fallidos</p>
                                <ul v-if="bulkResult.errors?.length" class="text-xs text-red-600 space-y-0.5">
                                    <li v-for="(err, i) in bulkResult.errors" :key="i">{{ err }}</li>
                                </ul>
                            </div>
                            <div v-if="bulkResult?.error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                                {{ bulkResult.error }}
                                <p v-if="bulkResult.error.includes('Bulk send failed')" class="text-xs mt-1">Para mas de 200 contactos, usa Campanas en vez de Envio Masivo.</p>
                            </div>
                        </div>
                        <div class="shrink-0 border-t border-slate-100 px-6 py-4 flex justify-end gap-3 bg-white">
                            <button @click="showBulkSend = false" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 transition">{{ t('common.close') }}</button>
                            <button @click="submitBulkSend" :disabled="bulkSending" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 disabled:opacity-50 transition">
                                {{ bulkSending ? t('whatsapp.sending') : t('whatsapp.sendToSelected') }}
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </teleport>
    </AuthenticatedLayout>
</template>
