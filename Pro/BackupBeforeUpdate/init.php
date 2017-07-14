<?php

include_once 'utils.php';

class Wptc_Backup_Before_Update extends WPTC_Privileges {
	protected $db;
	protected $config;
	protected $logger;
	protected $cron_server_curl;
	protected $upgrade_error_keys;
	protected $upgrade_wp_flow_keys;
	protected $upgrade_success_keys;
	protected $is_bulk_update_request;
	protected $upgrade_ptc_types;
	protected $update_common;

	public function __construct() {
		$this->config = WPTC_Pro_Factory::get('Wptc_Backup_Before_Update_Config');
		$this->logger = WPTC_Factory::get('logger');
		$this->update_common = WPTC_Base_Factory::get('Wptc_Update_Common');
		$this->cron_server_curl = WPTC_Base_Factory::get('Wptc_Cron_Server_Curl_Wrapper');
		$this->is_bulk_update_request = false;
		$this->upgrade_ptc_types = array('plugin' => 'Plugins', 'theme' => 'Themes', 'core' => 'WordPress', 'translation' => 'Translations');
		$this->upgrade_error_keys = array
		(
			'bad_request',
			'fs_unavailable',
			'fs_error',
			'fs_no_root_dir',
			'fs_no_content_dir',
			'fs_no_plugins_dir',
			'fs_no_themes_dir',
			'fs_no_folder',
			'download_failed',
			'no_package',
			'no_files',
			'folder_exists',
			'mkdir_failed',
			'incompatible_archive',
			'files_not_writable',
			'process_failed',
			'remove_old_failed'
		);
		$this->upgrade_wp_flow_keys = array
		(
			'installing_package',
			'maintenance_start',
			'maintenance_end',
			'downloading_package',
			'unpack_package',
			'remove_old'
		);
		$this->upgrade_success_keys = array(
			'up_to_date',
			'process_success'
		);

	}

	public function init() {
		if ($this->is_privileged_feature(get_class($this)) && $this->is_switch_on()) {
			$supposed_hooks_class = get_class($this) . '_Hooks';
			WPTC_Pro_Factory::get($supposed_hooks_class)->register_hooks();
		}
	}

	private function is_switch_on()
	{
		return true;
	}

	public function check_and_initiate_if_update_required_after_backup_wptc($args) {
		if (isset($args['backup_before_update']) && isset($args['update_ptc_type'])) {
			$update_needed = $args['backup_before_update'];
			$update_ptc_type = $args['update_ptc_type'];
			$is_auto_update = $args['is_auto_update'];
			$this->purify_update_req_data_wptc($update_needed, $update_ptc_type, $is_auto_update);
			$this->wptc_backup_before_update_setting();
		}
	}


	public function check_site_alive_after_update(){
		$post_arr = array(
			'event' => 'test_connection_send_email',
			'plugin_version' => WPTC_VERSION,
		);

		$this->cron_server_curl->do_call('users/stats', $post_arr);
	}

	private function purify_update_req_data_wptc($raw_upgrade_details, $update_ptc_type, $is_auto_update) {
		dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);
		if ($update_ptc_type == 'plugin') {
			$upgrade_details = purify_plugin_update_data_wptc($raw_upgrade_details);
		} else if ($update_ptc_type == 'theme') {
			$upgrade_details = purify_theme_update_data_wptc($raw_upgrade_details);
		} else if ($update_ptc_type == 'core') {
			if (!$is_auto_update) {
				$upgrade_details = purify_core_update_data_wptc($raw_upgrade_details);
			} else {
				$upgrade_details = $raw_upgrade_details;
			}
		} else if ($update_ptc_type == 'translation') {
			$upgrade_details = purify_translation_update_data_wptc($raw_upgrade_details);
		}

