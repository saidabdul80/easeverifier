<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home, login, register, services, pricing, documentation } from '@/routes';
import { ref } from 'vue';

const form = ref({ name: '', email: '', subject: '', message: '' });
const loading = ref(false);

const contactInfo = [
    { icon: 'mdi-email', title: 'Email', value: 'support@easeverifier.com', link: 'mailto:support@easeverifier.com' },
    { icon: 'mdi-phone', title: 'Phone', value: '+234 800 123 4567', link: 'tel:+2348001234567' },
    { icon: 'mdi-map-marker', title: 'Address', value: 'Lagos, Nigeria', link: null },
];

const submitForm = () => {
    loading.value = true;
    setTimeout(() => { loading.value = false; alert('Message sent! We\'ll get back to you soon.'); }, 1500);
};
</script>

<template>
    <Head title="Contact Us - Get Support for Identity Verification | EaseVerifier">
        <meta name="description" content="Contact EaseVerifier for support with identity verification services. Reach our team via email, phone, or contact form. We're here to help with NIN, BVN, and CAC verification." />
        <meta name="keywords" content="contact EaseVerifier, identity verification support, verification API help, customer support Nigeria" />
        <meta property="og:title" content="Contact Us - EaseVerifier" />
        <meta property="og:description" content="Get in touch with our support team for help with identity verification services." />
        <meta property="og:type" content="website" />
        <link rel="canonical" href="https://easeverifier.com/contact" />
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
                <div class="d-none d-md-flex align-center ga-2">
                    <v-btn variant="text" :href="services()">Services</v-btn>
                    <v-btn variant="text" :href="pricing()">Pricing</v-btn>
                    <v-btn variant="text" :href="documentation()">Documentation</v-btn>
                </div>
                <v-spacer />
                <div class="d-flex ga-2">
                    <v-btn variant="outlined" color="primary" :href="login()">Login</v-btn>
                    <v-btn variant="flat" color="primary" :href="register()" class="d-none d-sm-flex">Get Started</v-btn>
                </div>
            </v-container>
        </v-app-bar>

        <v-main>
            <section class="hero-section py-16 text-center">
                <v-container>
                    <h1 class="text-h3 text-md-h2 font-weight-bold text-white mb-4">Contact Us</h1>
                    <p class="text-h6 text-white opacity-80 mx-auto" style="max-width: 600px;">Have questions? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
                </v-container>
            </section>

            <section class="py-16">
                <v-container>
                    <v-row>
                        <v-col cols="12" md="5">
                            <h2 class="text-h5 font-weight-bold mb-6">Get in Touch</h2>
                            <div v-for="info in contactInfo" :key="info.title" class="d-flex align-center mb-6">
                                <v-avatar color="primary-lighten-5" size="48" class="mr-4">
                                    <v-icon color="primary">{{ info.icon }}</v-icon>
                                </v-avatar>
                                <div>
                                    <div class="text-body-2 text-grey">{{ info.title }}</div>
                                    <a v-if="info.link" :href="info.link" class="text-body-1 font-weight-medium text-decoration-none text-primary">{{ info.value }}</a>
                                    <div v-else class="text-body-1 font-weight-medium">{{ info.value }}</div>
                                </div>
                            </div>
                            <v-divider class="my-6" />
                            <h3 class="text-h6 font-weight-bold mb-4">Follow Us</h3>
                            <div class="d-flex ga-2">
                                <v-btn icon variant="outlined" color="primary"><v-icon>mdi-twitter</v-icon></v-btn>
                                <v-btn icon variant="outlined" color="primary"><v-icon>mdi-linkedin</v-icon></v-btn>
                                <v-btn icon variant="outlined" color="primary"><v-icon>mdi-facebook</v-icon></v-btn>
                            </div>
                        </v-col>
                        <v-col cols="12" md="7">
                            <v-card class="pa-6">
                                <h2 class="text-h5 font-weight-bold mb-6">Send us a Message</h2>
                                <v-form @submit.prevent="submitForm">
                                    <v-row>
                                        <v-col cols="12" sm="6">
                                            <v-text-field v-model="form.name" label="Full Name" variant="outlined" required />
                                        </v-col>
                                        <v-col cols="12" sm="6">
                                            <v-text-field v-model="form.email" label="Email Address" type="email" variant="outlined" required />
                                        </v-col>
                                        <v-col cols="12">
                                            <v-text-field v-model="form.subject" label="Subject" variant="outlined" required />
                                        </v-col>
                                        <v-col cols="12">
                                            <v-textarea v-model="form.message" label="Your Message" variant="outlined" rows="5" required />
                                        </v-col>
                                        <v-col cols="12">
                                            <v-btn type="submit" color="primary" size="large" :loading="loading" block>Send Message</v-btn>
                                        </v-col>
                                    </v-row>
                                </v-form>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-container>
            </section>
        </v-main>
    </v-app>
</template>

<style scoped>
.hero-section { background: linear-gradient(135deg, #1c6434 0%, #0d3d1f 100%); }
</style>

