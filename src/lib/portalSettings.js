import { getAppRuntimeConfig } from '@/lib/backendConfig';

// These defaults keep the React app usable outside of WordPress and provide a
// stable fallback any time the runtime config is missing a key.
const DEFAULT_SETTINGS = {
  content: {
    account_settings_title: 'Account Settings',
    profile_information_title: 'Profile Information',
    profile_information_description:
      'Primary contact and profile information used across the AAC portal. You may update your details and preferences in Account Settings.',
    update_profile_button_label: 'Update Profile Information',
    membership_snapshot_title: 'Membership Snapshot',
    membership_snapshot_description:
      'Live membership and benefit details coming from WordPress and Paid Memberships Pro.',
    linked_accounts_title: 'Linked Accounts',
    linked_accounts_description:
      'Manage household members connected to this AAC membership and redeem invite codes for child accounts.',
    member_details_description:
      'Members receive a free T-shirt and books with the purchase of their membership.',
    publications_title: 'Publications',
    publications_description:
      'Access the current AAC publication library and open each issue directly from the member portal.',
    publications_locked_title: 'Publications Unlock at Partner',
    publications_locked_description:
      'The AAC publication library is available to Partner members and above. Upgrade your membership to open digital issues and manage your publication preferences.',
    publications_upgrade_button_label: 'Upgrade Membership',
    publicationViewUrls: {
      aaj: 'https://aac-publications.s3.us-east-1.amazonaws.com/aaj/AAJ+2025.pdf',
      anac: 'https://aac-publications.s3.us-east-1.amazonaws.com/ANAC+2025+Book_Digital_reduced.pdf',
      acj: 'https://americanalpineclub.org/publications/',
      guidebook: 'https://www.flipsnack.com/americanalpineclub/guidebook-xv/full-view.html',
    },
    join_hero_kicker: 'Membership',
    join_hero_title: 'United We Climb.',
    join_hero_description:
      'Join the American Alpine Club to support climbing advocacy, rescue coverage, community grants, publications, events, and a member experience built for the people who keep showing up for the mountains.',
    join_primary_cta_label: 'Join Now',
    join_benefits_cta_label: 'Member Benefits',
    join_rescue_cta_label: 'Rescue Benefits',
    join_application_kicker: 'Application',
    join_application_title: 'Choose your membership and complete checkout.',
    join_application_description:
      'Select a membership level above, then complete the real AAC checkout form below.',
    join_redeem_code_button_label: 'Redeem Membership Code',
    login_hero_kicker: 'Member access',
    login_hero_title: 'Sign in to your AAC portal.',
    login_hero_description:
      'Access your membership details, rescue information, discounts, store purchases, and account settings in one place.',
    login_form_kicker: 'Login',
    login_form_title: 'Welcome back.',
    login_submit_label: 'Sign in',
    login_forgot_password_label: 'Forgot your password?',
    login_join_link_label: 'Need to join?',
    login_purchase_success_message: 'Purchase successful. Please sign in to access your member profile.',
    rescue_title: 'Rescue Insurance',
    rescue_coverage_title: 'RedPoint Rescue Coverage',
    rescue_emergency_title: 'Emergency Contact',
    rescue_claim_forms_title: 'Claim Forms',
    rescue_inactive_title: 'Membership Inactive',
    rescue_inactive_description:
      'Redpoint rescue and medical benefits are only available to active members.',
    rescue_upgrade_title: 'Unlock Rescue Benefits',
    rescue_upgrade_description:
      'Upgrade your membership to unlock crucial rescue and medical coverage.',
    rescue_manage_button_label: 'Manage Membership',
    linked_accounts_page_title: 'Linked Accounts',
    linked_accounts_page_description:
      'Enter a family invite code to create or claim a connected household account. If the email already has an AAC account, we will link that existing account after verifying the password.',
    linked_accounts_lookup_button_label: 'Check Code',
    linked_accounts_redeem_button_label: 'Redeem Invite Code',
    linked_accounts_success_message: 'Invite redeemed successfully. Redirecting to your member profile...',
    discounts_title: 'Partner Discounts',
    discounts_locked_title: 'Discounts Locked',
    discounts_locked_description:
      'Discounts are available to active members only. Renew or rejoin your membership to unlock partner offers.',
    discounts_free_locked_description:
      'Free memberships include portal preview access and promo emails, but partner discounts unlock with a paid membership.',
    discounts_upgrade_hint:
      'Upgrade from Free to Supporter or above whenever you are ready.',
    discounts_button_label: 'Visit Website',
    discountCards: [
      {
        brand: 'Patagonia',
        discount_percent: '20%',
        discount_code_text: 'Use your AAC Patagonia member code at checkout.',
        discount_percent_supporter: '15%',
        discount_percent_partner: '20%',
        discount_percent_leader: '25%',
        discount_percent_advocate: '25%',
        display_text: 'Premium outdoor clothing and gear for climbers and adventurers.',
        button_url: 'https://www.patagonia.com',
        image_url: 'https://images.unsplash.com/photo-1522163182402-834f871fd851?auto=format&fit=crop&w=900&q=80',
      },
      {
        brand: 'The North Face',
        discount_percent: '15%',
        discount_code_text: 'Use your AAC The North Face member code at checkout.',
        discount_percent_supporter: '10%',
        discount_percent_partner: '15%',
        discount_percent_leader: '18%',
        discount_percent_advocate: '20%',
        display_text: 'High-performance outdoor apparel and equipment.',
        button_url: 'https://www.thenorthface.com',
        image_url: 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=900&q=80',
      },
      {
        brand: 'Black Diamond',
        discount_percent: '25%',
        discount_code_text: 'Use your AAC Black Diamond member code at checkout.',
        discount_percent_supporter: '15%',
        discount_percent_partner: '20%',
        discount_percent_leader: '25%',
        discount_percent_advocate: '30%',
        display_text: 'Premium climbing gear, harnesses, and safety equipment.',
        button_url: 'https://www.blackdiamondequipment.com',
        image_url: 'https://images.unsplash.com/photo-1526491109672-74740652b963?auto=format&fit=crop&w=900&q=80',
      },
    ],
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
    primaryActionBackground: '#8f1515',
    primaryActionText: '#ffffff',
    secondaryActionBackground: '#f8c235',
    secondaryActionText: '#000000',
    joinHeroImageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Fillers.jpg',
    publicationTileImages: {
      aaj: '',
      anac: '',
      acj: '',
      guidebook: '',
    },
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
          { label: 'Join', href: '/join' },
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
          { id: 'account', label: 'Profile Information', to: '/account', icon: 'pen', order: 40 },
          { id: 'publications', label: 'Publications', to: '/publications', icon: 'book', order: 45 },
          {
            id: 'manage',
            label: 'Manage',
            href: 'https://wondrous-marshallleeharris.wpcomstaging.com/membership-account/membership-billing/',
            icon: 'settings',
            order: 50,
          },
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

  // WordPress sends settings in snake_case / PHP-friendly shapes. Here we merge
  // them into the camelCase structure the React app expects at render time.
  return {
    content: {
      ...DEFAULT_SETTINGS.content,
      ...(runtimeSettings.content || {}),
      publicationViewUrls: {
        ...DEFAULT_SETTINGS.content.publicationViewUrls,
        ...(runtimeSettings.content?.publicationViewUrls || {}),
      },
      discountCards:
        Array.isArray(runtimeSettings.content?.discountCards) && runtimeSettings.content.discountCards.length
          ? runtimeSettings.content.discountCards
          : DEFAULT_SETTINGS.content.discountCards,
    },
    design: {
      ...DEFAULT_SETTINGS.design,
      ...(runtimeSettings.design || {}),
      publicationTileImages: {
        ...DEFAULT_SETTINGS.design.publicationTileImages,
        ...(runtimeSettings.design?.publicationTileImages || {}),
      },
    },
    navigation: {
      topNavSections:
        runtimeSettings.navigation?.topNavSections || DEFAULT_SETTINGS.navigation.topNavSections,
      sidebarSections:
        runtimeSettings.navigation?.sidebarSections || DEFAULT_SETTINGS.navigation.sidebarSections,
    },
  };
};
