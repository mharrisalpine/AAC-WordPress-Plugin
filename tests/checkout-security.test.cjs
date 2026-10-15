const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
const source=fs.readFileSync('wordpress/aac-member-portal/assets/checkout-security.js','utf8');
function setup(response){
 const listeners={};let replay=0,timeout=0,notice=null,requests=0;
 const token={value:'old'};
 const form={matches:()=>true,setAttribute(){},removeAttribute(){},querySelector:()=>notice,querySelectorAll:s=>s.includes('pmpro_checkout_nonce')?[token]:[],prepend:n=>{notice=n},requestSubmit(){replay++}};
 const document={querySelector:()=>null,getElementById:()=>null,addEventListener(n,fn){listeners[n]=fn},createElement:()=>({dataset:{},setAttribute(){},scrollIntoView(){},remove(){notice=null}})};
 const window={aacCheckoutSecurity:{url:'/admin-ajax.php',context:'same'},addEventListener(){},setTimeout(){}};
 const context={window,document,URLSearchParams,Intl,Date,AbortSignal:{timeout(n){timeout=n;return {}}},fetch:async()=>{requests++;return response()}};
 vm.runInNewContext(source,context);
 const event={type:'submit',target:form,preventDefault(){},stopImmediatePropagation(){}};
 return {submit:()=>listeners.submit(event),state:()=>({replay,timeout,notice:notice?.textContent,token:token.value,requests})};
}
test('slow refresh waits up to 60 seconds and replays only after valid session-bound token',async()=>{
 let finish;const s=setup(()=>new Promise(resolve=>{finish=resolve}));
 const pending=s.submit();assert.equal(s.state().timeout,60000);assert.equal(s.state().replay,0);
 await s.submit();assert.equal(s.state().requests,1);
 finish({ok:true,json:async()=>({success:true,data:{nonce:'fresh',context:'same'}})});await pending;
 assert.equal(s.state().token,'fresh');assert.equal(s.state().replay,1);
});
test('timeout preserves entries and never submits payment',async()=>{
 const s=setup(()=>{throw Object.assign(new Error(),{name:'TimeoutError'})});await s.submit();
 assert.equal(s.state().replay,0);assert.equal(s.state().token,'old');assert.match(s.state().notice,/took too long/);
});
test('changed session remains blocked',async()=>{
 const s=setup(()=>({ok:true,json:async()=>({success:true,data:{nonce:'fresh',context:'other'}})}));await s.submit();
 assert.equal(s.state().replay,0);assert.match(s.state().notice,/sign-in session changed/);
});
test('invalid server response remains blocked',async()=>{
 const s=setup(()=>({ok:false,json:async()=>({success:false})}));await s.submit();assert.equal(s.state().replay,0);
});
