<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/two-factor/login';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface AuthConfigContent {
    title: string;
    description: string;
    toggleText: string;
    icon: string;
}

const showRecoveryInput = ref<boolean>(false);

const authConfigContent = computed<AuthConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Recovery Code',
            description: 'Enter one of your emergency recovery codes to access your account.',
            toggleText: 'Use authentication code instead',
            icon: 'mdi-key-variant',
        };
    }

    return {
        title: 'Two-Factor Authentication',
        description: 'Enter the 6-digit code from your authenticator app.',
        toggleText: 'Use recovery code instead',
        icon: 'mdi-cellphone-key',
    };
});

const form = useForm({
    code: '',
    recovery_code: '',
});

const toggleRecoveryMode = (): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    form.clearErrors();
    form.reset();
};

const submit = () => {
    form.post(store());
};
</script>

<template>
    <AuthLayout
        :title="authConfigContent.title"
        :description="authConfigContent.description"
    >
        <Head title="Two-Factor Authentication - EaseVerifier" />

        <div class="text-center mb-6">
            <v-avatar color="primary-lighten-5" size="80" class="mb-4">
                <v-icon color="primary" size="40">{{ authConfigContent.icon }}</v-icon>
            </v-avatar>
        </div>

        <!-- Authentication Code Form -->
        <v-form v-if="!showRecoveryInput" @submit.prevent="submit">
            <v-otp-input
                v-model="form.code"
                :length="6"
                type="number"
                :error="!!form.errors.code"
                class="mb-2"
            />
            <p v-if="form.errors.code" class="text-error text-caption text-center mb-4">
                {{ form.errors.code }}
            </p>

            <v-btn
                type="submit"
                color="primary"
                size="x-large"
                block
                :loading="form.processing"
                :disabled="form.processing || form.code.length < 6"
                class="mb-4"
            >
                <v-icon start>mdi-check-circle</v-icon>
                Verify Code
            </v-btn>
        </v-form>

        <!-- Recovery Code Form -->
        <v-form v-else @submit.prevent="submit">
            <v-text-field
                v-model="form.recovery_code"
                label="Recovery Code"
                prepend-inner-icon="mdi-key"
                placeholder="Enter your recovery code"
                :error-messages="form.errors.recovery_code"
                autofocus
                class="mb-4"
            />

            <v-btn
                type="submit"
                color="primary"
                size="x-large"
                block
                :loading="form.processing"
                :disabled="form.processing || !form.recovery_code"
                class="mb-4"
            >
                <v-icon start>mdi-check-circle</v-icon>
                Verify Recovery Code
            </v-btn>
        </v-form>

        <div class="text-center">
            <v-btn
                variant="text"
                color="primary"
                @click="toggleRecoveryMode"
            >
                {{ authConfigContent.toggleText }}
            </v-btn>
        </div>
    </AuthLayout>
</template>
