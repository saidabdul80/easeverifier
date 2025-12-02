<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home, login, register, services, pricing, documentation } from '@/routes';
import { ref } from 'vue';

const activeSection = ref('getting-started');
const copied = ref(false);

const sections = [
    { id: 'getting-started', title: 'Getting Started', icon: 'mdi-rocket-launch' },
    { id: 'authentication', title: 'Authentication', icon: 'mdi-lock' },
    { id: 'nin-verification', title: 'NIN Verification', icon: 'mdi-card-account-details' },
    { id: 'bvn-verification', title: 'BVN Verification', icon: 'mdi-bank' },
    { id: 'webhooks', title: 'Webhooks', icon: 'mdi-webhook' },
    { id: 'errors', title: 'Error Codes', icon: 'mdi-alert-circle' },
];

const copyCode = async (code: string) => {
    await navigator.clipboard.writeText(code);
    copied.value = true;
    setTimeout(() => copied.value = false, 2000);
};

const sampleRequest = `curl -X POST https://api.easeverifier.com/api/v1/verify/nin \\
  -H "Authorization: Bearer YOUR_API_KEY" \\
  -H "Content-Type: application/json" \\
  -d '{"nin": "12345678901", "consent": true}'`;

const sampleResponse = `{
  "success": true,
  "data": {
    "nin": "12345678901",
    "first_name": "John",
    "last_name": "Doe",
    "middle_name": "Smith",
    "date_of_birth": "1990-05-15",
    "gender": "Male",
    "phone": "08012345678",
    "photo": "base64_encoded_image..."
  }
}`;
</script>

