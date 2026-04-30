<?php if (!defined('ABSPATH')) { exit; } ?>
	</main>

	<footer class="aac-theme-footer">
		<div class="aac-theme-footer__inner">
			<div>
				<p class="aac-theme-footer__eyebrow"><?php esc_html_e('American Alpine Club', 'aac-portal-theme'); ?></p>
				<h2><?php esc_html_e('A reusable site shell for the wider AAC web presence.', 'aac-portal-theme'); ?></h2>
			</div>
			<div class="aac-theme-footer__meta">
				<p><?php echo esc_html(date_i18n('Y')); ?> <?php bloginfo('name'); ?></p>
				<p><?php esc_html_e('Built from the portal design system so the public site and member experience still feel like the same expedition.', 'aac-portal-theme'); ?></p>
			</div>
		</div>
	</footer>
</div>

<?php wp_footer(); ?>
</body>
</html>

