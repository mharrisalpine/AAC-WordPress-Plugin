import React from 'react';
import { Helmet } from 'react-helmet';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { ArrowLeft, BadgeCheck, Shield, TrendingDown, TrendingUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/hooks/useAuth';
import { useMembershipActions } from '@/hooks/useMembershipActions';
import {
  MEMBERSHIP_PLAN_DETAILS,
  MEMBERSHIP_PLAN_ORDER,
  MEMBERSHIP_PLAN_PRICES,
  formatDollars,
} from '@/lib/fakePaymentFlows';
import { isPublicMembershipTierId, normalizeTierId } from '@/lib/membershipTiers';

const MembershipManagementPage = () => {
  const { profile } = useAuth();
  const { openMembershipAction, hasManagedMembershipUrls } = useMembershipActions();

  const rawTier = profile?.profile_info?.tier;
  const currentTier = rawTier ? normalizeTierId(rawTier) : '';
  const isActive = profile?.profile_info?.status === 'Active';
  const currentIndex = currentTier ? MEMBERSHIP_PLAN_ORDER.indexOf(currentTier) : -1;
  const visiblePlanOrder = MEMBERSHIP_PLAN_ORDER.filter((tier) => isPublicMembershipTierId(tier));

  const getCardAction = (tier) => {
    if (!isActive || currentIndex === -1) {
      return {
        label: 'Choose Membership',
        icon: TrendingUp,
        type: 'join',
      };
    }

    const targetIndex = MEMBERSHIP_PLAN_ORDER.indexOf(tier);

    if (targetIndex === currentIndex) {
      return {
        label: 'Active Membership',
        icon: BadgeCheck,
        disabled: true,
      };
    }

    if (targetIndex < currentIndex) {
      return {
        label: 'Downgrade Membership',
        icon: TrendingDown,
        type: 'downgrade',
      };
    }

    return {
      label: 'Upgrade Membership',
      icon: TrendingUp,
      type: 'upgrade',
    };
  };

  return (
    <>
      <Helmet>
        <title>Manage Membership - American Alpine Club</title>
        <meta
          name="description"
          content="Compare AAC membership levels and manage upgrades or downgrades."
        />
      </Helmet>
      <div className="max-w-6xl mx-auto pt-28 pb-12 px-4">
        <Link to="/" className="inline-flex items-center gap-2 text-black hover:text-[#a07f21] transition-colors mb-6">
          <ArrowLeft size={16} />
          Back to portal
        </Link>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.45 }}
          className="space-y-8"
        >
          <div className="text-center">
            <div className="inline-flex items-center justify-center rounded-full bg-[#c8a43a] p-4 text-black mb-4">
              <Shield className="w-7 h-7" />
            </div>
            <h1 className="text-4xl font-bold text-black">Manage Membership</h1>
            <p className="text-black/75 mt-3 max-w-3xl mx-auto">
              Compare AAC membership tiers, see key benefits, and move to the plan that best matches your climbing season.
            </p>
            <p className="text-sm text-black/60 mt-2">
              {hasManagedMembershipUrls
                ? 'Membership changes are handled by Paid Memberships Pro on the WordPress site.'
                : 'Membership changes will use the local demo flow until Paid Memberships Pro registration URLs are available.'}
            </p>
          </div>

          <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
            {visiblePlanOrder.map((tier, index) => {
              const details = MEMBERSHIP_PLAN_DETAILS[tier];
              const action = getCardAction(tier);
              const ActionIcon = action.icon;
              const isCurrent = action.disabled;
              const annualLabel = MEMBERSHIP_PLAN_PRICES[tier] === 0 ? 'Portal preview membership' : 'Annual membership';

              return (
                <motion.div
                  key={tier}
                  initial={{ opacity: 0, y: 16 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.35, delay: index * 0.06 }}
                  className={`card-gradient rounded-[28px] border p-6 flex flex-col ${
                    isCurrent
                      ? 'border-[#c8a43a] shadow-[0_0_0_1px_rgba(200,164,58,0.22)]'
                      : 'border-stone-200'
                  }`}
                >
                  <div className="mb-6">
                    <p className="text-xs uppercase tracking-[0.25em] text-[#a07f21] mb-2">Membership Tier</p>
                    <h2 className="text-2xl font-bold text-black">{tier}</h2>
                    <div className="mt-3 text-3xl font-bold text-black">
                      {MEMBERSHIP_PLAN_PRICES[tier] === 0 ? 'Free' : formatDollars(MEMBERSHIP_PLAN_PRICES[tier])}
                    </div>
                    <p className="text-sm text-black/60">{annualLabel}</p>
                  </div>

                  <p className="text-black/75 mb-5">{details.summary}</p>

                  <div className="space-y-3 text-sm text-black/80 flex-1">
                    {details.bullets.map((bullet) => (
                      <div key={bullet} className="rounded-2xl bg-stone-50 border border-stone-200 px-4 py-3 text-black">
                        {bullet}
                      </div>
                    ))}
                  </div>

                  {isCurrent ? (
                    <div className="mt-6 rounded-2xl border border-[rgba(200,164,58,0.4)] bg-[rgba(200,164,58,0.15)] px-4 py-3 text-black font-semibold flex items-center justify-center gap-2">
                      <ActionIcon className="w-4 h-4" />
                      {action.label}
                    </div>
                  ) : (
                    <Button
                      type="button"
                      onClick={() => void openMembershipAction(action.type, { targetTier: tier })}
                      className="mt-6 w-full bg-[#b71c1c] hover:bg-[#8f1515] text-white"
                    >
                      <ActionIcon className="w-4 h-4 mr-2" />
                      {action.label}
                    </Button>
                  )}
                </motion.div>
              );
            })}
          </div>
        </motion.div>
      </div>
    </>
  );
};

export default MembershipManagementPage;
