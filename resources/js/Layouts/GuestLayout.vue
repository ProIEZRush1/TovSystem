<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen">
        <!-- Left Panel - Brand -->
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-brand-950 via-brand-900 to-brand-800 relative overflow-hidden">
            <!-- Decorative shapes -->
            <div class="absolute inset-0">
                <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-brand-700/20 blur-3xl"></div>
                <div class="absolute bottom-0 right-0 w-80 h-80 rounded-full bg-brand-600/15 blur-3xl"></div>
                <div class="absolute top-1/2 left-1/3 w-64 h-64 rounded-full bg-indigo-500/10 blur-2xl"></div>
            </div>

            <div class="relative z-10 flex flex-col justify-between p-12 w-full">
                <div>
                    <ApplicationLogo variant="light" />
                </div>

                <div class="space-y-6">
                    <h1 class="text-4xl font-bold text-white leading-tight">
                        WhatsApp Contact<br />Management System
                    </h1>
                    <p class="text-brand-200 text-lg max-w-md leading-relaxed">
                        Manage your contacts, track statuses, and import data efficiently with a powerful admin dashboard.
                    </p>
                    <div class="flex gap-6 pt-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-white">59K+</div>
                            <div class="text-xs text-brand-300 uppercase tracking-wider mt-1">Contacts</div>
                        </div>
                        <div class="w-px bg-brand-700"></div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-white">20+</div>
                            <div class="text-xs text-brand-300 uppercase tracking-wider mt-1">Statuses</div>
                        </div>
                        <div class="w-px bg-brand-700"></div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-white">CSV</div>
                            <div class="text-xs text-brand-300 uppercase tracking-wider mt-1">Import</div>
                        </div>
                    </div>
                </div>

                <div class="text-brand-400 text-sm">
                    &copy; {{ new Date().getFullYear() }} TovSystem
                </div>
            </div>
        </div>

        <!-- Right Panel - Form -->
        <div class="flex flex-1 flex-col justify-center px-6 py-12 lg:px-16 bg-white">
            <div class="absolute top-6 right-6">
                <LanguageSwitcher />
            </div>

            <div class="mx-auto w-full max-w-sm">
                <div class="lg:hidden mb-10">
                    <ApplicationLogo />
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
