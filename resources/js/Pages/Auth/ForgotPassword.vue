<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: { type: String },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-slate-900">Forgot password?</h2>
            <p class="mt-2 text-sm text-slate-500">No problem. Enter your email and we'll send you a reset link.</p>
        </div>

        <div v-if="status" class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm font-medium text-green-700">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput id="email" type="email" class="mt-1.5 block w-full" v-model="form.email" required autofocus autocomplete="username" placeholder="you@example.com" />
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <PrimaryButton class="w-full justify-center" :disabled="form.processing">Email Password Reset Link</PrimaryButton>
        </form>
    </GuestLayout>
</template>
