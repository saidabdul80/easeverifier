<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home, login, register, services, pricing, documentation } from '@/routes';
import { ref } from 'vue';

const activeSection = ref('overview');
const copied = ref(false);
const baseUrl = 'https://verify.ashlabtech.ng/api/v1';
const testNin = '11111111111';

const sections = [
    { id: 'overview', title: 'Overview', icon: 'mdi-rocket-launch' },
    { id: 'authentication', title: 'Authentication', icon: 'mdi-lock' },
    { id: 'identity', title: 'Identity Verify', icon: 'mdi-card-account-details' },
    { id: 'result-verification', title: 'Result Verify', icon: 'mdi-certificate' },
    { id: 'result-pins', title: 'Result PINs', icon: 'mdi-card-account-details-star-outline' },
    { id: 'wallet-history', title: 'Wallet & History', icon: 'mdi-wallet' },
    { id: 'errors', title: 'Errors', icon: 'mdi-alert-circle' },
];

const resultBoards = [
    {
        board: 'WAEC',
        form: 'GET /results/waec/form',
        fetch: 'POST /results/waec/fetch',
        fields: ['txtExamNumber', 'ExamYear', 'ExamType', 'txtPIN', 'txtCardSerialNo'],
        sample: `{
  "txtExamNumber": "1234567890",
  "ExamYear": "2024",
  "ExamType": "MAY/JUN",
  "txtPIN": "123456789012",
  "txtCardSerialNo": "WRN123456789"
}`,
    },
    {
        board: 'NECO',
        form: 'GET /results/neco/form',
        fetch: 'POST /results/neco/fetch',
        fields: ['exam_year', 'exam_type', 'reg_no', 'token'],
        sample: `{
  "exam_year": "2024",
  "exam_type": "ssce_int",
  "reg_no": "1234567890",
  "token": "123456789012"
}`,
    },
    {
        board: 'NBAIS',
        form: 'GET /results/nbais/form',
        fetch: 'POST /results/nbais/fetch',
        fields: ['parent_cat', 'sub_cat', 'year', 'month-select', 'exam_type', 'exam_no', 'pin', 'serial'],
        sample: `{
  "parent_cat": "8",
  "sub_cat": "1214",
  "year": "2022",
  "month-select": "Nov/Dec",
  "exam_type": "SAISSCE",
  "exam_no": "481634346OS",
  "pin": "123456789012",
  "serial": "NBAIS123456"
}`,
    },
    {
        board: 'NABTEB',
        form: 'GET /results/nabteb/form',
        fetch: 'POST /results/nabteb/fetch',
        fields: ['candid', 'examtype', 'examyear', 'serial', 'pin'],
        sample: `{
  "candid": "13123006",
  "examtype": "02",
  "examyear": "2021",
  "serial": "NER100000000",
  "pin": "123456789012"
}`,
    },
];

const copyCode = async (code: string) => {
    await navigator.clipboard.writeText(code);
    copied.value = true;
    setTimeout(() => copied.value = false, 2000);
};

const authHeader = `Authorization: Bearer YOUR_BEARER_TOKEN`;
const ninRequest = `curl -X POST ${baseUrl}/verify/nin \\
  -H "Authorization: Bearer YOUR_BEARER_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{"nin":"${testNin}","consent":true}'`;

const successResponse = `{
  "success": true,
  "status": 200,
  "data": {
    "first_name": "John",
    "last_name": "Doe",
    "_sandbox": false
  },
  "response_time": 1240,
  "message": "NIN Verified Successfully",
  "sandbox": false
}`;

const resultSuccessResponse = `{
  "success": true,
  "status": 200,
  "data": {
    "board": "NABTEB",
    "candidate": {
      "name": "TEST CANDIDATE",
      "exam_number": "13123006"
    },
    "subjects": [
      { "subject": "MATHEMATICS", "grade": "A1", "remark": null }
    ],
    "overall": null
  },
  "message": "NABTEB result fetched successfully",
  "sandbox": false
}`;

const errorResponse = `{
  "success": false,
  "error": "Insufficient wallet balance",
  "error_code": "INSUFFICIENT_FUNDS"
}`;
</script>

