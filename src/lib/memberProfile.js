export const getFullName = (accountInfo = {}) => {
  const first = (accountInfo.first_name || '').trim();
  const last = (accountInfo.last_name || '').trim();
  const combined = [first, last].filter(Boolean).join(' ').trim();

  return combined || accountInfo.name || 'AAC Member';
};

export const normalizePrintDigitalPreference = (value, fallback = 'Print') => {
  return value === 'Print' ? 'Print' : value === 'Digital' ? 'Digital' : fallback;
};

export const normalizeMembershipDiscountType = (value) => {
  return value === 'student' || value === 'military' ? value : '';
};

export const TSHIRT_SIZE_OPTIONS = [
  'No T-shirt',
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

export const normalizeBirthdateValue = (value) => {
  const normalized = String(value || '').trim();
  if (!normalized) {
    return '';
  }

  return /^\d{4}-\d{2}-\d{2}$/.test(normalized) ? normalized : '';
};

export const normalizeDateFieldValue = (value) => {
  const normalized = String(value || '').trim();
  if (!normalized) {
    return '';
  }

  return /^\d{4}-\d{2}-\d{2}$/.test(normalized) ? normalized : '';
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
  normalized.shipping_address_different = Boolean(normalized.shipping_address_different);
  normalized.shipping_address_1 = normalized.shipping_address_1 || '';
  normalized.shipping_address_2 = normalized.shipping_address_2 || '';
  normalized.shipping_city = normalized.shipping_city || '';
  normalized.shipping_state = normalized.shipping_state || '';
  normalized.shipping_postal_code = normalized.shipping_postal_code || '';
  normalized.shipping_country = normalized.shipping_country || '';
  normalized.emergency_contact_first_name = normalized.emergency_contact_first_name || '';
  normalized.emergency_contact_last_name = normalized.emergency_contact_last_name || '';
  normalized.emergency_contact_phone = normalized.emergency_contact_phone || '';
  normalized.emergency_contact_email = normalized.emergency_contact_email || '';
  normalized.emergency_contact_relationship = normalized.emergency_contact_relationship || '';
  normalized.student_university = normalized.student_university || normalized.university_or_school || normalized.university_school || normalized.school || '';
  normalized.student_university_id = normalized.student_university_id || normalized.university_id || normalized.university_school_id || '';
  normalized.graduation_date = normalizeDateFieldValue(normalized.graduation_date || normalized.student_graduation_date || normalized.aac_graduation_date);
  normalized.service_component = normalized.service_component || normalized.service_branch || normalized.military_service_component || normalized.aac_service_component || '';
  normalized.emergency_contact_relationship_options = Array.isArray(normalized.emergency_contact_relationship_options)
    ? normalized.emergency_contact_relationship_options
        .map((option) => {
          if (typeof option === 'string') {
            return { value: option, label: option };
          }

          if (option && typeof option === 'object') {
            const value = String(option.value || option.label || '').trim();
            const label = String(option.label || option.value || '').trim();
            if (!value || !label) {
              return null;
            }

            return { value, label };
          }

          return null;
        })
        .filter(Boolean)
    : [];
  normalized.publication_pref = derivedPublicationPref;
  normalized.aaj_pref = normalizePrintDigitalPreference(normalized.aaj_pref, derivedPublicationPref);
  normalized.anac_pref = normalizePrintDigitalPreference(normalized.anac_pref, derivedPublicationPref);
  normalized.acj_pref = normalizePrintDigitalPreference(normalized.acj_pref, derivedPublicationPref);
  normalized.guidebook_pref = normalizePrintDigitalPreference(normalized.guidebook_pref);
  normalized.aaj_preference_last_updated = normalized.aaj_preference_last_updated || '';
  normalized.anac_preference_last_updated = normalized.anac_preference_last_updated || '';
  normalized.american_climbing_journal_preference_last_updated = normalized.american_climbing_journal_preference_last_updated || '';
  normalized.guidebook_preferences_last_updated = normalized.guidebook_preferences_last_updated || '';
  normalized.membership_discount_type = normalizeMembershipDiscountType(normalized.membership_discount_type);
  normalized.size = normalizeTShirtSizeValue(normalized.size);
  normalized.auto_renew = Boolean(normalized.auto_renew);
  delete normalized.email_opt_out;
  delete normalized.do_not_call;
  delete normalized.do_not_contact;
  return normalized;
};
