import { getMembershipBenefits } from '@/lib/fakePaymentFlows';
import { AAC_CUTTING_EDGE_PODCASTS } from '@/lib/aacPodcasts';
import { normalizeAccountInfo } from '@/lib/memberProfile';

const USERS_KEY = 'aac_fake_users_v1';
const SESSION_KEY = 'aac_fake_session_v1';

const defaultPodcasts = AAC_CUTTING_EDGE_PODCASTS;

const readJson = (key, fallback) => {
  try {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : fallback;
  } catch (_error) {
    return fallback;
  }
};

const writeJson = (key, value) => {
  localStorage.setItem(key, JSON.stringify(value));
};

const normalizeEmail = (email) => String(email || '').trim().toLowerCase();

const makeId = (prefix) => `${prefix}_${Math.random().toString(36).slice(2, 10)}`;

const buildUserPayload = (record) => ({
  session: {
    user: {
      id: record.id,
      email: record.email,
    },
  },
  user: {
    id: record.id,
    email: record.email,
  },
  profile: record.profile,
});

const getUsers = () => readJson(USERS_KEY, []);

const saveUsers = (users) => writeJson(USERS_KEY, users);

const getSession = () => readJson(SESSION_KEY, null);

const saveSession = (session) => writeJson(SESSION_KEY, session);

const clearSession = () => localStorage.removeItem(SESSION_KEY);

const getDefaultProfile = ({ email, firstName, lastName }) => ({
  account_info: normalizeAccountInfo({
    first_name: firstName || '',
    last_name: lastName || '',
    email,
    photo_url: 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?auto=format&fit=crop&w=400&q=80',
    phone: '',
    street: '',
    city: '',
    state: '',
    zip: '',
    country: 'US',
    size: 'M',
    publication_pref: 'Digital',
    auto_renew: false,
    payment_method: '',
  }),
  profile_info: {
    member_id: `AAC-${Math.floor(100000 + Math.random() * 900000)}`,
    tier: '',
    renewal_date: '',
    status: 'Inactive',
  },
  benefits_info: getMembershipBenefits('Supporter'),
  membership_actions: {
    account_url: '',
    billing_url: '',
    cancel_url: '',
    current_level_id: null,
    current_level_checkout_url: '',
    levels: {},
  },
  grant_applications: [],
});

const mergeProfile = (currentProfile, updates) => {
  const merged = {
    ...currentProfile,
    ...updates,
    account_info: normalizeAccountInfo({
      ...(currentProfile?.account_info || {}),
      ...(updates?.account_info || {}),
    }),
    profile_info: {
      ...(currentProfile?.profile_info || {}),
      ...(updates?.profile_info || {}),
    },
    benefits_info: {
      ...(currentProfile?.benefits_info || {}),
      ...(updates?.benefits_info || {}),
    },
    membership_actions: {
      ...(currentProfile?.membership_actions || {}),
      ...(updates?.membership_actions || {}),
      levels: {
        ...(currentProfile?.membership_actions?.levels || {}),
        ...(updates?.membership_actions?.levels || {}),
      },
    },
    grant_applications: Array.isArray(updates?.grant_applications)
      ? updates.grant_applications
      : (currentProfile?.grant_applications || []),
  };

  if (!merged.account_info.email && currentProfile?.account_info?.email) {
    merged.account_info.email = currentProfile.account_info.email;
  }

  return merged;
};

const requireUserByEmail = (email) => {
  const users = getUsers();
  const user = users.find((entry) => entry.email === normalizeEmail(email));
  if (!user) {
    throw new Error('No account found for that email.');
  }
  return { user, users };
};

const requireCurrentUser = () => {
  const session = getSession();
  if (!session?.userId) {
    const error = new Error('Not authenticated');
    error.status = 401;
    throw error;
  }

  const users = getUsers();
  const user = users.find((entry) => entry.id === session.userId);
  if (!user) {
    clearSession();
    const error = new Error('Not authenticated');
    error.status = 401;
    throw error;
  }

  return { user, users };
};

