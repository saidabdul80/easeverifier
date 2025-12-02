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

const allServices = [
    { name: 'NIN Verification', slug: 'nin', icon: 'mdi-card-account-details', description: 'Verify National Identification Number to retrieve personal details including name, date of birth, and photograph.', features: ['Full Name', 'Date of Birth', 'Gender', 'Photo', 'Phone Number', 'Address'] },
    { name: 'BVN Verification', slug: 'bvn', icon: 'mdi-bank', description: 'Verify Bank Verification Number to retrieve banking and personal information securely.', features: ['Full Name', 'Date of Birth', 'Phone', 'Bank Info', 'Account Status'] , comingSoon: true},
    { name: 'CAC Verification', slug: 'cac', icon: 'mdi-domain', description: 'Verify Corporate Affairs Commission registration to confirm business legitimacy.', features: ['Company Name', 'RC Number', 'Directors', 'Address', 'Status'], comingSoon: true },
    { name: "Driver's License", slug: 'drivers-license', icon: 'mdi-card-account-details-outline', description: 'Verify Nigerian Driver\'s License to confirm license holder details and validity.', features: ['Full Name', 'License Number', 'Expiry Date', 'Class', 'Photo'], comingSoon: true },
    { name: "Voter's Card", slug: 'voters-card', icon: 'mdi-vote', description: 'Verify INEC Voter\'s Card to confirm voter registration details.', features: ['Full Name', 'VIN', 'Polling Unit', 'State', 'LGA'],comingSoon: true },
    { name: 'International Passport', slug: 'passport', icon: 'mdi-passport', description: 'Verify Nigerian International Passport details for identity confirmation.', features: ['Full Name', 'Passport No', 'Expiry Date', 'Photo', 'Nationality'], comingSoon: true },
];
</script>

<template>
    <Head title="Identity Verification Services - NIN, BVN, CAC API | EaseVerifier">
        <meta name="description" content="Explore EaseVerifier's identity verification services: NIN verification, BVN verification, CAC business verification, Driver's License, and Voter's Card verification API for Nigerian businesses." />
        <meta name="keywords" content="NIN verification API, BVN verification service, CAC verification, driver's license verification Nigeria, voter's card verification, identity API Nigeria" />
        <meta property="og:title" content="Identity Verification Services | EaseVerifier" />
        <meta property="og:description" content="Comprehensive identity verification services for Nigerian businesses - NIN, BVN, CAC, Driver's License and more." />
        <meta property="og:type" content="website" />
        <link rel="canonical" href="https://easeverifier.com/services" />
    </Head>

    <v-app>
        <!-- Navigation -->
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
                    <v-btn variant="text" :href="services()" color="primary">Services</v-btn>
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
            <!-- Hero Section -->
            <section class="services-hero py-16 text-center">
                <v-container>
                    <v-chip color="secondary" variant="flat" class="mb-4">
                        <v-icon start size="small">mdi-api</v-icon>
                        API & Web App Verification
                    </v-chip>
                    <h1 class="text-h3 text-md-h2 font-weight-bold text-white mb-4">
                        Verification Services
                    </h1>
                    <p class="text-h6 text-white opacity-80 mx-auto" style="max-width: 600px;">
                        Access Nigeria's most comprehensive identity verification platform. 
                        Verify NIN, BVN, CAC, and more through our simple API or web dashboard.
                    </p>
                </v-container>
            </section>

            <!-- Services Grid -->
            <section class="py-16 bg-grey-lighten-5">
                <v-container>
                    <v-row>
                        <v-col v-for="service in allServices" :key="service.slug" cols="12" md="6" lg="4">
                            <v-card class="h-100 service-card" :class="{ 'coming-soon-card': service.comingSoon }">
                                <v-card-text class="pa-6">
                                    <div class="d-flex align-center mb-4">
                                        <v-avatar :color="service.comingSoon ? 'grey-lighten-1' : 'primary-lighten-5'" size="56">
                                            <v-icon :color="service.comingSoon ? 'grey' : 'primary'" size="28">{{ service.icon }}</v-icon>
                                        </v-avatar>
                                        <v-chip v-if="service.comingSoon" color="info" size="small" class="ml-auto">Coming Soon</v-chip>
                                    </div>

                                    <h3 class="text-h6 font-weight-bold mb-2">{{ service.name }}</h3>
                                    <p class="text-body-2 text-grey-darken-1 mb-4">{{ service.description }}</p>

                                    <v-divider class="mb-4" />

                                    <p class="text-caption text-uppercase font-weight-bold text-grey mb-2">Data Returned</p>
                                    <div class="d-flex flex-wrap ga-1">
                                        <v-chip v-for="feature in service.features" :key="feature" size="x-small" variant="tonal" color="primary">
                                            {{ feature }}
                                        </v-chip>
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-container>
            </section>

            <!-- CTA Section -->
            <section class="py-16 cta-gradient">
                <v-container class="text-center">
                    <h2 class="text-h4 font-weight-bold text-white mb-4">Ready to Get Started?</h2>
                    <p class="text-body-1 text-white opacity-80 mb-6">Create an account and start verifying identities in minutes.</p>
                    <v-btn color="secondary" size="x-large" :href="register()" class="mr-4">
                        <v-icon start>mdi-rocket-launch</v-icon>
                        Create Free Account
                    </v-btn>
                    <v-btn variant="outlined" color="white" size="x-large" :href="documentation()">
                        <v-icon start>mdi-book-open-variant</v-icon>
                        View Documentation
                    </v-btn>
                </v-container>
            </section>
        </v-main>
    </v-app>
</template>

<style scoped>
.services-hero {
    background: linear-gradient(135deg, #1c6434 0%, #0d3d1f 100%);
}
.cta-gradient {
    background: linear-gradient(135deg, #1c6434 0%, #2e7d4a 100%);
}
.service-card {
    transition: all 0.3s ease;
}
.service-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}
.coming-soon-card {
    opacity: 0.8;
}
</style>

