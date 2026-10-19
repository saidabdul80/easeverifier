import{P as D}from"./PaygoResultSimulation-VM-lXkPm.js";import{_ as j}from"./CustomerLayout.vue_vue_type_script_setup_true_lang-BPEEkqPK.js";import{d as q,m as F,r as d,c as E,o as h,a as l,u as H,h as W,w as n,b as e,e as o,t as a,F as x,f as T}from"./app-s_fdu6Jf.js";import"./lock-keyhole-BMNFrZBR.js";import"./index-Bhb5BNBb.js";const Y={class:"mb-6"},M={class:"mb-2"},$={class:"mb-3"},z={class:"text-body-2 mb-2"},K={class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto"},X={class:"bg-grey-darken-4 text-white pa-4 rounded overflow-x-auto"},b="https://verify.ashlabtech.ng",y="https://verify.ashlabtech.ng/api/v1",P="https://verify.ashlabtech.ng/api/mcp",w="11111111111",J=`{
  "success": true,
  "status": 200,
  "data": {
    "first_name": "John",
    "last_name": "Doe"
  },
  "response_time": 1240,
  "message": "NIN Verified Successfully",
  "sandbox": false
}`,Z=`{
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
}`,Q=`{
  "success": false,
  "error": "Insufficient wallet balance",
  "error_code": "INSUFFICIENT_FUNDS"
}`,ee=`import crypto from 'node:crypto';

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
return res.json({ received: true });`,te=`{
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
}`,le=`{
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
}`,de=q({__name:"Documentation",props:{user:{}},setup(R){const v=F("overview"),I=`{
  "$schema": "https://agent-plugins.org/schemas/1.0.0/mcp.schema.json",
  "mcpServers": {
    "easeverifier": {
      "type": "streamable-http",
      "url": "${P}"
    }
  }
}`,N=[{name:"NIN Verification",endpoint:"POST /verify/nin",field:"nin",searchValue:"NIN"},{name:"BVN Verification",endpoint:"POST /verify/bvn",field:"bvn",searchValue:"BVN"},{name:"CAC Verification",endpoint:"POST /verify/cac",field:"rc_number",searchValue:"RC Number"},{name:"Driver's License Verification",endpoint:"POST /verify/drivers-license",field:"license_number",searchValue:"Driver's License Number"}],S=[{board:"WAEC",form:"GET /results/waec/form",fetch:"POST /results/waec/fetch",fields:["txtExamNumber","ExamYear","ExamType","txtPIN"],sample:`{
  "txtExamNumber": "1234567890",
  "ExamYear": "2024",
  "ExamType": "MAY/JUN",
  "txtPIN": "1111222233334444555"
}`},{board:"NECO",form:"GET /results/neco/form",fetch:"POST /results/neco/fetch",fields:["exam_year","exam_type","reg_no","token"],sample:`{
  "exam_year": "2024",
  "exam_type": "ssce_int",
  "reg_no": "1234567890",
  "token": "123456789012"
}`},{board:"NBAIS",form:"GET /results/nbais/form",fetch:"POST /results/nbais/fetch",fields:["year","month","exam_no","pin"],sample:`{
  "year": "2022",
  "month": "Nov/Dec",
  "exam_no": "481634346OS",
  "pin": "123456789012"
}`},{board:"NABTEB",form:"GET /results/nabteb/form",fetch:"POST /results/nabteb/fetch",fields:["candid","examtype","examyear","serial","pin"],sample:`{
  "candid": "13123006",
  "examtype": "02",
  "examyear": "2021",
  "serial": "NER100000000",
  "pin": "123456789012"
}`}],_={curl:`curl -X POST ${y}/verify/nin \\
  -H "Authorization: Bearer YOUR_BEARER_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{"nin":"${w}","consent":true}'`,javascript:`const response = await fetch('${y}/results/nabteb/fetch', {
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

const data = await response.json();`,php:`<?php

$client = new GuzzleHttp\\Client();

