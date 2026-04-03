import { normalizeTierId } from '@/lib/membershipTiers';

export const MEMBERSHIP_PLAN_PRICES = {
  Free: 0,
  Supporter: 45,
  Partner: 95,
  'Partner Family': 95,
  Leader: 150,
  Advocate: 500,
  GRF: 0,
};

export const MEMBERSHIP_PLAN_ORDER = ['Free', 'Supporter', 'Partner', 'Partner Family', 'Leader', 'Advocate', 'GRF'];

export const MEMBERSHIP_PLAN_DETAILS = {
  Free: {
    summary: 'A portal preview tier for prospective members who want an account before choosing a paid plan.',
    bullets: [
      'Create an AAC portal account',
      'Preview your profile and account settings',
      'Receive AAC promotional emails and promo codes',
      'No partner discounts or rescue benefits',
      'No access to the member store',
    ],
  },
  Supporter: {
    summary: 'A great entry point for AAC members who want community access and core publications.',
    bullets: [
      'Digital member communications and club updates',
      'Access to partner discounts and store offers',
      'AAC community events and member network access',
      'AAC email newsletter and climbing news',
      'Support for conservation and access advocacy',
    ],
  },
  Partner: {
    summary: 'Adds essential rescue support and broader benefits for active climbers.',
    bullets: [
      '$50,000 in rescue coverage',
      '$5,000 in medical coverage',
      'Everything included in Supporter',
      'Rescue benefit documentation and support',
      'Eligible for AAC grants and awards (where applicable)',
    ],
  },
  'Partner Family': {
    summary: 'Partner-level benefits configured for households with connected family members.',
    bullets: [
      '$50,000 in rescue coverage',
      '$5,000 in medical coverage',
      'Partner-level benefits with family add-ons',
      'Supports an additional adult and up to three dependents',
      'Child-account invite codes generated for connected family members',
    ],
  },
  Leader: {
    summary: 'Built for members who want stronger protection and premium club benefits.',
    bullets: [
      '$100,000 in rescue coverage',
      '$10,000 in medical coverage',
      'Everything included in Partner',
      'Higher published benefit ceilings',
      'Priority consideration for select AAC programs',
    ],
  },
  Advocate: {
    summary: 'Built for members who want to contribute at the highest annual level.',
    bullets: [
      'Annual Advocate membership',
      '$100,000 in rescue coverage',
      '$10,000 in medical coverage',
      'Everything included in Leader',
      'Expanded support for the AAC mission',
      'Advocate-level member recognition',
    ],
  },
  GRF: {
    summary: 'A manual donor tier for members who have contributed $1,500 within a single year.',
    bullets: [
      'Manual donor recognition tier',
      '$100,000 in rescue coverage',
      '$10,000 in medical coverage',
      'Everything included in Advocate',
      'Assigned by AAC staff only',
    ],
  },
};

export const getNextMembershipTier = (currentTier) => {
  const c = normalizeTierId(currentTier);
  switch (c) {
    case 'Free':
      return 'Supporter';
    case 'Supporter':
      return 'Partner';
    case 'Partner':
    case 'Partner Family':
      return 'Leader';
    case 'Leader':
      return 'Advocate';
    default:
      return 'Partner';
  }
};

export const getMembershipBenefits = (tier) => {
  const t = normalizeTierId(tier);
  const matrix = {
    Free: { rescue_amount: 0, medical_amount: 0 },
    Supporter: { rescue_amount: 0, medical_amount: 0 },
    Partner: { rescue_amount: 50000, medical_amount: 5000 },
    'Partner Family': { rescue_amount: 50000, medical_amount: 5000 },
    Leader: { rescue_amount: 100000, medical_amount: 10000 },
    Advocate: { rescue_amount: 100000, medical_amount: 10000 },
    GRF: { rescue_amount: 100000, medical_amount: 10000 },
  };

  return matrix[t] || matrix.Supporter;
};

export const createDonationPaymentIntent = ({ amount, fund, tributeType, tributeName, tributeMessage }) => ({
  kind: 'donation',
  title: 'Complete Donation',
  submitLabel: 'Donate Now',
  amount,
  currency: 'USD',
  successHeadline: 'Donation successful',
  successMessage:
    tributeType && tributeName
      ? `Thank you for supporting the American Alpine Club ${tributeType === 'honor' ? `in honor of ${tributeName}` : `in memory of ${tributeName}`}.`
      : 'Thank you for supporting the American Alpine Club.',
  successPath: '/donate',
  metadata: {
    category: 'donation',
    fund: fund || 'General AAC Fund',
    tributeType: tributeType || '',
    tributeName: tributeName || '',
    tributeMessage: tributeMessage || '',
  },
});

const sumCartItemsUsd = (cartItems) =>
  (cartItems || []).reduce((total, item) => {
    const priceInCents = item.variant.sale_price_in_cents ?? item.variant.price_in_cents ?? 0;
    return total + priceInCents * item.quantity;
  }, 0) / 100;

const mapCartItemsToMetadata = (cartItems) =>
  (cartItems || []).map((item) => ({
    product_id: item.product?.id ?? item.variant?.id,
    title: item.product?.title,
    variant_title: item.variant?.title,
    quantity: item.quantity,
    portal_line: Boolean(item.isPortalLine),
  }));

