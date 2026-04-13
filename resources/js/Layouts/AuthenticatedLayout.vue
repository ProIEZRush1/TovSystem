<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePermissions } from '@/composables/usePermissions';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import OnboardingTutorial from '@/Components/OnboardingTutorial.vue';
import { Link, usePage } from '@inertiajs/vue3';

const { t } = useI18n();
const page = usePage();
const { can, canAny } = usePermissions();
const sidebarOpen = ref(false);

const navigation = computed(() => {
    const items = [];
    if (can('dashboard.view')) {
        items.push({ name: t('nav.dashboard'), href: route('dashboard'), current: route().current('dashboard'), icon: 'dashboard' });
    }
    if (can('contacts.view')) {
        items.push({ name: t('nav.contacts'), href: route('contacts.index'), current: route().current('contacts.*'), icon: 'contacts' });
    }
    if (can('statuses.view')) {
        items.push({ name: t('nav.statuses'), href: route('statuses.index'), current: route().current('statuses.*'), icon: 'statuses' });
    }
    if (can('labels.view')) {
        items.push({ name: t('nav.labels'), href: route('labels.index'), current: route().current('labels.*'), icon: 'labels' });
    }
    if (can('whatsapp.view')) {
        items.push({ name: t('nav.whatsapp'), href: route('whatsapp.index'), current: route().current('whatsapp.*'), icon: 'whatsapp' });
    }
    if (can('import.manage')) {
        items.push({ name: t('nav.import'), href: route('import.create'), current: route().current('import.*'), icon: 'import' });
    }
    if (canAny(['admin.users.view', 'admin.roles.view'])) {
        items.push({ name: t('nav.admin'), href: route('admin.users.index'), current: route().current('admin.*'), icon: 'admin' });
    }
    return items;
});
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <OnboardingTutorial />
        <!-- Mobile sidebar backdrop -->
        <transition
            enter-active-class="transition-opacity ease-linear duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity ease-linear duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"></div>
        </transition>

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-white border-r border-slate-200 transition-transform duration-200 ease-in-out lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <!-- Logo -->
            <div class="flex h-16 items-center gap-3 border-b border-slate-100 px-6">
                <Link :href="route('dashboard')">
                    <ApplicationLogo />
                </Link>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto px-3 py-4">
                <ul class="space-y-1">
                    <li v-for="item in navigation" :key="item.name">
                        <Link
                            :href="item.href"
                            :class="[
                                'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150',
                                item.current
                                    ? 'bg-brand-50 text-brand-700'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                            ]"
                        >
                            <!-- Dashboard -->
                            <svg v-if="item.icon === 'dashboard'" :class="[item.current ? 'text-brand-600' : 'text-slate-400 group-hover:text-slate-600']" class="h-5 w-5 shrink-0 transition" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" />
                            </svg>
                            <!-- Contacts -->
                            <svg v-if="item.icon === 'contacts'" :class="[item.current ? 'text-brand-600' : 'text-slate-400 group-hover:text-slate-600']" class="h-5 w-5 shrink-0 transition" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            <!-- Statuses -->
                            <svg v-if="item.icon === 'statuses'" :class="[item.current ? 'text-brand-600' : 'text-slate-400 group-hover:text-slate-600']" class="h-5 w-5 shrink-0 transition" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                            </svg>
                            <!-- Labels -->
                            <svg v-if="item.icon === 'labels'" :class="[item.current ? 'text-brand-600' : 'text-slate-400 group-hover:text-slate-600']" class="h-5 w-5 shrink-0 transition" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                            </svg>
                            <!-- WhatsApp -->
                            <svg v-if="item.icon === 'whatsapp'" :class="[item.current ? 'text-brand-600' : 'text-slate-400 group-hover:text-slate-600']" class="h-5 w-5 shrink-0 transition" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                            </svg>
                            <!-- Import -->
                            <svg v-if="item.icon === 'import'" :class="[item.current ? 'text-brand-600' : 'text-slate-400 group-hover:text-slate-600']" class="h-5 w-5 shrink-0 transition" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                            </svg>
                            <!-- Admin -->
                            <svg v-if="item.icon === 'admin'" :class="[item.current ? 'text-brand-600' : 'text-slate-400 group-hover:text-slate-600']" class="h-5 w-5 shrink-0 transition" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>

                            {{ item.name }}
                        </Link>
                    </li>
                </ul>
            </nav>

            <!-- User section at bottom -->
            <div class="border-t border-slate-100 p-3">
                <div class="flex items-center justify-between px-3 py-2">
                    <LanguageSwitcher />
                </div>
                <Link :href="route('profile.edit')" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-brand-700 text-xs font-bold">
                        {{ $page.props.auth.user.name?.charAt(0)?.toUpperCase() }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="truncate font-medium text-sm text-slate-900">{{ $page.props.auth.user.name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $page.props.auth.user.email }}</p>
                    </div>
                </Link>
                <Link :href="route('logout')" method="post" as="button" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-500 hover:bg-red-50 hover:text-red-700 transition">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                    </svg>
                    {{ t('nav.logout') }}
                </Link>
            </div>
        </aside>

        <!-- Main content -->
        <div class="lg:pl-64">
            <!-- Top bar (mobile) -->
            <div class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-200 bg-white/80 backdrop-blur-md px-4 lg:hidden">
                <button @click="sidebarOpen = true" class="-m-2.5 p-2.5 text-slate-700">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <ApplicationLogo />
            </div>

            <!-- Page header -->
            <header v-if="$slots.header" class="border-b border-slate-200 bg-white">
                <div class="px-6 py-5 sm:px-8">
                    <slot name="header" />
                </div>
            </header>

            <FlashMessage />

            <!-- Page content -->
            <main class="p-6 sm:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
