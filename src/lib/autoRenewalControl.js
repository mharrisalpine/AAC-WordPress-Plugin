export const getAutoRenewalControl = (profile = {}, cancelUrl = '') => {
  const actions = profile.membership_actions || {};
  // The active PMPro subscription is authoritative when supplied. Saved
  // preferences can remain true after recurring billing has ended.
  const hasAutoRenewal = Object.prototype.hasOwnProperty.call(actions, 'current_subscription_id')
    ? Boolean(actions.current_subscription_id)
    : [true, 1, '1'].includes(profile.account_info?.auto_renew);
  const isParentAccount = !profile.linked_parent_account;
  return {
    hasAutoRenewal,
    isParentAccount,
    disabled: !hasAutoRenewal || !isParentAccount || !cancelUrl,
  };
};
