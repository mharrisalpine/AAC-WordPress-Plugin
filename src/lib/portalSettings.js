import { getAppRuntimeConfig } from '@/lib/backendConfig';

const DEFAULT_SETTINGS = {
  content: {
    account_settings_title: 'Account Settings',
    profile_information_description:
      'Primary contact and profile information used across the AAC portal. You may update your details and preferences in Account Settings.',
    membership_snapshot_description:
      'Live membership and benefit details coming from WordPress and Paid Memberships Pro.',
    member_details_description:
      'Members receive a free T-shirt and books with the purchase of their membership.',
    portal_preferences_title: 'Portal Preferences',
    portal_preferences_description:
      'Settings the portal is currently storing for your member record.',
    quick_actions_title: 'Quick Actions',
    quick_actions_description:
      'Jump straight into the next member task.',
    grant_applications_description:
      'Recent AAC grant submissions tied to your member record.',
  },
  design: {
    sidebarBackgroundUrl: '/sidebar-topo-v2.svg',
    sidebarOverlayStart: '0.18',
    sidebarOverlayEnd: '0.30',
    sidebarButtonBackground: '#000000',
    sidebarButtonHoverBackground: '#111111',
    sidebarButtonActiveBackground: '#000000',
    sidebarAccentColor: '#f8c235',
  },
  navigation: {
    topNavSections: [
      {
        id: 'get_involved',
        label: 'Get Involved',
        href: '/get-involved',
        children: [
          { label: 'Volunteer', href: '/volunteer' },
          { label: 'Donate', href: 'https://membership.americanalpineclub.org/donate', external: true },
          { label: 'Sign Up', href: 'https://membership.americanalpineclub.org/join', external: true },
        ],
      },
      {
        id: 'membership',
        label: 'Membership',
        href: '/membership',
        children: [
          { label: 'Benefits', href: '/benefits' },
          { label: 'Join', href: '/#membership-form' },
          { label: 'Renew', href: 'https://membership.americanalpineclub.org/renew', external: true },
        ],
      },
      {
        id: 'stories_news',
        label: 'Stories & News',
        href: '/stories',
        children: [
          { label: 'Articles & News', href: '/stories' },
          { label: 'The Prescription', href: '/prescription' },
          { label: 'The Line', href: '/line-archive' },
        ],
      },
      {
        id: 'lodging',
        label: 'Lodging',
        href: '/lodging',
        children: [
          { label: 'Grand Teton', href: '/grand-teton-climbers-ranch' },
          { label: 'The Gunks', href: '/gunks-campground' },
          { label: 'Hueco Tanks', href: '/hueco-rock-ranch' },
          { label: 'New River Gorge', href: '/new-river-gorge-campground' },
        ],
      },
      {
        id: 'publications',
        label: 'Publications',
        href: '/publications',
        children: [
          { label: 'AAJ', href: '/publications/aaj' },
          { label: 'Accidents', href: '/publications/accidents' },
          { label: 'Podcasts', href: '/the-american-alpine-club-podcast' },
        ],
      },
      {
        id: 'our_work',
        label: 'Our Work',
        href: '/our-work',
        children: [
          { label: "Gov't Affairs", href: '/advocacy' },
          { label: 'Grants', href: '/grants' },
          { label: 'Grief Fund', href: '/grieffund' },
          { label: 'Library', href: '/library' },
          { label: 'Chapters', href: '/chapters' },
        ],
      },
    ],
    sidebarSections: [
      {
        id: 'your_portal',
        title: 'Your portal',
        items: [
          { id: 'member_profile', label: 'Member Profile', to: '/profile', icon: 'user', order: 10 },
          { id: 'store', label: 'Store', to: '/store', icon: 'store', order: 20 },
          { id: 'rescue', label: 'Rescue', to: '/rescue', icon: 'shield', order: 30 },
          { id: 'account', label: 'Account', to: '/account', icon: 'settings', order: 40 },
        ],
      },
      {
        id: 'explore',
        title: 'Explore',
        items: [
          { id: 'discounts', label: 'Discounts', to: '/discounts', icon: 'tag', order: 10 },
          { id: 'podcasts', label: 'Podcasts', to: '/podcasts', icon: 'mic', order: 20 },
          { id: 'events', label: 'Events', to: '/meetups', icon: 'users', order: 30 },
          { id: 'lodging', label: 'Lodging', to: '/lodging', icon: 'bed', order: 40 },
          { id: 'grants', label: 'Grants', to: '/grants', icon: 'scroll-text', order: 50 },
          { id: 'contact', label: 'Contact Us', to: '/contact', icon: 'mail', order: 60 },
        ],
      },
    ],
  },
};

export const getPortalUiSettings = () => {
  const runtimeSettings = getAppRuntimeConfig().portalSettings || {};

  return {
    content: {
      ...DEFAULT_SETTINGS.content,
      ...(runtimeSettings.content || {}),
    },
    design: {
      ...DEFAULT_SETTINGS.design,
      ...(runtimeSettings.design || {}),
    },
    navigation: {
      topNavSections:
        runtimeSettings.navigation?.topNavSections || DEFAULT_SETTINGS.navigation.topNavSections,
      sidebarSections:
        runtimeSettings.navigation?.sidebarSections || DEFAULT_SETTINGS.navigation.sidebarSections,
    },
  };
};
