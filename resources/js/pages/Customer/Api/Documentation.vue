<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { ref } from 'vue';

defineProps<{ user: { name: string; email: string } }>();

const activeTab = ref('overview');

const codeExamples = {
    curl: `curl -X POST https://api.easeverifier.com/v1/verify/nin \\
  -H "Authorization: Bearer YOUR_API_KEY" \\
  -H "X-API-Secret: YOUR_API_SECRET" \\
  -H "Content-Type: application/json" \\
  -d '{"nin": "12345678901"}'`,
    php: `<?php
$client = new GuzzleHttp\\Client();
$response = $client->post('https://api.easeverifier.com/v1/verify/nin', [
    'headers' => [
        'Authorization' => 'Bearer YOUR_API_KEY',
        'X-API-Secret' => 'YOUR_API_SECRET',
    ],
    'json' => ['nin' => '12345678901']
]);
$data = json_decode($response->getBody(), true);`,
    javascript: `const response = await fetch('https://api.easeverifier.com/v1/verify/nin', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_API_KEY',
    'X-API-Secret': 'YOUR_API_SECRET',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({ nin: '12345678901' })
});
const data = await response.json();`,
    python: `import requests

response = requests.post(
    'https://api.easeverifier.com/v1/verify/nin',
    headers={
        'Authorization': 'Bearer YOUR_API_KEY',
        'X-API-Secret': 'YOUR_API_SECRET'
    },
    json={'nin': '12345678901'}
)
data = response.json()`
};
</script>

<template>
    <Head title="API Documentation - EaseVerifier" />
    <CustomerLayout :user="user">
        <div class="mb-6">
            <v-btn variant="text" prepend-icon="mdi-arrow-left" href="/customer/api" class="mb-2">Back to API Keys</v-btn>
            <h1 class="text-h4 font-weight-bold mb-1">API Documentation</h1>
            <p class="text-body-2 text-grey">Learn how to integrate EaseVerifier API into your application</p>
        </div>

        <v-tabs v-model="activeTab" color="primary" class="mb-6">
            <v-tab value="overview">Overview</v-tab>
            <v-tab value="authentication">Authentication</v-tab>
            <v-tab value="endpoints">Endpoints</v-tab>
            <v-tab value="examples">Code Examples</v-tab>
        </v-tabs>

        <v-window v-model="activeTab">
            <!-- Overview -->
            <v-window-item value="overview">
                <v-card>
                    <v-card-text class="pa-6">
                        <h2 class="text-h5 font-weight-bold mb-4">Getting Started</h2>
                        <p class="text-body-1 mb-4">The EaseVerifier API allows you to programmatically verify Nigerian identities including NIN, BVN, and CAC records.</p>
                        <v-alert type="info" variant="tonal" class="mb-4">
                            <strong>Base URL:</strong> <code>https://api.easeverifier.com/v1</code>
                        </v-alert>
                        <h3 class="text-h6 font-weight-bold mb-3">Quick Start</h3>
                        <ol class="pl-4 mb-4">
                            <li class="mb-2">Generate your API credentials from the <a href="/customer/api">API Keys page</a></li>
                            <li class="mb-2">Include your credentials in the request headers</li>
                            <li class="mb-2">Make a POST request to the verification endpoint</li>
                            <li>Handle the response in your application</li>
                        </ol>
                    </v-card-text>
                </v-card>
            </v-window-item>

            <!-- Authentication -->
            <v-window-item value="authentication">
                <v-card>
                    <v-card-text class="pa-6">
                        <h2 class="text-h5 font-weight-bold mb-4">Authentication</h2>
                        <p class="text-body-1 mb-4">All API requests require authentication using your API Key and Secret.</p>
                        <v-table class="mb-4">
                            <thead><tr><th>Header</th><th>Value</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>Authorization</code></td><td><code>Bearer YOUR_API_KEY</code></td><td>Your API key</td></tr>
                                <tr><td><code>X-API-Secret</code></td><td><code>YOUR_API_SECRET</code></td><td>Your API secret</td></tr>
                                <tr><td><code>Content-Type</code></td><td><code>application/json</code></td><td>Request content type</td></tr>
                            </tbody>
                        </v-table>
                    </v-card-text>
                </v-card>
            </v-window-item>

            <!-- Endpoints -->
            <v-window-item value="endpoints">
                <v-card class="mb-4">
                    <v-card-title class="d-flex align-center"><v-chip color="success" size="small" class="mr-2">POST</v-chip>/verify/nin</v-card-title>
                    <v-card-text>
                        <p class="mb-3">Verify a National Identification Number (NIN)</p>
                        <h4 class="text-subtitle-2 font-weight-bold mb-2">Request Body</h4>
                        <pre class="bg-grey-lighten-4 pa-3 rounded mb-3">{ "nin": "12345678901" }</pre>
                        <h4 class="text-subtitle-2 font-weight-bold mb-2">Response</h4>
                        <pre class="bg-grey-lighten-4 pa-3 rounded">{ "success": true, "data": { "first_name": "John", "last_name": "Doe", ... } }</pre>
                    </v-card-text>
                </v-card>
                <v-card class="mb-4">
                    <v-card-title class="d-flex align-center"><v-chip color="success" size="small" class="mr-2">POST</v-chip>/verify/bvn</v-card-title>
                    <v-card-text>
                        <p class="mb-3">Verify a Bank Verification Number (BVN)</p>
                        <h4 class="text-subtitle-2 font-weight-bold mb-2">Request Body</h4>
                        <pre class="bg-grey-lighten-4 pa-3 rounded">{ "bvn": "12345678901" }</pre>
                    </v-card-text>
                </v-card>
            </v-window-item>

            <!-- Code Examples -->
            <v-window-item value="examples">
                <v-card>
                    <v-card-text class="pa-6">
                        <h2 class="text-h5 font-weight-bold mb-4">Code Examples</h2>
                        <v-tabs color="primary" class="mb-4">
                            <v-tab v-for="(code, lang) in codeExamples" :key="lang" :value="lang">{{ lang }}</v-tab>
                        </v-tabs>
                        <pre v-for="(code, lang) in codeExamples" :key="lang" class="bg-grey-darken-4 text-white pa-4 rounded overflow-x-auto">{{ code }}</pre>
                    </v-card-text>
                </v-card>
            </v-window-item>
        </v-window>
    </CustomerLayout>
</template>

