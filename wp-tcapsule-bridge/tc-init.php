<?php
/*
------time capsule------
1.this file is totally used to move the files from the tempFolder to the actual root folder of wp
2.this file uses files from wordpress and also plugins to perform the copying actions
 */
set_time_limit(0);

global $start_time_tc_bridge, $post_data, $is_staging_req;
$start_time_tc_bridge = microtime(true);
$is_staging_req = 0;

$post_data = $_REQUEST;

$is_meta_data_req = is_meta_data_req();
$staging_req_type = staging_req_type();
define_constants();

require_once dirname(__FILE__). '/' ."common_include_files.php";

$Common_Include_Files = new Common_Include_Files('tc-init');

if (empty($is_meta_data_req) && empty($staging_req_type)) {
	$Common_Include_Files->init();
	init_db_connection();
	log_recent_calls();
	init_restore();
	WPTC_Factory::get('Debug_Log')->wptc_log_now('Normal restore copy called', 'RESTORE');
	dark_debug(array(), '-----------normal restore copy request-------------');
} else if($is_meta_data_req){
	$Common_Include_Files->init('meta_restore');
	init_db_connection();
	log_recent_calls();
	dark_debug(array(), '-----------is_meta_data_req restore copy request-------------');
} else if($staging_req_type){
	$post_data = decode_staging_request();
	$is_staging_req = 1;
	$initialize = 0;
	if ($staging_req_type == 1) {
		$post_data['initialize'] = true;
	}
	$Common_Include_Files->init();
	init_db_connection();
	log_recent_calls();
	WPTC_Factory::get('Debug_Log')->wptc_log_now('Staging restore copy called', 'RESTORE');
	init_restore();
	dark_debug(array(), '-----------staging_req_type restore copy request-------------');
}

set_server_req_wptc();

function log_recent_calls(){
	$options_obj = WPTC_Factory::get('config');
	$options_obj->set_option('recent_restore_ping', time());
}

