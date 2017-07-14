<?php

function is_php_version_compatible_for_s3_wptc() {
	if (version_compare(PHP_VERSION, '5.3.3') >= 0) {
		return true;
	}
	return false;
}

function is_php_version_compatible_for_g_drive_wptc() {
	if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
		return true;
	}
	return false;
}

function is_php_version_compatible_for_dropbox_wptc() {
	if (version_compare(PHP_VERSION, '5.3.1') >= 0) {
		return true;
	}
	return false;
}

function reset_restore_related_settings_wptc() {
	dark_debug(array(), "--------reset_restore_related_settings_wptc--------");
	$config = WPTC_Factory::get('config');

	//resetting restore config-options
	$config->set_option('restore_action_id', false);
	$config->set_option('in_progress_restore', false);
	$config->set_option('is_running_restore', false);
	$config->set_option('cur_res_b_id', false);
	$config->set_option('is_bridge_process', false);
	$config->set_option('current_bridge_file_name', false);

	$config->set_option('got_files_list_for_restore_to_point', 0);
	$config->set_option('live_files_to_restore_table', 0);
	$config->set_option('file_list_point_restore', 0);
	$config->set_option('recorded_files_to_restore_table', 0);
	$config->set_option('selected_files_temp_restore', 0);
	$config->set_option('selected_backup_type_restore', 0);
	$config->set_option('got_selected_files_to_restore', 0);
	$config->set_option('not_safe_for_write_files', 0);
	$config->set_option('recorded_this_selected_folder_restore', 0);
	$config->set_option('chunked', false);

	$config->set_option('check_is_safe_for_write_restore', 1);

	$config->set_option('garbage_deleted', 0);
}

register_shutdown_function('wptc_fatal_error_hadler');
function wptc_fatal_error_hadler($return = null) {
	$log_error_types = array(
		1 => 'PHP Fatal error', // fatal error
		2 => 'PHP Warning', //warning
		4 => 'PHP Parse', //parse
		8 => 'PHP Notice error', //notice
		16 => 'PHP Core error', //core error
		32 => 'PHP Core Warning', //core warning
		64 => 'PHP Core compile error', //core compile error
		128 => 'PHP Core compile error', //core compile waning
		8192 => 'PHP Deprecated error', //core compile waning
	);
	$last_error = error_get_last();
	if (empty($last_error) && empty($return)) {
			return false;
	} else {
		if ($return) {
			$config = WPTC_Factory::get('config');
			$recent_error = $config->get_option('plugin_recent_error');
			if (empty($recent_error)) {
				$recent_error = "Something went wrong ";
			}
			return $recent_error. ". \n Please contact us help@wptimecapsule.com";
		}
	}

	// hanlde_all_fatal_errors_to_revitalize_cron_wptc($last_error); // 30 seconds cron

	if (strpos($last_error['message'], 'use the CURLFile class') === false && strpos($last_error['message'], 'Automatically populating') === false) {
		if (strpos($last_error['file'], 'iwp-client') === false && defined('WPTC_DARK_TEST') && WPTC_DARK_TEST) {
			if (defined('WPTC_WP_CONTENT_DIR')) {
				file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_cl.php', $log_error_types[$last_error['type']] . ": " . $last_error['message'] . " in " . $last_error['file'] . " on " . " line " . $last_error['line'] . "\n", FILE_APPEND);
				if(WPTC_Factory::get('Debug_Log')){
					WPTC_Factory::get('Debug_Log')->wptc_log_now('Error: '.$log_error_types[$last_error['type']] . ": " . $last_error['message'] . " in " . $last_error['file'] . " on " . " line " . $last_error['line']);
				}
			} else {
				file_put_contents('DE_cl.php', $log_error_types[$last_error['type']] . ": " . $last_error['message'] . " in " . $last_error['file'] . " on " . " line " . $last_error['line'] . "\n", FILE_APPEND);
				if(WPTC_Factory::get('Debug_Log')){
					WPTC_Factory::get('Debug_Log')->wptc_log_now('Error: '.$log_error_types[$last_error['type']] . ": " . $last_error['message'] . " in " . $last_error['file'] . " on " . " line " . $last_error['line']);
				}
			}
		}
		if (strpos($last_error['file'], 'wp-time-capsule') !== false || strpos($last_error['file'], 'wp-tcapsule-bridge') !== false) {
			$config = WPTC_Factory::get('config');
			$error = $log_error_types[$last_error['type']] . ": " . $last_error['message'] . " in " . $last_error['file'] . " on " . " line " . $last_error['line'];
			$config->set_option('plugin_recent_error', $error);
		}
	}
}

/*
*30 seconds cron
if (!function_exists('hanlde_all_fatal_errors_to_revitalize_cron_wptc')) {
	function hanlde_all_fatal_errors_to_revitalize_cron_wptc($last_error) {
		if ($last_error['type'] != 1) {
			return true;
		}
		if (class_exists('WPTC_Factory')) {
			$config = WPTC_Factory::get('config');
			if ($config && $config->get_option('in_progress')) {
				dark_debug($last_error, '---------backup fatal error shut down-------------');
				revitalize_monitor_hook_wptc();
			}
		}
	}
}
*/

if (!function_exists('server_call_log_wptc')) {
	function server_call_log_wptc($value, $type, $url = null) {
		if (defined('WPTC_DARK_TEST') && WPTC_DARK_TEST) {
			$usr_time = time();
			if (function_exists('user_formatted_time_wptc')) {
				$usr_time = user_formatted_time_wptc(time());
			}

			try {
				@file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_cl_server_call_log_wptc.php', "\n -----$type-------$usr_time-------$url------- " . var_export($value, true) . "\n", FILE_APPEND);
			} catch (Exception $e) {
				@file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_cl_server_call_log_wptc.php', "\n -----$type-------$usr_time------$url-------- " . var_export(serialize($value), true) . "\n", FILE_APPEND);
			}
		}
	}
}

