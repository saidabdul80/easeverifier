<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import { Head, useForm, router } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();

const form = useForm({});

const resendVerification = () => {
    form.post(send() as unknown as string);
};

const handleLogout = () => {
    router.post(logout());
};
</script>

<template>
    <AuthLayout
        title="Verify your email"
        description="We've sent a verification link to your email address"
    >
        <Head title="Verify Email - EaseVerifier" />

        <div class="text-center mb-6">
            <v-avatar color="primary-lighten-5" size="100" class="mb-4">
                <v-icon color="primary" size="50">mdi-email-check-outline</v-icon>
            </v-avatar>
        </div>

        <v-alert
            v-if="status === 'verification-link-sent'"
            type="success"
            variant="tonal"
            class="mb-6"
            density="compact"
        >
            <div class="d-flex align-center">
                <v-icon start>mdi-check-circle</v-icon>
                A new verification link has been sent to your email address.
            </div>
        </v-alert>

        <v-card variant="outlined" class="pa-4 mb-6 bg-grey-lighten-5">
            <div class="d-flex align-start">
                <v-icon color="info" class="mr-3 mt-1">mdi-information-outline</v-icon>
                <div>
                    <p class="text-body-2 text-grey-darken-2 mb-0">
                        Before getting started, please verify your email address by clicking
                        on the link we just sent you. If you didn't receive the email, we will
                        gladly send you another.
                    </p>
                </div>
            </div>
        </v-card>

        <v-btn
            color="primary"
            size="large"
            block
            :loading="form.processing"
            :disabled="form.processing"
            @click="resendVerification"
            class="mb-4"
        >
            <v-icon start>mdi-email-sync</v-icon>
            Resend Verification Email
        </v-btn>

        <v-btn
            variant="text"
            color="grey-darken-1"
            block
            @click="handleLogout"
        >
            <v-icon start>mdi-logout</v-icon>
            Sign out
        </v-btn>
    </AuthLayout>
</template>
