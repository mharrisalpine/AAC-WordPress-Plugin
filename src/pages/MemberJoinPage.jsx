import React, { useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import { Helmet } from 'react-helmet';
import { MembershipTierSelect } from '@/components/MembershipTierSelect';
import { getPmproLevelIdForTier, getTierById, normalizeTierId } from '@/lib/membershipTiers';
import { mainSiteHref } from '@/lib/mainWebsiteNav';

const CHECKOUT_EMBED_MESSAGE = 'aac-pmpro-checkout-height';

const buildEmbeddedCheckoutUrl = (tierId) => {
  const normalizedTier = normalizeTierId(tierId);
  const levelId = getPmproLevelIdForTier(normalizedTier);
  const query = new URLSearchParams({
    level: String(levelId),
    aac_embed: '1',
  });

  return mainSiteHref(`/membership-checkout/?${query.toString()}`);
};

const MemberJoinPage = () => {
  const [selectedTierId, setSelectedTierId] = useState('Partner');
  const [embedHeight, setEmbedHeight] = useState(1440);

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

  return (
    <>
      <Helmet>
        <title>Join - American Alpine Club</title>
        <meta
          name="description"
          content="Choose your AAC membership level and complete your application using the live PMPro checkout form."
        />
      </Helmet>
      <div className="min-h-screen topo-lines">
        <section className="hero-break overflow-hidden bg-[#030000] text-white">
          <div className="mx-auto grid max-w-[1600px] gap-0 xl:grid-cols-[minmax(0,1.05fr),minmax(320px,0.95fr)]">
            <div className="flex items-center">
              <div className="w-full px-4 py-12 sm:px-6 sm:py-16 lg:px-10 xl:px-14 xl:py-20">
                <motion.div initial={{ opacity: 0, y: 18 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.45 }}>
                  <p className="text-[0.72rem] font-semibold uppercase tracking-[0.3em] text-[#f8c235]">Membership</p>
                  <h1 className="mt-4 max-w-3xl text-5xl leading-[0.95] text-white sm:text-6xl lg:text-7xl xl:text-[5.75rem]">
                    United We Climb.
                  </h1>
                  <p className="mt-5 max-w-2xl text-base leading-7 text-white/72 sm:text-lg">
                    Join the American Alpine Club to support climbing advocacy, rescue coverage, community grants,
                    publications, events, and a member experience built for the people who keep showing up for the mountains.
                  </p>

                  <div className="mt-8 flex flex-wrap gap-3">
                    <a
                      href="#membership-form"
                      className="inline-flex min-h-[3rem] items-center justify-center rounded-full bg-[#f8c235] px-6 text-sm font-semibold uppercase tracking-[0.16em] text-black transition-colors hover:bg-[#e1ae14]"
                    >
                      Join Now
                    </a>
                    <a
                      href={mainSiteHref('/benefits')}
                      className="inline-flex min-h-[3rem] items-center justify-center rounded-full border border-white/20 px-6 text-sm font-semibold uppercase tracking-[0.16em] text-white transition-colors hover:border-white/45 hover:bg-white/8"
                    >
                      Member Benefits
                    </a>
                    <a
                      href={mainSiteHref('/rescue')}
                      className="inline-flex min-h-[3rem] items-center justify-center rounded-full border border-white/20 px-6 text-sm font-semibold uppercase tracking-[0.16em] text-white transition-colors hover:border-white/45 hover:bg-white/8"
                    >
                      Rescue Benefits
                    </a>
                  </div>
                </motion.div>
              </div>
            </div>

            <div className="relative min-h-[300px] overflow-hidden xl:min-h-[520px]">
              <img
                src="https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Fillers.jpg"
                alt="American Alpine Club members in the mountains"
                className="absolute inset-0 h-full w-full object-cover"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-[#030000]/60 via-[#030000]/15 to-transparent" />
              <div className="absolute bottom-0 left-0 max-w-sm px-5 py-5 sm:px-6 sm:py-6">
                <div className="rounded-[1.5rem] border border-white/14 bg-black/35 px-5 py-4 backdrop-blur-sm">
                  <p className="text-[0.68rem] font-semibold uppercase tracking-[0.26em] text-[#f8c235]">Built for members</p>
                  <p className="mt-2 text-sm leading-6 text-white/78">
                    Membership checkout now uses the live AAC checkout flow directly inside the portal.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
          <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.45 }}>
            <div className="mb-8 flex flex-col gap-3 sm:mb-10 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <p className="text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#8f1515]">Application</p>
                <h2 className="mt-2 text-3xl text-[#030000] sm:text-4xl">Choose your membership and complete checkout.</h2>
              </div>
              <p className="max-w-2xl text-base leading-7 text-stone-600 sm:text-lg">
                Select a membership level above, then complete the real AAC checkout form below for {selectedTier.label}.
              </p>
            </div>

            <div id="membership-form" className="space-y-6">
              <div className="paper-panel rounded-[2rem] p-5 sm:p-8 lg:p-10">
                <p className="mb-4 text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-stone-600">Membership level</p>
                <MembershipTierSelect
                  variant="full"
                  selectedId={selectedTierId}
                  onSelect={setSelectedTierId}
                />
              </div>

              <div>
                <div className="overflow-hidden bg-white">
                  <iframe
                    key={checkoutUrl}
                    title={`${selectedTier.label} membership checkout`}
                    src={checkoutUrl}
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
