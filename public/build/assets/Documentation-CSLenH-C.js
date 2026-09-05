import{_ as k}from"./CustomerLayout.vue_vue_type_script_setup_true_lang-DWpQgD3A.js";import{d as U,m as C,r as s,c as _,o as E,a as t,u as D,h as V,w as n,b as l,e as u,t as a,F as x,f as N}from"./app-Djg90bXG.js";import"./index-Bhb5BNBb.js";const F={class:"mb-6"},G={class:"mb-2"},q={class:"mb-3"},H={class:"text-body-2 mb-2"},L={class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto"},j={class:"bg-grey-darken-4 text-white pa-4 rounded overflow-x-auto"},y="https://verify.ashlabtech.ng/api/v1",R="11111111111",z=`{
  "success": true,
  "status": 200,
  "data": {
    "first_name": "John",
    "last_name": "Doe"
  },
  "response_time": 1240,
  "message": "NIN Verified Successfully",
  "sandbox": false
}`,W=`{
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
}`,$=`{
  "success": false,
  "error": "Insufficient wallet balance",
  "error_code": "INSUFFICIENT_FUNDS"
}`,X=U({__name:"Documentation",props:{user:{}},setup(w){const f=C("overview"),g=[{board:"WAEC",form:"GET /results/waec/form",fetch:"POST /results/waec/fetch",fields:["txtExamNumber","ExamYear","ExamType","txtPIN","txtCardSerialNo"],sample:`{
  "txtExamNumber": "1234567890",
  "ExamYear": "2024",
  "ExamType": "MAY/JUN",
  "txtPIN": "123456789012",
  "txtCardSerialNo": "WRN123456789"
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
}`}],A={curl:`curl -X POST ${y}/verify/nin \\
  -H "Authorization: Bearer YOUR_BEARER_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{"nin":"${R}","consent":true}'`,javascript:`const response = await fetch('${y}/results/nabteb/fetch', {
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

