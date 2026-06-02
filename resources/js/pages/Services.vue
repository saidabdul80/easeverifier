<script setup lang="ts">
import PublicTopNav from '@/components/PublicTopNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import { documentation, register } from '@/routes';

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
    {
        name: 'NIN Verification',
        slug: 'nin',
        icon: 'mdi-card-account-details',
        description: 'Identity checks for onboarding and KYC.',
        features: ['Full name', 'DOB', 'Gender', 'Photo'],
        accent: 'service-nin',
    },
    {
        name: 'BVN Verification',
        slug: 'bvn',
        icon: 'mdi-bank',
        description: 'Bank-linked identity validation for finance flows.',
        features: ['Full name', 'Phone', 'DOB', 'Status'],
        accent: 'service-bvn',
        comingSoon: true,
    },
    {
        name: 'CAC Verification',
        slug: 'cac',
        icon: 'mdi-domain',
        description: 'Business record checks for company trust.',
        features: ['Company', 'RC number', 'Directors', 'Status'],
        accent: 'service-cac',
        comingSoon: true,
    },
    {
        name: "Driver's License",
        slug: 'drivers-license',
        icon: 'mdi-card-account-details-outline',
        description: 'License-holder identity and validity checks.',
        features: ['Name', 'License no', 'Expiry', 'Class'],
        accent: 'service-license',
        comingSoon: true,
    },
    {
        name: "Voter's Card",
        slug: 'voters-card',
        icon: 'mdi-vote',
        description: 'Voter registration lookup and validation.',
        features: ['Name', 'VIN', 'Polling unit', 'LGA'],
        accent: 'service-voter',
        comingSoon: true,
    },
    {
        name: 'International Passport',
        slug: 'passport',
        icon: 'mdi-passport',
        description: 'Passport identity confirmation for travel-related checks.',
        features: ['Name', 'Passport no', 'Expiry', 'Nationality'],
        accent: 'service-passport',
        comingSoon: true,
    },
];

const trustPoints = [
    { value: 'Fast', label: 'API-first responses' },
    { value: 'Simple', label: 'Wallet pricing model' },
    { value: 'Clean', label: 'Docs and dashboard flow' },
];
</script>

<template>
    <Head title="Identity Verification Services - NIN, BVN, CAC API | EaseVerifier">
        <meta name="description" content="Explore EaseVerifier's identity verification services: NIN verification, BVN verification, CAC business verification, Driver's License, and Voter's Card verification API for Nigerian businesses." />
        <meta name="keywords" content="NIN verification API, BVN verification service, CAC verification, driver's license verification Nigeria, voter's card verification, identity API Nigeria" />
        <meta property="og:title" content="Identity Verification Services | EaseVerifier" />
        <meta property="og:description" content="Comprehensive identity verification services for Nigerian businesses - NIN, BVN, CAC, Driver's License and more." />
        <meta property="og:type" content="website" />
        <link rel="canonical" href="https://verify.ashlabtech.ng/services" />
    </Head>

    <v-app class="services-app">
        <PublicTopNav current="services" />

        <v-main>
            <section class="services-hero">
                <div class="hero-glow hero-glow-a" />
                <div class="hero-glow hero-glow-b" />
                <v-container class="hero-content">
                    <div class="hero-badge">Services</div>
                    <v-row align="center">
                        <v-col cols="12" md="7">
                            <h1 class="hero-title">Checks that fit real onboarding.</h1>
                            <p class="hero-copy">NIN, BVN, CAC, and more through one cleaner verification workflow.</p>
                        </v-col>
                        <v-col cols="12" md="5">
                            <div class="trust-strip">
                                <div v-for="point in trustPoints" :key="point.label" class="trust-pill">
                                    <div class="trust-value">{{ point.value }}</div>
                                    <div class="trust-label">{{ point.label }}</div>
                                </div>
                            </div>
                        </v-col>
                    </v-row>
                </v-container>
            </section>

            <section class="services-body">
                <v-container>
                    <div class="section-head">
                        <div class="section-kicker">Available and Planned</div>
                        <h2 class="section-title">A focused service lineup.</h2>
                    </div>

                    <v-row>
                        <v-col v-for="service in allServices" :key="service.slug" cols="12" md="6" lg="4">
                            <v-card class="service-card h-100" :class="{ 'is-coming': service.comingSoon }" hover>
                                <v-card-text class="pa-6">
                                    <div class="d-flex align-center justify-space-between mb-4">
                                        <div class="service-icon" :class="service.accent">
                                            <v-icon color="white" size="24">{{ service.icon }}</v-icon>
                                        </div>
                                        <v-chip v-if="service.comingSoon" size="small" color="info" variant="flat">Coming Soon</v-chip>
                                        <v-chip v-else size="small" color="success" variant="flat">Live</v-chip>
                                    </div>

                                    <h3 class="text-h6 font-weight-bold text-grey-darken-4 mb-2">{{ service.name }}</h3>
                                    <p class="text-body-2 text-grey-darken-1 mb-4">{{ service.description }}</p>

                                    <div class="chip-cluster">
                                        <v-chip v-for="feature in service.features" :key="feature" size="small" variant="outlined" color="primary">
                                            {{ feature }}
                                        </v-chip>
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-container>
            </section>

            <section class="cta-shell">
                <v-container>
                    <div class="cta-band">
                        <div>
                            <div class="section-kicker text-white text-opacity-80">Start</div>
                            <h2 class="cta-title">Use the dashboard or ship with the API.</h2>
                        </div>
                        <div class="d-flex flex-wrap ga-3">
                            <Link :href="register()"><v-btn color="secondary" size="large" class="nav-action">Create Account</v-btn></Link>
                            <Link :href="documentation()"><v-btn variant="outlined" color="white" size="large" class="nav-link-btn">View Docs</v-btn></Link>
                        </div>
                    </div>
                </v-container>
            </section>
        </v-main>
    </v-app>
