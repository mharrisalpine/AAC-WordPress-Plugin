import React, { useMemo, useRef, useState } from 'react';
import { Helmet } from 'react-helmet';
import { motion } from 'framer-motion';
import { ArrowLeft, ArrowRight, ExternalLink, Facebook, Globe, Instagram, X } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { getPortalUiSettings } from '@/lib/portalSettings';

const SOCIAL_LINKS = [
  { key: 'website_url', label: 'Website', icon: Globe },
  { key: 'instagram_url', label: 'Instagram', icon: Instagram },
  { key: 'facebook_url', label: 'Facebook', icon: Facebook },
  { key: 'x_url', label: 'X', icon: X },
];

const getPhotographersHeroUrl = () => {
  if (typeof window === 'undefined') {
    return '/wp-content/plugins/aac-member-portal/app/assets/photographers-hero.jpg';
  }

  const assetBaseUrl = window.AAC_MEMBER_PORTAL_CONFIG?.assetBaseUrl;
  if (assetBaseUrl) {
    return `${String(assetBaseUrl).replace(/\/+$/, '')}/photographers-hero.jpg`;
  }

  return `${window.location.origin}/wp-content/plugins/aac-member-portal/app/assets/photographers-hero.jpg`;
};

const FeaturedPhotographersPage = () => {
  const portalUi = getPortalUiSettings();
  const portalContent = portalUi.content;
  const photographersHeroUrl = React.useMemo(() => getPhotographersHeroUrl(), []);
  const photographers = useMemo(
    () => (Array.isArray(portalContent.featuredPhotographers) ? portalContent.featuredPhotographers : []),
    [portalContent.featuredPhotographers]
  );
  const [selectedPhoto, setSelectedPhoto] = useState(null);
  const sliderRefs = useRef({});

  const closeModalOnContextMenu = (event) => {
    event.preventDefault();
    setSelectedPhoto(null);
  };

  const scrollGallery = (photographerIndex, direction) => {
    const slider = sliderRefs.current[photographerIndex];
    if (!slider) {
      return;
    }

    const scrollAmount = Math.max(slider.clientWidth * 0.82, 320) * direction;
    slider.scrollBy({ left: scrollAmount, behavior: 'smooth' });
  };

  const openPhoto = (photographerIndex, galleryIndex, photographerName, galleryItem) => {
    setSelectedPhoto({
      photographerIndex,
      galleryIndex,
      imageUrl: galleryItem.image_url,
      caption: galleryItem.caption,
      photographerName,
    });
  };

  const goToNextPhoto = () => {
    if (!selectedPhoto) {
      return;
    }

    const photographer = photographers[selectedPhoto.photographerIndex];
    const galleryItems = Array.isArray(photographer?.gallery_items) ? photographer.gallery_items.slice(0, 6) : [];
    if (!galleryItems.length) {
      return;
    }

    const nextIndex = (selectedPhoto.galleryIndex + 1) % galleryItems.length;
    const nextPhoto = galleryItems[nextIndex];
    if (!nextPhoto) {
      return;
    }

    openPhoto(selectedPhoto.photographerIndex, nextIndex, photographer?.name || selectedPhoto.photographerName, nextPhoto);
  };

  return (
    <>
      <Helmet>
        <title>Featured Photographers - American Alpine Club</title>
        <meta
          name="description"
          content="Meet AAC featured photographers and explore curated mountain galleries from the people behind the images."
        />
      </Helmet>

      <div className="bg-[#f6f1e8]">
        <motion.section
          initial={{ opacity: 0, y: 18 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.45 }}
          className="hero-break relative min-h-[68svh] overflow-hidden text-white sm:min-h-[72svh] xl:min-h-[78svh]"
        >
          <img
            src={photographersHeroUrl}
            alt=""
            aria-hidden="true"
            className="absolute inset-0 h-full w-full object-cover object-top"
          />
          <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(3,0,0,0.8)_0%,rgba(3,0,0,0.54)_42%,rgba(3,0,0,0.36)_66%,rgba(3,0,0,0.55)_100%)]" />
          <div className="absolute inset-0 bg-[linear-gradient(to_top,rgba(3,0,0,0.48),transparent,rgba(3,0,0,0.14))]" />

          <div className="relative flex min-h-[68svh] items-end px-4 pb-10 pt-[calc(var(--aac-portal-header-height)+1.25rem)] sm:min-h-[72svh] sm:px-6 sm:pb-14 sm:pt-[calc(var(--aac-portal-header-height)+1.75rem)] lg:px-10 xl:min-h-[78svh] xl:px-14 xl:pb-16 xl:pt-[calc(var(--aac-portal-header-height)+2rem)]">
            <div className="max-w-[40rem]">
              <p className="text-[0.72rem] font-semibold uppercase tracking-[0.38em] text-[#f8c235]">
                {portalContent.photographers_page_kicker}
              </p>
              <h1 className="mt-4 max-w-4xl text-[2.95rem] font-semibold leading-[0.94] tracking-[-0.04em] text-white sm:text-[3.9rem] xl:text-[5rem]">
                {portalContent.photographers_page_title}
              </h1>
              <p className="mt-4 max-w-2xl text-sm leading-7 text-white/84 sm:text-base sm:leading-8">
                {portalContent.photographers_page_description}
              </p>
            </div>
          </div>
        </motion.section>

        <div className="px-4 py-10 sm:px-6 lg:px-10 xl:px-14">
          <div className="mx-auto max-w-[1600px] space-y-16">
            {photographers.map((photographer, photographerIndex) => {
              const galleryItems = Array.isArray(photographer.gallery_items)
                ? photographer.gallery_items.slice(0, 6)
                : [];

              return (
                <motion.section
                  key={`${photographer.name || 'photographer'}-${photographerIndex}`}
                  initial={{ opacity: 0, y: 18 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.45, delay: photographerIndex * 0.05 }}
                  className="border-b border-stone-300/75 pb-16 last:border-b-0"
                >
                  <div className="grid gap-10 xl:grid-cols-[minmax(320px,0.8fr),minmax(0,1.2fr)] xl:gap-12">
                    <div
                      className="relative flex flex-col justify-between xl:sticky xl:top-28 xl:self-start"
                      style={{ background: 'transparent' }}
                    >
                      <div>
                        <div className="flex items-start gap-5">
                          <div className="h-24 w-24 shrink-0 overflow-hidden rounded-none bg-stone-200 sm:h-28 sm:w-28">
                            <img
                              src={photographer.profile_image_url}
                              alt={photographer.name || 'Featured photographer'}
                              className="h-full w-full object-cover"
                              draggable={false}
                              onDragStart={(event) => event.preventDefault()}
                            />
                          </div>
                          <div className="min-w-0">
                            <p className="text-[0.68rem] font-semibold uppercase tracking-[0.32em] text-[#8f1515]">
                              Featured Photographer
                            </p>
                            <h2 className="mt-3 text-3xl font-semibold tracking-[-0.03em] text-stone-950 sm:text-4xl">
                              {photographer.name}
                            </h2>
                          </div>
                        </div>

                        <p className="mt-7 max-w-md text-sm leading-8 text-stone-700 sm:text-[0.98rem]">
                          {photographer.short_bio}
                        </p>
                      </div>

                      <div className="mt-10 space-y-3">
                        {SOCIAL_LINKS.filter((link) => photographer[link.key]).map((link) => {
                          const Icon = link.icon;
                          return (
                            <a
                              key={link.key}
                              href={photographer[link.key]}
                              target="_blank"
                              rel="noreferrer"
                              className="flex items-center justify-between border-b border-stone-300/80 py-3 text-sm font-medium text-stone-900 transition hover:border-[#8f1515] hover:text-[#8f1515]"
                            >
                              <span className="flex items-center gap-3">
                                <Icon className="h-4 w-4 text-[#8f1515]" />
                                {link.label}
                              </span>
                              <ExternalLink className="h-4 w-4 text-stone-500" />
                            </a>
                          );
                        })}
                      </div>
                    </div>

                    <div className="space-y-4">
                      <div className="flex items-center justify-between gap-4">
                        <p className="text-[0.68rem] font-semibold uppercase tracking-[0.28em] text-stone-500">
                          Gallery
                        </p>
                        <div className="flex items-center gap-2">
                          <button
                            type="button"
                            className="inline-flex h-11 items-center justify-center px-1 text-stone-900 transition hover:text-[#8f1515]"
                            onClick={() => scrollGallery(photographerIndex, -1)}
                            aria-label={`Scroll ${photographer.name} gallery left`}
                          >
                            <ArrowLeft className="h-7 w-7 stroke-[2.4]" />
                          </button>
                          <button
                            type="button"
                            className="inline-flex h-11 items-center justify-center px-1 text-stone-900 transition hover:text-[#8f1515]"
                            onClick={() => scrollGallery(photographerIndex, 1)}
                            aria-label={`Scroll ${photographer.name} gallery right`}
                          >
                            <ArrowRight className="h-7 w-7 stroke-[2.4]" />
                          </button>
                        </div>
                      </div>

                      <div
                        ref={(node) => {
                          sliderRefs.current[photographerIndex] = node;
                        }}
                        className="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                      >
                      {galleryItems.map((galleryItem, galleryIndex) => (
                          <button
                            key={`${photographerIndex}-${galleryIndex}-${galleryItem.image_url || 'image'}`}
                            type="button"
                            className="group relative aspect-[4/5] w-[78vw] max-w-[460px] min-w-[280px] shrink-0 snap-start overflow-hidden rounded-none bg-stone-100 text-left sm:w-[44vw] lg:w-[34vw] xl:w-[28vw]"
                            onClick={() => openPhoto(photographerIndex, galleryIndex, photographer.name, galleryItem)}
                          >
                            <img
                              src={galleryItem.image_url}
                              alt={galleryItem.caption || `${photographer.name} gallery ${galleryIndex + 1}`}
                              className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
                              draggable={false}
                              onDragStart={(event) => event.preventDefault()}
                            />
                            <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 via-black/10 to-transparent px-3 pb-3 pt-10">
                              <p className="line-clamp-2 text-xs font-medium leading-5 text-white/92">
                                {galleryItem.caption || 'Open image'}
                              </p>
                            </div>
                          </button>
                        ))}
                      </div>
                    </div>
                  </div>
                </motion.section>
              );
            })}
          </div>
        </div>
      </div>

      <Dialog open={Boolean(selectedPhoto)} onOpenChange={(open) => !open && setSelectedPhoto(null)}>
        <DialogContent
          className="w-[calc(100%-1.5rem)] max-w-5xl border border-white/10 bg-[#090909] p-0 text-white shadow-[0_32px_100px_rgba(0,0,0,0.55)] sm:w-full"
          onContextMenu={closeModalOnContextMenu}
        >
          {selectedPhoto ? (
            <div className="grid gap-0 md:grid-cols-[minmax(0,1.3fr),minmax(280px,0.7fr)]">
              <div className="relative bg-black" onContextMenu={closeModalOnContextMenu}>
                <img
                  src={selectedPhoto.imageUrl}
                  alt={selectedPhoto.caption || selectedPhoto.photographerName}
                  className="h-full max-h-[80vh] w-full object-contain"
                  draggable={false}
                  onDragStart={(event) => event.preventDefault()}
                />
              </div>
              <div className="flex flex-col justify-between border-t border-white/10 p-6 md:border-l md:border-t-0">
                <DialogHeader className="text-left">
                  <p className="text-[0.68rem] font-semibold uppercase tracking-[0.26em] text-[#f8c235]">
                    Featured Photographers
                  </p>
                  <DialogTitle className="mt-3 text-2xl font-semibold text-white">
                    {selectedPhoto.photographerName}
                  </DialogTitle>
                  <DialogDescription className="mt-3 text-sm leading-7 text-white/72">
                    {selectedPhoto.caption || 'Right-click closes this modal.'}
                  </DialogDescription>
                </DialogHeader>

                <div className="mt-8 flex items-end justify-between gap-4">
                  <p className="max-w-[14rem] text-sm leading-7 text-white/72">
                    Right-click closes this modal.
                  </p>
                  <button
                    type="button"
                    className="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.18em] text-white transition hover:text-[#f8c235]"
                    onClick={goToNextPhoto}
                  >
                    Next Photo
                    <ArrowRight className="h-5 w-5 stroke-[2.4]" />
                  </button>
                </div>
              </div>
            </div>
          ) : null}
        </DialogContent>
      </Dialog>
    </>
  );
};

export default FeaturedPhotographersPage;
