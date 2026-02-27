<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: { type: String },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Email Verification" />

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-slate-900">Verify your email</h2>
            <p class="mt-2 text-sm text-slate-500">
                Thanks for signing up! Before getting started, please verify your email address by clicking the link we just sent you.
            </p>
        </div>

        <div v-if="verificationLinkSent" class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm font-medium text-green-700">
            A new verification link has been sent to the email address you provided during registration.
        </div>

        <form @submit.prevent="submit">
            <div class="flex items-center justify-between">
                <PrimaryButton :disabled="form.processing">Resend Verification Email</PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-sm font-medium text-slate-500 hover:text-slate-700 transition"
                >
                    Log Out
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
