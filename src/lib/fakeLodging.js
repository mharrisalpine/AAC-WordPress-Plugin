const LODGING_RESERVATIONS_KEY = 'aac_fake_lodging_reservations_v1';

export const AAC_LODGING_SOURCE_URL = 'https://americanalpineclub.org/lodging';

export const fakeLodgingSites = [
  {
    id: 'grand-teton-climbers-ranch',
    name: "Grand Teton Climbers' Ranch",
    shortLabel: 'Grand Teton',
    location: 'Grand Teton National Park, Wyoming',
    type: 'Cabins and shared ranch lodging',
    nightlyRate: 48,
    extraGuestNightlyRate: 12,
    maxPartySize: 6,
    season: 'Peak summer alpine season',
    summary:
      'A high-country basecamp feel near the Tetons for members planning long rock routes, alpine starts, and community evenings back at the ranch.',
    highlights: ['Shared member kitchen', 'Easy trailhead access', 'Classic AAC communal atmosphere'],
    detailsUrl: 'https://americanalpineclub.org/grand-teton-climbers-ranch',
  },
  {
    id: 'hueco-rock-ranch',
    name: 'Hueco Rock Ranch',
    shortLabel: 'Hueco',
    location: 'Hueco Tanks, Texas',
    type: 'Casitas and desert bunk lodging',
    nightlyRate: 42,
    extraGuestNightlyRate: 10,
    maxPartySize: 5,
    season: 'Cool-season bouldering trips',
    summary:
      'A warm desert staging point for bouldering missions, partner meetups, and quick access to the Hueco climbing season.',
    highlights: ['Desert basecamp vibe', 'Small group rooms', 'Trip-planning support notes'],
    detailsUrl: 'https://americanalpineclub.org/hueco-rock-ranch',
  },
  {
    id: 'new-river-gorge-campground',
    name: 'New River Gorge Campground',
    shortLabel: 'New River Gorge',
    location: 'New River Gorge, West Virginia',
    type: 'Campground and platform camping',
    nightlyRate: 28,
    extraGuestNightlyRate: 8,
    maxPartySize: 6,
    season: 'Spring and fall sport climbing',
    summary:
      'A member-friendly campground concept for long weekends built around the New River Gorge cliffs, evening fires, and shared climber logistics.',
    highlights: ['Simple shared camping setup', 'Road-trip friendly rates', 'Group gathering space'],
    detailsUrl: 'https://americanalpineclub.org/new-river-gorge-campground',
  },
  {
    id: 'gunks-campground',
    name: 'Gunks Campground',
    shortLabel: 'The Gunks',
    location: 'New Paltz, New York',
    type: 'Campground and tent sites',
    nightlyRate: 34,
    extraGuestNightlyRate: 8,
    maxPartySize: 5,
    season: 'Northeast trad weekends',
    summary:
      'A relaxed tent-and-van style member stay for cliff days in the Gunks, with quick access to routes and a mellow community camp setting.',
    highlights: ['Weekend-friendly camping', 'Trad climbing access', 'Community camp layout'],
    detailsUrl: 'https://americanalpineclub.org/gunks-campground',
  },
  {
    id: 'rumney-rattlesnake-campground',
    name: 'Rumney Rattlesnake Campground',
    shortLabel: 'Rumney',
    location: 'Rumney, New Hampshire',
    type: 'Campground and climber tent sites',
    nightlyRate: 30,
    extraGuestNightlyRate: 8,
    maxPartySize: 5,
    season: 'Summer and shoulder-season sport climbing',
    summary:
      'A simple AAC-style reservation experience built around Rumney climbing trips, with room for partners, families, and crag-side regrouping.',
    highlights: ['Flexible camp setup', 'Close to Rumney walls', 'Easy partner trip planning'],
    detailsUrl: 'https://americanalpineclub.org/rumney-rattlesnake-campground',
  },
  {
    id: 'snowbird-hut',
    name: 'Snowbird Hut',
    shortLabel: 'Snowbird Hut',
    location: 'Little Cottonwood Canyon, Utah',
    type: 'Mountain hut stay',
    nightlyRate: 84,
    extraGuestNightlyRate: 18,
    maxPartySize: 8,
    season: 'Winter hut and shoulder-season alpine stays',
    summary:
      'A mountain-hut style stay imagined for members planning ski mountaineering, alpine climbing, or a quiet canyon reset with a team.',
    highlights: ['Shared hut style lodging', 'Mountain trip staging', 'Higher-capacity team reservations'],
    detailsUrl: 'https://americanalpineclub.org/snowbird-hut',
  },
];

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

const getAllReservations = () => readJson(LODGING_RESERVATIONS_KEY, []);

export const getFakeLodgingSites = () => fakeLodgingSites;

export const getLodgingSiteById = (siteId) =>
  fakeLodgingSites.find((site) => site.id === siteId) || fakeLodgingSites[0];

export const calculateStayNights = (checkIn, checkOut) => {
  if (!checkIn || !checkOut) {
    return 0;
  }

  const start = new Date(`${checkIn}T12:00:00`);
  const end = new Date(`${checkOut}T12:00:00`);
  const diff = end.getTime() - start.getTime();

  if (!Number.isFinite(diff) || diff <= 0) {
    return 0;
  }

  return Math.round(diff / (24 * 60 * 60 * 1000));
};

export const calculateLodgingEstimate = (site, checkIn, checkOut, partySize = 1) => {
  const safePartySize = Math.max(1, Number(partySize) || 1);
  const nights = calculateStayNights(checkIn, checkOut);
  const baseRate = Number(site?.nightlyRate || 0);
  const extraGuestNightlyRate = Number(site?.extraGuestNightlyRate || 0);
  const extraGuests = Math.max(0, safePartySize - 1);
  const baseAmount = nights * baseRate;
  const extraGuestAmount = nights * extraGuests * extraGuestNightlyRate;
  const total = baseAmount + extraGuestAmount;

  return {
    nights,
    baseAmount,
    extraGuestAmount,
    total,
  };
};

export const getMemberLodgingReservations = (memberId) => {
  if (!memberId) {
    return [];
  }

  return getAllReservations()
    .filter((reservation) => reservation.memberId === memberId)
    .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
};

export const registerFakeLodgingReservation = ({ memberId, reservation }) => {
  if (!memberId || !reservation?.referenceId) {
    return null;
  }

  const reservations = getAllReservations();
  const existingIndex = reservations.findIndex(
    (entry) => entry.memberId === memberId && entry.referenceId === reservation.referenceId,
  );

  const nextReservation = {
    id: reservation.id || `lodging_${Math.random().toString(36).slice(2, 10)}`,
    memberId,
    ...reservation,
    createdAt: reservation.createdAt || new Date().toISOString(),
  };

  if (existingIndex >= 0) {
    reservations[existingIndex] = nextReservation;
  } else {
    reservations.unshift(nextReservation);
  }

  writeJson(LODGING_RESERVATIONS_KEY, reservations);
  return nextReservation;
};

export const formatLodgingDate = (value) => {
  if (!value) {
    return 'Select date';
  }

  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(`${value}T12:00:00`));
};

export const formatLodgingDateRange = (checkIn, checkOut) => {
  if (!checkIn || !checkOut) {
    return 'Choose your stay window';
  }

  return `${formatLodgingDate(checkIn)} - ${formatLodgingDate(checkOut)}`;
};
