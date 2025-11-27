<script setup lang="ts">
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(store(), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase
        title="Welcome back"
        description="Sign in to access your verification dashboard"
    >
        <Head title="Log in - EaseVerifier" />

        <v-alert
            v-if="status"
            type="success"
            variant="tonal"
            class="mb-6"
            density="compact"
        >
            {{ status }}
        </v-alert>

        <v-form @submit.prevent="submit">
            <v-text-field
                v-model="form.email"
                label="Email Address"
                type="email"
                prepend-inner-icon="mdi-email-outline"
                placeholder="you@company.com"
                :error-messages="form.errors.email"
                autocomplete="email"
                autofocus
                class="mb-1"
            />

            <v-text-field
                v-model="form.password"
                label="Password"
                :type="showPassword ? 'text' : 'password'"
                prepend-inner-icon="mdi-lock-outline"
                :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                @click:append-inner="showPassword = !showPassword"
                placeholder="Enter your password"
                :error-messages="form.errors.password"
                autocomplete="current-password"
                class="mb-1"
            />

            <div class="d-flex align-center justify-space-between mb-6">
                <v-checkbox
                    v-model="form.remember"
                    label="Remember me"
                    color="primary"
                    density="compact"
                    hide-details
                />
                <Link
                    v-if="canResetPassword"
                    :href="request()"
                    class="text-primary text-body-2 text-decoration-none font-weight-medium"
                >
                    Forgot password?
                </Link>
            </div>

            <v-btn
                type="submit"
                color="primary"
                size="x-large"
                block
                :loading="form.processing"
                :disabled="form.processing"
                class="mb-4"
            >
                <v-icon start>mdi-login</v-icon>
                Sign In
            </v-btn>

            <v-btn
                variant="outlined"
                color="grey-darken-1"
                size="large"
                block
                class="mb-6"
                disabled
            >
                <v-icon start>mdi-google</v-icon>
                Continue with Google
            </v-btn>

            <div class="text-center" v-if="canRegister">
                <span class="text-body-2 text-grey-darken-1">Don't have an account?</span>
                <Link
                    :href="register()"
                    class="text-primary text-body-2 text-decoration-none font-weight-bold ml-1"
                >
                    Create account
                </Link>
            </div>
        </v-form>
    </AuthBase>
</template>
