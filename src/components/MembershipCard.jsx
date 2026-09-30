
import React from 'react';
import { useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { CheckCircle2, CheckSquare2, Download, FileText, Flag, GraduationCap, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import ConfirmationLetterDialog from '@/components/ConfirmationLetterDialog';
import { getFullName, normalizeAccountInfo, normalizeMembershipDiscountType } from '@/lib/memberProfile';
import { getMembershipStatus } from '@/lib/membershipStatus';
import { getTierDisplayLabel } from '@/lib/membershipTiers';
import { getAppRuntimeConfig } from '@/lib/backendConfig';
import { getPortalUiSettings } from '@/lib/portalSettings';
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

const AAC_LOGO_URL = 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/09/light-header-logo.svg';
const REDPOINT_EMERGENCY_PHONE = '+01-628-251-1510';

const formatCoverageAmount = (value) => new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
  maximumFractionDigits: 0,
}).format(Number(value || 0));

const drawCardText = (context, text, x, y, options = {}) => {
  const {
    color = '#ffffff', font = '600 34px Arial', align = 'left', maxWidth,
  } = options;
  context.fillStyle = color;
  context.font = font;
  context.textAlign = align;
  context.fillText(String(text || ''), x, y, maxWidth);
};

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

const formatMemberCardYear = (value, fallback = 'N/A') => {
  const normalized = String(value || '').trim();
  if (/^\d{4}$/.test(normalized)) {
    return normalized;
  }

  const date = parseMembershipDate(value);
  if (!date) {
    return fallback;
  }

  return String(date.getFullYear());
};