function init_restore(){
	global $post_data, $is_staging_req;
	$options_obj = WPTC_Factory::get('config');

	//initialize file_system obj
	$creds = request_filesystem_credentials("", "", false, false, null);
	if (false === $creds) {
		return false;
	}

	if (!WP_Filesystem($creds)) {
		return false;
	}

	global $wp_filesystem;
	if (!$wp_filesystem) {
		initiate_filesystem_wptc();
		if (empty($wp_filesystem)) {
			send_response_wptc('FS_INIT_FAILED-018');
			return false;
		}
	}
	manual_debug_wptc('', 'insideBridge');

	//update the options table to indicate that bridge process is going on , only on the first call
	dark_debug($post_data, '---------$post_data------------');
	if (!empty($post_data) && !empty($post_data['initialize']) && $post_data['initialize'] == 'true') {
		$options_obj->set_option('is_bridge_process', true);
		$options_obj->set_option('restore_db_index', 0);
		$options_obj->set_option('restore_saved_index', 0);
		$options_obj->set_option('restore_db_process', true);
		WPTC_Factory::get('Debug_Log')->wptc_log_now('restore copy first called', 'RESTORE');
	}

	if (!($options_obj->get_option('is_bridge_process')) && !($options_obj->get_option('garbage_deleted'))) {
		echo json_encode('wptcs_over_wptce');
		return;
	}

	//prepare the from and to folders
	$actual_main_folder = ABSPATH;
	$this_site_name = basename($actual_main_folder);
	$actual_wp_content_folder = WP_CONTENT_DIR;

	$restore_temp_folder = $options_obj->get_option('backup_db_path') . '/tCapsule';
	$restore_temp_folder_fs_safe = $options_obj->wp_filesystem_safe_abspath_replace($restore_temp_folder);
	if (!empty($is_staging_req)) {
		$restore_temp_folder = $restore_temp_folder_fs_safe;
	}
	$content_name = basename($options_obj->get_option('backup_db_path'));
	$site_db_name = $options_obj->get_option('site_db_name');
	if ($content_name == 'uploads') {
		$restore_db_dump_file = $restore_temp_folder . '/wp-content/' . $content_name . '/tCapsule/backups/' . $site_db_name . '-backup.sql';
		$restore_db_dump_file_fs_safe = $restore_temp_folder_fs_safe . 'wp-content/' . $content_name . '/tCapsule/backups/' . $site_db_name . '-backup.sql';
	} else {
		$restore_db_dump_file = $restore_temp_folder . '/' . $content_name . '/tCapsule/backups/' . $site_db_name . '-backup.sql';
		$restore_db_dump_file_fs_safe = $restore_temp_folder_fs_safe . $content_name . '/tCapsule/backups/' . $site_db_name . '-backup.sql';
	}
	if (!empty($is_staging_req)) {
		$restore_db_dump_file = $restore_db_dump_file_fs_safe;
	}
	//check if the db restore process is already completed; if it is not completed do the DB restore
	if (($options_obj->get_option('restore_db_process'))) {
		//check if the sql file is selected during restore process ; if it doesnt exist then we dont need to do the restore db process
dark_debug($restore_temp_folder,'--------------$restore_temp_folder-------------');
dark_debug($restore_temp_folder_fs_safe,'--------------$restore_temp_folder_fs_safe-------------');
dark_debug($restore_db_dump_file,'--------------$restore_db_dump_file-------------');
dark_debug($restore_db_dump_file_fs_safe,'--------------$restore_db_dump_file_fs_safe-------------');
		if ($wp_filesystem->exists($restore_db_dump_file_fs_safe)) {
			WPTC_Factory::get('Debug_Log')->wptc_log_now('Got sql file for restore', 'RESTORE');
			dark_debug($is_staging_req, '---------$is_staging_req------------');
			// dark_debug($post_data['initialize'], '---------$post_dat initialize------------');
			if ($is_staging_req && $post_data['initialize']) {
				dark_debug(array(), '-----------staging------------');
				change_new_prefix_to_staging($options_obj, $restore_db_dump_file_fs_safe);
			}
			dark_debug(array(), '-----------db file found in this restore-------------');
			global $start_db_time_tc;
			$start_db_time_tc = microtime(true);
			start_maintenance_mode_wptc();
			WPTC_Factory::get('Debug_Log')->wptc_log_now('SQL file for restore started', 'RESTORE');
			$db_restore_result = tc_database_restore($restore_db_dump_file);
			WPTC_Factory::get('Debug_Log')->wptc_log_now('SQL file for restore end', 'RESTORE');
			$end_time_db_tc = microtime(true) - $start_db_time_tc;
			//on db restore completion - set the following values
			if ($db_restore_result) {
				$options_obj->set_option('restore_db_process', false);
				$options_obj->set_option('restore_db_index', 0);
				@unlink($restore_db_dump_file);
				echo json_encode('wptcs_callagain_wptce');
				exit;
			} else {
				$err_obj = array();
				$err_obj['restore_db_dump_file'] = $restore_db_dump_file;
				$err_obj['mysql_error'] = mysql_error();
				$error_array = array('error' => $err_obj);
				echo json_encode($error_array);
				handle_restore_error_wptc($options_obj);
				exit;
			}
		} else {
			dark_debug(array(), '-----------db file not found in this restore-------------');
			$options_obj->set_option('restore_db_process', false);
			$options_obj->set_option('restore_db_index', 0);
			echo json_encode('wptcs_callagain_wptce');
			exit;
		}
	} else {
		//if(!($options_obj->get_option('restore_db_process')))
		//first delete the sql file then carryout the copying files process
		if ($wp_filesystem->exists($restore_db_dump_file_fs_safe)) {
			$wp_filesystem->delete($restore_db_dump_file_fs_safe);
		}
		//if the db restore process is over ; call the function to perform copy
		manual_debug_wptc('', 'beforeStartingCopy');

		start_maintenance_mode_wptc();
		WPTC_Factory::get('Debug_Log')->wptc_log_now('files copy started', 'RESTORE');
		$full_copy_result = $options_obj->tc_file_system_move_dir($restore_temp_folder_fs_safe, $actual_main_folder);
		WPTC_Factory::get('Debug_Log')->wptc_log_now('files copy end', 'RESTORE');

		manual_debug_wptc('', 'afterCopyingDir');

		stop_maintenance_mode_wptc();

		if (!empty($full_copy_result) && is_array($full_copy_result) && array_key_exists('error', $full_copy_result)) {
			WPTC_Factory::get('Debug_Log')->wptc_log_now(serialize($full_copy_result), 'RESTORE');
			echo json_encode($full_copy_result);
		} else {
			//if we set this value as false ; then the bridge process for copying is completed
			WPTC_Factory::get('Debug_Log')->wptc_log_now('Restore completed', 'RESTORE');
			$options_obj->set_option('is_bridge_process', false);
			restore_complete();
		}
	}

	if (!($options_obj->get_option('is_bridge_process')) && !($options_obj->get_option('garbage_deleted'))) {
		echo json_encode('wptcs_over_wptce');
		return;
	}
}

