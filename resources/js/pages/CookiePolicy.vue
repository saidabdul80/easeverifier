<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home, login, register } from '@/routes';
</script>

<template>
    <Head title="Cookie Policy - How We Use Cookies | EaseVerifier">
        <meta name="description" content="Learn about EaseVerifier's use of cookies on our identity verification platform. Understand what cookies we use and how to manage your preferences." />
        <meta name="keywords" content="cookie policy, cookies, website cookies, tracking cookies, privacy settings" />
        <meta property="og:title" content="Cookie Policy - EaseVerifier" />
        <meta property="og:description" content="Information about how EaseVerifier uses cookies on our website and platform." />
        <meta property="og:type" content="article" />
        <link rel="canonical" href="https://easeverifier.com/cookies" />
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
                    <h1 class="text-h3 font-weight-bold text-white mb-2">Cookie Policy</h1>
                    <p class="text-body-1 text-white opacity-80">Last updated: December 1, 2024</p>
                </v-container>
            </section>

            <section class="py-12">
                <v-container>
                    <v-card class="pa-8 mx-auto" max-width="900">
                        <h2 class="text-h5 font-weight-bold mb-4">What Are Cookies?</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">Cookies are small text files stored on your device when you visit a website. They help websites remember your preferences and improve your browsing experience.</p>

                        <h2 class="text-h5 font-weight-bold mb-4">How We Use Cookies</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-2">EaseVerifier uses cookies for the following purposes:</p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li><strong>Essential Cookies:</strong> Required for the website to function properly, including authentication and security.</li>
                            <li><strong>Functional Cookies:</strong> Remember your preferences and settings.</li>
                            <li><strong>Analytics Cookies:</strong> Help us understand how visitors interact with our website.</li>
                            <li><strong>Performance Cookies:</strong> Monitor website performance and identify issues.</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">Types of Cookies We Use</h2>
                        <v-table class="mb-6">
                            <thead><tr><th>Cookie Name</th><th>Purpose</th><th>Duration</th></tr></thead>
                            <tbody>
                                <tr><td>session</td><td>User authentication</td><td>Session</td></tr>
                                <tr><td>XSRF-TOKEN</td><td>Security protection</td><td>Session</td></tr>
                                <tr><td>remember_token</td><td>Remember login</td><td>30 days</td></tr>
                                <tr><td>preferences</td><td>User preferences</td><td>1 year</td></tr>
                            </tbody>
                        </v-table>

                        <h2 class="text-h5 font-weight-bold mb-4">Third-Party Cookies</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">We may use third-party services like Google Analytics that set their own cookies. These cookies are governed by their respective privacy policies.</p>

                        <h2 class="text-h5 font-weight-bold mb-4">Managing Cookies</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">Most browsers allow you to control cookies through their settings. You can block or delete cookies, but this may affect your experience on our website. Note that essential cookies cannot be disabled as they are necessary for the site to function.</p>

                        <h2 class="text-h5 font-weight-bold mb-4">Updates to This Policy</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">We may update this Cookie Policy from time to time. We will notify you of any significant changes by posting a notice on our website.</p>

                        <h2 class="text-h5 font-weight-bold mb-4">Contact Us</h2>
                        <p class="text-body-1 text-grey-darken-1">If you have questions about our use of cookies, please contact us at <a href="mailto:privacy@easeverifier.com" class="text-primary">privacy@easeverifier.com</a>.</p>
                    </v-card>
                </v-container>
            </section>
        </v-main>
    </v-app>
</template>

<style scoped>
.hero-section { background: linear-gradient(135deg, #1c6434 0%, #0d3d1f 100%); }
</style>

