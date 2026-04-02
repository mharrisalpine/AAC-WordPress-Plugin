import React, { useEffect, useState } from 'react';
import { Helmet } from 'react-helmet';
import { motion } from 'framer-motion';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { LockKeyhole, Mail } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuth } from '@/hooks/useAuth';

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
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [forgotMode, setForgotMode] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const redirectTarget = getPortalRedirectTarget(location.search);

  useEffect(() => {
    if (user) {
      if (redirectTarget) {
        window.location.assign(redirectTarget);
        return;
      }

      navigate('/', { replace: true });
    }
  }, [navigate, redirectTarget, user]);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setSubmitting(true);
    try {
      if (forgotMode) {
        await resetPassword(email.trim());
        return;
      }

      const { error } = await signIn(email.trim(), password);
      if (!error) {
        if (redirectTarget) {
          window.location.assign(redirectTarget);
          return;
        }

        navigate('/', { replace: true });
      }
    } finally {
      setSubmitting(false);
    }
  };

  const busy = loading || submitting;

  return (
    <>
      <Helmet>
        <title>Login - American Alpine Club</title>
        <meta name="description" content="Sign in to your AAC member portal account." />
      </Helmet>
      <div className="relative min-h-screen overflow-hidden bg-[#030000] pt-24 text-white">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(248,194,53,0.18),transparent_28%),linear-gradient(180deg,rgba(3,0,0,0.9),rgba(3,0,0,0.98))]" />
        <div className="relative mx-auto grid max-w-6xl gap-10 px-4 pb-16 sm:px-6 lg:grid-cols-[0.95fr,1.05fr] lg:items-center">
          <motion.div
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45 }}
            className="pt-8"
          >
            <p className="text-[0.72rem] font-semibold uppercase tracking-[0.3em] text-[#f8c235]">Member access</p>
            <h1 className="mt-4 max-w-xl text-4xl leading-[0.95] text-white sm:text-5xl lg:text-6xl">
              Sign in to your AAC portal.
            </h1>
            <p className="mt-5 max-w-xl text-base leading-7 text-white/72 sm:text-lg">
              Access your membership details, rescue information, discounts, store purchases, and account settings in one place.
            </p>
          </motion.div>

          <motion.form
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.08 }}
            onSubmit={handleSubmit}
            className="paper-panel rounded-[2rem] border border-white/20 p-6 text-black shadow-2xl sm:p-8"
          >
            <div className="mb-6 flex items-start justify-between gap-4">
              <div>
                <p className="text-[0.72rem] font-semibold uppercase tracking-[0.24em] text-[#8f1515]">
                  {forgotMode ? 'Reset password' : 'Login'}
                </p>
                <h2 className="mt-2 text-3xl text-[#030000]">
                  {forgotMode ? 'Send a reset link.' : 'Welcome back.'}
                </h2>
              </div>
              <div className="rounded-2xl bg-[#8f1515]/10 p-3 text-[#8f1515]">
                {forgotMode ? <Mail className="h-6 w-6" /> : <LockKeyhole className="h-6 w-6" />}
              </div>
            </div>

            <div className="space-y-5">
              <div>
                <Label htmlFor="login-email" className="text-stone-900">Email</Label>
                <Input
                  id="login-email"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                  className="mt-1 bg-white text-black"
                />
              </div>

              {!forgotMode ? (
                <div>
                  <Label htmlFor="login-password" className="text-stone-900">Password</Label>
                  <Input
                    id="login-password"
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
                    className="mt-1 bg-white text-black"
                  />
                </div>
              ) : null}
            </div>

            <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <button
                type="button"
                onClick={() => setForgotMode((value) => !value)}
                className="text-left text-sm font-medium text-[#8f1515] transition-colors hover:text-[#6b1010]"
              >
                {forgotMode ? 'Back to sign in' : 'Forgot your password?'}
              </button>
              <Link to="/#membership-form" className="text-sm font-medium text-stone-600 transition-colors hover:text-[#8f1515]">
                Need to join?
              </Link>
            </div>

            <Button
              type="submit"
              disabled={busy}
              className="mt-8 h-12 w-full rounded-full bg-[#f8c235] text-base text-black hover:bg-[#dda914]"
            >
              {busy ? 'Please wait…' : forgotMode ? 'Send reset link' : 'Sign in'}
            </Button>
          </motion.form>
        </div>
      </div>
    </>
  );
};

export default LoginPage;