function get_backtrace_string_wptc($limit = 7) {
	if (WPTC_DARK_TEST === false) {
		return false;
	}
	$bactrace_arr = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);
	$backtrace_str = '';
	if (!is_array($bactrace_arr)) {
		return false;
	}
	foreach ($bactrace_arr as $k => $v) {
		if ($k == 0) {
			continue;
		}
		$line = empty($v['line']) ? 0 : $v['line'];
		$backtrace_str .= '<-' . $v['function'] . '(line ' . $line . ')';
	}
	return $backtrace_str;
}

function store_bridge_compatibile_values_wptc() {
	$config = WPTC_Factory::get('config');

	$config->set_option('site_url_wptc', get_home_url());

	if (is_multisite()) {
		$config->set_option('network_admin_url', network_admin_url());
	} else {
		$config->set_option('network_admin_url', admin_url());
	}
}

function is_wptc_timeout_cut($start_time = false, $reduce_sec = 0) {
	if ($start_time === false) {
		global $settings_ajax_start_time;
		$start_time = $settings_ajax_start_time;
	}
	$time_diff = microtime(true) - $start_time;
	if (!defined('WPTC_TIMEOUT')) {
		define('WPTC_TIMEOUT', 21);
	}
	$max_execution_time = WPTC_TIMEOUT - $reduce_sec;
	if ($time_diff >= $max_execution_time) {
		dark_debug($time_diff, "--------cutin ya--------");
		return true;
	} else {
		// dark_debug($time_diff, "--------allow--------");
	}
	return false;
}

// function replace_slashes_wptc($directory_name) {
// 	return str_replace(array("/"), DIRECTORY_SEPARATOR, $directory_name);
// }

// function replace_slashes_windows_wptc($path) {
// 	// return realpath($directory_name); //  below code is working well for it
// 	$path = str_replace(array(DIRECTORY_SEPARATOR, '\\'), DIRECTORY_SEPARATOR, $path);
// 	$parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
// 	$absolutes = array();
// 	foreach ($parts as $part) {
// 		if ('.' == $part) {
// 			continue;
// 		}

// 		if ('..' == $part) {
// 			array_pop($absolutes);
// 		} else {
// 			$absolutes[] = $part;
// 		}
// 	}
// 	if (DIRECTORY_SEPARATOR == '/') {
// 		return '/' . implode(DIRECTORY_SEPARATOR, $absolutes);
// 	}
// 	return implode(DIRECTORY_SEPARATOR, $absolutes);
// }

function get_tcsanitized_home_path() {
	//If site address and WordPress address differ but are not in a different directory
	//then get_home_path will return '/' and cause issues.
	$home_path = WPTC_ABSPATH;
	// if ($home_path == '/') {
	// 	$home_path = WPTC_ABSPATH;
	// }
	if (WPTC_DARK_TEST_SIMPLE) {
		$home_path = WPTC_ABSPATH . WPTC_WP_CONTENT_DIR .'/plugins/dark/';
	}
	return rtrim(wp_normalize_path($home_path), '/');
}

function get_uploadDir(){
	$options_obj = WPTC_Factory::get('config');
	if ($options_obj->get_option('in_progress_restore') || $options_obj->get_option('is_running_restore')) {
		$uploadDir['basedir'] = WPTC_WP_CONTENT_DIR.'/uploads';
	} else {
		$uploadDir = wp_upload_dir();
	}
	return $uploadDir;
}

function get_dirs_to_exculde_wptc() {
	$uploadDir = get_uploadDir();
	$upload_dir_path = wp_normalize_path($uploadDir['basedir']);
	$path = array(
			trim(WPTC_WP_CONTENT_DIR)."/managewp/backups",
			trim(WPTC_WP_CONTENT_DIR) . "/" . md5('iwp_mmb-client') . "/iwp_backups",
			trim(WPTC_WP_CONTENT_DIR)."/infinitewp",
			trim(WPTC_WP_CONTENT_DIR)."/".md5('mmb-worker')."/mwp_backups",
			trim(WPTC_WP_CONTENT_DIR)."/backupwordpress",
			trim(WPTC_WP_CONTENT_DIR)."/contents/cache",
			trim(WPTC_WP_CONTENT_DIR)."/content/cache",
			trim(WPTC_WP_CONTENT_DIR)."/cache",
			trim(WPTC_WP_CONTENT_DIR)."/logs",
			trim(WPTC_WP_CONTENT_DIR)."/old-cache",
			trim(WPTC_WP_CONTENT_DIR)."/w3tc",
			trim(WPTC_WP_CONTENT_DIR)."/cmscommander/backups",
			trim(WPTC_WP_CONTENT_DIR)."/gt-cache",
			trim(WPTC_WP_CONTENT_DIR)."/wfcache",
			trim(WPTC_WP_CONTENT_DIR)."/widget_cache",
			trim(WPTC_WP_CONTENT_DIR)."/bps-backup",
			trim(WPTC_WP_CONTENT_DIR)."/old-cache",
			trim(WPTC_WP_CONTENT_DIR)."/updraft",
			trim(WPTC_WP_CONTENT_DIR)."/nfwlog",
			trim(WPTC_WP_CONTENT_DIR)."/upgrade",
			trim(WPTC_WP_CONTENT_DIR)."/wflogs",
			trim(WPTC_WP_CONTENT_DIR)."/tmp",
			trim(WPTC_WP_CONTENT_DIR)."/backups",
			trim(WPTC_WP_CONTENT_DIR)."/updraftplus",
			trim(WPTC_WP_CONTENT_DIR)."/wishlist-backup",
			trim(WPTC_WP_CONTENT_DIR)."/wptouch-data/infinity-cache/",
			trim(WPTC_WP_CONTENT_DIR)."/mysql.sql",
			trim(WPTC_WP_CONTENT_DIR)."/DE_clTimeTaken.php",
			trim(WPTC_WP_CONTENT_DIR)."/DE_cl.php",
			trim(WPTC_WP_CONTENT_DIR)."/DE_clMemoryPeak.php",
			trim(WPTC_WP_CONTENT_DIR)."/DE_clMemoryUsage.php",
			trim(WPTC_WP_CONTENT_DIR)."/DE_clCalledTime.php",
			trim(WPTC_WP_CONTENT_DIR)."/DE_cl_func_mem.php",
			trim(WPTC_WP_CONTENT_DIR)."/DE_cl_func.php",
			trim(WPTC_WP_CONTENT_DIR)."/DE_cl_server_call_log_wptc.php",
			trim(WPTC_WP_CONTENT_DIR)."/DE_cl_dev_log_auto_update.php",
			trim(WPTC_WP_CONTENT_DIR)."/DE_cl_dev_log_auto_update.txt",
			trim(WPTC_WP_CONTENT_DIR)."/debug.log",
			trim(WPTC_WP_CONTENT_DIR)."/Dropbox_Backup",
			trim(WPTC_WP_CONTENT_DIR)."/backup-db",
			trim(WPTC_WP_CONTENT_DIR)."/updraft",
			trim(WPTC_WP_CONTENT_DIR)."/w3tc-config",
			trim(WPTC_WP_CONTENT_DIR)."/aiowps_backups",
			rtrim(trim(WPTC_PLUGIN_DIR), '/'), //WPTC plugin's file path
			$upload_dir_path."/wp-clone",
			$upload_dir_path."/db-backup",
			$upload_dir_path."/ithemes-security",
			$upload_dir_path."/mainwp/backup",
			$upload_dir_path."/backupbuddy_backups",
			$upload_dir_path."/vcf",
			$upload_dir_path."/pb_backupbuddy",
			$upload_dir_path."/sucuri",
			$upload_dir_path."/aiowps_backups",
			$upload_dir_path."/gravity_forms",
			$upload_dir_path."/mainwp",
			$upload_dir_path."/snapshots",
			$upload_dir_path."/wp-clone",
			$upload_dir_path."/wp_system",
			$upload_dir_path."/wpcf7_captcha",
			$upload_dir_path."/wc-logs",
			$upload_dir_path."/siteorigin-widgets",
			$upload_dir_path."/wp-hummingbird-cache",
			$upload_dir_path."/wp-security-audit-log",
			$upload_dir_path."/freshizer",
			$upload_dir_path."/report-cache",
			$upload_dir_path."/cache",
			$upload_dir_path."/et_temp",
			WPTC_ABSPATH."wp-admin/error_log",
			WPTC_ABSPATH."wp-admin/php_errorlog",
			WPTC_ABSPATH."error_log",
			WPTC_ABSPATH."error.log",
			WPTC_ABSPATH."debug.log",
			WPTC_ABSPATH."WS_FTP.LOG",
			WPTC_ABSPATH."security.log",
			WPTC_ABSPATH."wp-tcapsule-bridge.zip",
			WPTC_ABSPATH."dbcache",
			WPTC_ABSPATH."pgcache",
			WPTC_ABSPATH."objectcache",
		);
	return $path;
}

