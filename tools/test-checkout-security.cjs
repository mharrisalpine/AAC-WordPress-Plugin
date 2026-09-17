const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('wordpress/aac-member-portal/assets/checkout-security.js', 'utf8');
async function run({ context = 'same', fail = false, type = 'click' } = {}) {
  const listeners = {};
  const tokens = [{ value: 'old' }, { value: 'old' }];
  const consent = [{ value: 'old' }];
  let notice, submitted = 0, prevented = 0;
  const form = {
    matches: s => s === 'form#pmpro_form',
    querySelector: () => notice,
    querySelectorAll: s => s.includes('communications') ? consent : tokens,
    prepend: n => { notice = n; }, setAttribute() {}, removeAttribute() {},
    requestSubmit() { submitted++; },
  };
  const button = { type: 'submit', form, click() { submitted++; } };
  vm.runInNewContext(source, {
    window: { aacCheckoutSecurity: { url: '/ajax', context: 'same' } },
    document: {
      addEventListener: (name, callback) => { listeners[name] = callback; },
      createElement: () => ({ dataset: {}, setAttribute() {}, scrollIntoView() {}, remove() {} }),
    },
    fetch: async () => {
      if (fail) throw Error('offline');
      return { ok: true, json: async () => ({ success: true, data: { nonce: 'fresh', context, communicationsNonce: 'fresh-consent' } }) };
    }, URLSearchParams, AbortSignal, Date,
  });
  await listeners[type]({ type, target: type === 'submit' ? form : { closest: () => button },
    preventDefault() { prevented++; }, stopImmediatePropagation() {} });
  assert.equal(prevented, 1);
  if (fail || context !== 'same') {
    assert.equal(submitted, 0);
    assert.equal(tokens[0].value, 'old');
    assert.ok(notice.textContent);
  } else {
    assert.equal(submitted, 1);
    assert.deepEqual(tokens.map(t => t.value), ['fresh', 'fresh']);
    assert.equal(consent[0].value, 'fresh-consent');
  }
}
(async () => {
  await run(); await run({ type: 'submit' });
  await run({ context: 'other-account' }); await run({ fail: true });
  const php = fs.readFileSync('wordpress/aac-member-portal/aac-member-portal.php', 'utf8');
  assert.ok(!php.includes('wp_set_current_user(0)'));
  assert.ok(!php.includes('maybe_repair_pmpro_checkout_nonce'));
  assert.ok(php.includes("get_option('pmpro_checkout_page_id')"));
  console.log('PASS: click, keyboard submit, duplicate tokens, consent token, session change, offline recovery, no forced guest or server nonce replacement.');
})().catch(error => { console.error(error); process.exitCode = 1; });
