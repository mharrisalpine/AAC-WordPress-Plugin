import React, { createContext, useEffect, useState, useCallback, useMemo } from 'react';
import { useToast } from '@/components/ui/use-toast';
import {
  changeMemberPassword,
  getCurrentMember,
  loginMember,
  logoutMember,
  registerMember,
  requestPasswordReset,
  updateMemberProfile,
} from '@/lib/memberApi';

export const AuthContext = createContext(undefined);

export const AuthProvider = ({ children }) => {
  const { toast } = useToast();

  const [user, setUser] = useState(null);
  const [session, setSession] = useState(null);
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);

  const applyAuthState = useCallback((data) => {
    const nextUser = data?.user ?? null;
    setUser(nextUser);
    setSession(data?.session ?? (nextUser ? { user: nextUser } : null));
    setProfile(data?.profile ?? null);
  }, []);

  const refreshProfile = useCallback(async () => {
    setLoading(true);
    try {
      const data = await getCurrentMember();
      applyAuthState(data);
      return { data, error: null };
    } catch (error) {
      const isLoggedOutState =
        error.status === 401 ||
        (error.status === 403 && error?.payload?.code === 'rest_cookie_invalid_nonce');

      if (isLoggedOutState) {
        applyAuthState(null);
        return { data: null, error: null };
      }

      console.error('Error fetching member session:', error);
      toast({
        variant: 'destructive',
        title: 'Authentication Error',
        description: error.message || 'There was an issue loading your member profile.',
      });
      return { data: null, error };
    } finally {
      setLoading(false);
    }
  }, [applyAuthState, toast]);

  useEffect(() => {
    refreshProfile();
  }, [refreshProfile]);

  const signUp = useCallback(async (email, password, options) => {
    setLoading(true);
    try {
      const data = await registerMember(email, password, options);
      applyAuthState(data);
      toast({
        title: 'Welcome!',
        description: data?.requires_email_verification
          ? 'Your account was created. Please verify your email before logging in.'
          : 'Your account was created successfully.',
      });
      return { user: data?.user ?? null, error: null };
    } catch (error) {
      toast({
        variant: 'destructive',
        title: 'Sign up Failed',
        description: error.message || 'Something went wrong',
      });
      return { user: null, error };
    } finally {
      setLoading(false);
    }
  }, [applyAuthState, toast]);

  /**
   * Register, then PATCH profile before swapping to the main app (avoids Login unmounting mid-flow).
   */
  const signUpWithProfile = useCallback(async (email, password, registerOpts, { account_info, profile_info }) => {
    setLoading(true);
    try {
      const data = await registerMember(email, password, registerOpts);
      applyAuthState(data);

      const baseAcc = data?.profile?.account_info || {};
      const baseProf = data?.profile?.profile_info || {};
      await updateMemberProfile({
        account_info: { ...baseAcc, ...account_info },
        profile_info: { ...baseProf, ...profile_info },
      });

      const merged = await getCurrentMember();
      applyAuthState(merged);

      toast({
        title: 'Welcome!',
        description: data?.requires_email_verification
          ? 'Your account was created. Please verify your email before logging in.'
          : 'Your account was created successfully.',
      });
      return { error: null };
    } catch (error) {
      toast({
        variant: 'destructive',
        title: 'Sign up Failed',
        description: error.message || 'Something went wrong',
      });
      return { error };
    } finally {
      setLoading(false);
    }
  }, [applyAuthState, toast]);

  const signIn = useCallback(async (email, password) => {
    setLoading(true);
    try {
      const data = await loginMember(email, password);
      applyAuthState(data);
      return { error: null };
    } catch (error) {
      toast({
        variant: 'destructive',
        title: 'Sign in Failed',
        description: error.message || 'Something went wrong',
      });
      return { error };
    } finally {
      setLoading(false);
    }
  }, [applyAuthState, toast]);

  const signOut = useCallback(async () => {
    setLoading(true);
    try {
      await logoutMember();
      applyAuthState(null);
      return { error: null };
    } catch (error) {
      const isAlreadyLoggedOut =
        error?.status === 401 ||
        (error?.status === 403 && error?.payload?.code === 'rest_cookie_invalid_nonce');

      if (isAlreadyLoggedOut) {
        applyAuthState(null);
        return { error: null };
      }

      toast({
        variant: 'destructive',
        title: 'Sign out Failed',
        description: error.message || 'Something went wrong',
      });
      return { error };
    } finally {
      setLoading(false);
    }
  }, [applyAuthState, toast]);

  const updateProfile = useCallback(async (updates) => {
    if (!user) {
      toast({ variant: 'destructive', title: 'Not authenticated' });
      return { error: new Error('Not authenticated') };
    }

    try {
      const data = await updateMemberProfile(updates);
      if (data?.profile) {
        setProfile(data.profile);
      } else {
        await refreshProfile();
      }
      return { error: null };
    } catch (error) {
      toast({ variant: 'destructive', title: 'Update failed', description: error.message });
      return { error };
    }
  }, [refreshProfile, toast, user]);

  const resetPassword = useCallback(async (email) => {
    setLoading(true);
    try {
      await requestPasswordReset(email);
      toast({
        title: 'Check your email',
        description: 'A password reset link has been sent to your email address.',
      });
      return { error: null };
    } catch (error) {
      toast({
        variant: 'destructive',
        title: 'Password Reset Failed',
        description: error.message,
      });
      return { error };
    } finally {
      setLoading(false);
    }
  }, [toast]);

  const changePassword = useCallback(async (currentPassword, newPassword, confirmPassword) => {
    setLoading(true);
    try {
      const data = await changeMemberPassword(currentPassword, newPassword, confirmPassword);
      if (data?.user || data?.profile) {
        applyAuthState({
          user: data?.user ?? user,
          session: data?.user ? { user: data.user } : session,
          profile: data?.profile ?? profile,
        });
      } else {
        await refreshProfile();
      }
      toast({
        title: 'Password updated',
        description: 'Your AAC portal password has been changed successfully.',
      });
      return { error: null };
    } catch (error) {
      toast({
        variant: 'destructive',
        title: 'Password Update Failed',
        description: error.message || 'Unable to change your password.',
      });
      return { error };
    } finally {
      setLoading(false);
    }
  }, [applyAuthState, profile, refreshProfile, session, toast, user]);

  const value = useMemo(() => ({
    user,
    session,
    profile,
    loading,
    signUp,
    signUpWithProfile,
    signIn,
    signOut,
    updateProfile,
    resetPassword,
    changePassword,
    refreshProfile,
  }), [user, session, profile, loading, signUp, signUpWithProfile, signIn, signOut, updateProfile, resetPassword, changePassword, refreshProfile]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};
