<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const { t } = useI18n();

const props = defineProps({ user: Object, roles: Array });

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    is_super_admin: props.user.is_super_admin,
    is_active: props.user.is_active,
    roles: props.user.roles?.map(r => r.id) || [],
});

function submit() { form.put(route('admin.users.update', props.user.id)); }

function toggleRole(roleId) {
    const idx = form.roles.indexOf(roleId);
    if (idx >= 0) form.roles.splice(idx, 1);
    else form.roles.push(roleId);
}
</script>

<template>
    <Head :title="t('admin.users.edit')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.users.index')" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                </Link>
                <h2 class="text-xl font-bold text-slate-900">{{ t('admin.users.edit') }}: {{ user.name }}</h2>
            </div>
        </template>

        <div class="max-w-3xl">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel :value="t('admin.users.name')" />
                            <TextInput v-model="form.name" class="mt-1.5 block w-full" required />
                            <InputError :message="form.errors.name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel :value="t('admin.users.email')" />
                            <TextInput v-model="form.email" type="email" class="mt-1.5 block w-full" required />
                            <InputError :message="form.errors.email" class="mt-1" />
                        </div>
                        <div class="sm:col-span-2">
                            <InputLabel :value="t('admin.users.password')" />
                            <TextInput v-model="form.password" type="password" class="mt-1.5 block w-full" />
                            <p class="text-xs text-slate-400 mt-1.5">{{ t('admin.users.confirmPassword') }}</p>
                            <InputError :message="form.errors.password" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <Checkbox v-model:checked="form.is_super_admin" />
                            <span class="text-sm text-slate-700">{{ t('admin.users.superAdmin') }}</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <Checkbox v-model:checked="form.is_active" />
                            <span class="text-sm text-slate-700">{{ t('admin.users.active') }}</span>
                        </label>
                    </div>

                    <div>
                        <InputLabel :value="t('admin.users.roles')" />
                        <div class="mt-2 flex flex-wrap gap-2">
                            <label v-for="role in roles" :key="role.id"
                                class="flex items-center gap-2 rounded-lg border px-3.5 py-2.5 cursor-pointer transition"
                                :class="form.roles.includes(role.id) ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-500' : 'border-slate-200 hover:bg-slate-50'"
                            >
                                <Checkbox :checked="form.roles.includes(role.id)" @update:checked="toggleRole(role.id)" />
                                <span class="text-sm font-medium" :class="form.roles.includes(role.id) ? 'text-brand-700' : 'text-slate-700'">{{ role.name }}</span>
                            </label>
                        </div>
                        <InputError :message="form.errors.roles" class="mt-1" />
                    </div>

                    <div class="flex justify-end pt-2">
                        <PrimaryButton :disabled="form.processing">{{ t('admin.users.save') }}</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