export const createMerchandisePaymentIntent = ({ cartItems, accountInfo }) => {
  const amount = sumCartItemsUsd(cartItems);

  return {
    kind: 'merchandise',
    type: 'purchase',
    title: 'Complete Merchandise Order',
    submitLabel: 'Place Order',
    amount,
    currency: 'USD',
    successHeadline: 'Order successful',
    successMessage: 'Your AAC merchandise order has been placed successfully.',
    successPath: '/store',
    referenceId: `merch_${Date.now()}`,
    metadata: {
      category: 'merchandise',
      items: mapCartItemsToMetadata(cartItems),
      shipping_name: accountInfo?.name || '',
    },
  };
};

/** Membership signup / renewal cart only — separate from Shopify merchandise cart. */
export const createMembershipSignupPaymentIntent = ({ cartItems, accountInfo, targetTier }) => {
  const amount = sumCartItemsUsd(cartItems);

  return {
    kind: 'membership_cart',
    type: 'purchase',
    title: 'Complete membership checkout',
    submitLabel: 'Pay now',
    amount,
    currency: 'USD',
    successHeadline: 'Membership payment successful',
    successMessage: 'Thank you. Your membership dues and any donation were processed.',
    successPath: '/',
    referenceId: `membership_cart_${Date.now()}`,
    metadata: {
      category: 'membership_cart',
      items: mapCartItemsToMetadata(cartItems),
      shipping_name: accountInfo?.name || '',
      targetTier: targetTier || null,
    },
  };
};

export const createEventPaymentIntent = ({ event, registration }) => ({
  kind: 'event',
  type: 'registration',
  title: `Register for ${event.title}`,
  submitLabel: 'Pay Registration',
  amount: event.price_amount || 0,
  currency: 'USD',
  successHeadline: 'Event registration successful',
  successMessage: `Your registration for ${event.title} has been confirmed.`,
  successPath: '/meetups',
  referenceId: `event_${event.id}_${Date.now()}`,
  metadata: {
    category: 'event',
    eventId: event.id,
    eventTitle: event.title,
    registration,
  },
});

export const createLodgingPaymentIntent = ({ site, registration, stay }) => ({
  kind: 'lodging',
  type: 'reservation',
  title: `Reserve ${site.name}`,
  description: `Complete your placeholder reservation checkout for ${site.name}.`,
  submitLabel: 'Confirm Reservation',
  amount: stay.total || 0,
  currency: 'USD',
  successHeadline: 'Lodging reservation successful',
  successMessage: `Your placeholder reservation for ${site.name} has been confirmed.`,
  successPath: '/lodging',
  referenceId: `lodging_${site.id}_${Date.now()}`,
  metadata: {
    category: 'lodging',
    siteId: site.id,
    siteName: site.name,
    registration,
    stay,
  },
});

export const createMembershipPaymentIntent = ({ type, currentTier, targetTier }) => {
  const normalizedCurrent = normalizeTierId(currentTier);
  const resolvedTargetTier = normalizeTierId(
    targetTier ||
      (type === 'upgrade' ? getNextMembershipTier(normalizedCurrent) : normalizedCurrent || 'Partner'),
  );
  const amount = MEMBERSHIP_PLAN_PRICES[resolvedTargetTier] ?? MEMBERSHIP_PLAN_PRICES.Partner;
  const labels = {
    join: 'Join Membership',
    renew: `Renew ${resolvedTargetTier} Membership`,
    upgrade: `Upgrade to ${resolvedTargetTier}`,
    downgrade: `Downgrade to ${resolvedTargetTier}`,
    manage_payment: 'Update Card on File',
  };

  const descriptions = {
    join: amount === 0
      ? `Start a new ${resolvedTargetTier} AAC portal membership.`
      : `Start a new ${resolvedTargetTier} membership with fake card checkout.`,
    renew: amount === 0
      ? `Renew your ${resolvedTargetTier} AAC portal membership.`
      : `Renew your ${resolvedTargetTier} membership with fake card checkout.`,
    upgrade: `Upgrade your membership benefits to the ${resolvedTargetTier} tier.`,
    downgrade: `Move your membership to the ${resolvedTargetTier} tier for the next term.`,
    manage_payment: 'Save a fake credit card for future membership charges.',
  };

  const successHeadlines = {
    join: 'Membership payment successful',
    renew: 'Membership payment successful',
    upgrade: 'Membership updated',
    downgrade: 'Membership updated',
    manage_payment: 'Payment method updated',
  };

  const successMessages = {
    join: `Your ${resolvedTargetTier} membership changes were applied successfully.`,
    renew: `Your ${resolvedTargetTier} membership changes were applied successfully.`,
    upgrade: `You are now enrolled in the ${resolvedTargetTier} membership tier.`,
    downgrade: `Your membership was changed to the ${resolvedTargetTier} tier.`,
    manage_payment: 'Your fake card has been saved to the account.',
  };

  const successPaths = {
    join: '/',
    renew: '/',
    upgrade: '/membership',
    downgrade: '/membership',
    manage_payment: '/account',
  };

  return {
    kind: 'membership',
    type,
    title: labels[type] || 'Membership Payment',
    description: descriptions[type] || 'Complete your membership payment.',
    submitLabel: type === 'manage_payment'
      ? 'Save Card'
      : amount === 0
        ? 'Confirm Membership'
        : type === 'downgrade'
          ? 'Confirm Change'
          : 'Pay Now',
    amount: type === 'manage_payment' ? 0 : amount,
    currency: 'USD',
    successHeadline: successHeadlines[type] || 'Membership payment successful',
    successMessage: successMessages[type] || `Your ${resolvedTargetTier} membership changes were applied successfully.`,
    successPath: successPaths[type] || '/',
    metadata: {
      category: 'membership',
      membershipAction: type,
      currentTier: currentTier || null,
      targetTier: resolvedTargetTier,
    },
  };
};

export const formatDollars = (amount) =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(amount || 0);