</template>

<style scoped>
.services-app {
    background:
        radial-gradient(circle at top left, rgba(111, 211, 153, 0.16), transparent 30%),
        linear-gradient(180deg, #f6fbf7 0%, #edf5ef 100%);
}

.services-hero {
    position: relative;
    overflow: hidden;
    padding: 6.8rem 0 3.8rem;
    background: linear-gradient(135deg, #0d3a1c 0%, #12542a 56%, #1c7f44 100%);
}

.hero-content {
    position: relative;
    z-index: 1;
}

.hero-glow {
    position: absolute;
    border-radius: 999px;
    filter: blur(8px);
}

.hero-glow-a {
    top: 40px;
    right: -80px;
    width: 280px;
    height: 280px;
    background: rgba(244, 199, 76, 0.18);
}

.hero-glow-b {
    left: -80px;
    bottom: -90px;
    width: 260px;
    height: 260px;
    background: rgba(255, 255, 255, 0.08);
}

.hero-badge {
    display: inline-block;
    padding: 0.45rem 0.8rem;
    margin-bottom: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    color: #fff;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.hero-title {
    margin: 0 0 0.85rem;
    color: #fff;
    font-size: clamp(2.5rem, 5.5vw, 4.6rem);
    line-height: 0.96;
    letter-spacing: -0.05em;
    max-width: 11ch;
}

.hero-copy {
    margin: 0;
    max-width: 34rem;
    color: rgba(255, 255, 255, 0.76);
    font-size: 1rem;
}

.trust-strip {
    display: grid;
    gap: 0.9rem;
}

.trust-pill {
    padding: 1rem 1.1rem;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
}

.trust-value {
    color: #fff;
    font-size: 1.15rem;
    font-weight: 700;
}

.trust-label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.82rem;
}

.services-body {
    padding: 4.5rem 0;
}

.section-head {
    margin-bottom: 2rem;
}

.section-kicker {
    margin-bottom: 0.5rem;
    color: #1a7d42;
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.section-title {
    margin: 0;
    color: #13261c;
    font-size: clamp(1.8rem, 3.4vw, 2.8rem);
    line-height: 1.04;
    letter-spacing: -0.04em;
}

.service-card {
    border: 1px solid rgba(23, 83, 44, 0.08);
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 18px 40px rgba(22, 63, 37, 0.05);
    transition: transform 0.28s ease, box-shadow 0.28s ease;
}

.service-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 28px 60px rgba(22, 63, 37, 0.09);
}

.is-coming {
    opacity: 0.92;
}

.service-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 18px;
}

.service-nin { background: linear-gradient(135deg, #1a7d42, #26a269); }
.service-bvn { background: linear-gradient(135deg, #d18f10, #f4c74c); }
.service-cac { background: linear-gradient(135deg, #145c9e, #2f85de); }
.service-license { background: linear-gradient(135deg, #7b3fc1, #a56ef5); }
.service-voter { background: linear-gradient(135deg, #9d3c3c, #d96a6a); }
.service-passport { background: linear-gradient(135deg, #1f5e78, #45a2c9); }

.chip-cluster {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
}

.cta-shell {
    padding: 0 0 4.5rem;
}

.cta-band {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 2rem;
    border-radius: 30px;
    background: linear-gradient(135deg, #0f3e20 0%, #196b37 100%);
}

.cta-title {
    margin: 0;
    color: #fff;
    font-size: clamp(1.8rem, 4vw, 2.7rem);
    line-height: 1.02;
    letter-spacing: -0.04em;
    max-width: 13ch;
}

@media (max-width: 960px) {
    .services-hero {
        padding-top: 6rem;
    }

    .hero-title {
        max-width: none;
    }

    .trust-strip {
        margin-top: 1.5rem;
    }

    .cta-band {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