function setTcCookieNow($name, $value = false) {
	$options_obj = WPTC_Factory::get('config');

	if (!$value) {
		$value = microtime(true);
	}

	$contents[$name] = $value;
	$_GLOBALS['this_cookie'] = $contents;
	$options_obj->set_option('this_cookie', serialize($contents));

	return true;
}

function getTcCookie($name) {
	$options_obj = WPTC_Factory::get('config');
	if (!$options_obj->get_option('this_cookie')) {
		return false;
	} else {
		$contents = @unserialize($options_obj->get_option('this_cookie'));
		if (!isset($contents[$name])) {
			return false;
		}
		return $contents[$name];
	}
}

function deleteTcCookie() {

	$options_obj = WPTC_Factory::get('config');
	$options_obj->set_option('this_cookie', false);

	return true;
}

function maybe_call_again($need_return = NULL) {
	global $start_time_tc_bridge;
	global $start_db_time_tc;

	if (!defined('WPTC_TIMEOUT')) {
		define('WPTC_TIMEOUT', 23);
	}

	if (!empty($start_time_tc_bridge) && (microtime(true) - $start_time_tc_bridge) >= WPTC_TIMEOUT) {
		echo json_encode("wptcs_callagain_wptce");
		$end_db_time_tc = microtime(true) - $start_db_time_tc;
		manual_debug_wptc('', 'exitingBridge');
		if ($need_return == 1) {
			return true;
		}
		exit;
	}
}

function wptc_temp_copy() {
	global $wp_filesystem;
	if (!$wp_filesystem) {
		initiate_filesystem_wptc();
		if (empty($wp_filesystem)) {
			send_response_wptc('FS_INIT_FAILED-016');
			return false;
		}
	}
}

function check_is_file_from_file_name_wptc($file_name) {
	$base_name_rseult = basename($file_name);

	$exploded = explode('.', $base_name_rseult);

	if (count($exploded) > 1) {
		return true;
	}

	return false;
}

if (!function_exists('initialize_manual_debug_wptc')) {
	function initialize_manual_debug_wptc($conditions = '') {
		if (file_exists(WPTC_WP_CONTENT_DIR . '/DE_clMemoryPeak.php')) {
			@unlink(WPTC_WP_CONTENT_DIR . '/DE_clMemoryPeak.php');
		}
		if (file_exists(WPTC_WP_CONTENT_DIR . '/DE_clMemoryUsage.php')) {
			@unlink(WPTC_WP_CONTENT_DIR . '/DE_clMemoryUsage.php');
		}
		if (file_exists(WPTC_WP_CONTENT_DIR . '/DE_clTimeTaken.php')) {
			@unlink(WPTC_WP_CONTENT_DIR . '/DE_clTimeTaken.php');
		}
		global $debug_count, $every_count;
		$debug_count = 0;
		$every_count = 0;

		$this_memory_peak_in_mb = memory_get_peak_usage();
		$this_memory_peak_in_mb = $this_memory_peak_in_mb / 1048576;
		$this_memory_in_mb = memory_get_usage();
		$this_memory_in_mb = $this_memory_in_mb / 1048576;
		$this_time_taken = 0.2;

		file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_clMemoryPeak.php', $debug_count . $printText . "  " . round($this_memory_peak_in_mb, 2) . "\n");
		file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_clMemoryUsage.php', $debug_count . $printText . "  " . round($this_memory_in_mb, 2) . "\n");
		file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_clTimeTaken.php', $debug_count . $printText . "  " . round($this_time_taken, 2) . "\n");
	}
}

