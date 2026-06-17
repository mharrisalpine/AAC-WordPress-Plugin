import React, { useEffect, useMemo, useRef, useState } from 'react';
import { motion } from 'framer-motion';
import { Helmet } from 'react-helmet';
import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { MembershipTierSelect } from '@/components/MembershipTierSelect';
import { getPmproLevelIdForTier, getTierById, normalizeTierId } from '@/lib/membershipTiers';
import { mainSiteHref } from '@/lib/mainWebsiteNav';
import { getPortalUiSettings } from '@/lib/portalSettings';
import grandTetonHero from '@/assets/grand-teton-hero.jpg';

const CHECKOUT_EMBED_MESSAGE = 'aac-pmpro-checkout-height';
const POST_PURCHASE_LOGIN_URL = mainSiteHref('/membership/#/login?purchase_success=1');
const JOIN_HERO_TITLE = 'United\nWe Climb.';
const buildEmbeddedCheckoutUrl = (tierId) => {
  const normalizedTier = normalizeTierId(tierId);
  const levelId = getPmproLevelIdForTier(normalizedTier);
  const query = new URLSearchParams({
    level: String(levelId),
    aac_embed: '1',
    aac_rev: '274',
  });

  return mainSiteHref(`/membership-checkout/?${query.toString()}`);
};

