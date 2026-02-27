<script setup>
import { useI18n } from 'vue-i18n';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const { t } = useI18n();

defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    message: { type: String, default: '' },
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Modal :show="show" @close="emit('cancel')" max-width="md">
        <div class="p-6">
            <h3 class="text-lg font-medium text-slate-900">{{ title }}</h3>
            <p class="mt-2 text-sm text-slate-600">{{ message }}</p>
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="emit('cancel')">{{ t('common.cancel') }}</SecondaryButton>
                <DangerButton @click="emit('confirm')">{{ t('common.confirm') }}</DangerButton>
            </div>
        </div>
    </Modal>
</template>
