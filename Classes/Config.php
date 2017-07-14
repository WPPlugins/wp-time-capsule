<?php
/**
* A class with functions the perform a backup of WordPress
*
* @copyright Copyright (C) 2011-2014 Awesoft Pty. Ltd. All rights reserved.
* @author Michael De Wildt (http://www.mikeyd.com.au/)
* @license This program is free software; you can redistribute it and/or modify
*          it under the terms of the GNU General Public License as published by
*          the Free Software Foundation; either version 2 of the License, or
*          (at your option) any later version.
*
*          This program is distributed in the hope that it will be useful,
*          but WITHOUT ANY WARRANTY; without even the implied warranty of
*          MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*          GNU General Public License for more details.
*
*          You should have received a copy of the GNU General Public License
*          along with this program; if not, write to the Free Software
*          Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110, USA.
*/

class WPTC_Config {
	const MAX_HISTORY_ITEMS = 20;

	private $db, $options, $base;
	public function __construct() {
		$this->db = WPTC_Factory::db();
		$this->base = new Utils_Base();
	}

	public static function get_alternative_backup_dir() {
		return wp_normalize_path(WPTC_WP_CONTENT_DIR);
	}

	public static function get_default_backup_dir() {
		return wp_normalize_path(WPTC_WP_CONTENT_DIR . '/uploads');
	}

	public function get_backup_dir() {
		return wp_normalize_path($this->get_option('backup_db_path') . '/tCapsule/backups');
	}

	// public function replace_slashes($data) {
	// 	return wp_normalize_path($data);
	// }

	public function set_option($name, $value) {
		//Short circut if not changed
		// include_once( WPTC_ABSPATH . 'wp-admin/includes/plugin.php' );

		// // check for plugin using plugin name
		// if (!is_plugin_active( 'wp-time-capsule/wp-time-capsule.php' ) ) {
		// 	dark_debug(array(), '---------not activated------------');
		// 	return false;
		// }
		if ($this->get_option($name) === $value) {
			return $this;
		}

		$exists = $this->db->get_var(
			$this->db->prepare("SELECT * FROM {$this->db->base_prefix}wptc_options WHERE name = %s", $name)
		);

		if (is_null($exists)) {
			$this->db->insert($this->db->base_prefix . "wptc_options", array(
				'name' => $name,
				'value' => $value,
			));
		} else {
			$this->db->update(
				$this->db->base_prefix . 'wptc_options',
				array('value' => $value),
				array('name' => $name)
			);
		}

		$this->options[$name] = $value;

		return $this;
	}

	public function delete_option($option_name){
		return $this->db->delete("{$this->db->base_prefix}wptc_options", array( 'name' => $option_name ));
	}

	public function get_option($name, $no_cache = false) {
		if (!isset($this->options[$name]) || $no_cache) {
			$this->options[$name] = $this->db->get_var(
				$this->db->prepare("SELECT value FROM {$this->db->base_prefix}wptc_options WHERE name = %s", $name)
			);
		}

		return $this->options[$name];
	}

	public function append_option_arr_bool_compat($option_name, $new_val, $error_message = null) {
		if (!$new_val || $new_val == 1) {
			$this->set_option($option_name, $new_val);
			return true;
		}
		$prev_data = array();
		$raw_prev_data = $this->get_option($option_name);
		if (!empty($raw_prev_data)) {
			$prev_data = unserialize($raw_prev_data);
			if (!empty($prev_data)) {
				if (!empty($error_message)) {
					$prev_data[$error_message][] = $new_val;
				} else {
					$prev_data[] = $new_val;
				}
			}
		} else {
			if (!empty($error_message)) {
				$prev_data[$error_message][] = $new_val;
			} else {
				$prev_data[] = $new_val;
			}
		}
		$this->set_option($option_name, serialize($prev_data));
	}

	public function get_option_arr_bool_compat($option_name) {
		$this_ser = $this->get_option($option_name);
		if ($this_ser) {
			$this_arr = unserialize($this_ser);
			if ($this_arr) {
				return $this_arr;
			} else {
				return 1;
			}
		} else if (!$this_ser) {
			return 0;
		}
	}

	public static function set_time_limit() {
		@set_time_limit(0);
	}

	public static function set_backup_time_limit() {
		@set_time_limit(60);
	}

	public function choose_db_backup_path() {
		$dump_location = self::get_default_backup_dir();
		$dump_location_tmp = $dump_location.'/tCapsule/backups';
		// dark_debug($dump_location_tmp, '--------$dump_location_tmp--------');
		if (file_exists($dump_location_tmp)) {
			// dark_debug(array(), '--------existin return--------');
			$this->set_paths_flags($dump_location);
			return true;
		}
		$this->base->createRecursiveFileSystemFolder($dump_location_tmp);
		if (!file_exists($dump_location_tmp) ||  !is_writable($dump_location_tmp)) {
			$alternative_dump_location = self::get_alternative_backup_dir();
			$alternative_dump_location_tmp = $alternative_dump_location .'/tCapsule/backups';
			$this->base->createRecursiveFileSystemFolder($alternative_dump_location_tmp);
			if (!file_exists($dump_location_tmp) || !is_writable($alternative_dump_location_tmp)) {
				return false;
			} else {
				$this->set_paths_flags($alternative_dump_location);
				return true;
			}
		}
		$this->set_paths_flags($dump_location);
		return true;
	}

	private function set_paths_flags($path){
		$prev_path = $this->get_option('backup_db_path');
		if (!empty($prev_path) && $prev_path == $path) {
			return true;
		}
		$this->set_option('backup_db_path', $path);
		$this->set_option('site_abspath', WPTC_ABSPATH);
		$this->set_option('site_wp_content_dir', WPTC_WP_CONTENT_DIR);
		$this->set_option('site_db_name', DB_NAME);
	}

