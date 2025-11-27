<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/password/confirm';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    password: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post(store() as unknown as string, {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthLayout
        title="Confirm your password"
        description="This is a secure area. Please confirm your password to continue."
    >
        <Head title="Confirm Password - EaseVerifier" />

        <div class="text-center mb-6">
            <v-avatar color="warning-lighten-4" size="80" class="mb-4">
                <v-icon color="warning" size="40">mdi-shield-lock</v-icon>
            </v-avatar>
        </div>

        <v-card variant="outlined" class="pa-4 mb-6 bg-amber-lighten-5">
            <div class="d-flex align-start">
                <v-icon color="warning" class="mr-3 mt-1">mdi-alert-outline</v-icon>
                <div>
                    <p class="text-body-2 text-grey-darken-2 mb-0">
                        For your security, please confirm your password before proceeding
                        with this action.
                    </p>
                </div>
            </div>
        </v-card>

        <v-form @submit.prevent="submit">
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
                autofocus
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
                <v-icon start>mdi-check-circle</v-icon>
                Confirm Password
            </v-btn>
        </v-form>
    </AuthLayout>
</template>
