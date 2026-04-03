
import React from 'react';
import { motion } from 'framer-motion';
import { User, Sparkles, Calendar, Heart, FileDown, Flag, GraduationCap } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { createMembershipPaymentIntent, formatDollars } from '@/lib/fakePaymentFlows';
import { downloadMembershipConfirmationLetter } from '@/lib/membershipConfirmationLetter';
import { useMembershipActions } from '@/hooks/useMembershipActions';
import { getFullName, normalizeMembershipDiscountType } from '@/lib/memberProfile';
import { getMembershipStatus } from '@/lib/membershipStatus';
import { getTierById, getTierDisplayLabel, isManualOnlyMembershipTierId } from '@/lib/membershipTiers';
import { useNavigate } from 'react-router-dom';
import { cn } from '@/lib/utils';

const DISCOUNT_BADGE_CONTENT = {
  military: {
    label: 'Military',
    Icon: Flag,
  },
  student: {
    label: 'Student',
    Icon: GraduationCap,
  },
};

const MembershipCard = ({ profile }) => {
  const { openMembershipAction } = useMembershipActions();
  const navigate = useNavigate();

  const { account_info, profile_info } = profile;

  const status = getMembershipStatus(profile_info);
  const isMemberActive = status === 'Active';
  const discountType = normalizeMembershipDiscountType(account_info?.membership_discount_type);
  const discountBadge = discountType ? DISCOUNT_BADGE_CONTENT[discountType] : null;
  const DiscountBadgeIcon = discountBadge?.Icon;
  const isManualOnlyTier = isManualOnlyMembershipTierId(profile_info?.tier);
  const canManageMembership = isMemberActive && Boolean(profile_info?.tier);
  const membershipTierLabel = getTierDisplayLabel(profile_info?.tier, 'Free');
  const membershipDateLabel = account_info?.auto_renew ? 'RENEWS ON' : 'EXPIRES ON';
  const membershipDateValue = profile_info?.renewal_date || profile_info?.expiration_date;

  const handleJoinRenew = () => {
    const type = status === 'Active' ? 'renew' : 'join';
    const targetTier = profile_info?.tier || 'Partner';
    void openMembershipAction(type, { targetTier });
  };

  const handleDonate = () => {
    navigate('/donate');
  };

  const handleManageMembership = () => {
    navigate('/membership');
  };

  const handleDownloadConfirmationLetter = () => {
    downloadMembershipConfirmationLetter(profile);
  };

  return (
    <div className="w-full max-w-3xl mx-auto">
      <motion.div
        className="w-full card-gradient rounded-[28px] border border-stone-200/80 p-6 md:p-8"
        initial={{ opacity: 0, y: 18 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45 }}
      >
        <div className="space-y-6">
          <div className="flex items-start justify-between gap-4">
            <div className="flex items-start gap-4">
              {account_info?.photo_url ? (
                <img
                  src={account_info.photo_url}
                  alt={getFullName(account_info)}
                  className="w-20 h-20 shrink-0 rounded-full border-2 border-[#B71C1C] object-cover"
                />
              ) : (
                <div
                  className="flex h-20 w-20 shrink-0 items-center justify-center rounded-full border-2 border-dashed border-[#B71C1C]/50 bg-stone-100"
                  aria-hidden
                >
                  <User className="h-9 w-9 text-stone-400" strokeWidth={1.5} />
                </div>
              )}
              <div>
                <h2 className="text-xl md:text-2xl font-bold text-stone-900">{getFullName(account_info)}</h2>
                <span
                  className={cn(
                    'mt-2 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold',
                    isMemberActive ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700',
                  )}
                >
                  <span
                    className={cn(
                      'h-2.5 w-2.5 rounded-full',
                      isMemberActive ? 'bg-emerald-500' : 'bg-red-500',
                    )}
                  />
                  {status}
                </span>
              </div>
            </div>
            {discountBadge ? (
              <div className="flex shrink-0 flex-col items-center rounded-2xl border border-stone-200 bg-stone-50 px-3 py-2 text-center shadow-sm">
                {DiscountBadgeIcon ? <DiscountBadgeIcon className="h-5 w-5 text-[#8f1515]" strokeWidth={2.1} /> : null}
                <span className="mt-1 text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-700">
                  {discountBadge.label}
                </span>
              </div>
            ) : null}
          </div>

          <div className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
            <div className="flex items-center gap-2">
              <User className="w-4 h-4 text-stone-500" />
              <div>
                <p className="text-stone-500 text-xs">MEMBER ID</p>
                <p className="text-stone-900 font-semibold">{profile_info?.member_id || 'N/A'}</p>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <Sparkles className="w-4 h-4 text-stone-500" />
              <div>
                <p className="text-stone-500 text-xs">TIER</p>
                <p className="text-stone-900 font-semibold">{membershipTierLabel}</p>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <Calendar className="w-4 h-4 text-stone-500" />
              <div>
                <p className="text-stone-500 text-xs">{membershipDateLabel}</p>
                <p className="text-stone-900 font-semibold">
                  {membershipDateValue ? new Date(membershipDateValue).toLocaleDateString() : 'N/A'}
                </p>
              </div>
            </div>
          </div>

          <div className="mt-4 space-y-2">
            {status !== 'Active' && !isManualOnlyTier && (
              <Button onClick={handleJoinRenew} className="w-full bg-[#b71c1c] text-white hover:bg-[#8f1515]">
                {profile_info?.tier
                  ? `Renew ${getTierById(profile_info.tier).label} • ${formatDollars(createMembershipPaymentIntent({ type: 'renew', currentTier: profile_info?.tier, targetTier: profile_info?.tier }).amount)}`
                  : `Join Membership • ${formatDollars(createMembershipPaymentIntent({ type: 'join', targetTier: 'Partner' }).amount)}`}
              </Button>
            )}
            {canManageMembership && (
              <Button onClick={handleManageMembership} variant="secondary" className="w-full text-black hover:bg-[#a07f21]">
                Manage Membership
              </Button>
            )}
            <Button
              onClick={handleDownloadConfirmationLetter}
              variant="outline"
              className="flex w-full items-center gap-2 border-stone-300 text-stone-900 hover:bg-stone-100"
            >
              <FileDown size={16} /> Download Membership Confirmation Letter
            </Button>
            <Button onClick={handleDonate} variant="outline" className="flex w-full items-center gap-2 border-[#c8a43a] text-[#6b5310] hover:bg-[#c8a43a] hover:text-black">
              <Heart size={16} /> Donate
            </Button>
          </div>
        </div>
      </motion.div>
    </div>
  );
};

export default MembershipCard;
