import React from 'react';
import { motion } from 'framer-motion';
import { Calendar, CheckCircle2, CreditCard, HeartPulse, KeyRound, Receipt, Shield, User, Users } from 'lucide-react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import MembershipCard from '@/components/MembershipCard';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/hooks/useAuth';
import { useMembershipActions } from '@/hooks/useMembershipActions';
import { useToast } from '@/components/ui/use-toast';
import { scheduleLinkedAccountRemoval } from '@/lib/memberApi';
import { getRescuePageUrl } from '@/lib/backendConfig';
import { formatGrantApplicationDate, grantStatusClassName, normalizeGrantApplications } from '@/lib/grants';
import { getTierDisplayLabel } from '@/lib/membershipTiers';
import { formatTShirtSizeLabel, normalizePrintDigitalPreference } from '@/lib/memberProfile';
import { getPortalUiSettings } from '@/lib/portalSettings';
import { getMembershipStatus, isMembershipActive } from '@/lib/membershipStatus';
import { cn } from '@/lib/utils';

const DetailRow = ({ label, value }) => (
  <div className="flex items-start justify-between gap-4 border-b border-stone-200/80 py-3 last:border-b-0 last:pb-0">
    <span className="text-sm font-medium uppercase tracking-[0.18em] text-stone-500">{label}</span>
    <span className="text-right text-sm text-stone-900">{value || 'Not provided'}</span>
  </div>
);

const StatusRow = ({ status }) => {
  const isActive = status === 'Active';

  return (
    <div className="flex items-start justify-between gap-4 border-b border-stone-200/80 py-3 last:border-b-0 last:pb-0">
      <span className="text-sm font-medium uppercase tracking-[0.18em] text-stone-500">Status</span>
      <span
        className={cn(
          'inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold',
          isActive ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700',
        )}
      >
        <span
          className={cn(
            'h-2.5 w-2.5 rounded-full',
            isActive ? 'bg-emerald-500' : 'bg-red-500',
          )}
        />
        {status}
      </span>
    </div>
  );
};

const InfoCard = ({ icon: Icon, title, description, children }) => (
  <div className="card-gradient rounded-[28px] border border-stone-200/80 p-6">
    <div className="mb-5 flex items-start gap-3">
      <div className="rounded-2xl bg-[#c8a43a]/18 p-3 text-[#6b5310]">
        <Icon className="h-5 w-5" />
      </div>
      <div>
        <h2 className="text-xl font-bold text-stone-900">{title}</h2>
        {description ? <p className="mt-1 text-sm text-stone-600">{description}</p> : null}
      </div>
    </div>
    {children}
  </div>
);

const CUSTOM_BLOCK_ICONS = {
  receipt: Receipt,
  user: User,
  shield: Shield,
  users: Users,
  heart: HeartPulse,
  'credit-card': CreditCard,
  calendar: Calendar,
};

const formatAddress = (accountInfo = {}) => {
  const parts = [
    accountInfo.street,
    accountInfo.address2,
    [accountInfo.city, accountInfo.state].filter(Boolean).join(', '),
    [accountInfo.zip, accountInfo.country].filter(Boolean).join(' '),
  ].filter(Boolean);

  return parts.join(', ');
};

const formatMembershipDate = (value, fallback = 'Not scheduled') => {
  if (!value) {
    return fallback;
  }

  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? fallback : parsed.toLocaleDateString();
};

const formatCurrency = (amount) => {
  const numericAmount = Number(amount || 0);

  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(numericAmount);
};

const formatConnectedAccountPrice = (value) => {
  const amount = Number(value || 0);
  return amount > 0 ? `$${amount.toFixed(2)}/yr` : 'Included';
};

const formatLinkedAccountStatus = (status) => {
  if (status === 'removal_pending') {
    return 'Removing at renewal';
  }

  return status;
};