$response = $client->post('${y}/result-pins/purchase', [
    'headers' => [
        'Authorization' => 'Bearer YOUR_BEARER_TOKEN',
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'product_id' => 3,
        'quantity' => 1,
    ],
]);

$data = json_decode($response->getBody(), true);`},C=`const API_URL = '${y}';
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
});`,k=`const portalRef = 'APPLICATION-90210';
const checkout = new URL(
  '${b}/paygo/results/customer/YOUR_REFERRAL_CODE'
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
  '${b}/api/paygo/results/' + encodeURIComponent(portalRef) +
  '?portal_ref=' + encodeURIComponent(portalRef) + '&sitting=1',
  { headers: { Accept: 'application/json' } }
);
const verifiedResult = await response.json();`;return(ne,t)=>{const O=d("v-btn"),i=d("v-tab"),U=d("v-tabs"),c=d("v-alert"),f=d("v-table"),s=d("v-card-text"),u=d("v-card"),p=d("v-window-item"),m=d("v-card-title"),g=d("v-chip"),L=d("v-expansion-panel-text"),V=d("v-expansion-panel"),G=d("v-expansion-panels"),B=d("v-window");return h(),E(x,null,[l(H(W),{title:"API Documentation - EaseVerifier"}),l(j,{user:R.user},{default:n(()=>[e("div",Y,[l(O,{variant:"text","prepend-icon":"mdi-arrow-left",href:"/customer/api",class:"mb-2"},{default:n(()=>[...t[2]||(t[2]=[o("Back to API Keys",-1)])]),_:1}),t[3]||(t[3]=e("h1",{class:"text-h4 font-weight-bold mb-1"},"API Documentation",-1)),t[4]||(t[4]=e("p",{class:"text-body-2 text-grey"},"Integrate identity verification, result checks, payer-funded PayGo, result PIN purchases, wallet balance, history, and AI clients through MCP.",-1))]),l(U,{modelValue:v.value,"onUpdate:modelValue":t[0]||(t[0]=r=>v.value=r),color:"primary","show-arrows":"",class:"mb-6"},{default:n(()=>[l(i,{value:"overview"},{default:n(()=>[...t[5]||(t[5]=[o("Overview",-1)])]),_:1}),l(i,{value:"authentication"},{default:n(()=>[...t[6]||(t[6]=[o("Authentication",-1)])]),_:1}),l(i,{value:"identity"},{default:n(()=>[...t[7]||(t[7]=[o("Identity",-1)])]),_:1}),l(i,{value:"results"},{default:n(()=>[...t[8]||(t[8]=[o("Results",-1)])]),_:1}),l(i,{value:"paygo"},{default:n(()=>[...t[9]||(t[9]=[o("PayGo",-1)])]),_:1}),l(i,{value:"implementation"},{default:n(()=>[...t[10]||(t[10]=[o("Implementation",-1)])]),_:1}),l(i,{value:"pins"},{default:n(()=>[...t[11]||(t[11]=[o("Result PINs",-1)])]),_:1}),l(i,{value:"wallet"},{default:n(()=>[...t[12]||(t[12]=[o("Wallet",-1)])]),_:1}),l(i,{value:"mcp"},{default:n(()=>[...t[13]||(t[13]=[o("MCP",-1)])]),_:1}),l(i,{value:"examples"},{default:n(()=>[...t[14]||(t[14]=[o("Examples",-1)])]),_:1}),l(i,{value:"errors"},{default:n(()=>[...t[15]||(t[15]=[o("Errors",-1)])]),_:1})]),_:1},8,["modelValue"]),l(B,{modelValue:v.value,"onUpdate:modelValue":t[1]||(t[1]=r=>v.value=r)},{default:n(()=>[l(p,{value:"overview"},{default:n(()=>[l(u,null,{default:n(()=>[l(s,{class:"pa-6"},{default:n(()=>[t[19]||(t[19]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Getting Started",-1)),t[20]||(t[20]=e("p",{class:"text-body-1 mb-4"}," The EaseVerifier API is wallet-funded. API keys can be scoped to your main account or a branch, and branch-scoped keys automatically charge that branch wallet and return branch-specific history. ",-1)),l(c,{type:"info",variant:"tonal",class:"mb-4"},{default:n(()=>[t[16]||(t[16]=e("strong",null,"Base URL:",-1)),t[17]||(t[17]=o()),e("code",null,a(y))]),_:1}),l(f,null,{default:n(()=>[...t[18]||(t[18]=[e("thead",null,[e("tr",null,[e("th",null,"Service"),e("th",null,"Endpoint"),e("th",null,"Billing")])],-1),e("tbody",null,[e("tr",null,[e("td",null,"Service list"),e("td",null,[e("code",null,"GET /services")]),e("td",null,"Free")]),e("tr",null,[e("td",null,"Wallet balance"),e("td",null,[e("code",null,"GET /wallet/balance")]),e("td",null,"Free")]),e("tr",null,[e("td",null,"Identity verification"),e("td",null,[e("code",null,"POST /verify/nin"),o(", "),e("code",null,"/verify/bvn"),o(", "),e("code",null,"/verify/{service}")]),e("td",null,"Wallet")]),e("tr",null,[e("td",null,"Result form metadata"),e("td",null,[e("code",null,"GET /results/{board}/form")]),e("td",null,"Wallet unless sandbox")]),e("tr",null,[e("td",null,"Result verification"),e("td",null,[e("code",null,"POST /results/{board}/fetch")]),e("td",null,"Wallet unless sandbox")]),e("tr",null,[e("td",null,"PayGo verification"),e("td",null,[e("code",null,"/paygo/..."),o(", "),e("code",null,"/api/paygo/...")]),e("td",null,"End-user Paystack payment")]),e("tr",null,[e("td",null,"Result PIN products"),e("td",null,[e("code",null,"GET /result-pins/products")]),e("td",null,"Free")]),e("tr",null,[e("td",null,"Result PIN purchase"),e("td",null,[e("code",null,"POST /result-pins/purchase")]),e("td",null,"Wallet")]),e("tr",null,[e("td",null,"History"),e("td",null,[e("code",null,"GET /verifications"),o(", "),e("code",null,"GET /verifications/{reference}")]),e("td",null,"Free")])],-1)])]),_:1})]),_:1})]),_:1})]),_:1}),l(p,{value:"authentication"},{default:n(()=>[l(u,null,{default:n(()=>[l(s,{class:"pa-6"},{default:n(()=>[t[25]||(t[25]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Authentication",-1)),t[26]||(t[26]=e("p",{class:"text-body-1 mb-4"},[o("Send your generated token on every request. The bearer token and "),e("code",null,"X-API-Key"),o(" header are both supported.")],-1)),l(c,{type:"warning",variant:"tonal",class:"mb-4"},{default:n(()=>[...t[21]||(t[21]=[o(" Copy the token when the key is created. The full secret is not shown again. ",-1)])]),_:1}),l(f,{class:"mb-4"},{default:n(()=>[...t[22]||(t[22]=[e("thead",null,[e("tr",null,[e("th",null,"Header"),e("th",null,"Value"),e("th",null,"Description")])],-1),e("tbody",null,[e("tr",null,[e("td",null,[e("code",null,"Authorization")]),e("td",null,[e("code",null,"Bearer YOUR_BEARER_TOKEN")]),e("td",null,"Preferred authentication header")]),e("tr",null,[e("td",null,[e("code",null,"X-API-Key")]),e("td",null,[e("code",null,"YOUR_BEARER_TOKEN")]),e("td",null,"Alternative key header")]),e("tr",null,[e("td",null,[e("code",null,"Content-Type")]),e("td",null,[e("code",null,"application/json")]),e("td",null,"Required for POST requests")])],-1)])]),_:1}),l(c,{type:"info",variant:"tonal"},{default:n(()=>[t[23]||(t[23]=o(" Test NIN calls only accept ",-1)),e("strong",null,a(w)),t[24]||(t[24]=o(". Live keys call real providers and deduct wallet balance. ",-1))]),_:1})]),_:1})]),_:1})]),_:1}),l(p,{value:"identity"},{default:n(()=>[l(u,null,{default:n(()=>[l(s,{class:"pa-6"},{default:n(()=>[t[32]||(t[32]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Identity Verification",-1)),t[33]||(t[33]=e("p",{class:"text-body-1 mb-4"}," Identity endpoints expect the search value in the field that matches the service you are calling. ",-1)),l(c,{type:"info",variant:"tonal",class:"mb-4"},{default:n(()=>[t[27]||(t[27]=o(" For test NIN verification, send ",-1)),e("strong",null,a(w)),t[28]||(t[28]=o(". Services disabled by admin settings return ",-1)),t[29]||(t[29]=e("code",null,"SERVICE_UNAVAILABLE",-1)),t[30]||(t[30]=o(". ",-1))]),_:1}),l(f,{class:"mb-6"},{default:n(()=>[t[31]||(t[31]=e("thead",null,[e("tr",null,[e("th",null,"Service"),e("th",null,"Endpoint"),e("th",null,"Body")])],-1)),e("tbody",null,[(h(),E(x,null,T(N,r=>e("tr",{key:r.endpoint},[e("td",null,a(r.name),1),e("td",null,[e("code",null,a(r.endpoint),1)]),e("td",null,[e("code",null,'{ "'+a(r.field)+'": "'+a(r.searchValue)+'", "consent": true }',1)])])),64))])]),_:1}),t[34]||(t[34]=e("h3",{class:"text-subtitle-1 font-weight-bold mb-2"},"Success Response",-1)),e("pre",{class:"bg-grey-darken-4 text-blue-lighten-1 pa-4 rounded overflow-x-auto"},a(J))]),_:1})]),_:1})]),_:1}),l(p,{value:"results"},{default:n(()=>[l(c,{type:"warning",variant:"tonal",class:"mb-4"},{default:n(()=>[...t[35]||(t[35]=[o(" Result checker PINs, serials, and tokens may be consumed by the board provider. Submit only when the customer has authorized the lookup. ",-1)])]),_:1}),l(c,{type:"info",variant:"tonal",class:"mb-4"},{default:n(()=>[...t[36]||(t[36]=[o(" WAEC verification requires the examination number, year, examination type, and purchased PIN. No card serial number is required. Use ",-1),e("code",null,"MAY/JUN",-1),o(" for school candidates and ",-1),e("code",null,"NOV/DEC",-1),o(" for private candidates. ",-1)])]),_:1}),(h(),E(x,null,T(S,r=>l(u,{key:r.board,class:"mb-4"},{default:n(()=>[l(m,null,{default:n(()=>[o(a(r.board),1)]),_:2},1024),l(s,null,{default:n(()=>[e("div",M,[l(g,{color:"info",size:"small",class:"mr-2"},{default:n(()=>[...t[37]||(t[37]=[o("FORM",-1)])]),_:1}),e("code",null,a(r.form),1)]),e("div",$,[l(g,{color:"success",size:"small",class:"mr-2"},{default:n(()=>[...t[38]||(t[38]=[o("FETCH",-1)])]),_:1}),e("code",null,a(r.fetch),1)]),e("p",z,[t[39]||(t[39]=o("Required fields: ",-1)),e("code",null,a(r.fields.join(", ")),1)]),e("pre",K,a(r.sample),1)]),_:2},1024)]),_:2},1024)),64)),l(u,null,{default:n(()=>[l(m,null,{default:n(()=>[...t[40]||(t[40]=[o("Result Response",-1)])]),_:1}),l(s,null,{default:n(()=>[e("pre",{class:"bg-grey-darken-4 text-blue-lighten-1 pa-4 rounded overflow-x-auto"},a(Z))]),_:1})]),_:1})]),_:1}),l(p,{value:"paygo"},{default:n(()=>[l(u,null,{default:n(()=>[l(s,{class:"pa-6"},{default:n(()=>[t[44]||(t[44]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"PayGo Verification",-1)),t[45]||(t[45]=e("p",{class:"text-body-1 mb-4"}," PayGo lets an end user pay for a verification through a public Paystack checkout. It does not use an API bearer token and does not debit your API wallet. Create and price the service under PayGo Services, then use its generated public URLs. ",-1)),l(c,{type:"info",variant:"tonal",class:"mb-6"},{default:n(()=>[...t[41]||(t[41]=[o(" PayGo payment references are valid for seven days from confirmed payment. Keep result references private because the public pull URL uses the reference as its access credential. ",-1)])]),_:1}),l(f,{class:"mb-6"},{default:n(()=>[...t[42]||(t[42]=[e("thead",null,[e("tr",null,[e("th",null,"Flow"),e("th",null,"Endpoint"),e("th",null,"Purpose")])],-1),e("tbody",null,[e("tr",null,[e("td",null,"NIN checkout"),e("td",null,[e("code",null,"GET|POST /paygo/{publicSlug}/initiate/{nin?}")]),e("td",null,"Collect NIN details and initialize Paystack.")]),e("tr",null,[e("td",null,"NIN result"),e("td",null,[e("code",null,"GET|POST /api/paygo/{publicSlug}/verify/{nin?}")]),e("td",null,"Use the paid NIN reference with its three-attempt allowance.")]),e("tr",null,[e("td",null,"Result selector"),e("td",null,[e("code",null,"GET /paygo/results/customer/{referralCode}")]),e("td",null,"Let the payer choose an enabled examination board.")]),e("tr",null,[e("td",null,"Board checkout"),e("td",null,[e("code",null,"GET|POST /paygo/results/{publicSlug}")]),e("td",null,"Collect board fields, initialize payment, and fetch the result.")]),e("tr",null,[e("td",null,"Result pull"),e("td",null,[e("code",null,"GET /api/paygo/results/{reference}")]),e("td",null,"Return the stored verified result; limited to 30 requests per minute.")])],-1)])]),_:1}),t[46]||(t[46]=e("h3",{class:"text-subtitle-1 font-weight-bold mb-2"},"NIN PayGo Request",-1)),t[47]||(t[47]=e("p",{class:"text-body-2 mb-3"},"Request JSON explicitly to receive the Paystack checkout URL, then call the verify endpoint after payment. No bearer token is used.",-1)),e("pre",{class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto mb-6"},'curl -X POST "'+a(b)+`/paygo/YOUR_PUBLIC_SLUG/initiate?response=json" \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"nin":"12345678901","email":"payer@example.com"}'

