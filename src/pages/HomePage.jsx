import React, { useEffect } from 'react';
import { Helmet } from 'react-helmet';
import { motion } from 'framer-motion';
import { ArrowRight, BookOpen, CalendarDays, HeartHandshake, Mountain, ShoppingBag } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';
import { mainSiteHref } from '@/lib/mainWebsiteNav';
import { getPortalUiSettings } from '@/lib/portalSettings';

const HERO_VIDEO_URL = 'https://player.vimeo.com/video/1166009381?h=c4c3248b38&background=1&autoplay=1&muted=1&loop=1&autopause=0&controls=0&title=0&byline=0&portrait=0';
const INTRO_IMAGE_URL = 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-2.jpg';
const INTRO_ACCENT_IMAGE_URL = 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-3.jpg';
const JOIN_CARD_IMAGE_URL = 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-4.jpg';
const PUBLICATIONS_AAJ_IMAGE_URL = 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-95.jpeg';
const PUBLICATIONS_ANAC_IMAGE_URL = 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-28.jpeg';
const STORE_IMAGE_URL = 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/AAC-Navy-Hat.jpg';

const involvementCards = [
  {
    title: 'Join the Club',
    description:
      'Membership supports AAC advocacy, rescue benefits, climbing knowledge, grants, and the wider climbing community.',
    ctaLabel: 'Join Now',
    to: '/join',
    accent: 'bg-[#f8c235] text-black',
    imageUrl: JOIN_CARD_IMAGE_URL,
    icon: HeartHandshake,
  },
  {
    title: 'Attend an Event',
    description:
      'Connect with the AAC community through upcoming events, member gatherings, and shared learning in climbing spaces.',
    ctaLabel: 'See Events',
    href: mainSiteHref('/events/'),
    accent: 'bg-white text-black border border-stone-200',
    icon: CalendarDays,
  },
  {
    title: 'Stay at AAC Lodging',
    description:
      'Explore climber lodging destinations and plan your next trip through AAC campgrounds and ranch properties.',
    ctaLabel: 'Explore Lodging',
    href: mainSiteHref('/lodging/'),
    accent: 'bg-[#efe5d4] text-black border border-stone-200',
    icon: Mountain,
  },
];

const publicationCards = [
  {
    title: 'American Alpine Journal',
    description:
      'Long-form reporting on major climbs around the world, presented in AAC’s flagship publication.',
    href: mainSiteHref('/publications/aaj/'),
    imageUrl: PUBLICATIONS_AAJ_IMAGE_URL,
    accent: '#f8c235',
  },
  {
    title: 'Accidents in North American Climbing',
    description:
      'Annual accident analysis and takeaways that help climbers learn from the year’s most important incidents.',
    href: mainSiteHref('/publications/accidents/'),
    imageUrl: PUBLICATIONS_ANAC_IMAGE_URL,
    accent: '#b20710',
  },
];

