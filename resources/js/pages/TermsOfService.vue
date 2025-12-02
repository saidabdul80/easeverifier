<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home, login, register } from '@/routes';
</script>

<template>
    <Head title="Terms of Service - Service Agreement | EaseVerifier">
        <meta name="description" content="Review EaseVerifier's terms of service for using our identity verification API and platform. Understand your rights and responsibilities as a user." />
        <meta name="keywords" content="terms of service, service agreement, API terms, verification service terms, user agreement Nigeria" />
        <meta property="og:title" content="Terms of Service - EaseVerifier" />
        <meta property="og:description" content="Terms and conditions for using EaseVerifier's identity verification services and API." />
        <meta property="og:type" content="article" />
        <link rel="canonical" href="https://easeverifier.com/terms" />
    </Head>
    <v-app>
        <v-app-bar flat color="white" elevation="1">
            <v-container class="d-flex align-center">
                <Link :href="home()" class="text-decoration-none d-flex align-center">
                    <v-avatar color="primary" size="36" class="mr-2">
                        <v-icon color="white" size="20">mdi-shield-check</v-icon>
                    </v-avatar>
                    <span class="text-h6 font-weight-bold text-primary">EaseVerifier</span>
                </Link>
                <v-spacer />
                <div class="d-flex ga-2">
                    <v-btn variant="outlined" color="primary" :href="login()">Login</v-btn>
                    <v-btn variant="flat" color="primary" :href="register()" class="d-none d-sm-flex">Get Started</v-btn>
                </div>
            </v-container>
        </v-app-bar>

        <v-main>
            <section class="hero-section py-12 text-center">
                <v-container>
                    <h1 class="text-h3 font-weight-bold text-white mb-2">Terms of Service</h1>
                    <p class="text-body-1 text-white opacity-80">Last updated: December 1, 2024</p>
                </v-container>
            </section>

            <section class="py-12">
                <v-container>
                    <v-card class="pa-8 mx-auto" max-width="900">
                        <h2 class="text-h5 font-weight-bold mb-4">1. Acceptance of Terms</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">By accessing or using EaseVerifier's services, you agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use our services.</p>

                        <h2 class="text-h5 font-weight-bold mb-4">2. Description of Services</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">EaseVerifier provides identity verification services including NIN, BVN, and CAC verification through API access and web dashboard. Services are provided on a pay-as-you-go basis.</p>

                        <h2 class="text-h5 font-weight-bold mb-4">3. Account Registration</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">You must register for an account to use our services. You agree to provide accurate information and keep your account credentials secure. You are responsible for all activities under your account.</p>

                        <h2 class="text-h5 font-weight-bold mb-4">4. Acceptable Use</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-2">You agree not to:</p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>Use our services for illegal purposes</li>
                            <li>Attempt to gain unauthorized access to our systems</li>
                            <li>Share your API credentials with unauthorized parties</li>
                            <li>Resell our services without authorization</li>
                            <li>Abuse or overload our systems</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">5. Payment Terms</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">Services are prepaid. You must fund your wallet before making verification requests. All payments are non-refundable except where required by law. Prices may be updated with 30 days notice.</p>

                        <h2 class="text-h5 font-weight-bold mb-4">6. Service Availability</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">We strive to maintain 99.9% uptime but do not guarantee uninterrupted service. We may suspend services for maintenance with reasonable notice.</p>

                        <h2 class="text-h5 font-weight-bold mb-4">7. Limitation of Liability</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">EaseVerifier shall not be liable for any indirect, incidental, or consequential damages. Our total liability shall not exceed the amount paid by you in the 12 months preceding the claim.</p>

                        <h2 class="text-h5 font-weight-bold mb-4">8. Termination</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">We may terminate your account for violation of these terms. You may close your account at any time. Upon termination, your access to services will cease immediately.</p>

                        <h2 class="text-h5 font-weight-bold mb-4">9. Governing Law</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">These terms shall be governed by the laws of the Federal Republic of Nigeria. Any disputes shall be resolved in courts located in Lagos, Nigeria.</p>

                        <h2 class="text-h5 font-weight-bold mb-4">10. Contact</h2>
                        <p class="text-body-1 text-grey-darken-1">For questions about these terms, contact us at <a href="mailto:legal@easeverifier.com" class="text-primary">legal@easeverifier.com</a>.</p>
                    </v-card>
                </v-container>
            </section>
        </v-main>
    </v-app>
</template>

<style scoped>
.hero-section { background: linear-gradient(135deg, #1c6434 0%, #0d3d1f 100%); }
</style>