curl -X POST "`+a(b)+`/api/paygo/YOUR_PUBLIC_SLUG/verify" \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"reference":"PGO-REFERENCE","nin":"12345678901","consent":true}'`),t[48]||(t[48]=e("h3",{class:"text-subtitle-1 font-weight-bold mb-2"},"Portal context and packages",-1)),t[49]||(t[49]=e("p",{class:"text-body-2 mb-5"},[o(" Append "),e("code",null,"candidate_id"),o(", "),e("code",null,"portal_ref"),o(", "),e("code",null,"state"),o(", and "),e("code",null,"sitting"),o(" to the selector or board URL. They are returned on redirects and webhooks. Supplying your own unique "),e("code",null,"reference"),o(" creates a reference package that can complete two successful result attempts during its seven-day validity window. ")],-1)),t[50]||(t[50]=e("p",{class:"text-body-2 mb-5"}," Standard result payments use the pull limit captured when payment starts, and each successful API pull consumes one. Reference packages count successful result attempts instead, so polling an already completed attempt does not consume another attempt. ",-1)),l(f,{class:"mb-6"},{default:n(()=>[...t[43]||(t[43]=[e("thead",null,[e("tr",null,[e("th",null,"Callback mode"),e("th",null,"Behavior"),e("th",null,"Required setting")])],-1),e("tbody",null,[e("tr",null,[e("td",null,[e("code",null,"redirect")]),e("td",null,"A successful result returns to the success URL with status and portal context. Result errors remain on the EaseVerifier form for correction."),e("td",null,"Success and failure URLs")]),e("tr",null,[e("td",null,[e("code",null,"webhook")]),e("td",null,"Posts the completed or failed result to your backend."),e("td",null,"Customer webhook URL")]),e("tr",null,[e("td",null,[e("code",null,"hybrid")]),e("td",null,"Sends the webhook and redirects the browser."),e("td",null,"All callback URLs")])],-1)])]),_:1}),t[51]||(t[51]=e("h3",{class:"text-subtitle-1 font-weight-bold mb-2"},"Result Pull",-1)),e("pre",{class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto mb-6"},`curl -H "Accept: application/json" \\
  "`+a(b)+'/api/paygo/results/APPLICATION-90210?portal_ref=APPLICATION-90210&sitting=1"'),e("pre",{class:"bg-grey-darken-4 text-blue-lighten-1 pa-4 rounded overflow-x-auto mb-6"},a(le)),t[52]||(t[52]=e("h3",{class:"text-subtitle-1 font-weight-bold mb-2"},"Webhook Payload",-1)),t[53]||(t[53]=e("p",{class:"text-body-2 mb-3"},[o("Webhook requests include "),e("code",null,"X-EaseVerifier-Event"),o(", "),e("code",null,"X-EaseVerifier-Reference"),o(", and "),e("code",null,"X-EaseVerifier-Signature"),o(". A failed result uses the "),e("code",null,"paygo.result.failed"),o(" event with "),e("code",null,"result_status: failed"),o(".")],-1)),e("pre",{class:"bg-grey-darken-4 text-blue-lighten-1 pa-4 rounded overflow-x-auto"},a(te))]),_:1})]),_:1})]),_:1}),l(p,{value:"implementation"},{default:n(()=>[l(D,{class:"mb-6"}),l(c,{type:"warning",variant:"tonal",class:"mb-4"},{default:n(()=>[...t[54]||(t[54]=[o(" The result form endpoint and fetch endpoint are billed independently in live mode. Cache form field metadata on your server and refresh it only when needed. ",-1)])]),_:1}),l(u,{class:"mb-4"},{default:n(()=>[l(m,null,{default:n(()=>[...t[55]||(t[55]=[o("Direct WAEC API - Server-side JavaScript",-1)])]),_:1}),l(s,null,{default:n(()=>[t[56]||(t[56]=e("p",{class:"text-body-2 mb-3"},"This path uses your bearer token and deducts the result-fetch price from your wallet.",-1)),e("pre",{class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto"},a(C))]),_:1})]),_:1}),l(u,{class:"mb-4"},{default:n(()=>[l(m,null,{default:n(()=>[...t[57]||(t[57]=[o("School Portal PayGo Flow",-1)])]),_:1}),l(s,null,{default:n(()=>[t[58]||(t[58]=e("p",{class:"text-body-2 mb-3"},"Replace the referral code with the selector URL shown in PayGo Services. The payer funds this flow.",-1)),e("pre",{class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto"},a(k))]),_:1})]),_:1}),l(u,null,{default:n(()=>[l(m,null,{default:n(()=>[...t[59]||(t[59]=[o("Verify the PayGo Webhook",-1)])]),_:1}),l(s,null,{default:n(()=>[t[60]||(t[60]=e("p",{class:"text-body-2 mb-3"},[o("Compute HMAC-SHA256 with the webhook secret shown in PayGo Services and compare it with "),e("code",null,"X-EaseVerifier-Signature"),o(" using a timing-safe comparison. Verify your signed "),e("code",null,"state"),o(" before updating a candidate record.")],-1)),e("pre",{class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto"},a(ee))]),_:1})]),_:1})]),_:1}),l(p,{value:"pins"},{default:n(()=>[l(u,{class:"mb-4"},{default:n(()=>[l(m,null,{default:n(()=>[l(g,{color:"info",size:"small",class:"mr-2"},{default:n(()=>[...t[61]||(t[61]=[o("GET",-1)])]),_:1}),t[62]||(t[62]=o("/result-pins/products",-1))]),_:1}),l(s,null,{default:n(()=>[...t[63]||(t[63]=[o("Returns active products with ",-1),e("code",null,"id",-1),o(", ",-1),e("code",null,"card_type_id",-1),o(", ",-1),e("code",null,"price",-1),o(", ",-1),e("code",null,"min_quantity",-1),o(", and ",-1),e("code",null,"max_quantity",-1),o(".",-1)])]),_:1})]),_:1}),l(u,null,{default:n(()=>[l(m,null,{default:n(()=>[l(g,{color:"success",size:"small",class:"mr-2"},{default:n(()=>[...t[64]||(t[64]=[o("POST",-1)])]),_:1}),t[65]||(t[65]=o("/result-pins/purchase",-1))]),_:1}),l(s,null,{default:n(()=>[...t[66]||(t[66]=[e("p",{class:"text-body-2 mb-3"},[o("Send either "),e("code",null,"product_id"),o(" or "),e("code",null,"card_type_id"),o(", plus "),e("code",null,"quantity"),o(". Purchases deduct from wallet balance.")],-1),e("pre",{class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto"},`{
  "product_id": 3,
  "quantity": 1
}`,-1)])]),_:1})]),_:1})]),_:1}),l(p,{value:"wallet"},{default:n(()=>[l(u,null,{default:n(()=>[l(s,{class:"pa-6"},{default:n(()=>[t[68]||(t[68]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Wallet, Services, and History",-1)),l(f,null,{default:n(()=>[...t[67]||(t[67]=[e("thead",null,[e("tr",null,[e("th",null,"Endpoint"),e("th",null,"Description"),e("th",null,"Query")])],-1),e("tbody",null,[e("tr",null,[e("td",null,[e("code",null,"GET /wallet/balance")]),e("td",null,"Current wallet or branch wallet balance."),e("td",null,"None")]),e("tr",null,[e("td",null,[e("code",null,"GET /services")]),e("td",null,"Active services with customer pricing and currency."),e("td",null,"None")]),e("tr",null,[e("td",null,[e("code",null,"GET /verifications")]),e("td",null,"Paginated verification history."),e("td",null,[e("code",null,"service"),o(", "),e("code",null,"status"),o(", "),e("code",null,"per_page")])]),e("tr",null,[e("td",null,[e("code",null,"GET /verifications/{reference}")]),e("td",null,"Single verification request by reference."),e("td",null,"None")])],-1)])]),_:1})]),_:1})]),_:1})]),_:1}),l(p,{value:"mcp"},{default:n(()=>[l(u,null,{default:n(()=>[l(s,{class:"pa-6"},{default:n(()=>[t[76]||(t[76]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"MCP Server",-1)),t[77]||(t[77]=e("p",{class:"text-body-1 mb-4"}," Connect your EaseVerifier account to the Streamable HTTP MCP endpoint with OAuth 2.1. The server exposes identity verification, all supported result boards, PIN purchases, wallet balance, services, and verification history to the connected customer account. ",-1)),l(c,{type:"info",variant:"tonal",class:"mb-4"},{default:n(()=>[t[69]||(t[69]=e("strong",null,"Endpoint:",-1)),t[70]||(t[70]=o()),e("code",null,a(P)),t[71]||(t[71]=e("br",null,null,-1)),t[72]||(t[72]=o(" ChatGPT discovers the OAuth login automatically. Sign in, review the permissions, and select ",-1)),t[73]||(t[73]=e("strong",null,"Connect account",-1)),t[74]||(t[74]=o(". Your account must have API access enabled. ",-1))]),_:1}),e("pre",{class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto mb-4"},a(I)),l(c,{type:"warning",variant:"tonal"},{default:n(()=>[...t[75]||(t[75]=[o(" Do not add a customer API key to the plugin ZIP. API keys are for direct REST integrations; MCP uses OAuth account linking. Chargeable tools require explicit confirmation and should never be retried automatically after a timeout. ",-1)])]),_:1})]),_:1})]),_:1})]),_:1}),l(p,{value:"examples"},{default:n(()=>[l(u,null,{default:n(()=>[l(s,{class:"pa-6"},{default:n(()=>[t[78]||(t[78]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Code Examples",-1)),l(G,null,{default:n(()=>[(h(),E(x,null,T(_,(r,A)=>l(V,{key:A,title:String(A).toUpperCase()},{default:n(()=>[l(L,null,{default:n(()=>[e("pre",X,a(r),1)]),_:2},1024)]),_:2},1032,["title"])),64))]),_:1})]),_:1})]),_:1})]),_:1}),l(p,{value:"errors"},{default:n(()=>[l(u,null,{default:n(()=>[l(s,{class:"pa-6"},{default:n(()=>[t[80]||(t[80]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Errors",-1)),t[81]||(t[81]=e("p",{class:"text-body-1 mb-4"},[o("Failed requests return JSON with "),e("code",null,"success: false"),o(", a human-readable "),e("code",null,"error"),o(", and a machine-readable "),e("code",null,"error_code"),o(".")],-1)),e("pre",{class:"bg-grey-darken-4 text-red-lighten-2 pa-4 rounded overflow-x-auto mb-6"},a(Q)),l(f,null,{default:n(()=>[...t[79]||(t[79]=[e("thead",null,[e("tr",null,[e("th",null,"HTTP"),e("th",null,"Error Code"),e("th",null,"Meaning")])],-1),e("tbody",null,[e("tr",null,[e("td",null,[e("code",null,"400")]),e("td",null,[e("code",null,"SERVICE_UNAVAILABLE"),o(", "),e("code",null,"PIN_PURCHASE_FAILED"),o(", "),e("code",null,"RESULT_REFERENCE_INVALID"),o(", "),e("code",null,"UNKNOWN_ERROR")]),e("td",null,"Request was understood but could not be completed.")]),e("tr",null,[e("td",null,[e("code",null,"401")]),e("td",null,[e("code",null,"UNAUTHORIZED")]),e("td",null,"Missing, invalid, inactive, or IP-blocked API key.")]),e("tr",null,[e("td",null,[e("code",null,"402")]),e("td",null,[e("code",null,"INSUFFICIENT_FUNDS")]),e("td",null,"Wallet balance is too low.")]),e("tr",null,[e("td",null,[e("code",null,"404")]),e("td",null,[e("code",null,"NOT_FOUND"),o(", "),e("code",null,"PRODUCT_UNAVAILABLE"),o(", "),e("code",null,"UNSUPPORTED_RESULT_BOARD")]),e("td",null,"Requested record, product, or board was not found.")]),e("tr",null,[e("td",null,[e("code",null,"422")]),e("td",null,[e("code",null,"VALIDATION_ERROR"),o(", "),e("code",null,"TEST_NIN_REQUIRED")]),e("td",null,"Required fields are missing or invalid.")]),e("tr",null,[e("td",null,[e("code",null,"429")]),e("td",null,[e("code",null,"RATE_LIMIT_EXCEEDED"),o(", "),e("code",null,"PULL_LIMIT_EXCEEDED")]),e("td",null,"The API key or PayGo reference exceeded its configured limit.")])],-1)])]),_:1})]),_:1})]),_:1})]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["user"])],64)}}});export{de as default};
