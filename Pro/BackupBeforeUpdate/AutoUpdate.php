<?php

class Wptc_Backup_Before_Auto_Update {
	protected $config;
	protected $logger;
	protected $backup_before_update_obj;
	protected $backup_id;

	public function __construct() {
		$this->config = WPTC_Pro_Factory::get('Wptc_Backup_Before_Update_Config');
		$this->logger = WPTC_Factory::get('logger');
		$this->backup_before_update_obj = WPTC_Pro_Factory::get('Wptc_Backup_Before_Update');
		$this->backup_id = getTcCookie('backupID');
	}

	public function simulate_fresh_backup_during_auto_update($update_details) {
		dark_debug('Function :','---------'.__FUNCTION__.'-----------------');

		//$update_args = $this->prepare_common_update_format($update_details);
		start_fresh_backup_tc_callback_wptc('manual');
	}

	private function prepare_common_update_format($update_details) {
		if (empty($update_details)) {
			return false;
		}

		if (!empty($update_details) && !empty($update_details->plugin)) {
			$backup_before_update = array(
				0 => $update_details->plugin,
			);
			$update_ptc_type = 'plugin';
		} elseif (!empty($update_details) && !empty($update_details->theme)) {
			$backup_before_update = array(
				0 => $update_details->theme,
			);
			$update_ptc_type = 'theme';
		} elseif (!empty($update_details) && !empty($update_details->response) && $update_details->response == 'autoupdate') {
			$backup_before_update = array(
				0 => $update_details,
			);
			$update_ptc_type = 'core';
		} elseif (!empty($update_details) && !empty($update_details->language)) {
			$backup_before_update = array(
			);
			$update_ptc_type = 'translation';
		}

		$backup_args = array(
			'action' => 'start_fresh_backup_tc_wptc',
			'type' => 'manual',
			'backup_before_update' => $backup_before_update,
			'update_ptc_type' => $update_ptc_type,
			'is_auto_update' => '1',
		);

		return $backup_args;
	}

	public function is_backup_required_before_auto_update() {
		//is_update_required is false sometimes so stop validating that.
		// if ($is_update_required && !$this->config->get_option('started_backup_before_auto_update') && $this->backup_before_update_obj->check_if_update_blocked_always_by_user_setting()) {
		if ($this->backup_before_update_obj->check_if_update_blocked_always_by_user_setting()) {
			return true;
		}
		return false;
	}

	public function is_backup_running_already_for_auto_update() {
		if ($this->config->get_option('started_backup_before_auto_update') && is_any_ongoing_wptc_backup_process() ) {
			return true;
		}
		return false;
	}

	public function do_auto_update_after_backup_wptc($update_details = null) {
		if (!$this->config->get_option('started_backup_before_auto_update')) {
			return false;
		}

		dark_debug(array(), "--------force_running_auto_update--------");

		$this->dev_log_auto_update('Force Running Auto Update.', array());
		$this->force_run_auto_update();
		$this->config->set_option('started_backup_before_auto_update', false);
	}

	public function force_run_auto_update() {
		$serialized_data = $this->config->get_option('auto_update_queue');
		// dark_debug($serialized_data, '---------$serialized_data------------');
		if (empty($serialized_data)) {
			WPTC_Factory::get('logger')->log(__("Auto update data is empty", 'wptc'), 'auto_update_progress', $this->backup_id);
			return false;
		}

		$unserialized_data = unserialize($serialized_data);
		dark_debug($unserialized_data, '---------$unserialized_data------------');
		if (empty($unserialized_data)) {
			WPTC_Factory::get('logger')->log(__("Auto update data is empty", 'wptc'), 'auto_update_progress', $this->backup_id);
			return false;
		}
		$version = $this->get_current_version($unserialized_data['item_type'], $unserialized_data['item']);
		$response = array();
		switch ($unserialized_data['item_type']) {
			case 'plugin':
				$response = $this->backup_before_update_obj->upgrade_plugin_wptc($unserialized_data['item']);
				break;
			case 'theme':
				$response = $this->backup_before_update_obj->upgrade_theme_wptc($unserialized_data['item']);
				break;
			case 'translation':
				$response = $this->backup_before_update_obj->upgrade_translation_wptc($unserialized_data['item']);
				break;
			case 'core':
				$response = $this->backup_before_update_obj->upgrade_core_wptc($unserialized_data['item']);
				break;
			default:
				dark_debug($unserialized_data['item_type'], '---------not matched in force_run_auto_update------------');
				break;
		}
		dark_debug($response, '---------$response------------');
		$this->config->set_option('auto_update_queue', false);
		$this->backup_before_update_obj->process_update_response($unserialized_data['item_type'], $response, $autoupdate = true, $autoupdate_version = $version);
		$this->backup_before_update_obj->parse_bulk_upgrade_response();
	}

	public function get_current_version($type, $data){
		switch ($type) {
			case 'plugin':
				$recent_versions = array_values($data);
				return $recent_versions[0];
			case 'theme':
				$theme_data = array_values($data);
				$slug = $theme_data[0];
				dark_debug($slug, '---------------$slug-----------------');
				$theme_info = wp_get_theme( $slug );
				dark_debug($theme_info, '---------------$theme_info-----------------');
				return $theme_info->get( 'Version' );
			case 'translation':
				return false;
			case 'core':
				global $wp_version;
				return $wp_version;
			default:
				dark_debug(array(), '---------------Could not specify get_current_version-----------------');
				return false;
		}
	}

	public function force_trigger_auto_updates(){
		include_once ABSPATH . 'wp-includes/update.php';
		wp_maybe_auto_update();
	}

	public function auto_update_failed_email_user($data){
		dark_debug($data, '---------------$data-----------------');
		$email = $this->config->get_option('main_account_email');
		$errors = array(
			'type' => 'auto_update_failed',
			'update_info' => $data,
		);

		error_alert_wptc_server($errors);
	}

	public function dev_log_auto_update($type = null, $value = null) {
		if (defined('WPTC_DARK_TEST') && WPTC_DARK_TEST) {
			$usr_time = time();
			if (function_exists('user_formatted_time_wptc')) {
				$usr_time = user_formatted_time_wptc(time());
			}

			try {
				@file_put_contents(WP_CONTENT_DIR . '/DE_cl_dev_log_auto_update.txt', "\n -----$type-------$usr_time------------- " . var_export($value, true) . "\n", FILE_APPEND);
			} catch (Exception $e) {
				@file_put_contents(WP_CONTENT_DIR . '/DE_cl_dev_log_auto_update.txt', "\n -----$type-------$usr_time---------- " . var_export(serialize($value), true) . "\n", FILE_APPEND);
			}
		}
	}
}