$data = json_decode($response->getBody(), true);`};return(K,e)=>{const h=s("v-btn"),i=s("v-tab"),I=s("v-tabs"),m=s("v-alert"),c=s("v-table"),d=s("v-card-text"),r=s("v-card"),p=s("v-window-item"),b=s("v-card-title"),v=s("v-chip"),S=s("v-expansion-panel-text"),O=s("v-expansion-panel"),P=s("v-expansion-panels"),B=s("v-window");return E(),_(x,null,[t(D(V),{title:"API Documentation - EaseVerifier"}),t(k,{user:w.user},{default:n(()=>[l("div",F,[t(h,{variant:"text","prepend-icon":"mdi-arrow-left",href:"/customer/api",class:"mb-2"},{default:n(()=>[...e[2]||(e[2]=[u("Back to API Keys",-1)])]),_:1}),e[3]||(e[3]=l("h1",{class:"text-h4 font-weight-bold mb-1"},"API Documentation",-1)),e[4]||(e[4]=l("p",{class:"text-body-2 text-grey"},"Integrate identity verification, result checks, result PIN purchases, wallet balance, and history.",-1))]),t(I,{modelValue:f.value,"onUpdate:modelValue":e[0]||(e[0]=o=>f.value=o),color:"primary",class:"mb-6"},{default:n(()=>[t(i,{value:"overview"},{default:n(()=>[...e[5]||(e[5]=[u("Overview",-1)])]),_:1}),t(i,{value:"authentication"},{default:n(()=>[...e[6]||(e[6]=[u("Authentication",-1)])]),_:1}),t(i,{value:"identity"},{default:n(()=>[...e[7]||(e[7]=[u("Identity",-1)])]),_:1}),t(i,{value:"results"},{default:n(()=>[...e[8]||(e[8]=[u("Results",-1)])]),_:1}),t(i,{value:"pins"},{default:n(()=>[...e[9]||(e[9]=[u("Result PINs",-1)])]),_:1}),t(i,{value:"wallet"},{default:n(()=>[...e[10]||(e[10]=[u("Wallet",-1)])]),_:1}),t(i,{value:"examples"},{default:n(()=>[...e[11]||(e[11]=[u("Examples",-1)])]),_:1}),t(i,{value:"errors"},{default:n(()=>[...e[12]||(e[12]=[u("Errors",-1)])]),_:1})]),_:1},8,["modelValue"]),t(B,{modelValue:f.value,"onUpdate:modelValue":e[1]||(e[1]=o=>f.value=o)},{default:n(()=>[t(p,{value:"overview"},{default:n(()=>[t(r,null,{default:n(()=>[t(d,{class:"pa-6"},{default:n(()=>[e[16]||(e[16]=l("h2",{class:"text-h5 font-weight-bold mb-4"},"Getting Started",-1)),e[17]||(e[17]=l("p",{class:"text-body-1 mb-4"}," The EaseVerifier API is wallet-funded. API keys can be scoped to your main account or a branch, and branch-scoped keys automatically charge that branch wallet and return branch-specific history. ",-1)),t(m,{type:"info",variant:"tonal",class:"mb-4"},{default:n(()=>[e[13]||(e[13]=l("strong",null,"Base URL:",-1)),e[14]||(e[14]=u()),l("code",null,a(y))]),_:1}),t(c,null,{default:n(()=>[...e[15]||(e[15]=[l("thead",null,[l("tr",null,[l("th",null,"Service"),l("th",null,"Endpoint"),l("th",null,"Billing")])],-1),l("tbody",null,[l("tr",null,[l("td",null,"Service list"),l("td",null,[l("code",null,"GET /services")]),l("td",null,"Free")]),l("tr",null,[l("td",null,"Wallet balance"),l("td",null,[l("code",null,"GET /wallet/balance")]),l("td",null,"Free")]),l("tr",null,[l("td",null,"Identity verification"),l("td",null,[l("code",null,"POST /verify/nin"),u(", "),l("code",null,"/verify/bvn"),u(", "),l("code",null,"/verify/{service}")]),l("td",null,"Wallet")]),l("tr",null,[l("td",null,"Result form metadata"),l("td",null,[l("code",null,"GET /results/{board}/form")]),l("td",null,"Wallet unless sandbox")]),l("tr",null,[l("td",null,"Result verification"),l("td",null,[l("code",null,"POST /results/{board}/fetch")]),l("td",null,"Wallet unless sandbox")]),l("tr",null,[l("td",null,"Result PIN products"),l("td",null,[l("code",null,"GET /result-pins/products")]),l("td",null,"Free")]),l("tr",null,[l("td",null,"Result PIN purchase"),l("td",null,[l("code",null,"POST /result-pins/purchase")]),l("td",null,"Wallet")]),l("tr",null,[l("td",null,"History"),l("td",null,[l("code",null,"GET /verifications"),u(", "),l("code",null,"GET /verifications/{reference}")]),l("td",null,"Free")])],-1)])]),_:1})]),_:1})]),_:1})]),_:1}),t(p,{value:"authentication"},{default:n(()=>[t(r,null,{default:n(()=>[t(d,{class:"pa-6"},{default:n(()=>[e[22]||(e[22]=l("h2",{class:"text-h5 font-weight-bold mb-4"},"Authentication",-1)),e[23]||(e[23]=l("p",{class:"text-body-1 mb-4"},[u("Send your generated token on every request. The bearer token and "),l("code",null,"X-API-Key"),u(" header are both supported.")],-1)),t(m,{type:"warning",variant:"tonal",class:"mb-4"},{default:n(()=>[...e[18]||(e[18]=[u(" Copy the token when the key is created. The full secret is not shown again. ",-1)])]),_:1}),t(c,{class:"mb-4"},{default:n(()=>[...e[19]||(e[19]=[l("thead",null,[l("tr",null,[l("th",null,"Header"),l("th",null,"Value"),l("th",null,"Description")])],-1),l("tbody",null,[l("tr",null,[l("td",null,[l("code",null,"Authorization")]),l("td",null,[l("code",null,"Bearer YOUR_BEARER_TOKEN")]),l("td",null,"Preferred authentication header")]),l("tr",null,[l("td",null,[l("code",null,"X-API-Key")]),l("td",null,[l("code",null,"YOUR_BEARER_TOKEN")]),l("td",null,"Alternative key header")]),l("tr",null,[l("td",null,[l("code",null,"Content-Type")]),l("td",null,[l("code",null,"application/json")]),l("td",null,"Required for POST requests")])],-1)])]),_:1}),t(m,{type:"info",variant:"tonal"},{default:n(()=>[e[20]||(e[20]=u(" Test NIN calls only accept ",-1)),l("strong",null,a(R)),e[21]||(e[21]=u(". Live keys call real providers and deduct wallet balance. ",-1))]),_:1})]),_:1})]),_:1})]),_:1}),t(p,{value:"identity"},{default:n(()=>[t(r,null,{default:n(()=>[t(d,{class:"pa-6"},{default:n(()=>[e[25]||(e[25]=l("h2",{class:"text-h5 font-weight-bold mb-4"},"Identity Verification",-1)),e[26]||(e[26]=l("p",{class:"text-body-1 mb-4"},[u(" Identity endpoints currently expect the search value in the "),l("code",null,"nin"),u(" field, including BVN and generic service calls. ")],-1)),t(c,{class:"mb-6"},{default:n(()=>[...e[24]||(e[24]=[l("thead",null,[l("tr",null,[l("th",null,"Endpoint"),l("th",null,"Body")])],-1),l("tbody",null,[l("tr",null,[l("td",null,[l("code",null,"POST /verify/nin")]),l("td",null,[l("code",null,'{ "nin": "11111111111", "consent": true }')])]),l("tr",null,[l("td",null,[l("code",null,"POST /verify/bvn")]),l("td",null,[l("code",null,'{ "nin": "BVN_OR_SEARCH_VALUE", "consent": true }')])]),l("tr",null,[l("td",null,[l("code",null,"POST /verify/{service}")]),l("td",null,[l("code",null,'{ "nin": "SEARCH_VALUE", "consent": true }')])])],-1)])]),_:1}),e[27]||(e[27]=l("h3",{class:"text-subtitle-1 font-weight-bold mb-2"},"Success Response",-1)),l("pre",{class:"bg-grey-darken-4 text-blue-lighten-1 pa-4 rounded overflow-x-auto"},a(z))]),_:1})]),_:1})]),_:1}),t(p,{value:"results"},{default:n(()=>[t(m,{type:"warning",variant:"tonal",class:"mb-4"},{default:n(()=>[...e[28]||(e[28]=[u(" Result checker PINs, serials, and tokens may be consumed by the board provider. Submit only when the customer has authorized the lookup. ",-1)])]),_:1}),(E(),_(x,null,N(g,o=>t(r,{key:o.board,class:"mb-4"},{default:n(()=>[t(b,null,{default:n(()=>[u(a(o.board),1)]),_:2},1024),t(d,null,{default:n(()=>[l("div",G,[t(v,{color:"info",size:"small",class:"mr-2"},{default:n(()=>[...e[29]||(e[29]=[u("FORM",-1)])]),_:1}),l("code",null,a(o.form),1)]),l("div",q,[t(v,{color:"success",size:"small",class:"mr-2"},{default:n(()=>[...e[30]||(e[30]=[u("FETCH",-1)])]),_:1}),l("code",null,a(o.fetch),1)]),l("p",H,[e[31]||(e[31]=u("Required fields: ",-1)),l("code",null,a(o.fields.join(", ")),1)]),l("pre",L,a(o.sample),1)]),_:2},1024)]),_:2},1024)),64)),t(r,null,{default:n(()=>[t(b,null,{default:n(()=>[...e[32]||(e[32]=[u("Result Response",-1)])]),_:1}),t(d,null,{default:n(()=>[l("pre",{class:"bg-grey-darken-4 text-blue-lighten-1 pa-4 rounded overflow-x-auto"},a(W))]),_:1})]),_:1})]),_:1}),t(p,{value:"pins"},{default:n(()=>[t(r,{class:"mb-4"},{default:n(()=>[t(b,null,{default:n(()=>[t(v,{color:"info",size:"small",class:"mr-2"},{default:n(()=>[...e[33]||(e[33]=[u("GET",-1)])]),_:1}),e[34]||(e[34]=u("/result-pins/products",-1))]),_:1}),t(d,null,{default:n(()=>[...e[35]||(e[35]=[u("Returns active products with ",-1),l("code",null,"id",-1),u(", ",-1),l("code",null,"card_type_id",-1),u(", ",-1),l("code",null,"price",-1),u(", ",-1),l("code",null,"min_quantity",-1),u(", and ",-1),l("code",null,"max_quantity",-1),u(".",-1)])]),_:1})]),_:1}),t(r,null,{default:n(()=>[t(b,null,{default:n(()=>[t(v,{color:"success",size:"small",class:"mr-2"},{default:n(()=>[...e[36]||(e[36]=[u("POST",-1)])]),_:1}),e[37]||(e[37]=u("/result-pins/purchase",-1))]),_:1}),t(d,null,{default:n(()=>[...e[38]||(e[38]=[l("p",{class:"text-body-2 mb-3"},[u("Send either "),l("code",null,"product_id"),u(" or "),l("code",null,"card_type_id"),u(", plus "),l("code",null,"quantity"),u(". Purchases deduct from wallet balance.")],-1),l("pre",{class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto"},`{
  "product_id": 3,
  "quantity": 1
}`,-1)])]),_:1})]),_:1})]),_:1}),t(p,{value:"wallet"},{default:n(()=>[t(r,null,{default:n(()=>[t(d,{class:"pa-6"},{default:n(()=>[e[40]||(e[40]=l("h2",{class:"text-h5 font-weight-bold mb-4"},"Wallet, Services, and History",-1)),t(c,null,{default:n(()=>[...e[39]||(e[39]=[l("thead",null,[l("tr",null,[l("th",null,"Endpoint"),l("th",null,"Description"),l("th",null,"Query")])],-1),l("tbody",null,[l("tr",null,[l("td",null,[l("code",null,"GET /wallet/balance")]),l("td",null,"Current wallet or branch wallet balance."),l("td",null,"None")]),l("tr",null,[l("td",null,[l("code",null,"GET /services")]),l("td",null,"Active verification services."),l("td",null,"None")]),l("tr",null,[l("td",null,[l("code",null,"GET /verifications")]),l("td",null,"Paginated verification history."),l("td",null,[l("code",null,"service"),u(", "),l("code",null,"status"),u(", "),l("code",null,"per_page")])]),l("tr",null,[l("td",null,[l("code",null,"GET /verifications/{reference}")]),l("td",null,"Single verification request by reference."),l("td",null,"None")])],-1)])]),_:1})]),_:1})]),_:1})]),_:1}),t(p,{value:"examples"},{default:n(()=>[t(r,null,{default:n(()=>[t(d,{class:"pa-6"},{default:n(()=>[e[41]||(e[41]=l("h2",{class:"text-h5 font-weight-bold mb-4"},"Code Examples",-1)),t(P,null,{default:n(()=>[(E(),_(x,null,N(A,(o,T)=>t(O,{key:T,title:String(T).toUpperCase()},{default:n(()=>[t(S,null,{default:n(()=>[l("pre",j,a(o),1)]),_:2},1024)]),_:2},1032,["title"])),64))]),_:1})]),_:1})]),_:1})]),_:1}),t(p,{value:"errors"},{default:n(()=>[t(r,null,{default:n(()=>[t(d,{class:"pa-6"},{default:n(()=>[e[43]||(e[43]=l("h2",{class:"text-h5 font-weight-bold mb-4"},"Errors",-1)),e[44]||(e[44]=l("p",{class:"text-body-1 mb-4"},[u("Failed requests return JSON with "),l("code",null,"success: false"),u(", a human-readable "),l("code",null,"error"),u(", and a machine-readable "),l("code",null,"error_code"),u(".")],-1)),l("pre",{class:"bg-grey-darken-4 text-red-lighten-2 pa-4 rounded overflow-x-auto mb-6"},a($)),t(c,null,{default:n(()=>[...e[42]||(e[42]=[l("thead",null,[l("tr",null,[l("th",null,"HTTP"),l("th",null,"Error Code"),l("th",null,"Meaning")])],-1),l("tbody",null,[l("tr",null,[l("td",null,[l("code",null,"400")]),l("td",null,[l("code",null,"SERVICE_UNAVAILABLE"),u(", "),l("code",null,"PIN_PURCHASE_FAILED"),u(", "),l("code",null,"UNKNOWN_ERROR")]),l("td",null,"Request was understood but could not be completed.")]),l("tr",null,[l("td",null,[l("code",null,"401")]),l("td",null,[l("code",null,"UNAUTHORIZED")]),l("td",null,"Missing, invalid, inactive, or IP-blocked API key.")]),l("tr",null,[l("td",null,[l("code",null,"402")]),l("td",null,[l("code",null,"INSUFFICIENT_FUNDS")]),l("td",null,"Wallet balance is too low.")]),l("tr",null,[l("td",null,[l("code",null,"404")]),l("td",null,[l("code",null,"NOT_FOUND"),u(", "),l("code",null,"PRODUCT_UNAVAILABLE"),u(", "),l("code",null,"UNSUPPORTED_RESULT_BOARD")]),l("td",null,"Requested record, product, or board was not found.")]),l("tr",null,[l("td",null,[l("code",null,"422")]),l("td",null,[l("code",null,"VALIDATION_ERROR"),u(", "),l("code",null,"TEST_NIN_REQUIRED")]),l("td",null,"Required fields are missing or invalid.")]),l("tr",null,[l("td",null,[l("code",null,"429")]),l("td",null,[l("code",null,"RATE_LIMIT_EXCEEDED")]),l("td",null,"API key exceeded its per-minute limit.")])],-1)])]),_:1})]),_:1})]),_:1})]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["user"])],64)}}});export{X as default};
