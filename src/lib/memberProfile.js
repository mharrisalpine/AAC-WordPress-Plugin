export const getFullName = (accountInfo = {}) => {
  const first = (accountInfo.first_name || '').trim();
  const last = (accountInfo.last_name || '').trim();
  const combined = [first, last].filter(Boolean).join(' ').trim();

  return combined || accountInfo.name || 'AAC Member';
};

export const normalizePrintDigitalPreference = (value, fallback = 'Digital') => {
  return value === 'Print' ? 'Print' : value === 'Digital' ? 'Digital' : fallback;
};

export const normalizeMembershipDiscountType = (value) => {
  return value === 'student' || value === 'military' ? value : '';
};

export const normalizeBooleanPreference = (value) => {
  if (typeof value === 'string') {
    const lowered = value.trim().toLowerCase();
    return ['1', 'true', 'yes', 'on'].includes(lowered);
  }

  return Boolean(value);
};

export const TSHIRT_SIZE_OPTIONS = [
  'No T-shirt',
  'Unisex X-Small',
  'Unisex Small',
  'Unisex Medium',
  'Unisex Large',
  'Unisex X-Large',
  'Unisex XX-Large',
];

export const normalizeTShirtSizeValue = (value, fallback = 'No T-shirt') => {
  const normalized = String(value || '').trim();
  if (!normalized) {
    return fallback;
  }

  const lowered = normalized.toLowerCase();
  if (['none', 'no t-shirt', 'no t shirt', 'n/a', 'na'].includes(lowered)) {
    return 'No T-shirt';
  }

  const directLabelMap = {
    'unisex x-small': 'Unisex X-Small',
    'unisex small': 'Unisex Small',
    'unisex medium': 'Unisex Medium',
    'unisex large': 'Unisex Large',
    'unisex x-large': 'Unisex X-Large',
    'unisex xx-large': 'Unisex XX-Large',
  };
  if (directLabelMap[lowered]) {
    return directLabelMap[lowered];
  }

  const compactSizeMap = {
    xs: 'Unisex X-Small',
    xsmall: 'Unisex X-Small',
    s: 'Unisex Small',
    m: 'Unisex Medium',
    l: 'Unisex Large',
    xl: 'Unisex X-Large',
    xlarge: 'Unisex X-Large',
    xxl: 'Unisex XX-Large',
    xxlarge: 'Unisex XX-Large',
    '2xl': 'Unisex XX-Large',
  };
  if (compactSizeMap[lowered]) {
    return compactSizeMap[lowered];
  }

  if (lowered.startsWith('unisex ')) {
    const compact = lowered.replace(/^unisex\s+/, '').replace(/[\s-]+/g, '');
    return compactSizeMap[compact] || fallback;
  }

  return fallback;
};

export const formatTShirtSizeLabel = (value, fallback = '') => {
  return normalizeTShirtSizeValue(value, fallback);
};

export const normalizeMagazineSubscriptions = (value) => {
  if (!Array.isArray(value)) {
    return [];
  }

  return value
    .map((item) => String(item || '').trim())
    .filter(Boolean);
};

export const normalizeBirthdateValue = (value) => {
  const normalized = String(value || '').trim();
  if (!normalized) {
    return '';
  }

  return /^\d{4}-\d{2}-\d{2}$/.test(normalized) ? normalized : '';
};

export const formatMagazineSubscriptions = (value, fallback = 'None selected') => {
  const subscriptions = normalizeMagazineSubscriptions(value);

  return subscriptions.length ? subscriptions.join(', ') : fallback;
};

export const normalizeAccountInfo = (accountInfo = {}) => {
  const normalized = { ...accountInfo };
  const derivedPublicationPref = normalizePrintDigitalPreference(
    normalized.publication_pref,
    normalizePrintDigitalPreference(
      normalized.aaj_pref,
      normalizePrintDigitalPreference(
        normalized.anac_pref,
        normalizePrintDigitalPreference(
          normalized.acj_pref,
          normalizePrintDigitalPreference(normalized.guidebook_pref),
        ),
      ),
    ),
  );
  normalized.first_name = normalized.first_name || '';
  normalized.last_name = normalized.last_name || '';
  normalized.name = getFullName(normalized);
  normalized.birthdate = normalizeBirthdateValue(normalized.birthdate);
  normalized.street = normalized.street || '';
  normalized.address2 = normalized.address2 || '';
  normalized.city = normalized.city || '';
  normalized.state = normalized.state || '';
  normalized.zip = normalized.zip || '';
  normalized.country = normalized.country || '';
  normalized.publication_pref = derivedPublicationPref;
  normalized.aaj_pref = normalizePrintDigitalPreference(normalized.aaj_pref, derivedPublicationPref);
  normalized.anac_pref = normalizePrintDigitalPreference(normalized.anac_pref, derivedPublicationPref);
  normalized.acj_pref = normalizePrintDigitalPreference(normalized.acj_pref, derivedPublicationPref);
  normalized.guidebook_pref = normalizePrintDigitalPreference(normalized.guidebook_pref);
  normalized.magazine_subscriptions = normalizeMagazineSubscriptions(normalized.magazine_subscriptions);
  normalized.membership_discount_type = normalizeMembershipDiscountType(normalized.membership_discount_type);
  normalized.size = normalizeTShirtSizeValue(normalized.size);
  normalized.auto_renew = Boolean(normalized.auto_renew);
  normalized.email_opt_out = normalizeBooleanPreference(normalized.email_opt_out);
  normalized.do_not_call = normalizeBooleanPreference(normalized.do_not_call);
  normalized.do_not_contact = normalizeBooleanPreference(normalized.do_not_contact);
  return normalized;
};
