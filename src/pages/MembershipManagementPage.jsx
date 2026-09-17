import React from 'react';
import { Helmet } from 'react-helmet';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  ArrowLeft,
  BadgeCheck,
  Receipt,
  Shield,
  TrendingDown,
  TrendingUp,
  User,
  XCircle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import UpcomingPayment from '@/components/UpcomingPayment';
import { useAuth } from '@/hooks/useAuth';
import { useMembershipActions } from '@/hooks/useMembershipActions';
import { getMemberTransactions } from '@/lib/memberApi';
import { normalizeAccountInfo } from '@/lib/memberProfile';
import { getAutoRenewalControl } from '@/lib/autoRenewalControl';
import {
  MEMBERSHIP_PLAN_DETAILS,
  MEMBERSHIP_PLAN_ORDER,
  MEMBERSHIP_PLAN_PRICES,
  formatDollars,
} from '@/lib/membershipBenefits';
import {
  getTierDisplayLabel,
  isPublicMembershipTierId,
  normalizeTierId,
} from '@/lib/membershipTiers';

const MANAGE_TABS = [
  { id: 'account', label: 'Account', icon: User },
];

const parseMembershipDate = (value) => {
  const normalized = String(value || '').trim();
  if (!normalized) {
    return null;
  }

  const dateOnlyMatch = normalized.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (dateOnlyMatch) {
    const [, year, month, day] = dateOnlyMatch;
    const date = new Date(Number(year), Number(month) - 1, Number(day));
    return Number.isNaN(date.getTime()) ? null : date;
  }

  const date = new Date(normalized);
  return Number.isNaN(date.getTime()) ? null : date;
};

const formatMembershipDate = (value, fallback = 'Not scheduled') => {
  const date = parseMembershipDate(value);
  if (!date) {
    return fallback;
  }

  return date.toLocaleDateString(undefined, {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  });
};

const formatTransactionDate = (value) => {
  const date = parseMembershipDate(value);
  return date ? date.toLocaleDateString() : 'Date unavailable';
};

const getValidThroughDate = (profileInfo = {}) => {
  const candidates = [
    profileInfo.valid_through_date,
    profileInfo.renewal_date,
    profileInfo.expiration_date,
  ]
    .map((value) => ({ value, date: parseMembershipDate(value) }))
    .filter((candidate) => candidate.date);

  const latest = candidates.reduce((selected, candidate) => {
    if (!selected || candidate.date.getTime() > selected.date.getTime()) {
      return candidate;
    }
    return selected;
  }, null);

  return latest?.value || '';
};

const DetailRow = ({ label, value }) => (
  <div className="border-t border-stone-200 py-3">
    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-stone-500">
      {label}
    </p>
    <p className="mt-1 text-base font-semibold text-stone-950">{value || 'Not provided'}</p>
  </div>
);

const SectionHeader = ({ icon: Icon, eyebrow, title, description }) => (
  <div className="mb-6 border-b-2 border-[#b71c1c] pb-4">
    <div className="flex items-start gap-3">
      <div className="pt-1 text-[#b71c1c]">
        <Icon className="h-5 w-5" />
      </div>
      <div>
        {eyebrow ? (
          <p className="mb-2 text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-[#b71c1c]">
            {eyebrow}
          </p>
        ) : null}
        <h2 className="text-2xl font-bold text-stone-950">{title}</h2>
        {description ? <p className="mt-2 text-sm leading-6 text-stone-600">{description}</p> : null}
      </div>
    </div>
  </div>
);

