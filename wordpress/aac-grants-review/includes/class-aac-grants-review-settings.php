<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Grants_Review_Settings {
	public function get_portal_settings() {
		if (class_exists('AAC_Member_Portal_Admin') && method_exists('AAC_Member_Portal_Admin', 'get_settings')) {
			return AAC_Member_Portal_Admin::get_settings();
		}

		return get_option('aac_member_portal_settings', []);
	}

	public function get_portal_content() {
		$portal_settings = $this->get_portal_settings();
		return isset($portal_settings['content']) && is_array($portal_settings['content']) ? $portal_settings['content'] : [];
	}

	public function get_portal_grant_form_fields() {
		$content = $this->get_portal_content();
		$fields = isset($content['grant_form_fields']) && is_array($content['grant_form_fields']) ? array_values($content['grant_form_fields']) : [];

		$normalized_fields = array_values(array_filter(array_map(function ($field) {
			if (!is_array($field)) {
				return null;
			}

			$field_key = sanitize_key($field['field_key'] ?? '');
			$label = sanitize_text_field($field['label'] ?? '');
			$type = sanitize_key($field['type'] ?? 'text');
			if (!in_array($type, ['text', 'email', 'number', 'textarea', 'select'], true)) {
				$type = 'text';
			}

			if ($field_key === '' || $label === '') {
				return null;
			}

			return [
				'field_key' => $field_key,
				'label' => $label,
				'type' => $type,
				'required' => !empty($field['required']),
			];
		}, $fields)));

		if (!empty($normalized_fields)) {
			return $normalized_fields;
		}

		return self::get_default_portal_grant_form_fields();
	}

	public function get_portal_grant_opportunities() {
		$content = $this->get_portal_content();
		$opportunities = isset($content['grant_opportunities']) && is_array($content['grant_opportunities']) ? array_values($content['grant_opportunities']) : [];

		return array_values(array_filter(array_map(function ($opportunity) {
			if (!is_array($opportunity)) {
				return null;
			}

			$slug = sanitize_title($opportunity['slug'] ?? '');
			$name = sanitize_text_field($opportunity['name'] ?? '');
			if ($slug === '' || $name === '') {
				return null;
			}

			return [
				'slug' => $slug,
				'name' => $name,
				'category' => sanitize_text_field($opportunity['category'] ?? ''),
				'award' => sanitize_text_field($opportunity['award'] ?? ''),
				'fit' => sanitize_textarea_field($opportunity['fit'] ?? ''),
				'summary' => sanitize_textarea_field($opportunity['summary'] ?? ''),
			];
		}, $opportunities)));
	}

	public function get_portal_grants_builder_admin_url() {
		return admin_url('admin.php?page=aac-member-portal-settings&tab=grants');
	}

	private static function get_default_portal_grant_form_fields() {
		return [
			[
				'field_key' => 'project_title',
				'label' => 'Project Title',
				'type' => 'text',
				'required' => true,
			],
			[
				'field_key' => 'requested_amount',
				'label' => 'Requested Amount',
				'type' => 'number',
				'required' => true,
			],
			[
				'field_key' => 'objective_location',
				'label' => 'Objective / Location',
				'type' => 'text',
				'required' => true,
			],
			[
				'field_key' => 'discipline',
				'label' => 'Discipline',
				'type' => 'select',
				'required' => true,
			],
			[
				'field_key' => 'team_name',
				'label' => 'Partner / Team Name',
				'type' => 'text',
				'required' => false,
			],
			[
				'field_key' => 'summary',
				'label' => 'Project Summary',
				'type' => 'textarea',
				'required' => true,
			],
		];
	}
}
