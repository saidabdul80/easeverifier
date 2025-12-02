<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home, login, register, services, pricing, documentation } from '@/routes';

const posts = [
    {
        title: 'Understanding NIN Verification in Nigeria',
        excerpt: 'Learn how the National Identification Number (NIN) verification process works and why it\'s essential for businesses.',
        date: 'Nov 15, 2024',
        category: 'Education',
        image: 'mdi-card-account-details',
    },
    {
        title: 'Best Practices for KYC Compliance',
        excerpt: 'A comprehensive guide to implementing Know Your Customer (KYC) processes that meet regulatory requirements.',
        date: 'Nov 10, 2024',
        category: 'Compliance',
        image: 'mdi-clipboard-check',
    },
    {
        title: 'API Integration: Getting Started Guide',
        excerpt: 'Step-by-step tutorial on integrating EaseVerifier API into your application for seamless verification.',
        date: 'Nov 5, 2024',
        category: 'Technical',
        image: 'mdi-api',
    },
    {
        title: 'Preventing Identity Fraud in Fintech',
        excerpt: 'How fintech companies can leverage identity verification to reduce fraud and build customer trust.',
        date: 'Oct 28, 2024',
        category: 'Security',
        image: 'mdi-shield-lock',
    },
    {
        title: 'BVN vs NIN: What\'s the Difference?',
        excerpt: 'Understanding the key differences between Bank Verification Number and National Identification Number.',
        date: 'Oct 20, 2024',
        category: 'Education',
        image: 'mdi-help-circle',
    },
    {
        title: 'EaseVerifier Achieves 99.9% Uptime',
        excerpt: 'We\'re proud to announce our platform has maintained 99.9% uptime throughout 2024.',
        date: 'Oct 15, 2024',
        category: 'News',
        image: 'mdi-chart-line',
    },
];

const categories = ['All', 'Education', 'Compliance', 'Technical', 'Security', 'News'];
</script>

<template>
    <Head title="Blog - Identity Verification News & Insights | EaseVerifier">
        <meta name="description" content="Stay updated with the latest news on identity verification, KYC compliance, fraud prevention, and API integration tips from EaseVerifier's expert team." />
        <meta name="keywords" content="identity verification blog, KYC news Nigeria, NIN verification updates, fintech compliance, fraud prevention tips" />
        <meta property="og:title" content="Blog - EaseVerifier" />
        <meta property="og:description" content="Expert insights on identity verification, KYC compliance, and fraud prevention for Nigerian businesses." />
        <meta property="og:type" content="blog" />
        <link rel="canonical" href="https://easeverifier.com/blog" />
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
                    <h1 class="text-h3 text-md-h2 font-weight-bold text-white mb-4">Blog & Resources</h1>
                    <p class="text-h6 text-white opacity-80 mx-auto" style="max-width: 600px;">Insights, tutorials, and updates from the EaseVerifier team.</p>
                </v-container>
            </section>

            <section class="py-8">
                <v-container>
                    <div class="d-flex flex-wrap ga-2 justify-center mb-8">
                        <v-chip v-for="cat in categories" :key="cat" :color="cat === 'All' ? 'primary' : undefined" variant="outlined">{{ cat }}</v-chip>
                    </div>
                    <v-row>
                        <v-col v-for="post in posts" :key="post.title" cols="12" md="6" lg="4">
                            <v-card class="h-100" hover>
                                <div class="bg-primary-lighten-5 pa-8 text-center">
                                    <v-icon color="primary" size="48">{{ post.image }}</v-icon>
                                </div>
                                <v-card-text class="pa-6">
                                    <div class="d-flex align-center mb-2">
                                        <v-chip size="small" color="secondary" variant="flat">{{ post.category }}</v-chip>
                                        <v-spacer />
                                        <span class="text-caption text-grey">{{ post.date }}</span>
                                    </div>
                                    <h3 class="text-h6 font-weight-bold mb-2">{{ post.title }}</h3>
                                    <p class="text-body-2 text-grey-darken-1 mb-4">{{ post.excerpt }}</p>
                                    <v-btn variant="text" color="primary" class="px-0">Read More <v-icon end>mdi-arrow-right</v-icon></v-btn>
                                </v-card-text>
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