function decode_staging_request(){
	if (empty($_REQUEST) || empty($_REQUEST['data'])) {
		return false;
	}
	$decode_data = base64_decode($_REQUEST['data']);
	if (empty($decode_data)) {
		return false;
	}
	$unserialize = unserialize($decode_data);
	if (empty($unserialize)) {
		return false;
	}
	return $unserialize;
}

function is_meta_data_req(){
	$data = decode_staging_request();
	if (empty($data)) {
		return false;
	}
	if ($data['action'] == 'import_meta_file') {
		return true;
	}
	return false;
}


function staging_req_type(){
	$data = decode_staging_request();
	if (empty($data)) {
		return false;
	}
	if ($data['action'] == 'staging_restore' && !empty($data['initialize']) && $data['initialize'] == 'true') {
		return 1;
	} else if ($data['action'] == 'staging_restore') {
		return 2;
	}
	return false;
}

function define_constants(){
	define('WPTC_BRIDGE', true); //used in wptc-constants.php

	if (!defined('FS_CHMOD_FILE')) {
		define('FS_CHMOD_FILE', 0644);
	}

	if (!defined('FS_CHMOD_DIR')) {
		define('FS_CHMOD_DIR', 0755);
	}
}

function init_db_connection(){
	global $wpdb;
	$wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
	$wpdb->prefix = $wpdb->base_prefix = DB_PREFIX_WPTC;
//	set_basename_wp_content_dir();

}

function start_maintenance_mode_wptc() {
	global $wp_filesystem;
	if (!$wp_filesystem) {
		initiate_filesystem_wptc();
		if (empty($wp_filesystem)) {
			send_response_wptc('FS_INIT_FAILED-019');
			return false;
		}
	}
	$file_content = '<?php global $upgrading; $upgrading = time();';
	//file_put_contents(ABSPATH . '/.maintenance', $file_content);

	$config = WPTC_Factory::get('config');

	$file_name = $config->wp_filesystem_safe_abspath_replace(ABSPATH);
	$file_name .= '.maintenance';

	$wp_filesystem->put_contents($file_name, $file_content);
}

function stop_maintenance_mode_wptc() {
	$config = WPTC_Factory::get('config');

	$maintenance_file = $config->wp_filesystem_safe_abspath_replace(ABSPATH);
	$maintenance_file .= '.maintenance';

	global $wp_filesystem;
	if (!$wp_filesystem) {
		initiate_filesystem_wptc();
		if (empty($wp_filesystem)) {
			send_response_wptc('FS_INIT_FAILED-020');
			return false;
		}
	}

	if ($wp_filesystem->is_file($maintenance_file)) {
		$wp_filesystem->delete($maintenance_file);
	}
}

