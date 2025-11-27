<script setup lang="ts">
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <v-app>
        <v-main class="auth-background">
            <v-container fluid class="fill-height pa-0">
                <v-row no-gutters class="fill-height">
                    <!-- Left Side - Branding Panel -->
                    <v-col cols="12" lg="6" class="d-none d-lg-flex">
                        <div class="brand-panel fill-height w-100 d-flex flex-column justify-center pa-12">
                            <div class="brand-content">
                                <Link :href="home()" class="d-flex align-center mb-8 text-decoration-none">
                                    <v-icon color="white" size="48" class="mr-3">mdi-shield-check</v-icon>
                                    <span class="text-h4 font-weight-bold text-white">EaseVerifier</span>
                                </Link>
                                <h1 class="text-h3 font-weight-bold text-white mb-4">
                                    Identity Verification<br />
                                    <span class="text-secondary">Made Simple</span>
                                </h1>
                                <p class="text-h6 font-weight-light text-grey-lighten-2 mb-8">
                                    Verify NIN, BVN, and CAC records instantly with our powerful API.
                                    Trusted by 500+ businesses across Nigeria.
                                </p>
                                <div class="d-flex ga-8">
                                    <div class="text-center">
                                        <div class="text-h4 font-weight-bold text-secondary">10M+</div>
                                        <div class="text-body-2 text-grey-lighten-1">Verifications</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-h4 font-weight-bold text-secondary">500+</div>
                                        <div class="text-body-2 text-grey-lighten-1">Businesses</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-h4 font-weight-bold text-secondary">99.9%</div>
                                        <div class="text-body-2 text-grey-lighten-1">Uptime</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </v-col>

                    <!-- Right Side - Form Panel -->
                    <v-col cols="12" lg="6" class="d-flex align-center justify-center">
                        <div class="form-panel pa-6 pa-md-12 w-100" style="max-width: 480px;">
                            <!-- Mobile Logo -->
                            <div class="d-lg-none text-center mb-8">
                                <Link :href="home()" class="d-inline-flex align-center text-decoration-none">
                                    <v-icon color="primary" size="40" class="mr-2">mdi-shield-check</v-icon>
                                    <span class="text-h5 font-weight-bold text-primary">EaseVerifier</span>
                                </Link>
                            </div>

                            <!-- Form Header -->
                            <div class="text-center mb-8">
                                <h2 class="text-h4 font-weight-bold mb-2">{{ title }}</h2>
                                <p class="text-body-1 text-grey-darken-1">{{ description }}</p>
                            </div>

                            <!-- Form Slot -->
                            <slot />

                            <!-- Footer -->
                            <div class="text-center mt-8">
                                <p class="text-caption text-grey">
                                    <v-icon size="14" class="mr-1">mdi-lock</v-icon>
                                    Your data is protected with bank-grade encryption
                                </p>
                            </div>
                        </div>
                    </v-col>
                </v-row>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.auth-background {
    background: #f9fafb;
    min-height: 100vh;
}

.brand-panel {
    background: linear-gradient(135deg, #1c6434 0%, #0d3a1c 100%);
    position: relative;
    overflow: hidden;
}

.brand-panel::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(254, 205, 7, 0.1) 0%, transparent 70%);
    pointer-events: none;
}

.brand-panel::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -30%;
    width: 80%;
    height: 80%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.05) 0%, transparent 60%);
    pointer-events: none;
}

.brand-content {
    position: relative;
    z-index: 1;
}

.form-panel {
    background: white;
}

@media (min-width: 1280px) {
    .form-panel {
        background: transparent;
    }
}
</style>