if (!function_exists('manual_debug_wptc')) {
	function manual_debug_wptc($conditions = '', $printText = '', $forEvery = 0) {
		if (defined('WPTC_DARK_TEST') && WPTC_DARK_TEST) {
			global $debug_count;
			$debug_count++;
			$printText = '-' . $printText;

			global $every_count;
			//$conditions = 'printOnly';

			if (empty($forEvery)) {
				print_memory_debug_wptc($debug_count, $conditions, $printText);
			} else {
				$every_count++;
				if ($every_count % $forEvery == 0) {
					print_memory_debug_wptc($debug_count, $conditions, $printText);
					return true;
				}
			}
		}
	}
}

if (!function_exists('print_memory_debug_wptc')) {
	function print_memory_debug_wptc($debug_count, $conditions = '', $printText = '') {
		global $wptc_profiling_start;
		$config = WPTC_Factory::get('config');
		// $wptc_profiling_start = $config->get_option('wptc_profiling_start');

		$this_memory_peak_in_mb = memory_get_peak_usage();
		$this_memory_peak_in_mb = $this_memory_peak_in_mb / 1048576;
		$this_memory_in_mb = memory_get_usage();
		$this_memory_in_mb = $this_memory_in_mb / 1048576;
		$this_time_taken = microtime(true) - $wptc_profiling_start;

		$human_readable_profile_start = date('H:i:s', $wptc_profiling_start);

		if ($conditions == 'printOnly') {
			if ($this_memory_peak_in_mb >= 34) {
				file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_clMemoryPeak.php', $debug_count . $printText . "  " . round($this_memory_peak_in_mb, 2) . "\n", FILE_APPEND);
				file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_clMemoryUsage.php', $debug_count . $printText . "  " . round($this_memory_in_mb, 2) . "\n", FILE_APPEND);
				file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_clTimeTaken.php', $debug_count . $printText . "  " . round($this_time_taken, 2) . "\n", FILE_APPEND);
				file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_clCalledTime.php', $debug_count . $printText . $human_readable_profile_start . "  " . round($wptc_profiling_start, 2) . "\n", FILE_APPEND);
			}
		} else {
			file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_clMemoryPeak.php', $debug_count . $printText . "  " . round($this_memory_peak_in_mb, 2) . "\n", FILE_APPEND);
			file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_clMemoryUsage.php', $debug_count . $printText . "  " . round($this_memory_in_mb, 2) . "\n", FILE_APPEND);
			file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_clTimeTaken.php', $debug_count . $printText . "  " . round($this_time_taken, 2) . "\n", FILE_APPEND);
			file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_clCalledTime.php', $debug_count . $printText . $human_readable_profile_start . "  " . round($wptc_profiling_start, 2) . "\n", FILE_APPEND);
		}
	}
}

if (!function_exists('single_print_memory_debug_wptc')) {
	function single_print_memory_debug_wptc($conditions = '', $printText = '') {
		global $debug_count;
		$debug_count++;

		$this_time_taken = microtime(true);

		//file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_clCalledTime.php', $debug_count . $printText . "  " . round($this_time_taken, 2) . "\n", FILE_APPEND);
	}
}

if (!function_exists('dark_debug')) {
	function dark_debug($value = null, $key = null, $is_print_all_time = true, $forEvery = 0) {
		if (defined('WPTC_DARK_TEST') && WPTC_DARK_TEST && $is_print_all_time) {
			try {
				global $every_count;
				//$conditions = 'printOnly';

				$usr_time = microtime(true);
				$raw_time = microtime(true);
				if (function_exists('user_formatted_time_wptc')) {
					$usr_time = user_formatted_time_wptc(time());
				}

				if (empty($forEvery)) {
					@file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_cl.php', "\n -----$key------------$usr_time --- $raw_time  ----- " . var_export($value, true) . "\n", FILE_APPEND);
				} else {
					$every_count++;
					if ($every_count % $forEvery == 0) {
						@file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_cl.php', "\n -----$key------- " . var_export($value, true) . "\n", FILE_APPEND);
						return true;
					}
				}
			} catch (Exception $e) {
				@file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_cl.php', "\n -----$key----------$usr_time --- $raw_time  ------ " . var_export(serialize($value), true) . "\n", FILE_APPEND);
			}
		}
	}
}

if (!function_exists('dark_debug_2')) {
	function dark_debug_2($value = null, $key = null, $is_print_all_time = true, $forEvery = 0) {
		if (defined('WPTC_DARK_TEST') && WPTC_DARK_TEST && $is_print_all_time) {
			try {
				global $every_count;
				//$conditions = 'printOnly';

				$usr_time = time();
				if (function_exists('user_formatted_time_wptc')) {
					$usr_time = user_formatted_time_wptc(time());
				}

				if (empty($forEvery)) {
					@file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_cl_2.php', "\n -----$key------------$usr_time----- " . var_export($value, true) . "\n", FILE_APPEND);
				} else {
					$every_count++;
					if ($every_count % $forEvery == 0) {
						@file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_cl_2.php', "\n -----$key------- " . var_export($value, true) . "\n", FILE_APPEND);
						return true;
					}
				}
			} catch (Exception $e) {
				@file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_cl_2.php', "\n -----$key----------$usr_time------ " . var_export(serialize($value), true) . "\n", FILE_APPEND);
			}
		}
	}
}

if (!function_exists('dark_debug_func_map')) {
	function dark_debug_func_map($value = null, $key, $is_print_all_time = true) {
		if (defined('WPTC_DARK_TEST') && WPTC_DARK_TEST && $is_print_all_time) {
			file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_cl_func.php', "\n -----$key------- " . var_export($value, true) . "\n", FILE_APPEND);
			//echo serialize(array('result' => $value)) . $key;

			$this_memory_peak_in_mb = memory_get_peak_usage();
			$this_memory_peak_in_mb = $this_memory_peak_in_mb / 1048576;
			//file_put_contents(WPTC_WP_CONTENT_DIR . '/DE_cl_func_mem.php', "\n -----$key---in MB---- " . var_export($this_memory_peak_in_mb, true) . "\n", FILE_APPEND);
		}
	}
}
if (!function_exists('get_home_page_url')) {
	function get_home_page_url() {
		$config = WPTC_Factory::get('config');
		$site_url = $config->get_option('site_url_wptc');
		return $site_url;
	}
}
if (!function_exists('get_monitor_page_url')) {
	function get_monitor_page_url() {
		$monitor_url = network_admin_url('admin.php?page=wp-time-capsule-monitor');
		return $monitor_url;
	}
}
if (!function_exists('get_options_page_url')) {
	function get_options_page_url() {
		$options_url = network_admin_url('admin.php?page=wp-time-capsule');
		return $options_url;
	}
}