		$this->update_formated_backup_before_update_details($update_ptc_type, $upgrade_details, $is_auto_update);
	}

	private function update_formated_backup_before_update_details($update_ptc_type, $upgrade_details, $is_auto_update) {
		dark_debug($upgrade_details, '---------upgrade_details-------------');

		$upgrade_details_data['update_items'] = $upgrade_details;
		$upgrade_details_data['updates_type'] = $update_ptc_type;
		$upgrade_details_data['is_auto_update'] = $is_auto_update;
		dark_debug($upgrade_details_data, '---------bacup_before_upgrade_details_data-------------');
		if (!$upgrade_details_data) {
			return false;
		}
		$this->config->set_option('backup_before_update_details', serialize($upgrade_details_data));
	}

	public function wptc_backup_before_update_setting() {
		dark_debug($_POST, '--------wptc_backup_before_update_setting------------');

		if (isset($_POST['backup_before_update_setting'])) {
			$this->update_backup_before_update_setting($_POST['backup_before_update_setting']);
		}
	}

	public function update_backup_before_update_setting($val = null) {
		WPTC_Factory::get('config')->set_option('backup_before_update_setting', $val);
	}

	public function check_if_update_blocked_always_by_user_setting() {
		if ($this->config->get_option('backup_before_update_setting') && ($this->config->get_option('backup_before_update_setting') == 'always')) {
			dark_debug($this->config->get_option('backup_before_update_setting'), "--------check_if_update_blocked_always_by_user_setting--------");
			return true;
		}
		return false;
	}

	public function do_update_after_backup_wptc() {

		$raw_upgrade_details = $this->config->get_option('backup_before_update_details');
		if (empty($raw_upgrade_details)) {
			return false;
		}
		$upgrade_details = unserialize($raw_upgrade_details);
		if (empty($upgrade_details) || !is_array($upgrade_details)) {
			return false;
		}
		dark_debug($upgrade_details, '---------$upgrade_details i want-------------');
		$type_of_update = $upgrade_details['updates_type'];
		$update_items = $upgrade_details['update_items'];
		if (empty($type_of_update) || empty($update_items)) {
			if($type_of_update != 'translation'){
				return false;
			}
		}
		$this->do_upgrade($type_of_update, $update_items, $upgrade_details);
	}

	private function do_upgrade($type_of_update, $update_items, $upgrade_details){

		//Load admin_init hook to get Premium updates
		admin_wp_loaded_wptc();

		if(!$this->config->get_option('is_it_fresh_request')){
			$this->config->set_option('is_it_fresh_request', true);
			$this->config->set_option('upgrade_process_running', false);
			send_response_wptc('NEED_FRESH_REQUEST_TO_PROCESS_UPDATES', 'BACKUP');
		}
		dark_debug($type_of_update, '---------$type_of_update-------------');
		$this->config->set_option('backup_before_update_progress', $type_of_update);
		@set_time_limit(0);
		$need_more_server_request = $this->remove_a_item_from_update_list($upgrade_details, $type_of_update);
		dark_debug($need_more_server_request, '--------$need_more_server_request--------');
		if ($type_of_update == 'plugin') {
			$first_plugin_value = reset($update_items);
			$first_plugin_key = key($update_items);
			$update_items = array($first_plugin_key => $first_plugin_value);
			dark_debug($update_items, '--------$update_items after get only one--------');
			if ($this->update_common->is_free_plugin($first_plugin_key)) {
				$response = $this->upgrade_plugin_wptc($update_items);
			} else {
				$this->config->set_option('upgrade_process_running', false);
				send_response_wptc('Paid plugin skipped', 'BACKUP');
			}
		} else if ($type_of_update == 'theme') {
			$first_plugin_value = reset($update_items);
			$first_plugin_key = key($update_items);
			$update_items = array($first_plugin_value);
			dark_debug($update_items, '--------$update_items after get only one THEMES--------');
			if ($this->update_common->is_free_theme($first_plugin_value)) {
				$response = $this->upgrade_theme_wptc($update_items);
			} else {
				$this->config->set_option('upgrade_process_running', false);
				send_response_wptc('Paid theme skipped', 'BACKUP');
			}
		} else if ($type_of_update == 'core') {
			$response = $this->upgrade_core_wptc($update_items);
		} else if ($type_of_update == 'translation') {
			$response = $this->upgrade_translation_wptc($update_items);
		}
		dark_debug($response, '---------$response------------');
		$this->process_update_response($type_of_update, $response);
		if ($need_more_server_request && is_wptc_server_req()) {
			dark_debug(array(), '--------NEED MORE CALLS FROM SERVER--------');
			$this->config->set_option('upgrade_process_running', false);
			send_response_wptc('SEND_ANOTHER_REQUESTS_TO_PERFORM_'.strtoupper($type_of_update).'S_UPDATE', 'BACKUP');
		}
		$this->parse_bulk_upgrade_response();
		$this->config->set_option('backup_before_update_progress', false);
		$this->config->set_option('is_it_fresh_request', false);
	}

	private function remove_a_item_from_update_list($upgrade_details, $type_of_update){
		dark_debug($upgrade_details, '--------$upgrade_details remove_a_item_from_update_list--------');
		if ($type_of_update == 'plugin') {
			if(count($upgrade_details['update_items']) > 1){
				dark_debug(array(), '--------GREATER THAN 1--------');
				reset($upgrade_details['update_items']);
				$first_key = key($upgrade_details['update_items']);
				unset($upgrade_details['update_items'][$first_key]);
				dark_debug($upgrade_details, '--------$upgrade_details aftet unset--------');
				$this->config->set_option('backup_before_update_details', serialize($upgrade_details));
				return true;
			}
			dark_debug(array(), '--------LESS THAN 1--------');
			$this->config->set_option('backup_before_update_details', false);
		} else if ($type_of_update == 'theme') {
			if(count($upgrade_details['update_items']) > 1){
				reset($upgrade_details['update_items']);
				dark_debug(array(), '--------GREATER THAN 1--------');
				$first_key = key($upgrade_details['update_items']);
				unset($upgrade_details['update_items'][$first_key]);
				dark_debug($upgrade_details, '--------$upgrade_details aftet unset--------');
				$this->config->set_option('backup_before_update_details', serialize($upgrade_details));
				return true;
			}
			dark_debug(array(), '--------LESS THAN 1--------');
			$this->config->set_option('backup_before_update_details', false);
		} else if ($type_of_update == 'core') {
			$this->config->set_option('backup_before_update_details', false);
			// $response = $this->upgrade_core_wptc($update_items);
		} else if ($type_of_update == 'translation') {
			$this->config->set_option('backup_before_update_details', false);
			// $response = $this->upgrade_translation_wptc($update_items);
		}

		return false;
	}

	public function parse_bulk_upgrade_response(){
		dark_debug(array(), '--------Parse parse_bulk_upgrade_response--------');

		if ($this->config->get_option('is_bulk_update_request') && $this->is_bulk_update_request === false) {
			dark_debug(array(), '--------Comes 1--------');
			$this->config->set_option('upgrade_process_running', false);
			send_response_wptc('I_HAVE_SOME_MORE_BULK_UPDATES', 'BACKUP');
		}

		$raw_data = $this->config->get_option('update_response_details');
		dark_debug($raw_data, '--------$raw_data update_response_details--------');
		if (empty($raw_data)) {
			return false;
		}

		$upgrade_responses = unserialize($raw_data);
		// $ptc_type = $upgrade_responses['ptc_type'];
		$update_type = $upgrade_responses['update_type'];

		//set notice content
		$this->set_bbu_notice_view($upgrade_responses, $update_type);

		//set backup name
		$this->set_bbu_backup_name($upgrade_responses, $update_type);

		//Map update resulst with current backup
		$this->map_update_results_with_backup($upgrade_responses);

	}

	private function set_bbu_notice_view($upgrade_responses, $update_type){

		if ($this->is_bulk_update_request === true) { //IWP requested updates never need notice
			return false;
		}

		if ($update_type === 'auto') { //Auto updates never needs specific notice views.
			return false;
		}

		$total_upgrades = $success = $failed = 0;
		$ptc_type = '';

		if (!empty($upgrade_responses['plugin'])) {
			foreach ($upgrade_responses['plugin'] as $response) {
				$ptc_type = $this->upgrade_ptc_types['plugin'];
				$total_upgrades++;
				if ($response['status'] === 'success') {
					$success++;
				} else {
					$failed++;
				}
			}
		} else if (!empty($upgrade_responses['theme'])) {
			foreach ($upgrade_responses['theme'] as $response) {
				$ptc_type = $this->upgrade_ptc_types['theme'];
				$total_upgrades++;
				if ($response['status'] === 'success') {
					$success++;
				} else {
					$failed++;
				}
			}
		} else if (!empty($upgrade_responses['core'])) {
			foreach ($upgrade_responses['core'] as $response) {
				$ptc_type = $this->upgrade_ptc_types['core'];
				$total_upgrades++;
				if ($response['status'] === 'success') {
					$success++;
				} else {
					$failed++;
				}
			}
		} else if (!empty($upgrade_responses['translations'])) {
			foreach ($upgrade_responses['theme'] as $response) {
				$ptc_type = $this->upgrade_ptc_types['translation'];
				$total_upgrades++;
				if ($response['status'] === 'success') {
					$success++;
				} else {
					$failed++;
				}
			}
		}

		dark_debug($total_upgrades, '--------$total_upgrades--------');
		dark_debug($success, '--------$success--------');
		dark_debug($failed, '--------$failed--------');
		dark_debug($ptc_type, '--------$ptc_type--------');
		if ($total_upgrades > 1) {
				if ($success === $total_upgrades) {
						$this->config->set_option('bbu_note_view', serialize(array('type' => 'success', 'note' => $total_upgrades .' '.$ptc_type.' updated successfully :)')));
				} else if ($failed === $total_upgrades) {
						$this->config->set_option('bbu_note_view', serialize(array('type' => 'error', 'note' => $total_upgrades .' '.$ptc_type.' updates failed.')));
				} else {
						$this->config->set_option('bbu_note_view', serialize(array('type' => 'warning', 'note' => $success.' '.$ptc_type.' updated successfully and '.$failed.' plugin updates failed.')));
				}
		} else {
			if ($success) {
				if ($ptc_type === 'Plugins' || $ptc_type ===  'Themes') {
					$ptc_type = $response['name'];
				}
				$this->config->set_option('bbu_note_view', serialize(array('type' => 'success', 'note' => $ptc_type.' updated successfully :)')));
			} else {
				if ($ptc_type === 'Plugins' || $ptc_type ===  'Themes') {
					$ptc_type = $response['name'];
				}
				$this->config->set_option('bbu_note_view', serialize(array('type' => 'error', 'note' => $ptc_type.' upgrade failed.')));;
			}
		}
	}

	private function set_bbu_backup_name($upgrade_responses, $update_type){

		$total_upgrades = 0;
		$name  = $backup_name = '';
		if (!empty($upgrade_responses['plugin'])) {
			foreach ($upgrade_responses['plugin'] as $response) {
				$total_upgrades++;
				if ($name) {
					if ($response['version']) {
						$name .= ', '.$response['name']. '-'.$response['version'];
					} else {
						$name .= ', '.$response['name'];
					}
				} else {
					if ($response['version']) {
						$name .= $response['name']. '-'.$response['version'];
					} else {
						$name .= $response['name'];;
					}
				}
			}
		}

		if ($name) {
			if ($total_upgrades > 1) {
				$backup_name = $total_upgrades. ' '. $this->upgrade_ptc_types['plugin'] . ' ( '.$name.' ) ';
			} else {
				$backup_name = $total_upgrades. ' Plugin ( '.$name.' ) ';
			}
		}

		$total_upgrades = 0;
		$name = '';
		if (!empty($upgrade_responses['theme'])) {
			foreach ($upgrade_responses['theme'] as $response) {
				$total_upgrades++;
				if ($name) {
					if ($response['version']) {
						$name .= ', '.$response['name']. '-'.$response['version'];
					} else {
						$name .= ', '.$response['name'];
					}
				} else {
					if ($response['version']) {
						$name .= $response['name']. '-'.$response['version'];
					} else {
						$name .= $response['name'];
					}
				}
			}
		}
		dark_debug($total_upgrades, '--------$total_upgrades--------');
		if ($name) {
			if ($backup_name) {
				if ($total_upgrades > 1) {
					$backup_name .= ', ' . $total_upgrades. ' '. $this->upgrade_ptc_types['theme'] . ' ( '.$name.' ) ';
				} else {
					$backup_name .= ', ' . $total_upgrades. ' Theme ( '.$name.' ) ';
				}
			} else {
				if ($total_upgrades > 1) {
					$backup_name = $total_upgrades. ' '. $this->upgrade_ptc_types['theme'] . ' ( '.$name.' ) ';
				} else {
					$backup_name .= $total_upgrades. ' Theme ( '.$name.' ) ';
				}
			}
		}


		if (!empty($upgrade_responses['core'] ) ) {
			if ($backup_name) {
				if ($upgrade_responses['core']['version']) {
					$backup_name .= ', ' . $this->upgrade_ptc_types['core'] . '-'.$upgrade_responses['core']['version'];
				} else {
					$backup_name .= ', ' . $this->upgrade_ptc_types['core'];
				}
			} else {
				if ($upgrade_responses['core']['version']) {
					$backup_name = $this->upgrade_ptc_types['core'] . '-'.$upgrade_responses['core']['version'];
				} else {
					$backup_name = $this->upgrade_ptc_types['core'];
				}
			}
		}

		if (!empty($upgrade_responses['translation'] ) ) {
			if ($backup_name) {
				$backup_name .= ', ' . $this->upgrade_ptc_types['translation'];
			} else {
				$backup_name = $this->upgrade_ptc_types['translation'];
			}
		}

		dark_debug($backup_name, '--------$backup_name--------');
		if($update_type === 'manual'){
			if ($this->config->get_option('is_vulns_updates')) {
				store_backup_name_wptc($backup_name .' updated via vulnerable updates.');
			} else {
				store_backup_name_wptc($backup_name .' updated manually.');
			}
		} else{
			store_backup_name_wptc($backup_name .' auto updated.');
		}
	}

	private function map_update_results_with_backup($upgrade_responses){
		$processed_files = WPTC_Factory::get('processed-files', true);
		$processed_files->save_PTC_update_response($upgrade_responses);
	}

	public function process_update_response($type_of_update, $response, $autoupdate = false, $autoupdate_version = false){
		$backup_id = getTcCookie('backupID');
		$this->check_site_alive_after_update();
		$update_type = ($autoupdate) ? 'auto' : 'manual';
		$extra_note = ($autoupdate) ? ' (AU) - ' : '';

		if($autoupdate){
			$this->config->set_option('bbu_note_view', serialize(array('type' => 'message', 'note' => 'WPTC performed some auto-updates, check Activity log for more info.')));
		}
		if (empty($response)) {
			if($autoupdate){
				$this->turn_off_auto_update_and_send_email("Upgrade failed", $type_of_update, $backup_id);
			} else{
				$this->update_backup_name_wptc($type_of_update, '', false, $update_type, false, 'Upgrade failed.');
			}

			return $this->logger->log($extra_note.' '. $this->upgrade_ptc_types[$type_of_update] .' '. "Upgrade failed .", 'backup_progress', $backup_id);

		} else if(isset($response['error'])){
			if($autoupdate){
				$this->turn_off_auto_update_and_send_email($response['error'], $type_of_update, $backup_id);
			} else{
				$this->update_backup_name_wptc($type_of_update, '', false, $update_type, false ,$response['error']);
			}

			return $this->logger->log($extra_note.' '. $this->upgrade_ptc_types[$type_of_update] .' '. " Upgrade failed - Reason - ". $response['error'], 'backup_progress', $backup_id);
		}

		if ($type_of_update == 'plugin') {

			if (!isset($response['upgraded'])) {
				return $this->logger->log($extra_note. "Updating plugin failed - response is broken", 'backup_progress', $backup_id);
			}

			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugins_data = get_plugins();
			$update_status =  $err_msg =  false;
			foreach ($response['upgraded'] as $key => $value) {

				$name = $plugins_data[$key]['Name'];
				$version = $plugins_data[$key]['Version'];

				if ($value === 1) {
					$this->logger->log($extra_note. "Plugin ".$name. " updated successfully", 'backup_progress', $backup_id);
					$update_status = true;
				} else {
					$this->logger->log($extra_note. "Plugin ".$name . ' update failed - '.$value['error'], 'backup_progress', $backup_id);
					$err_msg = $value['error'];
					$update_status = false;
				}

				//save response data
				$this->update_backup_name_wptc('plugin', $name, $update_status, $update_type, $version, $err_msg);

				//extra check step after update if version mismatch then turn off auto update
				if ($autoupdate) {
					if(!$this->verify_auto_update_plugin($autoupdate_version, $version)){
						$this->turn_off_auto_update_and_send_email("Plugin ".$name . " update - Version Mismatched", $name. ' - v'.$version, $backup_id);
					}
				}

			}

		} else if($type_of_update == 'theme'){
			if (!isset($response['upgraded'])) {
				return $this->logger->log($extra_note. "Updating theme failed - response is broken", 'backup_progress', $backup_id);
			}

			$update_status =  $err_msg =  false;
			foreach ($response['upgraded'] as $key => $value) {
				$theme_info = wp_get_theme($key);
				if (!empty($theme_info)) {
					$name = $theme_info->get( 'Name' );
					$version = $theme_info->get( 'Version' );
				} else {
					$name = 'theme';
					$version = 0;
				}

				if ($value === 1) {
					$this->logger->log($extra_note. "Theme " . $name ." updated successfully", 'backup_progress', $backup_id);
					$update_status = true;
				} else{
					$this->logger->log($extra_note. "Theme " . $name . ' update failed - '.$value['error'], 'backup_progress', $backup_id);
					$update_status = false;
					$err_msg = $value['error'];
				}

				//save response data
				$this->update_backup_name_wptc('theme', $name, $update_status, $update_type, $version, $err_msg);

				if ($autoupdate) {
					if(!$this->verify_auto_update_plugin($autoupdate_version, $version)){
						$this->turn_off_auto_update_and_send_email("Theme ".$name . " update - Version Mismatched", $name. ' - v'. $version, $backup_id);
					}
				}
			}

		} else if ($type_of_update == 'core') {

			$update_status = $err_msg = false;
			if (isset($response['upgraded'])) {
				$update_status = true;
				$this->logger->log($extra_note. "WordPress updated successfully", 'backup_progress', $backup_id);
			} else {
				$this->logger->log($extra_note. "Updating WordPress failed - ". $response['error'], 'backup_progress', $backup_id);
				$update_status = false;
				$err_msg = $response['error'];
			}

			//save response data
			$this->update_backup_name_wptc('core', 'WordPress', $update_status, $update_type, get_bloginfo('version'), $err_msg);

			if(!$autoupdate){
				if(!$this->verify_auto_update_plugin($autoupdate_version, get_bloginfo('version'))){
					$this->turn_off_auto_update_and_send_email("WordPress update - Version Mismatched", 'WordPress - v'.get_bloginfo('version'), $backup_id);
				}
			}

		} else if ($type_of_update == 'translation') {

			$update_status = $err_msg = false;
			if (isset($response['upgraded'])) {
				$update_status = true;
				$this->logger->log($extra_note. "Translations updated successfully", 'backup_progress', $backup_id);
			} else {
				$this->logger->log($extra_note. "Updating translation failed - ". $response['error'], 'backup_progress', $backup_id);
				if($autoupdate){
					$this->turn_off_auto_update_and_send_email("Translation update failed - ". $response['error'] , 'Translations', $backup_id);
				}
				$update_status = false;
				$err_msg = $response['error'];
			}

			//save response data
			$this->update_backup_name_wptc('translation', 'Translations', $update_status, $update_type, false, $err_msg);

		}
	}

	private function update_backup_name_wptc($ptc_type, $ptc_name, $update_result, $update_type, $version = false, $err_msg = false){

		//perform a structure for cummulative results
		$raw_update_response_details = $this->config->get_option('update_response_details');
		if (empty($raw_update_response_details)) {
			//first update
			$update_response_details['update_type'] = $update_type;
		} else {
			$update_response_details = unserialize($raw_update_response_details);
		}
		$update_response_details[$ptc_type][] = array(
			'name' => $ptc_name,
			'version' => $version,
			'status' => empty($update_result) ? 'failed' : 'success',
			'err_msg' => $err_msg,
			);

		$this->config->set_option('update_response_details', serialize($update_response_details));
	}

	private function turn_off_auto_update_and_send_email($log_msg, $email_content, $backup_id){
		$this->logger->log($log_msg, 'backup_progress', $backup_id);
		do_action('turn_off_auto_update_wptc', time());
		do_action('auto_update_failed_email_user_wptc', $email_content);
		$this->logger->log("Auto update turned off", 'backup_progress', $backup_id);
	}

	private function verify_auto_update_plugin($old_version, $updated_version){
		dark_debug(__FUNCTION__, '----function name------');
		dark_debug(func_get_args(), '---------------arguments-----------------');
		return (version_compare( $old_version, $updated_version, '=' )) ? false : true;
	}

	public function upgrade_plugin_wptc($plugins, $plugin_details = false) {

		if (!$plugins || empty($plugins)) {
			return array(
				'error' => 'No plugin files for upgrade.',
			);
		}

		if (!is_server_writable_wptc()) {
			return $this->get_server_not_writable_arr();
		}

		@include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		@include_once ABSPATH . 'wp-admin/includes/plugin.php';

		if (!function_exists('wp_update_plugins')) {
			include_once(ABSPATH . 'wp-includes/update.php');
		}

		if (!class_exists('Plugin_Upgrader')) {
			@ob_end_clean();
			return array(
				'error' => 'WordPress update required first.'
			);
		}


		@wp_update_plugins();

		$upgrader_skin = new WPTC_Updater_TraceableUpdaterSkin();
		$upgrader = new Plugin_Upgrader($upgrader_skin);
		$result = $upgrader->bulk_upgrade(array_keys($plugins));
		dark_debug($upgrader_skin->get_upgrade_messages(), '--------$upgrader_skin PLUGIN--------');
		if (empty($result)) {
			return array(
				'error' => 'Upgrade failed .'
			);
		}

		$return = array();
		foreach ($result as $plugin_slug => $plugin_info) {
			if (!$plugin_info || is_wp_error($plugin_info)) {
				$return[$plugin_slug] = array('error' => $this->parse_upgrade_response($upgrader_skin->get_upgrade_messages()));
				continue;
			}

			$return[$plugin_slug] = 1;
		}

		return array(
			'upgraded' => $return,
		);
	}

	private function get_server_not_writable_arr(){
		return array(
			'error' => 'Failed, Server is not writable, please <a target="_blank" href="http://docs.wptimecapsule.com/article/24-why-does-plugins-themes-fail-to-update">add FTP details</a>',
		);
	}


	public function upgrade_theme_wptc($themes, $theme_details = false) {

		if (!$themes || empty($themes)) {
			return array(
				'error' => 'No theme files for upgrade.',
			);
		}

		if (!is_server_writable_wptc()) {
			return $this->get_server_not_writable_arr();
		}

		@include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		@include_once ABSPATH . 'wp-admin/includes/theme.php';

		if (!function_exists('wp_update_themes')) {
			include_once ABSPATH . 'wp-includes/update.php';
		}

		@wp_update_themes();

		if (!class_exists('Theme_Upgrader')) {
			@ob_end_clean();
			return array(
				'error' => 'WordPress update required first'
			);
		}

		$upgrader_skin = new WPTC_Updater_TraceableUpdaterSkin();
		$upgrader = new Theme_Upgrader($upgrader_skin);
		$result = $upgrader->bulk_upgrade($themes);

		dark_debug($upgrader_skin, '--------$upgrader_skin--------');
		dark_debug($upgrader_skin->get_upgrade_messages(), '-------feed back of skinn--------');

		$return  = array();
		if (empty($result)) {
			return array(
				'error' => 'Upgrade failed.',
			);
		}

		foreach ($result as $theme_tmp => $theme_info) {
			if (is_wp_error($theme_info) || empty($theme_info)) {
				$return[$theme_tmp] = array('error' => $this->parse_upgrade_response($upgrader_skin->get_upgrade_messages()));
				continue;
			}

			$return[$theme_tmp] = 1;
		}

		return array(
			'upgraded' => $return,
		);
	}

	public function upgrade_core_wptc($current) {

		dark_debug($current, '--------$current--------');
		$current = (array) $current;
		dark_debug($current, '--------$current--------');

		if (!$current || empty($current)) {
			return array(
				'error' => 'No core data for upgrade.',
			);
		}

		if (!is_server_writable_wptc()) {
			return $this->get_server_not_writable_arr();
		}

		ob_start();

		if (file_exists(ABSPATH.'/wp-admin/includes/update.php')) {
			include_once ABSPATH.'/wp-admin/includes/update.php';
		}

		$current_update = false;
		ob_end_flush();
		ob_end_clean();
		$core = wptc_mmb_get_transient('update_core');
		dark_debug($core, '--------$core--------');
		if (isset($core->updates) && !empty($core->updates)) {
			$updates = $core->updates[0];
			$updated = $core->updates[0];
			if (!isset($updated->response) || $updated->response == 'latest') {
				return array(
					'upgraded' => ' updated',
				);
			}

			if ($updated->response == "development" && $current['response'] == "upgrade") {
				return array(
					'error' => '<font color="#900">Unexpected error. Please upgrade manually.</font>',
				);
			} else {
				if ($updated->response == $current['response'] || ($updated->response == "upgrade" && $current['response'] == "development")) {
					if ($updated->locale != $current['locale']) {
						foreach ($updates as $update) {
							if ($update->locale == $current['locale']) {
								$current_update = $update;
								break;
							}
						}
						if ($current_update == false) {
							return array(
								'error' => ' Localization mismatch. Try again.',
							);
						}
					} else {
						$current_update = $updated;
					}
				} else {
					return array(
						'error' => ' Transient mismatch. Try again.',
					);
				}
			}
		} else {
			return array(
				'error' => ' Refresh transient failed. Try again.',
			);
		}
		if ($current_update != false) {
			global $wp_filesystem, $wp_version;

			if (version_compare($wp_version, '3.1.9', '>')) {
				if (!class_exists('Core_Upgrader')) {
					include_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
				}

				/** @handled class */
				$upgrader_skin = new WPTC_Updater_TraceableUpdaterSkin();
				$core 	= new Core_Upgrader($upgrader_skin);
				$result = $core->upgrade($current_update);
				dark_debug($upgrader_skin->get_upgrade_messages(), '--------$upgrader_skin CORE--------');
				wptc_mmb_maintenance_mode(false);
				if (is_wp_error($result)) {
					return array(
						'error' => $this->parse_upgrade_response($upgrader_skin->get_upgrade_messages()),
					);
				} else {
					return array(
						'upgraded' => ' updated',
					);
				}
			} else {
				if (!class_exists('WP_Upgrader')) {
					include_once ABSPATH.'wp-admin/includes/update.php';
					if (function_exists('wp_update_core')) {
						$result = wp_update_core($current_update);
						if (is_wp_error($result)) {
							return array(
								'error' => wptc_mmb_get_error($result),
							);
						} else {
							return array(
								'upgraded' => ' updated',
							);
						}
					}
				}

				if (class_exists('WP_Upgrader')) {
					/** @handled class */
					$upgrader_skin              = new WP_Upgrader_Skin();
					$upgrader_skin->done_header = true;

					/** @handled class */
					$upgrader = new WP_Upgrader($upgrader_skin);

					// Is an update available?
					if (!isset($current_update->response) || $current_update->response == 'latest') {
						return array(
							'upgraded' => ' updated',
						);
					}

					$res = $upgrader->fs_connect(
						array(
							ABSPATH,
							WP_CONTENT_DIR,
						)
					);
					if (is_wp_error($res)) {
						return array(
							'error' => wptc_mmb_get_error($res),
						);
					}

					$wp_dir = trailingslashit($wp_filesystem->abspath());

					$core_package = false;
					if (isset($current_update->package) && !empty($current_update->package)) {
						$core_package = $current_update->package;
					} elseif (isset($current_update->packages->full) && !empty($current_update->packages->full)) {
						$core_package = $current_update->packages->full;
					}

					$download = $upgrader->download_package($core_package);
					if (is_wp_error($download)) {
						return array(
							'error' => wptc_mmb_get_error($download),
						);
					}

					$working_dir = $upgrader->unpack_package($download);
					if (is_wp_error($working_dir)) {
						return array(
							'error' => wptc_mmb_get_error($working_dir),
						);
					}

					if (!$wp_filesystem->copy($working_dir.'/wordpress/wp-admin/includes/update-core.php', $wp_dir.'wp-admin/includes/update-core.php', true)) {
						$wp_filesystem->delete($working_dir, true);

						return array(
							'error' => 'Unable to move update files.',
						);
					}

					$wp_filesystem->chmod($wp_dir.'wp-admin/includes/update-core.php', FS_CHMOD_FILE);

					require ABSPATH.'wp-admin/includes/update-core.php';

					$update_core = update_core($working_dir, $wp_dir);
					ob_end_clean();

					$this->wptc_mmb_maintenance_mode(false);
					if (is_wp_error($update_core)) {
						return array(
							'error' => wptc_mmb_get_error($update_core),
						);
					}
					ob_end_flush();

					return array(
						'upgraded' => 'updated',
					);
				} else {
					return array(
						'error' => 'failed',
					);
				}
			}
		} else {
			return array(
				'error' => 'failed',
			);
		}
	}

	public function upgrade_translation_wptc($data = false) {

		@include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		@include_once ABSPATH . 'wp-admin/includes/file.php';
		@include_once ABSPATH . 'wp-admin/includes/misc.php';
		@include_once ABSPATH . 'wp-admin/includes/template.php';
		@include_once ABSPATH . 'wp-admin/includes/plugin.php';
		@include_once ABSPATH . 'wp-admin/includes/theme.php';

		if (!function_exists('wp_version_check') || !function_exists('get_core_checksums')) {
			include_once ABSPATH . '/wp-admin/includes/update.php';
		}

		if (!class_exists('Language_Pack_Upgrader')) {
			return array(
				'error' => 'WordPress update required first',
			);
		}

		if (!is_server_writable_wptc()) {
			return $this->get_server_not_writable_arr();
		}

		/** @handled class */
		$upgrader_skin = new WPTC_Updater_TraceableUpdaterSkin();
		$upgrader = new Language_Pack_Upgrader($upgrader_skin);
		$result = $upgrader->bulk_upgrade();
		dark_debug($upgrader_skin->get_upgrade_messages(), '--------$upgrader_skin Translations--------');
		if (empty($result)) {
			return array(
				'error' => 'Upgrade failed.',
			);
		}

		$return = 1;
		foreach ($result as $translate_tmp => $translate_info) {
			if (is_wp_error($translate_info) || empty($translate_info)) {
				$return = array('error' => $this->parse_upgrade_response($upgrader_skin->get_upgrade_messages()));
				break;
			}
		}

		return array('upgraded' => $return);
	}

	public function parse_upgrade_response($response = null , $parse_error = true){
		dark_debug($response, '--------$response--------');
		$error_message = '';
		foreach ($response as $key => $message) {

			if(in_array($message['key'], $this->upgrade_wp_flow_keys) !== false){
				dark_debug($message['key'], '--------WP FLOW SKIPPED--------');
				continue;
			}

			if(in_array($message['key'], $this->upgrade_success_keys) !== false){
				dark_debug($message['key'], '--------SUCCESS KEYS SKIPPED--------');
				continue;
			}

			if(in_array($message['key'], $this->upgrade_error_keys) !== false){
				dark_debug($message['key'], '--------FOUND ERROR 1 FOR SURE--------');
				$error_message = $message['key'];
				continue;
			}
			dark_debug($message['key'], '--------FOUND ERROR BECAUSE ITS NEW--------');
			$error_message = $message['key'];
			continue;
		}

		if ($parse_error) {
			return !empty($error_message) ? $error_message : 'Could not find error from response : ' . serialize($response);
		}
	}

	public function handle_iwp_update_request($upgrade_details){
		dark_debug($upgrade_details, '--------$upgrade_details handle_iwp_update_request--------');

		if($this->config->get_option('backup_before_update_setting') !== 'always'){
			dark_debug(array(), '--------USER not enabled BBU as always--------');
			return false;
		}

		if(!WPTC_Base_Factory::get('Wptc_App_Functions')->is_user_purchased_this_class('Wptc_Backup_Before_Update')){
			dark_debug(array(), '--------User is not purchased BBU--------');
			return false;
		}

		if (is_any_other_wptc_process_going_on()) {
			return array(
				'error' => 'WP Time Capsule is running a task in your site. Please try again once its finished.',
				'error_code' => 'WPTC_BUSY_WITH_ANOTHER_TASK'
				);
		}

		if (is_any_ongoing_wptc_backup_process()){
			return array(
				'error' => 'WP Time Capsule is backing up your site. Please try again once its finished.',
				'error_code' => 'WPTC_BUSY_WITH_ANOTHER_BACKUP'
				);
		}

		if (is_any_ongoing_wptc_restore_process()){
			return array(
				'error' => 'WP Time Capsule is restoring your site. Please try again once its finished.',
				'error_code' => 'WPTC_BUSY_WITH_RESTORE'
				);
		}

		$upgrade_plugins = array();

		if (!empty($upgrade_details['upgrade_plugins'])) {
			foreach ($upgrade_details['upgrade_plugins'] as $key => $plugin) {
				dark_debug($plugin, '--------$plugin--------');
				if (!isset($plugin['file'])) {
					$upgrade_plugins[$plugin['slug']] = $plugin['new_version'];
				} else {
					$upgrade_plugins[$plugin['file']] = $plugin['new_version'];
				}
			}
		}

		$upgrade_themes = array();

		if (!empty($upgrade_details['upgrade_themes'])) {
			foreach ($upgrade_details['upgrade_themes'] as $key => $theme) {
				$upgrade_themes[] = $theme['theme_tmp'];
			}
		}

		$wp_upgrade = array();

		if (!empty($upgrade_details['wp_upgrade'])) {
			$wp_upgrade = $upgrade_details['wp_upgrade'];
		}

		$upgrade_translations = ($upgrade_details['upgrade_translations']) ? true: false;

		if (!empty($upgrade_plugins)) {
			$final_upgrade_details['upgrade_plugins']['update_items'] = $upgrade_plugins;
			$final_upgrade_details['upgrade_plugins']['updates_type'] = 'plugin';
			$final_upgrade_details['upgrade_plugins']['is_auto_update'] = '0';
		}

		if (!empty($upgrade_themes)) {
			$final_upgrade_details['upgrade_themes']['update_items'] = $upgrade_themes;
			$final_upgrade_details['upgrade_themes']['updates_type'] = 'theme';
			$final_upgrade_details['upgrade_themes']['is_auto_update'] = '0';

		}

		if (!empty($wp_upgrade)) {
			$final_upgrade_details['wp_upgrade']['update_items'] = $wp_upgrade;
			$final_upgrade_details['wp_upgrade']['updates_type'] = 'core';
			$final_upgrade_details['wp_upgrade']['is_auto_update'] = '0';
		}

		if (!empty($upgrade_translations)) {
			$final_upgrade_details['upgrade_translations']['update_items'] = $upgrade_translations;
			$final_upgrade_details['upgrade_translations']['updates_type'] = 'translation';
			$final_upgrade_details['upgrade_translations']['is_auto_update'] = '0';
		}

		dark_debug($final_upgrade_details, '--------$final_upgrade_details--------');
		$this->bulk_update_request($final_upgrade_details);
		$this->config->set_option('is_bulk_update_request', true);
		$this->config->set_option('backup_before_update_details', false);
		start_fresh_backup_tc_callback_wptc('manual');

		return 'WPTC_TAKES_CARE_OF_IT'; //sending response so iwp client will update
	}

	public function do_bulk_upgrade_request(){

		dark_debug(array(), '--------Coming do_bulk_upgrade_request--------');
		if ($this->config->get_option('backup_before_update_details')) {
			dark_debug($this->config->get_option('backup_before_update_details'), '--------$this->config->get_option--------');
			dark_debug(array(), '--------Upgrade details not empty--------');
			return $this->do_update_after_backup_wptc();
		}

		$raw_bulk_update_request = $this->config->get_option('bulk_update_request');
		dark_debug($raw_bulk_update_request, '--------$raw_bulk_update_request--------');
		if (empty($raw_bulk_update_request)) {
			$this->config->set_option('is_bulk_update_request', false);
			$this->config->set_option('bulk_update_request', false);
			dark_debug(array(), '--------calling parse bulk upgrade response--------');
			$this->is_bulk_update_request = true;
			return $this->parse_bulk_upgrade_response();
		}

		$bulk_update_request = unserialize($raw_bulk_update_request);

		dark_debug($bulk_update_request, '--------$bulk_update_request--------');

		if (!empty($bulk_update_request['upgrade_plugins'])) {
			$this->config->set_option('backup_before_update_details', serialize($bulk_update_request['upgrade_plugins']));
			unset($bulk_update_request['upgrade_plugins']);
			$this->bulk_update_request($bulk_update_request);
			return $this->do_update_after_backup_wptc();
		}

		if (!empty($bulk_update_request['upgrade_themes'])) {
			$this->config->set_option('backup_before_update_details', serialize($bulk_update_request['upgrade_themes']));
			unset($bulk_update_request['upgrade_themes']);
			$this->bulk_update_request($bulk_update_request);
			return $this->do_update_after_backup_wptc();
		}

		if (!empty($bulk_update_request['wp_upgrade'])) {
			$this->config->set_option('backup_before_update_details', serialize($bulk_update_request['wp_upgrade']));
			unset($bulk_update_request['wp_upgrade']);
			$this->bulk_update_request($bulk_update_request);
			return $this->do_update_after_backup_wptc();
		}

		if (!empty($bulk_update_request['upgrade_translations'])) {
			$this->config->set_option('backup_before_update_details', serialize($bulk_update_request['upgrade_translations']));
			unset($bulk_update_request['upgrade_translations']);
			$this->bulk_update_request($bulk_update_request);
			return $this->do_update_after_backup_wptc();
		}
	}

	private function bulk_update_request($bulk_update_request){
		dark_debug($bulk_update_request, '--------$bulk_update_request--------');
		if (empty($bulk_update_request)) {
			return $this->config->set_option('bulk_update_request', false);
		}

		$this->config->set_option('bulk_update_request', serialize($bulk_update_request));
	}

	public function validate_free_paid_items($update_ptc_type, $update_items){
		dark_debug(func_get_args(), "--------" . __FUNCTION__ . "--------");
		$paid_items = array();
		if ($update_ptc_type === 'plugin') {
			foreach ($update_items as $slug) {
				if(!$this->update_common->is_free_plugin($slug)){
					$paid_items[] = $this->update_common->get_plugin_name_by_slug($slug);
				}
			}
		}

		if ($update_ptc_type === 'theme') {
			foreach ($update_items as $slug) {
				if(!$this->update_common->is_free_theme($slug)){
					$paid_items[] = $this->update_common->get_theme_name_by_slug($slug);
				}
			}
		}

		die_with_json_encode(array(
				'paid_items' => $paid_items,
			));
	}
}