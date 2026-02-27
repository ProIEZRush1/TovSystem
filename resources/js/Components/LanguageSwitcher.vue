<script setup>
import { useI18n } from 'vue-i18n';

const { locale } = useI18n();

function switchLocale(lang) {
    locale.value = lang;
    localStorage.setItem('locale', lang);
}
</script>

<template>
    <div class="flex items-center gap-1">
        <button
            @click="switchLocale('es')"
            :class="[
                'px-2 py-1 text-xs font-medium rounded transition',
                locale === 'es'
                    ? 'bg-indigo-600 text-white'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'
            ]"
        >
            ES
        </button>
        <button
            @click="switchLocale('en')"
            :class="[
                'px-2 py-1 text-xs font-medium rounded transition',
                locale === 'en'
                    ? 'bg-indigo-600 text-white'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'
            ]"
        >
            EN
        </button>
    </div>
</template>
