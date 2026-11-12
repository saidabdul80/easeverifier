<script setup lang="ts">
defineProps<{
    title?: string;
}>();
</script>

<template>
    <v-card>
        <v-card-title v-if="title || $slots.title">
            <slot name="title">{{ title }}</slot>
        </v-card-title>
        <v-card-text>
            <slot />
        </v-card-text>
        <v-card-actions v-if="$slots.actions">
            <slot name="actions" />
        </v-card-actions>
    </v-card>
</template>
