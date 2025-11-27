<script setup lang="ts">
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showConfirmPassword = ref(false);
const agreeToTerms = ref(false);

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
    form.post(store(), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase
        title="Create your account"
        description="Start verifying identities in minutes"
    >
        <Head title="Create Account - EaseVerifier" />

        <v-form @submit.prevent="submit">
            <v-text-field
                v-model="form.name"
                label="Full Name"
                prepend-inner-icon="mdi-account-outline"
                placeholder="John Doe"
                :error-messages="form.errors.name"
                autocomplete="name"
                autofocus
                class="mb-1"
            />

            <v-text-field
                v-model="form.email"
                label="Work Email"
                type="email"
                prepend-inner-icon="mdi-email-outline"
                placeholder="you@company.com"
                :error-messages="form.errors.email"
                autocomplete="email"
                class="mb-1"
            />

            <v-text-field
                v-model="form.password"
                label="Password"
                :type="showPassword ? 'text' : 'password'"
                prepend-inner-icon="mdi-lock-outline"
                :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                @click:append-inner="showPassword = !showPassword"
                placeholder="Create a strong password"
                :error-messages="form.errors.password"
                autocomplete="new-password"
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
                label="Confirm Password"
                :type="showConfirmPassword ? 'text' : 'password'"
                prepend-inner-icon="mdi-lock-check-outline"
                :append-inner-icon="showConfirmPassword ? 'mdi-eye-off' : 'mdi-eye'"
                @click:append-inner="showConfirmPassword = !showConfirmPassword"
                placeholder="Confirm your password"
                :error-messages="form.errors.password_confirmation"
                autocomplete="new-password"
                class="mb-2"
            />

            <v-checkbox
                v-model="agreeToTerms"
                color="primary"
                density="compact"
                class="mb-4"
            >
                <template #label>
                    <span class="text-body-2">
                        I agree to the
                        <a href="#" class="text-primary text-decoration-none font-weight-medium">Terms of Service</a>
                        and
                        <a href="#" class="text-primary text-decoration-none font-weight-medium">Privacy Policy</a>
                    </span>
                </template>
            </v-checkbox>

            <v-btn
                type="submit"
                color="primary"
                size="x-large"
                block
                :loading="form.processing"
                :disabled="form.processing || !agreeToTerms"
                class="mb-4"
            >
                <v-icon start>mdi-account-plus</v-icon>
                Create Account
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
                Sign up with Google
            </v-btn>

            <div class="text-center">
                <span class="text-body-2 text-grey-darken-1">Already have an account?</span>
                <Link
                    :href="login()"
                    class="text-primary text-body-2 text-decoration-none font-weight-bold ml-1"
                >
                    Sign in
                </Link>
            </div>
        </v-form>
    </AuthBase>
</template>
