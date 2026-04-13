import React, { useEffect, useState } from 'react';
import { Helmet } from 'react-helmet';
import { motion } from 'framer-motion';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { LockKeyhole, Mail } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuth } from '@/hooks/useAuth';
import { getPortalUiSettings } from '@/lib/portalSettings';
import { mainSiteHref } from '@/lib/mainWebsiteNav';
import loginHeroLeftImageDev from '@/assets/login-hero-left-image.jpg';

const PLUGIN_ASSET_BASE = mainSiteHref('/wp-content/plugins/aac-member-portal/app/assets');
const HERO_MEDIA_REV = '329';
const LOGIN_HERO_LEFT_IMAGE_URL = import.meta.env.DEV
  ? loginHeroLeftImageDev
  : `${PLUGIN_ASSET_BASE}/login-hero-left-image.jpg?v=${HERO_MEDIA_REV}`;

const getPortalRedirectTarget = (locationSearch) => {
  const searchCandidates = [locationSearch];

  if (typeof window !== 'undefined') {
    searchCandidates.push(window.location.search);
  }

  for (const search of searchCandidates) {
    if (!search) {
      continue;
    }

    const redirectTo = new URLSearchParams(search).get('redirect_to');
    if (!redirectTo || typeof window === 'undefined') {
      continue;
    }

    try {
      const targetUrl = new URL(redirectTo, window.location.origin);
      const appLoginUrl = new URL(window.location.href);
      const normalizedTarget = `${targetUrl.pathname}${targetUrl.search}${targetUrl.hash}`;
      const normalizedAppLogin = `${appLoginUrl.pathname}${appLoginUrl.search}${appLoginUrl.hash}`;

      if (targetUrl.origin !== window.location.origin || normalizedTarget === normalizedAppLogin) {
        return null;
      }

      return normalizedTarget;
    } catch {
      return null;
    }
  }

  return null;
};

