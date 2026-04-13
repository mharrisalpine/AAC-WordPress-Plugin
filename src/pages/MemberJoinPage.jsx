import React, { useEffect, useMemo, useRef, useState } from 'react';
import { motion } from 'framer-motion';
import { Helmet } from 'react-helmet';
import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { MembershipTierSelect } from '@/components/MembershipTierSelect';
import { getPmproLevelIdForTier, getTierById, normalizeTierId } from '@/lib/membershipTiers';
import { mainSiteHref } from '@/lib/mainWebsiteNav';
import { getPortalUiSettings } from '@/lib/portalSettings';

const CHECKOUT_EMBED_MESSAGE = 'aac-pmpro-checkout-height';
const POST_PURCHASE_LOGIN_URL = mainSiteHref('/membership/#/login?purchase_success=1');
const JOIN_HERO_VIDEO_URL =
  'https://player.vimeo.com/video/1166009381?h=c4c3248b38&background=1&autoplay=1&muted=1&loop=1&autopause=0&controls=0&title=0&byline=0&portrait=0';
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

  const selectedTier = useMemo(() => getTierById(selectedTierId), [selectedTierId]);
  const checkoutUrl = useMemo(() => buildEmbeddedCheckoutUrl(selectedTierId), [selectedTierId]);

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
      <div className="min-h-screen topo-lines">
        <section className="hero-break relative min-h-[calc(100svh-5.5rem)] overflow-hidden bg-[#030000] text-white">
          <div className="absolute inset-0">
            <iframe
              title="AAC signup hero video"
              src={JOIN_HERO_VIDEO_URL}
              className="pointer-events-none absolute inset-0 h-full w-full scale-[1.32] transform-gpu"
              frameBorder="0"
              allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share"
              referrerPolicy="strict-origin-when-cross-origin"
              allowFullScreen
            />
          </div>
          <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(3,0,0,0.88)_0%,rgba(3,0,0,0.72)_38%,rgba(3,0,0,0.4)_62%,rgba(3,0,0,0.58)_100%)]" />
          <div className="absolute inset-0 bg-gradient-to-t from-[#030000]/56 via-transparent to-[#030000]/18" />

          <div className="relative flex min-h-[calc(100svh-5.5rem)] items-end px-4 py-12 sm:px-6 sm:py-16 lg:px-10 xl:px-14 xl:py-20">
            <div className="flex w-full items-end">
              <div className="w-full max-w-5xl">
                <motion.div
                  initial={{ opacity: 0, y: 18 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.45 }}
                  className="max-w-3xl bg-black/34 px-6 py-7 sm:px-8 sm:py-8"
                >
                  <p className="text-[0.72rem] font-semibold uppercase tracking-[0.3em] text-[#f8c235]">{portalContent.join_hero_kicker}</p>
                  <h1 className="mt-4 max-w-3xl text-5xl leading-[0.95] text-white sm:text-6xl lg:text-7xl xl:text-[5.75rem]">
                    {portalContent.join_hero_title}
                  </h1>
                  <p className="mt-5 max-w-2xl text-base leading-7 text-white/80 sm:text-lg">
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

        <div className="w-full bg-[#f7f1e3] px-4 py-10 sm:px-6 sm:py-14 xl:px-8 2xl:px-10">
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
              <div className="paper-panel rounded-[1.6rem] p-5 text-[#030000] sm:p-8 lg:p-10">
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
                <div className="overflow-hidden rounded-[1.6rem] border border-black/10 bg-white shadow-[0_24px_70px_rgba(0,0,0,0.24)]">
                  <iframe
                    ref={checkoutFrameRef}
                    key={checkoutUrl}
                    title={`${selectedTier.label} membership checkout`}
                    src={checkoutUrl}
                    onLoad={handleCheckoutFrameLoad}
                    className="block w-full bg-white"
                    style={{ height: `${embedHeight}px`, border: 0 }}
                  />
                </div>
              </div>
            </div>
          </motion.div>
        </div>
      </div>
    </>
  );
};

export default MemberJoinPage;
