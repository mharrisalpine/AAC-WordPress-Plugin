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

export const TSHIRT_SIZE_OPTIONS = ['none', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'];

export const formatTShirtSizeLabel = (value, fallback = '') => {
  const normalized = String(value || '').trim();
  if (!normalized) {
    return fallback;
  }

  if (normalized === 'none' || normalized === 'No T-shirt') {
    return 'No T-shirt';
  }

  return normalized.startsWith('Unisex ') ? normalized : `Unisex ${normalized}`;
};

export const normalizeMagazineSubscriptions = (value) => {
  if (!Array.isArray(value)) {
    return [];
  }

  return value
    .map((item) => String(item || '').trim())
    .filter(Boolean);
};

export const formatMagazineSubscriptions = (value, fallback = 'None selected') => {
  const subscriptions = normalizeMagazineSubscriptions(value);

  return subscriptions.length ? subscriptions.join(', ') : fallback;
};

export const normalizeAccountInfo = (accountInfo = {}) => {
  const normalized = { ...accountInfo };
  const legacyPublicationPref = normalizePrintDigitalPreference(normalized.publication_pref);
  normalized.first_name = normalized.first_name || '';
  normalized.last_name = normalized.last_name || '';
  normalized.name = getFullName(normalized);
  normalized.street = normalized.street || '';
  normalized.address2 = normalized.address2 || '';
  normalized.city = normalized.city || '';
  normalized.state = normalized.state || '';
  normalized.zip = normalized.zip || '';
  normalized.country = normalized.country || '';
  normalized.publication_pref = legacyPublicationPref;
  normalized.aaj_pref = normalizePrintDigitalPreference(normalized.aaj_pref, legacyPublicationPref);
  normalized.anac_pref = normalizePrintDigitalPreference(normalized.anac_pref, legacyPublicationPref);
  normalized.acj_pref = normalizePrintDigitalPreference(normalized.acj_pref, legacyPublicationPref);
  normalized.guidebook_pref = normalizePrintDigitalPreference(normalized.guidebook_pref);
  normalized.magazine_subscriptions = normalizeMagazineSubscriptions(normalized.magazine_subscriptions);
  normalized.membership_discount_type = normalizeMembershipDiscountType(normalized.membership_discount_type);
  normalized.auto_renew = Boolean(normalized.auto_renew);
  return normalized;
};
