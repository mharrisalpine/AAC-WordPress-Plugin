import { getAppRuntimeConfig } from '@/lib/backendConfig';

const trimTrailingSlash = (value) => String(value || '').replace(/\/$/, '');

/**
 * Base URL for the public AAC website (WordPress / Squarespace). When empty, links use same-origin paths.
 * Set via window.AAC_MEMBER_PORTAL_CONFIG.mainWebsiteBaseUrl or VITE_MAIN_WEBSITE_BASE.
 */
export const getMainWebsiteBase = () => {
  const runtime = getAppRuntimeConfig().mainWebsiteBaseUrl;
  if (runtime) return trimTrailingSlash(runtime);
  const env = import.meta.env.VITE_MAIN_WEBSITE_BASE;
  if (env) return trimTrailingSlash(env);
  return '';
};

/** Build an href for a path on the main AAC website (e.g. /membership). */
export const mainSiteHref = (path) => {
  const normalized = path.startsWith('/') ? path : `/${path}`;
  const base = getMainWebsiteBase();
  return base ? `${base}${normalized}` : normalized;
};

/**
 * Mirrors the current WordPress staging navigation at
 * https://americanalpine.wpenginepowered.com/ with portal-aware utility links layered in by the header.
 * @typedef {{ label: string, path?: string, href?: string, external?: boolean }} NavChild
 * @typedef {{ type: 'folder', label: string, path: string, children: NavChild[] } | { type: 'link', label: string, href: string, external?: boolean }} NavSection
 */
export const AAC_MAIN_NAV = [
  {
    type: 'folder',
    label: 'Get Involved',
    path: '/get-involved',
    children: [
      { label: 'Volunteer', path: '/volunteer' },
      { label: 'Donate', href: 'https://membership.americanalpineclub.org/donate', external: true },
      { label: 'Sign Up', href: 'https://membership.americanalpineclub.org/join', external: true },
    ],
  },
  {
    type: 'folder',
    label: 'Membership',
    path: '/membership',
    children: [
      { label: 'Benefits', path: '/benefits' },
      { label: 'Join', href: '/#membership-form' },
      { label: 'Renew', href: 'https://membership.americanalpineclub.org/renew', external: true },
    ],
  },
  {
    type: 'folder',
    label: 'Stories & News',
    path: '/stories',
    children: [
      { label: 'Articles & News', path: '/stories' },
      { label: 'The Prescription', path: '/prescription' },
      { label: 'The Line', path: '/line-archive' },
    ],
  },
  {
    type: 'folder',
    label: 'Lodging',
    path: '/lodging',
    children: [
      { label: 'Grand Teton', path: '/grand-teton-climbers-ranch' },
      { label: 'The Gunks', path: '/gunks-campground' },
      { label: 'Hueco Tanks', path: '/hueco-rock-ranch' },
      { label: 'New River Gorge', path: '/new-river-gorge-campground' },
    ],
  },
  {
    type: 'folder',
    label: 'Publications',
    path: '/publications',
    children: [
      { label: 'AAJ', path: '/publications/aaj' },
      { label: 'Accidents', path: '/publications/accidents' },
      { label: 'Podcasts', path: '/the-american-alpine-club-podcast' },
    ],
  },
  {
    type: 'folder',
    label: 'Our Work',
    path: '/our-work',
    children: [
      { label: "Gov't Affairs", path: '/advocacy' },
      { label: 'Grants', path: '/grants' },
      { label: 'Grief Fund', path: '/grieffund' },
      { label: 'Library', path: '/library' },
      { label: 'Chapters', path: '/chapters' },
    ],
  },
];

export function resolveNavChildHref(child) {
  if (child.href) {
    return child.href;
  }
  return mainSiteHref(child.path || '/');
}

/** @deprecated Use AAC_MAIN_NAV + MainSiteNavigation; kept for any external imports. */
export const MAIN_SITE_TOP_NAV = [
  { label: 'Membership', path: '/membership' },
  { label: 'Our Work', path: '/our-work' },
  { label: 'Lodging', path: '/lodging-2' },
  { label: 'Stories', path: '/stories' },
  { label: 'Shop', path: '/shop' },
];
