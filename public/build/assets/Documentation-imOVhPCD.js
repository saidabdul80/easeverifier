import{d as $,m as V,r as o,c as _,o as x,a as l,w as n,b as e,u as b,h as J,l as Z,e as s,F as P,f as w,t as r,x as p,ae as f}from"./app-s_fdu6Jf.js";import{P as Q}from"./PaygoResultSimulation-VM-lXkPm.js";import{h as ee,s as te,p as le,d as ne,l as se,r as re}from"./index-Bhb5BNBb.js";import"./lock-keyhole-BMNFrZBR.js";const ae={class:"d-none d-md-flex align-center ga-2"},oe={class:"d-flex ga-2"},ie={class:"pa-6 pa-md-12",style:{"max-width":"980px"}},de={class:"d-md-none mb-6"},ue={class:"mb-12"},ce={class:"mb-12"},pe={class:"mb-12"},fe={class:"text-green-lighten-1 text-body-2",style:{"white-space":"pre-wrap"}},me={class:"mb-12"},ye={class:"mb-2"},be={class:"ml-2"},ve={class:"mb-3"},ge={class:"ml-2"},he={class:"text-body-2 mb-2"},Ee={class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded text-body-2",style:{"white-space":"pre-wrap"}},_e={class:"mb-12"},xe={class:"mb-12"},Pe={class:"mb-12"},Ae={class:"mb-12"},we={class:"mb-12"},Ie={class:"mb-12"},g="https://verify.ashlabtech.ng",h="https://verify.ashlabtech.ng/api/v1",B="https://verify.ashlabtech.ng/api/mcp",T="11111111111",Re="Authorization: Bearer YOUR_BEARER_TOKEN",Te=`{
  "success": true,
  "status": 200,
  "data": {
    "first_name": "John",
    "last_name": "Doe"
  },
  "response_time": 1240,
  "message": "NIN Verified Successfully",
  "sandbox": false
}`,Ne=`{
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
}`,ke=`{
  "success": false,
  "error": "Insufficient wallet balance",
  "error_code": "INSUFFICIENT_FUNDS"
}`,Se=`import crypto from 'node:crypto';

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
return res.json({ received: true });`,Ce=`{
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
}`,Oe=`{
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
}`,qe=$({__name:"Documentation",setup(Ue){const c=V("overview"),I=V(!1),N=[{name:"NIN Verification",endpoint:"POST /verify/nin",field:"nin",searchValue:"NIN",request:`curl -X POST ${h}/verify/nin \\
  -H "Authorization: Bearer YOUR_BEARER_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{"nin":"${T}","consent":true}'`},{name:"BVN Verification",endpoint:"POST /verify/bvn",field:"bvn",searchValue:"BVN",request:`curl -X POST ${h}/verify/bvn \\
  -H "Authorization: Bearer YOUR_BEARER_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{"bvn":"22123456789","consent":true}'`},{name:"CAC Verification",endpoint:"POST /verify/cac",field:"rc_number",searchValue:"RC Number",request:`curl -X POST ${h}/verify/cac \\
  -H "Authorization: Bearer YOUR_BEARER_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{"rc_number":"RC1234567","consent":true}'`},{name:"Driver's License Verification",endpoint:"POST /verify/drivers-license",field:"license_number",searchValue:"Driver's License Number",request:`curl -X POST ${h}/verify/drivers-license \\
  -H "Authorization: Bearer YOUR_BEARER_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{"license_number":"ABC123456789","consent":true}'`}],k=[{id:"overview",title:"Overview",icon:"mdi-rocket-launch"},{id:"authentication",title:"Authentication",icon:"mdi-lock"},{id:"identity",title:"Identity Verify",icon:"mdi-card-account-details"},{id:"result-verification",title:"Result Verify",icon:"mdi-certificate"},{id:"paygo",title:"PayGo",icon:"mdi-credit-card-outline"},{id:"implementation",title:"Result Verify Demo (Paygo)",icon:"mdi-code-braces"},{id:"result-pins",title:"Result PINs",icon:"mdi-card-account-details-star-outline"},{id:"wallet-history",title:"Wallet & History",icon:"mdi-wallet"},{id:"mcp",title:"MCP Server",icon:"mdi-robot-outline"},{id:"errors",title:"Errors",icon:"mdi-alert-circle"}],L=`{
  "$schema": "https://agent-plugins.org/schemas/1.0.0/mcp.schema.json",
  "mcpServers": {
    "easeverifier": {
      "type": "streamable-http",
      "url": "${B}"
    }
  }
}`,D=[{board:"WAEC",form:"GET /results/waec/form",fetch:"POST /results/waec/fetch",fields:["txtExamNumber","ExamYear","ExamType","txtPIN"],sample:`{
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
}`}],G=async S=>{await navigator.clipboard.writeText(S),I.value=!0,setTimeout(()=>I.value=!1,2e3)},q=`const API_URL = '${h}';
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
});`,H=`const portalRef = 'APPLICATION-90210';
const checkout = new URL(
  '${g}/paygo/results/customer/YOUR_REFERRAL_CODE'
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
  '${g}/api/paygo/results/' + encodeURIComponent(portalRef) +
  '?portal_ref=' + encodeURIComponent(portalRef) + '&sitting=1',
  { headers: { Accept: 'application/json' } }
);
const verifiedResult = await response.json();`;return(S,t)=>{const F=o("v-avatar"),R=o("v-spacer"),v=o("v-btn"),C=o("v-container"),j=o("v-app-bar"),W=o("v-icon"),M=o("v-list-item-title"),E=o("v-list-item"),O=o("v-list"),i=o("v-card"),U=o("v-col"),X=o("v-select"),d=o("v-card-title"),u=o("v-card-text"),m=o("v-alert"),y=o("v-table"),A=o("v-chip"),Y=o("v-row"),z=o("v-main"),K=o("v-app");return x(),_(P,null,[l(b(J),{title:"API Documentation - EaseVerifier"},{default:n(()=>[...t[2]||(t[2]=[e("meta",{name:"description",content:"EaseVerifier API and MCP documentation for identity verification, result verification, PayGo integration, result PIN purchase, wallet balance, services, and verification history."},null,-1),e("meta",{name:"keywords",content:"EaseVerifier API, EaseVerifier MCP, NIN verification API, result verification API, PayGo verification, WAEC API, NECO API, NABTEB API, NBAIS API, result PIN API"},null,-1),e("meta",{property:"og:title",content:"API Documentation - EaseVerifier"},null,-1),e("meta",{property:"og:description",content:"Complete integration guide for EaseVerifier API services."},null,-1),e("meta",{property:"og:type",content:"article"},null,-1),e("link",{rel:"canonical",href:"https://verify.ashlabtech.ng/documentation"},null,-1)])]),_:1}),l(K,null,{default:n(()=>[l(j,{flat:"",color:"white",elevation:"1"},{default:n(()=>[l(C,{class:"d-flex align-center"},{default:n(()=>[l(b(Z),{href:b(ee)(),class:"text-decoration-none d-flex align-center"},{default:n(()=>[l(F,{color:"primary",size:"36",class:"mr-2"},{default:n(()=>[...t[3]||(t[3]=[e("img",{src:"/ashlabtech.png",alt:"EaseVerifier",style:{width:"100%",height:"100%","object-fit":"contain"}},null,-1)])]),_:1}),t[4]||(t[4]=e("span",{class:"text-h6 font-weight-bold text-primary"},"EaseVerifier",-1))]),_:1},8,["href"]),l(R),e("div",ae,[l(v,{variant:"text",href:b(te)()},{default:n(()=>[...t[5]||(t[5]=[s("Services",-1)])]),_:1},8,["href"]),l(v,{variant:"text",href:b(le)()},{default:n(()=>[...t[6]||(t[6]=[s("Pricing",-1)])]),_:1},8,["href"]),l(v,{variant:"text",href:b(ne)(),color:"primary"},{default:n(()=>[...t[7]||(t[7]=[s("Documentation",-1)])]),_:1},8,["href"])]),l(R),e("div",oe,[l(v,{variant:"outlined",color:"primary",href:b(se)()},{default:n(()=>[...t[8]||(t[8]=[s("Login",-1)])]),_:1},8,["href"]),l(v,{variant:"flat",color:"primary",href:b(re)(),class:"d-none d-sm-flex"},{default:n(()=>[...t[9]||(t[9]=[s("Get Started",-1)])]),_:1},8,["href"])])]),_:1})]),_:1}),l(z,{class:"bg-grey-lighten-5"},{default:n(()=>[l(C,{fluid:"",class:"pa-0"},{default:n(()=>[l(Y,{"no-gutters":""},{default:n(()=>[l(U,{cols:"12",md:"3",lg:"2",class:"d-none d-md-block"},{default:n(()=>[l(i,{flat:"",class:"h-100 rounded-0 border-e",style:{position:"sticky",top:"64px"}},{default:n(()=>[l(O,{nav:"",density:"compact",class:"pa-4"},{default:n(()=>[(x(),_(P,null,w(k,a=>l(E,{key:a.id,active:c.value===a.id,onClick:Ve=>c.value=a.id,color:"primary",rounded:"lg"},{prepend:n(()=>[l(W,{size:"small"},{default:n(()=>[s(r(a.icon),1)]),_:2},1024)]),default:n(()=>[l(M,{class:"text-body-2"},{default:n(()=>[s(r(a.title),1)]),_:2},1024)]),_:2},1032,["active","onClick"])),64))]),_:1})]),_:1})]),_:1}),l(U,{cols:"12",md:"9",lg:"10"},{default:n(()=>[e("div",ie,[e("div",de,[l(X,{modelValue:c.value,"onUpdate:modelValue":t[0]||(t[0]=a=>c.value=a),items:k,"item-title":"title","item-value":"id",label:"Documentation section"},null,8,["modelValue"])]),p(e("section",ue,[t[13]||(t[13]=e("h1",{class:"text-h4 font-weight-bold mb-4"},"EaseVerifier API",-1)),t[14]||(t[14]=e("p",{class:"text-body-1 text-grey-darken-1 mb-6"}," The EaseVerifier API is a wallet-funded REST API for identity verification, examination result verification, result PIN purchase, wallet balance checks, service discovery, and verification history. ",-1)),l(i,{class:"mb-6",variant:"outlined"},{default:n(()=>[l(d,{class:"text-subtitle-1 font-weight-bold"},{default:n(()=>[...t[10]||(t[10]=[s("Base URL",-1)])]),_:1}),l(u,null,{default:n(()=>[e("code",{class:"bg-grey-lighten-4 pa-2 rounded"},r(h))]),_:1})]),_:1}),l(m,{type:"info",variant:"tonal",class:"mb-6"},{default:n(()=>[...t[11]||(t[11]=[s(" API keys may be scoped to a branch. When a key is branch-scoped, charges, wallet balance, and history are applied to that branch automatically. ",-1)])]),_:1}),l(y,null,{default:n(()=>[...t[12]||(t[12]=[e("thead",null,[e("tr",null,[e("th",null,"Service"),e("th",null,"Endpoint"),e("th",null,"Billing")])],-1),e("tbody",null,[e("tr",null,[e("td",null,"Service list"),e("td",null,[e("code",null,"GET /services")]),e("td",null,"Free")]),e("tr",null,[e("td",null,"Wallet balance"),e("td",null,[e("code",null,"GET /wallet/balance")]),e("td",null,"Free")]),e("tr",null,[e("td",null,"Identity verification"),e("td",null,[e("code",null,"POST /verify/nin"),s(", "),e("code",null,"/verify/bvn"),s(", "),e("code",null,"/verify/{service}")]),e("td",null,"Wallet")]),e("tr",null,[e("td",null,"Result form metadata"),e("td",null,[e("code",null,"GET /results/{board}/form")]),e("td",null,"Wallet unless sandbox")]),e("tr",null,[e("td",null,"Result verification"),e("td",null,[e("code",null,"POST /results/{board}/fetch")]),e("td",null,"Wallet unless sandbox")]),e("tr",null,[e("td",null,"PayGo verification"),e("td",null,[e("code",null,"/paygo/..."),s(", "),e("code",null,"/api/paygo/...")]),e("td",null,"End-user Paystack payment")]),e("tr",null,[e("td",null,"Result PIN products"),e("td",null,[e("code",null,"GET /result-pins/products")]),e("td",null,"Free")]),e("tr",null,[e("td",null,"Result PIN purchase"),e("td",null,[e("code",null,"POST /result-pins/purchase")]),e("td",null,"Wallet")]),e("tr",null,[e("td",null,"History"),e("td",null,[e("code",null,"GET /verifications"),s(", "),e("code",null,"GET /verifications/{reference}")]),e("td",null,"Free")])],-1)])]),_:1})],512),[[f,c.value==="overview"]]),p(e("section",ce,[t[18]||(t[18]=e("h1",{class:"text-h4 font-weight-bold mb-4"},"Authentication",-1)),t[19]||(t[19]=e("p",{class:"text-body-1 text-grey-darken-1 mb-6"}," Create an API key from the customer dashboard. Copy the bearer token immediately; the secret is not shown again. ",-1)),l(m,{type:"warning",variant:"tonal",class:"mb-6"},{default:n(()=>[...t[15]||(t[15]=[s(" Never expose API keys in frontend code. Use them only from trusted backend services. ",-1)])]),_:1}),l(i,{variant:"outlined",class:"mb-4"},{default:n(()=>[l(d,{class:"d-flex align-center"},{default:n(()=>[t[16]||(t[16]=e("span",null,"Supported Headers",-1)),l(R),l(v,{size:"small",variant:"text",onClick:t[1]||(t[1]=a=>G(Re))},{default:n(()=>[s(r(I.value?"Copied!":"Copy"),1)]),_:1})]),_:1}),l(u,{class:"bg-grey-darken-4"},{default:n(()=>[...t[17]||(t[17]=[e("pre",{class:"text-white text-body-2"},"Authorization: Bearer YOUR_BEARER_TOKEN",-1),e("pre",{class:"text-white text-body-2 mt-3"},"X-API-Key: YOUR_BEARER_TOKEN",-1)])]),_:1})]),_:1}),l(O,{density:"compact"},{default:n(()=>[l(E,{title:"Live keys",subtitle:"Call real providers and charge the wallet."}),l(E,{title:"Test keys",subtitle:`Return sandbox data where supported. NIN test keys only accept ${T}.`},null,8,["subtitle"]),l(E,{title:"Rate limit",subtitle:"Default limit is enforced per minute from the API key configuration."}),l(E,{title:"IP whitelist",subtitle:"Requests from blocked IP addresses return UNAUTHORIZED."})]),_:1})],512),[[f,c.value==="authentication"]]),p(e("section",pe,[t[26]||(t[26]=e("h1",{class:"text-h4 font-weight-bold mb-4"},"Identity Verification",-1)),t[27]||(t[27]=e("p",{class:"text-body-1 text-grey-darken-1 mb-6"}," Identity endpoints verify a search value against the configured provider chain. Use the field name that matches the service you are calling. ",-1)),l(m,{type:"info",variant:"tonal",class:"mb-4"},{default:n(()=>[t[20]||(t[20]=s(" For test NIN verification, send ",-1)),e("strong",null,r(T)),t[21]||(t[21]=s(". Other NIN values are rejected for test keys. Services disabled by admin settings return ",-1)),t[22]||(t[22]=e("code",null,"SERVICE_UNAVAILABLE",-1)),t[23]||(t[23]=s(". ",-1))]),_:1}),l(y,{class:"mb-6"},{default:n(()=>[t[24]||(t[24]=e("thead",null,[e("tr",null,[e("th",null,"Service"),e("th",null,"Endpoint"),e("th",null,"Body")])],-1)),e("tbody",null,[(x(),_(P,null,w(N,a=>e("tr",{key:a.endpoint},[e("td",null,r(a.name),1),e("td",null,[e("code",null,r(a.endpoint),1)]),e("td",null,[e("code",null,'{ "'+r(a.field)+'": "'+r(a.searchValue)+'", "consent": true }',1)])])),64))])]),_:1}),(x(),_(P,null,w(N,a=>l(i,{key:a.name,variant:"outlined",class:"mb-4"},{default:n(()=>[l(d,null,{default:n(()=>[s(r(a.name)+" Request",1)]),_:2},1024),l(u,{class:"bg-grey-darken-4"},{default:n(()=>[e("pre",fe,r(a.request),1)]),_:2},1024)]),_:2},1024)),64)),l(i,{variant:"outlined"},{default:n(()=>[l(d,null,{default:n(()=>[...t[25]||(t[25]=[s("Success Response",-1)])]),_:1}),l(u,{class:"bg-grey-darken-4"},{default:n(()=>[e("pre",{class:"text-blue-lighten-1 text-body-2",style:{"white-space":"pre-wrap"}},r(Te))]),_:1})]),_:1})],512),[[f,c.value==="identity"]]),p(e("section",me,[t[34]||(t[34]=e("h1",{class:"text-h4 font-weight-bold mb-4"},"Result Verification",-1)),t[35]||(t[35]=e("p",{class:"text-body-1 text-grey-darken-1 mb-6"}," First call the board form endpoint to get field definitions and option values. Then send the exact field names to the board fetch endpoint. ",-1)),l(m,{type:"warning",variant:"tonal",class:"mb-6"},{default:n(()=>[...t[28]||(t[28]=[s(" Result checker PINs, serials, and tokens can be consumed by the board provider. Submit only when the customer has authorized the lookup. ",-1)])]),_:1}),l(m,{type:"info",variant:"tonal",class:"mb-6"},{default:n(()=>[...t[29]||(t[29]=[s(" WAEC verification now requires only the examination number, year, examination type, and purchased PIN. A card serial number is not required. Use ",-1),e("code",null,"MAY/JUN",-1),s(" for school candidates and ",-1),e("code",null,"NOV/DEC",-1),s(" for private candidates. ",-1)])]),_:1}),(x(),_(P,null,w(D,a=>l(i,{key:a.board,variant:"outlined",class:"mb-5"},{default:n(()=>[l(d,null,{default:n(()=>[s(r(a.board),1)]),_:2},1024),l(u,null,{default:n(()=>[e("div",ye,[l(A,{size:"small",color:"info"},{default:n(()=>[...t[30]||(t[30]=[s("FORM",-1)])]),_:1}),e("code",be,r(a.form),1)]),e("div",ve,[l(A,{size:"small",color:"success"},{default:n(()=>[...t[31]||(t[31]=[s("FETCH",-1)])]),_:1}),e("code",ge,r(a.fetch),1)]),e("p",he,[t[32]||(t[32]=s("Required fields: ",-1)),e("code",null,r(a.fields.join(", ")),1)]),e("pre",Ee,r(a.sample),1)]),_:2},1024)]),_:2},1024)),64)),l(i,{variant:"outlined"},{default:n(()=>[l(d,null,{default:n(()=>[...t[33]||(t[33]=[s("Result Response",-1)])]),_:1}),l(u,{class:"bg-grey-darken-4"},{default:n(()=>[e("pre",{class:"text-blue-lighten-1 text-body-2",style:{"white-space":"pre-wrap"}},r(Ne))]),_:1})]),_:1})],512),[[f,c.value==="result-verification"]]),p(e("section",_e,[t[44]||(t[44]=e("h1",{class:"text-h4 font-weight-bold mb-4"},"PayGo Verification",-1)),t[45]||(t[45]=e("p",{class:"text-body-1 text-grey-darken-1 mb-6"}," PayGo lets an end user pay for a verification through a public Paystack checkout. It does not use an API bearer token and does not debit your API wallet. Create and price the PayGo service in the customer dashboard, then use the generated public URLs. ",-1)),l(m,{type:"info",variant:"tonal",class:"mb-6"},{default:n(()=>[...t[36]||(t[36]=[s(" PayGo payment references are valid for seven days from confirmed payment. Keep result references private because the public pull URL uses the reference as its access credential. ",-1)])]),_:1}),l(y,{class:"mb-6"},{default:n(()=>[...t[37]||(t[37]=[e("thead",null,[e("tr",null,[e("th",null,"Flow"),e("th",null,"Endpoint"),e("th",null,"Purpose")])],-1),e("tbody",null,[e("tr",null,[e("td",null,"NIN checkout"),e("td",null,[e("code",null,"GET|POST /paygo/{publicSlug}/initiate/{nin?}")]),e("td",null,"Collect NIN details and initialize Paystack.")]),e("tr",null,[e("td",null,"NIN result"),e("td",null,[e("code",null,"GET|POST /api/paygo/{publicSlug}/verify/{nin?}")]),e("td",null,"Use the paid NIN reference with its three-attempt allowance.")]),e("tr",null,[e("td",null,"Result selector"),e("td",null,[e("code",null,"GET /paygo/results/customer/{referralCode}")]),e("td",null,"Let the payer choose an enabled examination board.")]),e("tr",null,[e("td",null,"Board checkout"),e("td",null,[e("code",null,"GET|POST /paygo/results/{publicSlug}")]),e("td",null,"Collect board fields, initialize payment, and fetch the result.")]),e("tr",null,[e("td",null,"Result pull"),e("td",null,[e("code",null,"GET /api/paygo/results/{reference}")]),e("td",null,"Return the stored verified result; limited to 30 requests per minute.")])],-1)])]),_:1}),l(i,{variant:"outlined",class:"mb-5"},{default:n(()=>[l(d,null,{default:n(()=>[...t[38]||(t[38]=[s("NIN PayGo Request",-1)])]),_:1}),l(u,null,{default:n(()=>[t[39]||(t[39]=e("p",{class:"text-body-2 mb-3"},"Request JSON explicitly to receive the Paystack checkout URL, then call the verify endpoint after payment. No bearer token is used.",-1)),e("pre",{class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded text-body-2",style:{"white-space":"pre-wrap"}},'curl -X POST "'+r(g)+`/paygo/YOUR_PUBLIC_SLUG/initiate?response=json" \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"nin":"12345678901","email":"payer@example.com"}'

