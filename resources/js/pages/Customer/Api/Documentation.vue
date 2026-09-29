<script setup lang="ts">
import PaygoResultSimulation from '@/components/PaygoResultSimulation.vue';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{ user: { name: string; email: string } }>();

const activeTab = ref('overview');
const appBaseUrl = 'https://verify.ashlabtech.ng';
const baseUrl = 'https://verify.ashlabtech.ng/api/v1';
const testNin = '11111111111';

const identityServices = [
    { name: 'NIN Verification', endpoint: 'POST /verify/nin', field: 'nin', searchValue: 'NIN' },
    { name: 'BVN Verification', endpoint: 'POST /verify/bvn', field: 'bvn', searchValue: 'BVN' },
    { name: 'CAC Verification', endpoint: 'POST /verify/cac', field: 'rc_number', searchValue: 'RC Number' },
    { name: "Driver's License Verification", endpoint: 'POST /verify/drivers-license', field: 'license_number', searchValue: "Driver's License Number" },
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

const codeExamples = {
    curl: `curl -X POST ${baseUrl}/verify/nin \\
  -H "Authorization: Bearer YOUR_BEARER_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{"nin":"${testNin}","consent":true}'`,
    javascript: `const response = await fetch('${baseUrl}/results/nabteb/fetch', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_BEARER_TOKEN',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    candid: '13123006',
    examtype: '02',
    examyear: '2021',
    serial: 'NER100000000',
    pin: '123456789012'
  })
});

const data = await response.json();`,
    php: `<?php

$client = new GuzzleHttp\\Client();

$response = $client->post('${baseUrl}/result-pins/purchase', [
    'headers' => [
        'Authorization' => 'Bearer YOUR_BEARER_TOKEN',
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'product_id' => 3,
        'quantity' => 1,
    ],
]);

$data = json_decode($response->getBody(), true);`,
};

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

const resultResponse = `{
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
    ]
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
  txtPIN: '123456789012',
  txtCardSerialNo: 'WRN123456789'
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

window.location.assign(checkout.toString());

// Run this from your backend after the success redirect or webhook.
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
    <Head title="API Documentation - EaseVerifier" />

    <CustomerLayout :user="user">
        <div class="mb-6">
            <v-btn variant="text" prepend-icon="mdi-arrow-left" href="/customer/api" class="mb-2">Back to API Keys</v-btn>
            <h1 class="text-h4 font-weight-bold mb-1">API Documentation</h1>
            <p class="text-body-2 text-grey">Integrate identity verification, result checks, payer-funded PayGo, result PIN purchases, wallet balance, and history.</p>
        </div>

        <v-tabs v-model="activeTab" color="primary" show-arrows class="mb-6">
            <v-tab value="overview">Overview</v-tab>
            <v-tab value="authentication">Authentication</v-tab>
            <v-tab value="identity">Identity</v-tab>
            <v-tab value="results">Results</v-tab>
            <v-tab value="paygo">PayGo</v-tab>
            <v-tab value="implementation">Implementation</v-tab>
            <v-tab value="pins">Result PINs</v-tab>
            <v-tab value="wallet">Wallet</v-tab>
            <v-tab value="examples">Examples</v-tab>
            <v-tab value="errors">Errors</v-tab>
        </v-tabs>

        <v-window v-model="activeTab">
            <v-window-item value="overview">
                <v-card>
                    <v-card-text class="pa-6">
                        <h2 class="text-h5 font-weight-bold mb-4">Getting Started</h2>
                        <p class="text-body-1 mb-4">
                            The EaseVerifier API is wallet-funded. API keys can be scoped to your main account or a branch, and branch-scoped keys automatically charge that branch wallet and return branch-specific history.
                        </p>

                        <v-alert type="info" variant="tonal" class="mb-4">
                            <strong>Base URL:</strong> <code>{{ baseUrl }}</code>
                        </v-alert>

                        <v-table>
                            <thead>
                                <tr><th>Service</th><th>Endpoint</th><th>Billing</th></tr>
                            </thead>
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
                    </v-card-text>
                </v-card>
            </v-window-item>

            <v-window-item value="authentication">
                <v-card>
                    <v-card-text class="pa-6">
                        <h2 class="text-h5 font-weight-bold mb-4">Authentication</h2>
                        <p class="text-body-1 mb-4">Send your generated token on every request. The bearer token and <code>X-API-Key</code> header are both supported.</p>
                        <v-alert type="warning" variant="tonal" class="mb-4">
                            Copy the token when the key is created. The full secret is not shown again.
                        </v-alert>
                        <v-table class="mb-4">
                            <thead>
                                <tr><th>Header</th><th>Value</th><th>Description</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>Authorization</code></td><td><code>Bearer YOUR_BEARER_TOKEN</code></td><td>Preferred authentication header</td></tr>
                                <tr><td><code>X-API-Key</code></td><td><code>YOUR_BEARER_TOKEN</code></td><td>Alternative key header</td></tr>
                                <tr><td><code>Content-Type</code></td><td><code>application/json</code></td><td>Required for POST requests</td></tr>
                            </tbody>
                        </v-table>
                        <v-alert type="info" variant="tonal">
                            Test NIN calls only accept <strong>{{ testNin }}</strong>. Live keys call real providers and deduct wallet balance.
                        </v-alert>
                    </v-card-text>
                </v-card>
            </v-window-item>

            <v-window-item value="identity">
                <v-card>
                    <v-card-text class="pa-6">
                        <h2 class="text-h5 font-weight-bold mb-4">Identity Verification</h2>
                        <p class="text-body-1 mb-4">
                            Identity endpoints expect the search value in the field that matches the service you are calling.
                        </p>
                        <v-alert type="info" variant="tonal" class="mb-4">
                            For test NIN verification, send <strong>{{ testNin }}</strong>. Services disabled by admin settings return <code>SERVICE_UNAVAILABLE</code>.
                        </v-alert>
                        <v-table class="mb-6">
                            <thead>
                                <tr><th>Service</th><th>Endpoint</th><th>Body</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="service in identityServices" :key="service.endpoint">
                                    <td>{{ service.name }}</td>
                                    <td><code>{{ service.endpoint }}</code></td>
                                    <td><code>{ "{{ service.field }}": "{{ service.searchValue }}", "consent": true }</code></td>
                                </tr>
                            </tbody>
                        </v-table>

                        <h3 class="text-subtitle-1 font-weight-bold mb-2">Success Response</h3>
                        <pre class="bg-grey-darken-4 text-blue-lighten-1 pa-4 rounded overflow-x-auto">{{ successResponse }}</pre>
                    </v-card-text>
                </v-card>
            </v-window-item>

            <v-window-item value="results">
                <v-alert type="warning" variant="tonal" class="mb-4">
                    Result checker PINs, serials, and tokens may be consumed by the board provider. Submit only when the customer has authorized the lookup.
                </v-alert>

                <v-card v-for="board in resultBoards" :key="board.board" class="mb-4">
                    <v-card-title>{{ board.board }}</v-card-title>
                    <v-card-text>
                        <div class="mb-2"><v-chip color="info" size="small" class="mr-2">FORM</v-chip><code>{{ board.form }}</code></div>
                        <div class="mb-3"><v-chip color="success" size="small" class="mr-2">FETCH</v-chip><code>{{ board.fetch }}</code></div>
                        <p class="text-body-2 mb-2">Required fields: <code>{{ board.fields.join(', ') }}</code></p>
                        <pre class="bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto">{{ board.sample }}</pre>
                    </v-card-text>
                </v-card>

                <v-card>
                    <v-card-title>Result Response</v-card-title>
                    <v-card-text>
                        <pre class="bg-grey-darken-4 text-blue-lighten-1 pa-4 rounded overflow-x-auto">{{ resultResponse }}</pre>
                    </v-card-text>
                </v-card>
            </v-window-item>

            <v-window-item value="paygo">
                <v-card>
                    <v-card-text class="pa-6">
                        <h2 class="text-h5 font-weight-bold mb-4">PayGo Verification</h2>
                        <p class="text-body-1 mb-4">
                            PayGo lets an end user pay for a verification through a public Paystack checkout. It does not use an API bearer token and does not debit your API wallet. Create and price the service under PayGo Services, then use its generated public URLs.
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

                        <h3 class="text-subtitle-1 font-weight-bold mb-2">NIN PayGo Request</h3>
                        <p class="text-body-2 mb-3">Request JSON explicitly to receive the Paystack checkout URL, then call the verify endpoint after payment. No bearer token is used.</p>
                        <pre class="bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto mb-6">curl -X POST "{{ appBaseUrl }}/paygo/YOUR_PUBLIC_SLUG/initiate?response=json" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"nin":"12345678901","email":"payer@example.com"}'

curl -X POST "{{ appBaseUrl }}/api/paygo/YOUR_PUBLIC_SLUG/verify" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"reference":"PGO-REFERENCE","nin":"12345678901","consent":true}'</pre>

                        <h3 class="text-subtitle-1 font-weight-bold mb-2">Portal context and packages</h3>
                        <p class="text-body-2 mb-5">
                            Append <code>candidate_id</code>, <code>portal_ref</code>, <code>state</code>, and <code>sitting</code> to the selector or board URL. They are returned on redirects and webhooks. Supplying your own unique <code>reference</code> creates a reference package that can complete two successful result attempts during its seven-day validity window.
                        </p>
                        <p class="text-body-2 mb-5">
                            Standard result payments use the pull limit captured when payment starts, and each successful API pull consumes one. Reference packages count successful result attempts instead, so polling an already completed attempt does not consume another attempt.
                        </p>

                        <v-table class="mb-6">
                            <thead><tr><th>Callback mode</th><th>Behavior</th><th>Required setting</th></tr></thead>
                            <tbody>
                                <tr><td><code>redirect</code></td><td>A successful result returns to the success URL with status and portal context. Result errors remain on the EaseVerifier form for correction.</td><td>Success and failure URLs</td></tr>
                                <tr><td><code>webhook</code></td><td>Posts the completed or failed result to your backend.</td><td>Customer webhook URL</td></tr>
                                <tr><td><code>hybrid</code></td><td>Sends the webhook and redirects the browser.</td><td>All callback URLs</td></tr>
                            </tbody>
                        </v-table>

                        <h3 class="text-subtitle-1 font-weight-bold mb-2">Result Pull</h3>
                        <pre class="bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto mb-6">curl -H "Accept: application/json" \
  "{{ appBaseUrl }}/api/paygo/results/APPLICATION-90210?portal_ref=APPLICATION-90210&amp;sitting=1"</pre>
                        <pre class="bg-grey-darken-4 text-blue-lighten-1 pa-4 rounded overflow-x-auto mb-6">{{ paygoPullResponse }}</pre>

                        <h3 class="text-subtitle-1 font-weight-bold mb-2">Webhook Payload</h3>
                        <p class="text-body-2 mb-3">Webhook requests include <code>X-EaseVerifier-Event</code>, <code>X-EaseVerifier-Reference</code>, and <code>X-EaseVerifier-Signature</code>. A failed result uses the <code>paygo.result.failed</code> event with <code>result_status: failed</code>.</p>
                        <pre class="bg-grey-darken-4 text-blue-lighten-1 pa-4 rounded overflow-x-auto">{{ paygoWebhookPayload }}</pre>
                    </v-card-text>
                </v-card>
            </v-window-item>

            <v-window-item value="implementation">
                <PaygoResultSimulation class="mb-6" />

                <v-alert type="warning" variant="tonal" class="mb-4">
                    The result form endpoint and fetch endpoint are billed independently in live mode. Cache form field metadata on your server and refresh it only when needed.
                </v-alert>

                <v-card class="mb-4">
                    <v-card-title>Direct WAEC API - Server-side JavaScript</v-card-title>
                    <v-card-text>
                        <p class="text-body-2 mb-3">This path uses your bearer token and deducts the result-fetch price from your wallet.</p>
                        <pre class="bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto">{{ resultImplementation }}</pre>
                    </v-card-text>
                </v-card>

                <v-card class="mb-4">
                    <v-card-title>School Portal PayGo Flow</v-card-title>
                    <v-card-text>
                        <p class="text-body-2 mb-3">Replace the referral code with the selector URL shown in PayGo Services. The payer funds this flow.</p>
                        <pre class="bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto">{{ paygoPortalImplementation }}</pre>
                    </v-card-text>
                </v-card>

                <v-card>
                    <v-card-title>Verify the PayGo Webhook</v-card-title>
                    <v-card-text>
                        <p class="text-body-2 mb-3">Compute HMAC-SHA256 with the webhook secret shown in PayGo Services and compare it with <code>X-EaseVerifier-Signature</code> using a timing-safe comparison. Verify your signed <code>state</code> before updating a candidate record.</p>
                        <pre class="bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto">{{ webhookVerification }}</pre>
                    </v-card-text>
                </v-card>
            </v-window-item>

            <v-window-item value="pins">
                <v-card class="mb-4">
                    <v-card-title><v-chip color="info" size="small" class="mr-2">GET</v-chip>/result-pins/products</v-card-title>
                    <v-card-text>Returns active products with <code>id</code>, <code>card_type_id</code>, <code>price</code>, <code>min_quantity</code>, and <code>max_quantity</code>.</v-card-text>
                </v-card>

                <v-card>
                    <v-card-title><v-chip color="success" size="small" class="mr-2">POST</v-chip>/result-pins/purchase</v-card-title>
                    <v-card-text>
                        <p class="text-body-2 mb-3">Send either <code>product_id</code> or <code>card_type_id</code>, plus <code>quantity</code>. Purchases deduct from wallet balance.</p>
                        <pre class="bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto">{
  "product_id": 3,
  "quantity": 1
}</pre>
                    </v-card-text>
                </v-card>
            </v-window-item>

            <v-window-item value="wallet">
                <v-card>
                    <v-card-text class="pa-6">
                        <h2 class="text-h5 font-weight-bold mb-4">Wallet, Services, and History</h2>
                        <v-table>
                            <thead>
                                <tr><th>Endpoint</th><th>Description</th><th>Query</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>GET /wallet/balance</code></td><td>Current wallet or branch wallet balance.</td><td>None</td></tr>
                                <tr><td><code>GET /services</code></td><td>Active verification services.</td><td>None</td></tr>
                                <tr><td><code>GET /verifications</code></td><td>Paginated verification history.</td><td><code>service</code>, <code>status</code>, <code>per_page</code></td></tr>
                                <tr><td><code>GET /verifications/{reference}</code></td><td>Single verification request by reference.</td><td>None</td></tr>
                            </tbody>
                        </v-table>
                    </v-card-text>
                </v-card>
            </v-window-item>

            <v-window-item value="examples">
                <v-card>
                    <v-card-text class="pa-6">
                        <h2 class="text-h5 font-weight-bold mb-4">Code Examples</h2>
                        <v-expansion-panels>
                            <v-expansion-panel v-for="(code, language) in codeExamples" :key="language" :title="String(language).toUpperCase()">
                                <v-expansion-panel-text>
                                    <pre class="bg-grey-darken-4 text-white pa-4 rounded overflow-x-auto">{{ code }}</pre>
                                </v-expansion-panel-text>
                            </v-expansion-panel>
                        </v-expansion-panels>
                    </v-card-text>
                </v-card>
            </v-window-item>

            <v-window-item value="errors">
                <v-card>
                    <v-card-text class="pa-6">
                        <h2 class="text-h5 font-weight-bold mb-4">Errors</h2>
                        <p class="text-body-1 mb-4">Failed requests return JSON with <code>success: false</code>, a human-readable <code>error</code>, and a machine-readable <code>error_code</code>.</p>
                        <pre class="bg-grey-darken-4 text-red-lighten-2 pa-4 rounded overflow-x-auto mb-6">{{ errorResponse }}</pre>
                        <v-table>
                            <thead>
                                <tr><th>HTTP</th><th>Error Code</th><th>Meaning</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>400</code></td><td><code>SERVICE_UNAVAILABLE</code>, <code>PIN_PURCHASE_FAILED</code>, <code>RESULT_REFERENCE_INVALID</code>, <code>UNKNOWN_ERROR</code></td><td>Request was understood but could not be completed.</td></tr>
                                <tr><td><code>401</code></td><td><code>UNAUTHORIZED</code></td><td>Missing, invalid, inactive, or IP-blocked API key.</td></tr>
                                <tr><td><code>402</code></td><td><code>INSUFFICIENT_FUNDS</code></td><td>Wallet balance is too low.</td></tr>
                                <tr><td><code>404</code></td><td><code>NOT_FOUND</code>, <code>PRODUCT_UNAVAILABLE</code>, <code>UNSUPPORTED_RESULT_BOARD</code></td><td>Requested record, product, or board was not found.</td></tr>
                                <tr><td><code>422</code></td><td><code>VALIDATION_ERROR</code>, <code>TEST_NIN_REQUIRED</code></td><td>Required fields are missing or invalid.</td></tr>
                                <tr><td><code>429</code></td><td><code>RATE_LIMIT_EXCEEDED</code>, <code>PULL_LIMIT_EXCEEDED</code></td><td>The API key or PayGo reference exceeded its configured limit.</td></tr>
                            </tbody>
                        </v-table>
                    </v-card-text>
                </v-card>
            </v-window-item>
        </v-window>
    </CustomerLayout>
</template>
