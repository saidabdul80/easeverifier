<script setup lang="ts">
import PaygoResultSimulation from '@/components/PaygoResultSimulation.vue';
import { Head, Link } from '@inertiajs/vue3';
import { home, login, register, services, pricing, documentation } from '@/routes';
import { ref } from 'vue';

const activeSection = ref('overview');
const copied = ref(false);
const appBaseUrl = 'https://verify.ashlabtech.ng';
const baseUrl = 'https://verify.ashlabtech.ng/api/v1';
const mcpUrl = 'https://verify.ashlabtech.ng/api/mcp';
const testNin = '11111111111';

const identityServices = [
    {
        name: 'NIN Verification',
        endpoint: 'POST /verify/nin',
        field: 'nin',
        searchValue: 'NIN',
        request: `curl -X POST ${baseUrl}/verify/nin \\
  -H "Authorization: Bearer YOUR_BEARER_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{"nin":"${testNin}","consent":true}'`,
    },
    {
        name: 'BVN Verification',
        endpoint: 'POST /verify/bvn',
        field: 'bvn',
        searchValue: 'BVN',
        request: `curl -X POST ${baseUrl}/verify/bvn \\
  -H "Authorization: Bearer YOUR_BEARER_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{"bvn":"22123456789","consent":true}'`,
    },
    {
        name: 'CAC Verification',
        endpoint: 'POST /verify/cac',
        field: 'rc_number',
        searchValue: 'RC Number',
        request: `curl -X POST ${baseUrl}/verify/cac \\
  -H "Authorization: Bearer YOUR_BEARER_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{"rc_number":"RC1234567","consent":true}'`,
    },
    {
        name: "Driver's License Verification",
        endpoint: 'POST /verify/drivers-license',
        field: 'license_number',
        searchValue: "Driver's License Number",
        request: `curl -X POST ${baseUrl}/verify/drivers-license \\
  -H "Authorization: Bearer YOUR_BEARER_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{"license_number":"ABC123456789","consent":true}'`,
    },
];

const sections = [
    { id: 'overview', title: 'Overview', icon: 'mdi-rocket-launch' },
    { id: 'authentication', title: 'Authentication', icon: 'mdi-lock' },
    { id: 'identity', title: 'Identity Verify', icon: 'mdi-card-account-details' },
    { id: 'result-verification', title: 'Result Verify', icon: 'mdi-certificate' },
    { id: 'paygo', title: 'PayGo', icon: 'mdi-credit-card-outline' },
    { id: 'implementation', title: 'Result Verify Demo (Paygo)', icon: 'mdi-code-braces' },
    { id: 'result-pins', title: 'Result PINs', icon: 'mdi-card-account-details-star-outline' },
    { id: 'wallet-history', title: 'Wallet & History', icon: 'mdi-wallet' },
    { id: 'mcp', title: 'MCP Server', icon: 'mdi-robot-outline' },
    { id: 'errors', title: 'Errors', icon: 'mdi-alert-circle' },
];

const mcpConfig = `{
  "$schema": "https://agent-plugins.org/schemas/1.0.0/mcp.schema.json",
  "mcpServers": {
    "easeverifier": {
      "type": "streamable-http",
      "url": "${mcpUrl}"
    }
  }
}`;

