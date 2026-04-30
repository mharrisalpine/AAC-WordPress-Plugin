<?php
if (!defined('ABSPATH')) {
	exit;
}

if (is_front_page()) {
	require get_template_directory() . '/front-page.php';
	return;
}

get_header();
?>

<section class="aac-page-shell">
	<div class="aac-page-shell__inner">
		<?php while (have_posts()) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class('aac-prose-card'); ?>>
				<header class="aac-prose-card__header">
					<p class="aac-eyebrow"><?php esc_html_e('Page', 'aac-portal-theme'); ?></p>
					<h1><?php the_title(); ?></h1>
				</header>
				<div class="aac-prose-card__content">
					<?php the_content(); ?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
</section>

<?php
get_footer();
