<script setup lang="ts">
import { useToast } from '@/composables/useToast'
import { usePage } from '@inertiajs/vue3'
import { watch, onMounted } from 'vue'

const { toasts, remove, success, error, warning, info } = useToast()
const page = usePage()

// Watch for flash messages from backend
watch(
    () => page.props.flash,
    (flash: any) => {
        console.log('Toast: Flash messages changed:', flash)
        if (flash?.success) {
            console.log('Toast: Showing success message:', flash.success)
            success(flash.success)
        }
        if (flash?.error) {
            console.log('Toast: Showing error message:', flash.error)
            error(flash.error)
        }
        if (flash?.warning) {
            console.log('Toast: Showing warning message:', flash.warning)
            warning(flash.warning)
        }
        if (flash?.info) {
            console.log('Toast: Showing info message:', flash.info)
            info(flash.info)
        }
    },
    { deep: true, immediate: true }
)

// Also check on mount for initial flash messages
onMounted(() => {
    const flash = page.props.flash as any
    if (flash?.success) {
        success(flash.success)
    }
    if (flash?.error) {
        error(flash.error)
    }
    if (flash?.warning) {
        warning(flash.warning)
    }
    if (flash?.info) {
        info(flash.info)
    }
})

const getColor = (type: string) => {
    switch (type) {
        case 'success':
            return 'success'
        case 'error':
            return 'error'
        case 'warning':
            return 'warning'
        case 'info':
            return 'info'
        default:
            return 'primary'
    }
}

const getIcon = (type: string) => {
    switch (type) {
        case 'success':
            return 'mdi-check-circle'
        case 'error':
            return 'mdi-alert-circle'
        case 'warning':
            return 'mdi-alert'
        case 'info':
            return 'mdi-information'
        default:
            return 'mdi-bell'
    }
}
</script>

<template>
    <div class="toast-container">
        <v-snackbar
            v-for="(toast, index) in toasts"
            :key="`toast-${index}-${Date.now()}`"
            :model-value="true"
            :color="getColor(toast.type)"
            :timeout="toast.timeout"
            location="top right"
            :style="{ top: `${index * 80 + 20}px` }"
            class="toast-item"
            elevation="6"
            rounded="lg"
            @update:model-value="(val: boolean) => !val && remove(index)"
        >
            <div class="d-flex align-center">
                <v-icon :icon="getIcon(toast.type)" size="24" class="mr-3" />
                <span class="text-body-1 font-weight-medium">{{ toast.message }}</span>
            </div>

            <template v-slot:actions>
                <v-btn
                    variant="text"
                    icon="mdi-close"
                    size="small"
                    @click="remove(index)"
                />
            </template>
        </v-snackbar>
    </div>
</template>

<style scoped>
.toast-container {
    position: fixed;
    top: 0;
    right: 0;
    z-index: 9999;
    pointer-events: none;
}

.toast-item {
    pointer-events: auto;
    position: fixed !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

:deep(.v-snackbar__wrapper) {
    min-width: 344px;
    max-width: 500px;
}

:deep(.v-snackbar__content) {
    padding: 16px 20px;
}
</style>

