import React from 'react';
import { Helmet } from 'react-helmet';
import { motion } from 'framer-motion';
import { ArrowRight, BookOpen, CalendarDays, HeartHandshake, Mountain } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';
import { getPortalUiSettings } from '@/lib/portalSettings';
import grandTetonHero from '@/assets/grand-teton-hero.jpg';

const accentClassMap = {
  gold: 'bg-[#d9b26f] text-[#14160f]',
  light: 'border border-[#14160f] bg-transparent text-[#14160f]',
  sand: 'border border-[#14160f] bg-[#e5dece] text-[#14160f]',
  dark: 'bg-[#14160f] text-[#faf8f3]',
};

const iconRegistry = [HeartHandshake, CalendarDays, Mountain];

const isInternalHref = (href) => typeof href === 'string' && href.startsWith('/') && !href.startsWith('//');

const renderHomeLink = (href, children, className) => (
  isInternalHref(href) ? (
    <Link to={href} className={className}>{children}</Link>
  ) : (
    <a href={href} className={className}>{children}</a>
  )
);

const HOME_HERO_TITLE = 'United\nWe Climb.';

const HomePage = () => {
  const portalUi = getPortalUiSettings();
  const portalContent = portalUi.content;
  const portalDesign = portalUi.design;
  const homeSections = (portalUi.layout?.homeSections || []).map((section) => section.id);
  const involvementCards = portalContent.homeInvolvementCards || [];
  const publicationCards = portalContent.homePublicationCards || [];
  const partnerLogos = portalContent.homePartnerLogos || [];
  const heroImage = portalDesign.homeIntroImageUrl || grandTetonHero;

  return (
    <>
      <Helmet>
        <title>Home - American Alpine Club</title>
        <meta
          name="description"
          content="Explore AAC membership, publications, benefits, and ways to get involved from the member portal home page."
        />
      </Helmet>

      <div className="aac-cordillera-home bg-[#f1ece3] text-[#14160f]">
        {homeSections.includes('hero') ? (
          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45 }}
            className="relative grid min-h-[82svh] overflow-hidden border-b border-[#14160f] bg-[#14160f] text-[#faf8f3] lg:grid-cols-[minmax(0,0.92fr),minmax(28rem,1.08fr)]"
          >
            <div className="relative z-10 flex min-h-[66svh] flex-col justify-end px-5 py-10 sm:px-8 lg:px-12 xl:px-16">
              <p className="border-l-4 border-[#b8552e] pl-4 font-mono text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#d9b26f]">
                {portalContent.home_hero_kicker}
              </p>
              <h1 className="mt-5 max-w-[42rem] whitespace-pre-line font-condensed text-[clamp(4.5rem,10vw,10rem)] font-black uppercase leading-[0.82] tracking-normal text-[#faf8f3]">
                {HOME_HERO_TITLE}
              </h1>
              <p className="mt-7 max-w-2xl font-serif text-xl leading-8 text-[#f1ece3]/88 sm:text-2xl">
                {portalContent.home_hero_description}
              </p>

              <div className="mt-9 flex flex-wrap gap-3">
                <Button
                  asChild
                  className="min-h-[3.15rem] rounded-none border border-[#b8552e] bg-[#b8552e] px-7 font-mono text-xs font-semibold uppercase tracking-[0.18em] text-[#faf8f3] hover:bg-[#8b3d1f]"
                >
                  {renderHomeLink(portalContent.home_primary_cta_url, portalContent.home_primary_cta_label)}
                </Button>
                {renderHomeLink(
                  portalContent.home_secondary_cta_url,
                  portalContent.home_secondary_cta_label,
                  'inline-flex min-h-[3.15rem] items-center justify-center border border-[#f1ece3]/60 px-7 font-mono text-xs font-semibold uppercase tracking-[0.18em] text-[#f1ece3] transition-colors hover:border-[#d9b26f] hover:text-[#d9b26f]',
                )}
                {renderHomeLink(
                  portalContent.home_tertiary_cta_url,
                  portalContent.home_tertiary_cta_label,
                  'inline-flex min-h-[3.15rem] items-center justify-center border border-[#d9b26f] bg-[#d9b26f] px-7 font-mono text-xs font-semibold uppercase tracking-[0.18em] text-[#14160f] transition-colors hover:bg-[#c97c4f]',
                )}
              </div>
            </div>

            <div className="relative min-h-[44svh] border-t border-[#faf8f3]/18 lg:min-h-full lg:border-l lg:border-t-0">
              <img
                src={heroImage}
                alt="AAC climbers"
                className="absolute inset-0 h-full w-full object-cover"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-[#14160f]/72 via-[#14160f]/18 to-transparent" />
              <div className="absolute bottom-0 left-0 right-0 grid border-t border-[#faf8f3]/20 bg-[#14160f]/82 text-[#faf8f3] sm:grid-cols-3">
                <div className="border-b border-[#faf8f3]/14 p-5 sm:border-b-0 sm:border-r">
                  <p className="font-mono text-[0.65rem] font-semibold uppercase tracking-[0.22em] text-[#d9b26f]">
                    {portalContent.home_membership_chip_kicker}
                  </p>
                </div>
                <p className="col-span-2 p-5 font-serif text-base leading-7 text-[#f1ece3]/86">
                  {portalContent.home_membership_chip_description}
                </p>
              </div>
            </div>
          </motion.section>
        ) : null}

        <div className="mx-auto max-w-[92rem] space-y-10 px-5 py-12 sm:px-8 lg:px-12 xl:px-16">
          {homeSections.includes('intro') ? (
            <motion.section
              initial={{ opacity: 0, y: 18 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.45, delay: 0.04 }}
              className="grid border-y border-[#14160f] bg-[#faf8f3] lg:grid-cols-[minmax(0,0.95fr),minmax(0,1.05fr)]"
            >
              <div className="relative min-h-[22rem] overflow-hidden border-b border-[#14160f] lg:border-b-0 lg:border-r">
                <img src={portalDesign.homeIntroImageUrl || grandTetonHero} alt="AAC climbers" className="h-full w-full object-cover" />
                <div className="absolute bottom-0 left-0 bg-[#14160f] px-5 py-4 text-[#faf8f3]">
                  <p className="font-mono text-[0.64rem] font-semibold uppercase tracking-[0.24em] text-[#d9b26f]">
                    Est. 1902
                  </p>
                </div>
              </div>
              <div className="flex flex-col justify-center p-6 sm:p-8 lg:p-12">
                <div className="flex items-start gap-4">
                  <div className="border border-[#14160f] bg-[#e5dece] p-3 text-[#14160f]">
                    <Mountain className="h-5 w-5" />
                  </div>
                  <div>
                    <p className="font-mono text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#b8552e]">
                      {portalContent.home_intro_kicker}
                    </p>
                    <h2 className="mt-3 font-condensed text-[clamp(3rem,6vw,6.5rem)] font-black uppercase leading-[0.88] text-[#14160f]">
                      {portalContent.home_intro_title}
                    </h2>
                  </div>
                </div>
                <p className="mt-6 font-serif text-lg leading-8 text-[#4a5048]">
                  {portalContent.home_intro_description}
                </p>
                <p className="mt-4 font-serif text-lg leading-8 text-[#4a5048]">
                  {portalContent.home_intro_secondary_description}
                </p>
                <div className="mt-7">
                  <Button
                    asChild
                    className="min-h-[3rem] rounded-none border border-[#14160f] bg-transparent px-6 font-mono text-xs font-semibold uppercase tracking-[0.18em] text-[#14160f] hover:bg-[#14160f] hover:text-[#faf8f3]"
                  >
                    <a href={portalContent.home_intro_button_url}>
                      {portalContent.home_intro_button_label}
                      <ArrowRight className="ml-2 h-4 w-4" />
                    </a>
                  </Button>
                </div>
              </div>
            </motion.section>
          ) : null}

          {homeSections.includes('involvement') ? (
            <motion.section
              initial={{ opacity: 0, y: 18 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.45, delay: 0.08 }}
              className="border-y border-[#14160f] bg-[#faf8f3] p-6 sm:p-8 lg:p-10"
            >
              <div className="flex flex-col gap-5 border-b border-[#b8552e] pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                  <p className="font-mono text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#b8552e]">
                    {portalContent.home_involvement_kicker}
                  </p>
                  <h2 className="mt-3 font-condensed text-[clamp(3rem,6vw,6.5rem)] font-black uppercase leading-[0.88] text-[#14160f]">
                    {portalContent.home_involvement_title}
                  </h2>
                </div>
                <Button
                  asChild
                  className="min-h-[3rem] rounded-none border border-[#b8552e] bg-[#b8552e] px-6 font-mono text-xs font-semibold uppercase tracking-[0.18em] text-[#faf8f3] hover:bg-[#8b3d1f]"
                >
                  {renderHomeLink(portalContent.home_involvement_button_url, portalContent.home_involvement_button_label)}
                </Button>
              </div>

              <div className="mt-8 grid gap-6 lg:grid-cols-3">
                {involvementCards.map((card, index) => {
                  const Icon = iconRegistry[index] || HeartHandshake;
                  const ctaClass = `inline-flex w-full min-h-[3rem] items-center justify-center px-5 font-mono text-xs font-semibold uppercase tracking-[0.16em] transition-colors hover:bg-[#14160f] hover:text-[#faf8f3] ${accentClassMap[card.accentStyle] || accentClassMap.gold}`;

                  return (
                    <article
                      key={`${card.title}-${index}`}
                      className="flex h-full flex-col border border-[#14160f] bg-[#f1ece3]"
                    >
                      {card.imageUrl ? (
                        <div className="aspect-[1.18] overflow-hidden border-b border-[#14160f] bg-[#e5dece]">
                          <img src={card.imageUrl} alt={card.title} className="h-full w-full object-cover" />
                        </div>
                      ) : null}
                      <div className="flex flex-1 flex-col p-5">
                        <div className="w-fit border border-[#14160f] bg-[#faf8f3] p-3 text-[#14160f]">
                          <Icon className="h-5 w-5" />
                        </div>
                        <h3 className="mt-5 font-condensed text-4xl font-black uppercase leading-none text-[#14160f]">
                          {card.title}
                        </h3>
                        <p className="mt-4 flex-1 font-serif text-base leading-7 text-[#4a5048]">
                          {card.description}
                        </p>
                        <div className="mt-6">
                          {renderHomeLink(card.buttonUrl, card.buttonLabel, ctaClass)}
                        </div>
                      </div>
                    </article>
                  );
                })}
              </div>
            </motion.section>
          ) : null}

          {homeSections.includes('publications') ? (
            <motion.section
              initial={{ opacity: 0, y: 18 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.45, delay: 0.12 }}
              className="border-y border-[#14160f] bg-[#14160f] p-6 text-[#faf8f3] sm:p-8 lg:p-10"
            >
              <div className="flex flex-col gap-5 border-b border-[#d9b26f] pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                  <p className="font-mono text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#d9b26f]">
                    {portalContent.home_publications_kicker}
                  </p>
                  <h2 className="mt-3 font-condensed text-[clamp(3rem,6vw,6.5rem)] font-black uppercase leading-[0.88] text-[#faf8f3]">
                    {portalContent.home_publications_title}
                  </h2>
                </div>
                <a
                  href={portalContent.home_publications_button_url}
                  className="inline-flex min-h-[3rem] items-center justify-center border border-[#d9b26f] bg-[#d9b26f] px-6 font-mono text-xs font-semibold uppercase tracking-[0.18em] text-[#14160f] transition-colors hover:bg-[#c97c4f]"
                >
                  {portalContent.home_publications_button_label}
                </a>
              </div>

              <div className="mt-8 grid gap-6 lg:grid-cols-2">
                {publicationCards.map((card) => (
                  <a
                    key={card.title}
                    href={card.buttonUrl}
                    className="group grid border border-[#faf8f3]/28 bg-[#1e211a] text-[#faf8f3] md:grid-cols-[minmax(0,1fr),220px]"
                  >
                    <div className="flex flex-col justify-center p-6">
                      <div className="inline-flex w-fit items-center border border-[#d9b26f] px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-[#d9b26f]">
                        <BookOpen className="mr-2 h-3.5 w-3.5" />
                        Publication
                      </div>
                      <h3 className="mt-5 font-condensed text-4xl font-black uppercase leading-none text-[#faf8f3]">
                        {card.title}
                      </h3>
                      <p className="mt-4 font-serif text-base leading-7 text-[#f1ece3]/78">
                        {card.description}
                      </p>
                      <span className="mt-6 inline-flex items-center font-mono text-xs font-semibold uppercase tracking-[0.18em] text-[#d9b26f]">
                        {card.buttonLabel}
                        <ArrowRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-1" />
                      </span>
                    </div>
                    <div className="min-h-[260px] overflow-hidden border-t border-[#faf8f3]/28 bg-[#0f110d] md:border-l md:border-t-0">
                      <img src={card.imageUrl} alt={card.title} className="h-full w-full object-cover" />
                    </div>
                  </a>
                ))}
              </div>
            </motion.section>
          ) : null}

          {homeSections.includes('partners') ? (
            <motion.section
              initial={{ opacity: 0, y: 18 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.45, delay: 0.16 }}
              className="border-y border-[#14160f] bg-[#faf8f3] p-6 sm:p-8 lg:p-10"
            >
              <p className="font-mono text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#b8552e]">
                {portalContent.home_partners_kicker}
              </p>
              <h2 className="mt-3 font-condensed text-[clamp(3rem,6vw,6.5rem)] font-black uppercase leading-[0.88] text-[#14160f]">
                {portalContent.home_partners_title}
              </h2>
              <p className="mt-5 max-w-3xl font-serif text-lg leading-8 text-[#4a5048]">
                {portalContent.home_partners_description}
              </p>

              <div className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {partnerLogos.map((logo, index) => {
                  const content = (
                    <img src={logo.imageUrl} alt={logo.name} className="max-h-12 w-auto object-contain" />
                  );

                  return logo.linkUrl ? (
                    <a
                      key={`${logo.name}-${index}`}
                      href={logo.linkUrl}
                      className="flex min-h-[120px] items-center justify-center border border-[#14160f] bg-[#f1ece3] px-6 py-5 transition-colors hover:bg-[#e5dece]"
                    >
                      {content}
                    </a>
                  ) : (
                    <div
                      key={`${logo.name}-${index}`}
                      className="flex min-h-[120px] items-center justify-center border border-[#14160f] bg-[#f1ece3] px-6 py-5"
                    >
                      {content}
                    </div>
                  );
                })}
              </div>
            </motion.section>
          ) : null}
        </div>
      </div>
    </>
  );
};

export default HomePage;