const resultBoards = [
    {
        board: 'WAEC',
        form: 'GET /results/waec/form',
        fetch: 'POST /results/waec/fetch',
        fields: ['txtExamNumber', 'ExamYear', 'ExamType', 'txtPIN'],
        sample: `{
  "txtExamNumber": "1234567890",
  "ExamYear": "2024",
  "ExamType": "MAY/JUN",
  "txtPIN": "1111222233334444555"
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
        fields: ['year', 'month', 'exam_no', 'pin'],
        sample: `{
  "year": "2022",
  "month": "Nov/Dec",
  "exam_no": "481634346OS",
  "pin": "123456789012"
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

const successResponse = `{
  "success": true,
  "status": 200,
  "data": {
    "first_name": "John",
    "last_name": "Doe"
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

const resultImplementation = `const API_URL = '${baseUrl}';
const API_KEY = process.env.EASEVERIFIER_API_KEY;

async function verifyWaecResult(input) {
  const response = await fetch(API_URL + '/results/waec/fetch', {
    method: 'POST',
    headers: {
      Authorization: 'Bearer ' + API_KEY,
      'Content-Type': 'application/json',
      Accept: 'application/json'
    },
    body: JSON.stringify(input)
  });

  const payload = await response.json();
  if (!response.ok || !payload.success) {
    throw new Error(payload.error || 'Result verification failed');
  }

  return payload.data;
}

const result = await verifyWaecResult({
  txtExamNumber: '1234567890',
  ExamYear: '2024',
  ExamType: 'MAY/JUN',
  txtPIN: '1111222233334444555'
});`;

const paygoPortalImplementation = `const portalRef = 'APPLICATION-90210';
const checkout = new URL(
  '${appBaseUrl}/paygo/results/customer/YOUR_REFERRAL_CODE'
);

checkout.search = new URLSearchParams({
  candidate_id: 'STU-12345',
  portal_ref: portalRef,
  reference: portalRef,
  sitting: '1',
  state: 'YOUR_SIGNED_STATE'
}).toString();

// Send the browser to EaseVerifier to choose a board, enter result details,
// and pay. A ready result returns to your configured success URL.
window.location.assign(checkout.toString());

// From your backend after the success redirect or webhook:
const response = await fetch(
  '${appBaseUrl}/api/paygo/results/' + encodeURIComponent(portalRef) +
  '?portal_ref=' + encodeURIComponent(portalRef) + '&sitting=1',
  { headers: { Accept: 'application/json' } }
);
const verifiedResult = await response.json();`;

const webhookVerification = `import crypto from 'node:crypto';

const expected = crypto
  .createHmac('sha256', process.env.EASEVERIFIER_WEBHOOK_SECRET)
  .update(JSON.stringify(req.body))
  .digest('hex');

const supplied = req.get('X-EaseVerifier-Signature') || '';
const valid = supplied.length === expected.length &&
  crypto.timingSafeEqual(Buffer.from(supplied), Buffer.from(expected));

if (!valid) return res.status(401).json({ received: false });

// Store by payload.reference so webhook retries remain idempotent.
const payload = req.body;
return res.json({ received: true });`;

const paygoWebhookPayload = `{
  "event": "paygo.result.ready",
  "reference": "APPLICATION-90210",
  "candidate_id": "STU-12345",
  "portal_ref": "APPLICATION-90210",
  "sitting": 1,
  "state": "YOUR_SIGNED_STATE",
  "school_referral_code": "YOUR_REFERRAL_CODE",
  "board": "WAEC",
  "payment_status": "paid",
  "result_status": "ready",
  "lookup_label": "1234567890",
  "result": {
    "board": "WAEC",
    "candidate": { "name": "TEST CANDIDATE" },
    "subjects": [{ "subject": "MATHEMATICS", "grade": "A1" }]
  },
  "error": null,
  "error_code": null,
  "timestamp": "2026-09-29T10:30:00.000000Z"
}`;

const paygoPullResponse = `{
  "success": true,
  "status": 200,
  "reference": "APPLICATION-90210",
  "lookup_label": "WAEC 1234567890",
  "candidate_id": "STU-12345",
  "portal_ref": "APPLICATION-90210",
  "sitting": 1,
  "data": {
    "board": "WAEC",
    "candidate": { "name": "TEST CANDIDATE" },
    "subjects": [{ "subject": "MATHEMATICS", "grade": "A1" }]
  },
  "fetches_remaining": 1,
  "served_from": "reference_attempt_cache"
}`;
</script>

<template>
    <Head title="API Documentation - EaseVerifier">
        <meta name="description" content="EaseVerifier API and MCP documentation for identity verification, result verification, PayGo integration, result PIN purchase, wallet balance, services, and verification history." />
        <meta name="keywords" content="EaseVerifier API, EaseVerifier MCP, NIN verification API, result verification API, PayGo verification, WAEC API, NECO API, NABTEB API, NBAIS API, result PIN API" />
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
                                        <tr><td>PayGo verification</td><td><code>/paygo/...</code>, <code>/api/paygo/...</code></td><td>End-user Paystack payment</td></tr>
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
                                    Identity endpoints verify a search value against the configured provider chain. Use the field name that matches the service you are calling.
                                </p>
                                <v-alert type="info" variant="tonal" class="mb-4">
                                    For test NIN verification, send <strong>{{ testNin }}</strong>. Other NIN values are rejected for test keys. Services disabled by admin settings return <code>SERVICE_UNAVAILABLE</code>.
                                </v-alert>
                                <v-table class="mb-6">
                                    <thead><tr><th>Service</th><th>Endpoint</th><th>Body</th></tr></thead>
                                    <tbody>
                                        <tr v-for="service in identityServices" :key="service.endpoint">
                                            <td>{{ service.name }}</td>
                                            <td><code>{{ service.endpoint }}</code></td>
                                            <td><code>{ "{{ service.field }}": "{{ service.searchValue }}", "consent": true }</code></td>
                                        </tr>
                                    </tbody>
                                </v-table>
                                <v-card v-for="service in identityServices" :key="service.name" variant="outlined" class="mb-4">
                                    <v-card-title>{{ service.name }} Request</v-card-title>
                                    <v-card-text class="bg-grey-darken-4"><pre class="text-green-lighten-1 text-body-2" style="white-space: pre-wrap;">{{ service.request }}</pre></v-card-text>
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
                                <v-alert type="info" variant="tonal" class="mb-6">
                                    WAEC verification now requires only the examination number, year, examination type, and purchased PIN. A card serial number is not required. Use <code>MAY/JUN</code> for school candidates and <code>NOV/DEC</code> for private candidates.
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

                                <v-card variant="outlined">
                                    <v-card-title>Result Response</v-card-title>
                                    <v-card-text class="bg-grey-darken-4"><pre class="text-blue-lighten-1 text-body-2" style="white-space: pre-wrap;">{{ resultSuccessResponse }}</pre></v-card-text>
                                </v-card>
                            </section>

                            <section v-show="activeSection === 'paygo'" class="mb-12">
                                <h1 class="text-h4 font-weight-bold mb-4">PayGo Verification</h1>
                                <p class="text-body-1 text-grey-darken-1 mb-6">
                                    PayGo lets an end user pay for a verification through a public Paystack checkout. It does not use an API bearer token and does not debit your API wallet. Create and price the PayGo service in the customer dashboard, then use the generated public URLs.
                                </p>

                                <v-alert type="info" variant="tonal" class="mb-6">
                                    PayGo payment references are valid for seven days from confirmed payment. Keep result references private because the public pull URL uses the reference as its access credential.
                                </v-alert>

                                <v-table class="mb-6">
                                    <thead><tr><th>Flow</th><th>Endpoint</th><th>Purpose</th></tr></thead>
                                    <tbody>
                                        <tr><td>NIN checkout</td><td><code>GET|POST /paygo/{publicSlug}/initiate/{nin?}</code></td><td>Collect NIN details and initialize Paystack.</td></tr>
                                        <tr><td>NIN result</td><td><code>GET|POST /api/paygo/{publicSlug}/verify/{nin?}</code></td><td>Use the paid NIN reference with its three-attempt allowance.</td></tr>
                                        <tr><td>Result selector</td><td><code>GET /paygo/results/customer/{referralCode}</code></td><td>Let the payer choose an enabled examination board.</td></tr>
                                        <tr><td>Board checkout</td><td><code>GET|POST /paygo/results/{publicSlug}</code></td><td>Collect board fields, initialize payment, and fetch the result.</td></tr>
                                        <tr><td>Result pull</td><td><code>GET /api/paygo/results/{reference}</code></td><td>Return the stored verified result; limited to 30 requests per minute.</td></tr>
                                    </tbody>
                                </v-table>

                                <v-card variant="outlined" class="mb-5">
                                    <v-card-title>NIN PayGo Request</v-card-title>
                                    <v-card-text>
                                        <p class="text-body-2 mb-3">Request JSON explicitly to receive the Paystack checkout URL, then call the verify endpoint after payment. No bearer token is used.</p>
                                        <pre class="bg-grey-darken-4 text-green-lighten-1 pa-4 rounded text-body-2" style="white-space: pre-wrap;">curl -X POST "{{ appBaseUrl }}/paygo/YOUR_PUBLIC_SLUG/initiate?response=json" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"nin":"12345678901","email":"payer@example.com"}'

curl -X POST "{{ appBaseUrl }}/api/paygo/YOUR_PUBLIC_SLUG/verify" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"reference":"PGO-REFERENCE","nin":"12345678901","consent":true}'</pre>
                                    </v-card-text>
                                </v-card>

                                <h2 class="text-h6 font-weight-bold mb-3">Portal context</h2>
                                <p class="text-body-2 text-grey-darken-1 mb-4">
                                    Append <code>candidate_id</code>, <code>portal_ref</code>, <code>state</code>, and <code>sitting</code> to the selector or board URL. EaseVerifier returns this context on redirect and webhook callbacks. Supplying your own unique <code>reference</code> creates a reference package that can complete two successful result attempts during its seven-day validity window.
                                </p>
                                <p class="text-body-2 text-grey-darken-1 mb-4">
                                    Standard result payments use the pull limit captured when payment starts, and each successful API pull consumes one. Reference packages count successful result attempts instead, so polling an already completed attempt does not consume another attempt.
                                </p>

                                <v-table class="mb-6">
                                    <thead><tr><th>Callback mode</th><th>Behavior</th><th>Required setting</th></tr></thead>
                                    <tbody>
                                        <tr><td><code>redirect</code></td><td>A successful result returns to the success URL with status and portal context. Result errors remain on the EaseVerifier form so the payer can correct them.</td><td>Success and failure URLs</td></tr>
                                        <tr><td><code>webhook</code></td><td>EaseVerifier posts the completed or failed result to your backend.</td><td>Customer webhook URL</td></tr>
                                        <tr><td><code>hybrid</code></td><td>Sends the webhook and redirects the browser.</td><td>All callback URLs</td></tr>
                                    </tbody>
                                </v-table>

                                <v-card variant="outlined" class="mb-5">
                                    <v-card-title>Result Pull</v-card-title>
                                    <v-card-text>
                                        <pre class="bg-grey-darken-4 text-green-lighten-1 pa-4 rounded text-body-2" style="white-space: pre-wrap;">curl -H "Accept: application/json" \
  "{{ appBaseUrl }}/api/paygo/results/APPLICATION-90210?portal_ref=APPLICATION-90210&amp;sitting=1"</pre>
                                        <pre class="bg-grey-darken-4 text-blue-lighten-1 pa-4 rounded text-body-2 mt-4" style="white-space: pre-wrap;">{{ paygoPullResponse }}</pre>
                                    </v-card-text>
                                </v-card>

                                <v-card variant="outlined">
                                    <v-card-title>Webhook Payload</v-card-title>
                                    <v-card-text>
                                        <p class="text-body-2 mb-3">Webhook requests include <code>X-EaseVerifier-Event</code>, <code>X-EaseVerifier-Reference</code>, and <code>X-EaseVerifier-Signature</code>. A failed result uses the <code>paygo.result.failed</code> event with <code>result_status: failed</code>.</p>
                                        <pre class="bg-grey-darken-4 text-blue-lighten-1 pa-4 rounded text-body-2" style="white-space: pre-wrap;">{{ paygoWebhookPayload }}</pre>
                                    </v-card-text>
                                </v-card>
                            </section>

                            <section v-show="activeSection === 'implementation'" class="mb-12">
                                <h1 class="text-h4 font-weight-bold mb-4">Practical Implementation</h1>
                                <p class="text-body-1 text-grey-darken-1 mb-6">
                                    Keep bearer tokens and webhook secrets on your server. The direct API example below charges your wallet; the PayGo example sends the payer through checkout and retrieves the stored result afterward.
                                </p>

                                <PaygoResultSimulation class="mb-6" />

                                <v-alert type="warning" variant="tonal" class="mb-6">
                                    The result form endpoint and fetch endpoint are billed independently in live mode. Cache form field metadata on your server and refresh it only when needed.
                                </v-alert>

                                <v-card variant="outlined" class="mb-5">
                                    <v-card-title>Direct WAEC API - Server-side JavaScript</v-card-title>
                                    <v-card-text class="bg-grey-darken-4"><pre class="text-green-lighten-1 text-body-2" style="white-space: pre-wrap;">{{ resultImplementation }}</pre></v-card-text>
                                </v-card>

                                <v-card variant="outlined" class="mb-5">
                                    <v-card-title>School Portal PayGo Flow</v-card-title>
                                    <v-card-text class="bg-grey-darken-4"><pre class="text-green-lighten-1 text-body-2" style="white-space: pre-wrap;">{{ paygoPortalImplementation }}</pre></v-card-text>
                                </v-card>

                                <v-card variant="outlined">
                                    <v-card-title>Verify the PayGo Webhook</v-card-title>
                                    <v-card-text>
                                        <p class="text-body-2 mb-3">Compute HMAC-SHA256 with the PayGo webhook secret and compare it with <code>X-EaseVerifier-Signature</code> using a timing-safe comparison. Also verify your signed <code>state</code> before updating a candidate record.</p>
                                        <pre class="bg-grey-darken-4 text-green-lighten-1 pa-4 rounded text-body-2" style="white-space: pre-wrap;">{{ webhookVerification }}</pre>
                                    </v-card-text>
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
                                        <tr><td><code>GET /services</code></td><td>Active services with customer pricing and currency.</td><td>None</td></tr>
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

                            <section v-show="activeSection === 'mcp'" class="mb-12">
                                <h1 class="text-h4 font-weight-bold mb-4">MCP Server</h1>
                                <p class="text-body-1 text-grey-darken-1 mb-6">
                                    Connect an MCP client to the Streamable HTTP endpoint to use EaseVerifier services as focused AI tools. MCP access uses OAuth 2.1 account linking; customer API keys remain dedicated to the REST API.
                                </p>

                                <v-table class="mb-6">
                                    <thead><tr><th>Setting</th><th>Value</th></tr></thead>
                                    <tbody>
                                        <tr><td>Endpoint</td><td><code>{{ mcpUrl }}</code></td></tr>
                                        <tr><td>Transport</td><td><code>streamable-http</code></td></tr>
                                        <tr><td>Authentication</td><td>OAuth 2.1 authorization code flow with PKCE (<code>S256</code>)</td></tr>
                                        <tr><td>Protected resource metadata</td><td><code>{{ appBaseUrl }}/.well-known/oauth-protected-resource/api/mcp</code></td></tr>
                                        <tr><td>Protocol</td><td>MCP 2025-06-18 and compatible earlier versions</td></tr>
                                    </tbody>
                                </v-table>

                                <v-alert type="info" variant="tonal" class="mb-6">
                                    ChatGPT and compatible MCP clients discover OAuth automatically from the endpoint. Sign in to EaseVerifier, review the requested access, and select <strong>Connect account</strong>. Do not place an API key in the plugin configuration.
                                </v-alert>

                                <v-card variant="outlined" class="mb-6">
                                    <v-card-title>Agent Plugins configuration</v-card-title>
                                    <v-card-text class="bg-grey-darken-4">
                                        <pre class="text-green-lighten-1 text-body-2" style="white-space: pre-wrap;">{{ mcpConfig }}</pre>
                                    </v-card-text>
                                </v-card>

                                <v-table class="mb-6">
                                    <thead><tr><th>Tools</th><th>Behavior</th></tr></thead>
                                    <tbody>
                                        <tr><td><code>list_services</code>, <code>get_wallet_balance</code></td><td>Discover services, customer prices, currency, and wallet balance.</td></tr>
                                        <tr><td><code>list_verifications</code>, <code>get_verification</code></td><td>Read verification history scoped to the connected customer or branch.</td></tr>
                                        <tr><td><code>verify_identity</code></td><td>Run any active identity or business verification service.</td></tr>
                                        <tr><td><code>get_result_requirements</code>, <code>verify_result</code>, <code>list_nbais_schools</code></td><td>Verify WAEC, NECO, NBAIS, and NABTEB results.</td></tr>
                                        <tr><td><code>list_result_pin_products</code>, <code>purchase_result_pins</code></td><td>List current prices and purchase result checker PINs.</td></tr>
                                    </tbody>
                                </v-table>

                                <v-alert type="warning" variant="tonal">
                                    Verification, result form, result fetch, and PIN purchase tools may charge the wallet. MCP clients should request explicit user confirmation immediately before invoking them and must not retry an ambiguous chargeable call.
                                </v-alert>
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
                                        <tr><td><code>400</code></td><td><code>SERVICE_UNAVAILABLE</code>, <code>PIN_PURCHASE_FAILED</code>, <code>RESULT_REFERENCE_INVALID</code>, <code>UNKNOWN_ERROR</code></td><td>Request was understood but could not be completed.</td></tr>
                                        <tr><td><code>401</code></td><td><code>UNAUTHORIZED</code></td><td>Missing, invalid, inactive, or IP-blocked API key.</td></tr>
                                        <tr><td><code>402</code></td><td><code>INSUFFICIENT_FUNDS</code></td><td>Wallet balance is too low.</td></tr>
                                        <tr><td><code>404</code></td><td><code>NOT_FOUND</code>, <code>PRODUCT_UNAVAILABLE</code>, <code>UNSUPPORTED_RESULT_BOARD</code></td><td>Requested record, product, or board was not found.</td></tr>
                                        <tr><td><code>422</code></td><td><code>VALIDATION_ERROR</code>, <code>TEST_NIN_REQUIRED</code></td><td>Required fields are missing or invalid.</td></tr>
                                        <tr><td><code>429</code></td><td><code>RATE_LIMIT_EXCEEDED</code>, <code>PULL_LIMIT_EXCEEDED</code></td><td>The API key or PayGo reference exceeded its configured limit.</td></tr>
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