function restore_complete($error = false) {
	stop_maintenance_mode_wptc();

	$config = WPTC_Factory::get('config');
	$config->set_option('restore_completed_notice', 'yes');
	//delete the bridge files on completion
	global $wp_filesystem;
	if (!$wp_filesystem) {
		initiate_filesystem_wptc();
		if (empty($wp_filesystem)) {
			send_response_wptc('FS_INIT_FAILED-026');
			return false;
		}
	}
	if ($wp_filesystem->exists($config->wp_filesystem_safe_abspath_replace($config->get_option('backup_db_path')) . 'wptcrquery/wptc_saved_queries_restore.sql')) {
		$wp_filesystem->delete($config->wp_filesystem_safe_abspath_replace($config->get_option('backup_db_path')) . 'wptcrquery/wptc_saved_queries_restore.sql');
		}

	$config->set_option('in_progress_restore', false);
	$config->set_option('is_running_restore', false);
	$config->set_option('cur_res_b_id', false);
	$config->set_option('start_renaming_sql', false);

	$config->set_option('restore_db_index', 0);

	$config->set_option('got_files_list_for_restore_to_point', 0);
	$config->set_option('live_files_to_restore_table', 0);
	$config->set_option('file_list_point_restore', 0);
	$config->set_option('recorded_files_to_restore_table', 0);

	$config->set_option('selected_files_temp_restore', 0);
	$config->set_option('selected_backup_type_restore', 0);
	$config->set_option('got_selected_files_to_restore', 0);
	$config->set_option('not_safe_for_write_files', 0);
	$config->set_option('recorded_this_selected_folder_restore', 0);
	$config->set_option('recent_restore_ping', false);

	//$config->set_option('wptc_today_main_cycle', 0); //forcing full backup on doing a restore.

	$config->set_option('is_bridge_process', false);

	$processed_restore = new WPTC_Processed_Restoredfiles();
	$processed_restore->truncate();

	$config->remove_garbage_files(array('is_restore' => true));

	if (!empty($error)) {
		echo json_encode(array('error' => $error));
	}
	echo json_encode('wptcs_over_wptce');
	exit;
}

