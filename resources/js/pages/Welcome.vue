<script setup lang="ts">
import { dashboard, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const mobileMenu = ref(false);

const services = [
    {
        icon: 'mdi-card-account-details',
        title: 'NIN Verification',
        description: 'Verify National Identification Numbers instantly with our robust API.',
        color: 'primary',
    },
    {
        icon: 'mdi-bank',
        title: 'BVN Verification',
        description: 'Bank Verification Number validation for financial compliance.',
        color: 'secondary',
    },
    {
        icon: 'mdi-domain',
        title: 'CAC Verification',
        description: 'Corporate Affairs Commission business verification services.',
        color: 'accent',
    },
];

const features = [
    { icon: 'mdi-lightning-bolt', title: 'Lightning Fast', desc: 'Get verification results in milliseconds' },
    { icon: 'mdi-shield-check', title: 'Secure & Reliable', desc: '99.9% uptime with bank-grade security' },
    { icon: 'mdi-api', title: 'Easy Integration', desc: 'Simple RESTful API with comprehensive docs' },
    { icon: 'mdi-cash-multiple', title: 'Pay-as-you-go', desc: 'Only pay for what you use, no hidden fees' },
];

const stats = [
    { value: '10M+', label: 'Verifications' },
    { value: '500+', label: 'Businesses' },
    { value: '99.9%', label: 'Uptime' },
    { value: '<1s', label: 'Response Time' },
];
</script>

<template>
    <Head title="Identity Verification Services - EaseVerifier">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <v-app>
        <!-- Navigation -->
        <v-app-bar flat color="transparent" class="px-4">
            <v-container class="d-flex align-center py-0">
                <div class="d-flex align-center">
                    <v-icon color="primary" size="32" class="mr-2">mdi-shield-check</v-icon>
                    <span class="text-h6 font-weight-bold text-primary">EaseVerifier</span>
                </div>
                <v-spacer />
                <div class="d-none d-md-flex align-center ga-4">
                    <v-btn variant="text" color="primary">Services</v-btn>
                    <v-btn variant="text" color="primary">Pricing</v-btn>
                    <v-btn variant="text" color="primary">Documentation</v-btn>
                    <template v-if="$page.props.auth.user">
                        <Link :href="dashboard()">
                            <v-btn color="primary" variant="flat">Dashboard</v-btn>
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="login()">
                            <v-btn variant="outlined" color="primary">Log in</v-btn>
                        </Link>
                        <Link v-if="canRegister" :href="register()">
                            <v-btn color="primary" variant="flat">Get Started</v-btn>
                        </Link>
                    </template>
                </div>
                <v-app-bar-nav-icon class="d-md-none" @click="mobileMenu = !mobileMenu" />
            </v-container>
        </v-app-bar>

        <!-- Mobile Menu -->
        <v-navigation-drawer v-model="mobileMenu" temporary location="right">
            <v-list nav>
                <v-list-item prepend-icon="mdi-home" title="Services" />
                <v-list-item prepend-icon="mdi-currency-usd" title="Pricing" />
                <v-list-item prepend-icon="mdi-book-open-variant" title="Documentation" />
                <v-divider class="my-2" />
                <template v-if="$page.props.auth.user">
                    <Link :href="dashboard()">
                        <v-list-item prepend-icon="mdi-view-dashboard" title="Dashboard" />
                    </Link>
                </template>
                <template v-else>
                    <Link :href="login()">
                        <v-list-item prepend-icon="mdi-login" title="Log in" />
                    </Link>
                    <Link v-if="canRegister" :href="register()">
                        <v-list-item prepend-icon="mdi-account-plus" title="Get Started" />
                    </Link>
                </template>
            </v-list>
        </v-navigation-drawer>

        <v-main>
            <!-- Hero Section -->
            <section class="py-16 md:py-24" style="background: linear-gradient(135deg, #1c6434 0%, #0d3a1c 100%);">
                <v-container>
                    <v-row align="center">
                        <v-col cols="12" md="6" class="text-white">
                            <h1 class="text-h3 text-md-h2 font-weight-bold mb-4">
                                Identity Verification Made <span class="text-secondary">Simple</span>
                            </h1>
                            <p class="text-h6 font-weight-light mb-6 text-grey-lighten-2">
                                Verify NIN, BVN, and CAC records instantly with our powerful API.
                                Trusted by 500+ businesses across Nigeria.
                            </p>
                            <div class="d-flex flex-wrap ga-3">
                                <Link v-if="canRegister" :href="register()">
                                    <v-btn color="secondary" size="x-large" class="px-8">
                                        <v-icon start>mdi-rocket-launch</v-icon>
                                        Start Free Trial
                                    </v-btn>
                                </Link>
                                <v-btn variant="outlined" color="white" size="x-large" class="px-8">
                                    <v-icon start>mdi-book-open-variant</v-icon>
                                    View Documentation
                                </v-btn>
                            </div>
                            <div class="d-flex align-center mt-8 ga-6">
                                <div v-for="stat in stats" :key="stat.label" class="text-center">
                                    <div class="text-h4 font-weight-bold text-secondary">{{ stat.value }}</div>
                                    <div class="text-caption text-grey-lighten-1">{{ stat.label }}</div>
                                </div>
                            </div>
                        </v-col>
                        <v-col cols="12" md="6" class="d-none d-md-flex justify-center">
                            <v-card class="pa-6" width="400" elevation="12">
                                <div class="text-center mb-4">
                                    <v-icon color="primary" size="48">mdi-shield-check</v-icon>
                                    <h3 class="text-h6 mt-2">Try NIN Verification</h3>
                                </div>
                                <v-text-field
                                    label="Enter NIN"
                                    placeholder="12345678901"
                                    prepend-inner-icon="mdi-card-account-details"
                                    variant="outlined"
                                    disabled
                                />
                                <v-btn color="primary" block size="large" disabled>
                                    <v-icon start>mdi-magnify</v-icon>
                                    Verify Now
                                </v-btn>
                                <p class="text-caption text-center mt-3 text-grey">
                                    <v-icon size="14">mdi-lock</v-icon>
                                    Sign up to start verifying
                                </p>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-container>
            </section>

            <!-- Services Section -->
            <section class="py-16 bg-grey-lighten-4">
                <v-container>
                    <div class="text-center mb-12">
                        <h2 class="text-h4 text-md-h3 font-weight-bold mb-3">Our Verification Services</h2>
                        <p class="text-body-1 text-grey-darken-1 mx-auto" style="max-width: 600px;">
                            Comprehensive identity verification solutions designed for Nigerian businesses
                        </p>
                    </div>
                    <v-row>
                        <v-col v-for="service in services" :key="service.title" cols="12" md="4">
                            <v-card class="pa-6 h-100 service-card" hover>
                                <div class="d-flex align-center mb-4">
                                    <v-avatar :color="service.color" size="56">
                                        <v-icon color="white" size="28">{{ service.icon }}</v-icon>
                                    </v-avatar>
                                </div>
                                <h3 class="text-h6 font-weight-bold mb-2">{{ service.title }}</h3>
                                <p class="text-body-2 text-grey-darken-1 mb-4">{{ service.description }}</p>
                                <v-btn variant="text" color="primary" class="px-0">
                                    Learn more <v-icon end>mdi-arrow-right</v-icon>
                                </v-btn>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-container>
            </section>

            <!-- Features Section -->
            <section class="py-16">
                <v-container>
                    <div class="text-center mb-12">
                        <h2 class="text-h4 text-md-h3 font-weight-bold mb-3">Why Choose EaseVerifier?</h2>
                        <p class="text-body-1 text-grey-darken-1 mx-auto" style="max-width: 600px;">
                            Built for developers, trusted by enterprises
                        </p>
                    </div>
                    <v-row>
                        <v-col v-for="feature in features" :key="feature.title" cols="12" sm="6" md="3">
                            <div class="text-center pa-4">
                                <v-avatar color="primary-lighten-5" size="72" class="mb-4">
                                    <v-icon color="primary" size="36">{{ feature.icon }}</v-icon>
                                </v-avatar>
                                <h4 class="text-h6 font-weight-bold mb-2">{{ feature.title }}</h4>
                                <p class="text-body-2 text-grey-darken-1">{{ feature.desc }}</p>
                            </div>
                        </v-col>
                    </v-row>
                </v-container>
            </section>

            <!-- How It Works Section -->
            <section class="py-16 bg-grey-lighten-4">
                <v-container>
                    <div class="text-center mb-12">
                        <h2 class="text-h4 text-md-h3 font-weight-bold mb-3">How It Works</h2>
                        <p class="text-body-1 text-grey-darken-1 mx-auto" style="max-width: 600px;">
                            Get started in three simple steps
                        </p>
                    </div>
                    <v-row justify="center">
                        <v-col cols="12" md="4" class="text-center">
                            <v-avatar color="primary" size="64" class="mb-4">
                                <span class="text-h5 font-weight-bold">1</span>
                            </v-avatar>
                            <h4 class="text-h6 font-weight-bold mb-2">Create Account</h4>
                            <p class="text-body-2 text-grey-darken-1">Sign up and get your API keys instantly</p>
                        </v-col>
                        <v-col cols="12" md="4" class="text-center">
                            <v-avatar color="secondary" size="64" class="mb-4">
                                <span class="text-h5 font-weight-bold text-black">2</span>
                            </v-avatar>
                            <h4 class="text-h6 font-weight-bold mb-2">Fund Your Wallet</h4>
                            <p class="text-body-2 text-grey-darken-1">Add funds to your wallet using any payment method</p>
                        </v-col>
                        <v-col cols="12" md="4" class="text-center">
                            <v-avatar color="accent" size="64" class="mb-4">
                                <span class="text-h5 font-weight-bold">3</span>
                            </v-avatar>
                            <h4 class="text-h6 font-weight-bold mb-2">Start Verifying</h4>
                            <p class="text-body-2 text-grey-darken-1">Use our API or dashboard to verify identities</p>
                        </v-col>
                    </v-row>
                </v-container>
            </section>

            <!-- CTA Section -->
            <section class="py-16" style="background: linear-gradient(135deg, #1c6434 0%, #0d3a1c 100%);">
                <v-container>
                    <v-row justify="center">
                        <v-col cols="12" md="8" class="text-center text-white">
                            <h2 class="text-h4 text-md-h3 font-weight-bold mb-4">
                                Ready to Get Started?
                            </h2>
                            <p class="text-h6 font-weight-light mb-6 text-grey-lighten-2">
                                Join 500+ businesses already using EaseVerifier for their identity verification needs.
                            </p>
                            <Link v-if="canRegister" :href="register()">
                                <v-btn color="secondary" size="x-large" class="px-10">
                                    <v-icon start>mdi-rocket-launch</v-icon>
                                    Create Free Account
                                </v-btn>
                            </Link>
                        </v-col>
                    </v-row>
                </v-container>
            </section>

            <!-- Footer -->
            <v-footer class="bg-grey-darken-4 py-8">
                <v-container>
                    <v-row>
                        <v-col cols="12" md="4">
                            <div class="d-flex align-center mb-4">
                                <v-icon color="primary" size="32" class="mr-2">mdi-shield-check</v-icon>
                                <span class="text-h6 font-weight-bold text-white">EaseVerifier</span>
                            </div>
                            <p class="text-body-2 text-grey-lighten-1">
                                Nigeria's trusted identity verification platform for businesses.
                            </p>
                        </v-col>
                        <v-col cols="6" md="2">
                            <h4 class="text-subtitle-1 font-weight-bold text-white mb-3">Product</h4>
                            <div class="text-body-2 text-grey-lighten-1 mb-2">NIN Verification</div>
                            <div class="text-body-2 text-grey-lighten-1 mb-2">BVN Verification</div>
                            <div class="text-body-2 text-grey-lighten-1 mb-2">CAC Verification</div>
                            <div class="text-body-2 text-grey-lighten-1 mb-2">API Documentation</div>
                        </v-col>
                        <v-col cols="6" md="2">
                            <h4 class="text-subtitle-1 font-weight-bold text-white mb-3">Company</h4>
                            <div class="text-body-2 text-grey-lighten-1 mb-2">About Us</div>
                            <div class="text-body-2 text-grey-lighten-1 mb-2">Pricing</div>
                            <div class="text-body-2 text-grey-lighten-1 mb-2">Blog</div>
                            <div class="text-body-2 text-grey-lighten-1 mb-2">Contact</div>
                        </v-col>
                        <v-col cols="6" md="2">
                            <h4 class="text-subtitle-1 font-weight-bold text-white mb-3">Legal</h4>
                            <div class="text-body-2 text-grey-lighten-1 mb-2">Privacy Policy</div>
                            <div class="text-body-2 text-grey-lighten-1 mb-2">Terms of Service</div>
                            <div class="text-body-2 text-grey-lighten-1 mb-2">Cookie Policy</div>
                        </v-col>
                        <v-col cols="6" md="2">
                            <h4 class="text-subtitle-1 font-weight-bold text-white mb-3">Connect</h4>
                            <div class="d-flex ga-2">
                                <v-btn icon variant="text" color="grey-lighten-1" size="small">
                                    <v-icon>mdi-twitter</v-icon>
                                </v-btn>
                                <v-btn icon variant="text" color="grey-lighten-1" size="small">
                                    <v-icon>mdi-linkedin</v-icon>
                                </v-btn>
                                <v-btn icon variant="text" color="grey-lighten-1" size="small">
                                    <v-icon>mdi-github</v-icon>
                                </v-btn>
                            </div>
                        </v-col>
                    </v-row>
                    <v-divider class="my-6 border-opacity-25" />
                    <div class="text-center text-grey-lighten-1 text-body-2">
                        &copy; {{ new Date().getFullYear() }} EaseVerifier. All rights reserved.
                    </div>
                </v-container>
            </v-footer>
        </v-main>
    </v-app>
</template>

<style scoped>
.service-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.service-card:hover {
    transform: translateY(-8px);
}
</style>
