<?php
if (!defined('ABSPATH')) {
	exit;
}

get_header();

$hero_kicker = 'American Alpine Club';
$hero_title = "United\nWe Climb.";
$hero_description = 'AAC advances climbing knowledge, rescue support, advocacy, grants, publications, and community resources for climbers who care deeply about the mountains.';
$primary_cta_label = 'Join';
$primary_cta_url = aac_portal_theme_portal_route('join');
$secondary_cta_label = 'Donate';
$secondary_cta_url = 'https://membership.americanalpineclub.org/donate';
$hero_overlay = aac_portal_theme_get_setting('design', 'home_hero_overlay', 'linear-gradient(90deg, rgba(3,0,0,0.9), rgba(3,0,0,0.6))');
$hero_video_url = aac_portal_theme_get_setting('design', 'home_hero_video_url', '');

$shop_cards = [
	[
		'eyebrow' => 'Apparel',
		'title' => 'AAC Apparel',
		'description' => 'Technical layers, everyday tees, and member gear built for life between trailheads and town.',
		'url' => 'https://americanalpineclub.myshopify.com/collections/apparel',
		'image_url' => 'https://images.unsplash.com/photo-1517821365201-7734f463fdbb?auto=format&fit=crop&w=1400&q=80',
	],
	[
		'eyebrow' => 'Shells',
		'title' => 'Storm Layers',
		'description' => 'Weather-ready outerwear and mountain shells inspired by big objective days and rough forecasts.',
		'url' => 'https://americanalpineclub.myshopify.com/',
		'image_url' => 'https://images.unsplash.com/photo-1522163182402-834f871fd851?auto=format&fit=crop&w=1400&q=80',
	],
	[
		'eyebrow' => 'Packs',
		'title' => 'Approach & Haul',
		'description' => 'Load-carrying essentials for crag days, hut walks, and long approaches where the bag matters.',
		'url' => 'https://americanalpineclub.myshopify.com/',
		'image_url' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1400&q=80',
	],
	[
		'eyebrow' => 'Books',
		'title' => 'Books & Journals',
		'description' => 'Guidebooks, annual journals, and mountain knowledge that belongs in every climber’s kit.',
		'url' => home_url('/publications/'),
		'image_url' => 'https://images.unsplash.com/photo-1516589091380-5d8e87df6999?auto=format&fit=crop&w=1400&q=80',
	],
	[
		'eyebrow' => 'Membership',
		'title' => 'Gift Memberships',
		'description' => 'Give access, rescue coverage, and AAC community benefits in one mountain-ready package.',
		'url' => aac_portal_theme_portal_route('join'),
		'image_url' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1400&q=80',
	],
	[
		'eyebrow' => 'Rescue',
		'title' => 'Mountain Essentials',
		'description' => 'Support tools, rescue coverage information, and member resources for the days that matter most.',
		'url' => home_url('/rescue/'),
		'image_url' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1400&q=80',
	],
];

$feature_tiles = [
	[
		'eyebrow' => 'Membership',
		'title' => 'Support the climbing life from access work to rescue.',
		'description' => 'Membership powers advocacy, grants, rescue benefits, chapter programs, and a stronger future for climbers.',
		'button_label' => 'Explore Membership',
		'button_url' => aac_portal_theme_portal_route('join'),
		'image_url' => 'https://images.unsplash.com/photo-1522163182402-834f871fd851?auto=format&fit=crop&w=1600&q=80',
	],
	[
		'eyebrow' => 'Rescue',
		'title' => 'Know what your Redpoint benefits actually cover.',
		'description' => 'Clear rescue, medical, and transport coverage details built into the AAC member experience.',
		'button_label' => 'See Rescue Details',
		'button_url' => home_url('/rescue/'),
		'image_url' => 'https://images.unsplash.com/photo-1464823063530-08f10ed1a2dd?auto=format&fit=crop&w=1200&q=80',
	],
	[
		'eyebrow' => 'Grants',
		'title' => 'Back objectives, creativity, and climbing leadership.',
		'description' => 'AAC grants help climbers move ideas and expeditions from planning into real mountain action.',
		'button_label' => 'View Grants',
		'button_url' => home_url('/grants/'),
		'image_url' => 'https://images.unsplash.com/photo-1519904981063-b0cf448d479e?auto=format&fit=crop&w=1200&q=80',
	],
];

