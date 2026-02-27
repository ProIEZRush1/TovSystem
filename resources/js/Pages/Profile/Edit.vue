<script setup>
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

const { t } = useI18n();

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head :title="t('nav.profile')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-slate-900">{{ t('nav.profile') }}</h2>
        </template>

        <div class="max-w-3xl space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                />
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                <UpdatePasswordForm />
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                <DeleteUserForm />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