curl -X POST "`+r(g)+`/api/paygo/YOUR_PUBLIC_SLUG/verify" \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"reference":"PGO-REFERENCE","nin":"12345678901","consent":true}'`)]),_:1})]),_:1}),t[46]||(t[46]=e("h2",{class:"text-h6 font-weight-bold mb-3"},"Portal context",-1)),t[47]||(t[47]=e("p",{class:"text-body-2 text-grey-darken-1 mb-4"},[s(" Append "),e("code",null,"candidate_id"),s(", "),e("code",null,"portal_ref"),s(", "),e("code",null,"state"),s(", and "),e("code",null,"sitting"),s(" to the selector or board URL. EaseVerifier returns this context on redirect and webhook callbacks. Supplying your own unique "),e("code",null,"reference"),s(" creates a reference package that can complete two successful result attempts during its seven-day validity window. ")],-1)),t[48]||(t[48]=e("p",{class:"text-body-2 text-grey-darken-1 mb-4"}," Standard result payments use the pull limit captured when payment starts, and each successful API pull consumes one. Reference packages count successful result attempts instead, so polling an already completed attempt does not consume another attempt. ",-1)),l(y,{class:"mb-6"},{default:n(()=>[...t[40]||(t[40]=[e("thead",null,[e("tr",null,[e("th",null,"Callback mode"),e("th",null,"Behavior"),e("th",null,"Required setting")])],-1),e("tbody",null,[e("tr",null,[e("td",null,[e("code",null,"redirect")]),e("td",null,"A successful result returns to the success URL with status and portal context. Result errors remain on the EaseVerifier form so the payer can correct them."),e("td",null,"Success and failure URLs")]),e("tr",null,[e("td",null,[e("code",null,"webhook")]),e("td",null,"EaseVerifier posts the completed or failed result to your backend."),e("td",null,"Customer webhook URL")]),e("tr",null,[e("td",null,[e("code",null,"hybrid")]),e("td",null,"Sends the webhook and redirects the browser."),e("td",null,"All callback URLs")])],-1)])]),_:1}),l(i,{variant:"outlined",class:"mb-5"},{default:n(()=>[l(d,null,{default:n(()=>[...t[41]||(t[41]=[s("Result Pull",-1)])]),_:1}),l(u,null,{default:n(()=>[e("pre",{class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded text-body-2",style:{"white-space":"pre-wrap"}},`curl -H "Accept: application/json" \\
  "`+r(g)+'/api/paygo/results/APPLICATION-90210?portal_ref=APPLICATION-90210&sitting=1"'),e("pre",{class:"bg-grey-darken-4 text-blue-lighten-1 pa-4 rounded text-body-2 mt-4",style:{"white-space":"pre-wrap"}},r(Oe))]),_:1})]),_:1}),l(i,{variant:"outlined"},{default:n(()=>[l(d,null,{default:n(()=>[...t[42]||(t[42]=[s("Webhook Payload",-1)])]),_:1}),l(u,null,{default:n(()=>[t[43]||(t[43]=e("p",{class:"text-body-2 mb-3"},[s("Webhook requests include "),e("code",null,"X-EaseVerifier-Event"),s(", "),e("code",null,"X-EaseVerifier-Reference"),s(", and "),e("code",null,"X-EaseVerifier-Signature"),s(". A failed result uses the "),e("code",null,"paygo.result.failed"),s(" event with "),e("code",null,"result_status: failed"),s(".")],-1)),e("pre",{class:"bg-grey-darken-4 text-blue-lighten-1 pa-4 rounded text-body-2",style:{"white-space":"pre-wrap"}},r(Ce))]),_:1})]),_:1})],512),[[f,c.value==="paygo"]]),p(e("section",xe,[t[54]||(t[54]=e("h1",{class:"text-h4 font-weight-bold mb-4"},"Practical Implementation",-1)),t[55]||(t[55]=e("p",{class:"text-body-1 text-grey-darken-1 mb-6"}," Keep bearer tokens and webhook secrets on your server. The direct API example below charges your wallet; the PayGo example sends the payer through checkout and retrieves the stored result afterward. ",-1)),l(Q,{class:"mb-6"}),l(m,{type:"warning",variant:"tonal",class:"mb-6"},{default:n(()=>[...t[49]||(t[49]=[s(" The result form endpoint and fetch endpoint are billed independently in live mode. Cache form field metadata on your server and refresh it only when needed. ",-1)])]),_:1}),l(i,{variant:"outlined",class:"mb-5"},{default:n(()=>[l(d,null,{default:n(()=>[...t[50]||(t[50]=[s("Direct WAEC API - Server-side JavaScript",-1)])]),_:1}),l(u,{class:"bg-grey-darken-4"},{default:n(()=>[e("pre",{class:"text-green-lighten-1 text-body-2",style:{"white-space":"pre-wrap"}},r(q))]),_:1})]),_:1}),l(i,{variant:"outlined",class:"mb-5"},{default:n(()=>[l(d,null,{default:n(()=>[...t[51]||(t[51]=[s("School Portal PayGo Flow",-1)])]),_:1}),l(u,{class:"bg-grey-darken-4"},{default:n(()=>[e("pre",{class:"text-green-lighten-1 text-body-2",style:{"white-space":"pre-wrap"}},r(H))]),_:1})]),_:1}),l(i,{variant:"outlined"},{default:n(()=>[l(d,null,{default:n(()=>[...t[52]||(t[52]=[s("Verify the PayGo Webhook",-1)])]),_:1}),l(u,null,{default:n(()=>[t[53]||(t[53]=e("p",{class:"text-body-2 mb-3"},[s("Compute HMAC-SHA256 with the PayGo webhook secret and compare it with "),e("code",null,"X-EaseVerifier-Signature"),s(" using a timing-safe comparison. Also verify your signed "),e("code",null,"state"),s(" before updating a candidate record.")],-1)),e("pre",{class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded text-body-2",style:{"white-space":"pre-wrap"}},r(Se))]),_:1})]),_:1})],512),[[f,c.value==="implementation"]]),p(e("section",Pe,[t[64]||(t[64]=e("h1",{class:"text-h4 font-weight-bold mb-4"},"Result PINs",-1)),t[65]||(t[65]=e("p",{class:"text-body-1 text-grey-darken-1 mb-6"}," Use these endpoints to list available result checker PIN products and purchase PINs from wallet balance. ",-1)),l(i,{variant:"outlined",class:"mb-4"},{default:n(()=>[l(d,null,{default:n(()=>[l(A,{size:"small",color:"info",class:"mr-2"},{default:n(()=>[...t[56]||(t[56]=[s("GET",-1)])]),_:1}),t[57]||(t[57]=s("/result-pins/products",-1))]),_:1}),l(u,null,{default:n(()=>[...t[58]||(t[58]=[e("p",{class:"text-body-2"},[s("Returns active products with "),e("code",null,"id"),s(", "),e("code",null,"card_type_id"),s(", "),e("code",null,"price"),s(", "),e("code",null,"min_quantity"),s(", and "),e("code",null,"max_quantity"),s(".")],-1)])]),_:1})]),_:1}),l(i,{variant:"outlined",class:"mb-4"},{default:n(()=>[l(d,null,{default:n(()=>[l(A,{size:"small",color:"success",class:"mr-2"},{default:n(()=>[...t[59]||(t[59]=[s("POST",-1)])]),_:1}),t[60]||(t[60]=s("/result-pins/purchase",-1))]),_:1}),l(u,null,{default:n(()=>[...t[61]||(t[61]=[e("p",{class:"text-body-2"},[s("Send either "),e("code",null,"product_id"),s(" or "),e("code",null,"card_type_id"),s(", plus "),e("code",null,"quantity"),s(".")],-1),e("pre",{class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded text-body-2",style:{"white-space":"pre-wrap"}},`{
  "product_id": 3,
  "quantity": 1
}`,-1)])]),_:1})]),_:1}),l(i,{variant:"outlined"},{default:n(()=>[l(d,null,{default:n(()=>[...t[62]||(t[62]=[s("Purchase Response",-1)])]),_:1}),l(u,{class:"bg-grey-darken-4"},{default:n(()=>[...t[63]||(t[63]=[e("pre",{class:"text-blue-lighten-1 text-body-2",style:{"white-space":"pre-wrap"}},`{
  "success": true,
  "data": {
    "reference": "PIN-XXXXXXXXXX-1782400000",
    "quantity": 1,
    "status": "completed",
    "pins": [
      { "pin": "123456789012", "serial_no": "NER100000000" }
    ]
  }
}`,-1)])]),_:1})]),_:1})],512),[[f,c.value==="result-pins"]]),p(e("section",Ae,[t[69]||(t[69]=e("h1",{class:"text-h4 font-weight-bold mb-4"},"Wallet, Services, and History",-1)),l(y,{class:"mb-6"},{default:n(()=>[...t[66]||(t[66]=[e("thead",null,[e("tr",null,[e("th",null,"Endpoint"),e("th",null,"Description"),e("th",null,"Query")])],-1),e("tbody",null,[e("tr",null,[e("td",null,[e("code",null,"GET /wallet/balance")]),e("td",null,"Current wallet or branch wallet balance."),e("td",null,"None")]),e("tr",null,[e("td",null,[e("code",null,"GET /services")]),e("td",null,"Active services with customer pricing and currency."),e("td",null,"None")]),e("tr",null,[e("td",null,[e("code",null,"GET /verifications")]),e("td",null,"Paginated verification history."),e("td",null,[e("code",null,"service"),s(", "),e("code",null,"status"),s(", "),e("code",null,"per_page")])]),e("tr",null,[e("td",null,[e("code",null,"GET /verifications/{reference}")]),e("td",null,"Single verification request by reference."),e("td",null,"None")])],-1)])]),_:1}),l(i,{variant:"outlined"},{default:n(()=>[l(d,null,{default:n(()=>[...t[67]||(t[67]=[s("Wallet Response",-1)])]),_:1}),l(u,{class:"bg-grey-darken-4"},{default:n(()=>[...t[68]||(t[68]=[e("pre",{class:"text-blue-lighten-1 text-body-2",style:{"white-space":"pre-wrap"}},`{
  "success": true,
  "data": {
    "balance": "10000.00",
    "bonus_balance": "500.00",
    "total_balance": "10500.00",
    "currency": "NGN"
  }
}`,-1)])]),_:1})]),_:1})],512),[[f,c.value==="wallet-history"]]),p(e("section",we,[t[80]||(t[80]=e("h1",{class:"text-h4 font-weight-bold mb-4"},"MCP Server",-1)),t[81]||(t[81]=e("p",{class:"text-body-1 text-grey-darken-1 mb-6"}," Connect an MCP client to the Streamable HTTP endpoint to use EaseVerifier services as focused AI tools. MCP access uses OAuth 2.1 account linking; customer API keys remain dedicated to the REST API. ",-1)),l(y,{class:"mb-6"},{default:n(()=>[t[75]||(t[75]=e("thead",null,[e("tr",null,[e("th",null,"Setting"),e("th",null,"Value")])],-1)),e("tbody",null,[e("tr",null,[t[70]||(t[70]=e("td",null,"Endpoint",-1)),e("td",null,[e("code",null,r(B))])]),t[72]||(t[72]=e("tr",null,[e("td",null,"Transport"),e("td",null,[e("code",null,"streamable-http")])],-1)),t[73]||(t[73]=e("tr",null,[e("td",null,"Authentication"),e("td",null,[s("OAuth 2.1 authorization code flow with PKCE ("),e("code",null,"S256"),s(")")])],-1)),e("tr",null,[t[71]||(t[71]=e("td",null,"Protected resource metadata",-1)),e("td",null,[e("code",null,r(g)+"/.well-known/oauth-protected-resource/api/mcp")])]),t[74]||(t[74]=e("tr",null,[e("td",null,"Protocol"),e("td",null,"MCP 2025-06-18 and compatible earlier versions")],-1))])]),_:1}),l(m,{type:"info",variant:"tonal",class:"mb-6"},{default:n(()=>[...t[76]||(t[76]=[s(" ChatGPT and compatible MCP clients discover OAuth automatically from the endpoint. Sign in to EaseVerifier, review the requested access, and select ",-1),e("strong",null,"Connect account",-1),s(". Do not place an API key in the plugin configuration. ",-1)])]),_:1}),l(i,{variant:"outlined",class:"mb-6"},{default:n(()=>[l(d,null,{default:n(()=>[...t[77]||(t[77]=[s("Agent Plugins configuration",-1)])]),_:1}),l(u,{class:"bg-grey-darken-4"},{default:n(()=>[e("pre",{class:"text-green-lighten-1 text-body-2",style:{"white-space":"pre-wrap"}},r(L))]),_:1})]),_:1}),l(y,{class:"mb-6"},{default:n(()=>[...t[78]||(t[78]=[e("thead",null,[e("tr",null,[e("th",null,"Tools"),e("th",null,"Behavior")])],-1),e("tbody",null,[e("tr",null,[e("td",null,[e("code",null,"list_services"),s(", "),e("code",null,"get_wallet_balance")]),e("td",null,"Discover services, customer prices, currency, and wallet balance.")]),e("tr",null,[e("td",null,[e("code",null,"list_verifications"),s(", "),e("code",null,"get_verification")]),e("td",null,"Read verification history scoped to the connected customer or branch.")]),e("tr",null,[e("td",null,[e("code",null,"verify_identity")]),e("td",null,"Run any active identity or business verification service.")]),e("tr",null,[e("td",null,[e("code",null,"get_result_requirements"),s(", "),e("code",null,"verify_result"),s(", "),e("code",null,"list_nbais_schools")]),e("td",null,"Verify WAEC, NECO, NBAIS, and NABTEB results.")]),e("tr",null,[e("td",null,[e("code",null,"list_result_pin_products"),s(", "),e("code",null,"purchase_result_pins")]),e("td",null,"List current prices and purchase result checker PINs.")])],-1)])]),_:1}),l(m,{type:"warning",variant:"tonal"},{default:n(()=>[...t[79]||(t[79]=[s(" Verification, result form, result fetch, and PIN purchase tools may charge the wallet. MCP clients should request explicit user confirmation immediately before invoking them and must not retry an ambiguous chargeable call. ",-1)])]),_:1})],512),[[f,c.value==="mcp"]]),p(e("section",Ie,[t[84]||(t[84]=e("h1",{class:"text-h4 font-weight-bold mb-4"},"Errors",-1)),t[85]||(t[85]=e("p",{class:"text-body-1 text-grey-darken-1 mb-6"},[s("Failed requests return JSON with "),e("code",null,"success: false"),s(", a human-readable "),e("code",null,"error"),s(", and machine-readable "),e("code",null,"error_code"),s(".")],-1)),l(i,{variant:"outlined",class:"mb-6"},{default:n(()=>[l(d,null,{default:n(()=>[...t[82]||(t[82]=[s("Error Response",-1)])]),_:1}),l(u,{class:"bg-grey-darken-4"},{default:n(()=>[e("pre",{class:"text-red-lighten-2 text-body-2",style:{"white-space":"pre-wrap"}},r(ke))]),_:1})]),_:1}),l(y,null,{default:n(()=>[...t[83]||(t[83]=[e("thead",null,[e("tr",null,[e("th",null,"HTTP"),e("th",null,"Error Code"),e("th",null,"Meaning")])],-1),e("tbody",null,[e("tr",null,[e("td",null,[e("code",null,"400")]),e("td",null,[e("code",null,"SERVICE_UNAVAILABLE"),s(", "),e("code",null,"PIN_PURCHASE_FAILED"),s(", "),e("code",null,"RESULT_REFERENCE_INVALID"),s(", "),e("code",null,"UNKNOWN_ERROR")]),e("td",null,"Request was understood but could not be completed.")]),e("tr",null,[e("td",null,[e("code",null,"401")]),e("td",null,[e("code",null,"UNAUTHORIZED")]),e("td",null,"Missing, invalid, inactive, or IP-blocked API key.")]),e("tr",null,[e("td",null,[e("code",null,"402")]),e("td",null,[e("code",null,"INSUFFICIENT_FUNDS")]),e("td",null,"Wallet balance is too low.")]),e("tr",null,[e("td",null,[e("code",null,"404")]),e("td",null,[e("code",null,"NOT_FOUND"),s(", "),e("code",null,"PRODUCT_UNAVAILABLE"),s(", "),e("code",null,"UNSUPPORTED_RESULT_BOARD")]),e("td",null,"Requested record, product, or board was not found.")]),e("tr",null,[e("td",null,[e("code",null,"422")]),e("td",null,[e("code",null,"VALIDATION_ERROR"),s(", "),e("code",null,"TEST_NIN_REQUIRED")]),e("td",null,"Required fields are missing or invalid.")]),e("tr",null,[e("td",null,[e("code",null,"429")]),e("td",null,[e("code",null,"RATE_LIMIT_EXCEEDED"),s(", "),e("code",null,"PULL_LIMIT_EXCEEDED")]),e("td",null,"The API key or PayGo reference exceeded its configured limit.")])],-1)])]),_:1})],512),[[f,c.value==="errors"]])])]),_:1})]),_:1})]),_:1})]),_:1})]),_:1})],64)}}});export{qe as default};
