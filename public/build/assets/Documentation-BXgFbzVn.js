import{_ as k}from"./CustomerLayout.vue_vue_type_script_setup_true_lang-CBIzrDLG.js";import{d as C,m as U,r as a,c as y,o as _,a as t,u as D,h as F,w as n,b as e,e as u,t as o,F as E,f as N}from"./app-z7TO7xOR.js";import"./index-Bhb5BNBb.js";const G={class:"mb-6"},L={class:"mb-2"},q={class:"mb-3"},j={class:"text-body-2 mb-2"},z={class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto"},H={class:"bg-grey-darken-4 text-white pa-4 rounded overflow-x-auto"},x="https://verify.ashlabtech.ng/api/v1",T="11111111111",W=`{
  "success": true,
  "status": 200,
  "data": {
    "first_name": "John",
    "last_name": "Doe"
  },
  "response_time": 1240,
  "message": "NIN Verified Successfully",
  "sandbox": false
}`,$=`{
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
}`,K=`{
  "success": false,
  "error": "Insufficient wallet balance",
  "error_code": "INSUFFICIENT_FUNDS"
}`,Q=C({__name:"Documentation",props:{user:{}},setup(R){const m=U("overview"),g=[{name:"NIN Verification",endpoint:"POST /verify/nin",field:"nin",searchValue:"NIN"},{name:"BVN Verification",endpoint:"POST /verify/bvn",field:"bvn",searchValue:"BVN"},{name:"CAC Verification",endpoint:"POST /verify/cac",field:"rc_number",searchValue:"RC Number"},{name:"Driver's License Verification",endpoint:"POST /verify/drivers-license",field:"license_number",searchValue:"Driver's License Number"}],I=[{board:"WAEC",form:"GET /results/waec/form",fetch:"POST /results/waec/fetch",fields:["txtExamNumber","ExamYear","ExamType","txtPIN","txtCardSerialNo"],sample:`{
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
}`}],A={curl:`curl -X POST ${x}/verify/nin \\
  -H "Authorization: Bearer YOUR_BEARER_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{"nin":"${T}","consent":true}'`,javascript:`const response = await fetch('${x}/results/nabteb/fetch', {
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

$response = $client->post('${x}/result-pins/purchase', [
    'headers' => [
        'Authorization' => 'Bearer YOUR_BEARER_TOKEN',
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'product_id' => 3,
        'quantity' => 1,
    ],
]);

