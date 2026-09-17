import assert from 'node:assert/strict';
import { getAutoRenewalControl } from '../src/lib/autoRenewalControl.js';

for (const recurring of [true, false]) {
  for (const child of [true, false]) {
    const profile = {
      membership_actions: { current_subscription_id: recurring ? 123 : null },
      account_info: { auto_renew: true },
      linked_parent_account: child ? { parent_user_id: 456 } : null,
    };
    const result = getAutoRenewalControl(profile, '/cancel');
    assert.equal(result.hasAutoRenewal, recurring);
    assert.equal(result.disabled, !recurring || child);
    assert.equal(getAutoRenewalControl(profile, '').disabled, true);
  }
}
assert.equal(getAutoRenewalControl({}, '/cancel').disabled, true);
assert.equal(getAutoRenewalControl({ account_info: { auto_renew: 'false' } }, '/cancel').disabled, true);
console.log('Auto-renewal eligibility: Parent/linked, recurring/nonrecurring, and unavailable action checks passed.');
