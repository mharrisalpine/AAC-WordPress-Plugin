/**
 * Display membership tiles for signup / renewal.
 * Legacy stored id `Lifetime` still maps to Advocate for backward compatibility.
 */
export const MEMBERSHIP_TIER_OPTIONS = [
  {
    id: 'Free',
    label: 'Free',
    pmproLevelId: 1,
    blurb: 'Create an account, preview the portal, and receive promotional offers.',
    priceCents: 0,
    benefits: [
      'Create your AAC portal account',
      'Preview your profile and account settings',
      'Receive AAC promotional emails and promo codes',
      'Upgrade anytime to unlock paid member benefits',
      'No discounts, rescue coverage, or member store access',
    ],
  },
  {
    id: 'Supporter',
    label: 'Supporter',
    pmproLevelId: 2,
    blurb: 'Core member benefits and community access.',
    priceCents: 4500,
    benefits: [
      'Digital member communications and club updates',
      'Access to partner discounts and store offers',
      'AAC community events and member network access',
      'AAC email newsletter and climbing news',
      'Discounts on select AAC events and programs',
      'Support for climbing conservation and access advocacy',
    ],
  },
  {
    id: 'Partner',
    label: 'Partner',
    pmproLevelId: 3,
    blurb: 'Enhanced rescue & medical benefit levels.',
    priceCents: 9500,
    benefits: [
      '$50,000 in rescue coverage',
      '$5,000 in medical coverage',
      'Everything included in Supporter',
      'Documentation and support for rescue benefit claims',
      'Eligible for AAC grants and awards (where applicable)',
      'Member pricing on AAC publications and resources',
    ],
  },
  {
    id: 'Leader',
    label: 'Leader',
    pmproLevelId: 4,
    blurb: 'Highest published annual-tier benefit limits.',
    priceCents: 15000,
    benefits: [
      '$100,000 in rescue coverage',
      '$10,000 in medical coverage',
      'Everything included in Partner',
      'Higher published ceilings for rescue and medical benefits',
      'Priority consideration for select AAC programs',
      'Full mid-tier benefits package for active climbers',
    ],
  },
  {
    id: 'Advocate',
    label: 'Advocate',
    pmproLevelId: 5,
    blurb: 'Highest tier of annual member support.',
    priceCents: 50000,
    benefits: [
      'Annual Advocate membership',
      '$100,000 in rescue coverage',
      '$10,000 in medical coverage',
      'Everything included in Leader',
      'Expanded support for the AAC mission and climbing community',
      'Recognition as an Advocate-level member',
    ],
  },
];

export const DONATION_OPTIONS_USD = [
  { value: 0, label: 'No thanks' },
  { value: 5, label: '$5' },
  { value: 10, label: '$10' },
  { value: 15, label: '$15' },
  { value: 30, label: '$30' },
  { value: 50, label: '$50' },
  { value: 250, label: '$250' },
];

export const PHONE_TYPE_OPTIONS = [
  { value: 'mobile', label: 'Mobile' },
  { value: 'home', label: 'Home' },
  { value: 'work', label: 'Work' },
];

export const TSHIRT_SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'];

/** Map legacy stored tier ids to a canonical option id. */
export function normalizeTierId(raw) {
  if (!raw || typeof raw !== 'string') {
    return 'Partner';
  }
  if (raw === 'Lifetime') {
    return 'Advocate';
  }
  return MEMBERSHIP_TIER_OPTIONS.some((t) => t.id === raw) ? raw : 'Partner';
}

export function getTierById(id) {
  const normalized = normalizeTierId(id);
  return MEMBERSHIP_TIER_OPTIONS.find((t) => t.id === normalized) || MEMBERSHIP_TIER_OPTIONS[0];
}

export function getTierDisplayLabel(id, fallback = 'Free') {
  if (!id || typeof id !== 'string') {
    return fallback;
  }

  if (id === 'Lifetime') {
    return 'Advocate';
  }

  const exactMatch = MEMBERSHIP_TIER_OPTIONS.find((tier) => tier.id === id);
  return exactMatch ? exactMatch.label : id;
}

export function getPmproLevelIdForTier(id) {
  return getTierById(id).pmproLevelId;
}

export function isOneTimeMembershipTierId(id) {
  return false;
}
