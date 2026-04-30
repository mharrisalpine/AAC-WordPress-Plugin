import { getPortalUiSettings } from '@/lib/portalSettings';

const SUBMITTABLE_URL = 'https://theamericanalpineclub.submittable.com/submit';

const normalizeOpportunity = (opportunity = {}) => ({
  slug: String(opportunity.slug || '').trim(),
  name: String(opportunity.name || '').trim(),
  category: String(opportunity.category || '').trim(),
  award: String(opportunity.award || '').trim(),
  fit: String(opportunity.fit || '').trim(),
  summary: String(opportunity.summary || '').trim(),
  highlights: Array.isArray(opportunity.highlights)
    ? opportunity.highlights.map((item) => String(item || '').trim()).filter(Boolean)
    : String(opportunity.highlights || '')
        .split(/\r\n|\r|\n/)
        .map((item) => item.trim())
        .filter(Boolean),
  sourceUrl: String(opportunity.sourceUrl || opportunity.source_url || SUBMITTABLE_URL).trim() || SUBMITTABLE_URL,
});

const normalizeFieldDefinition = (field = {}) => ({
  field_key: String(field.field_key || field.fieldKey || '').trim(),
  label: String(field.label || '').trim(),
  type: ['text', 'email', 'number', 'textarea', 'select'].includes(String(field.type || '').trim())
    ? String(field.type || '').trim()
    : 'text',
  required: Boolean(field.required),
  placeholder: String(field.placeholder || '').trim(),
  help_text: String(field.help_text || field.helpText || '').trim(),
  options: Array.isArray(field.options)
    ? field.options.map((option) => String(option || '').trim()).filter(Boolean)
    : String(field.options || '')
        .split(/\r\n|\r|\n/)
        .map((option) => option.trim())
        .filter(Boolean),
});

export const getGrantOpportunities = () => {
  const portalContent = getPortalUiSettings().content || {};
  const opportunities = Array.isArray(portalContent.grantOpportunities) ? portalContent.grantOpportunities : [];
  const normalized = opportunities.map(normalizeOpportunity).filter((item) => item.slug && item.name);
  return normalized.length ? normalized : [];
};

export const getGrantFormFields = () => {
  const portalContent = getPortalUiSettings().content || {};
  const fields = Array.isArray(portalContent.grantFormFields) ? portalContent.grantFormFields : [];
  const normalized = fields.map(normalizeFieldDefinition).filter((item) => item.field_key && item.label);
  return normalized.length ? normalized : [];
};

export const getGrantOpportunityBySlug = (slug, opportunities = getGrantOpportunities()) =>
  opportunities.find((opportunity) => opportunity.slug === slug) || opportunities[0] || {
    slug: '',
    name: '',
    category: '',
    award: '',
    fit: '',
    summary: '',
    highlights: [],
    sourceUrl: SUBMITTABLE_URL,
  };

export const grantStatuses = [
  'Submitted',
  'Pending review',
  'Eligibility Review',
  'Committee Review',
  'Needs Revision',
  'Approved',
  'Rejected',
];

export const normalizeGrantStatus = (status) => {
  const normalized = String(status || '').trim().toLowerCase();

  if (normalized === 'submitted') return 'Submitted';
  if (normalized === 'eligibility review' || normalized === 'eligibility_review') return 'Eligibility Review';
  if (normalized === 'committee review' || normalized === 'committee_review') return 'Committee Review';
  if (normalized === 'needs revision' || normalized === 'needs_revision') return 'Needs Revision';
  if (status === 'Approved') return 'Approved';
  if (status === 'Rejected') return 'Rejected';
  return 'Pending review';
};

export const buildGrantFormState = (fieldDefinitions = getGrantFormFields()) =>
  fieldDefinitions.reduce((accumulator, field) => {
    accumulator[field.field_key] = '';
    return accumulator;
  }, {});

export const normalizeGrantApplications = (applications = []) => {
  if (!Array.isArray(applications)) {
    return [];
  }

  const opportunities = getGrantOpportunities();

  return applications
    .map((application) => {
      if (!application || typeof application !== 'object') {
        return null;
      }

      const opportunity = getGrantOpportunityBySlug(application.grant_slug, opportunities);
      const applicationDate = application.application_date || new Date().toISOString();

      return {
        id: application.id || `grant_${Math.random().toString(36).slice(2, 10)}`,
        review_application_id: application.review_application_id || null,
        grant_slug: application.grant_slug || opportunity.slug,
        grant_name: application.grant_name || opportunity.name,
        category: application.category || opportunity.category,
        application_date: applicationDate,
        status: normalizeGrantStatus(application.status),
        status_key: application.status_key || '',
        project_title: application.project_title || '',
        requested_amount: application.requested_amount || '',
        objective_location: application.objective_location || '',
        discipline: application.discipline || '',
        team_name: application.team_name || '',
        summary: application.summary || '',
        fields: Array.isArray(application.fields) ? application.fields : [],
        last_note: application.last_note || '',
        reviewed_at: application.reviewed_at || '',
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

export const grantStatusClassName = (status) => {
  if (status === 'Approved') {
    return 'bg-emerald-50 text-emerald-800 border border-emerald-200';
  }

  if (status === 'Rejected') {
    return 'bg-red-50 text-red-700 border border-red-200';
  }

  if (status === 'Needs Revision') {
    return 'bg-orange-50 text-orange-800 border border-orange-200';
  }

  if (status === 'Eligibility Review' || status === 'Committee Review') {
    return 'bg-sky-50 text-sky-800 border border-sky-200';
  }

  return 'bg-amber-50 text-amber-900 border border-amber-200';
};

export const grantPortalSourceUrl =
  getGrantOpportunities().find((opportunity) => opportunity.sourceUrl)?.sourceUrl || SUBMITTABLE_URL;