export const fakeAuthDb = {
  async getCurrentMember() {
    const { user } = requireCurrentUser();
    return buildUserPayload(user);
  },

  async loginMember(email, password) {
    const { user } = requireUserByEmail(email);

    if (user.password !== password) {
      throw new Error('Invalid email or password.');
    }

    saveSession({ userId: user.id });
    return buildUserPayload(user);
  },

  async registerMember(email, password, options = {}) {
    const normalizedEmail = normalizeEmail(email);
    const users = getUsers();

    if (!normalizedEmail || !password) {
      throw new Error('Email and password are required.');
    }

    if (users.some((entry) => entry.email === normalizedEmail)) {
      throw new Error('An account with that email already exists.');
    }

    const firstName = options?.data?.first_name || '';
    const lastName = options?.data?.last_name || '';

    const record = {
      id: makeId('member'),
      email: normalizedEmail,
      password,
      profile: getDefaultProfile({
        email: normalizedEmail,
        firstName,
        lastName,
      }),
      createdAt: new Date().toISOString(),
    };

    users.push(record);
    saveUsers(users);
    saveSession({ userId: record.id });

    return {
      ...buildUserPayload(record),
      requires_email_verification: false,
      fakeBackend: true,
    };
  },

  async logoutMember() {
    clearSession();
    return { success: true, fakeBackend: true };
  },

  async requestPasswordReset(email) {
    requireUserByEmail(email);
    return { success: true, fakeBackend: true };
  },

  async changePassword(currentPassword, newPassword, confirmPassword) {
    const { user, users } = requireCurrentUser();

    if (!currentPassword || !newPassword || !confirmPassword) {
      throw new Error('Current password, new password, and confirmation are required.');
    }

    if (user.password !== currentPassword) {
      throw new Error('Your current password is incorrect.');
    }

    if (newPassword !== confirmPassword) {
      throw new Error('New password and confirmation must match.');
    }

    if (newPassword.length < 8) {
      throw new Error('New password must be at least 8 characters long.');
    }

    if (newPassword === currentPassword) {
      throw new Error('Choose a new password that is different from your current password.');
    }

    const nextUsers = users.map((entry) => (
      entry.id === user.id
        ? { ...entry, password: newPassword }
        : entry
    ));

    saveUsers(nextUsers);

    return { success: true, fakeBackend: true };
  },

  async updateMemberProfile(updates) {
    const { user, users } = requireCurrentUser();
    const nextProfile = mergeProfile(user.profile, updates);

    const nextUsers = users.map((entry) => (
      entry.id === user.id
        ? {
            ...entry,
            email: normalizeEmail(nextProfile.account_info?.email || entry.email),
            profile: nextProfile,
          }
        : entry
    ));

    saveUsers(nextUsers);
    const nextUser = nextUsers.find((entry) => entry.id === user.id);

    return {
      success: true,
      profile: nextUser.profile,
      fakeBackend: true,
    };
  },

  async submitContactMessage({ name, email, message }) {
    const existing = readJson('aac_fake_contact_messages_v1', []);
    existing.unshift({
      id: makeId('contact'),
      name,
      email,
      message,
      createdAt: new Date().toISOString(),
    });
    writeJson('aac_fake_contact_messages_v1', existing);
    return { success: true, fakeBackend: true };
  },

  async getLatestPodcasts() {
    return {
      podcasts: defaultPodcasts,
      fakeBackend: true,
    };
  },

  async getMemberTransactions() {
    return {
      transactions: [],
      fakeBackend: true,
    };
  },
};

export const shouldUseFakeMemberDb = () => {
  if (import.meta.env.VITE_USE_FAKE_DB === 'true') {
    return true;
  }

  if (typeof window !== 'undefined' && window.AAC_MEMBER_PORTAL_CONFIG?.useFakeMemberDb) {
    return true;
  }

  return false;
};
