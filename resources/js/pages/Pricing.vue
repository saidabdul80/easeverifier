<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home, login, register, services, pricing, documentation } from '@/routes';

interface Service {
    id: number;
    name: string;
    slug: string;
    description: string;
    icon: string;
    default_price: number;
    is_active: boolean;
}

defineProps<{
    services: Service[];
}>();

const pricingTiers = [
    // { name: 'Starter', price: 0, description: 'Perfect for testing and small projects', verifications: '100 free', features: ['100 free verifications', 'NIN Verification', 'Web Dashboard', 'Email Support', 'Basic Analytics'], buttonText: 'Start Free', highlight: false },
    // { name: 'Growth', price: 50000, description: 'For growing businesses with higher volume', verifications: 'Pay-as-you-go', features: ['Unlimited verifications', 'All verification types', 'API Access', 'Priority Support', 'Advanced Analytics', 'Custom Pricing'], buttonText: 'Get Started', highlight: true },
    // { name: 'Enterprise', price: null, description: 'Custom solutions for large organizations', verifications: 'Volume discounts', features: ['Volume discounts', 'Dedicated support', 'SLA guarantee', 'Custom integration', 'White-label option', 'On-premise option'], buttonText: 'Contact Sales', highlight: false },
];

const servicePrices = [
    { name: 'NIN Verification', price: 100, unit: 'per lookup' },
    { name: 'BVN Verification', price: 150, unit: 'per lookup' },
    { name: 'CAC Verification', price: 200, unit: 'per lookup' },
    { name: "Driver's License", price: 120, unit: 'per lookup' },
    { name: "Voter's Card", price: 100, unit: 'per lookup' },
];

const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(price);
};
</script>

<template>
    <Head title="Pricing - Affordable Identity Verification API | EaseVerifier">
        <meta name="description" content="Transparent pay-as-you-go pricing for NIN, BVN, and CAC verification. Starting from ₦100 per verification. No hidden fees, volume discounts available." />
        <meta name="keywords" content="verification API pricing, NIN verification cost, BVN verification price, identity verification pricing Nigeria, KYC pricing" />
        <meta property="og:title" content="Pricing - EaseVerifier" />
        <meta property="og:description" content="Affordable identity verification API pricing. Pay-as-you-go from ₦100 per verification. Volume discounts available." />
        <meta property="og:type" content="website" />
        <link rel="canonical" href="https://easeverifier.com/pricing" />
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
                    <v-btn variant="text" :href="pricing()" color="primary">Pricing</v-btn>
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
            <section class="pricing-hero py-16 text-center">
                <v-container>
                    <v-chip color="secondary" variant="flat" class="mb-4">Simple Pricing</v-chip>
                    <h1 class="text-h3 text-md-h2 font-weight-bold text-white mb-4">Pay Only for What You Use</h1>
                    <p class="text-h6 text-white opacity-80 mx-auto" style="max-width: 600px;">No monthly fees, no hidden charges. Fund your wallet and verify as needed.</p>
                </v-container>
            </section>

            <section class="py-16">
                <v-container>
                    <v-row justify="center">
                        <v-col v-for="tier in pricingTiers" :key="tier.name" cols="12" md="4">
                            <v-card :class="{ 'border-primary border-2': tier.highlight }" class="h-100">
                                <v-chip v-if="tier.highlight" color="primary" class="position-absolute" style="top: -12px; left: 50%; transform: translateX(-50%);">Most Popular</v-chip>
                                <v-card-text class="pa-6 text-center">
                                    <h3 class="text-h5 font-weight-bold mb-2">{{ tier.name }}</h3>
                                    <p class="text-body-2 text-grey mb-4">{{ tier.description }}</p>
                                    <div class="mb-4">
                                        <span v-if="tier.price !== null" class="text-h3 font-weight-bold text-primary">{{ formatPrice(tier.price) }}</span>
                                        <span v-else class="text-h4 font-weight-bold text-primary">Custom</span>
                                        <p class="text-caption text-grey">{{ tier.verifications }}</p>
                                    </div>
                                    <v-btn :color="tier.highlight ? 'primary' : 'grey-darken-1'" :variant="tier.highlight ? 'flat' : 'outlined'" block size="large" class="mb-6" :href="register()">{{ tier.buttonText }}</v-btn>
                                    <v-divider class="mb-4" />
                                    <v-list density="compact" class="text-left">
                                        <v-list-item v-for="feature in tier.features" :key="feature" class="px-0">
                                            <template #prepend><v-icon color="success" size="small">mdi-check-circle</v-icon></template>
                                            <v-list-item-title class="text-body-2">{{ feature }}</v-list-item-title>
                                        </v-list-item>
                                    </v-list>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-container>
            </section>

            <section class="py-16 bg-grey-lighten-5">
                <v-container>
                    <h2 class="text-h4 font-weight-bold text-center mb-8">Service Pricing</h2>
                    <v-card max-width="600" class="mx-auto">
                        <v-table>
                            <thead><tr><th class="text-left">Service</th><th class="text-right">Price</th></tr></thead>
                            <tbody>
                                <tr v-for="service in servicePrices" :key="service.name">
                                    <td>{{ service.name }}</td>
                                    <td class="text-right"><span class="font-weight-bold text-primary">{{ formatPrice(service.price) }}</span> <span class="text-caption text-grey">{{ service.unit }}</span></td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>
                    <p class="text-center text-caption text-grey mt-4">* Volume discounts available for Enterprise customers</p>
                </v-container>
            </section>
        </v-main>
    </v-app>
</template>

<style scoped>
.pricing-hero { background: linear-gradient(135deg, #1c6434 0%, #0d3d1f 100%); }
</style>

