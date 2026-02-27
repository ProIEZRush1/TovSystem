<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    countries: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'country-detected']);

const showDropdown = ref(false);
const searchQuery = ref('');
const searchInput = ref(null);
const dropdownRef = ref(null);
const selectedCountry = ref(null);
const phoneDigits = ref('');
const highlightIndex = ref(-1);

// Parse initial value
onMounted(() => {
    if (props.modelValue) {
        parsePhone(props.modelValue);
    }
});

function parsePhone(fullPhone) {
    const digits = fullPhone.replace(/[^0-9]/g, '');
    if (!digits) return;

    // Try to match longest prefix first
    for (let len = 4; len >= 1; len--) {
        if (digits.length >= len) {
            const prefix = digits.substring(0, len);
            const match = props.countries.find(c => c.code === prefix);
            if (match) {
                selectedCountry.value = match;
                phoneDigits.value = digits.substring(len);
                return;
            }
        }
    }
    // No match - put all digits in the phone field
    phoneDigits.value = digits;
}

const filteredCountries = computed(() => {
    if (!searchQuery.value) return props.countries;
    const q = searchQuery.value.toLowerCase();
    return props.countries.filter(c =>
        c.name.toLowerCase().includes(q) ||
        c.code.includes(q) ||
        c.iso2.toLowerCase().includes(q)
    );
});

const fullPhoneNumber = computed(() => {
    if (selectedCountry.value && phoneDigits.value) {
        return '+' + selectedCountry.value.code + phoneDigits.value;
    }
    if (selectedCountry.value) {
        return '+' + selectedCountry.value.code;
    }
    return phoneDigits.value ? '+' + phoneDigits.value : '';
});

const detectedCountryName = computed(() => {
    return selectedCountry.value?.name || null;
});

watch(fullPhoneNumber, (val) => {
    emit('update:modelValue', val);
});

watch(detectedCountryName, (name) => {
    emit('country-detected', name);
});

function selectCountry(country) {
    selectedCountry.value = country;
    showDropdown.value = false;
    searchQuery.value = '';
    highlightIndex.value = -1;
    emit('country-detected', country.name);
}

function toggleDropdown() {
    showDropdown.value = !showDropdown.value;
    if (showDropdown.value) {
        searchQuery.value = '';
        highlightIndex.value = -1;
        nextTick(() => searchInput.value?.focus());
    }
}

function handleKeydown(e) {
    if (!showDropdown.value) return;
    const list = filteredCountries.value;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlightIndex.value = Math.min(highlightIndex.value + 1, list.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlightIndex.value = Math.max(highlightIndex.value - 1, 0);
    } else if (e.key === 'Enter' && highlightIndex.value >= 0) {
        e.preventDefault();
        selectCountry(list[highlightIndex.value]);
    } else if (e.key === 'Escape') {
        showDropdown.value = false;
    }
}

function handleClickOutside(e) {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        showDropdown.value = false;
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>

<template>
    <div class="relative" ref="dropdownRef">
        <div class="flex">
            <!-- Country selector button -->
            <button
                type="button"
                @click="toggleDropdown"
                class="inline-flex items-center gap-1.5 rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 py-2 text-sm hover:bg-slate-100 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition"
            >
                <template v-if="selectedCountry">
                    <span class="text-base leading-none">{{ selectedCountry.flag }}</span>
                    <span class="text-slate-600 font-mono text-xs">+{{ selectedCountry.code }}</span>
                </template>
                <template v-else>
                    <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                    </svg>
                    <span class="text-slate-400 text-xs">Code</span>
                </template>
                <svg class="h-3.5 w-3.5 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            <!-- Phone number input -->
            <input
                type="tel"
                v-model="phoneDigits"
                :placeholder="placeholder || 'Phone number'"
                class="flex-1 rounded-r-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-mono shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500 transition"
            />
        </div>

        <!-- Country dropdown -->
        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="showDropdown"
                class="absolute z-50 mt-1 w-full max-h-64 rounded-xl bg-white shadow-lg ring-1 ring-slate-900/10 overflow-hidden"
            >
                <!-- Search -->
                <div class="p-2 border-b border-slate-100">
                    <input
                        ref="searchInput"
                        v-model="searchQuery"
                        @keydown="handleKeydown"
                        type="text"
                        placeholder="Search country..."
                        class="w-full rounded-lg border-slate-200 bg-slate-50 px-3 py-2 text-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500"
                    />
                </div>

                <!-- List -->
                <ul class="overflow-y-auto max-h-48">
                    <li
                        v-for="(country, i) in filteredCountries"
                        :key="country.code + country.iso2"
                        @click="selectCountry(country)"
                        :class="[
                            'flex items-center gap-3 px-3 py-2 text-sm cursor-pointer transition',
                            highlightIndex === i ? 'bg-brand-50 text-brand-700' : 'text-slate-700 hover:bg-slate-50',
                            selectedCountry?.code === country.code && selectedCountry?.iso2 === country.iso2 ? 'font-medium' : ''
                        ]"
                    >
                        <span class="text-base leading-none w-6 text-center">{{ country.flag }}</span>
                        <span class="flex-1 truncate">{{ country.name }}</span>
                        <span class="text-slate-400 font-mono text-xs">+{{ country.code }}</span>
                    </li>
                    <li v-if="!filteredCountries.length" class="px-3 py-4 text-center text-sm text-slate-400">No countries found</li>
                </ul>
            </div>
        </Transition>

        <!-- Detected country indicator -->
        <div v-if="selectedCountry" class="mt-1.5 flex items-center gap-1.5">
            <span class="text-xs text-slate-400">{{ selectedCountry.flag }} {{ selectedCountry.name }}</span>
        </div>
    </div>
</template>
