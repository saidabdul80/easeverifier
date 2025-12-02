<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home, login, register } from '@/routes';
</script>

<template>
    <Head title="Privacy Policy - Data Protection & Security | EaseVerifier">
        <meta name="description" content="EaseVerifier's privacy policy explains how we collect, use, and protect your personal data during identity verification. We're committed to NDPR compliance and data security." />
        <meta name="keywords" content="privacy policy, data protection Nigeria, NDPR compliance, identity verification privacy, data security" />
        <meta property="og:title" content="Privacy Policy - EaseVerifier" />
        <meta property="og:description" content="Learn how EaseVerifier protects your data and maintains privacy during identity verification." />
        <meta property="og:type" content="article" />
        <link rel="canonical" href="https://easeverifier.com/privacy" />
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
                    <h1 class="text-h3 font-weight-bold text-white mb-2">Privacy Policy</h1>
                    <p class="text-body-1 text-white opacity-80">Last updated: December 1, 2024</p>
                </v-container>
            </section>

            <section class="py-12">
                <v-container>
                    <v-card class="pa-8 mx-auto" max-width="900">
                        <h2 class="text-h5 font-weight-bold mb-4">1. Information We Collect</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">We collect information you provide directly to us, including name, email address, phone number, and company details when you register for an account. We also collect verification data that you submit through our platform.</p>

                        <h2 class="text-h5 font-weight-bold mb-4">2. How We Use Your Information</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-2">We use the information we collect to:</p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>Provide, maintain, and improve our services</li>
                            <li>Process verification requests</li>
                            <li>Send you technical notices and support messages</li>
                            <li>Respond to your comments and questions</li>
                            <li>Comply with legal obligations</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">3. Data Security</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">We implement appropriate technical and organizational measures to protect your personal data against unauthorized access, alteration, disclosure, or destruction. All data is encrypted in transit and at rest.</p>

                        <h2 class="text-h5 font-weight-bold mb-4">4. Data Retention</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">We retain your personal data only for as long as necessary to fulfill the purposes for which it was collected, including to satisfy legal, accounting, or reporting requirements.</p>

                        <h2 class="text-h5 font-weight-bold mb-4">5. Your Rights</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-2">You have the right to:</p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>Access your personal data</li>
                            <li>Correct inaccurate data</li>
                            <li>Request deletion of your data</li>
                            <li>Object to processing of your data</li>
                            <li>Request data portability</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">6. Third-Party Services</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">We may share your information with third-party service providers who perform services on our behalf, such as payment processing and data verification through government databases.</p>

                        <h2 class="text-h5 font-weight-bold mb-4">7. Contact Us</h2>
                        <p class="text-body-1 text-grey-darken-1">If you have any questions about this Privacy Policy, please contact us at <a href="mailto:privacy@easeverifier.com" class="text-primary">privacy@easeverifier.com</a>.</p>
                    </v-card>
                </v-container>
            </section>
        </v-main>
    </v-app>
</template>

<style scoped>
.hero-section { background: linear-gradient(135deg, #1c6434 0%, #0d3d1f 100%); }
</style>

