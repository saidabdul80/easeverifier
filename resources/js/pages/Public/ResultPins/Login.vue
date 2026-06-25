<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
});

const submit = () => {
    form.post('/result-pins/login');
};
</script>

<template>
    <Head title="Access Result PINs - EaseVerifier" />

    <v-app>
        <v-main class="login-shell">
            <v-container class="fill-height d-flex align-center justify-center">
                <v-card width="460" class="rounded-xl">
                    <v-card-text class="pa-8">
                        <Link href="/" class="text-decoration-none d-flex align-center mb-6">
                            <v-avatar color="primary" size="36" class="mr-2">
                                <img src="/ashlabtech.png" alt="EaseVerifier" style="width: 100%; height: 100%; object-fit: contain;" />
                            </v-avatar>
                            <span class="text-h6 font-weight-bold text-primary">EaseVerifier</span>
                        </Link>

                        <h1 class="text-h4 font-weight-bold mb-2">Access My PINs</h1>
                        <p class="text-body-2 text-grey-darken-1 mb-6">Enter the email used during public purchase to access your result PIN interface.</p>

                        <v-alert v-if="$page.props.flash?.error" type="error" variant="tonal" class="mb-4">{{ $page.props.flash.error }}</v-alert>

                        <v-form @submit.prevent="submit">
                            <v-text-field v-model="form.email" label="Email address" type="email" :error-messages="form.errors.email" required />
                            <v-btn type="submit" color="primary" size="large" block :loading="form.processing">Continue</v-btn>
                        </v-form>

                        <v-btn href="/result-pins" variant="text" block class="mt-4">Buy new PINs</v-btn>
                    </v-card-text>
                </v-card>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.login-shell {
    min-height: 100vh;
    background: linear-gradient(135deg, #f7f2df 0%, #dbeadb 100%);
}
</style>