const MemberJoinPage = () => {
  const [selectedTierId, setSelectedTierId] = useState('Partner');
  const [embedHeight, setEmbedHeight] = useState(1440);
  const checkoutFrameRef = useRef(null);
  const portalUiSettings = getPortalUiSettings();
  const portalContent = portalUiSettings.content;
  const portalDesign = portalUiSettings.design;
  const joinHeroVideoUrl = portalDesign.joinHeroVideoUrl;
  const signupSurfaceColor = '#ffffff';

  const selectedTier = useMemo(() => getTierById(selectedTierId), [selectedTierId]);
  const checkoutUrl = useMemo(() => buildEmbeddedCheckoutUrl(selectedTierId), [selectedTierId]);
  const heroOverlayOpacity = joinHeroVideoUrl ? 0.2 : 1;
  const heroTintOpacity = joinHeroVideoUrl ? 0.12 : 1;

  useEffect(() => {
    const handleMessage = (event) => {
      if (event.origin !== window.location.origin) {
        return;
      }

      if (event.data?.type !== CHECKOUT_EMBED_MESSAGE) {
        return;
      }

      const nextHeight = Number(event.data.height);
      if (Number.isFinite(nextHeight) && nextHeight > 0) {
        setEmbedHeight(Math.max(nextHeight + 12, 640));
      }
    };

    window.addEventListener('message', handleMessage);
    return () => window.removeEventListener('message', handleMessage);
  }, []);

  const handleCheckoutFrameLoad = () => {
    const frameWindow = checkoutFrameRef.current?.contentWindow;
    if (!frameWindow) {
      return;
    }

    try {
      const frameUrl = new URL(frameWindow.location.href);
      const postPurchaseUrl = new URL(POST_PURCHASE_LOGIN_URL);
      const isConfirmationPath = frameUrl.pathname.includes('/membership-checkout/membership-confirmation');
      const isFramedProfile =
        frameUrl.pathname === postPurchaseUrl.pathname &&
        (frameUrl.hash === '#/profile' || frameUrl.hash.startsWith('#/profile?'));

      if (isConfirmationPath || isFramedProfile) {
        window.location.assign(postPurchaseUrl.toString());
      }
    } catch (error) {
      // Ignore cross-document timing errors and leave the iframe in place.
    }
  };

  return (
    <>
      <Helmet>
        <title>Join - American Alpine Club</title>
        <meta
          name="description"
          content={portalContent.join_hero_description}
        />
      </Helmet>
      <div className="min-h-screen" style={{ backgroundColor: signupSurfaceColor }}>
        <section
          className="hero-break relative min-h-[100svh] overflow-hidden text-white"
          style={{ backgroundColor: signupSurfaceColor }}
        >
          {joinHeroVideoUrl ? (
            <div className="absolute inset-0">
              <iframe
                title="AAC signup hero video"
                src={joinHeroVideoUrl}
                className="pointer-events-none absolute inset-0 h-full w-full scale-[1.32] transform-gpu"
                frameBorder="0"
                allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share"
                referrerPolicy="strict-origin-when-cross-origin"
                allowFullScreen
              />
            </div>
          ) : (
            <img
              src={grandTetonHero}
              alt=""
              aria-hidden="true"
              className="absolute inset-0 h-full w-full object-cover"
            />
          )}
          <div
            className="absolute inset-0"
            style={{ background: portalDesign.joinHeroOverlay, opacity: heroOverlayOpacity }}
          />
          <div
            className="absolute inset-0"
            style={{ background: portalDesign.joinHeroTintOverlay, opacity: heroTintOpacity }}
          />

          <div className="relative flex min-h-[100svh] items-end px-4 pb-12 pt-[calc(var(--aac-portal-header-height)+1.5rem)] sm:px-6 sm:pb-16 sm:pt-[calc(var(--aac-portal-header-height)+2rem)] lg:px-10 xl:px-14 xl:pb-20 xl:pt-[calc(var(--aac-portal-header-height)+2.5rem)]">
            <div className="flex w-full items-end">
              <div className="w-full max-w-4xl">
                <motion.div
                  initial={{ opacity: 0, y: 18 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.45 }}
                  className="max-w-[42rem] px-1 py-1 sm:px-0 sm:py-0"
                >
                  <p className="text-[0.72rem] font-semibold uppercase tracking-[0.3em] text-[#f8c235]">{portalContent.join_hero_kicker}</p>
                  <h1 className="mt-3 max-w-[38rem] whitespace-pre-line text-[4.4rem] leading-[0.92] text-white sm:text-[5.4rem] lg:text-[6.6rem] xl:text-[7.2rem]">
                    {JOIN_HERO_TITLE}
                  </h1>
                  <p className="mt-5 max-w-[38rem] text-lg leading-8 text-white/88 sm:text-[1.32rem]">
                    {portalContent.join_hero_description}
                  </p>

                  <div className="mt-8 flex flex-wrap gap-3">
                    <a
                      href="https://americanalpine.wpenginepowered.com/learn-more/"
                        className="inline-flex min-h-[3rem] items-center justify-center rounded-none border border-white bg-white px-6 text-sm font-semibold uppercase tracking-[0.16em] text-black transition-colors hover:border-white hover:bg-black hover:text-white"
                    >
                      {portalContent.join_benefits_cta_label}
                    </a>
                    <a
                      href="https://americanalpine.wpenginepowered.com/rescue/"
                      className="inline-flex min-h-[3rem] items-center justify-center rounded-none border border-[#8f1515] bg-[#8f1515] px-6 text-sm font-semibold uppercase tracking-[0.16em] text-white transition-colors hover:border-[#6b1010] hover:bg-[#6b1010]"
                    >
                      {portalContent.join_rescue_cta_label}
                    </a>
                  </div>
                </motion.div>
              </div>
            </div>
          </div>
        </section>

        <div className="w-full px-4 py-10 sm:px-6 sm:py-14 xl:px-8 2xl:px-10" style={{ backgroundColor: signupSurfaceColor }}>
          <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.45 }}>
            <div className="mb-8 sm:mb-10">
              <div>
                <p className="text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#f8c235]">{portalContent.join_application_kicker}</p>
                <h2 className="mt-2 text-3xl text-[#030000] sm:text-4xl">{portalContent.join_application_title}</h2>
              </div>
            </div>

            <div
              id="membership-form"
              className="space-y-6 text-[#030000]"
            >
              <div className="p-0 text-[#030000]">
                <p className="mb-4 text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-stone-600">Membership level</p>
                <MembershipTierSelect
                  variant="full"
                  selectedId={selectedTierId}
                  onSelect={setSelectedTierId}
                />
                <div className="mt-6 flex justify-center">
                  <Button
                    asChild
                    type="button"
                    className="min-h-[3rem] px-6 text-sm font-semibold uppercase tracking-[0.16em]"
                    style={{
                      backgroundColor: portalDesign.primaryActionBackground,
                      color: portalDesign.primaryActionText,
                    }}
                  >
                    <Link to="/linked-accounts">{portalContent.join_redeem_code_button_label}</Link>
                  </Button>
                </div>
              </div>

              <div>
                <iframe
                  ref={checkoutFrameRef}
                  key={checkoutUrl}
                  title={`${selectedTier.label} membership checkout`}
                  src={checkoutUrl}
                  onLoad={handleCheckoutFrameLoad}
                  className="block w-full bg-transparent"
                  style={{ height: `${embedHeight}px`, border: 0 }}
                />
              </div>
            </div>
          </motion.div>
        </div>
      </div>
    </>
  );
};

export default MemberJoinPage;
