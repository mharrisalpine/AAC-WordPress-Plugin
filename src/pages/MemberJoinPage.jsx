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
const CHECKOUT_BACK_MESSAGE = 'aac-pmpro-checkout-back';
const CHECKOUT_SCROLL_TOP_MESSAGE = 'aac-pmpro-checkout-scroll-top';
const JOIN_WIZARD_VISIBILITY_EVENT = 'aac-join-wizard-visibility';
const POST_PURCHASE_LOGIN_URL = mainSiteHref('/membership/#/login?purchase_success=1');
const JOIN_HERO_TITLE = 'United\nWe Climb.';
const buildEmbeddedCheckoutUrl = (tierId, initialWizardStep = '', nonceBust = '') => {
  const normalizedTier = normalizeTierId(tierId);
  const levelId = getPmproLevelIdForTier(normalizedTier);
  const query = new URLSearchParams({
    level: String(levelId),
    aac_embed: '1',
    aac_rev: '276',
  });
  if (initialWizardStep) {
    query.set('aac_wizard_step', initialWizardStep);
  }
  if (nonceBust) {
    query.set('aac_nonce_bust', nonceBust);
  }

  return mainSiteHref(`/membership-checkout/?${query.toString()}`);
};

const MemberJoinPage = () => {
  const [selectedTierId, setSelectedTierId] = useState('Partner');
  const [showCheckoutFlow, setShowCheckoutFlow] = useState(false);
  const [checkoutNonceBust, setCheckoutNonceBust] = useState('');
  const [embedHeight, setEmbedHeight] = useState(1440);
  const checkoutFrameRef = useRef(null);
  const checkoutFlowRef = useRef(null);
  const portalUiSettings = getPortalUiSettings();
  const portalContent = portalUiSettings.content;
  const portalDesign = portalUiSettings.design;
  const joinHeroVideoUrl = portalDesign.joinHeroVideoUrl;
  const signupSurfaceColor = showCheckoutFlow ? '#ffffff' : '#f6f4ef';

  const selectedTier = useMemo(() => getTierById(selectedTierId), [selectedTierId]);
  const checkoutUrl = useMemo(
    () => buildEmbeddedCheckoutUrl(selectedTierId, 'account', checkoutNonceBust),
    [selectedTierId, checkoutNonceBust],
  );
  const heroOverlayOpacity = joinHeroVideoUrl ? 0.2 : 1;
  const heroTintOpacity = joinHeroVideoUrl ? 0.12 : 1;

  useEffect(() => {
    const scrollCheckoutFlowToTop = (behavior = 'smooth') => {
      const target = checkoutFlowRef.current;
      if (!target) {
        return;
      }

      const headerHeightValue = typeof window !== 'undefined'
        ? Number.parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--aac-portal-header-height') || '0')
        : 0;
      const top = window.scrollY + target.getBoundingClientRect().top - Math.max(headerHeightValue + 20, 20);

      window.scrollTo({
        top: Math.max(0, top),
        behavior,
      });
    };

    const handleMessage = (event) => {
      if (event.origin !== window.location.origin) {
        return;
      }

      if (event.data?.type === CHECKOUT_BACK_MESSAGE) {
        setShowCheckoutFlow(false);
        window.requestAnimationFrame(() => {
          document.getElementById('membership-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        return;
      }

      if (event.data?.type === CHECKOUT_SCROLL_TOP_MESSAGE) {
        window.requestAnimationFrame(() => {
          scrollCheckoutFlowToTop('smooth');
        });
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

  useEffect(() => {
    if (typeof document !== 'undefined') {
      document.body.classList.toggle('aac-join-wizard-active', showCheckoutFlow);
    }

    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent(JOIN_WIZARD_VISIBILITY_EVENT, {
        detail: { active: showCheckoutFlow },
      }));
    }

    return () => {
      if (typeof document !== 'undefined') {
        document.body.classList.remove('aac-join-wizard-active');
      }
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent(JOIN_WIZARD_VISIBILITY_EVENT, {
          detail: { active: false },
        }));
      }
    };
  }, [showCheckoutFlow]);

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

  const handleContinueToCheckout = () => {
    setCheckoutNonceBust(String(Date.now()));
    setShowCheckoutFlow(true);
    window.requestAnimationFrame(() => {
      const target = checkoutFlowRef.current;
      if (!target) {
        return;
      }

      const headerHeightValue = Number.parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--aac-portal-header-height') || '0');
      const top = window.scrollY + target.getBoundingClientRect().top - Math.max(headerHeightValue + 20, 20);
      window.scrollTo({
        top: Math.max(0, top),
        behavior: 'smooth',
      });
    });
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
      <div
        className="min-h-screen"
        style={{ backgroundColor: signupSurfaceColor }}
      >
        {!showCheckoutFlow ? (
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
                    <Link
                      to="/linked-accounts"
                      className="inline-flex min-h-[3rem] items-center justify-center rounded-none border border-white/60 bg-transparent px-6 text-sm font-semibold uppercase tracking-[0.16em] text-white transition-colors hover:border-white hover:bg-white hover:text-black"
                    >
                      {portalContent.join_redeem_code_button_label}
                    </Link>
                  </div>
                </motion.div>
              </div>
            </div>
          </div>
        </section>
        ) : null}

        <div
          className={`w-full ${showCheckoutFlow ? 'px-0 pb-8 pt-3 sm:px-6 sm:pt-7 sm:pb-10 xl:px-8 2xl:px-10' : 'px-4 py-10 sm:px-6 sm:py-14 xl:px-8 2xl:px-10'}`}
          style={{ backgroundColor: signupSurfaceColor }}
        >
          <motion.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45 }}
            className={showCheckoutFlow ? 'flex flex-col' : ''}
          >
            {!showCheckoutFlow ? (
              <div className="mb-8 sm:mb-10">
                <div>
                  <p className="text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#f8c235]">{portalContent.join_application_kicker}</p>
                  <h2 className="mt-2 text-3xl text-[#030000] sm:text-4xl">
                    {portalContent.join_application_title}
                  </h2>
                </div>
              </div>
            ) : null}

            <div
              id="membership-form"
              className={showCheckoutFlow ? 'flex flex-col space-y-6 text-[#030000]' : 'space-y-6 text-[#030000]'}
            >
              <div
                className="p-0 text-[#030000]"
                style={{ display: showCheckoutFlow ? 'none' : 'block' }}
              >
                <p className="mb-4 text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-stone-600">Membership level</p>
                <MembershipTierSelect
                  variant="full"
                  selectedId={selectedTierId}
                  onSelect={setSelectedTierId}
                />
                <div className="mt-6 flex justify-center">
                  <Button
                    type="button"
                    onClick={handleContinueToCheckout}
                    className="min-h-[3rem] px-6 text-sm font-semibold uppercase tracking-[0.16em]"
                    style={{
                      backgroundColor: portalDesign.primaryActionBackground,
                      color: portalDesign.primaryActionText,
                    }}
                  >
                    Continue
                  </Button>
                </div>
              </div>

              <div
                ref={checkoutFlowRef}
                className="mx-auto min-h-0 w-full max-w-[88rem]"
                style={{ display: showCheckoutFlow ? 'block' : 'none' }}
              >
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