function tc_database_restore($file_name, $meta_data_import = null , $meta_db_prefixes = array()) {
	global $wpdb, $is_staging_req;
	$options_obj = WPTC_Factory::get('config');
	if ($meta_data_import === 1) {
		dark_debug(array(), '---------tc_database_restore------------');
		change_new_prefix_to_staging($options_obj, $file_name, $meta_data_import = 1, $meta_db_prefixes);
	}
	$prev_index = $options_obj->get_option('restore_db_index');
	$last_index = $options_obj->get_option('restore_saved_index');
	dark_debug($prev_index, '---------$prev_index------------');
	dark_debug($last_index, '---------$last_index------------');
	if (empty($meta_data_import)) {
		manual_debug_wptc('', 'startingDBBridgeRestore');
		manual_debug_wptc('', 'beforeReadingDBFile');
	}
	$current_query = '';


	$handle = fopen($file_name, "r");
	if (empty($meta_data_import)) {
		manual_debug_wptc('', 'afterReadingDBFile');
	}
	if (!empty($handle)) {
		$this_lines_count = 0;
		$loop_iteration = 0;
		while (($line = fgets($handle)) !== false) {
			$loop_iteration++;
			if (!empty($prev_index) && $loop_iteration <= $prev_index) {
				continue; //check index;if it is previously written ; then continue;
			}
			$this_lines_count++;
			if (substr($line, 0, 2) == '--' || $line == '') {
				continue; // Skip it if it's a comment
			}

			$current_query .= $line;

			if (substr(trim($line), -1, 1) == ';') {
				// If it has a semicolon at the end, it's the end of the query
				if (!is_unwanted_query_staging($current_query)) {
					$result = $wpdb->query($current_query);
					// dark_debug($current_query, '---------$current_query------------');
					// dark_debug($result, '---------$result------------');
					if ($result === false) {
						//file_put_contents(WP_CONTENT_DIR . '/BRIDGE-SQL-LOG.txt', "\n ----sql error------ " . var_export(mysql_error(), true) . "\n", FILE_APPEND);
						//file_put_contents(WP_CONTENT_DIR . '/BRIDGE-SQL-LOG.txt', "\n --current_query----- " . var_export($current_query, true) . "\n", FILE_APPEND);
					}
				} else {
					dark_debug($current_query, '---------$current_query rejected------------');
				}

				$current_query = '';

				if ($this_lines_count >= 10) {
					$this_lines_count = 0;
					$result = maybe_call_again(1);
					if ($result == true) {
						$wpdb->query('UNLOCK TABLES;');
						$options_obj->set_option('restore_db_index', $loop_iteration); //updating the status in db for each 10 lines
						if (!empty($meta_data_import)) {
							return $loop_iteration;
						}
						fclose($handle);
						exit;
					}
				}
			}
		}
	} else {
		return false;
	}

	$wpdb->query('UNLOCK TABLES;');

	if (!empty($meta_data_import)) {
		$options_obj->set_option('meta_restore_completed', 1); //updating the status in db for each 10 lines
		return 'meta restore completed';
	}
	$basename_db_path = basename($options_obj->get_option('backup_db_path'));
	if (!empty($is_staging_req)) {
		$basename_db_path = $options_obj->wp_filesystem_safe_abspath_replace($basename_db_path);
	}
	if ($basename_db_path == 'uploads') {
		$savedQueryDir = $options_obj->get_option('backup_db_path') . '/tCapsule/'.basename(WPTC_WP_CONTENT_DIR).'/uploads/wptcrquery';
	} else {
		$savedQueryDir = $options_obj->get_option('backup_db_path') . '/tCapsule/'.basename(WPTC_WP_CONTENT_DIR).'/wptcrquery';
	}
	if (!is_dir($savedQueryDir)) {
		return true;
	}

	manual_debug_wptc('', 'beforeAppendingFile');

	$sql_files = get_all_db_sql_files($savedQueryDir);
	dark_debug($sql_files, '---------$sql_files 1------------');
	usort($sql_files, 'cmp_wptc_sql_array');
	dark_debug($sql_files, '---------$sql_files 2-----------');

	if (count($sql_files) > 0) {
		foreach ($sql_files as $val) {
			if (strpos($val, '.sql') !== false) {
				$content = file_get_contents($savedQueryDir . '/' . $val);
				$restoreSql = fopen($savedQueryDir . '/wptc_saved_queries_restore.sql', 'a+');
				fwrite($restoreSql, $content);
				@unlink($savedQueryDir . '/' . $val);
				fclose($restoreSql);
			}
		}
	}

	manual_debug_wptc('', 'afterAppendingFile');
	$saved_query_file = $savedQueryDir . '/wptc_saved_queries_restore.sql';
	$linesinsql = fopen($saved_query_file, "r");
	manual_debug_wptc('', 'afterReadingSqls');

	if ($linesinsql) {
		$this_lines_count = 0;
		$s_loop_iteration = 0;
		while (($sline = fgets($linesinsql)) !== false) {
			$s_loop_iteration++;
			if (!empty($last_index) && $s_loop_iteration <= $last_index) {
				continue; //check index;if it is previously written ; then continue;
			}
			$this_lines_count++;
			if (substr($sline, 0, 2) == '--' || $sline == '') {
				continue; // Skip it if it's a comment
			}

			$current_query .= $sline;

			if (substr(trim($sline), -1, 1) == ';') {
				$result = $wpdb->query($current_query); // If it has a semicolon at the end, it's the end of the query
				if ($result == false) {
					//file_put_contents(WP_CONTENT_DIR . '/BRIDGE-SQL-LOG.txt', "\n ----sql error-2----- " . var_export(mysql_error(), true) . "\n", FILE_APPEND);
					//file_put_contents(WP_CONTENT_DIR . '/BRIDGE-SQL-LOG.txt', "\n --current_query-2---- " . var_export($current_query, true) . "\n", FILE_APPEND);
				}

				$current_query = '';

				if ($this_lines_count >= 10) {
					$this_lines_count = 0;
					$wpdb->query('UNLOCK TABLES;');
					$options_obj->set_option('restore_saved_index', $s_loop_iteration);
					$result = maybe_call_again(1);
					if ($result == true) {
						fclose($linesinsql);
						exit;
					}
				}
			}
		}
	}
	manual_debug_wptc('', 'endingBridgeRestore');
	return true;
}