const LoginPage = () => {
  const location = useLocation();
  const navigate = useNavigate();
  const { user, signIn, resetPassword, loading } = useAuth();
  const portalUiSettings = getPortalUiSettings();
  const portalContent = portalUiSettings.content;
  const portalDesign = portalUiSettings.design;
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [forgotMode, setForgotMode] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [authMessage, setAuthMessage] = useState('');
  const redirectTarget = getPortalRedirectTarget(location.search);
  const purchaseSuccess = new URLSearchParams(location.search).get('purchase_success') === '1';

  useEffect(() => {
    if (user && !purchaseSuccess) {
      if (redirectTarget) {
        window.location.assign(redirectTarget);
        return;
      }

      navigate('/profile', { replace: true });
    }
  }, [navigate, purchaseSuccess, redirectTarget, user]);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setSubmitting(true);
    setAuthMessage('');
    try {
      if (forgotMode) {
        const { error } = await resetPassword(email.trim());
        if (error) {
          setAuthMessage(error.message || 'We could not send the reset link.');
        }
        return;
      }

      const { error } = await signIn(email.trim(), password);
      if (error) {
        setAuthMessage(error.message || 'Incorrect password. Please try again.');
        return;
      }

      if (redirectTarget) {
        window.location.assign(redirectTarget);
        return;
      }

      navigate('/profile', { replace: true });
    } finally {
      setSubmitting(false);
    }
  };

  const handleForgotModeToggle = () => {
    setForgotMode((value) => !value);
    setAuthMessage('');
  };

  const handleEmailChange = (event) => {
    setEmail(event.target.value);
    if (authMessage) {
      setAuthMessage('');
    }
  };

  const handlePasswordChange = (event) => {
    setPassword(event.target.value);
    if (authMessage) {
      setAuthMessage('');
    }
  };

  const busy = loading || submitting;

  return (
    <>
      <Helmet>
        <title>Login - American Alpine Club</title>
        <meta name="description" content={portalContent.login_hero_description} />
      </Helmet>
      <div className="relative min-h-screen overflow-hidden bg-[#030000] text-white">
        <img
          src={LOGIN_HERO_LEFT_IMAGE_URL}
          alt=""
          aria-hidden="true"
          className="absolute inset-0 h-full w-full object-cover"
        />
        <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(3,0,0,0.24),rgba(3,0,0,0.72)),radial-gradient(circle_at_top,rgba(248,194,53,0.12),transparent_24%)]" />
        <div className="relative mx-auto flex min-h-screen max-w-6xl items-center px-4 pb-10 pt-24 sm:px-6 sm:pb-14 lg:px-8">
          <div className="grid w-full gap-8 lg:grid-cols-[0.95fr,0.75fr] lg:items-center">
          <motion.div
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45 }}
            className="relative pt-8 lg:pt-0"
          >
            <div className="relative flex h-full items-center">
              <div className="max-w-xl bg-black/44 px-6 py-6 backdrop-blur-[2px] sm:px-7 sm:py-7 lg:-translate-y-6">
                <p className="text-[0.72rem] font-semibold uppercase tracking-[0.3em] text-[#f8c235]">{portalContent.login_hero_kicker}</p>
                <h1 className="mt-4 max-w-xl text-4xl leading-[0.95] text-white sm:text-5xl lg:text-6xl">
                  {portalContent.login_hero_title}
                </h1>
                <p className="mt-5 max-w-xl text-base leading-7 text-white/84 sm:text-lg">
                  {portalContent.login_hero_description}
                </p>
              </div>
            </div>
          </motion.div>

          <motion.form
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.08 }}
            onSubmit={handleSubmit}
            className="paper-panel self-center rounded-[2rem] border border-white/24 bg-[#f7f1e8]/94 p-6 text-black shadow-[0_32px_80px_rgba(0,0,0,0.42)] backdrop-blur-md sm:p-8 lg:-translate-y-6"
          >
            <div className="mb-6 flex items-start justify-between gap-4">
              <div>
                <p className="text-[0.72rem] font-semibold uppercase tracking-[0.24em] text-[#8f1515]">
                  {forgotMode ? 'Reset password' : portalContent.login_form_kicker}
                </p>
                <h2 className="mt-2 text-3xl text-[#030000]">
                  {forgotMode ? 'Send a reset link.' : portalContent.login_form_title}
                </h2>
              </div>
              <div className="rounded-2xl bg-[#8f1515]/10 p-3 text-[#8f1515]">
                {forgotMode ? <Mail className="h-6 w-6" /> : <LockKeyhole className="h-6 w-6" />}
              </div>
            </div>

            {authMessage ? (
              <div
                className="mb-5 rounded-2xl border border-[#fca5a5]/40 bg-[#8f1515] px-4 py-3 text-sm font-medium text-white shadow-[0_14px_32px_rgba(143,21,21,0.28)]"
                role="alert"
                aria-live="polite"
              >
                {authMessage}
              </div>
            ) : null}

            {purchaseSuccess ? (
              <div
                className="mb-5 rounded-2xl border border-[#f8c235]/40 bg-[#1f1a08] px-4 py-3 text-sm font-medium text-white shadow-[0_14px_32px_rgba(31,26,8,0.28)]"
                role="status"
                aria-live="polite"
              >
                {portalContent.login_purchase_success_message}
              </div>
            ) : null}

            <div className="space-y-5">
              <div>
                <Label htmlFor="login-email" className="text-stone-900">Email</Label>
                <Input
                  id="login-email"
                  type="email"
                  value={email}
                  onChange={handleEmailChange}
                  required
                  className="mt-1 bg-white text-black"
                  autoComplete="email"
                />
              </div>

              {!forgotMode ? (
                <div>
                  <Label htmlFor="login-password" className="text-stone-900">Password</Label>
                  <Input
                    id="login-password"
                    type="password"
                    value={password}
                    onChange={handlePasswordChange}
                    required
                    className="mt-1 bg-white text-black"
                  />
                </div>
              ) : null}
            </div>

            <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <button
                type="button"
                onClick={handleForgotModeToggle}
                className="text-left text-sm font-medium text-[#8f1515] transition-colors hover:text-[#6b1010]"
              >
                {forgotMode ? 'Back to sign in' : portalContent.login_forgot_password_label}
              </button>
              <Link to="/join" className="text-sm font-medium text-stone-600 transition-colors hover:text-[#8f1515]">
                {portalContent.login_join_link_label}
              </Link>
            </div>

            <Button
              type="submit"
              disabled={busy}
              className="mt-8 h-12 w-full rounded-full text-base"
              style={{
                backgroundColor: portalDesign.secondaryActionBackground,
                color: portalDesign.secondaryActionText,
              }}
            >
              {busy ? 'Please wait…' : forgotMode ? 'Send reset link' : portalContent.login_submit_label}
            </Button>
          </motion.form>
          </div>
        </div>
      </div>
    </>
  );
};

export default LoginPage;
