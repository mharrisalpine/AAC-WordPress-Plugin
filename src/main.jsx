
import React from 'react';
import ReactDOM from 'react-dom/client';
import { Routes, Route } from 'react-router-dom';
import App from '@/App';
import ProductDetailPage from '@/pages/ProductDetailPage';
import SuccessPage from '@/pages/SuccessPage';
import FakePaymentPage from '@/pages/FakePaymentPage';
import MemberPortal from '@/pages/MemberPortal';
import MeetupsPage from '@/pages/MeetupsPage';
import ContactPage from '@/pages/ContactPage';
import DonationPage from '@/pages/DonationPage';
import MembershipManagementPage from '@/pages/MembershipManagementPage';
import LoginPage from '@/pages/LoginPage';
import MemberProfilePage from '@/pages/MemberProfilePage';
import ChangePasswordPage from '@/pages/ChangePasswordPage';
import GrantApplicationPage from '@/pages/GrantApplicationPage';
import LodgingPage from '@/pages/LodgingPage';
import PublicationsPage from '@/pages/PublicationsPage';
import LinkedAccountsPage from '@/pages/LinkedAccountsPage';
import MemberJoinPage from '@/pages/MemberJoinPage';
import HomePage from '@/pages/HomePage';
import '@/index.css';
import { Toaster } from '@/components/ui/toaster';
import { AuthProvider } from '@/contexts/AppAuthContext';
import { CartProvider } from '@/hooks/useCart';
import { AppRouter } from '@/lib/router';

const WP_PORTAL_MOUNT_ID = 'aac-member-portal-root';
const config = typeof window !== 'undefined' ? window.AAC_MEMBER_PORTAL_CONFIG : undefined;
const preferredId = config?.mountId || WP_PORTAL_MOUNT_ID;
const mountElement =
  document.getElementById(preferredId) ||
  document.getElementById(WP_PORTAL_MOUNT_ID) ||
  document.getElementById('root');

if (!mountElement) {
  throw new Error(
    `AAC Member Portal mount element not found (tried #${preferredId}, #${WP_PORTAL_MOUNT_ID}, #root).`
  );
}

ReactDOM.createRoot(mountElement).render(
  <React.StrictMode>
    <AppRouter>
      <AuthProvider>
        <CartProvider>
          <Routes>
            <Route path="/" element={<App />}>
              <Route index element={<MemberProfilePage />} />
              <Route path="profile" element={<MemberProfilePage />} />
              <Route path="change-password" element={<ChangePasswordPage />} />
              <Route path="product/:id" element={<ProductDetailPage />} />
              <Route path="success" element={<SuccessPage />} />
              <Route path="payment" element={<FakePaymentPage />} />
              <Route path="store" element={<MemberPortal storeTab="store" />} />
              <Route path="rescue" element={<MemberPortal storeTab="rescue" />} />
              <Route path="discounts" element={<MemberPortal storeTab="discounts" />} />
              <Route path="podcasts" element={<MemberPortal storeTab="podcasts" />} />
              <Route path="meetups" element={<MeetupsPage />} />
              <Route path="grants" element={<GrantApplicationPage />} />
              <Route path="lodging" element={<LodgingPage />} />
              <Route path="home" element={<HomePage />} />
              <Route path="join" element={<MemberJoinPage />} />
              <Route path="publications" element={<PublicationsPage />} />
              <Route path="linked-accounts" element={<LinkedAccountsPage />} />
              <Route path="membership" element={<MembershipManagementPage />} />
              <Route path="contact" element={<ContactPage />} />
              <Route path="account" element={<MemberPortal storeTab="account" />} />
              <Route path="donate" element={<DonationPage />} />
              <Route path="login" element={<LoginPage />} />
            </Route>
          </Routes>
          <Toaster />
        </CartProvider>
      </AuthProvider>
    </AppRouter>
  </React.StrictMode>
);
