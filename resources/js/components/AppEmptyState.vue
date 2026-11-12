<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div role="status" class="text-center pa-6">
        <p class="text-subtitle-1 font-weight-medium mb-2">{{ title }}</p>
        <p v-if="description" class="text-body-2 text-medium-emphasis mb-0">{{ description }}</p>
        <slot />
    </div>
</template>
