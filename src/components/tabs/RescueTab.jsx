
import React, { useState } from 'react';
import { motion } from 'framer-motion';
import { Phone, Mail, FileText, Shield, ArrowUpCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import RescuePolicyModal from '@/components/RescuePolicyModal';
import { openEmailAddress, openPhoneNumber } from '@/lib/mobileNavigation';
import { isFreeMembershipTier } from '@/lib/membershipAccess';
import { getMembershipStatus } from '@/lib/membershipStatus';
import { useNavigate } from 'react-router-dom';

const rescuePolicyContent = {
  title: "RedPoint Rescue Policy Details",
  content: [
    {
      heading: "1. Coverage Overview",
      text: "This policy provides coverage for reasonable and customary expenses incurred for search, rescue, and evacuation services required due to a covered incident during your travels. Coverage is available worldwide, for climbers, skiers, and outdoor enthusiasts.",
    },
    {
      heading: "2. Covered Activities",
      text: "Activities such as mountaineering, rock climbing, backcountry skiing/snowboarding, hiking, and trekking are covered. Activities must be conducted in a non-professional capacity. Exclusions apply for certain high-risk activities unless specified.",
    },
    {
      heading: "3. Evacuation & Medical Assistance",
      text: "In the event of a medical emergency, RedPoint will coordinate and pay for evacuation to the nearest appropriate medical facility. This includes medical monitoring and assistance with hospital admission.",
    },
    {
      heading: "4. Reporting an Incident",
      text: "All incidents must be reported to RedPoint Resolutions as soon as is reasonably possible. Use the emergency contact information provided in this app. Failure to contact RedPoint in a timely manner may limit or negate coverage.",
    },
    {
      heading: "5. Limitations & Exclusions",
      text: "This is a summary of benefits. Please refer to the full policy document for a complete list of terms, conditions, limitations, and exclusions. This is not an insurance policy but a service membership plan.",
    },
  ]
};

const formatCurrency = (amount) => {
  if (typeof amount !== 'number') return '$0';
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
};

const RescueTab = ({ profile }) => {
  const [isPolicyModalOpen, setIsPolicyModalOpen] = useState(false);
  const navigate = useNavigate();

  const profileInfo = profile?.profile_info || {};
  const benefits = profile?.benefits_info || {};
  const membershipStatus = getMembershipStatus(profileInfo);
  const isFreeTier = isFreeMembershipTier(profileInfo);
  const hasRescueBenefits = (benefits?.rescue_amount || 0) > 0 || (benefits?.medical_amount || 0) > 0;

  const handleCallRescue = () => {
    openPhoneNumber('+14154810600');
  };

  const handleEmailRescue = () => {
    openEmailAddress('memberservices@redpointresolutions.com');
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
          <h2 className="text-3xl font-bold mb-4 text-black">Membership Inactive</h2>
          <Shield className="w-20 h-20 text-[#B71C1C] mx-auto my-6" />
          <p className="text-black/75 text-lg mb-8">
            Redpoint rescue and medical benefits are only available to active members.
          </p>
          <Button
            onClick={() => navigate('/membership')}
            className="bg-[#B71C1C] hover:bg-[#D32F2F] text-white h-14 text-lg font-bold w-full"
          >
            <ArrowUpCircle className="w-6 h-6 mr-2" />
            Manage Membership
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
            Free memberships are for portal preview access and promo emails only. Upgrade to a paid tier to unlock rescue and medical coverage.
          </p>
          <Button
            onClick={() => navigate('/membership')}
            className="bg-[#B71C1C] hover:bg-[#D32F2F] text-white h-14 text-lg font-bold w-full"
          >
            <ArrowUpCircle className="w-6 h-6 mr-2" />
            Manage Membership
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
          <h2 className="text-3xl font-bold mb-4 text-black">Unlock Rescue Benefits</h2>
          <Shield className="w-20 h-20 text-[#B71C1C] mx-auto my-6" />
          <p className="text-black/75 text-lg mb-8">
            Upgrade your membership to unlock crucial rescue and medical coverage.
          </p>
          <Button
            onClick={() => navigate('/membership')}
            className="bg-[#B71C1C] hover:bg-[#D32F2F] text-white h-14 text-lg font-bold w-full"
          >
            <ArrowUpCircle className="w-6 h-6 mr-2" />
            Manage Membership
          </Button>
        </motion.div>
      </div>
    );
  }

  if (!benefits || benefits.rescue_amount === undefined || benefits.medical_amount === undefined) {
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
            We couldn't load the benefit details for your tier at this moment. Please check back later or contact support.
          </p>
        </motion.div>
      </div>
    );
  }

  return (
    <>
      <div className="py-6">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
        >
          <h2 className="text-3xl font-bold mb-6 text-black">Rescue Insurance</h2>

          <div className="max-w-2xl mx-auto space-y-6">
            <div className="card-gradient rounded-2xl p-6 border border-stone-200 shadow-xl">
              <div className="flex items-center gap-3 mb-6 pb-4 border-b border-[#B71C1C]">
                <Shield className="w-8 h-8 text-[#B71C1C]" />
                <h3 className="text-2xl font-bold text-black">RedPoint Rescue Coverage</h3>
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
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="bg-[#0B0B0B] rounded-lg p-4">
                    <p className="text-[#E0E0E0] text-sm mb-1">Status</p>
                    <p className={`${membershipStatus === 'Active' ? 'text-green-500' : 'text-red-500'} font-bold`}>{membershipStatus}</p>
                  </div>

                  <div className="bg-[#0B0B0B] rounded-lg p-4">
                    <p className="text-[#E0E0E0] text-sm mb-1">Benefit ID</p>
                    <p className="text-white font-mono text-sm">{profileInfo.member_id}</p>
                  </div>
                </div>

                <div className="bg-[#0B0B0B] rounded-lg p-4">
                  <p className="text-[#E0E0E0] text-sm mb-1">Membership Tier</p>
                  <p className="text-white font-bold">{profileInfo.tier}</p>
                </div>
              </div>
            </div>

            <div className="card-gradient rounded-2xl p-6 border border-stone-200 shadow-xl">
              <h3 className="text-xl font-bold text-black mb-4">Emergency Contact</h3>
              
              <div className="space-y-3">
                <div className="flex items-center gap-3 bg-[#0B0B0B] rounded-lg p-4">
                  <Phone className="w-5 h-5 text-[#B71C1C]" />
                  <div>
                    <p className="text-[#E0E0E0] text-sm">Phone</p>
                    <button onClick={handleCallRescue} className="text-white font-medium hover:text-[#B71C1C] transition-colors">
                      +1 (415) 481-0600
                    </button>
                  </div>
                </div>

                <div className="flex items-center gap-3 bg-[#0B0B0B] rounded-lg p-4">
                  <Mail className="w-5 h-5 text-[#B71C1C]" />
                  <div>
                    <p className="text-[#E0E0E0] text-sm">Email</p>
                    <button onClick={handleEmailRescue} className="text-left text-white font-medium hover:text-[#B71C1C] transition-colors break-all">
                      memberservices@redpointresolutions.com
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Button
                onClick={handleCallRescue}
                className="bg-[#B71C1C] hover:bg-[#D32F2F] text-white h-14 text-lg font-bold"
              >
                <Phone className="w-5 h-5 mr-2" />
                Call for Rescue
              </Button>

              <Button
                onClick={() => setIsPolicyModalOpen(true)}
                variant="outline"
                className="border-stone-400 text-black hover:bg-stone-100 h-14 text-lg"
              >
                <FileText className="w-5 h-5 mr-2" />
                View Policy
              </Button>
            </div>
          </div>
        </motion.div>
      </div>
      <RescuePolicyModal 
        isOpen={isPolicyModalOpen}
        onClose={() => setIsPolicyModalOpen(false)}
        policy={rescuePolicyContent}
      />
    </>
  );
};

export default RescueTab;
  
