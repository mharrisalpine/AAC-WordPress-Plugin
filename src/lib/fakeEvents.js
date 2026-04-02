const EVENT_REGISTRATIONS_KEY = 'aac_fake_event_registrations_v1';

const fakeEvents = [
  {
    id: 'ouray-ice-clinic',
    title: 'Ouray Ice Movement Clinic',
    date: '2026-12-11',
    time: '8:00 AM - 3:00 PM',
    location: 'Ouray Ice Park, Ouray, CO',
    level: 'Intermediate',
    price_amount: 25,
    price: '$25 member registration',
    summary: 'Dial in efficient crampon movement, anchors, and mock leads on steep blue ice with AAC guides.',
  },
  {
    id: 'elden-sunset-social',
    title: 'Sunset Cragging Social at Mount Elden',
    date: '2026-06-18',
    time: '5:30 PM - 9:00 PM',
    location: 'Mount Elden, Flagstaff, AZ',
    level: 'All levels',
    price_amount: 0,
    price: 'Free',
    summary: 'Meet local members for an evening of moderated climbing, partner matching, and post-session tacos.',
  },
  {
    id: 'jtree-anchor-lab',
    title: 'Joshua Tree Anchor Lab',
    date: '2026-10-03',
    time: '9:00 AM - 1:00 PM',
    location: 'Hidden Valley, Joshua Tree, CA',
    level: 'Beginner to Intermediate',
    price_amount: 15,
    price: '$15 member registration',
    summary: 'Practice natural-anchor systems, rope management, and route transitions in a low-pressure workshop format.',
  },
  {
    id: 'bishop-bouldering-weekend',
    title: 'Bishop Bouldering Weekend',
    date: '2026-11-07',
    time: '7:00 AM - 6:00 PM',
    location: 'Buttermilks, Bishop, CA',
    level: 'All levels',
    price_amount: 30,
    price: '$30 member registration',
    summary: 'A full day of circuit groups, movement coaching, and community hangs at the Buttermilks and Happy Boulders.',
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

const getAllRegistrations = () => readJson(EVENT_REGISTRATIONS_KEY, []);

export const getFakeEvents = () => fakeEvents;

export const getMemberEventRegistrations = (memberId) => {
  if (!memberId) return [];
  return getAllRegistrations().filter((registration) => registration.memberId === memberId);
};

export const registerForFakeEvent = ({ memberId, eventId, registration }) => {
  const registrations = getAllRegistrations();
  const existingIndex = registrations.findIndex(
    (entry) => entry.memberId === memberId && entry.eventId === eventId
  );

  const nextEntry = {
    memberId,
    eventId,
    ...registration,
    registeredAt: registration.registeredAt || new Date().toISOString(),
  };

  if (existingIndex >= 0) {
    registrations[existingIndex] = nextEntry;
  } else {
    registrations.unshift(nextEntry);
  }

  writeJson(EVENT_REGISTRATIONS_KEY, registrations);
  return nextEntry;
};
