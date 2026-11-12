<script setup lang="ts">
withDefaults(defineProps<{
    color?: string;
    variant?: 'flat' | 'text' | 'outlined' | 'tonal';
}>(), {
    color: 'primary',
    variant: 'flat',
});
</script>

<template>
    <v-btn :color="color" :variant="variant">
        <slot />
    </v-btn>
</template>
