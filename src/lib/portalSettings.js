import { getAppRuntimeConfig } from '@/lib/backendConfig';

// These defaults are the app's emergency backpack: they keep the frontend usable
// outside WordPress and catch missing runtime settings before the UI face-plants.
const DEFAULT_SETTINGS = {
  content: {
    home_hero_kicker: 'Home',
    home_hero_title: 'United\nWe Climb.',
    home_hero_description:
      'Explore AAC membership, rescue coverage, publications, grants, and community resources through the same member-focused experience that powers the portal.',
    home_primary_cta_label: 'Join',
    home_primary_cta_url: '/join',
    home_secondary_cta_label: 'Renew',
    home_secondary_cta_url: 'https://membership.americanalpineclub.org/renew',
    home_tertiary_cta_label: 'Learn More About Membership',
    home_tertiary_cta_url: 'https://americanalpine.wpenginepowered.com/learn-more/',
    home_membership_chip_kicker: 'Membership',
    home_membership_chip_description:
      'Climbing advocacy, rescue coverage, publications, events, and member resources all live here.',
    home_intro_kicker: 'Since 1902',
    home_intro_title: 'Built for climbers.',
    home_intro_description:
      'Founded in 1902, the American Alpine Club is a nonprofit that champions climbing knowledge, inspiration, advocacy, and community support for people who care deeply about the mountains.',
    home_intro_secondary_description:
      'From rescue benefits and member publications to grants, events, and lodging, the Club keeps building practical resources that help climbers stay connected and better supported.',
    home_intro_button_label: 'Learn More About The AAC',
    home_intro_button_url: 'https://americanalpine.wpenginepowered.com/learn-more/',
    home_involvement_kicker: 'Explore',
    home_involvement_title: 'How To Get Involved',
    home_involvement_button_label: 'Join the Club',
    home_involvement_button_url: '/join',
    home_publications_kicker: 'Library',
    home_publications_title: 'Our Publications',
    home_publications_button_label: 'All Publications',
    home_publications_button_url: 'https://americanalpine.wpenginepowered.com/publications/',
    home_store_kicker: 'Store',
    home_store_title: 'Shop AAC Store',
    home_store_description: 'Browse featured AAC apparel, gear, and member merchandise from the Club store.',
    home_store_button_label: 'AAC Store',
    home_store_button_url: 'https://americanalpineclub.myshopify.com/',
    home_partners_kicker: 'Network',
    home_partners_title: 'Our Partners',
    home_partners_description:
      'Partner brands and community collaborators help AAC extend member value across climbing gear, publications, events, and advocacy work.',
    homeInvolvementCards: [
      {
        title: 'Join the Club',
        description:
          'Membership supports AAC advocacy, rescue benefits, climbing knowledge, grants, and the wider climbing community.',
        buttonLabel: 'Join Now',
        buttonUrl: '/join',
        imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-4.jpg',
        accentStyle: 'gold',
      },
      {
        title: 'Attend an Event',
        description:
          'Connect with the AAC community through upcoming events, member gatherings, and shared learning in climbing spaces.',
        buttonLabel: 'See Events',
        buttonUrl: 'https://americanalpine.wpenginepowered.com/events/',
        imageUrl: '',
        accentStyle: 'light',
      },
      {
        title: 'Stay at AAC Lodging',
        description:
          'Explore climber lodging destinations and plan your next trip through AAC campgrounds and ranch properties.',
        buttonLabel: 'Explore Lodging',
        buttonUrl: 'https://americanalpine.wpenginepowered.com/lodging/',
        imageUrl: '',
        accentStyle: 'sand',
      },
    ],
    homePublicationCards: [
      {
        title: 'American Alpine Journal',
        description:
          'Long-form reporting on major climbs around the world, presented in AAC’s flagship publication.',
        buttonLabel: 'View Publication',
        buttonUrl: 'https://americanalpine.wpenginepowered.com/publications/aaj/',
        imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-95.jpeg',
        accentColor: '#f8c235',
      },
      {
        title: 'Accidents in North American Climbing',
        description:
          'Annual accident analysis and takeaways that help climbers learn from the year’s most important incidents.',
        buttonLabel: 'View Publication',
        buttonUrl: 'https://americanalpine.wpenginepowered.com/publications/accidents/',
        imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-28.jpeg',
        accentColor: '#b20710',
      },
    ],
    homePartnerLogos: [
      {
        name: 'American Alpine Club',
        imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/09/dark-header-logo.svg',
        linkUrl: 'https://americanalpine.wpenginepowered.com/',
      },
      {
        name: 'Backcountry',
        imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Filler-Logo-2.png',
        linkUrl: '',
      },
      {
        name: 'Black Diamond',
        imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Filler-Logo-1.png',
        linkUrl: '',
      },
    ],
    photographers_page_kicker: 'Featured Photographers',
    photographers_page_title: 'The people behind the mountain images.',
    photographers_page_description:
      'Highlight AAC photographers, their work, and the landscapes they keep bringing back to the community.',
    grants_page_kicker: 'AAC Grants',
    grants_page_title: 'Support ambitious climbing, research, and community projects.',
    grants_page_description:
      'Review current AAC grant opportunities, choose the best fit, and submit your application from inside the member portal.',
    memberProfileCardSections: {
      membership_card: { label: 'Membership Card', visible: 1 },
      profile_information: { label: 'Profile Information', visible: 1 },
      membership_snapshot: { label: 'Membership Snapshot', visible: 1 },
      redpoint_benefits: { label: 'Redpoint Benefits', visible: 1 },
      linked_accounts: { label: 'Linked Accounts', visible: 1 },
      my_grants: { label: 'My Grants', visible: 1 },
      custom_blocks: { label: 'Custom Member Profile Blocks', visible: 1 },
    },
    featuredPhotographers: [
      {
        name: 'Avery Ridge',
        short_bio:
          'Avery chases storm light, ridgelines, and the quiet moments that happen after a long approach. Their work leans into alpine scale without losing the human story inside it.',
        website_url: 'https://example.com/avery-ridge',
        instagram_url: 'https://instagram.com/averyridgephoto',
        facebook_url: '',
        x_url: '',
        profile_image_url: 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80',
        gallery_items: [
          { image_url: 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1200&q=80', caption: 'Alpenglow over a granite ridge' },
          { image_url: 'https://images.unsplash.com/photo-1519681393784-d120267933ba?auto=format&fit=crop&w=1200&q=80', caption: 'Dawn clouds spilling over the pass' },
          { image_url: 'https://images.unsplash.com/photo-1454496522488-7a8e488e8606?auto=format&fit=crop&w=1200&q=80', caption: 'Snow blowing across a summit plateau' },
          { image_url: 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=1200&q=80', caption: 'Blue hour in the cirque' },
          { image_url: 'https://images.unsplash.com/photo-1464820453369-31d2c0b651af?auto=format&fit=crop&w=1200&q=80', caption: 'A high basin after fresh snow' },
          { image_url: 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1200&q=80', caption: 'Treeline giving way to rock' },
        ],
      },
      {
        name: 'Morgan Vale',
        short_bio:
          'Morgan focuses on climbing culture, big terrain, and the texture of expedition life. The frame is usually full of weather, movement, and one very committed pair of boots.',
        website_url: 'https://example.com/morgan-vale',
        instagram_url: 'https://instagram.com/morganvale.photo',
        facebook_url: '',
        x_url: '',
        profile_image_url: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=900&q=80',
        gallery_items: [
          { image_url: 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&w=1200&q=80', caption: 'A valley opening into the range' },
          { image_url: 'https://images.unsplash.com/photo-1482192596544-9eb780fc7f66?auto=format&fit=crop&w=1200&q=80', caption: 'Switchbacks below dark granite walls' },
          { image_url: 'https://images.unsplash.com/photo-1508261305436-4f659d0743eb?auto=format&fit=crop&w=1200&q=80', caption: 'Cold light on a glacier edge' },
          { image_url: 'https://images.unsplash.com/photo-1464823063530-08f10ed1a2dd?auto=format&fit=crop&w=1200&q=80', caption: 'Jagged skyline at first light' },
          { image_url: 'https://images.unsplash.com/photo-1458668383970-8ddd3927deed?auto=format&fit=crop&w=1200&q=80', caption: 'Storm shadows racing across the basin' },
          { image_url: 'https://images.unsplash.com/photo-1463694775559-eea25626346b?auto=format&fit=crop&w=1200&q=80', caption: 'Camp below layered peaks' },
        ],
      },
      {
        name: 'Sierra Ash',
        short_bio:
          'Sierra works in cold morning color, long shadows, and the kind of trailhead starts that feel half-asleep until the range suddenly lights up. Their galleries tend to hold equal parts weather and wonder.',
        website_url: 'https://example.com/sierra-ash',
        instagram_url: 'https://instagram.com/sierraash.studio',
        facebook_url: '',
        x_url: '',
        profile_image_url: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=900&q=80',
        gallery_items: [
          { image_url: 'https://images.unsplash.com/photo-1464820453369-31d2c0b651af?auto=format&fit=crop&w=1200&q=80', caption: 'Sunbreak on a high alpine shelf' },
          { image_url: 'https://images.unsplash.com/photo-1426604966848-d7adac402bff?auto=format&fit=crop&w=1200&q=80', caption: 'Cloud bands lifting off the ridge' },
          { image_url: 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=1200&q=80', caption: 'Evening light over dark evergreens' },
          { image_url: 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1200&q=80', caption: 'Still water below a sharp skyline' },
          { image_url: 'https://images.unsplash.com/photo-1464823063530-08f10ed1a2dd?auto=format&fit=crop&w=1200&q=80', caption: 'A serrated horizon at first light' },
          { image_url: 'https://images.unsplash.com/photo-1465146344425-f00d5f5c8f07?auto=format&fit=crop&w=1200&q=80', caption: 'Wildflowers leading into the mountain wall' },
        ],
      },
      {
        name: 'Parker Stone',
        short_bio:
          'Parker leans toward bold terrain and small human scale, with a style that makes cliffs, glaciers, and camp life all feel part of the same larger story. There is usually one tiny person somewhere in the frame doing something ambitious.',
        website_url: 'https://example.com/parker-stone',
        instagram_url: 'https://instagram.com/parkerstone.images',
        facebook_url: '',
        x_url: '',
        profile_image_url: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=900&q=80',
        gallery_items: [
          { image_url: 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&w=1200&q=80', caption: 'A broad valley pulling toward the peaks' },
          { image_url: 'https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=1200&q=80', caption: 'Tent light under an early alpine dusk' },
          { image_url: 'https://images.unsplash.com/photo-1443890923422-7819ed4101c0?auto=format&fit=crop&w=1200&q=80', caption: 'Cloud shadows over broken granite' },
          { image_url: 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1200&q=80', caption: 'Steep walls rising above the basin' },
          { image_url: 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1200&q=80', caption: 'Blue shadows on glacier ice' },
          { image_url: 'https://images.unsplash.com/photo-1504203700686-0f64f89a1f2d?auto=format&fit=crop&w=1200&q=80', caption: 'Wind crossing a snowy ridgeline' },
        ],
      },
      {
        name: 'Juniper North',
        short_bio:
          'Juniper photographs mountain travel with a documentary eye, favoring clean compositions, quiet trail moments, and weather that looks one decision away from becoming a whole new plan.',
        website_url: 'https://example.com/juniper-north',
        instagram_url: 'https://instagram.com/junipernorth.photo',
        facebook_url: '',
        x_url: '',
        profile_image_url: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=900&q=80',
        gallery_items: [
          { image_url: 'https://images.unsplash.com/photo-1464820453369-31d2c0b651af?auto=format&fit=crop&w=1200&q=80', caption: 'Morning haze over a glacial cirque' },
          { image_url: 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=80', caption: 'Rock bands glowing under soft light' },
          { image_url: 'https://images.unsplash.com/photo-1463694775559-eea25626346b?auto=format&fit=crop&w=1200&q=80', caption: 'Base camp beneath layered summits' },
          { image_url: 'https://images.unsplash.com/photo-1454496522488-7a8e488e8606?auto=format&fit=crop&w=1200&q=80', caption: 'Spindrift crossing a broad face' },
          { image_url: 'https://images.unsplash.com/photo-1482192596544-9eb780fc7f66?auto=format&fit=crop&w=1200&q=80', caption: 'An approach trail under huge stone walls' },
          { image_url: 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=1200&q=80', caption: 'Blue twilight settling into the range' },
        ],
      },
    ],
    grantOpportunities: [
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
        sourceUrl: 'https://theamericanalpineclub.submittable.com/submit',
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
        sourceUrl: 'https://theamericanalpineclub.submittable.com/submit',
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
        sourceUrl: 'https://theamericanalpineclub.submittable.com/submit',
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
        sourceUrl: 'https://theamericanalpineclub.submittable.com/submit',
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
        sourceUrl: 'https://theamericanalpineclub.submittable.com/submit',
      },
    ],
    grantFormFields: [
      {
        field_key: 'project_title',
        label: 'Project Title',
        type: 'text',
        required: 1,
        placeholder: 'Example: Wind River Granite Objectives',
        help_text: '',
        options: '',
      },
      {
        field_key: 'requested_amount',
        label: 'Amount Requested',
        type: 'number',
        required: 1,
        placeholder: '$2,500',
        help_text: '',
        options: '',
      },
      {
        field_key: 'objective_location',
        label: 'Objective / Project Location',
        type: 'text',
        required: 0,
        placeholder: 'Wind River Range, Wyoming',
        help_text: '',
        options: '',
      },
      {
        field_key: 'discipline',
        label: 'Discipline',
        type: 'text',
        required: 0,
        placeholder: 'Alpine, Ice, Research, Community program…',
        help_text: '',
        options: '',
      },
      {
        field_key: 'team_name',
        label: 'Team / Partners',
        type: 'text',
        required: 0,
        placeholder: 'List the climbers, researchers, or collaborators involved',
        help_text: '',
        options: '',
      },
      {
        field_key: 'summary',
        label: 'Project Summary',
        type: 'textarea',
        required: 1,
        placeholder:
          'Describe the objective, why this grant fits, what the funding unlocks, and how the project serves the AAC community.',
        help_text: '',
        options: '',
      },
    ],
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
    memberProfileBlocks: [],
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
    join_hero_title: 'United\nWe Climb.',
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
    login_hero_title: 'United\nWe Climb.',
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
    pageBackground: '#f7f1e3',
    panelBackground: '#ffffff',
    panelBorderColor: '#d6d3d1',
    heroPanelBackground: 'rgba(0,0,0,0.34)',
    heroPanelBorderColor: 'rgba(255,255,255,0.14)',
    heroChipBackground: 'rgba(0,0,0,0.38)',
    heroChipBorderColor: 'rgba(255,255,255,0.18)',
    loginFormBackground: 'rgba(247,241,232,0.94)',
    loginOverlay: 'linear-gradient(180deg,rgba(3,0,0,0.24),rgba(3,0,0,0.72)),radial-gradient(circle_at_top,rgba(248,194,53,0.12),transparent 24%)',
    homeHeroOverlay: 'linear-gradient(90deg,rgba(3,0,0,0.88) 0%,rgba(3,0,0,0.72) 38%,rgba(3,0,0,0.4) 62%,rgba(3,0,0,0.58) 100%)',
    homeHeroTintOverlay: 'linear-gradient(to top, rgba(3,0,0,0.5), transparent, rgba(3,0,0,0.16))',
    joinHeroOverlay: 'linear-gradient(90deg,rgba(3,0,0,0.88) 0%,rgba(3,0,0,0.72) 38%,rgba(3,0,0,0.4) 62%,rgba(3,0,0,0.58) 100%)',
    joinHeroTintOverlay: 'linear-gradient(to top, rgba(3,0,0,0.56), transparent, rgba(3,0,0,0.18))',
    navBackground: '#030000',
    navTextColor: '#ffffff',
    navHoverTextColor: '#f8c235',
    navIconColor: '#f8c235',
    navDropdownBackground: 'rgba(11,9,8,0.95)',
    navDropdownTextColor: '#f4efe7',
    joinHeroImageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Fillers.jpg',
    homeHeroVideoUrl:
      'https://player.vimeo.com/video/1166009381?h=c4c3248b38&background=1&autoplay=1&muted=1&loop=1&autopause=0&controls=0&title=0&byline=0&portrait=0',
    joinHeroVideoUrl:
      'https://player.vimeo.com/video/1166009381?h=c4c3248b38&background=1&autoplay=1&muted=1&loop=1&autopause=0&controls=0&title=0&byline=0&portrait=0',
    loginBackgroundImageUrl: '',
    homeIntroImageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-2.jpg',
    homeIntroAccentImageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-3.jpg',
    homeStoreImageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/AAC-Navy-Hat.jpg',
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
  layout: {
    homeSections: [
      { id: 'hero', label: 'Hero', order: 10, visible: 1 },
      { id: 'intro', label: 'Intro', order: 20, visible: 1 },
      { id: 'involvement', label: 'Get Involved', order: 30, visible: 1 },
      { id: 'publications', label: 'Publications', order: 40, visible: 1 },
      { id: 'store', label: 'Store', order: 50, visible: 1 },
      { id: 'partners', label: 'Partners', order: 60, visible: 1 },
    ],
  },
};

export const getPortalUiSettings = () => {
  const runtimeSettings = getAppRuntimeConfig().portalSettings || {};

  // WordPress hands us a very PHP-shaped settings blob. We translate and merge it
  // here so React gets the camelCase structure it expects and nobody has to turn
  // each component into its own tiny interpreter.
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
      homeInvolvementCards:
        Array.isArray(runtimeSettings.content?.homeInvolvementCards) && runtimeSettings.content.homeInvolvementCards.length
          ? runtimeSettings.content.homeInvolvementCards
          : DEFAULT_SETTINGS.content.homeInvolvementCards,
      homePublicationCards:
        Array.isArray(runtimeSettings.content?.homePublicationCards) && runtimeSettings.content.homePublicationCards.length
          ? runtimeSettings.content.homePublicationCards
          : DEFAULT_SETTINGS.content.homePublicationCards,
      homePartnerLogos:
        Array.isArray(runtimeSettings.content?.homePartnerLogos) && runtimeSettings.content.homePartnerLogos.length
          ? runtimeSettings.content.homePartnerLogos
          : DEFAULT_SETTINGS.content.homePartnerLogos,
      featuredPhotographers:
        Array.isArray(runtimeSettings.content?.featuredPhotographers) && runtimeSettings.content.featuredPhotographers.length
          ? runtimeSettings.content.featuredPhotographers
          : DEFAULT_SETTINGS.content.featuredPhotographers,
      grantOpportunities:
        Array.isArray(runtimeSettings.content?.grantOpportunities) && runtimeSettings.content.grantOpportunities.length
          ? runtimeSettings.content.grantOpportunities
          : DEFAULT_SETTINGS.content.grantOpportunities,
      grantFormFields:
        Array.isArray(runtimeSettings.content?.grantFormFields) && runtimeSettings.content.grantFormFields.length
          ? runtimeSettings.content.grantFormFields
          : DEFAULT_SETTINGS.content.grantFormFields,
      memberProfileBlocks:
        Array.isArray(runtimeSettings.content?.memberProfileBlocks) && runtimeSettings.content.memberProfileBlocks.length
          ? runtimeSettings.content.memberProfileBlocks
          : DEFAULT_SETTINGS.content.memberProfileBlocks,
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
    layout: {
      homeSections:
        runtimeSettings.layout?.homeSections || DEFAULT_SETTINGS.layout.homeSections,
    },
  };
};
