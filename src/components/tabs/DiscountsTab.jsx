
import React, { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import DiscountModal from '@/components/DiscountModal';
import { canAccessDiscounts, isFreeMembershipTier } from '@/lib/membershipAccess';
import { getMembershipStatus } from '@/lib/membershipStatus';

const PARTNERS_STORAGE_KEY = 'aac_partners';
const PARTNERS_STORAGE_VERSION_KEY = 'aac_partners_version';
const PARTNERS_STORAGE_VERSION = '2';

const DEFAULT_PARTNERS = [
  {
    id: 1,
    name: 'Patagonia',
    discount: '20%',
    code: 'AAC20',
    url: 'https://www.patagonia.com',
    logo_url: 'https://images.unsplash.com/photo-1522163182402-834f871fd851?auto=format&fit=crop&w=900&q=80',
    description: 'Premium outdoor clothing and gear for climbers and adventurers.',
  },
  {
    id: 2,
    name: 'The North Face',
    discount: '15%',
    code: 'AAC15',
    url: 'https://www.thenorthface.com',
    logo_url: 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=900&q=80',
    description: 'High-performance outdoor apparel and equipment.',
  },
  {
    id: 3,
    name: 'Black Diamond',
    discount: '25%',
    code: 'AAC25',
    url: 'https://www.blackdiamondequipment.com',
    logo_url: 'https://images.unsplash.com/photo-1526491109672-74740652b963?auto=format&fit=crop&w=900&q=80',
    description: 'Premium climbing gear, harnesses, and safety equipment.',
  },
  {
    id: 4,
    name: 'Arc\'teryx',
    discount: '18%',
    code: 'AAC18',
    url: 'https://arcteryx.com',
    logo_url: 'https://images.unsplash.com/photo-1519904981063-b0cf448d479e?auto=format&fit=crop&w=900&q=80',
    description: 'Technical outdoor apparel designed for extreme conditions.',
  },
  {
    id: 5,
    name: 'Petzl',
    discount: '20%',
    code: 'AAC20P',
    url: 'https://www.petzl.com',
    logo_url: 'https://images.unsplash.com/photo-1516939884455-1445c8652f83?auto=format&fit=crop&w=900&q=80',
    description: 'Climbing helmets, headlamps, and safety equipment.',
  },
  {
    id: 6,
    name: 'La Sportiva',
    discount: '22%',
    code: 'AAC22',
    url: 'https://www.lasportiva.com',
    logo_url: 'https://images.unsplash.com/photo-1520639888713-7851133b1ed0?auto=format&fit=crop&w=900&q=80',
    description: 'High-performance climbing and approach shoes.',
  },
];

const DiscountsTab = ({ profile }) => {
  const [partners, setPartners] = useState([]);
  const [selectedPartner, setSelectedPartner] = useState(null);
  const membershipStatus = getMembershipStatus(profile?.profile_info);
  const isFreeTier = isFreeMembershipTier(profile?.profile_info);
  const isLocked = !canAccessDiscounts(profile?.profile_info);

  useEffect(() => {
    const storedPartners = localStorage.getItem(PARTNERS_STORAGE_KEY);
    const storedVersion = localStorage.getItem(PARTNERS_STORAGE_VERSION_KEY);

    if (storedPartners && storedVersion === PARTNERS_STORAGE_VERSION) {
      try {
        const parsedPartners = JSON.parse(storedPartners);
        if (Array.isArray(parsedPartners) && parsedPartners.length) {
          setPartners(parsedPartners);
          return;
        }
      } catch (_error) {
        // Fall back to the default AAC partner seed below.
      }
    }

    localStorage.setItem(PARTNERS_STORAGE_KEY, JSON.stringify(DEFAULT_PARTNERS));
    localStorage.setItem(PARTNERS_STORAGE_VERSION_KEY, PARTNERS_STORAGE_VERSION);
    setPartners(DEFAULT_PARTNERS);
  }, []);

  return (
    <div className="py-6">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
      >
        <h2 className="text-3xl font-bold mb-6 text-black">Partner Discounts</h2>

        {isLocked ? (
          <div className="max-w-2xl rounded-2xl border border-stone-200 bg-white/70 p-6 text-center">
            <p className="text-xl font-bold text-black mb-2">Discounts Locked</p>
            <p className="text-black/75">
              {isFreeTier
                ? 'Free memberships include portal preview access and promo emails, but partner discounts unlock with a paid membership.'
                : 'Discounts are available to active members only. Renew or rejoin your membership to unlock partner offers.'}
            </p>
            {membershipStatus !== 'Active' && !isFreeTier ? null : (
              <p className="mt-3 text-sm text-black/55">
                Upgrade from Free to Supporter or above whenever you are ready.
              </p>
            )}
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {partners.map((partner, index) => (
              <motion.div
                key={partner.id}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: index * 0.1 }}
                onClick={() => setSelectedPartner(partner)}
                className="card-gradient rounded-xl p-6 border border-stone-200 cursor-pointer red-glow transition-all hover:scale-105"
              >
                <div className="relative mb-4">
                  <img
                    src={partner.logo_url}
                    alt={partner.name}
                    className="w-full h-40 object-cover rounded-lg"
                  />
                  <div className="absolute top-2 right-2 bg-[#B71C1C] text-white rounded-full w-16 h-16 flex items-center justify-center font-bold shadow-lg">
                    {partner.discount}<br/>OFF
                  </div>
                </div>

                <h3 className="text-xl font-bold text-black mb-2">{partner.name}</h3>
                <p className="text-black/70 text-sm line-clamp-2">{partner.description}</p>

                <div className="mt-4 pt-4 border-t border-stone-200">
                  <p className="text-black/55 text-xs">Click to view discount code</p>
                </div>
              </motion.div>
            ))}
          </div>
        )}
      </motion.div>

      {!isLocked && (
        <DiscountModal
          partner={selectedPartner}
          onClose={() => setSelectedPartner(null)}
        />
      )}
    </div>
  );
};

export default DiscountsTab;
  