function get_all_db_sql_files($path){
	$sql_files = array();
	$files_obj = get_single_iterator_obj($path);
	foreach ($files_obj as $key => $file) {
			$file_path = $file->getPathname();
			$file_name= basename($file_path);
			if ($file_name == '.' || $file_name == '..' || !$file->isReadable() || stripos($file_path, 'wptc_saved_queries_restore.sql') ) {
				continue;
			}
			$sql_files[] = $file_name;
	}
	return $sql_files;
}

function replace_wptc_sercet_custom($raw_data){
	$response = $raw_data;
	$headerPos = stripos($response, 'wptc_saved_queries.sql.');
	if($headerPos !== false){
		$response = substr($response, $headerPos);
		$response = substr($response, strlen('wptc_saved_queries.sql.'), stripos($response, '-wptc-')-strlen('wptc_saved_queries.sql.'));
		$result = str_replace($response, '', $raw_data);
	}
	return $result;
}

function cmp_wptc_sql_array($ta, $tb){
	$a = replace_wptc_sercet_custom($ta);
	$b = replace_wptc_sercet_custom($tb);
	return strcmp($a, $b);
}

function is_unwanted_query_staging($req_query){
	$queries = array('CREATE DATABASE IF NOT EXISTS ', 'USE ');
	foreach ($queries as $query) {
		if (strpos($req_query, $query) !== FALSE) {
			return true;
		}
	}
	return false;
}

function handle_restore_error_wptc(&$options_obj) {
	$options_obj->remove_garbage_files(array('is_restore' => true));
	$options_obj->set_option('restore_db_process', false);
	$options_obj->set_option('is_bridge_process', false);
	$options_obj->set_option('restore_db_index', 0);

	restore_complete('Restoring DB error.');
}

function change_new_prefix_to_staging(&$options_obj, $db_file, $meta_data_import = null, $meta_db_prefixes = array()){
	global $current_db_prefix, $new_db_prefix;
	dark_debug(array(), '---------change_new_prefix_to_staging------------');
	dark_debug($meta_data_import, '---------$meta_data_import------------');
	if ($meta_data_import === 1) {
		$current_db_prefix = $meta_db_prefixes['current_db_prefix'];
		$new_db_prefix = $meta_db_prefixes['new_db_prefix'];
	} else {
		$current_db_prefix = $options_obj->get_option('current_db_prefix');;
		$new_db_prefix = $options_obj->get_option('new_db_prefix');
	}
	dark_debug($current_db_prefix, '---------$current_db_prefix------------');
	dark_debug($new_db_prefix, '---------$new_db_prefix------------');
	if ($current_db_prefix === $new_db_prefix) {
		dark_debug(array(), '---------prefix are not same------------');
		return false;
	}
	dark_debug($db_file, '---------$db_file sending to modify------------');
	modify_db_dump($db_file);
}

function modify_db_dump($db_file){
	$lines = file($db_file);
	@unlink($db_file);
	dark_debug(array(), '---------db file unlinked------------');
	// Loop through each line
	if (count($lines) && !empty($lines)) {
		foreach ($lines as $i => $line) {
			// dark_debug($line, '---------$line ------------');
			// Skip it if it's a comment
			if (substr($line, 0, 2) == '--' || $line == '' || substr($line, 0, 3) == '/*!')
				continue;
			// dark_debug(array(), '---------come to next part------------');
			$line = preg_replace_callback("/(TABLE[S]?|INSERT\ INTO|DROP\ TABLE\ IF\ EXISTS) [`]?([^`\;\ ]+)[`]?/", 'search_and_replace_prefix', $line);
			// dark_debug($line, '---------$line------------');
			// Add this line to the current segment
			if (file_put_contents($db_file, $line, FILE_APPEND) === FALSE)
				die('Error: Cannot write db file.');
		}
		return true;
	} else {
		return false;
	}
}

function search_and_replace_prefix($matches){
	global $current_db_prefix, $new_db_prefix;
	$subject = $matches[0];
	$old_table_name = $matches[2];
	$new_table_name = preg_replace("/$current_db_prefix/", $new_db_prefix, $old_table_name, 1);
	return str_replace($old_table_name, $new_table_name, $subject);
}
?>
