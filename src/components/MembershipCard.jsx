
import React from 'react';
import { motion } from 'framer-motion';
import { User, Sparkles, Calendar, Heart, FileDown, Flag, GraduationCap } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { createMembershipPaymentIntent, formatDollars } from '@/lib/fakePaymentFlows';
import { downloadMembershipConfirmationLetter } from '@/lib/membershipConfirmationLetter';
import { useMembershipActions } from '@/hooks/useMembershipActions';
import { getFullName, normalizeAccountInfo, normalizeMembershipDiscountType } from '@/lib/memberProfile';
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

  const accountInfo = normalizeAccountInfo(profile?.account_info || {});
  const profileInfo = profile?.profile_info || {};

  const status = getMembershipStatus(profileInfo);
  const isMemberActive = status === 'Active';
  const discountType = normalizeMembershipDiscountType(accountInfo.membership_discount_type);
  const discountBadge = discountType ? DISCOUNT_BADGE_CONTENT[discountType] : null;
  const DiscountBadgeIcon = discountBadge?.Icon;
  const isManualOnlyTier = isManualOnlyMembershipTierId(profileInfo?.tier);
  const canManageMembership = isMemberActive && Boolean(profileInfo?.tier);
  const membershipTierLabel = getTierDisplayLabel(profileInfo?.tier, 'Free');
  const joinedDateValue = profileInfo?.joined_date;
  const memberSinceLabel = joinedDateValue ? new Date(joinedDateValue).toLocaleDateString() : 'N/A';

  const handleJoinRenew = () => {
    const type = status === 'Active' ? 'renew' : 'join';
    const targetTier = profileInfo?.tier || 'Partner';
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
        className="relative w-full overflow-hidden rounded-[28px] border border-[#f8c235]/25 bg-[radial-gradient(circle_at_top_left,rgba(248,194,53,0.14),transparent_34%),linear-gradient(145deg,#050505_0%,#101010_55%,#17130f_100%)] p-6 text-white shadow-[0_28px_80px_rgba(3,0,0,0.28)] md:p-8"
        initial={{ opacity: 0, y: 18 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45 }}
      >
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 opacity-[0.14]"
          style={{
            backgroundImage:
              'linear-gradient(120deg, rgba(255,255,255,0.22) 0, rgba(255,255,255,0) 18%, rgba(255,255,255,0) 82%, rgba(248,194,53,0.18) 100%), repeating-linear-gradient(0deg, transparent 0, transparent 26px, rgba(255,255,255,0.035) 27px), repeating-linear-gradient(90deg, transparent 0, transparent 26px, rgba(255,255,255,0.03) 27px)',
          }}
        />

        <div className="relative space-y-6">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between sm:gap-5">
            <div className="flex min-w-0 items-start gap-4">
              {accountInfo.photo_url ? (
                <img
                  src={accountInfo.photo_url}
                  alt={getFullName(accountInfo)}
                  className="h-24 w-24 shrink-0 rounded-full border-2 border-[#f8c235] object-cover"
                />
              ) : (
                <div
                  className="flex h-24 w-24 shrink-0 items-center justify-center rounded-full border-2 border-dashed border-[#f8c235]/55 bg-white/10"
                  aria-hidden
                >
                  <User className="h-10 w-10 text-white/70" strokeWidth={1.5} />
                </div>
              )}
              <div className="min-w-0">
                <p className="text-[0.68rem] font-semibold uppercase tracking-[0.28em] text-[#f8c235]">American Alpine Club</p>
                <h2 className="mt-2 text-xl font-bold text-white md:text-2xl">{getFullName(accountInfo)}</h2>
                <span
                  className={cn(
                    'mt-3 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold',
                    isMemberActive ? 'bg-emerald-500/18 text-emerald-200' : 'bg-red-500/18 text-red-200',
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

            <div className="flex shrink-0 flex-col gap-3 sm:items-end">
              {discountBadge ? (
                <div className="flex w-fit flex-col items-center rounded-2xl border border-white/14 bg-white/8 px-3 py-2 text-center shadow-sm backdrop-blur-sm sm:self-end">
                  {DiscountBadgeIcon ? <DiscountBadgeIcon className="h-5 w-5 text-[#f8c235]" strokeWidth={2.1} /> : null}
                  <span className="mt-1 text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-white/84">
                    {discountBadge.label}
                  </span>
                </div>
              ) : null}

              <div className="rounded-[22px] border border-white/12 bg-white px-3 py-3 shadow-[0_16px_36px_rgba(0,0,0,0.18)]">
                <div
                  className="h-[86px] w-[86px] rounded-[14px] border border-stone-300 bg-white"
                  aria-label="Placeholder membership QR code"
                  style={{
                    backgroundColor: '#ffffff',
                    backgroundImage: `
                      linear-gradient(90deg, #0a0a0a 10%, transparent 10%, transparent 20%, #0a0a0a 20%, #0a0a0a 30%, transparent 30%, transparent 40%, #0a0a0a 40%, #0a0a0a 50%, transparent 50%, transparent 60%, #0a0a0a 60%, #0a0a0a 70%, transparent 70%, transparent 80%, #0a0a0a 80%, #0a0a0a 90%, transparent 90%),
                      linear-gradient(#0a0a0a 10%, transparent 10%, transparent 20%, #0a0a0a 20%, #0a0a0a 30%, transparent 30%, transparent 40%, #0a0a0a 40%, #0a0a0a 50%, transparent 50%, transparent 60%, #0a0a0a 60%, #0a0a0a 70%, transparent 70%, transparent 80%, #0a0a0a 80%, #0a0a0a 90%, transparent 90%)
                    `,
                    backgroundSize: '18px 18px, 18px 18px',
                    backgroundPosition: '0 0, 0 0',
                  }}
                />
                <p className="mt-2 text-center text-[0.58rem] font-semibold uppercase tracking-[0.22em] text-stone-500">
                  Scan Placeholder
                </p>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
            <div className="flex items-center gap-3 rounded-[20px] border border-white/10 bg-white/6 px-4 py-3">
              <User className="h-4 w-4 text-[#f8c235]" />
              <div>
                <p className="text-xs text-white/55">MEMBER ID</p>
                <p className="font-semibold text-white">{profileInfo?.member_id || 'N/A'}</p>
              </div>
            </div>
            <div className="flex items-center gap-3 rounded-[20px] border border-white/10 bg-white/6 px-4 py-3">
              <Sparkles className="h-4 w-4 text-[#f8c235]" />
              <div>
                <p className="text-xs text-white/55">MEMBERSHIP LEVEL</p>
                <p className="font-semibold text-white">{membershipTierLabel}</p>
              </div>
            </div>
            <div className="flex items-center gap-3 rounded-[20px] border border-white/10 bg-white/6 px-4 py-3">
              <Calendar className="h-4 w-4 text-[#f8c235]" />
              <div>
                <p className="text-xs text-white/55">MEMBER SINCE</p>
                <p className="font-semibold text-white">{memberSinceLabel}</p>
              </div>
            </div>
          </div>

          <div className="mt-4 grid gap-3 md:grid-cols-3">
            {status !== 'Active' && !isManualOnlyTier && (
              <Button onClick={handleJoinRenew} className="min-h-[3.25rem] w-full rounded-full bg-[#b71c1c] text-white hover:bg-[#8f1515]">
                {profileInfo?.tier
                  ? `Renew ${getTierById(profileInfo.tier).label} • ${formatDollars(createMembershipPaymentIntent({ type: 'renew', currentTier: profileInfo?.tier, targetTier: profileInfo?.tier }).amount)}`
                  : `Join Membership • ${formatDollars(createMembershipPaymentIntent({ type: 'join', targetTier: 'Partner' }).amount)}`}
              </Button>
            )}
            {canManageMembership && (
              <Button onClick={handleManageMembership} className="min-h-[3.25rem] w-full rounded-full bg-[#b71c1c] text-white hover:bg-[#8f1515]">
                Renew Membership
              </Button>
            )}
            <Button onClick={handleDonate} className="flex min-h-[3.25rem] w-full items-center gap-2 rounded-full bg-[#b71c1c] text-white hover:bg-[#8f1515]">
              <Heart size={16} /> Donate
            </Button>
            <Button
              onClick={handleDownloadConfirmationLetter}
              className="flex min-h-[3.25rem] w-full items-center gap-2 rounded-full bg-[#b71c1c] text-white hover:bg-[#8f1515]"
            >
              <FileDown size={16} /> Download Membership Confirmation Letter
            </Button>
          </div>
        </div>
      </motion.div>
    </div>
  );
};

export default MembershipCard;
