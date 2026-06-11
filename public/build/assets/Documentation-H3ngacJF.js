import{d as R,m as S,r as a,c as f,o as v,a as n,u as V,h as O,w as l,b as e,e as s,F as b,f as A,t as h}from"./app-dHCLC6yB.js";import{_ as Y}from"./CustomerLayout.vue_vue_type_script_setup_true_lang-CRz7gruo.js";import"./index-Zgh9O_oX.js";const B={class:"mb-6"},k=R({__name:"Documentation",props:{user:{}},setup(P){const p=S("overview"),c={curl:`curl -X POST https://verify.ashlabtech.ng/v1/verify/nin \\
  -H "Authorization: Bearer YOUR_API_KEY" \\
  -H "X-API-Secret: YOUR_API_SECRET" \\
  -H "Content-Type: application/json" \\
  -d '{"nin": "12345678901","consent": true}'`,php:`<?php
$client = new GuzzleHttp\\Client();
$response = $client->post('https://verify.ashlabtech.ng/v1/verify/nin', [
    'headers' => [
        'Authorization' => 'Bearer YOUR_API_KEY',
        'X-API-Secret' => 'YOUR_API_SECRET',
    ],
    'json' => ['nin' => '12345678901']
]);
$data = json_decode($response->getBody(), true);`,javascript:`const response = await fetch('https://verify.ashlabtech.ng/v1/verify/nin', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_API_KEY',
    'X-API-Secret': 'YOUR_API_SECRET',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({ nin: '12345678901' })
});
const data = await response.json();`,python:`import requests

response = requests.post(
    'https://verify.ashlabtech.ng/v1/verify/nin',
    headers={
        'Authorization': 'Bearer YOUR_API_KEY',
        'X-API-Secret': 'YOUR_API_SECRET'
    },
    json={'nin': '12345678901'}
)
data = response.json()`};return(C,t)=>{const w=a("v-btn"),r=a("v-tab"),_=a("v-tabs"),I=a("v-alert"),i=a("v-card-text"),u=a("v-card"),m=a("v-window-item"),x=a("v-table"),y=a("v-chip"),g=a("v-card-title"),E=a("v-window");return v(),f(b,null,[n(V(O),{title:"API Documentation - EaseVerifier"}),n(Y,{user:P.user},{default:l(()=>[e("div",B,[n(w,{variant:"text","prepend-icon":"mdi-arrow-left",href:"/customer/api",class:"mb-2"},{default:l(()=>[...t[2]||(t[2]=[s("Back to API Keys",-1)])]),_:1}),t[3]||(t[3]=e("h1",{class:"text-h4 font-weight-bold mb-1"},"API Documentation",-1)),t[4]||(t[4]=e("p",{class:"text-body-2 text-grey"},"Learn how to integrate EaseVerifier API into your application",-1))]),n(_,{modelValue:p.value,"onUpdate:modelValue":t[0]||(t[0]=o=>p.value=o),color:"primary",class:"mb-6"},{default:l(()=>[n(r,{value:"overview"},{default:l(()=>[...t[5]||(t[5]=[s("Overview",-1)])]),_:1}),n(r,{value:"authentication"},{default:l(()=>[...t[6]||(t[6]=[s("Authentication",-1)])]),_:1}),n(r,{value:"endpoints"},{default:l(()=>[...t[7]||(t[7]=[s("Endpoints",-1)])]),_:1}),n(r,{value:"examples"},{default:l(()=>[...t[8]||(t[8]=[s("Code Examples",-1)])]),_:1})]),_:1},8,["modelValue"]),n(E,{modelValue:p.value,"onUpdate:modelValue":t[1]||(t[1]=o=>p.value=o)},{default:l(()=>[n(m,{value:"overview"},{default:l(()=>[n(u,null,{default:l(()=>[n(i,{class:"pa-6"},{default:l(()=>[t[10]||(t[10]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Getting Started",-1)),t[11]||(t[11]=e("p",{class:"text-body-1 mb-4"},"The EaseVerifier API allows you to programmatically verify Nigerian identities including NIN, BVN, and CAC records.",-1)),n(I,{type:"info",variant:"tonal",class:"mb-4"},{default:l(()=>[...t[9]||(t[9]=[e("strong",null,"Base URL:",-1),s(),e("code",null,"https://verify.ashlabtech.ng/v1",-1)])]),_:1}),t[12]||(t[12]=e("h3",{class:"text-h6 font-weight-bold mb-3"},"Quick Start",-1)),t[13]||(t[13]=e("ol",{class:"pl-4 mb-4"},[e("li",{class:"mb-2"},[s("Generate your API credentials from the "),e("a",{href:"/customer/api"},"API Keys page")]),e("li",{class:"mb-2"},"Include your credentials in the request headers"),e("li",{class:"mb-2"},"Make a POST request to the verification endpoint"),e("li",null,"Handle the response in your application")],-1))]),_:1})]),_:1})]),_:1}),n(m,{value:"authentication"},{default:l(()=>[n(u,null,{default:l(()=>[n(i,{class:"pa-6"},{default:l(()=>[t[15]||(t[15]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Authentication",-1)),t[16]||(t[16]=e("p",{class:"text-body-1 mb-4"},"All API requests require authentication using your API Key and Secret.",-1)),n(x,{class:"mb-4"},{default:l(()=>[...t[14]||(t[14]=[e("thead",null,[e("tr",null,[e("th",null,"Header"),e("th",null,"Value"),e("th",null,"Description")])],-1),e("tbody",null,[e("tr",null,[e("td",null,[e("code",null,"Authorization")]),e("td",null,[e("code",null,"Bearer YOUR_API_KEY")]),e("td",null,"Your API key")]),e("tr",null,[e("td",null,[e("code",null,"X-API-Secret")]),e("td",null,[e("code",null,"YOUR_API_SECRET")]),e("td",null,"Your API secret")]),e("tr",null,[e("td",null,[e("code",null,"Content-Type")]),e("td",null,[e("code",null,"application/json")]),e("td",null,"Request content type")])],-1)])]),_:1})]),_:1})]),_:1})]),_:1}),n(m,{value:"endpoints"},{default:l(()=>[n(u,{class:"mb-4"},{default:l(()=>[n(g,{class:"d-flex align-center"},{default:l(()=>[n(y,{color:"success",size:"small",class:"mr-2"},{default:l(()=>[...t[17]||(t[17]=[s("POST",-1)])]),_:1}),t[18]||(t[18]=s("/verify/nin",-1))]),_:1}),n(i,null,{default:l(()=>[...t[19]||(t[19]=[e("p",{class:"mb-3"},"Verify a National Identification Number (NIN)",-1),e("h4",{class:"text-subtitle-2 font-weight-bold mb-2"},"Request Body",-1),e("pre",{class:"bg-grey-lighten-4 pa-3 rounded mb-3"},'{ "nin": "12345678901" }',-1),e("h4",{class:"text-subtitle-2 font-weight-bold mb-2"},"Response",-1),e("pre",{class:"bg-grey-lighten-4 pa-3 rounded"},'{ "success": true, "data": { "first_name": "John", "last_name": "Doe", ... } }',-1)])]),_:1})]),_:1}),n(u,{class:"mb-4"},{default:l(()=>[n(g,{class:"d-flex align-center"},{default:l(()=>[n(y,{color:"success",size:"small",class:"mr-2"},{default:l(()=>[...t[20]||(t[20]=[s("POST",-1)])]),_:1}),t[21]||(t[21]=s("/verify/bvn",-1))]),_:1}),n(i,null,{default:l(()=>[...t[22]||(t[22]=[e("p",{class:"mb-3"},"Verify a Bank Verification Number (BVN)",-1),e("h4",{class:"text-subtitle-2 font-weight-bold mb-2"},"Request Body",-1),e("pre",{class:"bg-grey-lighten-4 pa-3 rounded"},'{ "bvn": "12345678901" }',-1)])]),_:1})]),_:1})]),_:1}),n(m,{value:"examples"},{default:l(()=>[n(u,null,{default:l(()=>[n(i,{class:"pa-6"},{default:l(()=>[t[23]||(t[23]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Code Examples",-1)),n(_,{color:"primary",class:"mb-4"},{default:l(()=>[(v(),f(b,null,A(c,(o,d)=>n(r,{key:d,value:d},{default:l(()=>[s(h(d),1)]),_:2},1032,["value"])),64))]),_:1}),(v(),f(b,null,A(c,(o,d)=>e("pre",{key:d,class:"bg-grey-darken-4 text-white pa-4 rounded overflow-x-auto"},h(o),1)),64))]),_:1})]),_:1})]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["user"])],64)}}});export{k as default};