$data = json_decode($response->getBody(), true);`};return(Y,l)=>{const h=a("v-btn"),i=a("v-tab"),S=a("v-tabs"),f=a("v-alert"),c=a("v-table"),r=a("v-card-text"),d=a("v-card"),p=a("v-window-item"),b=a("v-card-title"),v=a("v-chip"),O=a("v-expansion-panel-text"),P=a("v-expansion-panel"),B=a("v-expansion-panels"),V=a("v-window");return _(),y(E,null,[t(D(F),{title:"API Documentation - EaseVerifier"}),t(k,{user:R.user},{default:n(()=>[e("div",G,[t(h,{variant:"text","prepend-icon":"mdi-arrow-left",href:"/customer/api",class:"mb-2"},{default:n(()=>[...l[2]||(l[2]=[u("Back to API Keys",-1)])]),_:1}),l[3]||(l[3]=e("h1",{class:"text-h4 font-weight-bold mb-1"},"API Documentation",-1)),l[4]||(l[4]=e("p",{class:"text-body-2 text-grey"},"Integrate identity verification, result checks, result PIN purchases, wallet balance, and history.",-1))]),t(S,{modelValue:m.value,"onUpdate:modelValue":l[0]||(l[0]=s=>m.value=s),color:"primary",class:"mb-6"},{default:n(()=>[t(i,{value:"overview"},{default:n(()=>[...l[5]||(l[5]=[u("Overview",-1)])]),_:1}),t(i,{value:"authentication"},{default:n(()=>[...l[6]||(l[6]=[u("Authentication",-1)])]),_:1}),t(i,{value:"identity"},{default:n(()=>[...l[7]||(l[7]=[u("Identity",-1)])]),_:1}),t(i,{value:"results"},{default:n(()=>[...l[8]||(l[8]=[u("Results",-1)])]),_:1}),t(i,{value:"pins"},{default:n(()=>[...l[9]||(l[9]=[u("Result PINs",-1)])]),_:1}),t(i,{value:"wallet"},{default:n(()=>[...l[10]||(l[10]=[u("Wallet",-1)])]),_:1}),t(i,{value:"examples"},{default:n(()=>[...l[11]||(l[11]=[u("Examples",-1)])]),_:1}),t(i,{value:"errors"},{default:n(()=>[...l[12]||(l[12]=[u("Errors",-1)])]),_:1})]),_:1},8,["modelValue"]),t(V,{modelValue:m.value,"onUpdate:modelValue":l[1]||(l[1]=s=>m.value=s)},{default:n(()=>[t(p,{value:"overview"},{default:n(()=>[t(d,null,{default:n(()=>[t(r,{class:"pa-6"},{default:n(()=>[l[16]||(l[16]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Getting Started",-1)),l[17]||(l[17]=e("p",{class:"text-body-1 mb-4"}," The EaseVerifier API is wallet-funded. API keys can be scoped to your main account or a branch, and branch-scoped keys automatically charge that branch wallet and return branch-specific history. ",-1)),t(f,{type:"info",variant:"tonal",class:"mb-4"},{default:n(()=>[l[13]||(l[13]=e("strong",null,"Base URL:",-1)),l[14]||(l[14]=u()),e("code",null,o(x))]),_:1}),t(c,null,{default:n(()=>[...l[15]||(l[15]=[e("thead",null,[e("tr",null,[e("th",null,"Service"),e("th",null,"Endpoint"),e("th",null,"Billing")])],-1),e("tbody",null,[e("tr",null,[e("td",null,"Service list"),e("td",null,[e("code",null,"GET /services")]),e("td",null,"Free")]),e("tr",null,[e("td",null,"Wallet balance"),e("td",null,[e("code",null,"GET /wallet/balance")]),e("td",null,"Free")]),e("tr",null,[e("td",null,"Identity verification"),e("td",null,[e("code",null,"POST /verify/nin"),u(", "),e("code",null,"/verify/bvn"),u(", "),e("code",null,"/verify/{service}")]),e("td",null,"Wallet")]),e("tr",null,[e("td",null,"Result form metadata"),e("td",null,[e("code",null,"GET /results/{board}/form")]),e("td",null,"Wallet unless sandbox")]),e("tr",null,[e("td",null,"Result verification"),e("td",null,[e("code",null,"POST /results/{board}/fetch")]),e("td",null,"Wallet unless sandbox")]),e("tr",null,[e("td",null,"Result PIN products"),e("td",null,[e("code",null,"GET /result-pins/products")]),e("td",null,"Free")]),e("tr",null,[e("td",null,"Result PIN purchase"),e("td",null,[e("code",null,"POST /result-pins/purchase")]),e("td",null,"Wallet")]),e("tr",null,[e("td",null,"History"),e("td",null,[e("code",null,"GET /verifications"),u(", "),e("code",null,"GET /verifications/{reference}")]),e("td",null,"Free")])],-1)])]),_:1})]),_:1})]),_:1})]),_:1}),t(p,{value:"authentication"},{default:n(()=>[t(d,null,{default:n(()=>[t(r,{class:"pa-6"},{default:n(()=>[l[22]||(l[22]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Authentication",-1)),l[23]||(l[23]=e("p",{class:"text-body-1 mb-4"},[u("Send your generated token on every request. The bearer token and "),e("code",null,"X-API-Key"),u(" header are both supported.")],-1)),t(f,{type:"warning",variant:"tonal",class:"mb-4"},{default:n(()=>[...l[18]||(l[18]=[u(" Copy the token when the key is created. The full secret is not shown again. ",-1)])]),_:1}),t(c,{class:"mb-4"},{default:n(()=>[...l[19]||(l[19]=[e("thead",null,[e("tr",null,[e("th",null,"Header"),e("th",null,"Value"),e("th",null,"Description")])],-1),e("tbody",null,[e("tr",null,[e("td",null,[e("code",null,"Authorization")]),e("td",null,[e("code",null,"Bearer YOUR_BEARER_TOKEN")]),e("td",null,"Preferred authentication header")]),e("tr",null,[e("td",null,[e("code",null,"X-API-Key")]),e("td",null,[e("code",null,"YOUR_BEARER_TOKEN")]),e("td",null,"Alternative key header")]),e("tr",null,[e("td",null,[e("code",null,"Content-Type")]),e("td",null,[e("code",null,"application/json")]),e("td",null,"Required for POST requests")])],-1)])]),_:1}),t(f,{type:"info",variant:"tonal"},{default:n(()=>[l[20]||(l[20]=u(" Test NIN calls only accept ",-1)),e("strong",null,o(T)),l[21]||(l[21]=u(". Live keys call real providers and deduct wallet balance. ",-1))]),_:1})]),_:1})]),_:1})]),_:1}),t(p,{value:"identity"},{default:n(()=>[t(d,null,{default:n(()=>[t(r,{class:"pa-6"},{default:n(()=>[l[29]||(l[29]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Identity Verification",-1)),l[30]||(l[30]=e("p",{class:"text-body-1 mb-4"}," Identity endpoints expect the search value in the field that matches the service you are calling. ",-1)),t(f,{type:"info",variant:"tonal",class:"mb-4"},{default:n(()=>[l[24]||(l[24]=u(" For test NIN verification, send ",-1)),e("strong",null,o(T)),l[25]||(l[25]=u(". Services disabled by admin settings return ",-1)),l[26]||(l[26]=e("code",null,"SERVICE_UNAVAILABLE",-1)),l[27]||(l[27]=u(". ",-1))]),_:1}),t(c,{class:"mb-6"},{default:n(()=>[l[28]||(l[28]=e("thead",null,[e("tr",null,[e("th",null,"Service"),e("th",null,"Endpoint"),e("th",null,"Body")])],-1)),e("tbody",null,[(_(),y(E,null,N(g,s=>e("tr",{key:s.endpoint},[e("td",null,o(s.name),1),e("td",null,[e("code",null,o(s.endpoint),1)]),e("td",null,[e("code",null,'{ "'+o(s.field)+'": "'+o(s.searchValue)+'", "consent": true }',1)])])),64))])]),_:1}),l[31]||(l[31]=e("h3",{class:"text-subtitle-1 font-weight-bold mb-2"},"Success Response",-1)),e("pre",{class:"bg-grey-darken-4 text-blue-lighten-1 pa-4 rounded overflow-x-auto"},o(W))]),_:1})]),_:1})]),_:1}),t(p,{value:"results"},{default:n(()=>[t(f,{type:"warning",variant:"tonal",class:"mb-4"},{default:n(()=>[...l[32]||(l[32]=[u(" Result checker PINs, serials, and tokens may be consumed by the board provider. Submit only when the customer has authorized the lookup. ",-1)])]),_:1}),(_(),y(E,null,N(I,s=>t(d,{key:s.board,class:"mb-4"},{default:n(()=>[t(b,null,{default:n(()=>[u(o(s.board),1)]),_:2},1024),t(r,null,{default:n(()=>[e("div",L,[t(v,{color:"info",size:"small",class:"mr-2"},{default:n(()=>[...l[33]||(l[33]=[u("FORM",-1)])]),_:1}),e("code",null,o(s.form),1)]),e("div",q,[t(v,{color:"success",size:"small",class:"mr-2"},{default:n(()=>[...l[34]||(l[34]=[u("FETCH",-1)])]),_:1}),e("code",null,o(s.fetch),1)]),e("p",j,[l[35]||(l[35]=u("Required fields: ",-1)),e("code",null,o(s.fields.join(", ")),1)]),e("pre",z,o(s.sample),1)]),_:2},1024)]),_:2},1024)),64)),t(d,null,{default:n(()=>[t(b,null,{default:n(()=>[...l[36]||(l[36]=[u("Result Response",-1)])]),_:1}),t(r,null,{default:n(()=>[e("pre",{class:"bg-grey-darken-4 text-blue-lighten-1 pa-4 rounded overflow-x-auto"},o($))]),_:1})]),_:1})]),_:1}),t(p,{value:"pins"},{default:n(()=>[t(d,{class:"mb-4"},{default:n(()=>[t(b,null,{default:n(()=>[t(v,{color:"info",size:"small",class:"mr-2"},{default:n(()=>[...l[37]||(l[37]=[u("GET",-1)])]),_:1}),l[38]||(l[38]=u("/result-pins/products",-1))]),_:1}),t(r,null,{default:n(()=>[...l[39]||(l[39]=[u("Returns active products with ",-1),e("code",null,"id",-1),u(", ",-1),e("code",null,"card_type_id",-1),u(", ",-1),e("code",null,"price",-1),u(", ",-1),e("code",null,"min_quantity",-1),u(", and ",-1),e("code",null,"max_quantity",-1),u(".",-1)])]),_:1})]),_:1}),t(d,null,{default:n(()=>[t(b,null,{default:n(()=>[t(v,{color:"success",size:"small",class:"mr-2"},{default:n(()=>[...l[40]||(l[40]=[u("POST",-1)])]),_:1}),l[41]||(l[41]=u("/result-pins/purchase",-1))]),_:1}),t(r,null,{default:n(()=>[...l[42]||(l[42]=[e("p",{class:"text-body-2 mb-3"},[u("Send either "),e("code",null,"product_id"),u(" or "),e("code",null,"card_type_id"),u(", plus "),e("code",null,"quantity"),u(". Purchases deduct from wallet balance.")],-1),e("pre",{class:"bg-grey-darken-4 text-green-lighten-1 pa-4 rounded overflow-x-auto"},`{
  "product_id": 3,
  "quantity": 1
}`,-1)])]),_:1})]),_:1})]),_:1}),t(p,{value:"wallet"},{default:n(()=>[t(d,null,{default:n(()=>[t(r,{class:"pa-6"},{default:n(()=>[l[44]||(l[44]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Wallet, Services, and History",-1)),t(c,null,{default:n(()=>[...l[43]||(l[43]=[e("thead",null,[e("tr",null,[e("th",null,"Endpoint"),e("th",null,"Description"),e("th",null,"Query")])],-1),e("tbody",null,[e("tr",null,[e("td",null,[e("code",null,"GET /wallet/balance")]),e("td",null,"Current wallet or branch wallet balance."),e("td",null,"None")]),e("tr",null,[e("td",null,[e("code",null,"GET /services")]),e("td",null,"Active verification services."),e("td",null,"None")]),e("tr",null,[e("td",null,[e("code",null,"GET /verifications")]),e("td",null,"Paginated verification history."),e("td",null,[e("code",null,"service"),u(", "),e("code",null,"status"),u(", "),e("code",null,"per_page")])]),e("tr",null,[e("td",null,[e("code",null,"GET /verifications/{reference}")]),e("td",null,"Single verification request by reference."),e("td",null,"None")])],-1)])]),_:1})]),_:1})]),_:1})]),_:1}),t(p,{value:"examples"},{default:n(()=>[t(d,null,{default:n(()=>[t(r,{class:"pa-6"},{default:n(()=>[l[45]||(l[45]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Code Examples",-1)),t(B,null,{default:n(()=>[(_(),y(E,null,N(A,(s,w)=>t(P,{key:w,title:String(w).toUpperCase()},{default:n(()=>[t(O,null,{default:n(()=>[e("pre",H,o(s),1)]),_:2},1024)]),_:2},1032,["title"])),64))]),_:1})]),_:1})]),_:1})]),_:1}),t(p,{value:"errors"},{default:n(()=>[t(d,null,{default:n(()=>[t(r,{class:"pa-6"},{default:n(()=>[l[47]||(l[47]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Errors",-1)),l[48]||(l[48]=e("p",{class:"text-body-1 mb-4"},[u("Failed requests return JSON with "),e("code",null,"success: false"),u(", a human-readable "),e("code",null,"error"),u(", and a machine-readable "),e("code",null,"error_code"),u(".")],-1)),e("pre",{class:"bg-grey-darken-4 text-red-lighten-2 pa-4 rounded overflow-x-auto mb-6"},o(K)),t(c,null,{default:n(()=>[...l[46]||(l[46]=[e("thead",null,[e("tr",null,[e("th",null,"HTTP"),e("th",null,"Error Code"),e("th",null,"Meaning")])],-1),e("tbody",null,[e("tr",null,[e("td",null,[e("code",null,"400")]),e("td",null,[e("code",null,"SERVICE_UNAVAILABLE"),u(", "),e("code",null,"PIN_PURCHASE_FAILED"),u(", "),e("code",null,"UNKNOWN_ERROR")]),e("td",null,"Request was understood but could not be completed.")]),e("tr",null,[e("td",null,[e("code",null,"401")]),e("td",null,[e("code",null,"UNAUTHORIZED")]),e("td",null,"Missing, invalid, inactive, or IP-blocked API key.")]),e("tr",null,[e("td",null,[e("code",null,"402")]),e("td",null,[e("code",null,"INSUFFICIENT_FUNDS")]),e("td",null,"Wallet balance is too low.")]),e("tr",null,[e("td",null,[e("code",null,"404")]),e("td",null,[e("code",null,"NOT_FOUND"),u(", "),e("code",null,"PRODUCT_UNAVAILABLE"),u(", "),e("code",null,"UNSUPPORTED_RESULT_BOARD")]),e("td",null,"Requested record, product, or board was not found.")]),e("tr",null,[e("td",null,[e("code",null,"422")]),e("td",null,[e("code",null,"VALIDATION_ERROR"),u(", "),e("code",null,"TEST_NIN_REQUIRED")]),e("td",null,"Required fields are missing or invalid.")]),e("tr",null,[e("td",null,[e("code",null,"429")]),e("td",null,[e("code",null,"RATE_LIMIT_EXCEEDED")]),e("td",null,"API key exceeded its per-minute limit.")])],-1)])]),_:1})]),_:1})]),_:1})]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["user"])],64)}}});export{Q as default};
