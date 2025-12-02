import{d as R,i as S,r as s,c as f,o as v,a as l,b as V,h as O,w as n,e,f as o,F as b,m as g,t as P}from"./app-Bt6Q0-C2.js";import{_ as Y}from"./CustomerLayout.vue_vue_type_script_setup_true_lang-OnzdTQrq.js";import"./index-Dz1Zefys.js";const B={class:"mb-6"},k=R({__name:"Documentation",props:{user:{}},setup(w){const p=S("overview"),c={curl:`curl -X POST https://api.easeverifier.com/v1/verify/nin \\
  -H "Authorization: Bearer YOUR_API_KEY" \\
  -H "X-API-Secret: YOUR_API_SECRET" \\
  -H "Content-Type: application/json" \\
  -d '{"nin": "12345678901"}'`,php:`<?php
$client = new GuzzleHttp\\Client();
$response = $client->post('https://api.easeverifier.com/v1/verify/nin', [
    'headers' => [
        'Authorization' => 'Bearer YOUR_API_KEY',
        'X-API-Secret' => 'YOUR_API_SECRET',
    ],
    'json' => ['nin' => '12345678901']
]);
$data = json_decode($response->getBody(), true);`,javascript:`const response = await fetch('https://api.easeverifier.com/v1/verify/nin', {
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
    'https://api.easeverifier.com/v1/verify/nin',
    headers={
        'Authorization': 'Bearer YOUR_API_KEY',
        'X-API-Secret': 'YOUR_API_SECRET'
    },
    json={'nin': '12345678901'}
)
data = response.json()`};return(C,t)=>{const I=s("v-btn"),r=s("v-tab"),_=s("v-tabs"),x=s("v-alert"),i=s("v-card-text"),u=s("v-card"),m=s("v-window-item"),E=s("v-table"),y=s("v-chip"),A=s("v-card-title"),h=s("v-window");return v(),f(b,null,[l(V(O),{title:"API Documentation - EaseVerifier"}),l(Y,{user:w.user},{default:n(()=>[e("div",B,[l(I,{variant:"text","prepend-icon":"mdi-arrow-left",href:"/customer/api",class:"mb-2"},{default:n(()=>[...t[2]||(t[2]=[o("Back to API Keys",-1)])]),_:1}),t[3]||(t[3]=e("h1",{class:"text-h4 font-weight-bold mb-1"},"API Documentation",-1)),t[4]||(t[4]=e("p",{class:"text-body-2 text-grey"},"Learn how to integrate EaseVerifier API into your application",-1))]),l(_,{modelValue:p.value,"onUpdate:modelValue":t[0]||(t[0]=a=>p.value=a),color:"primary",class:"mb-6"},{default:n(()=>[l(r,{value:"overview"},{default:n(()=>[...t[5]||(t[5]=[o("Overview",-1)])]),_:1}),l(r,{value:"authentication"},{default:n(()=>[...t[6]||(t[6]=[o("Authentication",-1)])]),_:1}),l(r,{value:"endpoints"},{default:n(()=>[...t[7]||(t[7]=[o("Endpoints",-1)])]),_:1}),l(r,{value:"examples"},{default:n(()=>[...t[8]||(t[8]=[o("Code Examples",-1)])]),_:1})]),_:1},8,["modelValue"]),l(h,{modelValue:p.value,"onUpdate:modelValue":t[1]||(t[1]=a=>p.value=a)},{default:n(()=>[l(m,{value:"overview"},{default:n(()=>[l(u,null,{default:n(()=>[l(i,{class:"pa-6"},{default:n(()=>[t[10]||(t[10]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Getting Started",-1)),t[11]||(t[11]=e("p",{class:"text-body-1 mb-4"},"The EaseVerifier API allows you to programmatically verify Nigerian identities including NIN, BVN, and CAC records.",-1)),l(x,{type:"info",variant:"tonal",class:"mb-4"},{default:n(()=>[...t[9]||(t[9]=[e("strong",null,"Base URL:",-1),o(),e("code",null,"https://api.easeverifier.com/v1",-1)])]),_:1}),t[12]||(t[12]=e("h3",{class:"text-h6 font-weight-bold mb-3"},"Quick Start",-1)),t[13]||(t[13]=e("ol",{class:"pl-4 mb-4"},[e("li",{class:"mb-2"},[o("Generate your API credentials from the "),e("a",{href:"/customer/api"},"API Keys page")]),e("li",{class:"mb-2"},"Include your credentials in the request headers"),e("li",{class:"mb-2"},"Make a POST request to the verification endpoint"),e("li",null,"Handle the response in your application")],-1))]),_:1})]),_:1})]),_:1}),l(m,{value:"authentication"},{default:n(()=>[l(u,null,{default:n(()=>[l(i,{class:"pa-6"},{default:n(()=>[t[15]||(t[15]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Authentication",-1)),t[16]||(t[16]=e("p",{class:"text-body-1 mb-4"},"All API requests require authentication using your API Key and Secret.",-1)),l(E,{class:"mb-4"},{default:n(()=>[...t[14]||(t[14]=[e("thead",null,[e("tr",null,[e("th",null,"Header"),e("th",null,"Value"),e("th",null,"Description")])],-1),e("tbody",null,[e("tr",null,[e("td",null,[e("code",null,"Authorization")]),e("td",null,[e("code",null,"Bearer YOUR_API_KEY")]),e("td",null,"Your API key")]),e("tr",null,[e("td",null,[e("code",null,"X-API-Secret")]),e("td",null,[e("code",null,"YOUR_API_SECRET")]),e("td",null,"Your API secret")]),e("tr",null,[e("td",null,[e("code",null,"Content-Type")]),e("td",null,[e("code",null,"application/json")]),e("td",null,"Request content type")])],-1)])]),_:1})]),_:1})]),_:1})]),_:1}),l(m,{value:"endpoints"},{default:n(()=>[l(u,{class:"mb-4"},{default:n(()=>[l(A,{class:"d-flex align-center"},{default:n(()=>[l(y,{color:"success",size:"small",class:"mr-2"},{default:n(()=>[...t[17]||(t[17]=[o("POST",-1)])]),_:1}),t[18]||(t[18]=o("/verify/nin",-1))]),_:1}),l(i,null,{default:n(()=>[...t[19]||(t[19]=[e("p",{class:"mb-3"},"Verify a National Identification Number (NIN)",-1),e("h4",{class:"text-subtitle-2 font-weight-bold mb-2"},"Request Body",-1),e("pre",{class:"bg-grey-lighten-4 pa-3 rounded mb-3"},'{ "nin": "12345678901" }',-1),e("h4",{class:"text-subtitle-2 font-weight-bold mb-2"},"Response",-1),e("pre",{class:"bg-grey-lighten-4 pa-3 rounded"},'{ "success": true, "data": { "first_name": "John", "last_name": "Doe", ... } }',-1)])]),_:1})]),_:1}),l(u,{class:"mb-4"},{default:n(()=>[l(A,{class:"d-flex align-center"},{default:n(()=>[l(y,{color:"success",size:"small",class:"mr-2"},{default:n(()=>[...t[20]||(t[20]=[o("POST",-1)])]),_:1}),t[21]||(t[21]=o("/verify/bvn",-1))]),_:1}),l(i,null,{default:n(()=>[...t[22]||(t[22]=[e("p",{class:"mb-3"},"Verify a Bank Verification Number (BVN)",-1),e("h4",{class:"text-subtitle-2 font-weight-bold mb-2"},"Request Body",-1),e("pre",{class:"bg-grey-lighten-4 pa-3 rounded"},'{ "bvn": "12345678901" }',-1)])]),_:1})]),_:1})]),_:1}),l(m,{value:"examples"},{default:n(()=>[l(u,null,{default:n(()=>[l(i,{class:"pa-6"},{default:n(()=>[t[23]||(t[23]=e("h2",{class:"text-h5 font-weight-bold mb-4"},"Code Examples",-1)),l(_,{color:"primary",class:"mb-4"},{default:n(()=>[(v(),f(b,null,g(c,(a,d)=>l(r,{key:d,value:d},{default:n(()=>[o(P(d),1)]),_:2},1032,["value"])),64))]),_:1}),(v(),f(b,null,g(c,(a,d)=>e("pre",{key:d,class:"bg-grey-darken-4 text-white pa-4 rounded overflow-x-auto"},P(a),1)),64))]),_:1})]),_:1})]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["user"])],64)}}});export{k as default};
