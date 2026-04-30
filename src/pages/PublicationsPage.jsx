import React from 'react';
import { motion } from 'framer-motion';
import { BookOpen, ExternalLink } from 'lucide-react';
import { Link } from 'react-router-dom';
import { getPortalUiSettings } from '@/lib/portalSettings';
import { getPublicationLibraryItems } from '@/lib/publications';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/hooks/useAuth';
import { isPartnerOrAboveMembershipTierId } from '@/lib/membershipTiers';
import { normalizeAccountInfo, normalizePrintDigitalPreference } from '@/lib/memberProfile';

const PUBLICATION_PREFERENCE_LABELS = {
  aaj: 'American Alpine Journal',
  anac: 'Accidents in North American Climbing',
  acj: 'American Climbing Journal',
  guidebook: 'Guidebook to Membership',
};

const getPublicationSelectionForItem = (accountInfo, itemId) => {
  const preferenceMap = {
    aaj: accountInfo?.aaj_pref,
    anac: accountInfo?.anac_pref,
    acj: accountInfo?.acj_pref,
    guidebook: accountInfo?.guidebook_pref,
  };

  return normalizePrintDigitalPreference(preferenceMap[itemId], 'Digital');
};

const PublicationsPage = () => {
  const { profile } = useAuth();
  const portalUiSettings = getPortalUiSettings();
  const portalContent = portalUiSettings.content;
  const portalDesign = portalUiSettings.design;
  const publicationItems = getPublicationLibraryItems();
  const accountInfo = normalizeAccountInfo(profile?.account_info || {});
  const canAccessPublications = isPartnerOrAboveMembershipTierId(profile?.profile_info?.tier);

  if (!canAccessPublications) {
    return (
      <div className="py-6">
        <motion.div
          initial={{ opacity: 0, y: 18 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.45 }}
        >
          <section className="card-gradient rounded-none border border-stone-200/80 p-6 text-center">
            <div className="mx-auto flex max-w-xl flex-col items-center">
              <div className="rounded-none bg-[#c8a43a]/18 p-3 text-[#6b5310]">
                <BookOpen className="h-5 w-5" />
              </div>
              <h1 className="mt-4 text-2xl font-bold text-stone-900">{portalContent.publications_locked_title}</h1>
              <p className="mt-2 text-sm leading-6 text-stone-600">
                {portalContent.publications_locked_description}
              </p>
              <Button
                asChild
                className="mt-5 rounded-none"
                style={{
                  backgroundColor: portalDesign.primaryActionBackground,
                  color: portalDesign.primaryActionText,
                }}
              >
                <Link to="/membership">{portalContent.publications_upgrade_button_label}</Link>
              </Button>
            </div>
          </section>
        </motion.div>
      </div>
    );
  }

  return (
    <div className="py-6">
      <motion.div
        initial={{ opacity: 0, y: 18 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45 }}
        className="space-y-6"
      >
        <section className="card-gradient rounded-none border border-stone-200/80 p-6">
          <div className="mb-5 flex items-start gap-3">
            <div className="rounded-none bg-[#c8a43a]/18 p-3 text-[#6b5310]">
              <BookOpen className="h-5 w-5" />
            </div>
            <div>
              <h1 className="text-2xl font-bold text-stone-900">{portalContent.publications_title}</h1>
              <p className="mt-1 text-sm text-stone-600">
                {portalContent.publications_description}
              </p>
              <p className="mt-2 text-sm text-stone-500">
                Delivery selections shown below come from your PMPro member fields.
              </p>
            </div>
          </div>

          <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            {publicationItems.map((item) => {
              const selection = getPublicationSelectionForItem(accountInfo, item.id);
              const selectionLabel = PUBLICATION_PREFERENCE_LABELS[item.id] || item.title;

              return (
              <article
                key={item.id}
                className="flex h-full flex-col overflow-hidden rounded-none border border-stone-200 bg-white shadow-[0_18px_44px_rgba(15,23,42,0.08)]"
              >
                <div className="flex aspect-[0.78] items-center justify-center overflow-hidden bg-[linear-gradient(160deg,#f7f1e4,#efe3c5)] p-4">
                  {item.imageUrl ? (
                    <img
                      src={item.imageUrl}
                      alt={`${item.title} cover`}
                      className="h-full w-full object-contain object-center"
                    />
                  ) : (
                    <div className="flex h-full w-full items-center justify-center rounded-none bg-[linear-gradient(160deg,#f2ead8,#e4d2ac)] text-center text-stone-600">
                      <div className="px-5">
                        <p className="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-stone-500">
                          {item.eyebrow}
                        </p>
                        <h2 className="mt-3 text-xl font-semibold leading-tight text-stone-900">
                          {item.title}
                        </h2>
                      </div>
                    </div>
                  )}
                </div>

                <div className="flex flex-1 flex-col px-4 py-4">
                  <div className="pb-4 text-center">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-stone-500">
                      {item.eyebrow}
                    </p>
                    <h2 className="mt-2 text-xl font-semibold leading-tight text-stone-900">
                      {item.title}
                    </h2>
                    <div className="mt-3 border border-stone-200 bg-stone-50 px-3 py-2 text-left">
                      <p className="text-[0.62rem] font-semibold uppercase tracking-[0.18em] text-stone-500">
                        PMPro Delivery Selection
                      </p>
                      <p className="mt-1 text-sm font-medium text-stone-900">
                        {selectionLabel}: {selection}
                      </p>
                    </div>
                  </div>
                  <p className="text-sm leading-6 text-stone-600">
                    {item.description}
                  </p>
                  <div className="mt-auto space-y-3 pt-3">
                    <Button
                      asChild
                      className="w-full rounded-none"
                      style={{
                        backgroundColor: portalDesign.primaryActionBackground,
                        color: portalDesign.primaryActionText,
                      }}
                    >
                      <a href={item.href} target="_blank" rel="noreferrer">
                        View
                        <ExternalLink className="ml-2 h-4 w-4" />
                      </a>
                    </Button>
                    <Button asChild variant="outline" className="w-full rounded-none border-stone-300 text-black hover:bg-stone-100">
                      <Link to="/account">Update preference</Link>
                    </Button>
                  </div>
                </div>
              </article>
              );
            })}
          </div>
        </section>
      </motion.div>
    </div>
  );
};

export default PublicationsPage;