$impact_cards = [
	[
		'eyebrow' => 'Advocacy',
		'title' => 'Protect access',
		'description' => 'AAC shows up for climbers where policy, public land, and stewardship decisions actually get made.',
		'button_label' => 'See Advocacy',
		'button_url' => home_url('/advocacy/'),
		'image_url' => 'https://images.unsplash.com/photo-1501555088652-021faa106b9b?auto=format&fit=crop&w=1400&q=80',
	],
	[
		'eyebrow' => 'Lodging',
		'title' => 'Stay closer to the climbing',
		'description' => 'Member lodging puts community and access right next to the objectives people travel for.',
		'button_label' => 'See Lodging',
		'button_url' => home_url('/lodging/'),
		'image_url' => 'https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=1400&q=80',
	],
	[
		'eyebrow' => 'Community',
		'title' => 'Meet your people',
		'description' => 'Chapters, events, and publications turn AAC into a real network instead of just a membership line item.',
		'button_label' => 'See Community',
		'button_url' => home_url('/chapters/'),
		'image_url' => 'https://images.unsplash.com/photo-1527631746610-bca00a040d60?auto=format&fit=crop&w=1400&q=80',
	],
];

$stories_query = get_posts([
	'post_type' => 'post',
	'post_status' => 'publish',
	'posts_per_page' => 6,
]);

$story_fallbacks = [
	[
		'title' => 'Breaking beta from the alpine edge',
		'excerpt' => 'Big routes, gear lessons, and hard-earned takeaways from climbers moving through consequential terrain.',
		'url' => home_url('/stories/'),
		'image_url' => 'https://images.unsplash.com/photo-1517821365201-7734f463fdbb?auto=format&fit=crop&w=1400&q=80',
	],
	[
		'title' => 'Expeditions that move the conversation forward',
		'excerpt' => 'Field notes, grant-supported objectives, and the stories that keep mountain culture evolving.',
		'url' => home_url('/stories/'),
		'image_url' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1400&q=80',
	],
	[
		'title' => 'Knowledge every climber carries down',
		'excerpt' => 'Route history, accident analysis, and the kinds of lessons that matter long after the trip ends.',
		'url' => home_url('/stories/'),
		'image_url' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1400&q=80',
	],
];
?>

<section class="aac-hero" style="--aac-hero-overlay: <?php echo esc_attr($hero_overlay); ?>;">
	<?php if ($hero_video_url) : ?>
		<div class="aac-hero__video" aria-hidden="true">
			<iframe
				class="aac-hero__video-frame"
				title="AAC homepage hero video"
				src="<?php echo esc_url($hero_video_url); ?>"
				frameborder="0"
				allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share"
				referrerpolicy="strict-origin-when-cross-origin"
				allowfullscreen
			></iframe>
		</div>
	<?php else : ?>
		<div class="aac-hero__media"></div>
	<?php endif; ?>
	<div class="aac-hero__content aac-hero__content--home">
		<p class="aac-eyebrow"><?php echo esc_html($hero_kicker); ?></p>
		<h1><?php echo esc_html($hero_title); ?></h1>
		<p class="aac-hero__description"><?php echo esc_html($hero_description); ?></p>
		<div class="aac-hero__actions">
			<a class="aac-theme-action aac-theme-action--secondary" href="<?php echo esc_url($primary_cta_url); ?>"><?php echo esc_html($primary_cta_label); ?></a>
			<a class="aac-theme-action aac-theme-action--primary" href="<?php echo esc_url($secondary_cta_url); ?>"><?php echo esc_html($secondary_cta_label); ?></a>
		</div>
	</div>
</section>