<template>
    <Head title="API Documentation - EaseVerifier">
        <meta name="description" content="EaseVerifier API documentation for identity verification, result verification, result PIN purchase, wallet balance, services, and verification history." />
        <meta name="keywords" content="EaseVerifier API, NIN verification API, result verification API, WAEC API, NECO API, NABTEB API, NBAIS API, result PIN API" />
        <meta property="og:title" content="API Documentation - EaseVerifier" />
        <meta property="og:description" content="Complete integration guide for EaseVerifier API services." />
        <meta property="og:type" content="article" />
        <link rel="canonical" href="https://verify.ashlabtech.ng/documentation" />
    </Head>

    <v-app>
        <v-app-bar flat color="white" elevation="1">
            <v-container class="d-flex align-center">
                <Link :href="home()" class="text-decoration-none d-flex align-center">
                    <v-avatar color="primary" size="36" class="mr-2">
                        <img src="/ashlabtech.png" alt="EaseVerifier" style="width: 100%; height: 100%; object-fit: contain;" />
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

                    <v-col cols="12" md="9" lg="10">
                        <div class="pa-6 pa-md-12" style="max-width: 980px;">
                            <div class="d-md-none mb-6">
                                <v-select v-model="activeSection" :items="sections" item-title="title" item-value="id" label="Documentation section" />
                            </div>

                            <section v-show="activeSection === 'overview'" class="mb-12">
                                <h1 class="text-h4 font-weight-bold mb-4">EaseVerifier API</h1>
                                <p class="text-body-1 text-grey-darken-1 mb-6">
                                    The EaseVerifier API is a wallet-funded REST API for identity verification, examination result verification, result PIN purchase, wallet balance checks, service discovery, and verification history.
                                </p>

                                <v-card class="mb-6" variant="outlined">
                                    <v-card-title class="text-subtitle-1 font-weight-bold">Base URL</v-card-title>
                                    <v-card-text><code class="bg-grey-lighten-4 pa-2 rounded">{{ baseUrl }}</code></v-card-text>
                                </v-card>

                                <v-alert type="info" variant="tonal" class="mb-6">
                                    API keys may be scoped to a branch. When a key is branch-scoped, charges, wallet balance, and history are applied to that branch automatically.
                                </v-alert>

                                <v-table>
                                    <thead><tr><th>Service</th><th>Endpoint</th><th>Billing</th></tr></thead>
                                    <tbody>
                                        <tr><td>Service list</td><td><code>GET /services</code></td><td>Free</td></tr>
                                        <tr><td>Wallet balance</td><td><code>GET /wallet/balance</code></td><td>Free</td></tr>
                                        <tr><td>Identity verification</td><td><code>POST /verify/nin</code>, <code>/verify/bvn</code>, <code>/verify/{service}</code></td><td>Wallet</td></tr>
                                        <tr><td>Result form metadata</td><td><code>GET /results/{board}/form</code></td><td>Wallet unless sandbox</td></tr>
                                        <tr><td>Result verification</td><td><code>POST /results/{board}/fetch</code></td><td>Wallet unless sandbox</td></tr>
                                        <tr><td>Result PIN products</td><td><code>GET /result-pins/products</code></td><td>Free</td></tr>
                                        <tr><td>Result PIN purchase</td><td><code>POST /result-pins/purchase</code></td><td>Wallet</td></tr>
                                        <tr><td>History</td><td><code>GET /verifications</code>, <code>GET /verifications/{reference}</code></td><td>Free</td></tr>
                                    </tbody>
                                </v-table>
                            </section>

                            <section v-show="activeSection === 'authentication'" class="mb-12">
                                <h1 class="text-h4 font-weight-bold mb-4">Authentication</h1>
                                <p class="text-body-1 text-grey-darken-1 mb-6">
                                    Create an API key from the customer dashboard. Copy the bearer token immediately; the secret is not shown again.
                                </p>
                                <v-alert type="warning" variant="tonal" class="mb-6">
                                    Never expose API keys in frontend code. Use them only from trusted backend services.
                                </v-alert>
                                <v-card variant="outlined" class="mb-4">
                                    <v-card-title class="d-flex align-center">
                                        <span>Supported Headers</span>
                                        <v-spacer />
                                        <v-btn size="small" variant="text" @click="copyCode(authHeader)">{{ copied ? 'Copied!' : 'Copy' }}</v-btn>
                                    </v-card-title>
                                    <v-card-text class="bg-grey-darken-4">
                                        <pre class="text-white text-body-2">Authorization: Bearer YOUR_BEARER_TOKEN</pre>
                                        <pre class="text-white text-body-2 mt-3">X-API-Key: YOUR_BEARER_TOKEN</pre>
                                    </v-card-text>
                                </v-card>
                                <v-list density="compact">
                                    <v-list-item title="Live keys" subtitle="Call real providers and charge the wallet." />
                                    <v-list-item title="Test keys" :subtitle="`Return sandbox data where supported. NIN test keys only accept ${testNin}.`" />
                                    <v-list-item title="Rate limit" subtitle="Default limit is enforced per minute from the API key configuration." />
                                    <v-list-item title="IP whitelist" subtitle="Requests from blocked IP addresses return UNAUTHORIZED." />
                                </v-list>
                            </section>

                            <section v-show="activeSection === 'identity'" class="mb-12">
                                <h1 class="text-h4 font-weight-bold mb-4">Identity Verification</h1>
                                <p class="text-body-1 text-grey-darken-1 mb-6">
                                    Identity endpoints verify a search parameter against the configured provider chain. The current request field is <code>nin</code> for <code>/verify/nin</code>, <code>/verify/bvn</code>, and <code>/verify/{service}</code>.
                                </p>
                                <v-alert type="info" variant="tonal" class="mb-4">
                                    For test NIN verification, send <strong>{{ testNin }}</strong>. Other NIN values are rejected for test keys.
                                </v-alert>
                                <v-table class="mb-6">
                                    <thead><tr><th>Endpoint</th><th>Body</th></tr></thead>
                                    <tbody>
                                        <tr><td><code>POST /verify/nin</code></td><td><code>{ "nin": "...", "consent": true }</code></td></tr>
                                        <tr><td><code>POST /verify/bvn</code></td><td><code>{ "nin": "BVN_OR_SEARCH_VALUE", "consent": true }</code></td></tr>
                                        <tr><td><code>POST /verify/{service}</code></td><td><code>{ "nin": "SEARCH_VALUE", "consent": true }</code></td></tr>
                                    </tbody>
                                </v-table>
                                <v-card variant="outlined" class="mb-4">
                                    <v-card-title>NIN Request</v-card-title>
                                    <v-card-text class="bg-grey-darken-4"><pre class="text-green-lighten-1 text-body-2" style="white-space: pre-wrap;">{{ ninRequest }}</pre></v-card-text>
                                </v-card>
                                <v-card variant="outlined">
                                    <v-card-title>Success Response</v-card-title>
                                    <v-card-text class="bg-grey-darken-4"><pre class="text-blue-lighten-1 text-body-2" style="white-space: pre-wrap;">{{ successResponse }}</pre></v-card-text>
                                </v-card>
                            </section>

                            <section v-show="activeSection === 'result-verification'" class="mb-12">
                                <h1 class="text-h4 font-weight-bold mb-4">Result Verification</h1>
                                <p class="text-body-1 text-grey-darken-1 mb-6">
                                    First call the board form endpoint to get field definitions and option values. Then send the exact field names to the board fetch endpoint.
                                </p>
                                <v-alert type="warning" variant="tonal" class="mb-6">
                                    Result checker PINs, serials, and tokens can be consumed by the board provider. Submit only when the customer has authorized the lookup.
                                </v-alert>

                                <v-card v-for="board in resultBoards" :key="board.board" variant="outlined" class="mb-5">
                                    <v-card-title>{{ board.board }}</v-card-title>
                                    <v-card-text>
                                        <div class="mb-2"><v-chip size="small" color="info">FORM</v-chip><code class="ml-2">{{ board.form }}</code></div>
                                        <div class="mb-3"><v-chip size="small" color="success">FETCH</v-chip><code class="ml-2">{{ board.fetch }}</code></div>
                                        <p class="text-body-2 mb-2">Required fields: <code>{{ board.fields.join(', ') }}</code></p>
                                        <pre class="bg-grey-darken-4 text-green-lighten-1 pa-4 rounded text-body-2" style="white-space: pre-wrap;">{{ board.sample }}</pre>
                                    </v-card-text>
                                </v-card>

                                <v-card variant="outlined" class="mb-4">
                                    <v-card-title>NBAIS School Lookup</v-card-title>
                                    <v-card-text>
                                        <p class="text-body-2">When <code>parent_cat</code> is selected, fetch schools with:</p>
                                        <code>GET /results/nbais/schools?parent_cat=8</code>
                                    </v-card-text>
                                </v-card>
                                <v-card variant="outlined">
                                    <v-card-title>Result Response</v-card-title>
                                    <v-card-text class="bg-grey-darken-4"><pre class="text-blue-lighten-1 text-body-2" style="white-space: pre-wrap;">{{ resultSuccessResponse }}</pre></v-card-text>
                                </v-card>
                            </section>

                            <section v-show="activeSection === 'result-pins'" class="mb-12">
                                <h1 class="text-h4 font-weight-bold mb-4">Result PINs</h1>
                                <p class="text-body-1 text-grey-darken-1 mb-6">
                                    Use these endpoints to list available result checker PIN products and purchase PINs from wallet balance.
                                </p>
                                <v-card variant="outlined" class="mb-4">
                                    <v-card-title><v-chip size="small" color="info" class="mr-2">GET</v-chip>/result-pins/products</v-card-title>
                                    <v-card-text>
                                        <p class="text-body-2">Returns active products with <code>id</code>, <code>card_type_id</code>, <code>price</code>, <code>min_quantity</code>, and <code>max_quantity</code>.</p>
                                    </v-card-text>
                                </v-card>
                                <v-card variant="outlined" class="mb-4">
                                    <v-card-title><v-chip size="small" color="success" class="mr-2">POST</v-chip>/result-pins/purchase</v-card-title>
                                    <v-card-text>
                                        <p class="text-body-2">Send either <code>product_id</code> or <code>card_type_id</code>, plus <code>quantity</code>.</p>
                                        <pre class="bg-grey-darken-4 text-green-lighten-1 pa-4 rounded text-body-2" style="white-space: pre-wrap;">{
  "product_id": 3,
  "quantity": 1
}</pre>
                                    </v-card-text>
                                </v-card>
                                <v-card variant="outlined">
                                    <v-card-title>Purchase Response</v-card-title>
                                    <v-card-text class="bg-grey-darken-4">
                                        <pre class="text-blue-lighten-1 text-body-2" style="white-space: pre-wrap;">{
  "success": true,
  "data": {
    "reference": "PIN-XXXXXXXXXX-1782400000",
    "quantity": 1,
    "status": "completed",
    "pins": [
      { "pin": "123456789012", "serial_no": "NER100000000" }
    ]
  }
}</pre>
                                    </v-card-text>
                                </v-card>
                            </section>

                            <section v-show="activeSection === 'wallet-history'" class="mb-12">
                                <h1 class="text-h4 font-weight-bold mb-4">Wallet, Services, and History</h1>
                                <v-table class="mb-6">
                                    <thead><tr><th>Endpoint</th><th>Description</th><th>Query</th></tr></thead>
                                    <tbody>
                                        <tr><td><code>GET /wallet/balance</code></td><td>Current wallet or branch wallet balance.</td><td>None</td></tr>
                                        <tr><td><code>GET /services</code></td><td>Active verification services.</td><td>None</td></tr>
                                        <tr><td><code>GET /verifications</code></td><td>Paginated verification history.</td><td><code>service</code>, <code>status</code>, <code>per_page</code></td></tr>
                                        <tr><td><code>GET /verifications/{reference}</code></td><td>Single verification request by reference.</td><td>None</td></tr>
                                    </tbody>
                                </v-table>
                                <v-card variant="outlined">
                                    <v-card-title>Wallet Response</v-card-title>
                                    <v-card-text class="bg-grey-darken-4">
                                        <pre class="text-blue-lighten-1 text-body-2" style="white-space: pre-wrap;">{
  "success": true,
  "data": {
    "balance": "10000.00",
    "bonus_balance": "500.00",
    "total_balance": "10500.00",
    "currency": "NGN"
  }
}</pre>
                                    </v-card-text>
                                </v-card>
                            </section>

                            <section v-show="activeSection === 'errors'" class="mb-12">
                                <h1 class="text-h4 font-weight-bold mb-4">Errors</h1>
                                <p class="text-body-1 text-grey-darken-1 mb-6">Failed requests return JSON with <code>success: false</code>, a human-readable <code>error</code>, and machine-readable <code>error_code</code>.</p>
                                <v-card variant="outlined" class="mb-6">
                                    <v-card-title>Error Response</v-card-title>
                                    <v-card-text class="bg-grey-darken-4"><pre class="text-red-lighten-2 text-body-2" style="white-space: pre-wrap;">{{ errorResponse }}</pre></v-card-text>
                                </v-card>
                                <v-table>
                                    <thead><tr><th>HTTP</th><th>Error Code</th><th>Meaning</th></tr></thead>
                                    <tbody>
                                        <tr><td><code>400</code></td><td><code>SERVICE_UNAVAILABLE</code>, <code>PIN_PURCHASE_FAILED</code>, <code>UNKNOWN_ERROR</code></td><td>Request was understood but could not be completed.</td></tr>
                                        <tr><td><code>401</code></td><td><code>UNAUTHORIZED</code></td><td>Missing, invalid, inactive, or IP-blocked API key.</td></tr>
                                        <tr><td><code>402</code></td><td><code>INSUFFICIENT_FUNDS</code></td><td>Wallet balance is too low.</td></tr>
                                        <tr><td><code>404</code></td><td><code>NOT_FOUND</code>, <code>PRODUCT_UNAVAILABLE</code>, <code>UNSUPPORTED_RESULT_BOARD</code></td><td>Requested record, product, or board was not found.</td></tr>
                                        <tr><td><code>422</code></td><td><code>VALIDATION_ERROR</code>, <code>TEST_NIN_REQUIRED</code></td><td>Required fields are missing or invalid.</td></tr>
                                        <tr><td><code>429</code></td><td><code>RATE_LIMIT_EXCEEDED</code></td><td>API key exceeded its per-minute limit.</td></tr>
                                    </tbody>
                                </v-table>
                            </section>
                        </div>
                    </v-col>
                </v-row>
            </v-container>
        </v-main>
    </v-app>
</template>
