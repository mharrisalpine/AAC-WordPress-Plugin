<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Grants_Review_Admin {
	const MENU_SLUG = 'aac-grants-review';

	private $repository;
	private $settings;

	public function __construct($repository, $settings) {
		$this->repository = $repository;
		$this->settings = $settings;

		add_action('admin_menu', [$this, 'register_admin_pages']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
		add_action('admin_post_aac_grants_review_update_application', [$this, 'handle_update_application']);
	}

	public function register_admin_pages() {
		add_menu_page(
			'AAC Grants Review',
			'AAC Grants Review',
			AAC_Grants_Review_Installer::REVIEW_CAP,
			self::MENU_SLUG,
			[$this, 'render_applications_page'],
			'dashicons-clipboard',
			59
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Applications',
			'Applications',
			AAC_Grants_Review_Installer::REVIEW_CAP,
			self::MENU_SLUG,
			[$this, 'render_applications_page']
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Settings',
			'Settings',
			'manage_options',
			self::MENU_SLUG . '-settings',
			[$this, 'render_settings_page']
		);
	}

	public function enqueue_admin_assets($hook) {
		if (false === strpos((string) $hook, self::MENU_SLUG)) {
			return;
		}

		wp_register_style('aac-grants-review-admin', false, [], AAC_GRANTS_REVIEW_VERSION);
		wp_enqueue_style('aac-grants-review-admin');
		wp_add_inline_style('aac-grants-review-admin', $this->get_admin_css());
	}

	public function render_applications_page() {
		if (!current_user_can(AAC_Grants_Review_Installer::REVIEW_CAP)) {
			wp_die('You do not have permission to review grant applications.');
		}

		$application_id = absint($_GET['application_id'] ?? 0);

		echo '<div class="wrap aac-grants-review">';

		if ($application_id) {
			$this->render_application_detail($application_id);
		} else {
			$this->render_application_list();
		}

		echo '</div>';
	}

	public function render_settings_page() {
		if (!current_user_can('manage_options')) {
			wp_die('You do not have permission to manage grant review settings.');
		}

		$portal_fields = method_exists($this->settings, 'get_portal_grant_form_fields')
			? $this->settings->get_portal_grant_form_fields()
			: [];
		$grant_opportunities = method_exists($this->settings, 'get_portal_grant_opportunities')
			? $this->settings->get_portal_grant_opportunities()
			: [];
		$builder_url = method_exists($this->settings, 'get_portal_grants_builder_admin_url')
			? $this->settings->get_portal_grants_builder_admin_url()
			: admin_url('admin.php?page=aac-member-portal-settings&tab=grants');

		echo '<div class="wrap aac-grants-review">';
		echo '<h1>AAC Grants Review Settings</h1>';
		$this->render_notice();
		echo '<div class="aac-grants-review__layout">';

		echo '<div class="aac-grants-review__card">';
		echo '<h2>Custom Grant Form Builder</h2>';
		echo '<p class="description">AAC grant applications now come from the custom grant builder in <strong>AAC Portal &gt; Grants</strong>. That builder controls the member-facing form fields and opportunity cards, and submissions flow straight into this review queue without any WPForms dependency.</p>';
		echo '<p><a class="button button-primary" href="' . esc_url($builder_url) . '">Open Grants Builder</a></p>';
		echo '</div>';

		echo '<div class="aac-grants-review__card">';
		echo '<h2>Grant Opportunities</h2>';
		echo '<p class="description">These are the current opportunities exposed to members through the custom AAC grant form.</p>';
		if (empty($grant_opportunities)) {
			echo '<p>No grant opportunities detected yet.</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Slug</th><th>Category</th><th>Award</th></tr></thead><tbody>';
			foreach ($grant_opportunities as $opportunity) {
				echo '<tr>';
				echo '<td>' . esc_html($opportunity['name']) . '</td>';
				echo '<td><code>' . esc_html($opportunity['slug']) . '</code></td>';
				echo '<td>' . esc_html($opportunity['category']) . '</td>';
				echo '<td>' . esc_html($opportunity['award']) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';

		echo '<div class="aac-grants-review__card">';
		echo '<h2>Application Fields</h2>';
		echo '<p class="description">These member-portal form fields are managed in <strong>AAC Portal &gt; Grants</strong> and are passed straight into this review queue. The overview cards still rely most heavily on keys like <code>project_title</code>, <code>requested_amount</code>, and <code>summary</code>.</p>';
		if (empty($portal_fields)) {
			echo '<p>No portal grant fields detected yet.</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Label</th><th>Field Key</th><th>Type</th><th>Required</th></tr></thead><tbody>';
			foreach ($portal_fields as $field) {
				echo '<tr>';
				echo '<td>' . esc_html($field['label']) . '</td>';
				echo '<td><code>' . esc_html($field['field_key']) . '</code></td>';
				echo '<td>' . esc_html($field['type']) . '</td>';
				echo '<td>' . (!empty($field['required']) ? 'Yes' : 'No') . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';

		echo '<div class="aac-grants-review__card">';
		echo '<h2>Workflow Notes</h2>';
		echo '<p class="description">Reviewers work entirely from this queue. Applicants submit through the custom form in the member portal, then statuses like <strong>Submitted</strong>, <strong>Eligibility Review</strong>, <strong>Committee Review</strong>, <strong>Approved</strong>, and <strong>Rejected</strong> are reflected back into the member experience and any connected syncs.</p>';
		echo '</div>';

		echo '</div>';
		echo '</div>';
	}

	public function handle_update_application() {
		if (!current_user_can(AAC_Grants_Review_Installer::REVIEW_CAP)) {
			wp_die('You do not have permission to review grants.');
		}

		check_admin_referer('aac_grants_review_update_application');

		$application_id = absint($_POST['application_id'] ?? 0);
		$assigned_reviewer_id = absint($_POST['assigned_reviewer_id'] ?? 0);
		$note = wp_kses_post(wp_unslash($_POST['note'] ?? ''));
		$next_status = sanitize_key($_POST['next_status'] ?? '');
		$allow_terminal_override = !empty($_POST['direct_terminal_decision']);

		if (isset($_POST['assign_reviewer_only'])) {
			$this->repository->assign_reviewer($application_id, $assigned_reviewer_id, get_current_user_id());
			$this->redirect_to_requested_location(self::MENU_SLUG, 'updated', '1', ['application_id' => $application_id]);
		}

		$result = $this->repository->update_workflow($application_id, $next_status, $note, $assigned_reviewer_id, get_current_user_id(), $allow_terminal_override);
		if (is_wp_error($result)) {
			$this->redirect_to_requested_location(self::MENU_SLUG, 'workflow_error', rawurlencode($result->get_error_message()), ['application_id' => $application_id]);
		}

		$this->redirect_to_requested_location(self::MENU_SLUG, 'updated', '1', ['application_id' => $application_id]);
	}

	private function render_application_list() {
		$status = sanitize_key($_GET['status'] ?? '');
		$search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
		$applications = $this->repository->get_applications([
			'status' => $status,
			'search' => $search,
			'limit' => 200,
		]);
		$counts = $this->repository->count_applications_by_status();
		$labels = AAC_Grants_Review_Repository::get_workflow_labels();

		echo '<div class="aac-grants-review__page-header">';
		echo '<div><h1>AAC Grants Review</h1><p class="description">Review, route, and decide on grant applications without making reviewers spelunk through raw submission payloads.</p></div>';
		echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=' . self::MENU_SLUG . '-settings')) . '">Settings</a>';
		echo '</div>';

		$this->render_notice();

		echo '<div class="aac-grants-review__filters">';
		echo '<a class="aac-grants-review__filter ' . ($status === '' ? 'is-active' : '') . '" href="' . esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)) . '">All <span>' . array_sum($counts) . '</span></a>';
		foreach ($labels as $key => $label) {
			$url = add_query_arg([
				'page' => self::MENU_SLUG,
				'status' => $key,
			], admin_url('admin.php'));
			echo '<a class="aac-grants-review__filter ' . ($status === $key ? 'is-active' : '') . '" href="' . esc_url($url) . '">' . esc_html($label) . ' <span>' . absint($counts[$key] ?? 0) . '</span></a>';
		}
		echo '</div>';

		echo '<form method="get" class="aac-grants-review__search">';
		echo '<input type="hidden" name="page" value="' . esc_attr(self::MENU_SLUG) . '" />';
		if ($status !== '') {
			echo '<input type="hidden" name="status" value="' . esc_attr($status) . '" />';
		}
		echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Search applicant, grant, or project title" />';
		submit_button('Search', 'secondary', '', false);
		echo '</form>';

		echo '<div class="aac-grants-review__card">';
		if (!$applications) {
			echo '<p>No applications found yet. Once members start submitting through the AAC grant form builder, they will show up here.</p>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat fixed striped">';
		echo '<thead><tr><th>Applicant</th><th>Grant</th><th>Project</th><th>Status</th><th>Submitted</th><th>Reviewer</th></tr></thead><tbody>';
		foreach ($applications as $application) {
			$detail_url = add_query_arg([
				'page' => self::MENU_SLUG,
				'application_id' => absint($application['id']),
			], admin_url('admin.php'));
			$reviewer = !empty($application['assigned_reviewer_id']) ? get_userdata(absint($application['assigned_reviewer_id'])) : null;
			echo '<tr>';
			echo '<td><strong><a href="' . esc_url($detail_url) . '">' . esc_html($application['applicant_name'] ?: $application['applicant_email']) . '</a></strong><br /><span class="description">' . esc_html($application['applicant_email']) . '</span></td>';
			echo '<td>' . esc_html($application['grant_name'] ?: 'Untitled grant') . '</td>';
			echo '<td>' . esc_html($application['project_title'] ?: 'Untitled project') . '</td>';
			echo '<td><span class="' . esc_attr(AAC_Grants_Review_Repository::get_status_badge_class($application['workflow_status'])) . '">' . esc_html($labels[$application['workflow_status']] ?? ucfirst($application['workflow_status'])) . '</span></td>';
			echo '<td>' . esc_html(mysql2date('M j, Y g:i a', $application['submitted_at'])) . '</td>';
			echo '<td>' . esc_html($reviewer ? $reviewer->display_name : 'Unassigned') . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '</div>';
	}

	private function render_application_detail($application_id) {
		$application = $this->repository->get_application($application_id);
		if (!$application) {
			echo '<h1>Application not found</h1><p><a href="' . esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)) . '">Back to applications</a></p>';
			return;
		}

		$labels = AAC_Grants_Review_Repository::get_workflow_labels();
		$transitions = AAC_Grants_Review_Repository::get_workflow_transitions()[$application['workflow_status']] ?? [];
		$normalized_fields = method_exists($this->repository, 'prepare_normalized_fields_for_display')
			? $this->repository->prepare_normalized_fields_for_display($application['normalized_fields'] ?? [])
			: (is_array($application['normalized_fields'] ?? null) ? $application['normalized_fields'] : []);
		$reviewers = get_users([
			'capability' => AAC_Grants_Review_Installer::REVIEW_CAP,
			'orderby' => 'display_name',
			'order' => 'ASC',
		]);

		echo '<div class="aac-grants-review__page-header">';
		echo '<div><a href="' . esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)) . '" class="aac-grants-review__back-link">&larr; Back to applications</a>';
		echo '<h1>' . esc_html($application['project_title'] ?: ($application['grant_name'] ?: 'Grant Application')) . '</h1>';
		if (($application['source'] ?? '') === 'portal') {
			echo '<p class="description">Captured from the AAC custom grant form builder in the member portal. The reviewer workflow still keeps the raw payload below, but this screen is here to keep folks out of form-dump purgatory.</p></div>';
		} else {
			echo '<p class="description">Captured from a legacy imported intake record #' . absint($application['source_entry_id']) . '. The raw submission is still available below, but this page tries to do the reviewer-friendly organizing so nobody has to translate a form dump by hand.</p></div>';
		}
		echo '<span class="' . esc_attr(AAC_Grants_Review_Repository::get_status_badge_class($application['workflow_status'])) . '">' . esc_html($labels[$application['workflow_status']] ?? ucfirst($application['workflow_status'])) . '</span>';
		echo '</div>';

		$this->render_notice();

		echo '<div class="aac-grants-review__workflow">';
		foreach ($labels as $status_key => $label) {
			$is_current = $application['workflow_status'] === $status_key;
			$is_complete = array_search($status_key, array_keys($labels), true) < array_search($application['workflow_status'], array_keys($labels), true);
			$class = 'aac-grants-review__step';
			if ($is_current) {
				$class .= ' is-current';
			} elseif ($is_complete) {
				$class .= ' is-complete';
			}
			echo '<div class="' . esc_attr($class) . '"><span>' . esc_html($label) . '</span></div>';
		}
		echo '</div>';

		echo '<div class="aac-grants-review__layout">';
		echo '<div class="aac-grants-review__main">';

		echo '<div class="aac-grants-review__card">';
		echo '<h2>Overview</h2>';
		echo '<div class="aac-grants-review__stats">';
		$this->render_stat('Applicant', $application['applicant_name'] ?: 'Not mapped yet');
		$this->render_stat('Email', $application['applicant_email'] ?: 'Not mapped yet');
		$this->render_stat('Grant', $application['grant_name'] ?: 'Not mapped yet');
		$this->render_stat('Requested', $application['requested_amount'] ? '$' . number_format((float) $application['requested_amount'], 2) : 'Not mapped yet');
		$this->render_stat('Submitted', mysql2date('M j, Y g:i a', $application['submitted_at']));
		$this->render_stat('Reviewer', !empty($application['assigned_reviewer_id']) && ($user = get_userdata(absint($application['assigned_reviewer_id']))) ? $user->display_name : 'Unassigned');
		echo '</div>';
		echo '</div>';

		echo '<div class="aac-grants-review__card">';
		echo '<h2>Submitted Fields</h2>';
		echo '<div class="aac-grants-review__field-grid">';
		foreach ($normalized_fields as $field) {
			echo '<div class="aac-grants-review__field">';
			echo '<p class="aac-grants-review__field-label">' . esc_html($field['label'] ?: 'Unnamed field') . '</p>';
			echo '<div class="aac-grants-review__field-value">' . nl2br(esc_html($field['value'] ?: '—')) . '</div>';
			echo '</div>';
		}
		echo '</div>';
		echo '</div>';

		echo '<div class="aac-grants-review__card">';
		echo '<h2>Workflow History</h2>';
		if (empty($application['history'])) {
			echo '<p>No workflow events yet.</p>';
		} else {
			echo '<div class="aac-grants-review__timeline">';
			foreach ($application['history'] as $event) {
				echo '<div class="aac-grants-review__timeline-item">';
				echo '<div class="aac-grants-review__timeline-title">' . esc_html(ucwords(str_replace('_', ' ', $event['action']))) . '</div>';
				echo '<div class="aac-grants-review__timeline-meta">' . esc_html($event['actor_name']) . ' · ' . esc_html(mysql2date('M j, Y g:i a', $event['created_at'])) . '</div>';
				if (!empty($event['from_status']) || !empty($event['to_status'])) {
					echo '<div class="aac-grants-review__timeline-status">' . esc_html(($labels[$event['from_status']] ?? 'Start') . ' → ' . ($labels[$event['to_status']] ?? 'Now')) . '</div>';
				}
				if (!empty($event['note'])) {
					echo '<div class="aac-grants-review__timeline-note">' . wpautop(wp_kses_post($event['note'])) . '</div>';
				}
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';

		if (!empty($application['raw_payload'])) {
			echo '<div class="aac-grants-review__card">';
			echo '<h2>Raw Submission Payload</h2>';
			echo '<pre class="aac-grants-review__pre">' . esc_html(wp_json_encode($application['raw_payload'], JSON_PRETTY_PRINT)) . '</pre>';
			echo '</div>';
		}

		echo '</div>';

		echo '<div class="aac-grants-review__sidebar">';
		echo '<div class="aac-grants-review__card">';
		echo '<h2>Reviewer Actions</h2>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('aac_grants_review_update_application');
		echo '<input type="hidden" name="action" value="aac_grants_review_update_application" />';
		echo '<input type="hidden" name="application_id" value="' . absint($application['id']) . '" />';

		echo '<p><label for="aac-grants-review-assigned"><strong>Assigned reviewer</strong></label></p>';
		echo '<select id="aac-grants-review-assigned" name="assigned_reviewer_id" class="widefat">';
		echo '<option value="0">Unassigned</option>';
		foreach ($reviewers as $reviewer) {
			printf(
				'<option value="%1$d" %3$s>%2$s</option>',
				absint($reviewer->ID),
				esc_html($reviewer->display_name),
				selected(absint($application['assigned_reviewer_id']), absint($reviewer->ID), false)
			);
		}
		echo '</select>';
		echo '<p class="submit"><button type="submit" name="assign_reviewer_only" value="1" class="button button-secondary">Save Reviewer</button></p>';

		if ($transitions) {
			echo '<hr />';
			echo '<p><label for="aac-grants-review-note"><strong>Reviewer note</strong></label></p>';
			echo '<textarea id="aac-grants-review-note" name="note" rows="6" class="widefat" placeholder="Capture context for the next reviewer, note what is missing, or explain the decision."></textarea>';
			echo '<p class="description">Each workflow move writes a timeline entry, so this is a good place for the reasoning we will want later.</p>';
			echo '<div class="aac-grants-review__action-buttons">';
			foreach ($transitions as $status_key) {
				echo '<button type="submit" class="button button-primary" name="next_status" value="' . esc_attr($status_key) . '">' . esc_html($labels[$status_key]) . '</button>';
			}
			echo '</div>';
			echo '<hr />';
			echo '<p><strong>Direct final decision</strong></p>';
			echo '<p class="description">If the reviewer already has enough information, they can approve or reject here without stepping through each intermediate workflow stage.</p>';
			echo '<div class="aac-grants-review__action-buttons">';
			if ($application['workflow_status'] !== AAC_Grants_Review_Repository::STATUS_APPROVED) {
				echo '<button type="submit" class="button button-primary" name="next_status" value="' . esc_attr(AAC_Grants_Review_Repository::STATUS_APPROVED) . '" onclick="this.form.direct_terminal_decision.value=\'1\';">Approve Now</button>';
			}
			if ($application['workflow_status'] !== AAC_Grants_Review_Repository::STATUS_REJECTED) {
				echo '<button type="submit" class="button button-secondary" name="next_status" value="' . esc_attr(AAC_Grants_Review_Repository::STATUS_REJECTED) . '" onclick="this.form.direct_terminal_decision.value=\'1\';">Reject Now</button>';
			}
			echo '</div>';
		} else {
			echo '<p class="description">This application is at a terminal step right now. If it needs another look, use a workflow path that allows reopening from its current status.</p>';
			echo '<input type="hidden" name="direct_terminal_decision" value="0" />';
		}

		if ($transitions) {
			echo '<input type="hidden" name="direct_terminal_decision" value="0" />';
		}

		echo '</form>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	private function render_stat($label, $value) {
		echo '<div class="aac-grants-review__stat"><p class="aac-grants-review__stat-label">' . esc_html($label) . '</p><p class="aac-grants-review__stat-value">' . esc_html($value) . '</p></div>';
	}

	private function render_notice() {
		if (!empty($_GET['settings_saved'])) {
			echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
		}

		if (!empty($_GET['imported'])) {
			echo '<div class="notice notice-success"><p>' . esc_html(wp_unslash($_GET['imported'])) . '</p></div>';
		}

		if (!empty($_GET['updated'])) {
			echo '<div class="notice notice-success"><p>Application updated.</p></div>';
		}

		if (!empty($_GET['import_error'])) {
			echo '<div class="notice notice-error"><p>' . esc_html(wp_unslash($_GET['import_error'])) . '</p></div>';
		}

		if (!empty($_GET['workflow_error'])) {
			echo '<div class="notice notice-error"><p>' . esc_html(wp_unslash($_GET['workflow_error'])) . '</p></div>';
		}
	}

	private function redirect_with_notice($page, $key, $value, $extra_args = []) {
		$args = array_merge(['page' => $page, $key => $value], $extra_args);
		wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
		exit;
	}

	private function redirect_to_requested_location($page, $key, $value, $extra_args = []) {
		$redirect_url = esc_url_raw(wp_unslash($_POST['redirect_url'] ?? ''));
		if ($redirect_url && wp_validate_redirect($redirect_url, false)) {
			$redirect_args = array_merge([$key => $value], $extra_args);
			wp_safe_redirect(add_query_arg($redirect_args, $redirect_url));
			exit;
		}

		$this->redirect_with_notice($page, $key, $value, $extra_args);
	}

	private function get_admin_css() {
		return '
			.aac-grants-review { max-width: 1440px; }
			.aac-grants-review__page-header { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; margin:18px 0 20px; }
			.aac-grants-review__back-link { display:inline-block; margin-bottom:8px; text-decoration:none; }
			.aac-grants-review__layout { display:grid; grid-template-columns:minmax(0, 1.5fr) minmax(300px, 0.72fr); gap:20px; align-items:start; }
			.aac-grants-review__main, .aac-grants-review__sidebar { display:flex; flex-direction:column; gap:20px; }
			.aac-grants-review__card { background:#fff; border:1px solid #dcdcde; border-radius:18px; padding:24px; box-shadow:0 12px 28px rgba(15, 23, 42, 0.06); }
			.aac-grants-review__card h2 { margin-top:0; margin-bottom:14px; }
			.aac-grants-review__stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
			.aac-grants-review__stat { border:1px solid #e5e7eb; border-radius:14px; padding:14px 16px; background:#fafaf9; }
			.aac-grants-review__stat-label, .aac-grants-review__field-label { margin:0 0 6px; font-size:11px; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280; font-weight:700; }
			.aac-grants-review__stat-value { margin:0; font-size:16px; font-weight:700; color:#111827; }
			.aac-grants-review__field-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:14px; }
			.aac-grants-review__field { border:1px solid #ececec; border-radius:14px; padding:14px 16px; background:#fff; }
			.aac-grants-review__field-value { color:#111827; line-height:1.65; white-space:pre-wrap; word-break:break-word; }
			.aac-grants-review__workflow { display:grid; grid-template-columns:repeat(6, minmax(0, 1fr)); gap:10px; margin:0 0 20px; }
			.aac-grants-review__step { border:1px solid #d6d3d1; border-radius:999px; padding:10px 12px; text-align:center; background:#fffaf0; color:#57534e; font-size:12px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; }
			.aac-grants-review__step.is-current { background:#111827; color:#fff; border-color:#111827; }
			.aac-grants-review__step.is-complete { background:#ecfdf3; color:#166534; border-color:#86efac; }
			.aac-grants-review__timeline { display:flex; flex-direction:column; gap:14px; }
			.aac-grants-review__timeline-item { border-left:3px solid #d1d5db; padding-left:14px; }
			.aac-grants-review__timeline-title { font-weight:700; color:#111827; }
			.aac-grants-review__timeline-meta, .aac-grants-review__timeline-status { margin-top:4px; color:#6b7280; font-size:12px; }
			.aac-grants-review__timeline-note { margin-top:8px; color:#1f2937; }
			.aac-grants-review__pre { overflow:auto; max-height:420px; background:#09090b; color:#f8fafc; padding:16px; border-radius:14px; }
			.aac-grants-review__filters { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:18px; }
			.aac-grants-review__filter { display:inline-flex; gap:8px; align-items:center; padding:10px 14px; border-radius:999px; border:1px solid #d6d3d1; background:#fff; text-decoration:none; color:#292524; font-weight:600; }
			.aac-grants-review__filter span { display:inline-flex; align-items:center; justify-content:center; min-width:26px; height:26px; border-radius:999px; background:#f5f5f4; font-size:12px; }
			.aac-grants-review__filter.is-active { border-color:#111827; background:#111827; color:#fff; }
			.aac-grants-review__filter.is-active span { background:rgba(255,255,255,0.14); color:#fff; }
			.aac-grants-review__search { display:flex; gap:10px; margin-bottom:18px; }
			.aac-grants-review__search input[type="search"] { width:360px; max-width:100%; }
			.aac-grants-review__badge { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; background:#f3f4f6; color:#374151; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; }
			.aac-grants-review__badge--active { background:#fef3c7; color:#92400e; }
			.aac-grants-review__badge--approved { background:#dcfce7; color:#166534; }
			.aac-grants-review__badge--warning { background:#fee2e2; color:#b45309; }
			.aac-grants-review__badge--rejected { background:#fee2e2; color:#991b1b; }
			.aac-grants-review__action-buttons { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
			@media (max-width: 1180px) {
				.aac-grants-review__layout { grid-template-columns:1fr; }
				.aac-grants-review__workflow { grid-template-columns:repeat(2, minmax(0, 1fr)); }
			}
		';
	}
}