const TransactionList = ({ transactions, loading }) => {
  if (loading) {
    return (
      <div className="border-y-2 border-[#b71c1c] px-5 py-5 text-sm font-semibold text-stone-700">
        Loading recent receipt...
      </div>
    );
  }

  if (!transactions.length) {
    return (
      <div className="border-y-2 border-[#b71c1c] px-5 py-5 text-sm text-stone-700">
        <p className="font-semibold text-stone-950">No recent PMPro receipt is available yet.</p>
        <p className="mt-2 leading-6">
          Completed membership payments will appear here after PMPro records the order.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {transactions.slice(0, 5).map((transaction) => (
        <div
          key={transaction.id || transaction.referenceId}
          className="aac-transaction-register-entry grid gap-3 border border-black bg-white p-4 md:grid-cols-[1fr,auto]"
        >
          <div>
            <p className="font-semibold text-stone-950">
              {transaction.description || 'Membership payment'}
            </p>
            <p className="mt-1 text-sm text-stone-500">
              {formatTransactionDate(transaction.createdAt)} • {transaction.status || 'Recorded'}
            </p>
			<div className="mt-3 space-y-2 border-t border-stone-100 pt-3">
				{(Array.isArray(transaction.lineItems) && transaction.lineItems.length
					? transaction.lineItems
					: [{ label: transaction.description || 'Membership payment', amount: transaction.amount }]
				).map((item, index) => (
					<div key={`${transaction.id || transaction.referenceId}-line-${index}`} className="flex items-center justify-between gap-5 text-sm">
						<span className="text-stone-600">{item.label || 'Membership payment'}</span>
						<span className="font-medium text-stone-950">{formatDollars(item.amount)}</span>
					</div>
				))}
			</div>
          </div>
          <div className="text-left md:text-right">
			<p className="text-xs font-semibold uppercase tracking-[0.16em] text-stone-500">Total paid</p>
			<p className="mt-1 text-lg font-bold text-stone-950">{formatDollars(transaction.amount)}</p>
            {transaction.referenceId ? (
              <p className="text-xs uppercase tracking-[0.18em] text-stone-500">
                {transaction.referenceId}
              </p>
            ) : null}
          </div>
        </div>
      ))}
    </div>
  );
};

const MembershipManagementPage = ({ standaloneUpgrade = false }) => {
  const location = useLocation();
  const navigate = useNavigate();
  const { profile } = useAuth();
  const {
    getMembershipActionUrl,
    openMembershipAction,
    hasManagedMembershipUrls,
  } = useMembershipActions();
  const requestedTab = new URLSearchParams(location.search).get('tab');
  const initialTab = MANAGE_TABS.some((tab) => tab.id === requestedTab) ? requestedTab : 'account';
  const [activeTab, setActiveTab] = React.useState(initialTab);
  const [transactions, setTransactions] = React.useState([]);
  const [transactionsLoading, setTransactionsLoading] = React.useState(true);
  const [showBillingForm, setShowBillingForm] = React.useState(false);
  const [billingFormHeight, setBillingFormHeight] = React.useState(720);
  const [upgradeCheckout, setUpgradeCheckout] = React.useState(null);
  const [upgradeCheckoutHeight, setUpgradeCheckoutHeight] = React.useState(900);
  const billingFrameRef = React.useRef(null);
  const upgradeFrameRef = React.useRef(null);

  React.useEffect(() => {
    if (requestedTab === 'change' && !standaloneUpgrade) {
      navigate('/membership/upgrade', { replace: true });
      return;
    }
    if (MANAGE_TABS.some((tab) => tab.id === requestedTab)) {
      setActiveTab(requestedTab);
    }
  }, [navigate, requestedTab, standaloneUpgrade]);

  React.useEffect(() => {
    let cancelled = false;

    const loadTransactions = async () => {
      setTransactionsLoading(true);
      try {
        const data = await getMemberTransactions();
        if (!cancelled) {
          const entries = Array.isArray(data?.transactions) ? data.transactions : [];
          setTransactions(
            entries
              .filter((transaction) => transaction.kind === 'Membership')
              .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt)),
          );
        }
      } catch (_error) {
        if (!cancelled) {
          setTransactions([]);
        }
      } finally {
        if (!cancelled) {
          setTransactionsLoading(false);
        }
      }
    };

    loadTransactions();

    return () => {
      cancelled = true;
    };
  }, []);

  const accountInfo = React.useMemo(
    () => normalizeAccountInfo(profile?.account_info || {}),
    [profile?.account_info],
  );
  const profileInfo = profile?.profile_info || {};
  const actions = profile?.membership_actions || {};
  const rawTier = profileInfo?.tier;
  const currentTier = rawTier ? normalizeTierId(rawTier) : '';
  const currentTierLabel = getTierDisplayLabel(rawTier, 'Membership');
  const isActive = profileInfo?.status === 'Active';
  const currentIndex = currentTier ? MEMBERSHIP_PLAN_ORDER.indexOf(currentTier) : -1;
  const visiblePlanOrder = MEMBERSHIP_PLAN_ORDER.filter((tier) => isPublicMembershipTierId(tier));
  const selectablePlanOrder = visiblePlanOrder.filter((tier) => {
    if (tier === currentTier) {
      return Boolean(actions?.current_level_checkout_url || actions?.levels?.[tier]?.checkout_url);
    }

    return actions?.levels?.[tier]?.action_type === 'upgrade' && Boolean(actions?.levels?.[tier]?.checkout_url);
  });
  const displayedUpgradePlanOrder = standaloneUpgrade ? visiblePlanOrder : selectablePlanOrder;
  const pendingDowngrade = actions?.pending_downgrade || null;
  const validThrough = getValidThroughDate(profileInfo);
  const autoRenewDisableUrl = getMembershipActionUrl('cancel');
  const { hasAutoRenewal, isParentAccount, disabled: autoRenewDisabled } = getAutoRenewalControl(profile, autoRenewDisableUrl);
  const billingUrl = getMembershipActionUrl('manage_payment');
  const embeddedBillingUrl = React.useMemo(() => {
    if (!billingUrl) return '';
    try {
      const url = new URL(billingUrl, window.location.origin);
      url.searchParams.set('aac_embed', '1');
      return url.toString();
    } catch (_error) {
      return billingUrl;
    }
  }, [billingUrl]);
  const cancelUrl = getMembershipActionUrl('cancel');
  const isWideManageTab = standaloneUpgrade || activeTab === 'account' || activeTab === 'cancel';
  const widePanelClass = 'relative left-1/2 w-screen -translate-x-1/2 bg-white px-6 py-6 sm:px-12 lg:px-20 xl:px-28 2xl:px-40';
  const widePanelInnerClass = 'mx-auto w-full max-w-[1600px]';

  React.useEffect(() => {
    const handleBillingFrameHeight = (event) => {
      if (event.origin !== window.location.origin || event.data?.type !== 'aac-pmpro-checkout-height') {
        return;
      }

      const nextHeight = Number(event.data.height || 0);
      if (Number.isFinite(nextHeight) && nextHeight > 300) {
        if (event.source === billingFrameRef.current?.contentWindow) {
          setBillingFormHeight(Math.ceil(nextHeight));
        } else if (event.source === upgradeFrameRef.current?.contentWindow) {
          setUpgradeCheckoutHeight(Math.ceil(nextHeight));
        }
      }
    };

    window.addEventListener('message', handleBillingFrameHeight);
    return () => window.removeEventListener('message', handleBillingFrameHeight);
  }, []);

  const handleAutoRenewalToggle = () => {
    if (autoRenewDisabled) return;
    window.location.assign(autoRenewDisableUrl);
  };

  const openUpgradeCheckout = (tier, membershipAction = 'upgrade') => {
    const checkoutUrl = getMembershipActionUrl(membershipAction, { targetTier: tier });
    if (!checkoutUrl) {
      void openMembershipAction(membershipAction, { targetTier: tier });
      return;
    }

    try {
      const url = new URL(checkoutUrl, window.location.origin);
      url.searchParams.set('aac_embed', '1');
      url.searchParams.set('aac_member_checkout', '1');
      url.searchParams.set('aac_membership_action', membershipAction);
      url.searchParams.set('aac_wizard', '0');
      url.searchParams.delete('aac_signup');
      url.searchParams.delete('aac_embed');
      window.location.assign(url.toString());
    } catch (_error) {
      void openMembershipAction(membershipAction, { targetTier: tier });
    }
  };

  const getCardAction = (tier) => {
    if (!isActive || currentIndex === -1) {
      return {
        label: 'Choose Membership',
        icon: TrendingUp,
        type: 'join',
      };
    }

    const targetIndex = MEMBERSHIP_PLAN_ORDER.indexOf(tier);

    if (pendingDowngrade?.target_tier === tier) {
      return {
        label: 'Downgrade Scheduled',
        icon: BadgeCheck,
        disabled: true,
      };
    }

    if (targetIndex === currentIndex) {
      return {
        label: 'Active Membership',
        icon: BadgeCheck,
        disabled: true,
      };
    }

    if (targetIndex < currentIndex) {
      return {
        label: 'Downgrade Locked',
        icon: TrendingDown,
        disabled: true,
      };
    }

    return {
      label: 'Upgrade Membership',
      icon: TrendingUp,
      type: 'upgrade',
    };
  };

  const renderAccountSection = () => (
    <section className="bg-white py-2">
      <div className="grid gap-4 lg:grid-cols-[1.1fr,0.9fr] lg:gap-6">
        <div className="aac-billing-membership-details border border-black bg-white p-4">
          <div className="grid gap-x-8 md:grid-cols-2">
            <DetailRow label="Membership" value={currentTierLabel} />
            <DetailRow label="Status" value={profileInfo.status || 'Not available'} />
            <DetailRow label="Valid Through" value={formatMembershipDate(validThrough)} />
            <DetailRow label="Auto-Renewal" value={hasAutoRenewal ? 'On' : 'Off'} />
          </div>
        </div>

        <div className="space-y-2 border-y-2 border-[#b71c1c] py-3">
          <Button
            type="button"
            onClick={() => navigate('/membership/upgrade')}
            disabled={!selectablePlanOrder.length}
            className="h-12 w-full rounded-none bg-[#b71c1c] text-white hover:bg-[#8f1515]"
          >
            <TrendingUp className="mr-2 h-4 w-4" />
            Upgrade Membership
          </Button>
          {billingUrl && actions?.current_subscription_id ? (
            <Button
              type="button"
              variant="outline"
              onClick={() => setShowBillingForm((visible) => !visible)}
              className="aac-white-outline-button h-12 w-full rounded-none border-stone-300 bg-white text-black hover:bg-stone-100"
            >
              {showBillingForm ? 'Close Billing Information' : 'Update Billing Information'}
            </Button>
          ) : null}
          <div className="border border-stone-300 bg-white p-4">
            <div className="flex items-center justify-between gap-4">
              <div>
                <p className="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-[#b71c1c]">
                  Automatic Renewal
                </p>
                <p id="auto-renewal-help" className="mt-1 text-sm leading-5 text-stone-600">
                  {!isParentAccount
                    ? 'Automatic renewal is managed by the Parent account.'
                    : hasAutoRenewal
                    ? 'Automatic renewal is on. Turning it off will not cancel your membership today; access continues through the current subscription period.'
                    : 'Automatic renewal is off. This control is unavailable for memberships without automatic renewal.'}
                </p>
              </div>
              <button
                type="button"
                onClick={handleAutoRenewalToggle}
                disabled={autoRenewDisabled}
                role="switch"
                aria-checked={hasAutoRenewal}
                aria-describedby="auto-renewal-help"
                className={`aac-auto-renew-toggle relative inline-flex h-8 w-16 shrink-0 items-center rounded-full border transition-colors disabled:cursor-not-allowed disabled:opacity-50 ${
                  hasAutoRenewal ? 'border-[#b71c1c] bg-[#b71c1c]' : 'border-stone-300 bg-stone-100'
                }`}
                aria-label="Automatic renewal"
              >
                <span
                  className={`aac-auto-renew-toggle__thumb absolute h-6 w-6 rounded-full bg-white shadow-sm transition-transform ${
                    hasAutoRenewal ? 'translate-x-8' : 'translate-x-1'
                  }`}
                />
              </button>
            </div>
            <p className="mt-2 text-xs font-bold uppercase tracking-[0.18em] text-stone-950">
              {hasAutoRenewal ? 'On' : 'Off'}
            </p>
          </div>
        </div>
      </div>

      {hasAutoRenewal && isParentAccount ? <UpcomingPayment subscriptionId={actions.current_subscription_id} /> : null}

      {showBillingForm && embeddedBillingUrl ? (
        <section className="mt-6 border-t-2 border-[#b71c1c] pt-5">
          <div className="mb-4 border-b-2 border-[#b71c1c] pb-3">
            <p className="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-[#b71c1c]">
              Payment Method
            </p>
            <h2 className="mt-2 text-2xl font-bold text-black">Update Billing Information</h2>
            <p className="mt-2 text-sm leading-6 text-stone-600">
              Update the billing address and card used for your membership renewal.
            </p>
          </div>
          <iframe
            ref={billingFrameRef}
            title="Update Billing Information"
            src={embeddedBillingUrl}
            className="block w-full border-0 bg-white"
            style={{ height: `${billingFormHeight}px` }}
          />
        </section>
      ) : null}

      <div className="aac-membership-transaction-section mt-6 border-t-2 border-[#b71c1c] pt-5">
        <div className="mb-3 flex items-center gap-3 border-b-2 border-[#b71c1c] pb-3">
          <Receipt className="h-6 w-6 text-[#a07f21]" />
          <h3 className="text-xl font-bold text-stone-950">Transaction register</h3>
        </div>
        <p className="mb-3 text-sm leading-5 text-stone-600">
          Membership payments you complete in this portal appear here. Non-membership charges are not shown in this register.
        </p>
        <TransactionList transactions={transactions} loading={transactionsLoading} />
      </div>

    </section>
  );

  const renderChangeSection = () => (
    <section className={widePanelClass}>
      <div className={widePanelInnerClass}>
        {upgradeCheckout ? (
          <>
            <SectionHeader
              icon={TrendingUp}
              eyebrow="Membership Upgrade"
              title={`Upgrade to ${upgradeCheckout.tier}`}
              description="Review the prorated upgrade amount and complete payment using your existing member information."
            />
            <Button
              type="button"
              variant="outline"
              onClick={() => setUpgradeCheckout(null)}
              className="mb-5 rounded-none border-2 border-black bg-white text-black hover:bg-stone-100"
            >
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back to membership levels
            </Button>
            <iframe
              ref={upgradeFrameRef}
              title={`Upgrade to ${upgradeCheckout.tier}`}
              src={upgradeCheckout.url}
              className="block w-full border-0 bg-white"
              style={{ height: `${upgradeCheckoutHeight}px` }}
            />
          </>
        ) : (
          <>
        <SectionHeader
          icon={Shield}
          eyebrow="Membership"
          title="Upgrade membership"
          description={
            hasManagedMembershipUrls
              ? 'Upgrades are prorated and charged today. Downgrades are available for auto-renew members within 30 days of renewal.'
              : 'Membership changes will use the local demo flow until Paid Memberships Pro registration URLs are available.'
          }
        />

      {pendingDowngrade ? (
        <p className="mb-6 border-y-2 border-[#b71c1c] px-5 py-4 text-sm font-semibold text-black">
          Pending downgrade: {pendingDowngrade.target_tier} on {formatMembershipDate(pendingDowngrade.effective_date)}
        </p>
      ) : null}

        <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
        {(standaloneUpgrade ? displayedUpgradePlanOrder : visiblePlanOrder).map((tier, index) => {
          const details = MEMBERSHIP_PLAN_DETAILS[tier];
          const action = standaloneUpgrade && tier === currentTier
            ? (hasAutoRenewal
              ? { label: 'Auto-Renewal Active', icon: BadgeCheck, type: 'renew', disabled: true }
              : { label: 'Renew Current Level', icon: BadgeCheck, type: 'renew', disabled: false })
            : getCardAction(tier);

          const ActionIcon = action.icon;
          const isCurrent = action.disabled;
          const annualLabel = MEMBERSHIP_PLAN_PRICES[tier] === 0 ? 'Portal preview membership' : 'Annual membership';

          return (
            <motion.div
              key={tier}
              initial={{ opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.35, delay: index * 0.06 }}
              className="flex flex-col bg-white py-6"
            >
              <div className="mb-6 border-b-2 border-[#b71c1c] pb-4">
                <p className="text-xs uppercase tracking-[0.25em] text-[#a07f21] mb-2">
                  Membership Level
                </p>
                <h3 className="text-2xl font-bold text-black">{tier}</h3>
                <div className="mt-3 text-3xl font-bold text-black">
                  {MEMBERSHIP_PLAN_PRICES[tier] === 0 ? 'Free' : formatDollars(MEMBERSHIP_PLAN_PRICES[tier])}
                </div>
                <p className="text-sm text-black/60">{annualLabel}</p>
              </div>

              <p className="text-black/75 mb-5">{details.summary}</p>

              <div className="flex-1 space-y-3 text-sm text-black/80">
                {details.bullets.map((bullet) => (
                  <div key={bullet} className="border-t border-stone-200 py-3 text-black">
                    {bullet}
                  </div>
                ))}
              </div>

              {isCurrent ? (
                <div className="mt-6 flex items-center justify-center gap-2 border-t-2 border-[#b71c1c] pt-4 font-semibold text-black">
                  <ActionIcon className="w-4 h-4" />
                  {action.label}
                </div>
              ) : (
                <Button
                  type="button"
                  onClick={() => {
                    if (action.type === 'upgrade' || action.type === 'renew') {
                      openUpgradeCheckout(tier, action.type);
                      return;
                    }
                    void openMembershipAction(action.type, { targetTier: tier });
                  }}
                  className="mt-6 w-full rounded-none bg-[#b71c1c] text-white hover:bg-[#8f1515]"
                >
                  <ActionIcon className="w-4 h-4 mr-2" />
                  {action.label}
                </Button>
              )}
            </motion.div>
          );
        })}
        </div>
        {standaloneUpgrade && !selectablePlanOrder.length ? (
          <p className="border border-[#b71c1c] bg-red-50 p-5 text-sm font-medium text-[#8f1515]">
            No renewable or higher PMPro membership levels are currently available for this account.
          </p>
        ) : null}
          </>
        )}
      </div>
    </section>
  );

  const renderCancelSection = () => (
    <section className={widePanelClass}>
      <div className={widePanelInnerClass}>
        <SectionHeader
          icon={XCircle}
          eyebrow="Cancellation"
          title="Cancel automatic renewal"
          description="Cancelling stops future automatic billing. Paid membership access remains available through the current term when an expiration date is on file."
        />

        <div className="grid gap-6 md:grid-cols-3">
          <DetailRow label="Current Membership" value={currentTierLabel} />
          <DetailRow label="Access Through" value={formatMembershipDate(validThrough, 'Current term')} />
          <DetailRow label="Auto-Renewal" value={hasAutoRenewal ? 'On' : 'Off'} />
        </div>

        <div className="mt-6 border-y-2 border-[#b71c1c] px-5 py-5">
          {cancelUrl && hasAutoRenewal ? (
            <>
              <p className="text-sm leading-6 text-stone-700">
                This account has an active recurring subscription. Continue to the PMPro cancellation review to stop future renewal billing.
              </p>
              <div className="mt-5 flex flex-col justify-center gap-3 sm:flex-row">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setActiveTab('account')}
                  className="h-12 rounded-none border-[#b71c1c] px-6 text-[#b71c1c] hover:bg-red-50"
                >
                  Return to Account
                </Button>
                <Button
                  type="button"
                  onClick={() => void openMembershipAction('cancel')}
                  className="h-12 rounded-none bg-[#b71c1c] px-6 text-white hover:bg-[#8f1515]"
                >
                  Continue to Cancellation Review
                </Button>
              </div>
            </>
          ) : (
            <p className="text-sm leading-6 text-stone-700">
              Automatic renewal is already off or PMPro does not have an active recurring subscription attached to this account. No cancellation action is needed.
            </p>
          )}
        </div>
      </div>
    </section>
  );

  const renderActiveSection = () => {
    switch (activeTab) {
      case 'change':
        return renderChangeSection();
      case 'cancel':
        return renderCancelSection();
      case 'account':
      default:
        return renderAccountSection();
    }
  };

  return (
    <>
      <Helmet>
        <title>{standaloneUpgrade ? 'Upgrade Membership' : 'Manage Membership'} - American Alpine Club</title>
        <meta
          name="description"
          content={standaloneUpgrade
            ? 'Compare AAC membership levels and upgrade or change your membership.'
            : 'Manage AAC account details, billing, and cancellation.'}
        />
      </Helmet>
      <div className={`aac-manage-page mx-auto w-full bg-white px-6 pb-6 !pt-2 sm:px-12 lg:px-20 xl:px-28 2xl:px-40 ${isWideManageTab ? 'max-w-none' : 'max-w-7xl'}`}>
        <Link to={standaloneUpgrade ? '/membership' : '/'} className="mb-4 inline-flex items-center gap-2 text-black transition-colors hover:text-[#a07f21]">
          <ArrowLeft size={16} />
          {standaloneUpgrade ? 'Back to billing' : 'Back to portal'}
        </Link>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.45 }}
          className="space-y-4"
        >
          {!standaloneUpgrade ? <div className="border-b-2 border-[#b71c1c] pb-4">
            <p className="mb-2 text-[0.68rem] font-semibold uppercase tracking-[0.26em] text-[#b71c1c]">
              Member Account
            </p>
            <h1 className="text-4xl font-bold text-black sm:text-5xl">Manage Membership</h1>
            <p className="mt-2 max-w-3xl text-sm leading-5 text-stone-600 sm:text-base">
              Manage account details, billing, membership changes, and cancellation options from one place.
            </p>
          </div> : null}

          {standaloneUpgrade ? renderChangeSection() : renderActiveSection()}
      </motion.div>
      </div>
    </>
  );
};

export default MembershipManagementPage;
