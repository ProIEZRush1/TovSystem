<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const show = ref(false);
const currentStep = ref(0);

const STORAGE_KEY = 'tov_onboarding_seen';

onMounted(() => {
    if (!localStorage.getItem(STORAGE_KEY)) {
        show.value = true;
    }
});

function next() {
    if (currentStep.value < 2) {
        currentStep.value++;
    } else {
        dismiss();
    }
}

function prev() {
    if (currentStep.value > 0) {
        currentStep.value--;
    }
}

function dismiss() {
    localStorage.setItem(STORAGE_KEY, '1');
    show.value = false;
}

const steps = [
    {
        icon: 'contacts',
        titleKey: 'onboarding.step1Title',
        descKey: 'onboarding.step1Desc',
    },
    {
        icon: 'select',
        titleKey: 'onboarding.step2Title',
        descKey: 'onboarding.step2Desc',
    },
    {
        icon: 'actions',
        titleKey: 'onboarding.step3Title',
        descKey: 'onboarding.step3Desc',
    },
];
</script>

<template>
    <teleport to="body">
        <transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">
                    <!-- Progress dots -->
                    <div class="flex justify-center gap-2 pt-6">
                        <div
                            v-for="(_, i) in steps"
                            :key="i"
                            :class="[
                                'h-2 rounded-full transition-all duration-300',
                                i === currentStep ? 'w-8 bg-brand-600' : 'w-2 bg-slate-200'
                            ]"
                        ></div>
                    </div>

                    <!-- Step content -->
                    <div class="px-8 py-6 text-center">
                        <!-- Icon -->
                        <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-2xl bg-brand-50">
                            <!-- Contacts icon -->
                            <svg v-if="steps[currentStep].icon === 'contacts'" class="h-10 w-10 text-brand-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            <!-- Select icon -->
                            <svg v-if="steps[currentStep].icon === 'select'" class="h-10 w-10 text-brand-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <!-- Actions icon -->
                            <svg v-if="steps[currentStep].icon === 'actions'" class="h-10 w-10 text-brand-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                            </svg>
                        </div>

                        <h3 class="text-xl font-bold text-slate-900 mb-2">{{ t(steps[currentStep].titleKey) }}</h3>
                        <p class="text-sm text-slate-600 leading-relaxed">{{ t(steps[currentStep].descKey) }}</p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex items-center justify-between border-t border-slate-100 px-8 py-4">
                        <button
                            v-if="currentStep > 0"
                            @click="prev"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 transition"
                        >
                            {{ t('onboarding.prev') }}
                        </button>
                        <button
                            v-else
                            @click="dismiss"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-slate-400 hover:text-slate-600 transition"
                        >
                            {{ t('onboarding.skip') }}
                        </button>

                        <button
                            @click="next"
                            class="rounded-lg bg-brand-600 px-6 py-2 text-sm font-semibold text-white hover:bg-brand-500 transition"
                        >
                            {{ currentStep < 2 ? t('onboarding.next') : t('onboarding.done') }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>
