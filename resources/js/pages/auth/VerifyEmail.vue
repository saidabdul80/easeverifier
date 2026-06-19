<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    status?: string;
    email?: string | null;
}>();

const form = useForm({});
const verifyForm = useForm({
    otp: '',
});

const otpValue = computed({
    get: () => verifyForm.otp,
    set: (value: string) => {
        verifyForm.otp = value.replace(/\D/g, '').slice(0, 6);
    },
});

const resendVerification = () => {
    form.post(send() as unknown as string);
};

const submitOtp = () => {
    verifyForm.post('/email/verify-otp', {
        preserveScroll: true,
    });
};

const handleLogout = () => {
    router.post(logout());
};
</script>

<template>
    <AuthLayout
        title="Verify your email"
        description="Enter the one-time code sent to your inbox"
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
                A new verification code has been sent to your email address.
            </div>
        </v-alert>

        <v-card variant="outlined" class="pa-4 mb-6 bg-grey-lighten-5">
            <div class="d-flex align-start">
                <v-icon color="info" class="mr-3 mt-1">mdi-information-outline</v-icon>
                <div>
                    <p class="text-body-2 text-grey-darken-2 mb-0">
                        We've sent a 6-digit code to <strong>{{ email }}</strong>. Enter it below to activate your account.
                    </p>
                </div>
            </div>
        </v-card>

        <v-form @submit.prevent="submitOtp">
            <v-otp-input
                v-model="otpValue"
                length="6"
                class="mb-4"
                variant="outlined"
            />

            <v-alert
                v-if="verifyForm.errors.otp"
                type="error"
                variant="tonal"
                density="compact"
                class="mb-4"
            >
                {{ verifyForm.errors.otp }}
            </v-alert>

            <v-btn
                type="submit"
                color="primary"
                size="large"
                block
                :loading="verifyForm.processing"
                :disabled="verifyForm.processing || verifyForm.otp.length !== 6"
                class="mb-4"
            >
                <v-icon start>mdi-shield-check</v-icon>
                Verify Email
            </v-btn>
        </v-form>

        <v-btn
            variant="outlined"
            block
            :loading="form.processing"
            :disabled="form.processing"
            @click="resendVerification"
            class="mb-4"
        >
            <v-icon start>mdi-email-sync</v-icon>
            Resend Code
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
