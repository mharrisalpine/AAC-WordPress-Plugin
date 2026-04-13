
import React from 'react';
import { motion } from 'framer-motion';
import { Phone, FileText, Shield, ArrowUpCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { openExternalUrl, openPhoneNumber } from '@/lib/mobileNavigation';
import { isFreeMembershipTier } from '@/lib/membershipAccess';
import { getFullName } from '@/lib/memberProfile';
import { getMembershipStatus } from '@/lib/membershipStatus';
import { useNavigate } from 'react-router-dom';
import { getPortalUiSettings } from '@/lib/portalSettings';

const RESCUE_POLICY_PDF_URL =
  'https://static1.squarespace.com/static/55830fd9e4b0ec758c892f81/t/672cf3df9d91737b077434e7/1730999263210/Rescue+Terms+and+Exclusions.pdf';
const MEDICAL_EVACUATION_CLAIM_URL =
  'https://aac-profile.s3.amazonaws.com/website_assets/MedicalEvacuationClaim_AAC_Redpoint.pdf';
const MEDICAL_REIMBURSEMENT_CLAIM_URL =
  'https://aac-profile.s3.amazonaws.com/website_assets/MedicalExpenseClaim_AAC_Redpoint.pdf';

const formatCurrency = (amount) => {
  if (typeof amount !== 'number') return '$0';
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
};

const formatMembershipDate = (value) => {
  if (!value) {
    return 'Not scheduled';
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return 'Not scheduled';
  }

  return parsed.toLocaleDateString();
};

const RescueTab = ({ profile }) => {
  const navigate = useNavigate();
  const portalUiSettings = getPortalUiSettings();
  const portalContent = portalUiSettings.content;
  const portalDesign = portalUiSettings.design;

  const accountInfo = profile?.account_info || {};
  const profileInfo = profile?.profile_info || {};
  const benefits = profile?.benefits_info || {};
  const membershipStatus = getMembershipStatus(profileInfo);
  const isFreeTier = isFreeMembershipTier(profileInfo);
  const memberName = getFullName(accountInfo);
  const expirationDateLabel = formatMembershipDate(
    accountInfo.auto_renew ? profileInfo.renewal_date : profileInfo.expiration_date
  );
  const hasRescueBenefits =
    (benefits?.rescue_amount || 0) > 0 ||
    (benefits?.medical_amount || 0) > 0 ||
    (benefits?.mortal_remains_amount || 0) > 0 ||
    Boolean(benefits?.rescue_reimbursement_process);

  const handleCallRescue = () => {
    openPhoneNumber('+16282511510');
  };

  const handleViewPolicy = async () => {
    await openExternalUrl(RESCUE_POLICY_PDF_URL);
  };

  const handleMedicalEvacuationClaim = async () => {
    await openExternalUrl(MEDICAL_EVACUATION_CLAIM_URL);
  };

  const handleMedicalReimbursementClaim = async () => {
    await openExternalUrl(MEDICAL_REIMBURSEMENT_CLAIM_URL);
  };

  if (!profile) {
    return <div className="text-black text-center pt-10">Loading Rescue Benefits...</div>;
  }
  
  if (membershipStatus !== 'Active') {
    return (
      <div className="py-6">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
          className="text-center max-w-md mx-auto"
        >
          <h2 className="text-3xl font-bold mb-4 text-black">{portalContent.rescue_inactive_title}</h2>
          <Shield className="w-20 h-20 text-[#B71C1C] mx-auto my-6" />
          <p className="text-black/75 text-lg mb-8">
            {portalContent.rescue_inactive_description}
          </p>
          <Button
            onClick={() => navigate('/membership')}
            className="h-14 text-lg font-bold w-full"
            style={{
              backgroundColor: portalDesign.primaryActionBackground,
              color: portalDesign.primaryActionText,
            }}
          >
            <ArrowUpCircle className="w-6 h-6 mr-2" />
            {portalContent.rescue_manage_button_label}
          </Button>
        </motion.div>
      </div>
    );
  }

  if (isFreeTier) {
    return (
      <div className="py-6">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
          className="text-center max-w-md mx-auto"
        >
          <h2 className="text-3xl font-bold mb-4 text-black">Rescue Benefits Start With Paid Membership</h2>
          <Shield className="w-20 h-20 text-[#B71C1C] mx-auto my-6" />
          <p className="text-black/75 text-lg mb-8">
            Free memberships are for portal preview access and promo emails only. Upgrade to a paid membership level to unlock rescue and medical coverage.
          </p>
          <Button
            onClick={() => navigate('/membership')}
            className="h-14 text-lg font-bold w-full"
            style={{
              backgroundColor: portalDesign.primaryActionBackground,
              color: portalDesign.primaryActionText,
            }}
          >
            <ArrowUpCircle className="w-6 h-6 mr-2" />
            {portalContent.rescue_manage_button_label}
          </Button>
        </motion.div>
      </div>
    );
  }
  
  if (profileInfo.tier === 'Supporter' || !hasRescueBenefits) {
    return (
      <div className="py-6">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
          className="text-center max-w-md mx-auto"
        >
          <h2 className="text-3xl font-bold mb-4 text-black">{portalContent.rescue_upgrade_title}</h2>
          <Shield className="w-20 h-20 text-[#B71C1C] mx-auto my-6" />
          <p className="text-black/75 text-lg mb-8">
            {portalContent.rescue_upgrade_description}
          </p>
          <Button
            onClick={() => navigate('/membership')}
            className="h-14 text-lg font-bold w-full"
            style={{
              backgroundColor: portalDesign.primaryActionBackground,
              color: portalDesign.primaryActionText,
            }}
          >
            <ArrowUpCircle className="w-6 h-6 mr-2" />
            {portalContent.rescue_manage_button_label}
          </Button>
        </motion.div>
      </div>
    );
  }

  if (
    !benefits ||
    benefits.rescue_amount === undefined ||
    benefits.medical_amount === undefined ||
    benefits.mortal_remains_amount === undefined ||
    benefits.rescue_reimbursement_process === undefined
  ) {
    return (
      <div className="py-6">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
          className="text-center max-w-md mx-auto"
        >
          <h2 className="text-3xl font-bold mb-4 text-black">Benefits Information Unavailable</h2>
          <Shield className="w-20 h-20 text-stone-500 mx-auto my-6" />
          <p className="text-black/75 text-lg mb-8">
            We couldn't load the benefit details for your membership level at this moment. Please check back later or contact support.
          </p>
        </motion.div>
      </div>
    );
  }

  return (
      <div className="py-6">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
        >
          <h2 className="text-3xl font-bold mb-6 text-black">{portalContent.rescue_title}</h2>

          <div className="max-w-2xl mx-auto space-y-6">
            <div className="card-gradient rounded-2xl p-6 border border-stone-200 shadow-xl">
              <div className="flex items-center gap-3 mb-6 pb-4 border-b border-[#B71C1C]">
                <Shield className="w-8 h-8 text-[#B71C1C]" />
                <h3 className="text-2xl font-bold text-black">{portalContent.rescue_coverage_title}</h3>
              </div>

              <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="bg-[#0B0B0B] rounded-lg p-4">
                        <p className="text-[#E0E0E0] text-sm mb-1">Rescue Coverage</p>
                        <p className="text-[#B71C1C] text-3xl font-bold">{formatCurrency(benefits.rescue_amount)}</p>
                    </div>
                    <div className="bg-[#0B0B0B] rounded-lg p-4">
                        <p className="text-[#E0E0E0] text-sm mb-1">Medical Coverage</p>
                        <p className="text-[#B71C1C] text-3xl font-bold">{formatCurrency(benefits.medical_amount)}</p>
                    </div>
                    <div className="bg-[#0B0B0B] rounded-lg p-4">
                        <p className="text-[#E0E0E0] text-sm mb-1">Mortal Remains Transport</p>
                        <p className="text-[#B71C1C] text-3xl font-bold">{formatCurrency(benefits.mortal_remains_amount)}</p>
                    </div>
                    <div className="bg-[#0B0B0B] rounded-lg p-4">
                        <p className="text-[#E0E0E0] text-sm mb-1">Rescue Reimbursement Process</p>
                        <p className="text-white text-xl font-bold">
                          {benefits.rescue_reimbursement_process ? 'Included' : 'Not included'}
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4">
                  <div className="bg-[#0B0B0B] rounded-lg p-4">
                    <p className="text-[#E0E0E0] text-sm mb-1">Member Name</p>
                    <p className="text-white font-bold">{memberName}</p>
                  </div>
                  <div className="bg-[#0B0B0B] rounded-lg p-4">
                    <p className="text-[#E0E0E0] text-sm mb-1">Status</p>
                    <p className={`${membershipStatus === 'Active' ? 'text-green-500' : 'text-red-500'} font-bold`}>{membershipStatus}</p>
                  </div>
                  <div className="bg-[#0B0B0B] rounded-lg p-4">
                    <p className="text-[#E0E0E0] text-sm mb-1">Expiration Date</p>
                    <p className="text-white font-bold">{expirationDateLabel}</p>
                  </div>
                </div>

                <div className="bg-[#0B0B0B] rounded-lg p-4">
                  <p className="text-[#E0E0E0] text-sm mb-1">Membership Level</p>
                  <p className="text-white font-bold">{profileInfo.tier}</p>
                </div>
              </div>
            </div>

            <div className="card-gradient rounded-2xl p-6 border border-stone-200 shadow-xl">
              <h3 className="text-xl font-bold text-black mb-4">{portalContent.rescue_emergency_title}</h3>
              
              <div className="space-y-3">
                <div className="flex items-center gap-3 bg-[#0B0B0B] rounded-lg p-4">
                  <Phone className="w-5 h-5 text-[#B71C1C]" />
                  <div>
                    <p className="text-[#E0E0E0] text-sm">Phone</p>
                    <button onClick={handleCallRescue} className="text-white font-medium hover:text-[#B71C1C] transition-colors">
                      +1 628-251-1510
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Button
                onClick={handleCallRescue}
                className="h-14 text-lg font-bold"
                style={{
                  backgroundColor: portalDesign.primaryActionBackground,
                  color: portalDesign.primaryActionText,
                }}
              >
                <Phone className="w-5 h-5 mr-2" />
                Call for Rescue
              </Button>

              <Button
                onClick={handleViewPolicy}
                variant="outline"
                className="border-stone-400 h-14 text-lg"
                style={{
                  backgroundColor: 'transparent',
                  color: portalDesign.secondaryActionText,
                  borderColor: portalDesign.secondaryActionBackground,
                }}
              >
                <FileText className="w-5 h-5 mr-2" />
                View Policy
              </Button>
            </div>

            <div className="card-gradient rounded-2xl p-6 border border-stone-200 shadow-xl">
              <div className="flex items-center gap-3 mb-4">
                <FileText className="w-7 h-7 text-[#B71C1C]" />
                <h3 className="text-xl font-bold text-black">{portalContent.rescue_claim_forms_title}</h3>
              </div>
              <p className="text-black/70 mb-5">
                Download the appropriate claim form for medical evacuation or medical reimbursement.
              </p>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Button
                  onClick={handleMedicalEvacuationClaim}
                  className="bg-[#0B0B0B] hover:bg-[#1A1A1A] text-white min-h-[5rem] px-5 text-left justify-start whitespace-normal"
                >
                  <div className="flex flex-col items-start">
                    <span className="text-base font-bold">File a Medical Evacuation Claim</span>
                    <span className="text-sm text-white/75">Open PDF form</span>
                  </div>
                </Button>

                <Button
                  onClick={handleMedicalReimbursementClaim}
                  className="bg-[#0B0B0B] hover:bg-[#1A1A1A] text-white min-h-[5rem] px-5 text-left justify-start whitespace-normal"
                >
                  <div className="flex flex-col items-start">
                    <span className="text-base font-bold">File a Medical Reimbursement Claim</span>
                    <span className="text-sm text-white/75">Open PDF form</span>
                  </div>
                </Button>
              </div>
            </div>
          </div>
        </motion.div>
      </div>
  );
};

export default RescueTab;
  