<section class="aac-home-shop">
	<div class="aac-home-shell aac-home-shop__inner">
		<div class="aac-home-shop__heading">
			<div>
				<p class="aac-eyebrow">Web Store</p>
				<h2>Mountain-ready gear, memberships, books, and field essentials.</h2>
				<p>Pictured as a darker paneled slider instead of light cards, with more black, more red, and much less gold.</p>
			</div>
			<div class="aac-home-slider-controls">
				<button type="button" class="aac-home-slider-button" data-aac-slider-prev="home-shop" aria-label="Previous shop cards">&larr;</button>
				<button type="button" class="aac-home-slider-button" data-aac-slider-next="home-shop" aria-label="Next shop cards">&rarr;</button>
			</div>
		</div>

		<div class="aac-home-shop__track" data-aac-slider="home-shop">
			<?php foreach ($shop_cards as $card) : ?>
				<article class="aac-home-shop-card">
					<div class="aac-home-shop-card__media">
						<img src="<?php echo esc_url($card['image_url']); ?>" alt="">
					</div>
					<div class="aac-home-shop-card__body">
						<p class="aac-home-kicker"><?php echo esc_html($card['eyebrow']); ?></p>
						<h3><?php echo esc_html($card['title']); ?></h3>
						<p><?php echo esc_html($card['description']); ?></p>
						<a class="aac-home-link" href="<?php echo esc_url($card['url']); ?>">Explore</a>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="aac-home-features">
	<div class="aac-home-shell">
		<div class="aac-home-feature-lead">
			<div class="aac-home-feature-lead__media">
				<img src="<?php echo esc_url($feature_tiles[0]['image_url']); ?>" alt="">
			</div>
			<div class="aac-home-feature-lead__copy">
				<p class="aac-eyebrow"><?php echo esc_html($feature_tiles[0]['eyebrow']); ?></p>
				<h2><?php echo esc_html($feature_tiles[0]['title']); ?></h2>
				<p><?php echo esc_html($feature_tiles[0]['description']); ?></p>
				<a class="aac-theme-action aac-theme-action--primary" href="<?php echo esc_url($feature_tiles[0]['button_url']); ?>"><?php echo esc_html($feature_tiles[0]['button_label']); ?></a>
			</div>
		</div>

		<div class="aac-home-feature-pair">
			<?php foreach (array_slice($feature_tiles, 1) as $tile) : ?>
				<article class="aac-home-feature-card">
					<div class="aac-home-feature-card__media">
						<img src="<?php echo esc_url($tile['image_url']); ?>" alt="">
					</div>
					<div class="aac-home-feature-card__body">
						<p class="aac-home-kicker"><?php echo esc_html($tile['eyebrow']); ?></p>
						<h3><?php echo esc_html($tile['title']); ?></h3>
						<p><?php echo esc_html($tile['description']); ?></p>
						<a class="aac-home-link" href="<?php echo esc_url($tile['button_url']); ?>"><?php echo esc_html($tile['button_label']); ?></a>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="aac-home-stories">
	<div class="aac-home-shell">
		<div class="aac-home-stories__heading">
			<div>
				<p class="aac-eyebrow">Climbing Stories</p>
				<h2>Field notes, reporting, and the stories that keep the culture moving.</h2>
			</div>
			<div class="aac-home-slider-controls">
				<button type="button" class="aac-home-slider-button" data-aac-slider-prev="home-stories" aria-label="Previous stories">&larr;</button>
				<button type="button" class="aac-home-slider-button" data-aac-slider-next="home-stories" aria-label="Next stories">&rarr;</button>
			</div>
		</div>

		<div class="aac-home-story-track" data-aac-slider="home-stories">
			<?php if (!empty($stories_query)) : ?>
				<?php foreach ($stories_query as $story) : ?>
					<?php
					$story_image = get_the_post_thumbnail_url($story, 'large');
					if (!$story_image) {
						$story_image = 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1400&q=80';
					}
					?>
					<article class="aac-home-story-card">
						<div class="aac-home-story-card__media">
							<img src="<?php echo esc_url($story_image); ?>" alt="">
						</div>
						<div class="aac-home-story-card__body">
							<p class="aac-home-kicker"><?php echo esc_html(get_the_date('F j, Y', $story)); ?></p>
							<h3><?php echo esc_html(get_the_title($story)); ?></h3>
							<p><?php echo esc_html(wp_trim_words(wp_strip_all_tags(get_the_excerpt($story) ?: $story->post_content), 24)); ?></p>
							<a class="aac-home-link" href="<?php echo esc_url(get_permalink($story)); ?>">Read Story</a>
						</div>
					</article>
				<?php endforeach; ?>
			<?php else : ?>
				<?php foreach ($story_fallbacks as $story) : ?>
					<article class="aac-home-story-card">
						<div class="aac-home-story-card__media">
							<img src="<?php echo esc_url($story['image_url']); ?>" alt="">
						</div>
						<div class="aac-home-story-card__body">
							<p class="aac-home-kicker">Stories &amp; News</p>
							<h3><?php echo esc_html($story['title']); ?></h3>
							<p><?php echo esc_html($story['excerpt']); ?></p>
							<a class="aac-home-link" href="<?php echo esc_url($story['url']); ?>">Read Story</a>
						</div>
					</article>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
</section>

<section class="aac-home-impact">
	<div class="aac-home-shell">
		<div class="aac-home-impact__heading">
			<p class="aac-eyebrow">How the AAC shows up</p>
			<h2>Programs that feel more editorial and product-led, but still point back to real member value.</h2>
		</div>

		<div class="aac-home-impact__grid">
			<?php foreach ($impact_cards as $card) : ?>
				<article class="aac-home-impact-card">
					<div class="aac-home-impact-card__media">
						<img src="<?php echo esc_url($card['image_url']); ?>" alt="">
					</div>
					<div class="aac-home-impact-card__body">
						<p class="aac-home-kicker"><?php echo esc_html($card['eyebrow']); ?></p>
						<h3><?php echo esc_html($card['title']); ?></h3>
						<p><?php echo esc_html($card['description']); ?></p>
						<a class="aac-home-link" href="<?php echo esc_url($card['button_url']); ?>"><?php echo esc_html($card['button_label']); ?></a>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php
get_footer();
