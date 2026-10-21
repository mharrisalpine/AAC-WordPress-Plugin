const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('wordpress/aac-member-portal/templates/checkout-profile-prefill.php', 'utf8').split('?>').slice(1).join('?>');
function run(eligible = true) {
 const controls = {};
 for (const name of ['pmpro_sfirstname', 'bfirstname', 'bemail', 'bcountry', 'bstate', 'baddress1', 'university_or_school', 'service_componentservice_branch']) controls[name] = { value: '', dataset: {}, dispatchEvent() {}, addEventListener() {} };
 controls.bemail.value = 'edited@example.org';
 controls.bcountry = { ...controls.bcountry, value: 'US', tagName: 'SELECT', options: [{value:'US',textContent:'United States'}, {value:'CA',textContent:'Canada'}] };
 const context = { checkoutProfileDefaults: { first_name: 'Member', email: 'saved@example.org', country: 'Canada', state: 'Ontario', street: '123 Main', university_or_school: 'Do not fill', service_componentservice_branch: 'Do not fill' }, document: {querySelector: s => controls[s.match(/name="([^"]+)"/)[1]], getElementById: n => controls[n]}, Event: class {} };
 vm.createContext(context);
 vm.runInContext(source.replace(/<\?php[\s\S]*?\?>/g, String(eligible)) + '\nfillUpgradeProfileDefaults();', context);
 return { controls, context };
}
test('prefills native and billing fields, normalizes country, preserves entered values and excludes discounts', () => {
 const {controls, context} = run();
 assert.equal(controls.pmpro_sfirstname.value, 'Member');
 assert.equal(controls.bfirstname.value, 'Member');
 assert.equal(controls.bemail.value, 'edited@example.org');
 assert.equal(controls.bcountry.value, 'CA');
 assert.equal(controls.bstate.value, 'Ontario');
 assert.equal(controls.baddress1.value, '123 Main');
 assert.equal(controls.university_or_school.value, '');
 assert.equal(controls.service_componentservice_branch.value, '');
 controls.bfirstname.value = '';
 vm.runInContext('fillUpgradeProfileDefaults()', context);
 assert.equal(controls.bfirstname.value, '');
});
test('does not prefill guest, non-upgrade or POST checkout', () => {
 assert.equal(run(false).controls.bfirstname.value, '');
});
test('restores state after country widget clears it but preserves an intentional edit', () => {
 const { controls, context } = run();
 controls.bstate.value = '';
 controls.bstate.tagName = 'SELECT';
 controls.bstate.options = [{value:'',textContent:'Select state'}, {value:'ON',textContent:'Ontario'}];
 vm.runInContext('fillUpgradeProfileDefaults()', context);
 assert.equal(controls.bstate.value,'ON');
 controls.bstate.value = '';
 controls.bstate.dataset.aacProfileEdited = 'true';
 vm.runInContext('fillUpgradeProfileDefaults()', context);
 assert.equal(controls.bstate.value,'');
});
