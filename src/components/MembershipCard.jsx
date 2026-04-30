
import React from 'react';
import { motion } from 'framer-motion';
import { User, Calendar, FileDown, Flag, GraduationCap, QrCode, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { createMembershipPaymentIntent, formatDollars } from '@/lib/fakePaymentFlows';
import { downloadMembershipConfirmationLetter } from '@/lib/membershipConfirmationLetter';
import { useMembershipActions } from '@/hooks/useMembershipActions';
import { getFullName, normalizeAccountInfo, normalizeMembershipDiscountType } from '@/lib/memberProfile';
import { getMembershipStatus } from '@/lib/membershipStatus';
import { getTierById, getTierDisplayLabel, isManualOnlyMembershipTierId } from '@/lib/membershipTiers';
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

const getPortalHomeUrl = () => {
  if (typeof window === 'undefined') {
    return 'https://americanalpineclub.org/membership/';
  }

  return new URL('/membership/', window.location.origin).toString();
};

const MembershipCard = ({ profile }) => {
  const { openMembershipAction } = useMembershipActions();

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
  const portalHomeUrl = React.useMemo(() => getPortalHomeUrl(), []);
  const qrCodeUrl = React.useMemo(
    () => `https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=0&data=${encodeURIComponent(portalHomeUrl)}`,
    [portalHomeUrl]
  );

  const handleJoinRenew = () => {
    const type = status === 'Active' ? 'renew' : 'join';
    const targetTier = profileInfo?.tier || 'Partner';
    void openMembershipAction(type, { targetTier });
  };

  const handleManageMembership = () => {
    window.location.assign('/membership/#/membership');
  };

  const handleDownloadConfirmationLetter = () => {
    downloadMembershipConfirmationLetter(profile);
  };

  return (
    <div className="w-full max-w-4xl mx-auto">
      <motion.div
        className="relative w-full overflow-hidden rounded-[32px] border border-white/12 bg-[radial-gradient(circle_at_top_left,rgba(248,194,53,0.16),transparent_30%),radial-gradient(circle_at_bottom_right,rgba(183,28,28,0.16),transparent_26%),linear-gradient(145deg,#050505_0%,#0b0b0b_50%,#131313_100%)] p-6 text-white shadow-[0_28px_90px_rgba(3,0,0,0.32)] md:p-8"
        initial={{ opacity: 0, y: 18 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45 }}
      >
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 opacity-[0.16]"
          style={{
            backgroundImage:
              'linear-gradient(120deg, rgba(255,255,255,0.18) 0, rgba(255,255,255,0) 18%, rgba(255,255,255,0) 82%, rgba(248,194,53,0.16) 100%), repeating-linear-gradient(0deg, transparent 0, transparent 24px, rgba(255,255,255,0.03) 25px), repeating-linear-gradient(90deg, transparent 0, transparent 24px, rgba(255,255,255,0.028) 25px)',
          }}
        />
        <div className="pointer-events-none absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-white/35 to-transparent" />

        <div className="relative space-y-6">
          <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div className="flex min-w-0 items-start gap-5">
              {accountInfo.photo_url ? (
                <img
                  src={accountInfo.photo_url}
                  alt={getFullName(accountInfo)}
                  className="h-28 w-28 shrink-0 rounded-[28px] border border-[#f8c235]/60 object-cover shadow-[0_16px_40px_rgba(0,0,0,0.28)]"
                />
              ) : (
                <div
                  className="flex h-28 w-28 shrink-0 items-center justify-center rounded-[28px] border border-dashed border-[#f8c235]/55 bg-white/10 shadow-[0_16px_40px_rgba(0,0,0,0.18)]"
                  aria-hidden
                >
                  <User className="h-12 w-12 text-white/70" strokeWidth={1.5} />
                </div>
              )}
              <div className="min-w-0 pt-1">
                <p className="text-[0.68rem] font-semibold uppercase tracking-[0.28em] text-[#f8c235]">American Alpine Club</p>
                <h2 className="mt-2 text-2xl font-bold text-white md:text-[2rem]">{getFullName(accountInfo)}</h2>
                <p className="mt-2 text-sm uppercase tracking-[0.24em] text-[#f8c235]">{membershipTierLabel}</p>
                <span
                  className={cn(
                    'mt-4 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold',
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

            <div className="grid shrink-0 gap-3 sm:grid-cols-[auto_auto] lg:grid-cols-1">
              {discountBadge ? (
                <div className="flex w-fit flex-col items-center rounded-2xl border border-white/14 bg-white/8 px-4 py-3 text-center shadow-sm backdrop-blur-sm lg:self-end">
                  {DiscountBadgeIcon ? <DiscountBadgeIcon className="h-5 w-5 text-[#f8c235]" strokeWidth={2.1} /> : null}
                  <span className="mt-1 text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-white/84">
                    {discountBadge.label}
                  </span>
                </div>
              ) : null}

              <a
                href={portalHomeUrl}
                className="group rounded-[24px] border border-white/14 bg-white/8 p-3 text-white shadow-[0_18px_40px_rgba(0,0,0,0.18)] transition-colors hover:border-[#f8c235]/55 hover:bg-white/12"
                aria-label="Open AAC home page"
              >
                <div className="flex items-center gap-3">
                  <div className="rounded-[18px] bg-white p-2.5 shadow-sm">
                    <img
                      src={qrCodeUrl}
                      alt="QR code for the AAC home page"
                      className="h-[96px] w-[96px] rounded-[12px] object-contain"
                    />
                  </div>
                  <div className="min-w-0">
                    <div className="flex items-center gap-2 text-[#f8c235]">
                      <QrCode className="h-4 w-4" />
                      <p className="text-[0.65rem] font-semibold uppercase tracking-[0.2em]">Member Quick Link</p>
                    </div>
                    <p className="mt-2 text-sm font-semibold text-white">Scan to open the AAC home page</p>
                    <p className="mt-1 text-xs text-white/60">Perfect for a quick handoff from card to portal.</p>
                    <div className="mt-3 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.16em] text-white/70 transition-colors group-hover:text-[#f8c235]">
                      Open Home <ChevronRight className="h-3.5 w-3.5" />
                    </div>
                  </div>
                </div>
              </a>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
            <div className="flex items-center gap-3 rounded-[22px] border border-white/10 bg-white/6 px-4 py-4">
              <User className="h-4 w-4 text-[#f8c235]" />
              <div>
                <p className="text-xs text-white/55">MEMBER ID</p>
                <p className="font-semibold text-white">{profileInfo?.member_id || 'N/A'}</p>
              </div>
            </div>
            <div className="flex items-center gap-3 rounded-[22px] border border-white/10 bg-white/6 px-4 py-4">
              <Calendar className="h-4 w-4 text-[#f8c235]" />
              <div>
                <p className="text-xs text-white/55">MEMBER SINCE</p>
                <p className="font-semibold text-white">{memberSinceLabel}</p>
              </div>
            </div>
          </div>
        </div>
      </motion.div>

      <motion.div
        className="mt-3 overflow-hidden rounded-[24px] border border-stone-200 bg-white/92 p-3 shadow-[0_16px_44px_rgba(3,0,0,0.12)] backdrop-blur-sm"
        initial={{ opacity: 0, y: 18 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45, delay: 0.08 }}
      >
        <div className="flex flex-col gap-2 lg:flex-row">
          {status !== 'Active' && !isManualOnlyTier && (
            <Button onClick={handleJoinRenew} className="min-h-[2.85rem] flex-1 rounded-[16px] bg-[#b71c1c] text-white hover:bg-[#8f1515]">
              {profileInfo?.tier
                ? `Renew ${getTierById(profileInfo.tier).label} • ${formatDollars(createMembershipPaymentIntent({ type: 'renew', currentTier: profileInfo?.tier, targetTier: profileInfo?.tier }).amount)}`
                : `Join Membership • ${formatDollars(createMembershipPaymentIntent({ type: 'join', targetTier: 'Partner' }).amount)}`}
            </Button>
          )}
          {canManageMembership && (
            <Button onClick={handleManageMembership} className="min-h-[2.85rem] flex-1 rounded-[16px] bg-[#b71c1c] text-white hover:bg-[#8f1515]">
              Renew Membership
            </Button>
          )}
          <Button
            onClick={handleDownloadConfirmationLetter}
            className="flex min-h-[2.85rem] flex-1 items-center gap-2 rounded-[16px] bg-[#b71c1c] text-white hover:bg-[#8f1515]"
          >
            <FileDown size={16} /> Confirmation Letter
          </Button>
        </div>
      </motion.div>
    </div>
  );
};

export default MembershipCard;
