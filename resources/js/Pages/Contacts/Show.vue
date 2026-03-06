<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { usePermissions } from '@/composables/usePermissions';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import PhoneInput from '@/Components/PhoneInput.vue';

const { t } = useI18n();
const { can } = usePermissions();

const props = defineProps({
    contact: Object,
    statuses: Array,
    labels: Array,
    phoneCountries: Array,
});

const form = useForm({
    name: props.contact.name || '',
    phone: props.contact.phone || '',
    country: props.contact.country || '',
    status_id: props.contact.status_id || '',
    source: props.contact.source || '',
    notes: props.contact.notes || '',
    label_ids: props.contact.labels?.map(l => l.id) || [],
});

function onCountryDetected(countryName) {
    if (countryName) {
        form.country = countryName;
    }
}

function toggleLabel(labelId) {
    const idx = form.label_ids.indexOf(labelId);
    if (idx >= 0) {
        form.label_ids.splice(idx, 1);
    } else {
        form.label_ids.push(labelId);
    }
}

function isLabelSelected(labelId) {
    return form.label_ids.includes(labelId);
}

function submit() {
    form.put(route('contacts.update', props.contact.id));
}
</script>

<template>
    <Head :title="t('contacts.details')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('contacts.index')" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                </Link>
                <h2 class="text-xl font-bold text-slate-900">{{ t('contacts.details') }}</h2>
            </div>
        </template>

        <div class="max-w-3xl">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <InputLabel :value="t('contacts.phone')" />
                            <div class="mt-1.5">
                                <PhoneInput
                                    v-model="form.phone"
                                    :countries="phoneCountries"
                                    @country-detected="onCountryDetected"
                                />
                            </div>
                            <InputError :message="form.errors.phone" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel :value="t('contacts.name')" />
                            <TextInput v-model="form.name" class="mt-1.5 block w-full" />
                            <InputError :message="form.errors.name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel :value="t('contacts.country')" />
                            <div class="mt-1.5 flex items-center gap-2">
                                <TextInput v-model="form.country" class="block w-full" readonly />
                            </div>
                            <p class="text-xs text-slate-400 mt-1">{{ t('contacts.countryAutoDetected') }}</p>
                            <InputError :message="form.errors.country" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel :value="t('contacts.status')" />
                            <select v-model="form.status_id" class="mt-1.5 block w-full rounded-lg border-slate-300 bg-slate-50 text-sm shadow-sm focus:border-brand-500 focus:bg-white focus:ring-brand-500 transition">
                                <option value="">-</option>
                                <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <InputError :message="form.errors.status_id" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel :value="t('contacts.source')" />
                            <TextInput v-model="form.source" class="mt-1.5 block w-full" />
                            <InputError :message="form.errors.source" class="mt-1" />
                        </div>
                    </div>

                    <!-- Labels -->
                    <div>
                        <InputLabel :value="t('labels.title')" />
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            <button
                                v-for="label in labels"
                                :key="label.id"
                                type="button"
                                @click="toggleLabel(label.id)"
                                :class="[
                                    'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium border-2 transition cursor-pointer',
                                    isLabelSelected(label.id)
                                        ? 'border-current shadow-sm'
                                        : 'border-transparent opacity-50 hover:opacity-75'
                                ]"
                                :style="{ backgroundColor: label.color + '20', color: label.color }"
                            >
                                <svg v-if="isLabelSelected(label.id)" class="h-3 w-3 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                {{ label.name }}
                            </button>
                        </div>
                        <p v-if="!labels?.length" class="mt-1.5 text-xs text-slate-400">{{ t('labels.noLabels') }}</p>
                    </div>

                    <div>
                        <InputLabel :value="t('contacts.notes')" />
                        <textarea v-model="form.notes" rows="3" class="mt-1.5 block w-full rounded-lg border-slate-300 bg-slate-50 text-sm shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500 transition"></textarea>
                        <InputError :message="form.errors.notes" class="mt-1" />
                    </div>
                    <div v-if="can('contacts.update')" class="flex justify-end pt-2">
                        <PrimaryButton :disabled="form.processing">{{ t('contacts.save') }}</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
