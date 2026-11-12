<script setup lang="ts">
defineProps<{
    caption?: string;
}>();
</script>

<template>
    <v-table>
        <caption v-if="caption" class="text-left text-body-2 pa-3">{{ caption }}</caption>
        <slot />
    </v-table>
</template>