	public static function set_memory_limit() {
		@ini_set('memory_limit', WP_MAX_MEMORY_LIMIT);
	}

	public function is_scheduled() {
		return wp_get_schedule('execute_instant_drobox_backup_wptc') !== false;
	}

	public function set_schedule($day, $time, $frequency) {
		$blog_time = strtotime(date('Y-m-d H', strtotime(current_time('mysql'))) . ':00:00');

		//Grab the date in the blogs timezone
		$date = date('Y-m-d', $blog_time);

		//Check if we need to schedule the backup in the future
		$time_arr = explode(':', $time);
		$current_day = date('D', $blog_time);
		if ($day && ($current_day != $day)) {
			$date = date('Y-m-d', strtotime("next $day"));
		} elseif ((int) $time_arr[0] <= (int) date('H', $blog_time)) {
			if ($day) {
				$date = date('Y-m-d', strtotime("+7 days", $blog_time));
			} else {
				$date = date('Y-m-d', strtotime("+1 day", $blog_time));
			}
		}

		$timestamp = wp_next_scheduled('execute_periodic_drobox_backup_wptc');
		if ($timestamp) {
			wp_unschedule_event($timestamp, 'execute_periodic_drobox_backup_wptc');
		}

		//This will be in the blogs timezone
		$scheduled_time = strtotime($date . ' ' . $time);

		//Convert the selected time to that of the server
		$server_time = strtotime(date('Y-m-d H') . ':00:00') + ($scheduled_time - $blog_time);

		wp_schedule_event($server_time, $frequency, 'execute_periodic_drobox_backup_wptc');

		return $this;
	}

	public function get_schedule() {
		$time = wp_next_scheduled('execute_periodic_drobox_backup_wptc');
		$frequency = wp_get_schedule('execute_periodic_drobox_backup_wptc');
		$schedule = null;

		if ($time && $frequency) {
			//Convert the time to the blogs timezone
			$blog_time = strtotime(date('Y-m-d H', strtotime(current_time('mysql'))) . ':00:00');
			$blog_time += $time - strtotime(date('Y-m-d H') . ':00:00');
			$schedule = array($blog_time, $frequency);
		}

		return $schedule;
	}

	public function clear_history() {
		$this->set_option('history', null);
	}

	public function get_history() {
		$history = $this->get_option('history');
		if (!$history) {
			return array();
		}

		return explode(',', $history);
	}

	public function get_cloud_path($source, $file, $root = false) {
		$dropbox_location = null;
		//if ($this->get_option('store_in_subfolder')){
		if (!$this->get_option('dropbox_location')) {
			$dropbox_location = $this->get_dropbox_folder_tc();
			$this->set_option('dropbox_location', $dropbox_location);
		} else {
			$dropbox_location = $this->get_option('dropbox_location');
		}
		//}
		// dark_debug($dropbox_location, '---------$dropbox_location------------');
		//$dropbox_location = basename(home_url());
		// dark_debug($root, '---------$root------------');
		if ($root) {
			return $dropbox_location;
		}
		$is_staging_running = $this->get_option('is_staging_running');
		if ($is_staging_running) {
			$source = $this->get_option('site_abspath');
		}
		$source = rtrim($source, '/');
		// dark_debug($source, '---------$source------------');
		$file = wp_normalize_path($file);
		// dark_debug($file, '---------$file------------');
		$t1 = str_replace($source, $dropbox_location, $file);
		// dark_debug($t1, '---------$t1------------');
		$t2 = dirname($t1);
		// dark_debug($t2, '---------$t2------------');
		return ltrim($t2, '/');
	}

	public function get_dropbox_folder_tc() {
		$this_site_name = str_replace(array(
			"_",
			"/",
			"~",
		), array(
			"",
			"-",
			"-",
		), rtrim($this->remove_http(get_bloginfo('url')), "/"));
		return $this_site_name;
	}

	public function remove_http($url = '') {
		if ($url == 'http://' OR $url == 'https://') {
			return $url;
		}
		return preg_replace('/^(http|https)\:\/\/(www.)?/i', '', $url);

	}

	public function log_finished_time() {
		$history = $this->get_history();
		$history[] = time();

		if (count($history) > self::MAX_HISTORY_ITEMS) {
			array_shift($history);
		}

		$this->set_option('history', implode(',', $history));

		return $this;
	}

	public function reset_restore_flags(){
		$this->set_option('in_progress_restore', false);
		$this->set_option('is_running_restore', false);
		$this->set_option('cur_res_b_id', false);
		$this->set_option('chunked', false);
		$this->set_option('start_renaming_sql', false);
		return $this;
	}

	public function set_last_cron_trigger_time(){
		$tt = time();
		$usertime_full_stamp = $this->cnvt_UTC_to_usrTime($tt);
		$usertime_full = date('j M, g:ia', $usertime_full_stamp);
		$this->set_option('last_cron_triggered_time', $usertime_full);
	}

	public function clear_all_hooks(){
		wp_clear_scheduled_hook('monitor_tcdropbox_backup_hook_wptc');
		wp_clear_scheduled_hook('run_tc_backup_hook_wptc');
		wp_clear_scheduled_hook('execute_instant_drobox_backup_wptc');
	}

