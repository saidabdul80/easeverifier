<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email as emailRoute } from '@/routes/password';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(emailRoute() as unknown as string);
};
</script>

<template>
    <AuthLayout
        title="Forgot your password?"
        description="No worries, we'll send you reset instructions"
    >
        <Head title="Forgot Password - EaseVerifier" />

        <v-alert
            v-if="status"
            type="success"
            variant="tonal"
            class="mb-6"
            density="compact"
        >
            <div class="d-flex align-center">
                <v-icon start>mdi-email-check</v-icon>
                {{ status }}
            </div>
        </v-alert>

        <div v-if="!status" class="text-center mb-6">
            <v-avatar color="primary-lighten-5" size="80" class="mb-4">
                <v-icon color="primary" size="40">mdi-lock-reset</v-icon>
            </v-avatar>
        </div>

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
                class="mb-2"
            />

            <v-btn
                type="submit"
                color="primary"
                size="x-large"
                block
                :loading="form.processing"
                :disabled="form.processing"
                class="mb-6"
            >
                <v-icon start>mdi-email-fast</v-icon>
                Send Reset Link
            </v-btn>
        </v-form>

        <div class="text-center">
            <Link
                :href="login()"
                class="text-primary text-body-2 text-decoration-none d-inline-flex align-center"
            >
                <v-icon size="18" class="mr-1">mdi-arrow-left</v-icon>
                Back to sign in
            </Link>
        </div>
    </AuthLayout>
</template>
