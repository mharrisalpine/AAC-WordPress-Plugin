const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('wordpress/aac-member-portal/aac-member-portal.php', 'utf8');
const start = source.indexOf('const selectedDiscount = document.querySelector', source.indexOf('function syncOrderSummary'));
const end = source.indexOf('const magazineRows =', start);
const calculation = source.slice(start, end) + '\noutput = { membershipDiscount, promoDiscount, hasSeparatePromo, total: discountedMembership - promoDiscount };';
function calculate(code, result, selectedDiscount = null, membershipResult = null) {
  const context = { checkoutBasePrice: 125, checkoutLevelId: 3, window: { __aacValidatedDiscountCode: result, __aacValidatedMembershipDiscount: membershipResult },
    document: { querySelector: () => selectedDiscount, getElementById: () => null, querySelectorAll: () => [{value: code}] } };
  vm.runInNewContext(calculation, context);
  return context.output;
}
test('entered, pending and rejected codes never produce an applied $0 promo line', () => {
  for (const code of ['', 'INVALID', 'PENDING']) {
    const result = calculate(code, null);
    assert.equal(result.hasSeparatePromo, false);
    assert.equal(result.total, 125);
  }
});
test('PMPro replacement prices produce the actual discount including free membership', () => {
  for (const price of [100, 62.5, 1, 0]) {
    const result = calculate('TEST', {code: 'test', level_id: 3, initial_payment: price});
    assert.equal(result.promoDiscount, 125 - price);
    assert.equal(result.total, price);
    assert.equal(result.hasSeparatePromo, true);
  }
});
test('stale results, other levels and missing prices cannot discount the summary', () => {
  for (const result of [{code:'OLD',level_id:3,initial_payment:1},{code:'NEW',level_id:4,initial_payment:1},{code:'NEW',level_id:3,initial_payment:null}]) {
    assert.equal(calculate('NEW',result).hasSeparatePromo,false);
  }
});
test('manual code overrides rather than stacks with a membership tier discount', () => {
  const selection={dataset:{aacMembershipDiscountRate:'.2',aacMembershipDiscountCode:'STUDENT',aacMembershipPrice:'100'}};
  const result=calculate('MANUAL',{code:'MANUAL',level_id:3,initial_payment:110},selection);
  assert.equal(result.membershipDiscount,0);
  assert.equal(result.total,110);
  assert.equal(calculate('',null,selection).total,100);
});
test('native completion updates summary after any request duration and ignores stale results', () => {
  const a=source.indexOf("document.addEventListener('aac:discount-applied'");
  const b=source.indexOf('\n\t\t\tfunction ensureSignupAccountFields',a);
  let listener, updates=0;
  const context={checkoutLevelId:3,window:{},restoreMagazineSelections(){},syncOrderSummary(){updates++},CustomEvent:function(name){this.type=name},document:{dispatchEvent(){},querySelector(){return null},addEventListener(name,fn){listener=fn},getElementById(){return {value:'TEST'}}}};
  vm.runInNewContext(source.slice(a,b),context);
  listener({detail:{code:'OLD',level_id:3,valid:true,initial_payment:1}});assert.equal(updates,0);
  listener({detail:{code:'TEST',level_id:3,valid:true,initial_payment:1}});assert.equal(updates,1);assert.equal(context.window.__aacValidatedDiscountCode.initial_payment,1);
  listener({detail:{code:'TEST',level_id:3,valid:false}});assert.equal(updates,2);assert.equal(context.window.__aacValidatedDiscountCode,null);
});
