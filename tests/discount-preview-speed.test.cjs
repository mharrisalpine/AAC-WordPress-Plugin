const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('wordpress/aac-member-portal/aac-member-portal.php','utf8');
const a=source.indexOf('async function validatePromoForSummary(');
const b=source.indexOf("document.addEventListener('aac:discount-applied'",a);
for(const code of ['STUDENT','MILITARY']) test(code+' uses current server quote without waiting for AJAX',async()=>{
 let requests=0,updates=0;
 const quote={code,level_id:3,initial_payment:85};
 const window={setTimeout:fn=>fn()};
 const context={window,Date,URLSearchParams,CustomEvent:class{},document:{getElementById:()=>null,dispatchEvent(){updates++}},captureMagazineSelections(){},restoreMagazineSelections(){},syncOrderSummary(){},checkoutLevelId:3,checkoutDiscountQuotes:{[code.toLowerCase()]:quote},checkoutDiscountQuotesExpireAt:Date.now()+300000,membershipPreviewRequest:0,promoValidationConfig:{endpoint:'/',nonce:'test'},fetch:async()=>{requests++;return {json:async()=>({success:true,data:quote})}}};
 vm.createContext(context);vm.runInContext(source.slice(a,b),context);
 await context.validatePromoForSummary(code);assert.equal(requests,0);assert.equal(window.__aacValidatedMembershipDiscount,quote);assert.equal(updates,1);
 context.checkoutDiscountQuotesExpireAt=0;
 await context.validatePromoForSummary(code);assert.equal(requests,1);
});