const MemberProfilePage = () => {
  const navigate = useNavigate();
  const { profile, loading, refreshProfile } = useAuth();
  const { openMembershipAction } = useMembershipActions();
  const { toast } = useToast();
  const portalUiSettings = getPortalUiSettings();
  const portalContent = portalUiSettings.content;
  const portalDesign = portalUiSettings.design;
  const location = useLocation();
  const [removingSlotId, setRemovingSlotId] = React.useState('');

  if (loading || !profile) {
    return <div className="pt-10 text-center text-stone-800">Loading member profile...</div>;
  }

  const accountInfo = profile.account_info || {};
  const profileInfo = profile.profile_info || {};
  const benefitsInfo = profile.benefits_info || {};
  const connectedAccounts = Array.isArray(profile.connected_accounts) ? profile.connected_accounts : [];
  const familyMembership = profile.family_membership || { mode: '', additional_adult: false, dependent_count: 0 };
  const linkedParentAccount = profile.linked_parent_account || null;
  const membershipStatus = getMembershipStatus(profileInfo);
  const membershipActive = isMembershipActive(profileInfo);
  const membershipTierLabel = getTierDisplayLabel(profileInfo.tier, 'Free');
  const discountGroupLabel = accountInfo.membership_discount_type === 'student'
    ? 'Student'
    : accountInfo.membership_discount_type === 'military'
      ? 'Military'
      : 'None';
  const autoRenewEnabled = Boolean(accountInfo.auto_renew);
  const renewalDateLabel = autoRenewEnabled ? formatMembershipDate(profileInfo.renewal_date) : 'Not scheduled';
  const expirationDateLabel = autoRenewEnabled
    ? 'Not scheduled'
    : formatMembershipDate(profileInfo.expiration_date, 'Not scheduled');
  const linkedSuccess = new URLSearchParams(location.search).get('linked') === '1';
  const grantApplications = normalizeGrantApplications(profile.grant_applications);
  const memberProfileBlocks = Array.isArray(portalContent.memberProfileBlocks)
    ? portalContent.memberProfileBlocks.filter((block) => block && (block.title || block.description || (Array.isArray(block.entries) && block.entries.length)))
    : [];
  const memberProfileCardSections = portalContent.memberProfileCardSections || {};
  const isCardVisible = (cardId) => {
    const cardSettings = memberProfileCardSections?.[cardId];
    if (!cardSettings || typeof cardSettings !== 'object') {
      return true;
    }

    return cardSettings.visible !== 0 && cardSettings.visible !== false;
  };
  const canManageConnectedAccounts = !linkedParentAccount;
  const shouldShowLinkedAccounts = Boolean(
    linkedParentAccount ||
    familyMembership.mode === 'family' ||
    connectedAccounts.length > 0
  );
  const hasRedpointBenefits = Boolean(
    Number(benefitsInfo.rescue_amount || 0) > 0 ||
    Number(benefitsInfo.medical_amount || 0) > 0 ||
    Number(benefitsInfo.mortal_remains_amount || 0) > 0 ||
    benefitsInfo.rescue_reimbursement_process
  );
  const redpointCoverageLabel = membershipActive && hasRedpointBenefits ? 'Active' : 'Not active';

  const handleScheduleRemoval = async (slotId) => {
    if (!slotId) {
      return;
    }

    setRemovingSlotId(slotId);
    try {
      await scheduleLinkedAccountRemoval(slotId);
      await refreshProfile();
      toast({
        title: 'Family member scheduled for removal',
        description: 'This linked account will stay active through the current family plan end date.',
      });
    } catch (error) {
      toast({
        variant: 'destructive',
        title: 'Unable to update family plan',
        description: error.message || 'We could not schedule this family member for removal right now.',
      });
    } finally {
      setRemovingSlotId('');
    }
  };

  return (
    <div className="pb-6 pt-4 md:pt-6">
      <motion.div
        initial={{ opacity: 0, y: 18 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45 }}
        className="space-y-6"
      >
        {isCardVisible('membership_card') ? <MembershipCard profile={profile} /> : null}

        <div className="grid gap-6 xl:grid-cols-2">
          {isCardVisible('profile_information') ? (
            <InfoCard
              icon={User}
              title={portalContent.profile_information_title}
              description={portalContent.profile_information_description}
            >
              <div className="space-y-1">
                <DetailRow label="Email" value={accountInfo.email} />
                <DetailRow label="Phone" value={accountInfo.phone} />
                <DetailRow label="Address" value={formatAddress(accountInfo)} />
                <DetailRow label="T-Shirt Size" value={formatTShirtSizeLabel(accountInfo.size)} />
                <DetailRow label="American Alpine Journal" value={normalizePrintDigitalPreference(accountInfo.aaj_pref)} />
                <DetailRow label="Accidents in North American Climbing" value={normalizePrintDigitalPreference(accountInfo.anac_pref)} />
                <DetailRow label="American Climbing Journal" value={normalizePrintDigitalPreference(accountInfo.acj_pref)} />
                <DetailRow label="Guidebook to Membership" value={normalizePrintDigitalPreference(accountInfo.guidebook_pref)} />
              </div>
              <div className="mt-5 flex justify-center">
                <Button
                  type="button"
                  className="min-h-[3.125rem] rounded-full bg-[#b71c1c] px-7 text-white shadow-sm hover:bg-[#8f1515]"
                  onClick={() => navigate('/account')}
                >
                  {portalContent.update_profile_button_label}
                </Button>
              </div>
            </InfoCard>
          ) : null}

          {isCardVisible('membership_snapshot') ? (
            <InfoCard
              icon={Shield}
              title={portalContent.membership_snapshot_title}
              description={portalContent.membership_snapshot_description}
            >
              <div className="space-y-1">
                <DetailRow label="Member ID" value={profileInfo.member_id} />
                <StatusRow status={membershipStatus} />
                <DetailRow label="Membership Level" value={membershipTierLabel} />
                <DetailRow label="Discount Group" value={discountGroupLabel} />
                <DetailRow label="Renewal Date" value={renewalDateLabel} />
                <DetailRow label="Expiration Date" value={expirationDateLabel} />
              </div>
            </InfoCard>
          ) : null}
        </div>

        {isCardVisible('redpoint_benefits') ? (
        <section className="overflow-hidden rounded-[28px] border border-[#8f1515] bg-[#b71c1c] text-white shadow-[0_26px_70px_rgba(111,16,16,0.34)]">
          <div className="border-b border-white/12 px-6 py-6 sm:px-7">
            <div className="flex items-start gap-3">
              <div className="rounded-2xl border border-white/16 bg-white/10 p-3 text-white">
                <HeartPulse className="h-5 w-5" />
              </div>
              <div>
                <p className="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-[#ffd78a]">Medical & Rescue</p>
                <h2 className="mt-2 text-2xl font-bold text-white">Redpoint Benefits</h2>
                <p className="mt-1 text-sm text-white/76">Your current Redpoint rescue and evacuation coverage snapshot.</p>
              </div>
            </div>
          </div>

          <div className="grid gap-4 px-6 py-6 sm:px-7 md:grid-cols-2 xl:grid-cols-4">
            <div className="rounded-[24px] border border-white/12 bg-white/10 px-5 py-5 backdrop-blur-[2px]">
              <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-white/70">Coverage Status</p>
              <p className="mt-3 text-xl font-semibold text-white">{redpointCoverageLabel}</p>
              <p className="mt-2 text-sm text-white/74">
                {membershipActive && hasRedpointBenefits
                  ? 'Included with your current membership.'
                  : 'Upgrade or renew an eligible membership to restore coverage.'}
              </p>
            </div>
            <div className="rounded-[24px] border border-white/12 bg-white/10 px-5 py-5 backdrop-blur-[2px]">
              <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-white/70">Rescue Coverage</p>
              <p className="mt-3 text-2xl font-bold text-white">{formatCurrency(benefitsInfo.rescue_amount)}</p>
            </div>
            <div className="rounded-[24px] border border-white/12 bg-white/10 px-5 py-5 backdrop-blur-[2px]">
              <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-white/70">Medical Coverage</p>
              <p className="mt-3 text-2xl font-bold text-white">{formatCurrency(benefitsInfo.medical_amount)}</p>
            </div>
            <div className="rounded-[24px] border border-white/12 bg-white/10 px-5 py-5 backdrop-blur-[2px]">
              <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-white/70">Mortal Remains Transport</p>
              <p className="mt-3 text-2xl font-bold text-white">{formatCurrency(benefitsInfo.mortal_remains_amount)}</p>
            </div>
          </div>

          <div className="border-t border-white/12 px-6 py-5 sm:px-7">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-white/70">Rescue Reimbursement Process</p>
                <p className="mt-2 text-lg font-semibold text-white">
                  {benefitsInfo.rescue_reimbursement_process ? 'Included' : 'Not included'}
                </p>
              </div>
              <Button
                type="button"
                variant="outline"
                className="min-h-[3rem] rounded-full border-white/30 bg-white/10 px-6 text-white hover:bg-white hover:text-[#510909]"
                onClick={() => {
                  const rescuePageUrl = getRescuePageUrl();
                  if (rescuePageUrl && typeof window !== 'undefined') {
                    window.location.assign(rescuePageUrl);
                  }
                }}
              >
                View full rescue details
              </Button>
            </div>
          </div>
        </section>
        ) : null}

        {shouldShowLinkedAccounts && isCardVisible('linked_accounts') ? (
          <InfoCard
            icon={Users}
            title={portalContent.linked_accounts_title}
            description={portalContent.linked_accounts_description}
          >
            {linkedSuccess ? (
              <div className="mb-5 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
                <span>Linked account updated successfully.</span>
              </div>
            ) : null}

            {linkedParentAccount ? (
              <div className="mb-5 rounded-[20px] border border-stone-200 bg-stone-50/80 px-4 py-4">
                <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Linked Parent Account</p>
                <div className="mt-3 grid gap-3 text-sm text-stone-700 sm:grid-cols-2">
                  <div className="rounded-2xl bg-white px-4 py-3">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Connected To</p>
                    <p className="mt-1 font-semibold text-stone-900">{linkedParentAccount.parent_name || 'AAC Parent Account'}</p>
                    {linkedParentAccount.parent_email ? <p className="mt-1 text-stone-600">{linkedParentAccount.parent_email}</p> : null}
                  </div>
                  <div className="rounded-2xl bg-white px-4 py-3">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Linked Role</p>
                    <p className="mt-1 font-semibold text-stone-900">{linkedParentAccount.label || 'Family member'}</p>
                    {linkedParentAccount.invite_code ? <p className="mt-1 font-mono text-stone-600">{linkedParentAccount.invite_code}</p> : null}
                    {linkedParentAccount.scheduled_removal_date ? (
                      <p className="mt-1 text-stone-600">
                        Access ends {formatMembershipDate(linkedParentAccount.scheduled_removal_date, 'Not scheduled')}
                      </p>
                    ) : null}
                  </div>
                </div>
              </div>
            ) : null}

            {familyMembership.mode === 'family' || connectedAccounts.length > 0 ? (
              <>
                <div className="space-y-1">
                  <DetailRow
                    label="Family Plan"
                    value={familyMembership.mode === 'family' ? 'Enabled' : 'Not enabled'}
                  />
                  <DetailRow
                    label="Additional Adult"
                    value={familyMembership.additional_adult ? 'Included' : 'Not included'}
                  />
                  <DetailRow
                    label="Dependents"
                    value={String(familyMembership.dependent_count || 0)}
                  />
                </div>
                {connectedAccounts.length ? (
                  <div className="mt-5 space-y-3">
                    {connectedAccounts.map((account) => (
                      <div key={account.id} className="rounded-[20px] border border-stone-200 bg-stone-50/80 px-4 py-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                          <div>
                            <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">
                              {account.type === 'adult' ? 'Additional Adult' : 'Dependent'}
                            </p>
                            <p className="mt-1 text-sm font-semibold text-stone-900">{account.label}</p>
                            <p className="mt-1 text-sm text-stone-600">
                              {account.child_name || 'Pending child account'}
                              {account.child_email ? ` • ${account.child_email}` : ''}
                            </p>
                            {account.scheduled_removal_date ? (
                              <p className="mt-1 text-sm text-stone-600">
                                Access ends {formatMembershipDate(account.scheduled_removal_date, 'Not scheduled')}
                              </p>
                            ) : null}
                          </div>
                          <span
                            className={cn(
                              'inline-flex items-center rounded-full px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em]',
                              account.status === 'connected'
                                ? 'bg-emerald-50 text-emerald-800'
                                : account.status === 'removal_pending'
                                  ? 'bg-red-50 text-red-700'
                                  : 'bg-amber-50 text-amber-800',
                            )}
                          >
                            {formatLinkedAccountStatus(account.status)}
                          </span>
                        </div>
                        <div className="mt-3 grid gap-3 text-sm text-stone-700 sm:grid-cols-2">
                          <div className="rounded-2xl bg-white px-4 py-3">
                            <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Invite Code</p>
                            <p className="mt-1 font-mono text-sm text-stone-900">{account.invite_code || 'Pending'}</p>
                          </div>
                          <div className="rounded-2xl bg-white px-4 py-3">
                            <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Recurring Charge</p>
                            <p className="mt-1 text-sm font-semibold text-stone-900">{formatConnectedAccountPrice(account.price)}</p>
                          </div>
                        </div>
                        {canManageConnectedAccounts && account.child_user_id > 0 ? (
                          <div className="mt-4 flex justify-end">
                            <Button
                              type="button"
                              variant={account.status === 'removal_pending' ? 'outline' : 'default'}
                              className="min-h-[2.75rem] px-5"
                              disabled={account.status === 'removal_pending' || removingSlotId === account.id}
                              onClick={() => void handleScheduleRemoval(account.id)}
                            >
                              {account.status === 'removal_pending'
                                ? 'Removal scheduled'
                                : removingSlotId === account.id
                                  ? 'Scheduling…'
                                  : 'Remove At Renewal'}
                            </Button>
                          </div>
                        ) : null}
                      </div>
                    ))}
                  </div>
                ) : null}
              </>
            ) : null}

            <div className="mt-5 flex justify-center">
              <Button
                asChild
                type="button"
                className="rounded-full"
                style={{
                  backgroundColor: portalDesign.primaryActionBackground,
                  color: portalDesign.primaryActionText,
                }}
              >
                <Link to="/linked-accounts">{portalContent.linked_accounts_redeem_button_label}</Link>
              </Button>
            </div>
          </InfoCard>
        ) : null}

        {memberProfileBlocks.length && isCardVisible('custom_blocks') ? (
          <div className="grid gap-6 xl:grid-cols-2">
            {memberProfileBlocks.map((block, index) => {
              const Icon = CUSTOM_BLOCK_ICONS[block.icon] || Receipt;
              const entries = Array.isArray(block.entries) ? block.entries.filter((entry) => entry && (entry.label || entry.value || entry.description)) : [];
              const buttonUrl = String(block.button_url || '').trim();
              const buttonLabel = String(block.button_label || '').trim();
              return (
                <InfoCard
                  key={`${block.title || 'custom-block'}-${index}`}
                  icon={Icon}
                  title={block.title || 'Member Profile Block'}
                  description={block.description}
                >
                  {entries.length ? (
                    <div className="space-y-1">
                      {entries.map((entry, entryIndex) => (
                        <div key={`${entry.label || 'entry'}-${entryIndex}`} className="border-b border-stone-200/80 py-3 last:border-b-0 last:pb-0">
                          <div className="flex items-start justify-between gap-4">
                            <span className="text-sm font-medium uppercase tracking-[0.18em] text-stone-500">
                              {entry.label || 'Entry'}
                            </span>
                            <span className="text-right text-sm text-stone-900">
                              {entry.value || 'Not provided'}
                            </span>
                          </div>
                          {entry.description ? (
                            <p className="mt-2 text-sm leading-6 text-stone-600">{entry.description}</p>
                          ) : null}
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="rounded-[24px] border border-dashed border-stone-300 bg-stone-50/80 px-5 py-6 text-sm text-stone-600">
                      No entries have been added to this block yet.
                    </div>
                  )}
                  {buttonUrl && buttonLabel ? (
                    <div className="mt-5 flex justify-center">
                      {buttonUrl.startsWith('/') ? (
                        <Button asChild type="button" className="rounded-full" style={{ backgroundColor: portalDesign.primaryActionBackground, color: portalDesign.primaryActionText }}>
                          <Link to={buttonUrl}>{buttonLabel}</Link>
                        </Button>
                      ) : (
                        <Button asChild type="button" className="rounded-full" style={{ backgroundColor: portalDesign.primaryActionBackground, color: portalDesign.primaryActionText }}>
                          <a href={buttonUrl} target="_blank" rel="noreferrer">{buttonLabel}</a>
                        </Button>
                      )}
                    </div>
                  ) : null}
                </InfoCard>
              );
            })}
          </div>
        ) : null}

        <div className="grid gap-6">
          {isCardVisible('my_grants') ? (
            <InfoCard
              icon={CheckCircle2}
              title="My Grants"
              description={portalContent.grant_applications_description}
            >
              {grantApplications.length ? (
                <div className="space-y-3">
                  {grantApplications.map((application) => (
                    <div
                      key={application.id}
                      className="rounded-[22px] border border-stone-200 bg-white px-5 py-4"
                    >
                      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div className="space-y-1">
                          <p className="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-[#8a6a19]">
                            {application.category || 'Grant Application'}
                          </p>
                          <h3 className="text-lg font-semibold text-stone-900">{application.grant_name}</h3>
                          <p className="text-sm text-stone-600">
                            {application.project_title || 'Application submitted'} • {formatGrantApplicationDate(application.application_date)}
                          </p>
                        </div>
                        <span
                          className={cn(
                            'inline-flex items-center self-start rounded-full px-3 py-1.5 text-sm font-semibold md:self-auto',
                            grantStatusClassName(application.status),
                          )}
                        >
                          {application.status}
                        </span>
                      </div>
                      {application.last_note ? (
                        <div className="mt-3 rounded-2xl border border-stone-200 bg-stone-50/80 px-4 py-3 text-sm leading-6 text-stone-700">
                          {application.last_note}
                        </div>
                      ) : null}
                    </div>
                  ))}
                </div>
              ) : (
                <div className="rounded-[24px] border border-dashed border-stone-300 bg-stone-50/80 px-6 py-8 text-center text-stone-600">
                  No grant applications yet. Visit the Grants page when you are ready to submit a new application.
                </div>
              )}
              <div className="mt-5 flex justify-center">
                <Button
                  type="button"
                  variant="outline"
                  className="min-h-[3rem] rounded-full border-stone-300 px-6 text-black hover:bg-stone-100"
                  onClick={() => navigate('/grants')}
                >
                  View grants page
                </Button>
              </div>
            </InfoCard>
          ) : null}

          <InfoCard
            icon={Receipt}
            title={portalContent.quick_actions_title || 'Quick Actions'}
            description={portalContent.quick_actions_description}
          >
            <div className="space-y-3">
              <Button
                type="button"
                onClick={() => navigate('/account')}
                variant="outline"
                className="w-full justify-start border-stone-300 text-black hover:bg-stone-100"
              >
                <User className="mr-2 h-4 w-4" />
                Update personal information
              </Button>
              <Button
                type="button"
                onClick={() => navigate('/change-password')}
                variant="outline"
                className="w-full justify-start border-stone-300 text-black hover:bg-stone-100"
              >
                <KeyRound className="mr-2 h-4 w-4" />
                Change password
              </Button>
              <Button
                type="button"
                onClick={() => void openMembershipAction('manage_payment')}
                variant="outline"
                className="w-full justify-start border-stone-300 text-black hover:bg-stone-100"
              >
                <CreditCard className="mr-2 h-4 w-4" />
                Manage payment method
              </Button>
              <Button
                type="button"
                onClick={() => navigate('/membership')}
                variant="outline"
                className="w-full justify-start border-stone-300 text-black hover:bg-stone-100"
              >
                <Calendar className="mr-2 h-4 w-4" />
                Review membership levels
              </Button>
              {!membershipActive ? (
                <Button
                  type="button"
                  onClick={() => void openMembershipAction(profileInfo.tier ? 'renew' : 'join', { targetTier: profileInfo.tier || 'Partner' })}
                  className="w-full justify-start bg-[#f8c235] text-black hover:bg-[#dda914]"
                >
                  <Shield className="mr-2 h-4 w-4" />
                  {profileInfo.tier ? 'Renew current membership' : 'Start membership'}
                </Button>
              ) : null}
              <div className="rounded-2xl border border-black/8 bg-stone-50/80 px-4 py-3 text-sm leading-6 text-stone-700">
                PMPro account pages are linked automatically when available. Profile edits still stay inside the AAC app.
              </div>
            </div>
          </InfoCard>
        </div>

      </motion.div>
    </div>
  );
};

export default MemberProfilePage;