if (!function_exists('get_wptc_cron_url')) {
	function get_wptc_cron_url() {
		return trailingslashit(home_url());
	}
}

if (!function_exists('user_formatted_time_wptc')) {
	function user_formatted_time_wptc($timestamp = '', $format = false) {
		if (empty($timestamp)) {
			return false;
		}
		if (empty($format)) {
			$usr_formated_time = WPTC_Factory::get('config')->get_wptc_user_today_date_time('g:i:s a Y-m-d', $timestamp);
		} else {
			$usr_formated_time = WPTC_Factory::get('config')->get_wptc_user_today_date_time($format, $timestamp);
		}
		return $usr_formated_time;
	}
}

if (!function_exists('send_response_wptc')) {
	function send_response_wptc($status = null, $type = null, $data = null, $is_log =0) {
		if (!is_wptc_server_req() && !is_wptc_node_server_req()) {
			return false;
		}
		$config = WPTC_Factory::get('config');
		dark_debug(get_backtrace_string_wptc(),'---------send_response_wptc-----------------');
		if (empty($is_log)) {
			$post_arr['status'] = $status;
			$post_arr['type'] = $type;
			$post_arr['version'] = WPTC_VERSION;
			$post_arr['source'] = 'WPTC';
			$post_arr['scheduled_time'] = $config->get_option('schedule_time_str');
			$post_arr['timezone'] = $config->get_option('wptc_timezone');
			$post_arr['last_backup_time'] = $config->get_option('last_backup_time');
			if (!empty($data)) {
				$post_arr['progress'] = $data;
			}
		} else {
			$post_arr = $data;
		}
		// dark_debug($post_arr, '---------$post_arr------------');
		echo "<WPTC_START>".json_encode($post_arr)."<WPTC_END>";
		die();
	}
}

