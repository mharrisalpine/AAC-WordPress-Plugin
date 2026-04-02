
import React, { useState, useEffect } from 'react';
import { Helmet } from 'react-helmet';
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import { Toaster } from '@/components/ui/toaster';
import Header from '@/components/Header';
import MemberJoinPage from '@/pages/MemberJoinPage';
import PortalSidebar from '@/components/PortalSidebar';
import { MembershipSignupModal } from '@/components/MembershipSignupModal';
import { useAuth } from '@/hooks/useAuth';
import { shouldPromptMembershipVerification } from '@/lib/membershipRenewal';

function App() {
  const { user, profile, loading, signOut } = useAuth();
  const navigate = useNavigate();
  const [isCartOpen, setIsCartOpen] = useState(false);
  const [renewalModalOpen, setRenewalModalOpen] = useState(false);
  const location = useLocation();
  const [activeTab, setActiveTab] = useState('profile');
  const [portalMenuOpen, setPortalMenuOpen] = useState(false);
  const isDonateRoute = location.pathname === '/donate';

  useEffect(() => {
    setPortalMenuOpen(false);
  }, [location.pathname]);

  useEffect(() => {
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
    return <div className="min-h-screen topo-lines flex items-center justify-center text-stone-800">Loading...</div>;
  }

  const publicOutletPaths = new Set(['/donate', '/payment', '/success', '/login']);
  const showPublicOutlet = publicOutletPaths.has(location.pathname);
  const handleLogout = async () => {
    const result = await signOut();
    if (!result?.error) {
      navigate('/login', { replace: true });
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
          {showPublicOutlet ? <Outlet context={{ isCartOpen, setIsCartOpen, activeTab, setActiveTab }} /> : <MemberJoinPage />}
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
      
      <div className="topo-lines flex min-h-screen flex-col">
        {showHeader ? (
          <>
            <Header
              onLogout={handleLogout}
              onCartClick={() => setIsCartOpen(true)}
              onOpenPortalMenu={() => setPortalMenuOpen(true)}
            />
            <div className="flex min-h-0 flex-1">
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