<template>
    <Head title="API Documentation - NIN, BVN, CAC Verification API Guide | EaseVerifier">
        <meta name="description" content="Complete API documentation for EaseVerifier's identity verification services. Learn how to integrate NIN, BVN, and CAC verification into your application with code examples." />
        <meta name="keywords" content="verification API documentation, NIN API, BVN API, CAC API, identity verification integration, REST API Nigeria, KYC API docs" />
        <meta property="og:title" content="API Documentation - EaseVerifier" />
        <meta property="og:description" content="Complete API guide for identity verification integration. Code examples, authentication, webhooks, and error handling." />
        <meta property="og:type" content="article" />
        <link rel="canonical" href="https://easeverifier.com/documentation" />
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
                    <v-btn variant="text" :href="documentation()" color="primary">Documentation</v-btn>
                </div>
                <v-spacer />
                <div class="d-flex ga-2">
                    <v-btn variant="outlined" color="primary" :href="login()">Login</v-btn>
                    <v-btn variant="flat" color="primary" :href="register()" class="d-none d-sm-flex">Get Started</v-btn>
                </div>
            </v-container>
        </v-app-bar>

        <v-main class="bg-grey-lighten-5">
            <v-container fluid class="pa-0">
                <v-row no-gutters>
                    <!-- Sidebar -->
                    <v-col cols="12" md="3" lg="2" class="d-none d-md-block">
                        <v-card flat class="h-100 rounded-0 border-e" style="position: sticky; top: 64px;">
                            <v-list nav density="compact" class="pa-4">
                                <v-list-item v-for="section in sections" :key="section.id" :active="activeSection === section.id" @click="activeSection = section.id" color="primary" rounded="lg">
                                    <template #prepend><v-icon size="small">{{ section.icon }}</v-icon></template>
                                    <v-list-item-title class="text-body-2">{{ section.title }}</v-list-item-title>
                                </v-list-item>
                            </v-list>
                        </v-card>
                    </v-col>

                    <!-- Content -->
                    <v-col cols="12" md="9" lg="10">
                        <div class="pa-6 pa-md-12" style="max-width: 900px;">
                            <!-- Getting Started -->
                            <section v-show="activeSection === 'getting-started'" class="mb-12">
                                <h1 class="text-h4 font-weight-bold mb-4">Getting Started</h1>
                                <p class="text-body-1 text-grey-darken-1 mb-6">Welcome to EaseVerifier API. This guide will help you integrate our identity verification services into your application.</p>
                                
                                <v-card class="mb-6" variant="outlined">
                                    <v-card-title class="text-subtitle-1 font-weight-bold">Base URL</v-card-title>
                                    <v-card-text><code class="bg-grey-lighten-4 pa-2 rounded">https://api.easeverifier.com/v1</code></v-card-text>
                                </v-card>

                                <h3 class="text-h6 font-weight-bold mb-3">Quick Start Steps</h3>
                                <v-timeline density="compact" side="end">
                                    <v-timeline-item dot-color="primary" size="small"><strong>1. Create Account</strong> - Sign up at easeverifier.com</v-timeline-item>
                                    <v-timeline-item dot-color="primary" size="small"><strong>2. Get API Key</strong> - Generate your API credentials from dashboard</v-timeline-item>
                                    <v-timeline-item dot-color="primary" size="small"><strong>3. Fund Wallet</strong> - Add funds to your wallet</v-timeline-item>
                                    <v-timeline-item dot-color="success" size="small"><strong>4. Start Verifying</strong> - Make your first API call</v-timeline-item>
                                </v-timeline>
                            </section>

                            <!-- Authentication -->
                            <section v-show="activeSection === 'authentication'" class="mb-12">
                                <h1 class="text-h4 font-weight-bold mb-4">Authentication</h1>
                                <p class="text-body-1 text-grey-darken-1 mb-6">All API requests require authentication using Bearer tokens.</p>
                                <v-alert type="info" variant="tonal" class="mb-6"><strong>Security:</strong> Never expose your API key in client-side code.</v-alert>
                                <v-card variant="outlined" class="mb-4">
                                    <v-card-title class="d-flex align-center"><span>Example Header</span><v-spacer /><v-btn size="small" variant="text" @click="copyCode('Authorization: Bearer YOUR_API_KEY')">{{ copied ? 'Copied!' : 'Copy' }}</v-btn></v-card-title>
                                    <v-card-text class="bg-grey-darken-4"><pre class="text-white text-body-2">Authorization: Bearer YOUR_API_KEY</pre></v-card-text>
                                </v-card>
                            </section>

                            <!-- NIN Verification -->
                            <section v-show="activeSection === 'nin-verification'" class="mb-12">
                                <h1 class="text-h4 font-weight-bold mb-4">NIN Verification</h1>
                                <p class="text-body-1 text-grey-darken-1 mb-6">Verify National Identification Numbers and retrieve associated personal data.</p>
                                <v-chip color="success" class="mb-4">POST</v-chip><code class="ml-2">/nin/verify</code>
                                <v-card variant="outlined" class="mb-4 mt-4">
                                    <v-card-title>Request Example</v-card-title>
                                    <v-card-text class="bg-grey-darken-4"><pre class="text-green-lighten-1 text-body-2" style="white-space: pre-wrap;">{{ sampleRequest }}</pre></v-card-text>
                                </v-card>
                                <v-card variant="outlined">
                                    <v-card-title>Response Example</v-card-title>
                                    <v-card-text class="bg-grey-darken-4"><pre class="text-blue-lighten-1 text-body-2">{{ sampleResponse }}</pre></v-card-text>
                                </v-card>
                            </section>

                            <!-- BVN Section -->
                            <section v-show="activeSection === 'bvn-verification'" class="mb-12">
                                <h1 class="text-h4 font-weight-bold mb-4">BVN Verification</h1>
                                <v-alert type="warning" variant="tonal">BVN Verification is coming soon. Contact us for early access.</v-alert>
                            </section>

                            <!-- Webhooks -->
                            <section v-show="activeSection === 'webhooks'" class="mb-12">
                                <h1 class="text-h4 font-weight-bold mb-4">Webhooks</h1>
                                <p class="text-body-1 text-grey-darken-1 mb-6">Configure webhooks to receive real-time notifications about verification results.</p>
                                <v-card variant="outlined"><v-card-text>Configure your webhook URL in the API settings of your dashboard.</v-card-text></v-card>
                            </section>

                            <!-- Errors -->
                            <section v-show="activeSection === 'errors'" class="mb-12">
                                <h1 class="text-h4 font-weight-bold mb-4">Error Codes</h1>
                                <v-table><thead><tr><th>Code</th><th>Description</th></tr></thead>
                                <tbody>
                                    <tr><td><code>400</code></td><td>Bad Request - Invalid parameters</td></tr>
                                    <tr><td><code>401</code></td><td>Unauthorized - Invalid or missing API key</td></tr>
                                    <tr><td><code>402</code></td><td>Payment Required - Insufficient wallet balance</td></tr>
                                    <tr><td><code>404</code></td><td>Not Found - Identity not found</td></tr>
                                    <tr><td><code>429</code></td><td>Too Many Requests - Rate limit exceeded</td></tr>
                                    <tr><td><code>500</code></td><td>Server Error - Please try again</td></tr>
                                </tbody></v-table>
                            </section>
                        </div>
                    </v-col>
                </v-row>
            </v-container>
        </v-main>
    </v-app>
</template>

