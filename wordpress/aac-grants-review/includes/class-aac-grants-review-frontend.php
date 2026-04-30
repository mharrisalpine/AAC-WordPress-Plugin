<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Grants_Review_Frontend {
	const SHORTCODE = 'aac_grants_review_portal';

	private $repository;
	private $settings;

	public function __construct($repository, $settings) {
		$this->repository = $repository;
		$this->settings = $settings;

		add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
	}

	public function render_shortcode() {
		if (!is_user_logged_in()) {
			return $this->wrap_page(
				'<div class="aac-grants-review-portal__card"><h2>Reviewer sign-in required</h2><p>Please sign in with a WordPress account that has grant review access.</p><p><a class="aac-grants-review-portal__button" href="' . esc_url(wp_login_url(get_permalink())) . '">Sign In</a></p></div>'
			);
		}

		if (!$this->user_can_review()) {
			return $this->wrap_page(
				'<div class="aac-grants-review-portal__card"><h2>Access restricted</h2><p>This page is only available to WordPress administrators and volunteer reviewers with grant review access.</p></div>'
			);
		}

		$application_id = absint($_GET['application_id'] ?? 0);
		$content = $application_id > 0
			? $this->render_application_detail($application_id)
			: $this->render_application_list();

		return $this->wrap_page($this->render_notice() . $content);
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
		$total_count = array_sum($counts);
		$active_review_count = absint($counts[AAC_Grants_Review_Repository::STATUS_ELIGIBILITY] ?? 0) + absint($counts[AAC_Grants_Review_Repository::STATUS_COMMITTEE] ?? 0);
		$revision_count = absint($counts[AAC_Grants_Review_Repository::STATUS_NEEDS_REVISION] ?? 0);
		$approved_count = absint($counts[AAC_Grants_Review_Repository::STATUS_APPROVED] ?? 0);
		$unassigned_count = 0;
		foreach ($applications as $application) {
			if (empty($application['assigned_reviewer_id'])) {
				$unassigned_count++;
			}
		}

		ob_start();
		?>
		<div class="aac-grants-review-portal__header">
			<div>
				<p class="aac-grants-review-portal__eyebrow">Volunteer Reviewer Portal</p>
				<h1>Grant Review Queue</h1>
				<p>Review, assign, and decide on grant applications in a simpler front-end workspace.</p>
			</div>
			<div class="aac-grants-review-portal__header-actions">
				<a class="aac-grants-review-portal__button aac-grants-review-portal__button--secondary" href="<?php echo esc_url(admin_url('admin.php?page=aac-grants-review')); ?>">Open wp-admin queue</a>
			</div>
		</div>

		<div class="aac-grants-review-portal__summary-grid">
			<?php echo $this->render_summary_stat('Applications in queue', $total_count, 'All captured submissions in the review workflow.'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $this->render_summary_stat('Active review', $active_review_count, 'Applications currently in eligibility or committee review.'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $this->render_summary_stat('Needs revision', $revision_count, 'Applicants waiting on follow-up or missing details.'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $this->render_summary_stat('Unassigned on this view', $unassigned_count, 'Applications in the current filtered set without a reviewer.'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $this->render_summary_stat('Approved', $approved_count, 'Applications that reached a final approved decision.'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<div class="aac-grants-review-portal__filters">
			<?php echo $this->render_filter_link('', 'All', array_sum($counts)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php foreach ($labels as $key => $label) : ?>
				<?php echo $this->render_filter_link($key, $label, absint($counts[$key] ?? 0), $status); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endforeach; ?>
		</div>

		<form method="get" class="aac-grants-review-portal__search">
			<input type="hidden" name="status" value="<?php echo esc_attr($status); ?>" />
			<input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search applicant, grant, or project title" />
			<button type="submit" class="aac-grants-review-portal__button aac-grants-review-portal__button--secondary">Search</button>
		</form>

		<div class="aac-grants-review-portal__card aac-grants-review-portal__table-card">
			<?php if (!$applications) : ?>
				<p>No applications found for this filter yet.</p>
			<?php else : ?>
				<div class="aac-grants-review-portal__queue-grid">
					<?php foreach ($applications as $application) : ?>
						<?php echo $this->render_application_queue_card($application, $labels); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_application_detail($application_id) {
		$application = $this->repository->get_application($application_id);
		if (!$application) {
			return '<div class="aac-grants-review-portal__card"><h2>Application not found</h2><p><a href="' . esc_url($this->build_page_url()) . '">Back to review queue</a></p></div>';
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

		ob_start();
		?>
		<div class="aac-grants-review-portal__header">
			<div>
				<p class="aac-grants-review-portal__eyebrow">Grant Application</p>
				<a class="aac-grants-review-portal__back-link" href="<?php echo esc_url($this->build_page_url()); ?>">&larr; Back to review queue</a>
				<h1><?php echo esc_html($application['project_title'] ?: ($application['grant_name'] ?: 'Grant Application')); ?></h1>
				<p><?php echo esc_html(($application['source'] ?? '') === 'portal'
					? 'Captured from the AAC member portal grant builder and routed into the volunteer review workflow.'
					: 'Captured from a legacy imported intake record.'); ?></p>
			</div>
			<span class="<?php echo esc_attr(AAC_Grants_Review_Repository::get_status_badge_class($application['workflow_status'])); ?>">
				<?php echo esc_html($labels[$application['workflow_status']] ?? ucfirst($application['workflow_status'])); ?>
			</span>
		</div>

		<div class="aac-grants-review-portal__workflow">
			<?php foreach ($labels as $status_key => $label) : ?>
				<?php
				$is_current = $application['workflow_status'] === $status_key;
				$is_complete = array_search($status_key, array_keys($labels), true) < array_search($application['workflow_status'], array_keys($labels), true);
				$class = 'aac-grants-review-portal__step';
				if ($is_current) {
					$class .= ' is-current';
				} elseif ($is_complete) {
					$class .= ' is-complete';
				}
				?>
				<div class="<?php echo esc_attr($class); ?>"><span><?php echo esc_html($label); ?></span></div>
			<?php endforeach; ?>
		</div>

		<div class="aac-grants-review-portal__layout">
			<div class="aac-grants-review-portal__main">
				<div class="aac-grants-review-portal__card">
					<h2>Overview</h2>
					<div class="aac-grants-review-portal__stats">
						<?php echo $this->render_stat('Applicant', $application['applicant_name'] ?: 'Not mapped yet'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->render_stat('Email', $application['applicant_email'] ?: 'Not mapped yet'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->render_stat('Grant', $application['grant_name'] ?: 'Not mapped yet'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->render_stat('Requested', $application['requested_amount'] ? '$' . number_format((float) $application['requested_amount'], 2) : 'Not mapped yet'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->render_stat('Submitted', mysql2date('M j, Y g:i a', $application['submitted_at'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php
						$assigned_user = !empty($application['assigned_reviewer_id']) ? get_userdata(absint($application['assigned_reviewer_id'])) : null;
						echo $this->render_stat('Reviewer', $assigned_user ? $assigned_user->display_name : 'Unassigned'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</div>
				</div>

				<div class="aac-grants-review-portal__card">
					<h2>Submitted Fields</h2>
					<div class="aac-grants-review-portal__field-grid">
						<?php foreach ($normalized_fields as $field) : ?>
							<div class="aac-grants-review-portal__field">
								<p class="aac-grants-review-portal__field-label"><?php echo esc_html($field['label'] ?: 'Unnamed field'); ?></p>
								<div class="aac-grants-review-portal__field-value"><?php echo nl2br(esc_html($field['value'] ?: '—')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="aac-grants-review-portal__card">
					<h2>Workflow History</h2>
					<?php if (empty($application['history'])) : ?>
						<p>No workflow events yet.</p>
					<?php else : ?>
						<div class="aac-grants-review-portal__timeline">
							<?php foreach ($application['history'] as $event) : ?>
								<div class="aac-grants-review-portal__timeline-item">
									<div class="aac-grants-review-portal__timeline-title"><?php echo esc_html(ucwords(str_replace('_', ' ', $event['action']))); ?></div>
									<div class="aac-grants-review-portal__timeline-meta"><?php echo esc_html($event['actor_name'] . ' · ' . mysql2date('M j, Y g:i a', $event['created_at'])); ?></div>
									<?php if (!empty($event['from_status']) || !empty($event['to_status'])) : ?>
										<div class="aac-grants-review-portal__timeline-status"><?php echo esc_html(($labels[$event['from_status']] ?? 'Start') . ' → ' . ($labels[$event['to_status']] ?? 'Now')); ?></div>
									<?php endif; ?>
									<?php if (!empty($event['note'])) : ?>
										<div class="aac-grants-review-portal__timeline-note"><?php echo wpautop(wp_kses_post($event['note'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="aac-grants-review-portal__sidebar">
				<div class="aac-grants-review-portal__card">
					<h2>Reviewer Actions</h2>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<?php wp_nonce_field('aac_grants_review_update_application'); ?>
						<input type="hidden" name="action" value="aac_grants_review_update_application" />
						<input type="hidden" name="application_id" value="<?php echo absint($application['id']); ?>" />
						<input type="hidden" name="redirect_url" value="<?php echo esc_attr($this->build_page_url(['application_id' => absint($application['id'])])); ?>" />
						<input type="hidden" name="direct_terminal_decision" value="0" />

						<p><label for="aac-grants-review-assigned-front"><strong>Assigned reviewer</strong></label></p>
						<select id="aac-grants-review-assigned-front" name="assigned_reviewer_id" class="aac-grants-review-portal__select">
							<option value="0">Unassigned</option>
							<?php foreach ($reviewers as $reviewer) : ?>
								<option value="<?php echo absint($reviewer->ID); ?>" <?php selected(absint($application['assigned_reviewer_id']), absint($reviewer->ID)); ?>>
									<?php echo esc_html($reviewer->display_name); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p><button type="submit" name="assign_reviewer_only" value="1" class="aac-grants-review-portal__button aac-grants-review-portal__button--secondary">Save Reviewer</button></p>

						<?php if ($transitions) : ?>
							<hr />
							<p><label for="aac-grants-review-note-front"><strong>Reviewer note</strong></label></p>
							<textarea id="aac-grants-review-note-front" name="note" rows="6" class="aac-grants-review-portal__textarea" placeholder="Capture context, note what is missing, or explain the decision."></textarea>
							<p class="aac-grants-review-portal__muted">Every workflow move writes a timeline entry.</p>
							<div class="aac-grants-review-portal__action-buttons">
								<?php foreach ($transitions as $status_key) : ?>
									<button type="submit" class="aac-grants-review-portal__button" name="next_status" value="<?php echo esc_attr($status_key); ?>">
										<?php echo esc_html($labels[$status_key]); ?>
									</button>
								<?php endforeach; ?>
							</div>
							<hr />
							<p><strong>Direct final decision</strong></p>
							<p class="aac-grants-review-portal__muted">Approve or reject immediately without stepping through each stage.</p>
							<div class="aac-grants-review-portal__action-buttons">
								<?php if ($application['workflow_status'] !== AAC_Grants_Review_Repository::STATUS_APPROVED) : ?>
									<button type="submit" class="aac-grants-review-portal__button" name="next_status" value="<?php echo esc_attr(AAC_Grants_Review_Repository::STATUS_APPROVED); ?>" onclick="this.form.direct_terminal_decision.value='1';">Approve Now</button>
								<?php endif; ?>
								<?php if ($application['workflow_status'] !== AAC_Grants_Review_Repository::STATUS_REJECTED) : ?>
									<button type="submit" class="aac-grants-review-portal__button aac-grants-review-portal__button--secondary" name="next_status" value="<?php echo esc_attr(AAC_Grants_Review_Repository::STATUS_REJECTED); ?>" onclick="this.form.direct_terminal_decision.value='1';">Reject Now</button>
								<?php endif; ?>
							</div>
						<?php else : ?>
							<p class="aac-grants-review-portal__muted">This application is currently at a terminal step.</p>
						<?php endif; ?>
					</form>
				</div>

				<?php if (!empty($application['raw_payload'])) : ?>
					<div class="aac-grants-review-portal__card">
						<h2>Raw Submission Payload</h2>
						<pre class="aac-grants-review-portal__pre"><?php echo esc_html(wp_json_encode($application['raw_payload'], JSON_PRETTY_PRINT)); ?></pre>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_filter_link($key, $label, $count, $current_status = '') {
		$class = 'aac-grants-review-portal__filter';
		if (($key === '' && $current_status === '') || $key === $current_status) {
			$class .= ' is-active';
		}

		$url = $this->build_page_url($key === '' ? [] : ['status' => $key]);

		return sprintf(
			'<a class="%1$s" href="%2$s">%3$s <span>%4$d</span></a>',
			esc_attr($class),
			esc_url($url),
			esc_html($label),
			absint($count)
		);
	}

	private function render_stat($label, $value) {
		return '<div class="aac-grants-review-portal__stat"><p class="aac-grants-review-portal__stat-label">' . esc_html($label) . '</p><p class="aac-grants-review-portal__stat-value">' . esc_html($value) . '</p></div>';
	}

	private function render_summary_stat($label, $value, $description = '') {
		return '<div class="aac-grants-review-portal__summary-card"><p class="aac-grants-review-portal__summary-label">' . esc_html($label) . '</p><p class="aac-grants-review-portal__summary-value">' . esc_html((string) $value) . '</p><p class="aac-grants-review-portal__summary-description">' . esc_html($description) . '</p></div>';
	}

	private function render_application_queue_card($application, $labels) {
		$detail_url = $this->build_page_url(['application_id' => absint($application['id'])]);
		$reviewer = !empty($application['assigned_reviewer_id']) ? get_userdata(absint($application['assigned_reviewer_id'])) : null;
		$status_label = $labels[$application['workflow_status']] ?? ucfirst((string) $application['workflow_status']);
		$requested_amount = !empty($application['requested_amount'])
			? '$' . number_format((float) $application['requested_amount'], 2)
			: 'Not provided';

		ob_start();
		?>
		<article class="aac-grants-review-portal__queue-card">
			<div class="aac-grants-review-portal__queue-topline">
				<p class="aac-grants-review-portal__queue-eyebrow"><?php echo esc_html($application['grant_name'] ?: 'Grant application'); ?></p>
				<span class="<?php echo esc_attr(AAC_Grants_Review_Repository::get_status_badge_class($application['workflow_status'])); ?>">
					<?php echo esc_html($status_label); ?>
				</span>
			</div>
			<h2 class="aac-grants-review-portal__queue-title">
				<a href="<?php echo esc_url($detail_url); ?>"><?php echo esc_html($application['project_title'] ?: ($application['grant_name'] ?: 'Untitled application')); ?></a>
			</h2>
			<p class="aac-grants-review-portal__queue-applicant"><?php echo esc_html($application['applicant_name'] ?: $application['applicant_email']); ?></p>
			<p class="aac-grants-review-portal__queue-email"><?php echo esc_html($application['applicant_email'] ?: 'No applicant email mapped yet'); ?></p>
			<div class="aac-grants-review-portal__queue-meta">
				<div class="aac-grants-review-portal__queue-meta-item">
					<p class="aac-grants-review-portal__field-label">Submitted</p>
					<p><?php echo esc_html(mysql2date('M j, Y g:i a', $application['submitted_at'])); ?></p>
				</div>
				<div class="aac-grants-review-portal__queue-meta-item">
					<p class="aac-grants-review-portal__field-label">Requested</p>
					<p><?php echo esc_html($requested_amount); ?></p>
				</div>
				<div class="aac-grants-review-portal__queue-meta-item">
					<p class="aac-grants-review-portal__field-label">Reviewer</p>
					<p><?php echo esc_html($reviewer ? $reviewer->display_name : 'Unassigned'); ?></p>
				</div>
				<div class="aac-grants-review-portal__queue-meta-item">
					<p class="aac-grants-review-portal__field-label">Member ID</p>
					<p><?php echo esc_html($application['aac_member_id'] ?: 'Not mapped'); ?></p>
				</div>
			</div>
			<div class="aac-grants-review-portal__queue-actions">
				<a class="aac-grants-review-portal__button" href="<?php echo esc_url($detail_url); ?>">Open Review</a>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	private function render_notice() {
		if (!empty($_GET['updated'])) {
			return '<div class="aac-grants-review-portal__notice aac-grants-review-portal__notice--success"><p>Application updated.</p></div>';
		}

		if (!empty($_GET['workflow_error'])) {
			return '<div class="aac-grants-review-portal__notice aac-grants-review-portal__notice--error"><p>' . esc_html(wp_unslash($_GET['workflow_error'])) . '</p></div>';
		}

		return '';
	}

	private function wrap_page($content) {
		return '<div class="aac-grants-review-portal">' . $this->get_css() . $content . '</div>';
	}

	private function build_page_url($args = []) {
		$base = get_permalink();
		if (!$base) {
			$base = home_url('/');
		}

		$merged = array_merge($_GET, $args);
		unset($merged['updated'], $merged['workflow_error'], $merged['assign_reviewer_only'], $merged['next_status'], $merged['note'], $merged['application_id']);
		if (isset($args['application_id'])) {
			$merged['application_id'] = absint($args['application_id']);
		}
		if (isset($args['status'])) {
			$merged['status'] = sanitize_key($args['status']);
		}
		if (empty($merged['status'])) {
			unset($merged['status']);
		}
		if (empty($merged['s'])) {
			unset($merged['s']);
		}

		return add_query_arg($merged, $base);
	}

	private function user_can_review() {
		return current_user_can('manage_options') || current_user_can(AAC_Grants_Review_Installer::REVIEW_CAP);
	}

	private function get_css() {
		return '<style>
			.aac-grants-review-portal{max-width:1480px;margin:0 auto;padding:42px 20px 72px;background:linear-gradient(180deg,#0d0d0f 0%,#111214 55%,#17181b 100%);color:#f8fafc}
			.aac-grants-review-portal__header{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin:0 0 24px}
			.aac-grants-review-portal__header h1{margin:6px 0 10px;font-size:clamp(2rem,3vw,3rem);line-height:1.02}
			.aac-grants-review-portal__header p{margin:0;color:#d6d3d1;max-width:760px;line-height:1.7}
			.aac-grants-review-portal__eyebrow{margin:0;font-size:12px;letter-spacing:.22em;text-transform:uppercase;color:#f8c235;font-weight:700}
			.aac-grants-review-portal__back-link{display:inline-block;margin-top:10px;color:#f8c235;text-decoration:none;font-weight:600}
			.aac-grants-review-portal__notice{margin:0 0 18px;padding:14px 16px;border:1px solid rgba(255,255,255,.18);background:#18181b}
			.aac-grants-review-portal__notice p{margin:0;font-weight:600}
			.aac-grants-review-portal__notice--success{border-color:#86efac;background:#ecfdf3;color:#166534}
			.aac-grants-review-portal__notice--error{border-color:#fecaca;background:#fef2f2;color:#991b1b}
			.aac-grants-review-portal__layout{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(300px,.72fr);gap:24px;align-items:start}
			.aac-grants-review-portal__main,.aac-grants-review-portal__sidebar{display:flex;flex-direction:column;gap:24px}
			.aac-grants-review-portal__card{background:#18181b;border:1px solid rgba(255,255,255,.12);padding:24px;box-shadow:0 18px 40px rgba(0,0,0,.28)}
			.aac-grants-review-portal__card h2{margin:0 0 14px;font-size:1.25rem}
			.aac-grants-review-portal__summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:0 0 24px}
			.aac-grants-review-portal__summary-card{border:1px solid rgba(255,255,255,.1);background:#111214;padding:18px 18px 16px}
			.aac-grants-review-portal__summary-label{margin:0;color:#f8c235;font-size:11px;font-weight:700;letter-spacing:.18em;text-transform:uppercase}
			.aac-grants-review-portal__summary-value{margin:12px 0 8px;font-size:2rem;font-weight:800;line-height:1}
			.aac-grants-review-portal__summary-description{margin:0;color:#d6d3d1;font-size:13px;line-height:1.5}
			.aac-grants-review-portal__stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
			.aac-grants-review-portal__stat{border:1px solid rgba(255,255,255,.1);padding:14px 16px;background:#111214}
			.aac-grants-review-portal__stat-label,.aac-grants-review-portal__field-label{margin:0 0 6px;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#a8a29e;font-weight:700}
			.aac-grants-review-portal__stat-value{margin:0;font-size:16px;font-weight:700;color:#f8fafc}
			.aac-grants-review-portal__field-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}
			.aac-grants-review-portal__field{border:1px solid rgba(255,255,255,.08);padding:14px 16px;background:#111214}
			.aac-grants-review-portal__field-value{color:#f8fafc;line-height:1.65;white-space:pre-wrap;word-break:break-word}
			.aac-grants-review-portal__workflow{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin:0 0 24px}
			.aac-grants-review-portal__step{border:1px solid rgba(255,255,255,.1);padding:10px 12px;text-align:center;background:#111214;color:#d6d3d1;font-size:12px;font-weight:700;letter-spacing:.05em;text-transform:uppercase}
			.aac-grants-review-portal__step.is-current{background:#8f1515;color:#fff;border-color:#8f1515}
			.aac-grants-review-portal__step.is-complete{background:#1f3528;color:#d9ffe7;border-color:#305942}
			.aac-grants-review-portal__timeline{display:flex;flex-direction:column;gap:14px}
			.aac-grants-review-portal__timeline-item{border-left:3px solid rgba(248,194,53,.4);padding-left:14px}
			.aac-grants-review-portal__timeline-title{font-weight:700;color:#f8fafc}
			.aac-grants-review-portal__timeline-meta,.aac-grants-review-portal__timeline-status,.aac-grants-review-portal__muted{margin-top:4px;color:#a8a29e;font-size:12px}
			.aac-grants-review-portal__timeline-note{margin-top:8px;color:#e7e5e4}
			.aac-grants-review-portal__pre{overflow:auto;max-height:420px;background:#09090b;color:#f8fafc;padding:16px}
			.aac-grants-review-portal__filters{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px}
			.aac-grants-review-portal__filter{display:inline-flex;gap:8px;align-items:center;padding:10px 14px;border:1px solid rgba(255,255,255,.14);background:#18181b;text-decoration:none;color:#f5f5f4;font-weight:600}
			.aac-grants-review-portal__filter span{display:inline-flex;align-items:center;justify-content:center;min-width:26px;height:26px;background:#111214;font-size:12px}
			.aac-grants-review-portal__filter.is-active{border-color:#8f1515;background:#8f1515;color:#fff}
			.aac-grants-review-portal__filter.is-active span{background:rgba(255,255,255,.14);color:#fff}
			.aac-grants-review-portal__search{display:flex;gap:10px;margin-bottom:18px}
			.aac-grants-review-portal__search input[type="search"],.aac-grants-review-portal__select,.aac-grants-review-portal__textarea{width:100%;border:1px solid rgba(255,255,255,.14);padding:12px 14px;background:#111214;color:#f8fafc}
			.aac-grants-review-portal__textarea{min-height:140px}
			.aac-grants-review-portal__button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 18px;border:1px solid #8f1515;background:#8f1515;color:#fff;text-decoration:none;font-weight:700;cursor:pointer}
			.aac-grants-review-portal__button--secondary{border-color:#f8c235;background:#111214;color:#f8c235}
			.aac-grants-review-portal__action-buttons{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}
			.aac-grants-review-portal__queue-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
			.aac-grants-review-portal__queue-card{display:flex;flex-direction:column;gap:14px;border:1px solid rgba(255,255,255,.08);background:#111214;padding:18px}
			.aac-grants-review-portal__queue-topline{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
			.aac-grants-review-portal__queue-eyebrow{margin:0;font-size:11px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#f8c235}
			.aac-grants-review-portal__queue-title{margin:0;font-size:1.2rem;line-height:1.2}
			.aac-grants-review-portal__queue-title a{color:#f8fafc;text-decoration:none}
			.aac-grants-review-portal__queue-applicant{margin:0;font-size:1rem;font-weight:700;color:#fff}
			.aac-grants-review-portal__queue-email{margin:-8px 0 0;color:#d6d3d1;font-size:.95rem;word-break:break-word}
			.aac-grants-review-portal__queue-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
			.aac-grants-review-portal__queue-meta-item{border-top:1px solid rgba(255,255,255,.08);padding-top:12px}
			.aac-grants-review-portal__queue-meta-item p:last-child{margin:0;color:#f8fafc}
			.aac-grants-review-portal__queue-actions{display:flex;justify-content:flex-start;padding-top:2px}
			.aac-grants-review__badge{display:inline-flex;align-items:center;padding:6px 10px;background:#27272a;color:#f4f4f5;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
			.aac-grants-review__badge--active{background:#3f3008;color:#fef3c7}
			.aac-grants-review__badge--approved{background:#1f3528;color:#dcfce7}
			.aac-grants-review__badge--warning{background:#49220d;color:#fed7aa}
			.aac-grants-review__badge--rejected{background:#4c1214;color:#fecaca}
			@media (max-width:1180px){.aac-grants-review-portal__layout{grid-template-columns:1fr}.aac-grants-review-portal__workflow{grid-template-columns:repeat(2,minmax(0,1fr))}}
			@media (max-width:782px){.aac-grants-review-portal__header,.aac-grants-review-portal__search{flex-direction:column}.aac-grants-review-portal{padding:24px 16px 48px}.aac-grants-review-portal__queue-meta{grid-template-columns:1fr}}
		</style>';
	}
}
