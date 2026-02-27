<script setup>
defineProps({
    name: { type: String, required: true },
    color: { type: String, default: '#6B7280' },
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
        :style="{
            backgroundColor: color + '20',
            color: color,
        }"
    >
        <span class="mr-1.5 h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: color }"></span>
        {{ name }}
    </span>
</template>
