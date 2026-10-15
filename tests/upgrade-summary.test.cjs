const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('wordpress/aac-member-portal/aac-member-portal.php','utf8');
const a=source.indexOf('window.__aacBuildUpgradeMembershipRows = function');
const b=source.indexOf('const promoValidationConfig',a);
function rows(price,code='STUDENT',level=3){
 const window={location:{search:'?aac_membership_action=upgrade'},__aacUpgradePricing:{catalog:125,credit:55,level_id:3},__aacValidatedDiscountCode:price===null?null:{code,level_id:level,initial_payment:price}};
 vm.runInNewContext(source.slice(a,b),{window,URLSearchParams,checkoutBasePrice:125,checkoutLevelId:3});
 return window.__aacBuildUpgradeMembershipRows('STUDENT','Student discount');
}
test('upgrade summary itemizes catalog, exact discount and unused credit',()=>{
 const r=rows(85);assert.deepEqual(Array.from(r,x=>x.amount),[125,-40,-55]);assert.equal(r.reduce((t,x)=>t+x.amount,0),30);
});
test('rejected, removed and stale codes do not discount',()=>{
 for(const r of [rows(null),rows(85,'OLD'),rows(85,'STUDENT',4)])assert.equal(r.reduce((t,x)=>t+x.amount,0),70);
});
test('credit is capped for free and below-credit upgrade prices',()=>{
 for(const price of [0,40,55])assert.equal(rows(price).reduce((t,x)=>t+x.amount,0),0);
});
test('both checkout shells consume the shared upgrade breakdown',()=>{
 for(const name of ['fullscreen-portal.php','managed-shell-content.php']){
 const s=fs.readFileSync('wordpress/aac-member-portal/templates/'+name,'utf8');
 assert.match(s,/const upgradeRows = window\.__aacBuildUpgradeMembershipRows\?\./);
 // Parse every inline script, substituting server-generated literals.
 for(const match of s.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)){
 const js=match[1].replace(/<\?php[\s\S]*?\?>/g,'null'); if(js.trim())new vm.Script(js);
 }
 }
});
test('selecting a discount requests validation even when selected card already reports that code',()=>{
 for(const name of ['fullscreen-portal.php','managed-shell-content.php']){
  const s=fs.readFileSync('wordpress/aac-member-portal/templates/'+name,'utf8');
  const start=s.indexOf('const syncMembershipDiscountCodeSelection =');
  const end=s.indexOf('\n\t',s.indexOf('\n\t\t};',start)+5);
  const block=s.slice(start,s.indexOf('const syncSelectedMembershipDiscountForSubmit',start));
  let calls=[];const window={};
  const context={window,normalizeDiscountCode:v=>String(v||'').toLowerCase(),getDiscountCodeState:()=> 'STUDENT',getMembershipDiscountLabelForCode:()=> 'Student',applyNativeDiscountCode:(code)=>{calls.push(code);window.__aacAppliedDiscountCode=code;}};
  vm.runInNewContext(block+'\nsyncMembershipDiscountCodeSelection({checked:true,dataset:{aacMembershipDiscountCode:"STUDENT"}});',context);
  assert.deepEqual(calls,['STUDENT']);
 }
});

test('upgrade without proration metadata uses validated Student and Military totals',()=>{
 for (const code of ['STUDENT','MILITARY']) {
  const window={location:{search:'?aac_membership_action=upgrade'},__aacUpgradePricing:null,__aacValidatedMembershipDiscount:{code,level_id:3,initial_payment:85}};
  vm.runInNewContext(source.slice(a,b),{window,URLSearchParams,checkoutBasePrice:125,checkoutLevelId:3});
  const result=window.__aacBuildUpgradeMembershipRows(code,code+' discount');
  assert.deepEqual(Array.from(result,x=>x.amount),[125,-40]);
  assert.equal(result.reduce((total,row)=>total+row.amount,0),85);
  assert.equal(window.__aacBuildUpgradeMembershipRows('', '').reduce((total,row)=>total+row.amount,0),125);
 }
});
