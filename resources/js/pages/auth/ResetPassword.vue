<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import { update } from '@/routes/password';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps<{
    token: string;
    email: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showConfirmPassword = ref(false);

const passwordStrength = computed(() => {
    const password = form.password;
    if (!password) return { score: 0, label: '', color: 'grey' };

    let score = 0;
    if (password.length >= 8) score++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;

    const levels = [
        { score: 0, label: '', color: 'grey' },
        { score: 1, label: 'Weak', color: 'error' },
        { score: 2, label: 'Fair', color: 'warning' },
        { score: 3, label: 'Good', color: 'info' },
        { score: 4, label: 'Strong', color: 'success' },
    ];

    return levels[score] || levels[0];
});

const submit = () => {
    form.post(update(), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthLayout
        title="Set new password"
        description="Your new password must be different from previous passwords"
    >
        <Head title="Reset Password - EaseVerifier" />

        <div class="text-center mb-6">
            <v-avatar color="primary-lighten-5" size="80" class="mb-4">
                <v-icon color="primary" size="40">mdi-lock-check</v-icon>
            </v-avatar>
        </div>

        <v-form @submit.prevent="submit">
            <v-text-field
                v-model="form.email"
                label="Email Address"
                type="email"
                prepend-inner-icon="mdi-email-outline"
                :error-messages="form.errors.email"
                readonly
                disabled
                class="mb-1"
            />

            <v-text-field
                v-model="form.password"
                label="New Password"
                :type="showPassword ? 'text' : 'password'"
                prepend-inner-icon="mdi-lock-outline"
                :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                @click:append-inner="showPassword = !showPassword"
                placeholder="Create a strong password"
                :error-messages="form.errors.password"
                autocomplete="new-password"
                autofocus
                class="mb-0"
            />

            <!-- Password Strength Indicator -->
            <div v-if="form.password" class="mb-4">
                <div class="d-flex align-center ga-2 mb-1">
                    <v-progress-linear
                        :model-value="passwordStrength.score * 25"
                        :color="passwordStrength.color"
                        height="4"
                        rounded
                        class="flex-grow-1"
                    />
                    <span
                        class="text-caption font-weight-medium"
                        :class="`text-${passwordStrength.color}`"
                    >
                        {{ passwordStrength.label }}
                    </span>
                </div>
                <p class="text-caption text-grey">
                    Use 8+ characters with uppercase, lowercase, numbers & symbols
                </p>
            </div>

            <v-text-field
                v-model="form.password_confirmation"
                label="Confirm New Password"
                :type="showConfirmPassword ? 'text' : 'password'"
                prepend-inner-icon="mdi-lock-check-outline"
                :append-inner-icon="showConfirmPassword ? 'mdi-eye-off' : 'mdi-eye'"
                @click:append-inner="showConfirmPassword = !showConfirmPassword"
                placeholder="Confirm your new password"
                :error-messages="form.errors.password_confirmation"
                autocomplete="new-password"
                class="mb-4"
            />

            <v-btn
                type="submit"
                color="primary"
                size="x-large"
                block
                :loading="form.processing"
                :disabled="form.processing"
            >
                <v-icon start>mdi-lock-reset</v-icon>
                Reset Password
            </v-btn>
        </v-form>
    </AuthLayout>
</template>
