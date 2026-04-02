import { useCallback } from 'react';
import { useAuth } from '@/hooks/useAuth';
import { useFakePayment } from '@/hooks/useFakePayment';
import { createMembershipPaymentIntent } from '@/lib/fakePaymentFlows';
import { openExternalUrl } from '@/lib/mobileNavigation';

const isNativeShell = () =>
  import.meta.env.VITE_APP_RUNTIME === 'mobile' ||
  Boolean(window?.Capacitor);

export const useMembershipActions = () => {
  const { profile } = useAuth();
  const { startPaymentFlow } = useFakePayment();

  const getMembershipActionUrl = useCallback((type, overrides = {}) => {
    const actions = profile?.membership_actions || {};
    const targetTier = overrides.targetTier || profile?.profile_info?.tier || '';

    if (type === 'manage') {
      return actions.account_url || '';
    }

    if (type === 'manage_payment') {
      return actions.billing_url || actions.account_url || '';
    }

    if (type === 'cancel') {
      return actions.cancel_url || '';
    }

    if (targetTier && actions.levels?.[targetTier]?.checkout_url) {
      return actions.levels[targetTier].checkout_url;
    }

    if (actions.current_level_checkout_url && (type === 'renew' || type === 'join')) {
      return actions.current_level_checkout_url;
    }

    return '';
  }, [profile?.membership_actions, profile?.profile_info?.tier]);

  const navigateToMembershipUrl = useCallback(async (url) => {
    if (!url) {
      return false;
    }

    if (isNativeShell()) {
      await openExternalUrl(url);
      return true;
    }

    window.location.assign(url);
    return true;
  }, []);

  const openMembershipAction = useCallback(async (type, overrides = {}) => {
    const url = getMembershipActionUrl(type, overrides);

    if (url) {
      return navigateToMembershipUrl(url);
    }

    const intent = createMembershipPaymentIntent({
      type,
      currentTier: profile?.profile_info?.tier,
      targetTier: overrides.targetTier,
    });

    startPaymentFlow({
      ...intent,
      ...overrides,
      metadata: {
        ...intent.metadata,
        ...(overrides.metadata || {}),
      },
    });

    return false;
  }, [getMembershipActionUrl, navigateToMembershipUrl, profile?.profile_info?.tier, startPaymentFlow]);

  return {
    getMembershipActionUrl,
    openMembershipAction,
    hasManagedMembershipUrls: Boolean(
      profile?.membership_actions?.account_url ||
      profile?.membership_actions?.billing_url ||
      profile?.membership_actions?.cancel_url ||
      Object.keys(profile?.membership_actions?.levels || {}).length
    ),
  };
};
