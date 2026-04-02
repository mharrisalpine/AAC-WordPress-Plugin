import { apiRequest, setAuthToken, setRestNonce } from '@/lib/apiClient';
import { AAC_CUTTING_EDGE_PODCASTS, normalizePodcastList } from '@/lib/aacPodcasts';
import { fakeAuthDb, shouldUseFakeMemberDb } from '@/lib/fakeMemberDb';

const withFallback = async (remoteCall, fallbackCall) => {
  if (shouldUseFakeMemberDb()) {
    return fallbackCall();
  }

  try {
    return await remoteCall();
  } catch (error) {
    const shouldFallback =
      error?.status === 404 ||
      error?.message === 'Failed to fetch' ||
      error?.message?.includes('NetworkError') ||
      error?.message?.includes('Load failed');

    if (!shouldFallback) {
      throw error;
    }

    console.warn('Falling back to fake member database:', error.message);
    return fallbackCall();
  }
};

export const getCurrentMember = () =>
  withFallback(
    () => apiRequest('/me'),
    () => fakeAuthDb.getCurrentMember()
  );

export async function loginMember(email, password) {
  const data = await withFallback(
    () => apiRequest('/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }),
    () => fakeAuthDb.loginMember(email, password)
  );

  setAuthToken(data.token || null);
  setRestNonce(data.restNonce || null);
  return data;
}

export async function registerMember(email, password, options = {}) {
  const data = await withFallback(
    () => apiRequest('/register', {
      method: 'POST',
      body: JSON.stringify({
        email,
        password,
        first_name: options?.data?.first_name || '',
        last_name: options?.data?.last_name || '',
      }),
    }),
    () => fakeAuthDb.registerMember(email, password, options)
  );

  setAuthToken(data.token || null);
  setRestNonce(data.restNonce || null);
  return data;
}

export async function logoutMember() {
  try {
    return await withFallback(
      () => apiRequest('/logout', { method: 'POST' }),
      () => fakeAuthDb.logoutMember()
    );
  } finally {
    setAuthToken(null);
    setRestNonce(null);
  }
}

export const requestPasswordReset = (email) =>
  withFallback(
    () => apiRequest('/reset-password', {
      method: 'POST',
      body: JSON.stringify({ email }),
    }),
    () => fakeAuthDb.requestPasswordReset(email)
  );

export async function changeMemberPassword(currentPassword, newPassword, confirmPassword) {
  const data = await withFallback(
    () => apiRequest('/change-password', {
      method: 'POST',
      body: JSON.stringify({
        current_password: currentPassword,
        new_password: newPassword,
        confirm_password: confirmPassword,
      }),
    }),
    () => fakeAuthDb.changePassword(currentPassword, newPassword, confirmPassword)
  );

  if (data?.restNonce) {
    setRestNonce(data.restNonce);
  }

  return data;
}

export const updateMemberProfile = (updates) =>
  withFallback(
    () => apiRequest('/profile', {
      method: 'PATCH',
      body: JSON.stringify(updates),
    }),
    () => fakeAuthDb.updateMemberProfile(updates)
  );

export const submitContactMessage = ({ name, email, message }) =>
  withFallback(
    () => apiRequest('/contact', {
      method: 'POST',
      body: JSON.stringify({ name, email, message }),
    }),
    () => fakeAuthDb.submitContactMessage({ name, email, message })
  );

export const getLatestPodcasts = async () => {
  try {
    if (!shouldUseFakeMemberDb()) {
      const data = await apiRequest('/podcasts');
      const podcasts = normalizePodcastList(data?.podcasts);

      return {
        ...data,
        podcasts: podcasts.length ? podcasts : AAC_CUTTING_EDGE_PODCASTS,
      };
    }
  } catch (error) {
    console.warn('Falling back to AAC podcast defaults:', error?.message || error);
  }

  const data = await fakeAuthDb.getLatestPodcasts();
  const podcasts = normalizePodcastList(data?.podcasts);

  return {
    ...data,
    podcasts: podcasts.length ? podcasts : AAC_CUTTING_EDGE_PODCASTS,
  };
};

export const getMemberTransactions = () =>
  withFallback(
    () => apiRequest('/transactions'),
    () => fakeAuthDb.getMemberTransactions()
  );
