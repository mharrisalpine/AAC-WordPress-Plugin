import { getMembershipBenefits } from '@/lib/fakePaymentFlows';
import { AAC_CUTTING_EDGE_PODCASTS } from '@/lib/aacPodcasts';
import { normalizeAccountInfo } from '@/lib/memberProfile';

const USERS_KEY = 'aac_fake_users_v1';
const SESSION_KEY = 'aac_fake_session_v1';
const PODCAST_LISTENS_KEY = 'aac_fake_podcast_listens_v1';

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

const getPodcastListens = () => readJson(PODCAST_LISTENS_KEY, []);

const savePodcastListens = (rows) => writeJson(PODCAST_LISTENS_KEY, rows);

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
    size: 'No T-shirt',
    aaj_pref: 'Digital',
    anac_pref: 'Digital',
    acj_pref: 'Digital',
    guidebook_pref: 'Digital',
    auto_renew: false,
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
  connected_accounts: [],
  family_membership: {
    mode: '',
    additional_adult: false,
    dependent_count: 0,
  },
  linked_parent_account: null,
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
    connected_accounts: Array.isArray(updates?.connected_accounts)
      ? updates.connected_accounts
      : (currentProfile?.connected_accounts || []),
    family_membership: updates?.family_membership
      ? { ...(currentProfile?.family_membership || {}), ...updates.family_membership }
      : (currentProfile?.family_membership || { mode: '', additional_adult: false, dependent_count: 0 }),
    linked_parent_account: updates?.linked_parent_account ?? currentProfile?.linked_parent_account ?? null,
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

  async submitGrantApplication(payload = {}) {
    const { user, users } = requireCurrentUser();
    const fields = Array.isArray(payload.fields)
      ? payload.fields.map((field) => ({
          field_key: String(field.field_key || '').trim(),
          label: String(field.label || '').trim(),
          type: String(field.type || '').trim(),
          value: String(field.value || '').trim(),
        }))
      : [];
    const fieldValue = (fieldKey) => fields.find((field) => field.field_key === fieldKey)?.value || '';
    const nextApplication = {
      id: makeId('grant'),
      review_application_id: null,
      grant_slug: String(payload.grant_slug || '').trim(),
      grant_name: String(payload.grant_name || '').trim(),
      category: String(payload.category || '').trim(),
      application_date: new Date().toISOString(),
      status: 'Submitted',
      status_key: 'submitted',
      project_title: String(payload.project_title || fieldValue('project_title') || '').trim(),
      requested_amount: String(payload.requested_amount || fieldValue('requested_amount') || '').trim(),
      objective_location: String(payload.objective_location || fieldValue('objective_location') || '').trim(),
      discipline: String(payload.discipline || fieldValue('discipline') || '').trim(),
      team_name: String(payload.team_name || fieldValue('team_name') || '').trim(),
      summary: String(payload.summary || fieldValue('summary') || '').trim(),
      fields,
      last_note: '',
      reviewed_at: '',
    };

    const nextProfile = mergeProfile(user.profile, {
      grant_applications: [nextApplication, ...(user.profile?.grant_applications || [])],
    });

    const nextUsers = users.map((entry) => (
      entry.id === user.id
        ? { ...entry, profile: nextProfile }
        : entry
    ));

    saveUsers(nextUsers);
    const nextUser = nextUsers.find((entry) => entry.id === user.id);

    return {
      success: true,
      application: nextApplication,
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

  async recordPodcastListen(payload = {}) {
    const { user } = requireCurrentUser();
    const episodeId = String(payload.episode_id || '').trim();
    if (!episodeId) {
      throw new Error('Episode ID is required.');
    }

    const normalizedStatus = String(payload.status || 'started').trim().toLowerCase() === 'completed'
      ? 'completed'
      : 'started';
    const completionPercent = Number.isFinite(Number(payload.completion_percent))
      ? Math.max(0, Math.min(100, Number(payload.completion_percent)))
      : 0;
    const now = new Date().toISOString();
    const listens = getPodcastListens();
    const existingIndex = listens.findIndex((entry) => entry.user_id === user.id && entry.episode_id === episodeId);
    const existing = existingIndex >= 0 ? listens[existingIndex] : null;
    const durationMs = Number.isFinite(Number(payload.duration_ms)) ? Math.max(0, Number(payload.duration_ms)) : Number(existing?.duration_ms || 0);
    let lastPositionMs = Number.isFinite(Number(payload.last_position_ms))
      ? Math.max(0, Number(payload.last_position_ms))
      : Number(existing?.last_position_ms || 0);
    const resolvedCompletionPercent = normalizedStatus === 'completed'
      ? Math.max(completionPercent, Number(existing?.completion_percent || 0), 100)
      : Math.max(completionPercent, Number(existing?.completion_percent || 0));

    if (normalizedStatus === 'completed' && durationMs > 0) {
      lastPositionMs = Math.max(lastPositionMs, durationMs);
    } else {
      lastPositionMs = Math.max(lastPositionMs, Number(existing?.last_position_ms || 0));
    }

    const nextRow = {
      id: existing?.id || makeId('podcast'),
      user_id: user.id,
      episode_id: episodeId,
      episode_title: String(payload.episode_title || existing?.episode_title || '').trim(),
      source_url: String(payload.source_url || existing?.source_url || '').trim(),
      source_page_url: String(payload.source_page_url || existing?.source_page_url || '').trim(),
      embed_url: String(payload.embed_url || existing?.embed_url || '').trim(),
      status: normalizedStatus === 'completed' || existing?.status === 'completed' ? 'completed' : 'started',
      completion_percent: resolvedCompletionPercent,
      duration_ms: durationMs,
      last_position_ms: lastPositionMs,
      started_at: existing?.started_at || now,
      completed_at: normalizedStatus === 'completed'
        ? now
        : (existing?.completed_at || ''),
      completion_count: normalizedStatus === 'completed'
        ? Number(existing?.completion_count || 0) + 1
        : Number(existing?.completion_count || 0),
      last_event_at: now,
      raw_payload: payload,
    };

    if (existingIndex >= 0) {
      listens[existingIndex] = nextRow;
    } else {
      listens.unshift(nextRow);
    }

    savePodcastListens(listens);

    return {
      success: true,
      listen: nextRow,
      fakeBackend: true,
    };
  },

  async getMemberTransactions() {
    return {
      transactions: [],
      fakeBackend: true,
    };
  },

  async validateInviteCode(code) {
    const normalizedCode = String(code || '').trim().toUpperCase();
    if (!normalizedCode) {
      throw new Error('Enter a valid invite code.');
    }

    return {
      success: true,
      invite: {
        code: normalizedCode,
        label: 'Dependent 1',
        type: 'dependent',
        status: 'pending',
        price: 45,
        parent_name: 'AAC Parent Member',
      },
      fakeBackend: true,
    };
  },

  async redeemInviteCode(payload = {}) {
    const normalizedEmail = normalizeEmail(payload.email);
    const inviteCode = String(payload.invite_code || '').trim().toUpperCase();
    const firstName = payload.first_name || '';
    const lastName = payload.last_name || '';
    const users = getUsers();
    const session = getSession();
    let record = null;

    if (session?.userId) {
      record = users.find((entry) => entry.id === session.userId) || null;
    }

    if (!record) {
      if (!normalizedEmail || !payload.password) {
        throw new Error('Enter your email and password to redeem this invite.');
      }

      record = users.find((entry) => entry.email === normalizedEmail) || null;

      if (record) {
        if (record.password !== payload.password) {
          throw new Error('Incorrect password. Please try again.');
        }
      } else {
        record = {
          id: makeId('member'),
          email: normalizedEmail,
          password: payload.password,
          profile: getDefaultProfile({
            email: normalizedEmail,
            firstName,
            lastName,
          }),
          createdAt: new Date().toISOString(),
        };
        users.push(record);
        saveUsers(users);
      }
    }

    record.profile = mergeProfile(record.profile, {
      linked_parent_account: {
        parent_user_id: 1,
        parent_name: 'AAC Parent Member',
        parent_email: 'parent@example.com',
        invite_code: inviteCode,
        type: 'dependent',
        label: 'Dependent 1',
        status: 'connected',
      },
    });

    const nextUsers = users.map((entry) => (entry.id === record.id ? record : entry));
    saveUsers(nextUsers);
    saveSession({ userId: record.id });

    return {
      success: true,
      invite: {
        code: inviteCode,
        label: 'Dependent 1',
        type: 'dependent',
        status: 'connected',
        price: 45,
        parent_name: 'AAC Parent Member',
      },
      linked_parent_account: record.profile.linked_parent_account,
      ...buildUserPayload(record),
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