if (!function_exists('reset_backup_related_settings_wptc')) {
	function reset_backup_related_settings_wptc() {
		dark_debug(array(), '-----------reset_backup_related_settings_wptc-------------');
		$config = WPTC_Factory::get('config');
		//resetting backup config-options
		$config->set_option('gotfileslist', false);
		$config->set_option('in_progress', false);
		$config->set_option('total_file_count', 0);
		$config->set_option('is_running', false);
		$config->set_option('ignored_files_count', 0);
		$config->set_option('supposed_total_files_count', 0);
		$config->set_option('wptc_sub_cycle_running', 0);
		$config->set_option('wptc_main_cycle_running', 0);
		$config->set_option('schedule_backup_running', false);
		// $config->set_option('cached_wptc_g_drive_folder_id', 0);
		// $config->set_option('cached_g_drive_this_site_main_folder_id', 0);
		$config->set_option('is_meta_data_backup_failed', '');
		$config->set_option('meta_data_upload_offset', 0);
		$config->set_option('meta_data_upload_id', '');
		$config->set_option('meta_data_upload_s3_part_number', '');
		$config->set_option('meta_data_upload_s3_parts_array', '');
		$config->set_option('meta_data_backup_process', '');
		$config->set_option('backup_before_update_progress', false);
		$config->set_option('wptc_current_backup_type', 0);
		$config->set_option('recent_restore_ping', false);
		$config->set_option('wptc_update_progress', false);
		$config->set_option('bbu_upgrade_process_running', false);

		$config->set_option('file_list_point', 0);
		global $wpdb;
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_current_process`");
	}
}

if (!function_exists('remove_response_junk')) {
	function remove_response_junk(&$response){
		$headerPos = stripos($response, '<WPTCHEADER');
		if($headerPos !== false){
			$response = substr($response, $headerPos);
			$response = substr($response, strlen('<WPTCHEADER>'), stripos($response, '</ENDWPTCHEADER')-strlen('<WPTCHEADER>'));
		}
	}
}

if (!function_exists('die_with_json_encode')) {
	function die_with_json_encode($msg = 'empty data', $escape = 0){
		switch ($escape) {
			case 1:
			die(json_encode($msg, JSON_UNESCAPED_SLASHES));
			case 2:
			die(json_encode($msg, JSON_UNESCAPED_UNICODE));
		}
		die(json_encode($msg));
	}
}

if (!function_exists('override_is_dir')) {
	function override_is_dir($good_path){
		if (is_dir($good_path)) {
			return true;
		} else {
			$ext = pathinfo($good_path, PATHINFO_EXTENSION);
			if (empty($ext)) {
				if (is_file($good_path)) {
					return false;
				} else{
					return true;
				}
			} else {
				return false;
			}
		}
	}
}

if (!function_exists('remove_secret')) {
	function remove_secret($file, $basename = true) {
		if (preg_match('/-wptc-secret$/', $file)) {
			$file = substr($file, 0, strrpos($file, '.'));
		}
		if ($basename) {
			return basename($file);
		}
		return $file;
	}
}

function addTrailingSlash($string) {
	return removeTrailingSlash($string) . '/';
}

function removeTrailingSlash($string) {
	return rtrim($string, '/');
}

function ftp_file_sys_failed_msg($msg){
	dark_debug($msg, '---------$msg------------');
	header('Content-Type: application/json');
	die(json_encode(array('error' => $msg)));
}

// if (!function_exists('set_basename_wp_content_dir')) {
// 	function set_basename_wp_content_dir(){
// 		$basename = basename(WPTC_WP_CONTENT_DIR);
// 		if (!defined('WPTC_WP_CONTENT_DIR')) {
// 			$wptc_base_wp_content_dir = $config->get_option('WPTC_WP_CONTENT_DIR');
// 			if (!empty($wptc_base_wp_content_dir)) {
// 				return $wptc_base_wp_content_dir;
// 			}
// 		}
// 		if (!defined('WPTC_WP_CONTENT_DIR')) {
// 			define('WPTC_WP_CONTENT_DIR', $basename);
// 		}
// 		$config = WPTC_Factory::get('config');
// 		if ($config->get_option('WPTC_WP_CONTENT_DIR')) {
// 			$config->set_option('WPTC_WP_CONTENT_DIR', WPTC_WP_CONTENT_DIR);
// 		}
// 	}
// }

function extract_headers($header){
	$all_headers = getallheaders();
	// dark_debug($all_headers, '---------$all_headers------------');
	if(!empty($all_headers[$header])){
		return $token_header = $all_headers[$header];
	}
	return false;
}

if (!function_exists('getallheaders'))  {
	function getallheaders()
	{
		if (!is_array($_SERVER)) {
			return array();
		}

		$headers = array();
		foreach ($_SERVER as $name => $value) {
			if (substr($name, 0, 5) == 'HTTP_') {
				$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			}
		}
		return $headers;
	}
}

function decode_auth_token($token){
	$tokenObj = explode('.', $token);
	$base64Url = $tokenObj[1];
	$base64Url = str_replace('-', '+', $base64Url);
	$base64Url = str_replace('_', '/', $base64Url);
	$decoded_data = base64_decode($base64Url);
	if (empty($decoded_data)) {
		return false;
	}
	$auth_data = json_decode($decoded_data, true);
	if (empty($auth_data)) {
		return false;
	}
	return $auth_data['appId'];
}

function initiate_filesystem_wptc() {
	$is_admin_call = false;
	if(is_admin()){
		$is_admin_call = true;
		global $initiate_filesystem_wptc_direct_load;
		if (empty($initiate_filesystem_wptc_direct_load)) {
			$initiate_filesystem_wptc_direct_load = true;
		} else{
			if (!is_wptc_server_req()) {
				return false;
			}
		}
	}

	if(!is_wptc_server_req() && $is_admin_call === false){
		return false;
	}
	$creds = request_filesystem_credentials("", "", false, false, null);
	if (false === $creds) {
		return false;
	}

	if (!WP_Filesystem($creds)) {
		return false;
	}
}

function add_protocol_common($URL) {
	$URL = trim($URL);
	return (substr($URL, 0, 7) == 'http://' || substr($URL, 0, 8) == 'https://')
		? $URL
		: 'http://'.$URL;
}

function get_single_iterator_obj($path) {
	$path = rtrim($path, '/');
	$source = realpath($path);
	$Mdir = null;

	if (!is_readable($source)) {
		return false;
	}

	$Mdir = new RecursiveDirectoryIterator($source , RecursiveDirectoryIterator::SKIP_DOTS);
	$Mfile = new RecursiveIteratorIterator($Mdir, RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);
	return $Mfile;
}

function get_common_multicall_key_status($key, $file){
	$config = WPTC_Factory::get('config');
	$raw_data = $config->get_option($key);
	dark_debug($raw_data, '---------$raw_data get_common_multicall_key_status------------');
	if (empty($raw_data)) {
		return 0;
	}
	$data = unserialize($raw_data);
	dark_debug($data, '---------$data get_common_multicall_key_status------------');
	if (isset($data[$file])) {
		dark_debug($data[$file], '---------$data file get_common_multicall_key_status------------');
		return $data[$file];
	}
	return 0;
}

function update_common_multicall_key_status($key, $file, $actual_files_count){
	$config = WPTC_Factory::get('config');
	$raw_data = $config->get_option($key);
	if (empty($raw_data)) {
		$current_status = array($file => $actual_files_count);
		dark_debug($current_status, '---------$current_status file------------');
		$config->set_option($key, serialize($current_status));
		return false;
	}
	$data = unserialize($raw_data);
	dark_debug($data, '---------$raw_data file update_common_multicall_key_status------------');
	$data[$file] = $actual_files_count;
	dark_debug($data, '---------$data file update_common_multicall_key_status------------');
	$config->set_option($key, serialize($data));
}

function clear_common_multicall_key_status($key, $file){
	$config = WPTC_Factory::get('config');
	$raw_data = $config->get_option($key);
	if (empty($raw_data)) {
		return false;
	}
	$data = unserialize($raw_data);
	dark_debug($data, '---------$data clear_common_multicall_key_status------------');
	if (isset($data[$file])) {
		unset($data[$file]);
	}
	dark_debug($data, '---------$data clear_common_multicall_key_status------------');
	if (empty($data)) {
		return $config->set_option($key, false);
	}
	return $config->set_option($key, serialize($data));
}

function is_hash_required($file_path){
	if ( is_readable($file_path) && filesize($file_path) < WPTC_HASH_FILE_LIMIT) {
		return true;
	} else {
		return false;
	}
}

function get_radio_input_wptc($id, $value = '', $current_setting = '', $name = '') {
	$is_checked = '';
	if ($current_setting == $value) {
		$is_checked = 'checked';
	}

	$input = '';
	$input .= '<input name="'.$name.'" type="radio" id="' . $id . '"	' . $is_checked . ' value="' . $value . '">';

	return $input;
}

function error_alert_wptc_server($err_info_arr = array()) {
	$config = WPTC_Factory::get('config');

	$app_id = $config->get_option('appID');

	$email = trim($config->get_option('main_account_email', true));
	$email_encoded = base64_encode($email);

	$pwd = trim($config->get_option('main_account_pwd', true));
	$pwd_encoded = base64_encode($pwd);

	//$post_string = 'site_url=' . home_url() . "&pwd=" . $pwd_encoded . "&name=" . $name . "&email=" . $email . "&cloudAccount=" . $cloudAccount . "&connectedEmail" . $connectedEmail;

	$post_req = array(
		'app_id' => $app_id,
		'email' => $email_encoded,
		'site_url' => home_url(),
	);

	$post_arr = array_merge($post_req, $err_info_arr);
	dark_debug($post_arr, '---------$error_alert_wptc_server------------');
	$push_result = do_cron_call_wptc('users/alert', $post_arr);
}

function is_any_other_wptc_process_going_on() {
	if (apply_filters('is_any_staging_process_going_on', '')) {
		return true;
	}
	return false;
}

function stop_if_ongoing_backup_wptc(){
	if(is_any_ongoing_wptc_backup_process()){
		set_backup_in_progress_server(false);
	}
}

function purify_plugin_update_data_wptc($raw_upgrade_details) {
	$result = get_plugins();
	foreach ($raw_upgrade_details as $key => $value) {
		$upgrade_details[$value] = $result[$value]['Version'];
	}
	return $upgrade_details;
}

function purify_translation_update_data_wptc($raw_upgrade_details) {
	dark_debug($raw_upgrade_details, '---------purify_translation_update_data-------------');
	return $raw_upgrade_details;
}

function purify_theme_update_data_wptc($raw_upgrade_details) {
	dark_debug($raw_upgrade_details, '---------purify_theme_update_data-------------');
	return $raw_upgrade_details;
}

function purify_core_update_data_wptc($raw_upgrade_details) {
	dark_debug($raw_upgrade_details, '---------purify_core_update_data-------------');
	$transient = (array) wptc_mmb_get_transient('update_core');
	$std_obj_data = $transient['updates'][0];
	$std_obj_into_array = (array) $transient['updates'][0];
	// $data = $data[0];
	if ($std_obj_into_array['version'] == $raw_upgrade_details[0]) {
		dark_debug($std_obj_data, '---------$std_obj_data-------------');
		return $std_obj_data;
	}

	$config = WPTC_Factory::get('config');
	$config->set_option('bbu_note_view', serialize(array('type' => 'error', 'note' => 'WordPress upgrade version mismatch :(')));
	return false;
}

function is_wptc_table($tableName) {
	global $wpdb;
	//ignoring tables of wptc plugin
	$wp_prefix_with_tc_prefix = $wpdb->base_prefix . WPTC_TC_PLUGIN_NAME;
	$wptc_strpos = strpos($tableName, $wp_prefix_with_tc_prefix);

	if (false !== $wptc_strpos && $wptc_strpos === 0) {
		return true;
	}
	return false;
}


function is_wptc_file($file){
	if(stripos($file, WPTC_TC_PLUGIN_NAME) === FALSE){
		return false;
	}
	return true;
}

function wptc_mmb_get_transient($option_name) {
	if (trim($option_name) == '') {
		return FALSE;
	}
	global $wp_version;
	$transient = array();
	if (version_compare($wp_version, '2.7.9', '<=')) {
		return get_option($option_name);
	} else if (version_compare($wp_version, '2.9.9', '<=')) {
		$transient = get_option('_transient_' . $option_name);
		return apply_filters("transient_" . $option_name, $transient);
	} else {
		$transient = get_option('_site_transient_' . $option_name);
		return apply_filters("site_transient_" . $option_name, $transient);
	}
}

function is_wptc_server_req(){
	global $wptc_server_req;
	if (isset($wptc_server_req) && $wptc_server_req === true) {
		return true;
	}
	return false;
}

function is_wptc_node_server_req(){
	global $wptc_node_server_req;
	if (isset($wptc_node_server_req) && $wptc_node_server_req === true) {
		return true;
	}
	return false;
}

function trim_value_wptc(&$v){
	$v = trim($v);
}

if(!function_exists('wptc_get_file_size')){
	function wptc_get_file_size($file)
	{
		clearstatcache();
		$normal_file_size = filesize($file);
		if(($normal_file_size !== false)&&($normal_file_size >= 0))
		{
			return $normal_file_size;
		}
		else
		{
			$file = realPath($file);
			if(!$file)
			{
				echo 'wptc_get_file_size_error : realPath error';
			}
			$ch = curl_init("file://" . $file);
			curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_FILE);
			curl_setopt($ch, CURLOPT_NOBODY, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, true);
			$data = curl_exec($ch);
			$curl_error = curl_error($ch);
			curl_close($ch);
			if ($data !== false && preg_match('/Content-Length: (\d+)/', $data, $matches)) {
				return (string) $matches[1];
			}
			else
			{
				echo 'wptc_get_file_size_error : '.$curl_error;
				return $normal_file_size;
			}

		}
	}
}

if (!function_exists('json_encode')) {
	function json_encode($a=false) {
		if (is_null($a)) return 'null';
		if ($a === false) return 'false';
		if ($a === true) return 'true';
		if (is_scalar($a)) {
			if (is_float($a)) {
				// Always use "." for floats.
				return floatval(str_replace(",", ".", strval($a)));
			}

			if (is_string($a)) {
				static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
				return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
			} else{
				return $a;
			}
		}
		$isList = true;
		for ($i = 0, reset($a); $i < count($a); $i++, next($a))	{
			if (key($a) !== $i) {
				$isList = false;
				break;
			}
		}
		$result = array();
		if ($isList) {
			foreach ($a as $v) $result[] = jsonEncoder($v);
			return '[' . join(',', $result) . ']';
		}
		else {
			foreach ($a as $k => $v) $result[] = jsonEncoder($k).':'.jsonEncoder($v);
			return '{' . join(',', $result) . '}';
		}
	}
}

if (!function_exists('jsonEncoder')) {
	function jsonEncoder( $data, $options = 0, $depth = 512 ) {
		if ( version_compare( PHP_VERSION, '5.5', '>=' ) ) {
			$args = array( $data, $options, $depth );
		} elseif ( version_compare( PHP_VERSION, '5.3', '>=' ) ) {
			$args = array( $data, $options );
		} else {
			$args = array( $data );
		}
		$json = @call_user_func_array( 'json_encode', $args );

		if ( false !== $json && ( version_compare( PHP_VERSION, '5.5', '>=' ) || false === strpos( $json, 'null' ) ) )  {
			return $json;
		}

		$args[0] = jsonCompatibleCheck( $data, $depth );
		return @call_user_func_array( 'json_encode', $args );
	}
}

function is_chunk_hash_required($file_path){
	if (filesize($file_path) > HASH_CHUNK_LIMIT) {
		// dark_debug(filesize($file_path), '----filesize($file_path)----------------');
		// dark_debug(HASH_CHUNK_LIMIT, '----HASH_CHUNK_LIMIT---------------');
		return true;
	} else {
		return false;
	}
}

function compute_Md5_Hash($file_path, $limit = 0, $offset = 0) {
	// dark_debug(func_get_args(), '---------func_get_args()------------');
	$is_hash_required = is_hash_required($file_path);
	// dark_debug($is_hash_required, '---------$is_hash_required------------');
	if (!$is_hash_required) {
		return null;
	}
	$chunk_hash = is_chunk_hash_required($file_path);
	// dark_debug($chunk_hash, '---------$chunk_hash------------');
	if ($chunk_hash === false) {
		// md5_file is always faster if we don't chunk the file
		$hash = md5_file($file_path);

		return $hash !== false ? $hash : null;
	}
	$ctx = hash_init('md5');
	if (!$ctx) {
		// Fail to initialize file hashing
		return null;
	}

	$limit = filesize($file_path) - $offset;

	$handle = @fopen($file_path, "rb");
	if ($handle === false) {
		// Failed opening file, cleanup hash context
		hash_final($ctx);

		return null;
	}

	fseek($handle, $offset);

	while ($limit > 0) {
		// Limit chunk size to either our remaining chunk or max chunk size
		$chunkSize = $limit < HASH_CHUNK_LIMIT ? $limit : HASH_CHUNK_LIMIT;
		$limit -= $chunkSize;

		$chunk = fread($handle, $chunkSize);
		hash_update($ctx, $chunk);
	}

	fclose($handle);

	return hash_final($ctx);
}

function _dupx_array_rtrim(&$value) {
	$value = rtrim($value, '\/');
}

function is_zero_bytes_file($file){
	if (!file_exists($file)) {
		return false;
	}
	return (filesize($file) === 0) ? true : false;
}

function save_files_zero_bytes($is_zero_bytes_file, $file){
	if (!$is_zero_bytes_file) {
		return false;
	}

	$config = WPTC_Factory::get('config');
	$raw = $config->get_option('zero_bytes_files_list', true);
	if (empty($raw)) {
		return $config->set_option('zero_bytes_files_list', serialize(array($file)));
	}
	dark_debug($raw, '---------------$raw-----------------');
	$unserialized = unserialize($raw);
	dark_debug($unserialized, '---------------$unserialize1-----------------');
	if (empty($unserialized)) {
		return $config->set_option('zero_bytes_files_list', serialize(array($file)));
	}
	dark_debug($unserialized, '---------------$unserialize-----------------');
	$unserialized[] = $file;
	dark_debug($unserialized, '---------------$unserialize new-----------------');
	$unserialized = array_unique($unserialized);
	dark_debug($unserialized, '---------------$unserialized-----------------');
	return $config->set_option('zero_bytes_files_list', serialize($unserialized));
}

function is_file_in_zero_bytes_list($file){
	dark_debug(array(), '---------------is_file_in_zero_bytes_list-----------------');
	$config = WPTC_Factory::get('config');
	$raw = $config->get_option('zero_bytes_files_list', true);
	if (empty($raw)) {
		return false;
	}
	dark_debug($raw, '---------------$raw-----------------');
	$unserialized = unserialize($raw);
	dark_debug($unserialized, '---------------$unserialize1-----------------');
	if (empty($unserialized)) {
		return false;
	}

	if (in_array($file, $unserialized)) {
		return true;
	}

	return false;
}

function get_home_path_wptc(){
	$override_script_filename = WPTC_ABSPATH.'wp-admin/admin.php'; // assume all cron calls like admin calls
	$home    = set_url_scheme( get_option( 'home' ), 'http' );
	$siteurl = set_url_scheme( get_option( 'siteurl' ), 'http' );
	if ( ! empty( $home ) && 0 !== strcasecmp( $home, $siteurl ) ) {
		$wp_path_rel_to_home = str_ireplace( $home, '', $siteurl ); /* $siteurl - $home */
		$pos = strripos( str_replace( '\\', '/',  $override_script_filename), trailingslashit( $wp_path_rel_to_home ) );
		$home_path = substr( $override_script_filename, 0, $pos );
		$home_path = trailingslashit( $home_path );
	} else {
		$home_path = WPTC_ABSPATH;
	}

	return str_replace( '\\', '/', $home_path );
}

function set_server_req_wptc($node_server_req = false){
	global $wptc_server_req;
	$wptc_server_req = true;
	if ($node_server_req) {
		global $wptc_node_server_req;
		$wptc_node_server_req = true;
	}
}

function is_windows_machine_wptc(){
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
		return true;
	}
	return false;
}

function is_server_writable_wptc(){

	if (!function_exists('get_filesystem_method')) {
		include_once ABSPATH.'wp-admin/includes/file.php';
	}

	if ((!defined('FTP_HOST') || !defined('FTP_USER')) && (get_filesystem_method(array(), false) != 'direct')) {
		return false;
	} else {
		return true;
	}
}

function wptc_mmb_delete_transient($option_name) {
	if (trim($option_name) == '') {
		return FALSE;
	}

	global $wp_version;
	if (version_compare($wp_version, '2.7.9', '<=')) {
		delete_option($option_name);
	} else if (version_compare($wp_version, '2.9.9', '<=')) {
		delete_option('_transient_' . $option_name);
	} else {
		delete_option('_site_transient_' . $option_name);
	}
}

function admin_wp_loaded_wptc(){
	require_once ABSPATH.'wp-admin/includes/admin.php';

	//some themes causing fatal error due to this so commenting this temporarely.
	// do_action('admin_init');

	if (function_exists('wp_clean_update_cache')) {
		wp_clean_update_cache();
	}

	@set_current_screen();
	do_action('load-update-core.php');

	wptc_mmb_delete_transient('update_plugins');
	@wp_update_plugins();


	wptc_mmb_delete_transient('update_themes');
	@wp_update_themes();

	wptc_mmb_delete_transient('update_core');
	@wp_version_check();

	/** @handled function */
	wp_version_check(array(), true);

	$update_plugins = get_site_transient( 'update_plugins' );
	set_site_transient( 'update_plugins', $update_plugins );

	$update_themes = get_site_transient( 'update_themes' );
	set_site_transient( 'update_themes', $update_themes );
}