const partnerLogos = [
  { name: 'American Alpine Club', imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/09/dark-header-logo.svg' },
  { name: 'Backcountry', imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Filler-Logo-2.png' },
  { name: 'Black Diamond', imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Filler-Logo-1.png' },
];

const FUNDRAISE_UP_WIDGET_ID = 'ANMVEMFF';
const FUNDRAISE_UP_CAMPAIGN_ID = 'FUNQGMXLVKU';
const FUNDRAISE_UP_SCRIPT_ID = 'aac-fundraiseup-widget';
const FUNDRAISE_UP_AUTOLAUNCH_KEY = 'aac-fundraiseup-home-autolaunch';

const HomePage = () => {
  const portalUi = getPortalUiSettings();
  const portalContent = portalUi.content;
  const portalDesign = portalUi.design;

  useEffect(() => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
      return;
    }

    if (window.FundraiseUp || document.getElementById(FUNDRAISE_UP_SCRIPT_ID)) {
      return;
    }

    (function loadFundraiseUp(w, d, s, n, a) {
      if (!w[n]) {
        const methods = 'call,catch,on,once,set,then,track,openCheckout'.split(',');
        const stub = function createStub(method) {
          return typeof method === 'function'
            ? stub.l.push([arguments]) && stub
            : function proxy() {
                return stub.l.push([method, arguments]) && stub;
              };
        };
        const target = d.getElementsByTagName(s)[0];
        const script = d.createElement(s);
        script.async = true;
        script.id = FUNDRAISE_UP_SCRIPT_ID;
        script.src = `https://cdn.fundraiseup.com/widget/${a}`;
        target.parentNode.insertBefore(script, target);
        stub.s = Date.now();
        stub.v = 5;
        stub.h = w.location.href;
        stub.l = [];
        for (let i = 0; i < methods.length; i += 1) {
          stub[methods[i]] = stub(methods[i]);
        }
        w[n] = stub;
      }
    })(window, document, 'script', 'FundraiseUp', FUNDRAISE_UP_WIDGET_ID);

    const openCheckout = () => {
      if (window.FundraiseUp && typeof window.FundraiseUp.openCheckout === 'function') {
        try {
          window.FundraiseUp.openCheckout(FUNDRAISE_UP_CAMPAIGN_ID);
          sessionStorage.setItem(FUNDRAISE_UP_AUTOLAUNCH_KEY, '1');
          return true;
        } catch (error) {
          return false;
        }
      }

      return false;
    };

    if (sessionStorage.getItem(FUNDRAISE_UP_AUTOLAUNCH_KEY)) {
      return undefined;
    }

    if (openCheckout()) {
      return undefined;
    }

    let attempts = 0;
    const maxAttempts = 40;
    const interval = window.setInterval(() => {
      attempts += 1;
      const opened = openCheckout();
      if (opened || attempts >= maxAttempts) {
        window.clearInterval(interval);
      }
    }, 250);

    return () => {
      window.clearInterval(interval);
    };
  }, []);

  return (
    <>
      <Helmet>
        <title>Home - American Alpine Club</title>
        <meta
          name="description"
          content="Explore AAC membership, publications, store highlights, and ways to get involved from the member portal home page."
        />
      </Helmet>

      <div className="py-6">
        <div className="mx-auto max-w-7xl space-y-8">
          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45 }}
            className="relative overflow-hidden rounded-[2rem] border border-stone-200/80 bg-[#030000] text-white shadow-[0_26px_80px_rgba(3,0,0,0.28)]"
          >
            <div className="absolute inset-0">
              <iframe
                title="AAC homepage hero video"
                src={HERO_VIDEO_URL}
                className="pointer-events-none absolute left-1/2 top-1/2 h-[120%] w-[120%] min-w-[1280px] -translate-x-1/2 -translate-y-1/2"
                frameBorder="0"
                allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share"
                referrerPolicy="strict-origin-when-cross-origin"
                allowFullScreen
              />
            </div>
            <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(3,0,0,0.88)_0%,rgba(3,0,0,0.72)_38%,rgba(3,0,0,0.4)_62%,rgba(3,0,0,0.58)_100%)]" />
            <div className="absolute inset-0 bg-gradient-to-t from-[#030000]/50 via-transparent to-[#030000]/16" />

            <div className="relative min-h-[520px] px-6 py-12 sm:px-8 lg:px-10 xl:px-14 xl:py-16">
              <div className="flex min-h-[456px] items-end">
                <div className="w-full max-w-4xl">
                  <div className="max-w-3xl rounded-[1.75rem] border border-white/14 bg-black/34 px-6 py-7 backdrop-blur-sm sm:px-8 sm:py-8">
                    <p className="text-[0.72rem] font-semibold uppercase tracking-[0.3em] text-[#f8c235]">Home</p>
                    <h1 className="mt-4 max-w-3xl text-5xl leading-[0.95] text-white sm:text-6xl lg:text-7xl">
                      United We Climb.
                    </h1>
                    <p className="mt-5 max-w-2xl text-base leading-7 text-white/84 sm:text-lg">
                      Explore AAC membership, rescue coverage, publications, grants, and community resources through
                      the same member-focused experience that powers the portal.
                    </p>

                    <div className="mt-8 flex flex-wrap gap-3">
                      <Button
                        asChild
                        className="min-h-[3rem] rounded-none px-6 text-sm font-semibold uppercase tracking-[0.16em]"
                        style={{
                          backgroundColor: portalDesign.secondaryActionBackground,
                          color: portalDesign.secondaryActionText,
                        }}
                      >
                        <Link to="/join">Join</Link>
                      </Button>
                      <a
                        href="https://membership.americanalpineclub.org/renew"
                        className="inline-flex min-h-[3rem] items-center justify-center rounded-none border border-white px-6 text-sm font-semibold uppercase tracking-[0.16em] text-white transition-colors hover:bg-white hover:text-black"
                      >
                        Renew
                      </a>
                      <a
                        href="https://americanalpine.wpenginepowered.com/learn-more/"
                        className="inline-flex min-h-[3rem] items-center justify-center rounded-none border border-white/18 bg-white/[0.04] px-6 text-sm font-semibold uppercase tracking-[0.16em] text-white transition-colors hover:border-[#f8c235] hover:text-[#f8c235]"
                      >
                        Learn More About Membership
                      </a>
                    </div>
                  </div>

                  <div className="mt-5 max-w-sm rounded-[1.5rem] border border-white/18 bg-black/38 px-5 py-4 backdrop-blur-sm">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.26em] text-[#f8c235]">
                      {portalContent.join_hero_kicker}
                    </p>
                    <p className="mt-2 text-sm leading-6 text-white/84">
                      Climbing advocacy, rescue coverage, publications, events, and member resources all live here.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </motion.section>

          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.04 }}
            className="grid gap-6 lg:grid-cols-[minmax(0,0.95fr),minmax(0,1.05fr)]"
          >
            <div className="relative overflow-hidden rounded-[2rem] border border-stone-200 bg-white shadow-[0_20px_60px_rgba(15,23,42,0.10)]">
              <img src={INTRO_IMAGE_URL} alt="AAC climbers" className="h-full w-full object-cover" />
              <div className="absolute bottom-4 right-4 hidden w-40 overflow-hidden rounded-[1.25rem] border border-white/60 shadow-xl lg:block">
                <img src={INTRO_ACCENT_IMAGE_URL} alt="AAC climber silhouette" className="h-full w-full object-cover" />
              </div>
            </div>
            <div className="card-gradient rounded-[2rem] border border-stone-200/80 p-6 sm:p-8">
              <div className="flex items-start gap-3">
                <div className="rounded-2xl bg-[#f8c235]/18 p-3 text-[#6b5310]">
                  <Mountain className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#8f1515]">Since 1902</p>
                  <h2 className="mt-2 text-3xl font-bold text-stone-900">Built for climbers.</h2>
                </div>
              </div>
              <p className="mt-5 text-base leading-7 text-stone-700">
                Founded in 1902, the American Alpine Club is a nonprofit that champions climbing knowledge,
                inspiration, advocacy, and community support for people who care deeply about the mountains.
              </p>
              <p className="mt-4 text-base leading-7 text-stone-700">
                From rescue benefits and member publications to grants, events, and lodging, the Club keeps building
                practical resources that help climbers stay connected and better supported.
              </p>
              <div className="mt-6">
                <Button
                  asChild
                  className="rounded-none px-6"
                  style={{
                    backgroundColor: portalDesign.secondaryActionBackground,
                    color: portalDesign.secondaryActionText,
                  }}
                >
                  <a href="https://americanalpine.wpenginepowered.com/learn-more/">
                    Learn More About The AAC
                    <ArrowRight className="ml-2 h-4 w-4" />
                  </a>
                </Button>
              </div>
            </div>
          </motion.section>

          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.08 }}
            className="rounded-[2rem] border border-stone-200/80 bg-white p-6 shadow-[0_20px_60px_rgba(15,23,42,0.08)] sm:p-8"
          >
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <p className="text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#8f1515]">Explore</p>
                <h2 className="mt-2 text-3xl font-bold text-stone-900">How To Get Involved</h2>
              </div>
              <Button
                asChild
                className="rounded-none px-6"
                style={{
                  backgroundColor: portalDesign.primaryActionBackground,
                  color: portalDesign.primaryActionText,
                }}
              >
                <Link to="/join">Join the Club</Link>
              </Button>
            </div>

            <div className="mt-6 grid gap-5 lg:grid-cols-3">
              {involvementCards.map((card) => {
                const Icon = card.icon;
                const cta = card.to ? (
                  <Button asChild className="w-full rounded-none px-5">
                    <Link to={card.to}>{card.ctaLabel}</Link>
                  </Button>
                ) : (
                  <a
                    href={card.href}
                    className={`inline-flex w-full min-h-[3rem] items-center justify-center rounded-none px-5 text-sm font-semibold uppercase tracking-[0.16em] transition-colors ${card.accent}`}
                  >
                    {card.ctaLabel}
                  </a>
                );

                return (
                  <article
                    key={card.title}
                    className="flex h-full flex-col overflow-hidden rounded-[1.7rem] border border-stone-200 bg-[#f8f4eb]"
                  >
                    {card.imageUrl ? (
                      <div className="aspect-[1.25] overflow-hidden bg-stone-200">
                        <img src={card.imageUrl} alt={card.title} className="h-full w-full object-cover" />
                      </div>
                    ) : null}
                    <div className="flex flex-1 flex-col p-5">
                      <div className="rounded-2xl bg-black/5 p-3 text-stone-900">
                        <Icon className="h-5 w-5" />
                      </div>
                      <h3 className="mt-4 text-2xl font-bold text-stone-900">{card.title}</h3>
                      <p className="mt-3 flex-1 text-sm leading-6 text-stone-700">{card.description}</p>
                      <div className="mt-5">{cta}</div>
                    </div>
                  </article>
                );
              })}
            </div>
          </motion.section>

          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.12 }}
            className="rounded-[2rem] border border-stone-200/80 bg-white p-6 shadow-[0_20px_60px_rgba(15,23,42,0.08)] sm:p-8"
          >
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <p className="text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#8f1515]">Library</p>
                <h2 className="mt-2 text-3xl font-bold text-stone-900">Our Publications</h2>
              </div>
              <a
                href={mainSiteHref('/publications/')}
                className="inline-flex min-h-[3rem] items-center justify-center rounded-none border border-[#f8c235] bg-[#f8c235] px-6 text-sm font-semibold uppercase tracking-[0.16em] text-black transition-colors hover:bg-[#ddb01d]"
              >
                All Publications
              </a>
            </div>

            <div className="mt-6 grid gap-5 lg:grid-cols-2">
              {publicationCards.map((card) => (
                <a
                  key={card.title}
                  href={card.href}
                  className="group grid overflow-hidden rounded-[1.7rem] border border-stone-200 bg-[#0d0a09] text-white shadow-[0_20px_48px_rgba(3,0,0,0.18)] md:grid-cols-[minmax(0,1fr),220px]"
                >
                  <div className="flex flex-col justify-center p-6">
                    <div
                      className="inline-flex w-fit items-center rounded-none px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.2em]"
                      style={{ backgroundColor: `${card.accent}22`, color: card.accent }}
                    >
                      <BookOpen className="mr-2 h-3.5 w-3.5" />
                      Publication
                    </div>
                    <h3 className="mt-4 text-2xl font-bold text-white">{card.title}</h3>
                    <p className="mt-3 text-sm leading-6 text-white/72">{card.description}</p>
                    <span className="mt-5 inline-flex items-center text-sm font-semibold uppercase tracking-[0.16em]" style={{ color: card.accent }}>
                      View Publication
                      <ArrowRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-1" />
                    </span>
                  </div>
                  <div className="min-h-[240px] overflow-hidden bg-stone-900">
                    <img src={card.imageUrl} alt={card.title} className="h-full w-full object-cover" />
                  </div>
                </a>
              ))}
            </div>
          </motion.section>

          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.16 }}
            className="grid gap-6 lg:grid-cols-[minmax(0,0.85fr),minmax(0,1.15fr)]"
          >
            <article className="overflow-hidden rounded-[2rem] border border-stone-200 bg-white shadow-[0_20px_60px_rgba(15,23,42,0.08)]">
              <div className="aspect-[1.05] overflow-hidden">
                <img src={STORE_IMAGE_URL} alt="AAC Store featured product" className="h-full w-full object-cover" />
              </div>
              <div className="p-6">
                <p className="text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#8f1515]">Store</p>
                <h2 className="mt-2 text-3xl font-bold text-stone-900">Shop AAC Store</h2>
                <p className="mt-4 text-sm leading-6 text-stone-700">
                  Browse featured AAC apparel, gear, and member merchandise from the Club store.
                </p>
                <div className="mt-6">
                  <a
                    href="https://americanalpineclub.myshopify.com/"
                    className="inline-flex min-h-[3rem] items-center justify-center rounded-none bg-black px-6 text-sm font-semibold uppercase tracking-[0.16em] text-white transition-colors hover:bg-[#1f1a18]"
                  >
                    <ShoppingBag className="mr-2 h-4 w-4" />
                    AAC Store
                  </a>
                </div>
              </div>
            </article>

            <article className="card-gradient rounded-[2rem] border border-stone-200/80 p-6 shadow-[0_20px_60px_rgba(15,23,42,0.08)] sm:p-8">
              <p className="text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#8f1515]">Network</p>
              <h2 className="mt-2 text-3xl font-bold text-stone-900">Our Partners</h2>
              <p className="mt-4 max-w-2xl text-sm leading-6 text-stone-700">
                Partner brands and community collaborators help AAC extend member value across climbing gear,
                publications, events, and advocacy work.
              </p>

              <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {partnerLogos.map((logo, index) => (
                  <div
                    key={`${logo.name}-${index}`}
                    className="flex min-h-[120px] items-center justify-center rounded-[1.4rem] border border-stone-200 bg-white px-6 py-5"
                  >
                    <img src={logo.imageUrl} alt={logo.name} className="max-h-12 w-auto object-contain" />
                  </div>
                ))}
              </div>
            </article>
          </motion.section>
        </div>
      </div>
    </>
  );
};

export default HomePage;
