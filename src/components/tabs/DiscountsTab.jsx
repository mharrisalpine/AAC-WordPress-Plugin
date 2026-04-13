import React from 'react';
import { motion } from 'framer-motion';
import { ExternalLink, Tag } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { canAccessDiscounts, getMembershipTier, isFreeMembershipTier } from '@/lib/membershipAccess';
import { getMembershipStatus } from '@/lib/membershipStatus';
import { getPortalUiSettings } from '@/lib/portalSettings';
import { openExternalUrl } from '@/lib/mobileNavigation';
import { getTierDisplayLabel } from '@/lib/membershipTiers';

const normalizeDiscountCards = (cards = []) =>
  (Array.isArray(cards) ? cards : [])
    .map((card, index) => ({
      id: `${card.brand || 'discount'}-${index}`,
      brand: String(card.brand || '').trim(),
      discountPercent: String(card.discount_percent || '').trim(),
      discountCodeText: String(card.discount_code_text || '').trim(),
      supporterCodeText: String(card.discount_code_text_supporter || '').trim(),
      partnerCodeText: String(card.discount_code_text_partner || '').trim(),
      leaderCodeText: String(card.discount_code_text_leader || '').trim(),
      advocateCodeText: String(card.discount_code_text_advocate || '').trim(),
      supporterPercent: String(card.discount_percent_supporter || '').trim(),
      partnerPercent: String(card.discount_percent_partner || '').trim(),
      leaderPercent: String(card.discount_percent_leader || '').trim(),
      advocatePercent: String(card.discount_percent_advocate || '').trim(),
      displayText: String(card.display_text || '').trim(),
      buttonUrl: String(card.button_url || '').trim(),
      imageUrl: String(card.image_url || '').trim(),
    }))
    .filter(
      (card) =>
        card.brand ||
        card.displayText ||
        card.discountPercent ||
        card.discountCodeText ||
        card.supporterCodeText ||
        card.partnerCodeText ||
        card.leaderCodeText ||
        card.advocateCodeText ||
        card.supporterPercent ||
        card.partnerPercent ||
        card.leaderPercent ||
        card.advocatePercent ||
        card.buttonUrl ||
        card.imageUrl
    );

const resolveMembershipDiscountPercent = (card, membershipTier) => {
  switch (membershipTier) {
    case 'Supporter':
      return card.supporterPercent || card.discountPercent;
    case 'Partner':
      return card.partnerPercent || card.discountPercent;
    case 'Leader':
      return card.leaderPercent || card.discountPercent;
    case 'Advocate':
    case 'GRF':
    case 'Lifetime':
      return card.advocatePercent || card.leaderPercent || card.discountPercent;
    default:
      return card.discountPercent;
  }
};

const resolveMembershipDiscountCodeText = (card, membershipTier) => {
  switch (membershipTier) {
    case 'Supporter':
      return card.supporterCodeText || card.discountCodeText;
    case 'Partner':
      return card.partnerCodeText || card.discountCodeText;
    case 'Leader':
      return card.leaderCodeText || card.discountCodeText;
    case 'Advocate':
    case 'GRF':
    case 'Lifetime':
      return card.advocateCodeText || card.leaderCodeText || card.discountCodeText;
    default:
      return card.discountCodeText;
  }
};

