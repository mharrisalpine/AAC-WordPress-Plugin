const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
for(const template of ['fullscreen-portal.php','managed-shell-content.php']) {
 const source=fs.readFileSync('wordpress/aac-member-portal/templates/'+template,'utf8');
 test(template+': eligibility survives generic summary title',()=>{
  const a=source.indexOf('const currentLevelSupportsDiscountTiers =');
  const b=source.indexOf('const getCheckoutControl',a);
  for(const [name,us,choices,expected] of [['Membership',true,true,true],['Membership',false,true,false],['Membership',true,false,false],['Partner',true,true,true],['Supporter',true,false,false]]) {
   const ctx={getCurrentCheckoutLevelName:()=>name,isCheckoutCountryUS:()=>us,document:{querySelector:()=>choices?{}:null}};
   vm.createContext(ctx);vm.runInContext(source.slice(a,b)+';this.result=currentLevelSupportsDiscountTiers();',ctx);assert.equal(ctx.result,expected);
  }
 });
 test(template+': lookup finds PMPro compound IDs after fields move',()=>{
  const a=source.indexOf('const findDiscountDetailField =');
  const b=source.indexOf('const findStudentUniversityField',a);
  const field={querySelector:()=>({textContent:'Service Component'})};
  const moved={querySelectorAll:()=>[field]};
  const empty={querySelector:()=>null,querySelectorAll:()=>[]};
  const ctx={document:{querySelectorAll:()=>[field],querySelector:s=>s==='[data-aac-checkout-discount-details]'?moved:null},fieldset:empty};
  vm.createContext(ctx);vm.runInContext(source.slice(a,b)+';this.result=findDiscountDetailField(fieldset,["#service_component_div"],/service/i);',ctx);assert.equal(ctx.result,field);
 });
}
