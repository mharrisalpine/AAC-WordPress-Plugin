<?php
if (!defined('ABSPATH')) {
	exit;
}

if (is_home()) {
	require get_template_directory() . '/front-page.php';
	return;
}

get_header();
?>

<section class="aac-page-shell">
	<div class="aac-page-shell__inner">
		<div class="aac-prose-card">
			<header class="aac-prose-card__header">
				<p class="aac-eyebrow"><?php esc_html_e('Posts', 'aac-portal-theme'); ?></p>
				<h1><?php esc_html_e('Latest updates', 'aac-portal-theme'); ?></h1>
			</header>

			<div class="aac-post-list">
				<?php if (have_posts()) : ?>
					<?php while (have_posts()) : the_post(); ?>
						<article id="post-<?php the_ID(); ?>" <?php post_class('aac-post-list__item'); ?>>
							<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p><?php echo esc_html(get_the_excerpt()); ?></p>
						</article>
					<?php endwhile; ?>
				<?php else : ?>
					<p><?php esc_html_e('No posts found yet.', 'aac-portal-theme'); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();