const DiscountsTab = ({ profile }) => {
  const portalUi = getPortalUiSettings();
  const portalContent = portalUi.content;
  const portalDesign = portalUi.design;
  const membershipStatus = getMembershipStatus(profile?.profile_info);
  const membershipTier = getMembershipTier(profile?.profile_info);
  const membershipTierLabel = getTierDisplayLabel(membershipTier || 'Supporter', 'Member');
  const isFreeTier = isFreeMembershipTier(profile?.profile_info);
  const isLocked = !canAccessDiscounts(profile?.profile_info);
  const discountCards = normalizeDiscountCards(portalContent.discountCards);

  const handleVisitOffer = async (url) => {
    if (!url) {
      return;
    }

    await openExternalUrl(url);
  };

  return (
    <div className="py-6">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
      >
        <h2 className="mb-6 text-3xl font-bold text-black">{portalContent.discounts_title}</h2>

        {isLocked ? (
          <div className="max-w-2xl rounded-2xl border border-stone-200 bg-white/70 p-6 text-center">
            <p className="mb-2 text-xl font-bold text-black">{portalContent.discounts_locked_title}</p>
            <p className="text-black/75">
              {isFreeTier
                ? portalContent.discounts_free_locked_description
                : portalContent.discounts_locked_description}
            </p>
            {membershipStatus !== 'Active' && !isFreeTier ? null : (
              <p className="mt-3 text-sm text-black/55">
                {portalContent.discounts_upgrade_hint}
              </p>
            )}
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            {discountCards.map((card, index) => {
              const membershipPercent = resolveMembershipDiscountPercent(card, membershipTier);
              const membershipCodeText = resolveMembershipDiscountCodeText(card, membershipTier);

              return (
              <motion.article
                key={card.id}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: index * 0.08 }}
                className="card-gradient flex h-full flex-col overflow-hidden rounded-[20px] border border-stone-200 shadow-[0_14px_34px_rgba(15,23,42,0.08)]"
              >
                <div className="relative aspect-[0.95] overflow-hidden bg-stone-100">
                  {card.imageUrl ? (
                    <div className="flex h-full w-full items-center justify-center bg-[linear-gradient(160deg,#f7f3ea,#ece2ca)] p-3">
                      <img
                        src={card.imageUrl}
                        alt={card.brand || 'AAC discount partner'}
                        className="h-full w-full object-contain"
                      />
                    </div>
                  ) : (
                    <div className="flex h-full w-full items-center justify-center bg-[linear-gradient(160deg,#f2ead8,#e4d2ac)]">
                      <Tag className="h-10 w-10 text-stone-500" />
                    </div>
                  )}
                  {membershipPercent || card.discountPercent ? (
                    <div className="absolute right-3 top-3 rounded-[0.9rem] bg-[#8f1515] px-3 py-2 text-center text-white shadow-lg">
                      <p className="text-[0.56rem] font-semibold uppercase tracking-[0.18em] text-white/80">{membershipTierLabel}</p>
                      <p className="mt-1 text-lg font-bold leading-none">{membershipPercent || card.discountPercent}</p>
                    </div>
                  ) : null}
                </div>

                <div className="flex flex-1 flex-col px-4 py-4">
                  <div>
                    <p className="text-[0.62rem] font-semibold uppercase tracking-[0.16em] text-[#8f1515]">Partner Offer</p>
                    <h3 className="mt-2 text-lg font-bold leading-tight text-black">{card.brand || 'AAC Partner'}</h3>
                    {card.displayText ? (
                      <p className="mt-2.5 text-[0.82rem] leading-5 text-black/70">{card.displayText}</p>
                    ) : null}
                    {membershipCodeText ? (
                      <div className="mt-3 rounded-[0.9rem] border border-[#8f1515]/12 bg-[#8f1515]/6 px-3 py-2.5">
                        <p className="text-[0.56rem] font-semibold uppercase tracking-[0.16em] text-[#8f1515]">
                          Discount Code
                        </p>
                        <p className="mt-1 text-[0.8rem] font-medium leading-5 text-black/80 whitespace-pre-line">{membershipCodeText}</p>
                      </div>
                    ) : null}
                  </div>

                  <div className="mt-auto pt-4">
                    <Button
                      type="button"
                      className="w-full text-[0.72rem] uppercase tracking-[0.12em]"
                      style={{
                        backgroundColor: portalDesign.primaryActionBackground,
                        color: portalDesign.primaryActionText,
                      }}
                      onClick={() => void handleVisitOffer(card.buttonUrl)}
                      disabled={!card.buttonUrl}
                    >
                      {portalContent.discounts_button_label}
                      <ExternalLink className="ml-2 h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </motion.article>
              );
            })}
          </div>
        )}
      </motion.div>
    </div>
  );
};

export default DiscountsTab;
