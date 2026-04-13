<script setup>
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue';
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

async function sendMessage() {
    if (!newMessage.value.trim() || !activePhone.value || sending.value) return;
    sending.value = true;
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
        console.error('Failed to send message', e);
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
        const response = await axios.post(route('whatsapp.bulk-send', props.account.id), {
            phones,
            type: bulkType.value,
            message: bulkMessage.value || null,
            template_name: bulkTemplateName.value || null,
            language_code: bulkLanguage.value,
        });
        bulkResult.value = response.data;
    } catch (e) {
        console.error('Bulk send failed', e);
    } finally {
        bulkSending.value = false;
    }
}

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
                            <div class="flex items-center justify-end gap-1 mt-1">
                                <span :class="['text-xs', msg.direction === 'outbound' ? 'text-green-200' : 'text-slate-400']">
                                    {{ formatTime(msg.sent_at || msg.created_at) }}
                                </span>
                                <span v-if="msg.direction === 'outbound'" :class="['text-xs', msg.status === 'read' ? 'text-blue-300' : 'text-green-200']">
                                    {{ msg.status === 'delivered' || msg.status === 'read' ? '...' : '' }}
                                </span>
                            </div>
                        </div>
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
                    <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">
                        <div class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
                            <h3 class="text-lg font-bold text-slate-900">{{ t('whatsapp.bulkSendTitle') }}</h3>
                            <button @click="showBulkSend = false" class="text-slate-400 hover:text-slate-600">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="px-6 py-4 space-y-4">
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
                            <div v-if="bulkType === 'template'" class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.templateName') }}</label>
                                    <input v-model="bulkTemplateName" type="text" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="hello_world" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.language') }}</label>
                                    <input v-model="bulkLanguage" type="text" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ t('whatsapp.selectContacts') }}</label>
                                <textarea v-model="bulkPhones" rows="6" class="block w-full rounded-lg border-slate-300 text-sm font-mono focus:border-brand-500 focus:ring-brand-500" placeholder="+5215512345678&#10;+5215587654321"></textarea>
                            </div>
                            <div v-if="bulkResult" class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm">
                                <p class="font-medium text-green-800">{{ t('whatsapp.bulkResult', { sent: bulkResult.sent, failed: bulkResult.failed }) }}</p>
                            </div>
                        </div>
                        <div class="border-t border-slate-100 px-6 py-4 flex justify-end gap-3">
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