	public function truncate_current_backup_tables(){
		$processed = new WPTC_Processed_DBTables();
		$processed->truncate();
		global $wpdb;
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_current_process`");
	}

	public function clear_amazon_chunks(){
		if ($this->get_option('default_repo') == 's3') {
			$dropbox_location = $this->get_option('dropbox_location');
			WPTC_Factory::get('s3')->abort_all_multipart_uploads($dropbox_location);
		}
	}

	public function clear_current_backup_history(){
		global $wpdb;
		$cur_backup_id = getTcCookie('backupID');
		$delete_processed_files_sql = "DELETE FROM `" . $wpdb->base_prefix . "wptc_processed_files` WHERE backupID= ".$cur_backup_id;
		$delete_processed = $wpdb->query($delete_processed_files_sql);
		$delete_backup_names = "DELETE FROM `" . $wpdb->base_prefix . "wptc_backup_names` WHERE backup_id=".$cur_backup_id;
		$delete_names = $wpdb->query($delete_backup_names);
		$delete_backup_meta = $wpdb->query("DELETE FROM `" . $wpdb->base_prefix . "wptc_backups` WHERE backup_id=".$cur_backup_id);
	}

	public function set_main_cycle_time(){
		$user_time_now = $this->get_wptc_user_today_date_time('Y-m-d');
		$this->set_option('wptc_today_main_cycle', $user_time_now);
		$this->set_option('wptc_main_cycle_running', false);
	}

	public function reset_complete_flags(){
		//Daily Main cycle complete process
		if ($this->get_option('wptc_main_cycle_running')) {
			$this->set_main_cycle_time();
		}
		$this->set_option('in_progress', false);
		$table_name = WPTC_Factory::db()->prefix . 'wptc_processed_files';
		$table_status_name = $table_name . "_meta_data";
		$this->set_option($table_status_name, '');
		$this->set_option('db_meta_data_status', false);
		$this->set_option('add_backup_general_data', false);
		$this->set_option('gotfileslist', false);
		$this->set_option('this_backup_exclude_files_done', false);
		$this->set_option('current_process_file_id', false);
		$this->set_option('is_meta_data_backup_failed', '');
		$this->set_option('meta_data_upload_offset', 0);
		$this->set_option('meta_data_upload_id', '');
		$this->set_option('meta_data_upload_s3_part_number', '');
		$this->set_option('meta_data_upload_s3_parts_array', '');
		$this->set_option('meta_data_backup_process', '');
		$this->set_option('db_meta_data_status', '');
		$this->set_option('total_file_count', 0);
		$this->set_option('recent_backup_ping', false);
		$this->set_option('is_running', false);
		$this->set_option('ignored_files_count', 0);
		$this->set_option('chunked', false);
		$this->set_option('supposed_total_files_count', 0);
		$this->set_option('schedule_backup_running', false);
		$this->set_option('got_exclude_files', false);
		$this->set_option('got_exclude_tables', false);
		$this->set_option('exclude_already_excluded_folders', false);
		$this->set_option('insert_exclude_file_list', false);
		$this->set_option('done_all_exclude_files_tables', false);
		// $this->set_option('cached_wptc_g_drive_folder_id', 0);
		// $this->set_option('cached_g_drive_this_site_main_folder_id', 0);
		$this->set_option('backup_before_update_progress', false);
		$this->set_option('show_sycn_db_view_wptc', true);
		$this->set_option('show_processing_files_view_wptc', true);

		$this->set_option('wptc_sub_cycle_running', 0);
		$this->set_option('wptc_main_cycle_running', 0);
		$this->set_option('wptc_db_backup_completed', false);
		$this->set_option('wptc_current_backup_type', 0);

		$this->set_option('file_list_point', 0);
		$this->set_option('wptc_profiling_start', 0);
		$this->set_option('insert_default_excluded_files', false);
		$this->set_option('file_list_point_obj_iteration', false);
		$this->set_option('file_list_point_obj', false);
		$this->set_option('shell_db_dump_status', false);
		$this->set_option('shell_db_dump_prev_size', false);
		$this->set_option('first_backup_started_atleast_once', true);
	}

	public function complete($this_process = null, $ignored_backup = false, $is_error = false) {
		dark_debug(array(), '-----------Completed called-------------');
		if ($this_process == 'restore') {
			return $this->reset_restore_flags();
		}
		$in_progress = $this->get_option('in_progress', true);
		if(empty($in_progress)){
			dark_debug(array(), '-----------Break in middle because force stop 2-------------');
			if ($this_process == 'logout') {
				return false;
			}
			// $this->clear_first_backup();
			send_response_wptc('backup_stopped_manually', 'BACKUP');
		}


		/*this part of code is temporarily added  should be removed when auto backup comes in */
		$this->set_option('upgrade_process_running', time());
		$this->set_option('bbu_upgrade_process_running', true);
		do_action('just_completed_fresh_backup_any_wptc_h', time());
		$this->set_option('upgrade_process_running', false);
		// do_action('finish_auto_backup', time());
		$this->set_last_cron_trigger_time();
		/* ends */
		$this->clear_all_hooks();
		$this->truncate_current_backup_tables();
		$this->reset_complete_flags();
		if (!$ignored_backup) {
			$this->set_option('last_backup_time', getTcCookie('backupID'));
			$this->set_option('last_backup_ping', time());
			$this->log_finished_time();

			if ($this->get_option('starting_first_backup') && !$is_error && $this_process != 'deactivated_plugin') {
				// $this->set_main_cycle_time();
				$this->send_first_backup_completed_email();
				$this->set_option('starting_first_backup', false);
				do_action('just_completed_first_backup_wptc_h', time());
				do_action('send_database_size', time());
			} else {
				do_action('just_completed_not_first_backup_wptc_h', time());
				do_action('send_database_size', time());
			}
		}

		$this->clear_amazon_chunks();

		$mail_backup_errors = $this->get_option_arr_bool_compat('mail_backup_errors');
		$frequently_changed_files = $this->get_option_arr_bool_compat('frequently_changed_files');

		dark_debug($frequently_changed_files, "--------frequently_changed_files--------");
		dark_debug($mail_backup_errors, "--------mail_backup_errors--------");

		if (!empty($mail_backup_errors) && $this_process != 'deactivated_plugin') {
			//send mail with error details
			$mail_backup_errors_temp = json_encode($mail_backup_errors, JSON_UNESCAPED_SLASHES);
			$errors = array('error_details' => $mail_backup_errors_temp);
			$errors['type'] = 'backup_failed';
			$errors['cloudAccount'] = DEFAULT_REPO_LABEL;
			error_alert_wptc_server($errors);
		}

		if (!empty($frequently_changed_files) && $this_process != 'deactivated_plugin') {
			//send mail with error details
			$frequently_changed_files_temp = json_encode($frequently_changed_files, JSON_UNESCAPED_SLASHES);
			$errors = array('error_details' => $frequently_changed_files_temp);
			$errors['type'] = 'frequently_changed_files';
			$errors['cloudAccount'] = DEFAULT_REPO_LABEL;
			error_alert_wptc_server($errors);
		}

		set_backup_in_progress_server(false);
		do_action('send_ptc_list_to_server_wptc', time());
		do_action('is_staging_backup_wptc_h', time());
		do_action('send_backups_data_to_server_wptc', time());
		do_action('run_vulns_check_wptc', time());
		do_action('force_trigger_auto_updates_wptc', time());
		do_action('send_ip_address_to_server_wptc', time());
		$this->set_option('bbu_upgrade_process_running', false);
		return $this;
	}

	public function force_complete(){
		dark_debug(array(), '-----------force_complete called-------------');
		// $this->clear_first_backup();
		do_action('force_stop_reset_autobackup_wptc_h', time());
		$this->reset_complete_flags();
		$this->clear_all_hooks();
		$this->truncate_current_backup_tables();
		$this->clear_amazon_chunks();
		$this->clear_current_backup_history();
		$this->force_complete_reset_flags();
		set_backup_in_progress_server(false);
	}

	public function force_complete_reset_flags(){
		$this->set_option('starting_first_backup', false);
	}

	public function clear_first_backup(){
		if ($this->get_option('starting_first_backup')) {
			$this->set_option('wptc_today_main_cycle', false);
			$this->set_option('wptc_main_cycle_running', false);
		}
	}

	public function send_first_backup_completed_email() {
		$email_data = array(
			'type' => 'first_backup_completed',
		);
		error_alert_wptc_server($email_data);
	}

	public function die_if_stopped() {
		$in_progress = $this->db->get_var("SELECT value FROM {$this->db->base_prefix}wptc_options WHERE name = 'in_progress'");
		if (!$in_progress) {
			$msg = __('Backup stopped by user.', 'wptc');
			WPTC_Factory::get('logger')->log($msg);
			die($msg);
		}
	}

	//Getting WPTC Timezone by given format
	// @format inputs should be in the list of datetime format parameter
	public function get_wptc_user_today_date_time($format, $timestamp = '') {
		if (empty($timestamp)) {
			$timestamp = time();
		}

		$wptc_timezone = $this->get_option('wptc_timezone');
		if(empty($wptc_timezone)){
			$wptc_timezone = 'UTC';
		}
		/*
		//Converting to UTC and then again to wptc timezone makes problem in some server
		// $user_tz = new DateTime(date('Y-m-d H:i:s', $timestamp), new DateTimeZone('UTC'));
		// $user_tz->setTimeZone(new DateTimeZone($wptc_timezone));
		// $user_tz_now = $user_tz->format($format);
		*/
		//directly change to user's timezone
		$date = new DateTime(date('Y-m-d H:i:s', $timestamp));
		$timezone_obj = $date->setTimezone(new DateTimeZone($wptc_timezone));
		$user_tz_now = $date->format($format);
		return $user_tz_now;
	}

	public function get_some_min_advanced_current_hour_of_the_day_wptc($time = 0) {
		$FORMAT = 'g:00 a';

		if (empty($time)) {
			$time = time();
		}

		$sleeped_time_stamp = $time + 300;

		$wptc_timezone = $this->get_option('wptc_timezone');
		$user_tz = new DateTime(date('Y-m-d H:i:s', $sleeped_time_stamp), new DateTimeZone('UTC'));
		$user_tz->setTimeZone(new DateTimeZone($wptc_timezone));
		$user_tz_now = $user_tz->format($FORMAT);
		return $user_tz_now;
	}

	public function get_adjusted_timestamp_for_that_timezone_wptc($scheduled_time_string) {
		$hour_from_string_arr = explode(':', $scheduled_time_string);
		$hour_from_string = $hour_from_string_arr[0];

		dark_debug($hour_from_string, "--------hour_from_string--------");

		$this_timestamp = mktime($hour_from_string, 0, 0);
		$adjusted_timestamp = $this_timestamp - (60 * 2); //2 mins adjustment

		$tz_formatted_timestamp = $this->get_wptc_user_today_date_time('u', $adjusted_timestamp);

		return $tz_formatted_timestamp;
	}

	public function is_main_account_authorized($email = null, $pwd = null) {
		if (!empty($email)) {
			$main_account_email = $email;
		} else {
			$main_account_email = $this->get_option('main_account_email');
		}
		if (!empty($pwd)) {
			$main_account_pwd = $this->hash_pwd($pwd);
		} else {
			$main_account_pwd = $this->get_option('main_account_pwd');
		}
		if (empty($main_account_email) || empty($main_account_pwd)) {
			return false;
		}

		$wptc_token = $this->get_option('wptc_token');

		dark_debug($wptc_token, "--------stroed token is--------");

		$site_url = $this->get_option('site_url_wptc');
		$admin_url = $this->get_option('network_admin_url');

		$params = array('email' => $main_account_email, 'pwd' => $main_account_pwd, 'site_url' => $site_url, 'version' => WPTC_VERSION, 'ip_address' => gethostbyname($_SERVER['HTTP_HOST']), 'admin_url' => $admin_url );
		dark_debug($params, '--------$params--------');
		$rawResponseData = $this->doCall(WPTC_USER_SERVICE_URL, $params, 20, array('normalPost' => 1), $wptc_token);

		dark_debug($rawResponseData, "--------rawResponseData--------");

		if (empty($rawResponseData) || !is_string($rawResponseData)) {
			return false;
		}

		$cust_info = json_decode(base64_decode($rawResponseData));

		$this->set_option('plan_info', json_encode(array(), true));
		$this->set_option('privileges_wptc', false);
		$this->set_option('valid_user_but_no_plans_purchased', false);
		$this->set_option('card_added', false);
		$this->set_option('active_sites', false);
		$this->set_option('user_slot_info', json_encode(array(), true));

		$this->set_option('main_account_email', $main_account_email);
		$this->set_option('main_account_pwd', $main_account_pwd);

		if ($this->process_service_info($cust_info)) {
			dark_debug($cust_info, "--------acocunt auth trure--------");

			if(empty($cust_info->success)){
				return false;					//hack
			}

			$cust_req_info = $cust_info->success[0];
			$this_d_name = $cust_req_info->cust_display_name;
			$this_token = $cust_req_info->wptc_token;

			$this->set_option('uuid', $cust_req_info->uuid);
			$this->set_option('wptc_token', $this_token);
			$this->set_option('main_account_name', $this_d_name);

			do_action('update_white_labling_settings_wptc', $cust_req_info);

			if (isset($cust_req_info->connected_sites_count)){
				$this->set_option('connected_sites_count', $cust_req_info->connected_sites_count);
			} else {
				$this->set_option('connected_sites_count', 1); //set default 1 sites connected if server does send sites count
			}

			if(!empty($cust_info->logged_in_but_no_plans_yet)){
				$this->do_options_for_no_plans_yet($cust_info);
				return false;
			}
			else{

				// $cust_req_info = (!empty($cust_info) && !empty($cust_info->success) && !empty($cust_info->success[0])) ? true : false;

				$this->process_subs_info_wptc($cust_req_info);
				$this->process_privilege_wptc($cust_req_info);

				$is_cron_service = $this->check_if_cron_service_exists();
				if ($is_cron_service) {
					$this->set_option('is_user_logged_in', true);
					return true;
				}
			}
		}
		$this->set_option('is_user_logged_in', false);
		return false;
	}

	private function process_service_info(&$cust_info) {
		if (empty($cust_info) || !empty($cust_info->error)) {
			$err_msg = $this->process_wptc_error_msg_then_take_action($cust_info);

			$this->set_option('card_added', false);

			if($err_msg == 'logged_in_but_no_plans_yet'){
				$this->do_options_for_no_plans_yet($cust_info);

				return true;			//hack
			}
			return false;
		} else {
			return true;
		}
	}

	public function do_options_for_no_plans_yet(&$cust_info)
	{
		$this->set_option('valid_user_but_no_plans_purchased', true);
		$this->set_option('card_added', $cust_info->is_card_added);
		$this->set_option('plan_info', json_encode($cust_info->plan_info, true));
		$this->set_option('user_slot_info', json_encode($cust_info->this_user_slot, true));
		$this->set_option('active_sites', json_encode($cust_info->active_sites, true));

		$this->set_option('is_user_logged_in', true);	//hack
	}

	private function process_subs_info_wptc($cust_req_info=null)
	{
		// dark_debug($cust_req_info->slot_info, "--------doing privilegees--------");

		if(empty($cust_req_info->slot_info)){
			$this->set_option('subscription_info', false);
			return false;
		}

		$sub_info = (array)$cust_req_info->slot_info;

		$this->set_option('subscription_info', json_encode($sub_info));
	}

	private function process_privilege_wptc($cust_req_info = null)
	{

		// dark_debug($cust_req_info->subscription_features, "--------doing privilegees--------");

		if(empty($cust_req_info->subscription_features)){
			$this->reset_privileges();
			return false;
		}

		$sub_features = (array)$cust_req_info->subscription_features;

		$privileged_feature = array();
		$privileges_args = array();

		foreach($sub_features as $plan_id => $single_sub){
			foreach($single_sub as $key => $v){
				$privileged_feature[$v->type][] = 'Wptc_' . ucfirst($v->feature);
				$privileges_args['Wptc_' . ucfirst($v->feature)] = (!empty($v->args)) ? $v->args : array();
			}
		}

		$this->set_option('privileges_wptc', json_encode($privileged_feature));
		$this->set_option('privileges_args', json_encode($privileges_args));
	}

	public function reset_privileges()
	{
		$this->set_option('privileges_wptc', false);
		$this->set_option('privileges_args', false);
	}

	public function process_access_denied_error($rawResponseData) {
		if (is_string($rawResponseData) && stripos($rawResponseData, 'Access denied') !== false) {
			$this->set_option('main_account_login_last_error', $rawResponseData);
		}
	}

	public function hash_pwd($str) {
		return md5($str);
	}

	public function check_if_cron_service_exists() {
		if (!$this->get_option('wptc_server_connected') || !$this->get_option('appID') || $this->get_option('signup') != 'done') {
			if ($this->get_option('main_account_email')) {
				return signup_wptc_server_wptc();
			}
		}
		return true;
	}

	public function get_last_login_error_msg() {
		$err_msg = $this->get_option('main_account_login_last_error');
		if (empty($err_msg)) {
			return 'Oops. The login details seems to be incorrect. Please try again.';
		}
		return $err_msg;
	}

	public function process_wptc_error_msg_then_take_action(&$cust_info) {
		$err_msg = 'Oops. The login details seems to be incorrect. Please try again.';
		if (empty($cust_info->error)) {
			// return $err_msg;
		}
		if ($cust_info->error == 'process_site_validation') {
			$err_msg = 'Oops. Trial period expired for this site.';

			if(!empty($cust_info->extra) && !empty($cust_info->error)){
				if($cust_info->error == 'no_free_slot_available'){
					$err_msg = 'Oops. This site seems to be connected with some other account.';
				}
			} else {
				return 'logged_in_but_no_plans_yet';
			}
		} else if ($cust_info->error == 'no_slot_available') {
			$err_msg = 'Oops. This site seems to be connected with some other account.';
		}

		$this->set_option('main_account_login_last_error', $err_msg);
		$this->set_option('main_account_email', false);
		$this->set_option('main_account_pwd', false);
		$this->set_option('privileges_wptc', false);

		return $err_msg;
	}

	function doCall($URL, $data, $timeout = 60, $options = array(), $wptc_token = null) {
		$ch = curl_init();
		$URL = trim($URL);

		$post_string = base64_encode(serialize($data));
		$post_string = urlencode($post_string);

		if(empty($wptc_token)){
			$wptc_token = $this->get_option('wptc_token');
		}

		// dark_debug($post_string, "--------post_string--------");
		curl_setopt($ch, CURLOPT_URL, $URL);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, 'data=' . $post_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

		if(!empty($wptc_token)){
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				"Authorization: $wptc_token",
			));
		}

		$microtimeStarted = time();
		$rawResponse = curl_exec($ch);
		$microtimeEnded = time();

		$curlInfo = array();
		$curlInfo['info'] = curl_getinfo($ch);

		if (curl_errno($ch)) {
			$curlInfo['errorNo'] = curl_errno($ch);
			$curlInfo['error'] = curl_error($ch);

			$rawResponse = array('error' => $curlInfo['error']);
		}

		curl_close($ch);
		// dark_debug($rawResponse,'--------------$rawResponse-------------');
		return $rawResponse;
	}

	public function remove_garbage_files($options = array('is_restore' => false), $hard_reset = false) {
		try {
			global $wp_filesystem;

			if (!$wp_filesystem) {
				initiate_filesystem_wptc();
				if (empty($wp_filesystem)) {
					send_response_wptc('FS_INIT_FAILED-006');
					return false;
				}
			}


			dark_debug(get_backtrace_string_wptc(), "--------removing garbage files--------");

			$this_config_like_file = $this->wp_filesystem_safe_abspath_replace(WPTC_ABSPATH);
			$this_config_like_file = $this_config_like_file . 'config-like-file.php';

			if ($wp_filesystem->exists($this_config_like_file)) {
				$wp_filesystem->delete($this_config_like_file);
			}

			$this_temp_backup_folder = $this->get_option('backup_db_path') . '/tCapsule';
			$this_temp_backup_folder = $this->wp_filesystem_safe_abspath_replace($this_temp_backup_folder);

			$this->delete_files_of_this_folder($this_temp_backup_folder, $options);
			if(!$this->get_option('is_staging_running')){
				$current_bridge_file_name = $this->get_option('current_bridge_file_name');
				if (!empty($current_bridge_file_name)) {
					$root_bridge_file_path = WPTC_ABSPATH . '/' . $current_bridge_file_name;
					$root_bridge_file_path = $this->wp_filesystem_safe_abspath_replace($root_bridge_file_path);
					$this->delete_files_of_this_folder($root_bridge_file_path, $options);
					$wp_filesystem->delete($root_bridge_file_path);
				}
			}

			$this_backups = $this->wp_filesystem_safe_abspath_replace(WPTC_ABSPATH . '/backups');
			$this->delete_files_of_this_folder($this_backups, $options);
			$wp_filesystem->delete($this_backups);

			$this->set_option('garbage_deleted', true);
			if (!$hard_reset) {
				$this->send_restore_complete_status();
			}
		} catch (Exception $e) {
			dark_debug(array(), "--------error --------");
		}
	}

	public function send_restore_complete_status() {
		$domain_url = WPTC_CRSERVER_URL;
		$post_type = 'POST';

		$app_id = $this->get_option('appID');
		$wptc_token = $this->get_option('wptc_token');

		$email = trim($this->get_option('main_account_email', true));
		$email_encoded = base64_encode($email);

		$pwd = trim($this->get_option('main_account_pwd', true));
		$pwd_encoded = base64_encode($pwd);

		$post_arr = array(
			'app_id' => $app_id,
			'email' => $email_encoded,
			'pwd' => $pwd_encoded,
			'site_url' => '',
			'event' => 'restore_completed',
		);
		$post_arr['version'] = WPTC_VERSION;
		$post_arr['source'] = 'WPTC';
		$route_path = 'users/stats';
		if (WPTC_DARK_TEST) {
			server_call_log_wptc($post_arr, '----REQUEST-----', WPTC_CRSERVER_URL . "/" . $route_path);
		}
		$chb = curl_init();
		// dark_debug($post_arr,'--------------$post_arr-------------');
		curl_setopt($chb, CURLOPT_URL, $domain_url . "/" . ltrim($route_path, '/'));
		curl_setopt($chb, CURLOPT_CUSTOMREQUEST, $post_type);
		// curl_setopt($chb, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($chb, CURLOPT_POSTFIELDS, http_build_query($post_arr));
		curl_setopt($chb, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($chb, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($chb, CURLOPT_SSL_VERIFYHOST, FALSE);

		if(!empty($wptc_token)){
			curl_setopt($chb, CURLOPT_HTTPHEADER, array(
				"Authorization: $wptc_token",
			));
		}

		if (!defined('WPTC_CURL_TIMEOUT')) {
			define('WPTC_CURL_TIMEOUT', 20);
		}
		curl_setopt($chb, CURLOPT_TIMEOUT, WPTC_CURL_TIMEOUT);

		$pushresult = curl_exec($chb);

		if (WPTC_DARK_TEST) {
			server_call_log_wptc($pushresult, '-----RESPONSE-----');
		}

		return $pushresult;
	}

	public function delete_files_by_path($root_bridge_file_path, $options){
		global $wp_filesystem;
		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-007');
				return false;
			}
		}

		$is_staging_running = $this->get_option('is_staging_running');
		if (!$is_staging_running) {
			$root_bridge_file_path = $this->wp_filesystem_safe_abspath_replace($root_bridge_file_path);
		}
		$this->delete_files_of_this_folder($root_bridge_file_path, $options);
		$wp_filesystem->delete($root_bridge_file_path);
	}

	public function save_encoded_not_safe_for_write_files($single_file) {
		$not_safe_for_write_files = array();

		$not_safe_for_write_files = $this->get_encoded_not_safe_for_write_files();

		$not_safe_for_write_files[$single_file] = 1;
		$this->set_option('not_safe_for_write_files', json_encode($not_safe_for_write_files));
	}

	public function get_encoded_not_safe_for_write_files() {
		$not_safe_for_write_files = array();

		$not_safe_for_write_files_ser = $this->get_option('not_safe_for_write_files');
		if ($not_safe_for_write_files_ser) {
			$not_safe_for_write_files = json_decode($not_safe_for_write_files_ser, true);
		}

		return $not_safe_for_write_files;
	}

	public function delete_files_of_this_folder($folder_name, $options = array('is_restore' => false)) {
		$folder_name = trailingslashit($folder_name);
		// global $wp_filesystem, $wptc_profiling_start;
		global $wp_filesystem;
		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-008');
				return false;
			}
		}
		if (!$wp_filesystem->is_dir($folder_name)) {
			return;
		}

		$dirlist = $wp_filesystem->dirlist($folder_name);
		$folder_name = trailingslashit($folder_name);

		if (empty($dirlist)) {
			$wp_filesystem->delete($folder_name);
			return;
		}
		foreach ($dirlist as $filename => $fileinfo) {
			if ('f' == $fileinfo['type']) {
				$wp_filesystem->delete($folder_name . $filename);
				if (!empty($options['is_restore'])) {
					maybe_call_again();
				} else if (!empty($options['is_backup'])) {
					// $starting_backup_path_time = $wptc_profiling_start;
					$starting_backup_path_time = $this->config->get_option('wptc_profiling_start');
					check_timeout_cut_and_exit_wptc($starting_backup_path_time);
				}
			} elseif ('d' == $fileinfo['type']) {
				$this->delete_files_of_this_folder($folder_name . $filename);
				$this->delete_files_of_this_folder($folder_name . $filename); //second time to delete empty folders
			}
		}
	}

	public function delete_empty_folders($this_folder = null, $prev_dir_deleted_count = 0) {
		$this_folder = trailingslashit($this_folder);
		global $wp_filesystem;
		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-009');
				return false;
			}
		}
		$this_folder_dir_list = $wp_filesystem->dirlist($this_folder);
		$is_any_exists_count = 0;
		if (!empty($this_folder_dir_list)) {
			foreach ($this_folder_dir_list as $key => $value) {
				$is_any_exists_count++;
				if ($value['type'] == 'd') {
					$prev_del_count = $this->delete_empty_folders($this_folder . '/' . $key, $prev_dir_deleted_count);
					$is_any_exists_count -= $prev_del_count;
				}
			}
		}
		if ($is_any_exists_count < 1) {
			$prev_dir_deleted_count++;
			$wp_filesystem->delete($this_folder);
			return $prev_dir_deleted_count;
		}
	}

	public static function init_delete_user_recorded_exculuded_files() {
		$files_array = get_user_excluded_files_folders_from_settings_wptc();
		WPTC_Factory::get('fileList')->delete_excluded_files($files_array);
	}

	public function create_dump_dir($options = array('is_bridge' => false)) {
		if(!is_wptc_server_req() && !current_user_can('activate_plugins')){
			return false;
		}
		if (!$options['is_bridge']) {
			require_once WPTC_ABSPATH . "wp-admin/includes/class-wp-filesystem-base.php";
			require_once WPTC_ABSPATH . "wp-admin/includes/class-wp-filesystem-direct.php";
			require_once WPTC_ABSPATH . "wp-admin/includes/class-wp-filesystem-ftpext.php";
			require_once WPTC_ABSPATH . "wp-admin/includes/class-wp-filesystem-ssh2.php";
			require_once WPTC_ABSPATH . "wp-admin/includes/class-wp-filesystem-ftpsockets.php";

			initiate_filesystem_wptc();
		}

		global $wp_filesystem;
		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-036');
				return false;
			}
		}

		$dump_dir = $this->get_backup_dir();
		dark_debug($dump_dir, '--------$dump_dir--------');
		$dump_dir = $this->wp_filesystem_safe_abspath_replace($dump_dir);
		dark_debug($dump_dir, '--------$dump_dir check again--------');
		if ($wp_filesystem->exists($dump_dir)){
			self::create_silence_file();
			return true;
		}

		$this->base->createRecursiveFileSystemFolder($dump_dir);

		if (!$wp_filesystem->exists($dump_dir)) {
			stop_if_ongoing_backup_wptc();
			$error_message = sprintf(__("WordPress Time Capsule requires write access to '%s', please ensure it exists and has write permissions.", 'wptc'), $dump_dir);
			throw new Exception($error_message);
			return;
		}

		self::create_silence_file();
		return true;
	}

	public function wp_filesystem_safe_abspath_replace($file_path) {
		global $wp_filesystem;
		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-027');
				return $file_path;
			}
		}
		$file_path = trailingslashit($file_path);
		$options = WPTC_Factory::get('config');
		$is_staging_running = $options->get_option('is_staging_running');
		if($is_staging_running){
			$site_abspath = $options->get_option('site_abspath');
			$safe_path = str_replace(wp_normalize_path($site_abspath), wp_normalize_path($wp_filesystem->abspath()), wp_normalize_path($file_path));
		} else{
			$safe_path = str_replace(WPTC_ABSPATH, wp_normalize_path($wp_filesystem->abspath()), wp_normalize_path($file_path));
		}
		return wp_normalize_path($safe_path);
	}

	public function replace_to_original_abspath($file_path){
		global $wp_filesystem;
		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-028');
				return false;
			}
		}
		$is_staging_running = $this->get_option('is_staging_running');
		if($is_staging_running){
			$site_abspath = $this->get_option('site_abspath');
			$safe_path = str_replace($wp_filesystem->abspath(), $site_abspath, $file_path);
		} else{
			return $file_path;
		}
		return $safe_path;
	}

	public function tc_file_system_copy_dir($from, $to = '', $action = array('multicall_exit' => false)) {
		global $wp_filesystem;
		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-029');
				return false;
			}
		}
		$from = trailingslashit($from);
		$to = trailingslashit($to);

		$dirlist = $wp_filesystem->dirlist($from);

		foreach ((array) $dirlist as $filename => $fileinfo) {
			if ('f' == $fileinfo['type'] && $filename != '.htaccess') {
				if (!$this->tc_file_system_copy($from . $filename, $to . $filename, false, FS_CHMOD_FILE)) {
					$wp_filesystem->chmod($to . $filename, 0644);
					if (!$this->tc_file_system_copy($from . $filename, $to . $filename, false, FS_CHMOD_FILE)) {
						return false;
					}
				}
				if ($action['multicall_exit'] == true) {
					maybe_call_again();
				}
			} elseif ('d' == $fileinfo['type']) {
				if (!$wp_filesystem->is_dir($to . $filename)) {
					if (!$wp_filesystem->mkdir($to . $filename, FS_CHMOD_DIR)) {
						return false;
					}
				}
				$result = $this->tc_file_system_copy_dir($from . $filename, $to . $filename, $action);
				if (!$result) {
					return false;
				}
			}
		}
		return true;
	}

	public function tc_file_system_copy($source, $destination, $overwrite = false, $mode = 0644) {
		global $wp_filesystem;
		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-030');
				return false;
			}
		}

		$copy_result = $wp_filesystem->copy($source, $destination, $overwrite, $mode);

		if (!$copy_result && !$overwrite) {
			return true;
		}
		return $copy_result;
	}

	public function tc_file_system_move_dir($from, $to = '') {
		global $wp_filesystem;
		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-031');
				return false;
			}
		}
		$from = trailingslashit($from);
		$to = trailingslashit($to);

		$dirlist = $wp_filesystem->dirlist($from);

		foreach ((array) $dirlist as $filename => $fileinfo) {
			if ('f' == $fileinfo['type']) {
				if (!$this->tc_file_system_move($from . $filename, $to . $filename, true, FS_CHMOD_FILE)) {
					$wp_filesystem->chmod($to . $filename, 0644);
					if (!$this->tc_file_system_move($from . $filename, $to . $filename, true, FS_CHMOD_FILE)) {
						return array('error' => 'cannot move file');
					}
				}
			} elseif ('d' == $fileinfo['type']) {
				if (!$wp_filesystem->is_dir($to . $filename)) {
					if (!$wp_filesystem->mkdir($to . $filename, FS_CHMOD_DIR)) {
						return array('error' => 'cannot create directory');
					}
				}
				$result = $this->tc_file_system_move_dir($from . $filename, $to . $filename);
				if (!$result) {
					return array('error' => 'cannot move directory');
				}
			}
			maybe_call_again();
		}

		return true;
	}

	public function tc_file_system_move($source, $destination, $overwrite = false, $mode = false) {
		global $wp_filesystem;
		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-032');
				return false;
			}
		}
		//initialize processed files object
		$processed_files = WPTC_Factory::get('processed-restoredfiles', true);
		$current_processed_files = array();

		// check if already processed ; if so dont copy
		$processed_file = $processed_files->get_file($source);
		if (!($processed_file)) {
			$copy_result = $wp_filesystem->move($source, $destination, $overwrite, $mode);
			if ($copy_result) {
				//if copied then add the details to DB
				$this_file_detail['file'] = wp_normalize_path($source);
				$this_file_detail['copy_status'] = true;
				$this_file_detail['revision_number'] = null;
				$this_file_detail['revision_id'] = null;
				$this_file_detail['mtime_during_upload'] = null;

				$current_processed_files[] = $this_file_detail;
				$processed_files->add_files($current_processed_files);
				//set the in_progress option to false on final file copy
			}
			return $copy_result;
		}
		return true;
	}

	public function cnvt_UTC_to_usrTime($time_stamp = "1453964442", $format = "F j, Y, g:i a") {
		$user_time_zone = $this->get_option('wptc_timezone');

		if (empty($user_time_zone)) {
			$user_time_zone = 'utc';
		}

		date_default_timezone_set('UTC');

		$str_time = date($format, $time_stamp);

		$local_time = new DateTime($str_time);
		$tz_start = new DateTimeZone($user_time_zone);
		$local_time->setTimezone($tz_start);
		$start_date_time = (array) $local_time;

		$new_time = $start_date_time['date'];
		$new_time_stamp = strtotime($new_time);

		return $new_time_stamp;
	}

	private static function create_silence_file() {
		$options_obj = WPTC_Factory::get('config');
		$silence = $options_obj->get_backup_dir();
		$silence = $silence. '/' . 'index.php';
		global $wp_filesystem;
		if (!$wp_filesystem->exists($silence)) {
			$wp_filesystem->put_contents($silence, "<?php\n// Silence is golden.\n");
			// $fh = @fopen($silence, 'w');
			// if ($fh) {
			// 	fwrite($fh, "<?php\n// Silence is golden.\n");
			// 	fclose($fh);
			// }
		}
	}

}
