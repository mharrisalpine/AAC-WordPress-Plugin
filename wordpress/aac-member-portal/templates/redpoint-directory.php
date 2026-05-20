<?php
if (!defined('ABSPATH')) {
	exit;
}

$search_value = esc_attr((string) ($args['search'] ?? ''));
$has_search = $search_value !== '';
$member = !empty($members[0]) && is_array($members[0]) ? $members[0] : null;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Redpoint Member Lookup</title>
	<?php wp_head(); ?>
	<style>
		body {
			margin: 0;
			background: #f5f2eb;
			color: #111;
			font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
		}
		.aac-redpoint-shell {
			max-width: 980px;
			margin: 0 auto;
			padding: 40px 24px 56px;
		}
		.aac-redpoint-card,
		.aac-redpoint-toolbar,
		.aac-redpoint-result {
			background: #fff;
			border: 1px solid #ded7cb;
		}
		.aac-redpoint-card,
		.aac-redpoint-toolbar,
		.aac-redpoint-result {
			padding: 28px 32px;
			margin-bottom: 20px;
		}
		.aac-redpoint-eyebrow {
			margin: 0 0 10px;
			color: #b58a10;
			font-size: 13px;
			font-weight: 700;
			letter-spacing: 0.18em;
			text-transform: uppercase;
		}
		.aac-redpoint-title {
			margin: 0 0 10px;
			font-size: 54px;
			line-height: 0.95;
			font-weight: 800;
		}
		.aac-redpoint-copy {
			margin: 0;
			color: #4b4b4b;
			font-size: 18px;
			line-height: 1.5;
			max-width: 760px;
		}
		.aac-redpoint-field label {
			display: block;
			margin-bottom: 8px;
			font-size: 14px;
			font-weight: 700;
			color: #343434;
		}
		.aac-redpoint-search-row {
			display: grid;
			grid-template-columns: minmax(0, 1fr) auto auto;
			gap: 14px;
			align-items: end;
		}
		.aac-redpoint-input {
			width: 100%;
			height: 52px;
			padding: 0 14px;
			border: 1px solid #d8d0c2;
			background: #fff;
			font-size: 16px;
			box-sizing: border-box;
		}
		.aac-redpoint-button {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-height: 52px;
			padding: 0 20px;
			background: #111;
			color: #fff;
			text-decoration: none;
			font-weight: 700;
			letter-spacing: 0.04em;
			text-transform: uppercase;
			border: 0;
			cursor: pointer;
		}
		.aac-redpoint-button--secondary {
			background: #fff;
			color: #111;
			border: 1px solid #d8d0c2;
		}
		.aac-redpoint-result-grid {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 18px;
		}
		.aac-redpoint-result-item {
			border: 1px solid #ece7df;
			padding: 18px 20px;
		}
		.aac-redpoint-result-label {
			display: block;
			margin-bottom: 8px;
			font-size: 12px;
			font-weight: 800;
			letter-spacing: 0.12em;
			text-transform: uppercase;
			color: #7a7469;
		}
		.aac-redpoint-result-value {
			font-size: 24px;
			line-height: 1.2;
			font-weight: 700;
			color: #111;
		}
		.aac-redpoint-empty {
			color: #666;
			font-size: 16px;
			line-height: 1.5;
		}
		@media (max-width: 900px) {
			.aac-redpoint-title {
				font-size: 42px;
			}
			.aac-redpoint-search-row,
			.aac-redpoint-result-grid {
				grid-template-columns: 1fr;
			}
		}
	</style>
</head>
<body <?php body_class('aac-redpoint-directory-page'); ?>>
<?php wp_body_open(); ?>
<main class="aac-redpoint-shell">
	<section class="aac-redpoint-card">
		<p class="aac-redpoint-eyebrow">Admin Lookup</p>
		<h1 class="aac-redpoint-title">Redpoint Member Lookup</h1>
		<p class="aac-redpoint-copy">Search by exact email address or phone number. Results only appear for an exact match.</p>
	</section>

	<section class="aac-redpoint-toolbar">
		<form method="get" action="<?php echo esc_url(home_url('/redpoint/')); ?>">
			<div class="aac-redpoint-search-row">
				<div class="aac-redpoint-field">
					<label for="aac-redpoint-search">Email or Phone Number</label>
					<input class="aac-redpoint-input" id="aac-redpoint-search" type="search" name="search" value="<?php echo $search_value; ?>" placeholder="name@example.com or 3032148285" autocomplete="off">
				</div>
				<button class="aac-redpoint-button" type="submit">Search</button>
				<a class="aac-redpoint-button aac-redpoint-button--secondary" href="<?php echo esc_url(home_url('/redpoint/')); ?>">Reset</a>
			</div>
		</form>
	</section>

	<?php if ($has_search) : ?>
		<section class="aac-redpoint-result">
			<?php if ($member) : ?>
				<div class="aac-redpoint-result-grid">
					<div class="aac-redpoint-result-item">
						<span class="aac-redpoint-result-label">Name</span>
						<div class="aac-redpoint-result-value"><?php echo esc_html($member['name'] ?: 'Unknown Member'); ?></div>
					</div>
					<div class="aac-redpoint-result-item">
						<span class="aac-redpoint-result-label">Membership Tier</span>
						<div class="aac-redpoint-result-value"><?php echo esc_html($member['membership']['tier'] ?: 'Not available'); ?></div>
					</div>
					<div class="aac-redpoint-result-item">
						<span class="aac-redpoint-result-label">Status</span>
						<div class="aac-redpoint-result-value"><?php echo esc_html($member['membership']['status'] ?: 'Unknown'); ?></div>
					</div>
					<div class="aac-redpoint-result-item">
						<span class="aac-redpoint-result-label">Expiration Date</span>
						<div class="aac-redpoint-result-value"><?php echo esc_html($member['membership']['expiration_date'] ?: 'Not scheduled'); ?></div>
					</div>
				</div>
			<?php else : ?>
				<div class="aac-redpoint-empty">No exact match found for that email address or phone number.</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
