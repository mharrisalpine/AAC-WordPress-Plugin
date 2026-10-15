const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('wordpress/aac-member-portal/aac-member-portal.php','utf8');
const start=source.indexOf('function ensureCheckoutAutoRenewSection()');
const end=source.indexOf('function syncOrderSummary()',start);
function setup(search='') {
 const listeners={};
 const native={checked:true,dispatchEvent(){}};
 const toggle={checked:true,addEventListener(event,callback){listeners[event]=callback;}};
 const state={textContent:'On'};
 const fieldset={dataset:{},setAttribute(){},querySelector(s){return s.includes('name="autorenew"')?native:s.includes('__state')?state:s.includes('toggle')?toggle:{textContent:''};}};
 let inserted=null;
 const summary={parentNode:{insertBefore(node){inserted=node;}}};
 const document={getElementById(id){return id==='pmpro_pricing_fields'?summary:inserted;},querySelector(){return {};},createElement(){return fieldset;}};
 const ctx={document,window:{location:{search}},URLSearchParams,Event:class {}};
 vm.createContext(ctx);vm.runInContext(source.slice(start,end),ctx);
 ctx.ensureCheckoutAutoRenewSection();
 return {ctx,native,toggle,state,listeners};
}
test('upgrade toggle changes submitted renewal checkbox in both directions and survives refresh',()=>{
 const s=setup('?aac_membership_action=upgrade');
 s.toggle.checked=false;s.listeners.change();assert.equal(s.native.checked,false);assert.equal(s.state.textContent,'Off');
 s.ctx.ensureCheckoutAutoRenewSection();assert.equal(s.native.checked,false);
 s.toggle.checked=true;s.listeners.change();assert.equal(s.native.checked,true);assert.equal(s.state.textContent,'On');
});
test('prepaid extension still forces renewal off',()=>{
 const s=setup('?aac_extend_membership=1');assert.equal(s.native.checked,false);assert.equal(s.toggle.disabled,true);
});
