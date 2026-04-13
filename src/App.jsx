
import React, { useState, useEffect } from 'react';
import { Helmet } from 'react-helmet';
import { Outlet, useLocation } from 'react-router-dom';
import { Toaster } from '@/components/ui/toaster';
import Header from '@/components/Header';
import HomePage from '@/pages/HomePage';
import PortalSidebar from '@/components/PortalSidebar';
import { MembershipSignupModal } from '@/components/MembershipSignupModal';
import { useAuth } from '@/hooks/useAuth';
import { Button } from '@/components/ui/button';
import { useMembershipActions } from '@/hooks/useMembershipActions';
import { getExpirationWarningDetails, shouldPromptMembershipVerification } from '@/lib/membershipRenewal';

const ExpirationBanner = ({ details, onRenew }) => {
  if (!details) {
    return null;
  }

  const message = details.isExpired
    ? 'Your membership has expired. Renew now to restore uninterrupted member access.'
    : details.daysUntilExpiration === 0
      ? `Your membership expires today${details.formattedDate ? `, ${details.formattedDate}` : ''}. Renew now to avoid a lapse in access.`
      : `Your membership expires in ${details.daysUntilExpiration} day${details.daysUntilExpiration === 1 ? '' : 's'}${details.formattedDate ? `, on ${details.formattedDate}` : ''}. Renew now to keep your member access active.`;

  return (
    <div className="border-b border-[#f8c235]/25 bg-[#8f1515] text-white">
      <div className="mx-auto flex max-w-[1600px] flex-col gap-3 px-4 py-3 md:px-6 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <p className="text-[0.72rem] font-semibold uppercase tracking-[0.22em] text-[#f8c235]">Membership Expiration Notice</p>
          <p className="mt-1 text-sm leading-6 text-white">{message}</p>
        </div>
        <Button
          type="button"
          variant="secondary"
          className="shrink-0 border border-[#f8c235] bg-[#f8c235] px-5 text-sm font-semibold uppercase tracking-[0.14em] text-black hover:bg-[#ddb01d]"
          onClick={onRenew}
        >
          Renew Membership
        </Button>
      </div>
    </div>
  );
};

function App() {
  const { user, profile, loading, signOut } = useAuth();
  const [isCartOpen, setIsCartOpen] = useState(false);
  const [renewalModalOpen, setRenewalModalOpen] = useState(false);
  const location = useLocation();
  const [activeTab, setActiveTab] = useState('profile');
  const [portalMenuOpen, setPortalMenuOpen] = useState(false);
  const isDonateRoute = location.pathname === '/donate';
  const { openMembershipAction } = useMembershipActions();
  const expirationWarning = getExpirationWarningDetails(profile);

  useEffect(() => {
    setPortalMenuOpen(false);
  }, [location.pathname]);

  useEffect(() => {
    // The WordPress admin bar can bleed into the embedded/fullscreen portal layouts,
    // so we aggressively hide it on the frontend to keep the app shell consistent.
    const styleId = 'aac-hide-wordpress-admin-bar';
    let styleElement = document.getElementById(styleId);

    if (!styleElement) {
      styleElement = document.createElement('style');
      styleElement.id = styleId;
      styleElement.textContent = `
        html { margin-top: 0 !important; }
        body { margin-top: 0 !important; padding-top: 0 !important; }
        body.admin-bar { margin-top: 0 !important; padding-top: 0 !important; }
        #wpadminbar {
          display: none !important;
          visibility: hidden !important;
          opacity: 0 !important;
          pointer-events: none !important;
        }
      `;
      document.head.appendChild(styleElement);
    }

    const hideAdminBar = () => {
      document.documentElement.style.setProperty('margin-top', '0', 'important');

      if (document.body) {
        document.body.style.setProperty('margin-top', '0', 'important');
        document.body.style.setProperty('padding-top', '0', 'important');
        document.body.classList.remove('admin-bar');
      }

      const adminBar = document.getElementById('wpadminbar');
      if (adminBar) {
        adminBar.style.setProperty('display', 'none', 'important');
        adminBar.style.setProperty('visibility', 'hidden', 'important');
        adminBar.style.setProperty('opacity', '0', 'important');
        adminBar.style.setProperty('pointer-events', 'none', 'important');
        adminBar.setAttribute('aria-hidden', 'true');
      }
    };

    hideAdminBar();

    const observer = new MutationObserver(() => {
      hideAdminBar();
    });

    if (document.body) {
      observer.observe(document.body, { childList: true, subtree: true });
    }

    return () => observer.disconnect();
  }, []);

  useEffect(() => {
    // The renewal modal is meant to appear once per browser session when a member is
    // approaching expiration and does not have auto-renew enabled.
    if (!user?.id || !profile) {
      return;
    }
    const key = `aac_renewal_modal_${user.id}`;
    if (sessionStorage.getItem(key)) {
      return;
    }
    if (shouldPromptMembershipVerification(profile)) {
      sessionStorage.setItem(key, '1');
      setRenewalModalOpen(true);
    }
  }, [user?.id, profile]);

  if (loading) {
    return <div className="min-h-screen member-app-surface flex items-center justify-center text-stone-800">Loading...</div>;
  }

  const publicOutletPaths = new Set(['/donate', '/payment', '/success', '/login', '/linked-accounts', '/home', '/join']);
  const showPublicOutlet = publicOutletPaths.has(location.pathname);
  const handleLogout = async () => {
    const result = await signOut();
    if (!result?.error) {
      try {
        Object.keys(sessionStorage)
          .filter((key) => key.startsWith('aac_renewal_modal_'))
          .forEach((key) => sessionStorage.removeItem(key));
      } catch (error) {
        // Ignore sessionStorage cleanup failures.
      }

      const portalPageUrl = window.AAC_MEMBER_PORTAL_CONFIG?.portalPageUrl || '/membership';
      const normalizedPortalUrl = String(portalPageUrl).replace(/\/+$/, '');
      window.location.assign(`${normalizedPortalUrl}/#/login`);
    }
  };

  if (!user) {
    return (
      <div className="topo-lines flex min-h-screen flex-col">
        <Header
          variant="public"
          onLogout={() => {}}
          onCartClick={() => {}}
          onOpenPortalMenu={() => {}}
        />
        <main className="min-h-0 min-w-0 flex-1 overflow-y-auto">
          {showPublicOutlet ? <Outlet context={{ isCartOpen, setIsCartOpen, activeTab, setActiveTab }} /> : <HomePage />}
        </main>
        <Toaster />
      </div>
    );
  }
  
  const showHeader = !['/success'].includes(location.pathname);

  return (
    <>
      <MembershipSignupModal open={renewalModalOpen} onOpenChange={setRenewalModalOpen} mode="renewal" />
      <Helmet>
        <title>American Alpine Club - Member Portal</title>
        <meta name="description" content="Access your AAC membership card, rescue insurance, partner discounts, merchandise store, and account settings." />
      </Helmet>
      
      <div className="member-app-surface flex h-screen min-h-screen flex-col overflow-hidden">
        {/* Authenticated members stay inside the full app shell: header, banner, sticky
            sidebar, and a scrollable main content pane. Public routes render above. */}
        {showHeader ? (
          <>
            <Header
              onLogout={handleLogout}
              onCartClick={() => setIsCartOpen(true)}
              onOpenPortalMenu={() => setPortalMenuOpen(true)}
            />
            <ExpirationBanner
              details={expirationWarning}
              onRenew={() => void openMembershipAction('renew', { targetTier: profile?.profile_info?.tier || 'Partner' })}
            />
            <div className="flex min-h-0 flex-1 overflow-hidden">
              <PortalSidebar mobileOpen={portalMenuOpen} onMobileClose={() => setPortalMenuOpen(false)} />
              <main
                className={`mx-auto min-h-0 min-w-0 flex-1 overflow-y-auto ${isDonateRoute ? 'px-0 py-0' : 'px-4 py-6 md:pb-8'}`}
                style={{ paddingBottom: isDonateRoute ? 'env(safe-area-inset-bottom, 0px)' : 'calc(1.5rem + env(safe-area-inset-bottom, 0px))' }}
              >
                <div className={isDonateRoute ? '' : 'mx-auto max-w-7xl'}>
                  <Outlet context={{ isCartOpen, setIsCartOpen, activeTab, setActiveTab }} />
                </div>
              </main>
            </div>
          </>
        ) : (
          <main className="flex-1 px-4 py-6">
            <Outlet context={{ isCartOpen, setIsCartOpen, activeTab, setActiveTab }} />
          </main>
        )}
        <Toaster />
      </div>
    </>
  );
}

export default App;
