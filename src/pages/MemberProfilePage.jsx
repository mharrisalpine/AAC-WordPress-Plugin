import React from 'react';
import { motion } from 'framer-motion';
import { Calendar, CheckCircle2, CreditCard, FileText, KeyRound, MapPin, Receipt, Shield, User, Users } from 'lucide-react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import MembershipCard from '@/components/MembershipCard';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/hooks/useAuth';
import { useMembershipActions } from '@/hooks/useMembershipActions';
import { useToast } from '@/components/ui/use-toast';
import { formatGrantApplicationDate, grantStatusClassName, normalizeGrantApplications } from '@/lib/grants';
import { scheduleLinkedAccountRemoval } from '@/lib/memberApi';
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

const formatAddress = (accountInfo = {}) => {
  const parts = [
    accountInfo.street,
    accountInfo.address2,
    [accountInfo.city, accountInfo.state].filter(Boolean).join(', '),
    [accountInfo.zip, accountInfo.country].filter(Boolean).join(' '),
  ].filter(Boolean);

  return parts.join(', ');
};

const formatBenefitCurrency = (amount) => (
  Number(amount || 0) > 0 ? `$${Number(amount).toLocaleString()}` : 'Not included'
);

const formatBenefitBoolean = (value) => (value ? 'Included' : 'Not included');

const formatMembershipDate = (value, fallback = 'Not scheduled') => {
  if (!value) {
    return fallback;
  }

  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? fallback : parsed.toLocaleDateString();
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
  const { openMembershipAction, getMembershipActionUrl } = useMembershipActions();
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
  const grantApplications = normalizeGrantApplications(profile.grant_applications);
  const connectedAccounts = Array.isArray(profile.connected_accounts) ? profile.connected_accounts : [];
  const familyMembership = profile.family_membership || { mode: '', additional_adult: false, dependent_count: 0 };
  const linkedParentAccount = profile.linked_parent_account || null;
  const membershipStatus = getMembershipStatus(profileInfo);
  const membershipActive = isMembershipActive(profileInfo);
  const membershipTierLabel = getTierDisplayLabel(profileInfo.tier, 'Free');
  const managePaymentUrl = getMembershipActionUrl('manage_payment');
  const paymentMethodLabel = accountInfo.payment_method || 'No card on file';
  const autoRenewEnabled = Boolean(accountInfo.auto_renew);
  const renewalDateLabel = autoRenewEnabled ? formatMembershipDate(profileInfo.renewal_date) : 'Not scheduled';
  const expirationDateLabel = autoRenewEnabled
    ? 'Not scheduled'
    : formatMembershipDate(profileInfo.expiration_date, 'Not scheduled');
  const linkedSuccess = new URLSearchParams(location.search).get('linked') === '1';
  const canManageConnectedAccounts = !linkedParentAccount;

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
    <div className="py-6">
      <motion.div
        initial={{ opacity: 0, y: 18 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45 }}
        className="space-y-6"
      >
        <MembershipCard profile={profile} />

        <div className="grid gap-6 xl:grid-cols-2">
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
                className="min-h-[3.125rem] rounded-full px-7 shadow-sm"
                style={{
                  backgroundColor: portalDesign.primaryActionBackground,
                  color: portalDesign.primaryActionText,
                }}
                onClick={() => navigate('/account')}
              >
                {portalContent.update_profile_button_label}
              </Button>
            </div>
          </InfoCard>

          <InfoCard
            icon={Shield}
            title={portalContent.membership_snapshot_title}
            description={portalContent.membership_snapshot_description}
          >
            <div className="space-y-1">
              <DetailRow label="Member ID" value={profileInfo.member_id} />
              <StatusRow status={membershipStatus} />
              <DetailRow label="Membership Level" value={membershipTierLabel} />
              <DetailRow label="Renewal Date" value={renewalDateLabel} />
              <DetailRow label="Expiration Date" value={expirationDateLabel} />
              <DetailRow label="Rescue Coverage" value={formatBenefitCurrency(benefitsInfo.rescue_amount)} />
              <DetailRow label="Medical Coverage" value={formatBenefitCurrency(benefitsInfo.medical_amount)} />
              <DetailRow label="Mortal Remains Transport" value={formatBenefitCurrency(benefitsInfo.mortal_remains_amount)} />
              <DetailRow label="Rescue Reimbursement Process" value={formatBenefitBoolean(benefitsInfo.rescue_reimbursement_process)} />
            </div>
          </InfoCard>
        </div>

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
          ) : (
            <div className="rounded-[20px] border border-dashed border-stone-200 bg-stone-50/80 px-4 py-4 text-sm leading-6 text-stone-600">
              Redeem a family invite code to connect a linked household account, or enable the family option on a Partner membership to generate codes for additional adults and dependents.
            </div>
          )}

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

        <div className="grid gap-6 xl:grid-cols-[1.15fr,0.85fr]">
          <InfoCard
            icon={MapPin}
            title={portalContent.portal_preferences_title || 'Portal Preferences'}
            description={portalContent.portal_preferences_description}
          >
            <div className="space-y-1">
              <DetailRow label="Auto Renew" value={accountInfo.auto_renew ? 'Enabled' : 'Disabled'} />
              <DetailRow
                label="Card on File"
                value={managePaymentUrl ? (
                  <button
                    type="button"
                    onClick={() => void openMembershipAction('manage_payment')}
                    className="text-sm font-medium text-[#8a6a19] transition hover:text-[#5f470f] hover:underline underline-offset-4"
                  >
                    {paymentMethodLabel}
                  </button>
                ) : paymentMethodLabel}
              />
              <DetailRow label="Billing Portal" value={getMembershipActionUrl('manage_payment') ? 'Available' : 'Not configured'} />
              <DetailRow label="Cancellation" value={getMembershipActionUrl('cancel') ? 'Available' : 'Not configured'} />
            </div>
          </InfoCard>

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

        <InfoCard
          icon={FileText}
          title="Grant Applications"
          description={portalContent.grant_applications_description}
        >
          {grantApplications.length ? (
            <div className="space-y-3">
              {grantApplications.map((application) => (
                <div
                  key={application.id}
                  className="flex flex-col gap-4 rounded-[22px] border border-stone-200 bg-white px-5 py-4 md:flex-row md:items-center md:justify-between"
                >
                  <div>
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-[#8a6a19]">
                      {application.category || 'Grant'}
                    </p>
                    <h3 className="mt-1 text-lg font-semibold text-stone-900">{application.grant_name}</h3>
                    <p className="mt-1 text-sm text-stone-600">
                      Applied {formatGrantApplicationDate(application.application_date)}
                    </p>
                  </div>
                  <span
                    className={cn(
                      'inline-flex items-center rounded-full px-3 py-1.5 text-sm font-semibold',
                      grantStatusClassName(application.status),
                    )}
                  >
                    {application.status}
                  </span>
                </div>
              ))}
            </div>
          ) : (
            <div className="rounded-2xl border border-dashed border-stone-300 bg-stone-50/80 px-5 py-6 text-sm leading-6 text-stone-700">
              No grant applications yet. Start one from the Grants page in the left navigation.
            </div>
          )}
        </InfoCard>
      </motion.div>
    </div>
  );
};

export default MemberProfilePage;
