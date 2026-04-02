const SUBMITTABLE_URL = 'https://theamericanalpineclub.submittable.com/submit';

export const grantOpportunities = [
  {
    slug: 'climbing-grief-grant',
    name: 'Climbing Grief Grant',
    category: 'Wellbeing',
    award: 'Up to $600',
    fit: 'Therapeutic support for members directly impacted by climbing, alpinism, or ski mountaineering grief and trauma.',
    summary:
      'Support for therapy or professional programs that help members work through grief, loss, or trauma related to mountain sports.',
    highlights: [
      'Focused on grief, loss, and trauma recovery',
      'Designed for U.S. applicants with demonstrated need',
      'Best for applicants with a clear care plan and provider',
    ],
    sourceUrl: SUBMITTABLE_URL,
  },
  {
    slug: 'catalyst-adventure-grants-for-change',
    name: 'CATALYST: Adventure Grants for Change',
    category: 'Access',
    award: 'AAC grant support',
    fit: 'Applicants or teams facing barriers to climbing access who are advancing a specific, attainable U.S. objective.',
    summary:
      'A grant aimed at expanding access to climbing by supporting underrepresented communities and closing opportunity gaps across climbing disciplines.',
    highlights: [
      'AAC members only',
      'Supports individuals or teams of 2 to 4',
      'Objective must be in the United States',
    ],
    sourceUrl: SUBMITTABLE_URL,
  },
  {
    slug: 'momentum-grant',
    name: 'Momentum Grant',
    category: 'Alpine Progression',
    award: 'AAC grant support',
    fit: 'Intermediate to advanced alpine climbers or ski-alpinists pursuing a meaningful step up in North America.',
    summary:
      'Created to back climbers who are growing their mountain craft through ambitious alpine objectives, new lines, or significant repeats.',
    highlights: [
      'North America projects only',
      'Strong fit for ice, mixed, rock, and ski-alpinist objectives',
      'Best for applicants showing a clear progression in skill and ambition',
    ],
    sourceUrl: SUBMITTABLE_URL,
  },
  {
    slug: 'live-your-dream-2026',
    name: 'Live Your Dream',
    category: 'Exploration',
    award: 'AAC grant support',
    fit: 'Climbers with personally ambitious goals who want to grow their abilities and share exploration with their communities.',
    summary:
      'A broad-based grant for climbers across ages, experience levels, and disciplines who are pursuing meaningful next-step adventures.',
    highlights: [
      'Open across climbing disciplines',
      'Encourages ambitious but personally relevant goals',
      'Community impact and storytelling matter',
    ],
    sourceUrl: SUBMITTABLE_URL,
  },
  {
    slug: 'research-grants',
    name: 'Research Grants',
    category: 'Science & Stewardship',
    award: 'AAC research funding',
    fit: 'Researchers studying climbing landscapes, ecosystems, land management, or community health connected to climbing.',
    summary:
      'Supports scientific work that improves understanding of climbing environments and helps protect the landscapes and communities climbers depend on.',
    highlights: [
      'Strong fit for climbing-landscape research',
      'Projects should address timely issues affecting climbers or crags',
      'Useful for academic and field-based work',
    ],
    sourceUrl: SUBMITTABLE_URL,
  },
];

const normalizedOpportunityMap = new Map(
  grantOpportunities.map((opportunity) => [opportunity.slug, opportunity]),
);

export const grantStatuses = ['Pending review', 'Approved', 'Rejected'];

export const getGrantOpportunityBySlug = (slug) =>
  normalizedOpportunityMap.get(slug) || grantOpportunities[0];

export const normalizeGrantStatus = (status) => {
  if (status === 'Approved') return 'Approved';
  if (status === 'Rejected') return 'Rejected';
  return 'Pending review';
};

export const normalizeGrantApplications = (applications = []) => {
  if (!Array.isArray(applications)) {
    return [];
  }

  return applications
    .map((application) => {
      if (!application || typeof application !== 'object') {
        return null;
      }

      const opportunity = getGrantOpportunityBySlug(application.grant_slug);
      const applicationDate = application.application_date || new Date().toISOString();

      return {
        id: application.id || `grant_${Math.random().toString(36).slice(2, 10)}`,
        grant_slug: opportunity.slug,
        grant_name: application.grant_name || opportunity.name,
        category: application.category || opportunity.category,
        application_date: applicationDate,
        status: normalizeGrantStatus(application.status),
        project_title: application.project_title || '',
        requested_amount: application.requested_amount || '',
        objective_location: application.objective_location || '',
        discipline: application.discipline || '',
        team_name: application.team_name || '',
        summary: application.summary || '',
      };
    })
    .filter(Boolean)
    .sort((a, b) => new Date(b.application_date) - new Date(a.application_date));
};

export const formatGrantApplicationDate = (value) => {
  if (!value) {
    return 'Not submitted';
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return 'Not submitted';
  }

  return date.toLocaleDateString();
};

export const buildGrantApplicationRecord = ({ opportunity, form }) => ({
  id: `grant_${Math.random().toString(36).slice(2, 10)}`,
  grant_slug: opportunity.slug,
  grant_name: opportunity.name,
  category: opportunity.category,
  application_date: new Date().toISOString(),
  status: 'Pending review',
  project_title: form.projectTitle.trim(),
  requested_amount: form.requestedAmount.trim(),
  objective_location: form.objectiveLocation.trim(),
  discipline: form.discipline.trim(),
  team_name: form.teamName.trim(),
  summary: form.summary.trim(),
});

export const grantStatusClassName = (status) => {
  if (status === 'Approved') {
    return 'bg-emerald-50 text-emerald-800 border border-emerald-200';
  }

  if (status === 'Rejected') {
    return 'bg-red-50 text-red-700 border border-red-200';
  }

  return 'bg-amber-50 text-amber-900 border border-amber-200';
};

export const grantPortalSourceUrl = SUBMITTABLE_URL;
