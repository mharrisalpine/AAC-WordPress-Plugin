<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_Admin {
	const OPTION_KEY = 'aac_member_portal_settings';
	const MENU_SLUG = 'aac-member-portal-settings';

	public function __construct() {
		add_action('admin_menu', [$this, 'register_admin_page']);
		add_action('admin_init', [$this, 'register_settings']);
	}

	public static function get_defaults() {
		return [
			'content' => [
				'account_settings_title' => 'Account Settings',
				'contact_recipient_email' => 'mharris@americanalpineclub.org',
				'profile_information_description' => 'Primary contact and profile information used across the AAC portal. You may update your details and preferences in Account Settings.',
				'membership_snapshot_description' => 'Live membership and benefit details coming from WordPress and Paid Memberships Pro.',
				'member_details_description' => 'Members receive a free T-shirt and books with the purchase of their membership.',
				'portal_preferences_title' => 'Portal Preferences',
				'portal_preferences_description' => 'Settings the portal is currently storing for your member record.',
				'quick_actions_title' => 'Quick Actions',
				'quick_actions_description' => 'Jump straight into the next member task.',
				'grant_applications_description' => 'Recent AAC grant submissions tied to your member record.',
			],
			'design' => [
				'sidebar_background_url' => '',
				'sidebar_overlay_start' => '0.18',
				'sidebar_overlay_end' => '0.30',
				'sidebar_button_background' => '#000000',
				'sidebar_button_hover_background' => '#111111',
				'sidebar_button_active_background' => '#000000',
				'sidebar_accent_color' => '#f8c235',
			],
			'components' => [
				'section_titles' => [
					'your_portal' => 'Your portal',
					'explore' => 'Explore',
				],
				'top_nav_items' => self::get_default_top_nav_items(),
				'sidebar_items' => self::get_default_sidebar_items(),
			],
		];
	}

	public static function get_default_top_nav_items() {
		return [
			'get_involved' => [
				'label' => 'Get Involved',
				'order' => 10,
				'visible' => 1,
			],
			'membership' => [
				'label' => 'Membership',
				'order' => 20,
				'visible' => 1,
			],
			'stories_news' => [
				'label' => 'Stories & News',
				'order' => 30,
				'visible' => 1,
			],
			'lodging' => [
				'label' => 'Lodging',
				'order' => 40,
				'visible' => 1,
			],
			'publications' => [
				'label' => 'Publications',
				'order' => 50,
				'visible' => 1,
			],
			'our_work' => [
				'label' => 'Our Work',
				'order' => 60,
				'visible' => 1,
			],
		];
	}

	public static function get_default_sidebar_items() {
		return [
			'member_profile' => [
				'label' => 'Member Profile',
				'section' => 'your_portal',
				'order' => 10,
				'visible' => 1,
			],
			'store' => [
				'label' => 'Store',
				'section' => 'your_portal',
				'order' => 20,
				'visible' => 1,
			],
			'rescue' => [
				'label' => 'Rescue',
				'section' => 'your_portal',
				'order' => 30,
				'visible' => 1,
			],
			'account' => [
				'label' => 'Account',
				'section' => 'your_portal',
				'order' => 40,
				'visible' => 1,
			],
			'discounts' => [
				'label' => 'Discounts',
				'section' => 'explore',
				'order' => 10,
				'visible' => 1,
			],
			'podcasts' => [
				'label' => 'Podcasts',
				'section' => 'explore',
				'order' => 20,
				'visible' => 1,
			],
			'events' => [
				'label' => 'Events',
				'section' => 'explore',
				'order' => 30,
				'visible' => 1,
			],
			'lodging' => [
				'label' => 'Lodging',
				'section' => 'explore',
				'order' => 40,
				'visible' => 1,
			],
			'grants' => [
				'label' => 'Grants',
				'section' => 'explore',
				'order' => 50,
				'visible' => 1,
			],
			'contact' => [
				'label' => 'Contact Us',
				'section' => 'explore',
				'order' => 60,
				'visible' => 1,
			],
		];
	}

	public static function get_settings() {
		$stored = get_option(self::OPTION_KEY, []);
		$stored = is_array($stored) ? $stored : [];

		return self::merge_with_defaults(self::get_defaults(), $stored);
	}

	public static function get_contact_recipient_email() {
		$settings = self::get_settings();
		$recipient_email = sanitize_email($settings['content']['contact_recipient_email'] ?? '');

		if ($recipient_email && is_email($recipient_email)) {
			return $recipient_email;
		}

		return sanitize_email(get_option('admin_email'));
	}

	public function register_admin_page() {
		add_menu_page(
			'AAC Portal Settings',
			'AAC Portal',
			'manage_options',
			self::MENU_SLUG,
			[$this, 'render_admin_page'],
			'dashicons-admin-generic',
			56
		);
	}

	public function register_settings() {
		register_setting(
			'aac_member_portal_settings_group',
			self::OPTION_KEY,
			[$this, 'sanitize_settings']
		);
	}

	public function sanitize_settings($input) {
		$defaults = self::get_defaults();
		$input = is_array($input) ? $input : [];

		$settings = $defaults;

		$settings['content']['account_settings_title'] = sanitize_text_field($input['content']['account_settings_title'] ?? $defaults['content']['account_settings_title']);
		$contact_recipient_email = sanitize_email($input['content']['contact_recipient_email'] ?? $defaults['content']['contact_recipient_email']);
		$settings['content']['contact_recipient_email'] = $contact_recipient_email && is_email($contact_recipient_email)
			? $contact_recipient_email
			: $defaults['content']['contact_recipient_email'];
		$settings['content']['profile_information_description'] = sanitize_textarea_field($input['content']['profile_information_description'] ?? $defaults['content']['profile_information_description']);
		$settings['content']['membership_snapshot_description'] = sanitize_textarea_field($input['content']['membership_snapshot_description'] ?? $defaults['content']['membership_snapshot_description']);
		$settings['content']['member_details_description'] = sanitize_textarea_field($input['content']['member_details_description'] ?? $defaults['content']['member_details_description']);
		$settings['content']['portal_preferences_title'] = sanitize_text_field($input['content']['portal_preferences_title'] ?? $defaults['content']['portal_preferences_title']);
		$settings['content']['portal_preferences_description'] = sanitize_textarea_field($input['content']['portal_preferences_description'] ?? $defaults['content']['portal_preferences_description']);
		$settings['content']['quick_actions_title'] = sanitize_text_field($input['content']['quick_actions_title'] ?? $defaults['content']['quick_actions_title']);
		$settings['content']['quick_actions_description'] = sanitize_textarea_field($input['content']['quick_actions_description'] ?? $defaults['content']['quick_actions_description']);
		$settings['content']['grant_applications_description'] = sanitize_textarea_field($input['content']['grant_applications_description'] ?? $defaults['content']['grant_applications_description']);

		$settings['design']['sidebar_background_url'] = esc_url_raw($input['design']['sidebar_background_url'] ?? '');
		$settings['design']['sidebar_overlay_start'] = $this->sanitize_opacity($input['design']['sidebar_overlay_start'] ?? $defaults['design']['sidebar_overlay_start']);
		$settings['design']['sidebar_overlay_end'] = $this->sanitize_opacity($input['design']['sidebar_overlay_end'] ?? $defaults['design']['sidebar_overlay_end']);
		$settings['design']['sidebar_button_background'] = $this->sanitize_hex_color_or_default($input['design']['sidebar_button_background'] ?? '', $defaults['design']['sidebar_button_background']);
		$settings['design']['sidebar_button_hover_background'] = $this->sanitize_hex_color_or_default($input['design']['sidebar_button_hover_background'] ?? '', $defaults['design']['sidebar_button_hover_background']);
		$settings['design']['sidebar_button_active_background'] = $this->sanitize_hex_color_or_default($input['design']['sidebar_button_active_background'] ?? '', $defaults['design']['sidebar_button_active_background']);
		$settings['design']['sidebar_accent_color'] = $this->sanitize_hex_color_or_default($input['design']['sidebar_accent_color'] ?? '', $defaults['design']['sidebar_accent_color']);

		$section_titles = $input['components']['section_titles'] ?? [];
		foreach ($defaults['components']['section_titles'] as $section_id => $default_title) {
			$settings['components']['section_titles'][$section_id] = sanitize_text_field($section_titles[$section_id] ?? $default_title);
		}

		$top_nav_items = $input['components']['top_nav_items'] ?? [];
		foreach ($defaults['components']['top_nav_items'] as $item_id => $item_defaults) {
			$item_input = isset($top_nav_items[$item_id]) && is_array($top_nav_items[$item_id]) ? $top_nav_items[$item_id] : [];

			$settings['components']['top_nav_items'][$item_id] = [
				'label' => sanitize_text_field($item_input['label'] ?? $item_defaults['label']),
				'order' => isset($item_input['order']) ? (int) $item_input['order'] : (int) $item_defaults['order'],
				'visible' => empty($item_input['visible']) ? 0 : 1,
			];
		}

		$sidebar_items = $input['components']['sidebar_items'] ?? [];
		foreach ($defaults['components']['sidebar_items'] as $item_id => $item_defaults) {
			$item_input = isset($sidebar_items[$item_id]) && is_array($sidebar_items[$item_id]) ? $sidebar_items[$item_id] : [];
			$section = sanitize_key($item_input['section'] ?? $item_defaults['section']);
			if (!isset($defaults['components']['section_titles'][$section])) {
				$section = $item_defaults['section'];
			}

			$settings['components']['sidebar_items'][$item_id] = [
				'label' => sanitize_text_field($item_input['label'] ?? $item_defaults['label']),
				'section' => $section,
				'order' => isset($item_input['order']) ? (int) $item_input['order'] : (int) $item_defaults['order'],
				'visible' => empty($item_input['visible']) ? 0 : 1,
			];
		}

		return $settings;
	}

	public function render_admin_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = self::get_settings();
		$defaults = self::get_defaults();
		?>
		<div class="wrap">
			<h1>AAC Portal Settings</h1>
			<p>Manage shared portal copy, sidebar styling, and the visibility/order of navigation components used by both the React app and the PMPro WordPress shells.</p>

			<form method="post" action="options.php">
				<?php settings_fields('aac_member_portal_settings_group'); ?>

				<div style="display:grid;gap:24px;max-width:1100px;">
					<section style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:24px;">
						<h2 style="margin-top:0;">Content</h2>
						<table class="form-table" role="presentation">
							<tbody>
								<?php $this->render_input_row(self::OPTION_KEY . '[content][account_settings_title]', 'Account Settings title', $settings['content']['account_settings_title']); ?>
								<?php $this->render_input_row(self::OPTION_KEY . '[content][contact_recipient_email]', 'Contact form recipient email', $settings['content']['contact_recipient_email'], 'email', 'Messages from the member app Contact form will be sent to this address.'); ?>
								<?php $this->render_textarea_row(self::OPTION_KEY . '[content][profile_information_description]', 'Profile Information description', $settings['content']['profile_information_description']); ?>
								<?php $this->render_textarea_row(self::OPTION_KEY . '[content][membership_snapshot_description]', 'Membership Snapshot description', $settings['content']['membership_snapshot_description']); ?>
								<?php $this->render_textarea_row(self::OPTION_KEY . '[content][member_details_description]', 'Member Details description', $settings['content']['member_details_description']); ?>
								<?php $this->render_input_row(self::OPTION_KEY . '[content][portal_preferences_title]', 'Portal Preferences title', $settings['content']['portal_preferences_title']); ?>
								<?php $this->render_textarea_row(self::OPTION_KEY . '[content][portal_preferences_description]', 'Portal Preferences description', $settings['content']['portal_preferences_description']); ?>
								<?php $this->render_input_row(self::OPTION_KEY . '[content][quick_actions_title]', 'Quick Actions title', $settings['content']['quick_actions_title']); ?>
								<?php $this->render_textarea_row(self::OPTION_KEY . '[content][quick_actions_description]', 'Quick Actions description', $settings['content']['quick_actions_description']); ?>
								<?php $this->render_textarea_row(self::OPTION_KEY . '[content][grant_applications_description]', 'Grant Applications description', $settings['content']['grant_applications_description']); ?>
							</tbody>
						</table>
					</section>

					<section style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:24px;">
						<h2 style="margin-top:0;">Design</h2>
						<table class="form-table" role="presentation">
							<tbody>
								<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_background_url]', 'Sidebar background image URL', $settings['design']['sidebar_background_url'], 'url', 'Leave blank to use the bundled topo background.'); ?>
								<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_overlay_start]', 'Sidebar overlay start opacity', $settings['design']['sidebar_overlay_start'], 'number', 'Lower values make the topo lines more visible.', '0', '1', '0.01'); ?>
								<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_overlay_end]', 'Sidebar overlay end opacity', $settings['design']['sidebar_overlay_end'], 'number', 'Used for the darker lower part of the overlay.', '0', '1', '0.01'); ?>
								<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_button_background]', 'Sidebar button background', $settings['design']['sidebar_button_background'], 'text'); ?>
								<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_button_hover_background]', 'Sidebar button hover background', $settings['design']['sidebar_button_hover_background'], 'text'); ?>
								<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_button_active_background]', 'Sidebar button active background', $settings['design']['sidebar_button_active_background'], 'text'); ?>
								<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_accent_color]', 'Sidebar accent color', $settings['design']['sidebar_accent_color'], 'text'); ?>
							</tbody>
						</table>
					</section>

					<section style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:24px;">
						<h2 style="margin-top:0;">Component Visibility and Arrangement</h2>
						<p>Update section titles and control where each sidebar item appears.</p>

						<table class="form-table" role="presentation">
							<tbody>
								<?php foreach ($settings['components']['section_titles'] as $section_id => $title) : ?>
									<?php $this->render_input_row(self::OPTION_KEY . '[components][section_titles][' . $section_id . ']', sprintf('Section title: %s', $defaults['components']['section_titles'][$section_id]), $title); ?>
								<?php endforeach; ?>
							</tbody>
						</table>

						<h3 style="margin:24px 0 12px;">Top Navigation</h3>
						<p>Control the top AAC navigation labels, order, and visibility. Child links stay mapped to the current AAC destinations.</p>

						<table class="widefat striped" style="margin-top:16px;">
							<thead>
								<tr>
									<th>Section</th>
									<th>Label</th>
									<th>Order</th>
									<th>Visible</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($settings['components']['top_nav_items'] as $item_id => $item_settings) : ?>
									<tr>
										<td><strong><?php echo esc_html($item_id); ?></strong></td>
										<td>
											<input
												type="text"
												class="regular-text"
												name="<?php echo esc_attr(self::OPTION_KEY . '[components][top_nav_items][' . $item_id . '][label]'); ?>"
												value="<?php echo esc_attr($item_settings['label']); ?>"
											/>
										</td>
										<td>
											<input
												type="number"
												name="<?php echo esc_attr(self::OPTION_KEY . '[components][top_nav_items][' . $item_id . '][order]'); ?>"
												value="<?php echo esc_attr($item_settings['order']); ?>"
												style="width:90px;"
											/>
										</td>
										<td>
											<label>
												<input
													type="checkbox"
													name="<?php echo esc_attr(self::OPTION_KEY . '[components][top_nav_items][' . $item_id . '][visible]'); ?>"
													value="1"
													<?php checked(!empty($item_settings['visible'])); ?>
												/>
												Visible
											</label>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<table class="widefat striped" style="margin-top:16px;">
							<thead>
								<tr>
									<th>Component</th>
									<th>Label</th>
									<th>Section</th>
									<th>Order</th>
									<th>Visible</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($settings['components']['sidebar_items'] as $item_id => $item_settings) : ?>
									<tr>
										<td><strong><?php echo esc_html($item_id); ?></strong></td>
										<td>
											<input
												type="text"
												class="regular-text"
												name="<?php echo esc_attr(self::OPTION_KEY . '[components][sidebar_items][' . $item_id . '][label]'); ?>"
												value="<?php echo esc_attr($item_settings['label']); ?>"
											/>
										</td>
										<td>
											<select name="<?php echo esc_attr(self::OPTION_KEY . '[components][sidebar_items][' . $item_id . '][section]'); ?>">
												<?php foreach ($settings['components']['section_titles'] as $section_id => $section_title) : ?>
													<option value="<?php echo esc_attr($section_id); ?>" <?php selected($item_settings['section'], $section_id); ?>>
														<?php echo esc_html($section_title); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</td>
										<td>
											<input
												type="number"
												name="<?php echo esc_attr(self::OPTION_KEY . '[components][sidebar_items][' . $item_id . '][order]'); ?>"
												value="<?php echo esc_attr($item_settings['order']); ?>"
												style="width:90px;"
											/>
										</td>
										<td>
											<label>
												<input
													type="checkbox"
													name="<?php echo esc_attr(self::OPTION_KEY . '[components][sidebar_items][' . $item_id . '][visible]'); ?>"
													value="1"
													<?php checked(!empty($item_settings['visible'])); ?>
												/>
												Visible
											</label>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</section>
				</div>

				<?php submit_button('Save Portal Settings'); ?>
			</form>
		</div>
		<?php
	}

	private function render_input_row($name, $label, $value, $type = 'text', $help = '', $min = null, $max = null, $step = null) {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label></th>
			<td>
				<input
					type="<?php echo esc_attr($type); ?>"
					id="<?php echo esc_attr($name); ?>"
					name="<?php echo esc_attr($name); ?>"
					value="<?php echo esc_attr($value); ?>"
					class="regular-text"
					<?php echo $min !== null ? 'min="' . esc_attr($min) . '"' : ''; ?>
					<?php echo $max !== null ? 'max="' . esc_attr($max) . '"' : ''; ?>
					<?php echo $step !== null ? 'step="' . esc_attr($step) . '"' : ''; ?>
				/>
				<?php if ($help) : ?>
					<p class="description"><?php echo esc_html($help); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function render_textarea_row($name, $label, $value) {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label></th>
			<td>
				<textarea
					id="<?php echo esc_attr($name); ?>"
					name="<?php echo esc_attr($name); ?>"
					rows="3"
					class="large-text"
				><?php echo esc_textarea($value); ?></textarea>
			</td>
		</tr>
		<?php
	}

	private function sanitize_opacity($value) {
		$value = is_scalar($value) ? (float) $value : 0.18;
		$value = max(0, min(1, $value));
		return number_format($value, 2, '.', '');
	}

	private function sanitize_hex_color_or_default($value, $default) {
		$sanitized = sanitize_hex_color($value);
		return $sanitized ? $sanitized : $default;
	}

	private static function merge_with_defaults($defaults, $values) {
		foreach ($defaults as $key => $default_value) {
			if (is_array($default_value)) {
				$values[$key] = self::merge_with_defaults($default_value, isset($values[$key]) && is_array($values[$key]) ? $values[$key] : []);
				continue;
			}

			if (!array_key_exists($key, $values)) {
				$values[$key] = $default_value;
			}
		}

		return $values;
	}
}
