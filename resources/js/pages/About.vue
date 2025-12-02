<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home, login, register, services, pricing, documentation } from '@/routes';

const team = [
    { name: 'John Adebayo', role: 'CEO & Founder', avatar: 'mdi-account-circle' },
    { name: 'Amaka Okonkwo', role: 'CTO', avatar: 'mdi-account-circle' },
    { name: 'Chidi Nwosu', role: 'Head of Operations', avatar: 'mdi-account-circle' },
];

const values = [
    { icon: 'mdi-shield-check', title: 'Trust & Security', desc: 'We prioritize data security and privacy in everything we do.' },
    { icon: 'mdi-lightning-bolt', title: 'Innovation', desc: 'Continuously improving our technology to serve you better.' },
    { icon: 'mdi-handshake', title: 'Partnership', desc: 'We grow when our customers succeed.' },
    { icon: 'mdi-heart', title: 'Customer First', desc: 'Your needs drive our development priorities.' },
];
</script>

<template>
    <Head title="About Us - Nigeria's Trusted Identity Verification Company | EaseVerifier">
        <meta name="description" content="Learn about EaseVerifier - Nigeria's leading identity verification company. We help 500+ businesses verify NIN, BVN, and CAC records instantly with our secure API." />
        <meta name="keywords" content="about EaseVerifier, identity verification company Nigeria, KYC provider Nigeria, verification API company" />
        <meta property="og:title" content="About Us - EaseVerifier" />
        <meta property="og:description" content="Nigeria's leading identity verification company helping 500+ businesses verify identities securely." />
        <meta property="og:type" content="website" />
        <link rel="canonical" href="https://easeverifier.com/about" />
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
                    <h1 class="text-h3 text-md-h2 font-weight-bold text-white mb-4">About EaseVerifier</h1>
                    <p class="text-h6 text-white opacity-80 mx-auto" style="max-width: 600px;">Nigeria's trusted identity verification platform, helping businesses verify identities with confidence.</p>
                </v-container>
            </section>

            <section class="py-16">
                <v-container>
                    <v-row align="center">
                        <v-col cols="12" md="6">
                            <h2 class="text-h4 font-weight-bold mb-4">Our Mission</h2>
                            <p class="text-body-1 text-grey-darken-1 mb-4">EaseVerifier was founded with a simple mission: to make identity verification simple, fast, and affordable for Nigerian businesses of all sizes.</p>
                            <p class="text-body-1 text-grey-darken-1 mb-4">We believe that every business should have access to reliable verification tools to prevent fraud, ensure compliance, and build trust with their customers.</p>
                            <p class="text-body-1 text-grey-darken-1">Today, we serve over 500+ businesses across Nigeria, processing millions of verification requests every month.</p>
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-card class="pa-8 bg-primary-lighten-5">
                                <v-row>
                                    <v-col cols="6" class="text-center">
                                        <div class="text-h3 font-weight-bold text-primary">500+</div>
                                        <div class="text-body-2 text-grey">Businesses</div>
                                    </v-col>
                                    <v-col cols="6" class="text-center">
                                        <div class="text-h3 font-weight-bold text-primary">10M+</div>
                                        <div class="text-body-2 text-grey">Verifications</div>
                                    </v-col>
                                    <v-col cols="6" class="text-center">
                                        <div class="text-h3 font-weight-bold text-primary">99.9%</div>
                                        <div class="text-body-2 text-grey">Uptime</div>
                                    </v-col>
                                    <v-col cols="6" class="text-center">
                                        <div class="text-h3 font-weight-bold text-primary">&lt;1s</div>
                                        <div class="text-body-2 text-grey">Response Time</div>
                                    </v-col>
                                </v-row>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-container>
            </section>

            <section class="py-16 bg-grey-lighten-5">
                <v-container>
                    <h2 class="text-h4 font-weight-bold text-center mb-8">Our Values</h2>
                    <v-row>
                        <v-col v-for="value in values" :key="value.title" cols="12" sm="6" md="3">
                            <v-card class="pa-6 text-center h-100" flat>
                                <v-avatar color="primary" size="64" class="mb-4">
                                    <v-icon color="white" size="32">{{ value.icon }}</v-icon>
                                </v-avatar>
                                <h3 class="text-h6 font-weight-bold mb-2">{{ value.title }}</h3>
                                <p class="text-body-2 text-grey-darken-1">{{ value.desc }}</p>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-container>
            </section>

            <section class="py-16">
                <v-container>
                    <h2 class="text-h4 font-weight-bold text-center mb-8">Leadership Team</h2>
                    <v-row justify="center">
                        <v-col v-for="member in team" :key="member.name" cols="12" sm="6" md="4">
                            <v-card class="pa-6 text-center" flat>
                                <v-avatar color="grey-lighten-3" size="100" class="mb-4">
                                    <v-icon size="60" color="grey">{{ member.avatar }}</v-icon>
                                </v-avatar>
                                <h3 class="text-h6 font-weight-bold">{{ member.name }}</h3>
                                <p class="text-body-2 text-grey">{{ member.role }}</p>
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