const formatValidThru = (profileInfo = {}) => {
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
  const value = latest?.value || '';
  if (!value) {
    return 'N/A';
  }

  const date = latest?.date || parseMembershipDate(value);
  if (!date) {
    return 'N/A';
  }

  return date.toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

const formatMemberNumber = (memberId) => {
  const normalized = String(memberId || '').trim();
  return normalized || 'N/A';
};

const MembershipCard = ({ profile }) => {
  const [confirmationLetterOpen, setConfirmationLetterOpen] = React.useState(false);
  const navigate = useNavigate();
  const portalDesign = getPortalUiSettings().design || {};
  const runtimeConfig = getAppRuntimeConfig();
  const assetBaseUrl = String(runtimeConfig.assetBaseUrl || '').replace(/\/$/, '');
  const appBaseUrl = assetBaseUrl.replace(/\/assets$/, '');
  const topoBackgroundUrl = portalDesign.sidebarBackgroundUrl && !portalDesign.sidebarBackgroundUrl.startsWith('/')
    ? portalDesign.sidebarBackgroundUrl
    : `${appBaseUrl || ''}/sidebar-topo-v2.svg`;

  const accountInfo = normalizeAccountInfo(profile?.account_info || {});
  const profileInfo = profile?.profile_info || {};
  const benefitsInfo = profile?.benefits_info || {};

  const status = getMembershipStatus(profileInfo);
  const isMemberActive = status === 'Active';
  const discountType = normalizeMembershipDiscountType(accountInfo.membership_discount_type);
  const discountBadge = discountType ? DISCOUNT_BADGE_CONTENT[discountType] : null;
  const DiscountBadgeIcon = discountBadge?.Icon;
  const membershipTierLabel = getTierDisplayLabel(profileInfo?.tier, 'Free');
  const familyMembership = profile?.family_membership || {};
  const connectedAccounts = Array.isArray(profile?.connected_accounts) ? profile.connected_accounts : [];
  const isFamilyPlan = Boolean(
    familyMembership.mode === 'family' ||
    profile?.linked_parent_account ||
    connectedAccounts.length > 0,
  );
  const memberSinceLabel = formatMemberCardYear(profileInfo?.joined_date);
  const validThruLabel = formatValidThru(profileInfo);
  const hasAutoRenewal = Boolean(accountInfo.auto_renew || profile?.membership_actions?.current_subscription_id);
  const memberNumber = formatMemberNumber(profileInfo?.member_id);
  const memberName = getFullName(accountInfo);

  const handleDownloadCard = () => {
    const canvas = document.createElement('canvas');
    canvas.width = 1080;
    canvas.height = 1920;
    const context = canvas.getContext('2d');
    if (!context) return;

    const gradient = context.createLinearGradient(0, 0, 1080, 1920);
    gradient.addColorStop(0, '#050505');
    gradient.addColorStop(0.58, '#17110f');
    gradient.addColorStop(1, '#260806');
    context.fillStyle = gradient;
    context.fillRect(0, 0, 1080, 1920);
    context.fillStyle = '#b71c1c';
    context.fillRect(0, 0, 24, 1920);
    context.strokeStyle = 'rgba(248,194,53,.16)';
    context.lineWidth = 3;
    for (let y = 120; y < 1850; y += 105) {
      context.beginPath();
      context.moveTo(25, y);
      context.bezierCurveTo(270, y - 70, 560, y + 70, 1080, y - 20);
      context.stroke();
    }

    drawCardText(context, 'AMERICAN ALPINE CLUB', 84, 120, { font: '800 42px Arial', color: '#ffffff' });
    drawCardText(context, 'DIGITAL MEMBERSHIP CARD', 84, 174, { font: '600 20px Arial', color: '#f8c235' });
    drawCardText(context, status.toUpperCase(), 996, 120, { font: '700 24px Arial', color: isMemberActive ? '#86efac' : '#d6d3d1', align: 'right' });

    drawCardText(context, `${membershipTierLabel.toUpperCase()} MEMBER`, 84, 370, { font: '700 27px Arial', color: '#ff8a80' });
    drawCardText(context, memberName, 84, 455, { font: '700 64px Georgia', maxWidth: 910 });
    drawCardText(context, `MEMBER NO. ${memberNumber}`, 84, 525, { font: '500 25px monospace', color: '#d6d3d1' });
    drawCardText(context, accountInfo.email, 84, 575, { font: '500 22px Arial', color: '#a8a29e', maxWidth: 910 });

    context.fillStyle = 'rgba(255,255,255,.08)';
    context.fillRect(64, 635, 952, 270);
    drawCardText(context, 'MEMBERSHIP', 96, 700, { font: '700 20px Arial', color: '#f8c235' });
    drawCardText(context, 'VALID THROUGH', 96, 766, { font: '600 19px Arial', color: '#a8a29e' });
    drawCardText(context, validThruLabel, 96, 815, { font: '700 34px Arial' });
    drawCardText(context, 'MEMBER SINCE', 620, 766, { font: '600 19px Arial', color: '#a8a29e' });
    drawCardText(context, memberSinceLabel, 620, 815, { font: '700 34px Arial' });

    context.fillStyle = '#b71c1c';
    context.fillRect(64, 965, 952, 4);
    drawCardText(context, 'REDPOINT BENEFITS', 84, 1045, { font: '800 34px Arial' });
    drawCardText(context, isMemberActive ? 'COVERAGE ACTIVE' : 'COVERAGE NOT ACTIVE', 996, 1045, { font: '700 22px Arial', color: isMemberActive ? '#86efac' : '#fca5a5', align: 'right' });

    const coverageRows = [
      ['RESCUE', formatCoverageAmount(benefitsInfo.rescue_amount)],
      ['MEDICAL EXPENSE', formatCoverageAmount(benefitsInfo.medical_amount)],
      ['MORTAL REMAINS TRANSPORT', formatCoverageAmount(benefitsInfo.mortal_remains_amount)],
    ];
    coverageRows.forEach(([label, value], index) => {
      const y = 1160 + (index * 122);
      drawCardText(context, label, 84, y, { font: '600 21px Arial', color: '#a8a29e' });
      drawCardText(context, value, 996, y, { font: '700 34px Arial', align: 'right' });
    });

    context.fillStyle = 'rgba(183,28,28,.24)';
    context.fillRect(64, 1510, 952, 230);
    drawCardText(context, '24/7 EMERGENCY ASSISTANCE', 540, 1575, { font: '800 22px Arial', color: '#ffb4ae', align: 'center' });
    drawCardText(context, 'CALL REDPOINT TRAVEL PROTECTION', 540, 1632, { font: '700 25px Arial', align: 'center' });
    drawCardText(context, REDPOINT_EMERGENCY_PHONE, 540, 1698, { font: '800 42px Arial', color: '#f8c235', align: 'center' });
    drawCardText(context, 'Present this card as proof of current AAC membership.', 540, 1828, { font: '500 21px Arial', color: '#a8a29e', align: 'center' });

    const link = document.createElement('a');
    link.download = `aac-membership-card-${memberNumber}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
  };

  const handleDownloadConfirmationLetter = () => {
    setConfirmationLetterOpen(true);
  };

  const handleChangeMembership = () => {
    navigate('/membership/upgrade');
  };

  const cardBackgroundStyle = {
    backgroundImage: `linear-gradient(180deg, rgba(3, 0, 0, 0.66), rgba(3, 0, 0, 0.58)), radial-gradient(circle at 18% 16%, rgba(183, 28, 28, 0.2), transparent 34%), url("${topoBackgroundUrl}")`,
    backgroundPosition: 'center center, center top',
    backgroundRepeat: 'no-repeat, no-repeat, repeat',
    backgroundSize: 'cover, cover, 760px auto',
  };

  return (
    <>
      <motion.div
      className="aac-membership-card-shell mx-auto w-full max-w-6xl bg-white py-4"
      initial={{ opacity: 0, y: 18 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.45 }}
    >
      <div
        className="aac-membership-card relative aspect-[2.55/1] min-h-[280px] w-full overflow-hidden bg-[#030000] p-5 text-white sm:min-h-[295px] sm:p-6 lg:min-h-[310px] lg:p-7"
        style={cardBackgroundStyle}
      >
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 opacity-[0.24]"
          style={{
            backgroundImage:
              'radial-gradient(circle at 17% 22%, rgba(183, 28, 28, 0.36) 0 1px, transparent 2px 76px, rgba(183, 28, 28, 0.22) 77px 78px, transparent 79px 148px), linear-gradient(112deg, transparent 0 16%, rgba(248,194,53,0.13) 16.1%, transparent 16.25% 46%, rgba(183,28,28,0.24) 46.1%, transparent 46.3% 76%, rgba(255,255,255,0.08) 76.1%, transparent 76.3%)',
            backgroundSize: '100% 100%, 360px 360px',
          }}
        />
        <div aria-hidden className="absolute inset-y-0 left-0 w-2 bg-[#b71c1c]" />
        <div aria-hidden className="absolute inset-x-0 bottom-0 h-px bg-white/8" />

        <div className="relative flex h-full flex-col">
          <div className="flex items-start justify-between gap-5">
            <div className="flex min-w-0 items-center">
              <img
                src={AAC_LOGO_URL}
                alt="American Alpine Club"
                className="h-10 w-auto max-w-[13rem] object-contain sm:h-12"
              />
            </div>
            <div className="aac-membership-card-badges flex max-w-[62%] shrink-0 flex-wrap items-center justify-end gap-2">
              <span
                className={cn(
                  'inline-flex items-center gap-2 border-2 px-3.5 py-2.5 text-[0.72rem] font-bold uppercase tracking-[0.2em]',
                  isMemberActive ? 'border-emerald-400 text-emerald-200' : 'border-white/38 text-white/70',
                )}
              >
                {isMemberActive ? <CheckCircle2 className="h-4 w-4" strokeWidth={2.4} /> : null}
                {status}
              </span>
              <span
                className={cn(
                  'hidden rounded-none border-2 px-3.5 py-2.5 text-[0.72rem] font-bold uppercase tracking-[0.22em] sm:inline-flex',
                  isMemberActive ? 'border-[#ff4b43] text-[#ff6b63]' : 'border-white/38 text-white/70',
                )}
              >
                {membershipTierLabel}
              </span>
              {discountBadge ? (
                <span className="inline-flex items-center gap-2 border-2 border-[#f8c235] px-3.5 py-2.5 text-[0.7rem] font-bold uppercase tracking-[0.18em] text-[#f8c235]">
                  {DiscountBadgeIcon ? <DiscountBadgeIcon className="h-4 w-4 text-[#f8c235]" strokeWidth={2.3} /> : null}
                  {discountBadge.label}
                </span>
              ) : null}
              {isFamilyPlan ? (
                <span className="inline-flex items-center gap-2 border-2 border-[#f8c235] px-3.5 py-2.5 text-[0.7rem] font-bold uppercase tracking-[0.18em] text-[#f8c235]">
                  <Users className="h-4 w-4 text-[#f8c235]" strokeWidth={2.3} />
                  Family
                </span>
              ) : null}
            </div>
          </div>

          <div className="aac-membership-card-identity mt-6 flex flex-1 items-center sm:mt-8">
            <div className="min-w-0 self-center">
              <p className="text-sm font-bold uppercase tracking-[0.28em] text-[#ff8a80] sm:text-base">
                {membershipTierLabel} Member
              </p>
              <h2 className="aac-membership-card-name mt-3 font-serif text-3xl leading-[1.08] tracking-normal text-white sm:text-5xl lg:text-6xl">
                {memberName}
              </h2>
              <p className="aac-membership-card-number mt-6 font-mono text-sm tracking-[0.18em] text-white/52 sm:text-lg">
                No. {memberNumber}
              </p>
            </div>
          </div>

          <div className="aac-membership-card-meta mb-8 mt-auto flex flex-col gap-4 pt-5 sm:mb-10 sm:flex-row sm:items-end sm:justify-between lg:mb-12">
            <div className="grid w-full grid-cols-2 gap-8 sm:gap-12">
              <div>
                <p className="font-mono text-[0.66rem] uppercase tracking-[0.3em] text-white/46 sm:text-xs">Valid Thru</p>
                <div className="mt-2 flex flex-wrap items-center gap-2">
                  <p className="font-mono text-xl tracking-[0.06em] text-white sm:text-2xl">{validThruLabel}</p>
                  {hasAutoRenewal ? (
                    <span className="inline-flex items-center gap-1.5 border border-emerald-400/35 bg-emerald-500/10 px-2 py-1 text-[0.58rem] font-semibold uppercase tracking-[0.16em] text-emerald-200">
                      <CheckSquare2 className="h-3.5 w-3.5 text-emerald-300" strokeWidth={2.4} />
                      Auto-Renewal
                    </span>
                  ) : null}
                </div>
              </div>
              <div className="text-right">
                <p className="font-mono text-[0.66rem] uppercase tracking-[0.3em] text-white/46 sm:text-xs">Member Since</p>
                <p className="mt-2 font-mono text-xl tracking-[0.06em] text-white sm:text-2xl">{memberSinceLabel}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="aac-membership-actions-divider mt-4 border-t-2 border-[#b71c1c] pt-4">
        <div className="flex flex-col gap-2 lg:flex-row">
          <Button
            onClick={handleDownloadCard}
            className="flex min-h-[2.85rem] flex-1 items-center justify-center gap-2 rounded-none bg-[#b71c1c] text-white hover:bg-[#8f1515]"
          >
            <Download size={16} /> Download Card
          </Button>
          <Button
            onClick={handleChangeMembership}
            className="flex min-h-[2.85rem] flex-1 items-center justify-center gap-2 rounded-none bg-[#b71c1c] text-white hover:bg-[#8f1515]"
          >
            Renew
          </Button>
          <Button
            onClick={handleDownloadConfirmationLetter}
            className="flex min-h-[2.85rem] flex-1 items-center justify-center gap-2 rounded-none bg-[#b71c1c] text-white hover:bg-[#8f1515]"
          >
            <FileText size={16} /> View Confirmation Letter
          </Button>
        </div>
      </div>
      </motion.div>
      <ConfirmationLetterDialog
        open={confirmationLetterOpen}
        onOpenChange={setConfirmationLetterOpen}
        profile={profile}
      />
    </>
  );
};

export default MembershipCard;
