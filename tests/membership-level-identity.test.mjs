import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { isCurrentMembershipLevel } from '../src/lib/membershipLevelIdentity.js';

for (const [levelId, name] of [[6, 'Partner Adult'], [7, 'Partner Dependent']]) {
  const actions = { current_level_id: levelId, levels: { Partner: { level_id: 3 } } };
  assert.equal(isCurrentMembershipLevel(actions, 'Partner', name), false);
  // Even a normalized display tier must not override the actual PMPro IDs.
  assert.equal(isCurrentMembershipLevel(actions, 'Partner', 'Partner'), false);
}
assert.equal(isCurrentMembershipLevel({ current_level_id: 3, levels: { Partner: { level_id: 3 } } }, 'Partner', 'Partner'), true);
assert.equal(isCurrentMembershipLevel({}, 'Partner', 'Partner Adult'), false);
assert.equal(isCurrentMembershipLevel({}, 'Partner', 'Partner Dependent'), false);
assert.equal(isCurrentMembershipLevel({}, 'Partner', 'Partner'), true);
const page = fs.readFileSync(new URL('../src/pages/MembershipManagementPage.jsx', import.meta.url), 'utf8');
const selectionCode = page.slice(page.indexOf('  const getCardAction ='), page.indexOf('  const renderAccountSection ='));
for (const [id, name, expectedType] of [[6, 'Partner Adult', 'upgrade'], [7, 'Partner Dependent', 'upgrade'], [3, 'Partner', 'renew']]) {
  const calls = [];
  const context = {
    isCurrentMembershipLevel, isActive: true, currentIndex: 1,
    MEMBERSHIP_PLAN_ORDER: ['Supporter', 'Partner', 'Leader', 'Advocate'],
    pendingDowngrade: null, standaloneUpgrade: true, currentTier: 'Partner',
    rawTier: name, hasAutoRenewal: false, selectedUpgradeTier: 'Partner',
    actions: { current_level_id: id, levels: { Partner: { level_id: 3, checkout_url: '/checkout?level=3' } } },
    openUpgradeCheckout: (...args) => calls.push(args),
    openMembershipAction: () => assert.fail('Unexpected fallback'),
  };
  vm.runInNewContext(`${selectionCode}\ncontinueToSelectedMembership();`, context);
  assert.deepEqual(calls, [['Partner', expectedType]], name);
}
console.log('Membership level identity and checkout selection tests passed.');
