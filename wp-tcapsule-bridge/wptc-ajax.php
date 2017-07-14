<?php

set_time_limit(0);
error_reporting(0);
ini_set('display_errors', 'Off');

global $start_time_tc;
$start_time_tc = microtime(true);
define('WPTC_BRIDGE', true); //used in wptc-constants.php

require_once dirname(__FILE__). '/' ."common_include_files.php";

$Common_Include_Files = new Common_Include_Files('wptc-ajax');
$Common_Include_Files->init();

if (!defined('FS_CHMOD_FILE')) {
	define('FS_CHMOD_FILE', 0644);
}

if (!defined('FS_CHMOD_DIR')) {
	define('FS_CHMOD_DIR', 0755);
}

set_server_req_wptc();

//initialize wpdb since we are using it independently
global $wpdb;
$wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);

//setting the prefix from post value;
$wpdb->prefix = $wpdb->base_prefix = DB_PREFIX_WPTC;

//set_basename_wp_content_dir();


$initialize = false;
$post_data = $_POST;

$Common_Include_Files->start_sentry_restore_logs();

if (!empty($post_data['initialize'])) {
	$initialize = true;
} else if (isset($post_data['data'])) {
	$post_data = @unserialize(base64_decode($post_data['data']));
	if (!empty($post_data['initialize'])) {
		$initialize = true;
	}
}


dark_debug(array(), "--------Bridge above------OK--");

WPTC_Factory::get('Debug_Log')->wptc_log_now('wptc-ajax.php called', 'RESTORE');

$creds = request_filesystem_credentials("", "", false, false, null);
if (false === $creds) {
	echo json_encode(array('error' => 'Filesystem error: Couldnt get filesystem creds.'));
	WPTC_Factory::get('Debug_Log')->wptc_log_now('Filesystem error: Couldnt get filesystem creds.', 'RESTORE');
	die();
}

if (!WP_Filesystem($creds)) {
	echo json_encode(array('error' => 'Filesystem error: Couldnt initiate filesystem.'));
	WPTC_Factory::get('Debug_Log')->wptc_log_now('Filesystem error: Couldnt get filesystem creds.', 'RESTORE');
	die();
}

global $wp_filesystem;

if (isset($post_data['action']) && $post_data['action'] == 'reset_restore_settings' ) {
	WPTC_Factory::get('Debug_Log')->wptc_log_now('Reset restore related settings', 'RESTORE');
	dark_debug(array(), '-----------Cme in-------------');
	$config = WPTC_Factory::get('config');
	$config->remove_garbage_files(array('is_restore' => true), true);
	reset_restore_related_settings_wptc();
	die("RESET_SUCCESSFULLY");
}

if (isset($post_data['action']) && $post_data['action'] == 'get_last_php_error' ) {
	dark_debug(array(), '-----------Cme in get_last_php_error-------------');
	$error = wptc_fatal_error_hadler(1);
	dark_debug($error, '---------$error------------');
	die($error);
}

dark_debug(array(), "--------below filesystem--------");

$wptc_restore = new WPTC_Restore_Download($post_data);
$wptc_restore->initiate_restore($initialize);

class WPTC_Restore_Download {
	//private $wp_filesystem;
	private $config;
	private $backup_controller;
	private $processed_files;
	private $output;
	private $processed_file_count;
	private $is_continue_from_email;
	private $post_data;

	public function __construct($post_data = array()) {
		$this->post_data = $post_data;
		$this->is_continue_from_email = false;
		if (!empty($post_data['continue'])) {
			$this->is_continue_from_email = true;
		}

		$this->config = WPTC_Factory::get('config');
		$this->backup_controller = new WPTC_BackupController();
		$this->processed_files = WPTC_Factory::get('processed-restoredfiles', true);
		$this->config->set_option('recent_restore_ping', time());
		$this->dropbox = WPTC_Factory::get(DEFAULT_REPO);
		$this->output = $this->output ? $this->output : WPTC_Extension_Manager::construct()->get_output();
	}

	private function process_safe_for_write_check_options() {
		if (!empty($this->post_data) && !empty($this->post_data['ignore_file_write_check'])) {
			$this->config->set_option('check_is_safe_for_write_restore', 0);
		}
	}

	public function initiate_restore($initialize) {
		WPTC_Factory::get('Debug_Log')->wptc_log_now('initiate_restore called', 'RESTORE');

		dark_debug_func_map($initialize, "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);

		$this->config->set_option('wptc_profiling_start', microtime(true));
		// global $wptc_profiling_start;
		// $wptc_profiling_start = microtime(true);
		$this->config->set_option('in_progress_restore', true);

		$this->process_safe_for_write_check_options();

		$this->continue_copy_from_bridge_if_already_started();

		$data = array();

		if (!empty($initialize) && empty($data)) {
			$data = $this->config->get_option('restore_post_data');
			if (empty($data)) {
				dark_debug(error_get_last(), "--------no files to restore--------");
				$this->proper_restore_complete_exit(array('error' => 'Didnt get Files to Restore'));
			}
			$data = unserialize($data);
		}

		$files_to_restore = array();

		if (isset($data['files_to_restore'])) {
			$files_to_restore = $data['files_to_restore'];
		}

		if (!empty($data['cur_res_b_id']) && $data['cur_res_b_id'] != 'false') {
			//This is restore to point
			$cur_res_b_id = $data['cur_res_b_id'];

			if (empty($files_to_restore)) {
				$files_to_restore = array('0' => 1); //dummy
			}
		}
		if (!empty($files_to_restore)) {
			if (!empty($cur_res_b_id)) {
				WPTC_Factory::get('Debug_Log')->wptc_log_now('Restore to point', 'RESTORE');
				$this->config->set_option('cur_res_b_id', $cur_res_b_id); //the current b_id will be used to determine the future old files which are to be restored to the prev restore point
				$this->config->set_option('in_restore_deletion', false);
				$this->config->set_option('unknown_files_delete', true);
				$this->config->set_option('selected_id_for_restore', $cur_res_b_id);

				$this->config->set_option('file_list_point_restore', 0);
			} else {
				WPTC_Factory::get('Debug_Log')->wptc_log_now('Restore to specfic file', 'RESTORE');
				$this->config->set_option('cur_res_b_id', '');
				$this->config->set_option('selected_id_for_restore', $data['selectedID']);
				$this->config->set_option('unknown_files_delete', false);
			}

			$this->restore_now($files_to_restore);

			$started = true;
		} else {
			//if there is a bridge process going on ; then dont do restore-download
			if ($this->config->get_option('is_bridge_process')) {

				echo json_encode("wptcs_over_wptce");
				die();
			}
			$restore_result = $this->new_restore_execute();
		}
	}

	private function continue_copy_from_bridge_if_already_started() {
		if ($this->config->get_option('is_bridge_process')) {
			if ($this->is_continue_from_email) {
				echo json_encode(array('continue_from_email' => true));
				die();
			}
		}
	}

	public function restore_now($files_to_restore) {
		dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);
		manual_debug_wptc('', 'startingRestoreNow');

		if (empty($files_to_restore)) {
			return true;
		}
		$this->config->set_option('in_progress_restore', true);

		$restore_action_id = $this->config->get_option('restore_action_id');

		$this->config->set_option('got_files_list_for_restore_to_point', 0);
		$this->config->set_option('live_files_to_restore_table', 0);
		$this->config->set_option('file_list_point_restore', 0);
		$this->config->set_option('recorded_files_to_restore_table', 0);
		$this->config->set_option('selected_files_temp_restore', 0);
		$this->config->set_option('selected_backup_type_restore', 0);
		$this->config->set_option('got_selected_files_to_restore', 0);
		$this->config->set_option('not_safe_for_write_files', 0);
		$this->config->set_option('recorded_this_selected_folder_restore', 0);
		$this->config->set_option('is_bridge_process', 0);

		dark_debug(array(), "--------above --------");

		WPTC_Factory::get('logger')->log(sprintf(__('Restore started on %s.', 'wptc'), date("l F j, Y", strtotime(current_time('mysql')))), 'restore_start', $restore_action_id);
		$time = ini_get('max_execution_time');
		dark_debug(array(), "--------below 1 --------");
		WPTC_Factory::get('logger')->log(sprintf(__('Your time limit is %s and your memory limit is %s'),
			$time ? $time . ' ' . __('seconds', 'wptc') : __('unlimited', 'wptc'),
			ini_get('memory_limit')
		), 'restore_start', $restore_action_id);

		if (ini_get('safe_mode')) {
			WPTC_Factory::get('logger')->log(__("Safe mode is enabled on your server so the PHP time and memory limit cannot be set by the Restore process. So if your Restore fails it's highly probable that these settings are too low.", 'wptc'), 'restore_error', $restore_action_id);
		}

		$cur_res_b_id = $this->config->get_option("cur_res_b_id");

		dark_debug($cur_res_b_id, "--------cur_res_b_id--------");

		$TypeCheck = new WPTC_Processed_Files();

		$this->processed_files->truncate(); //for testing commented

		if (empty($cur_res_b_id)) {
			//This conditional loop is for restoring single or selected files.

			$selected_restore_id = $this->config->get_option("selected_id_for_restore");
			$Backuptype = $TypeCheck->backup_type_check($selected_restore_id);

			$this->config->set_option('selected_files_temp_restore', serialize($files_to_restore));
			$this->config->set_option('selected_backup_type_restore', $Backuptype);

			$this->config->set_option('got_files_list_for_restore_to_point', 1); //resetting flag indicating no need of restore_to_point

			WPTC_Factory::get('logger')->log(__("Files prepared for Restoring.", 'wptc'), 'restore_error', $restore_action_id);
		} else {
			//This conditional loop is for restoring to a point. This will have only the sql file.
			$this->config->set_option('got_selected_files_to_restore', 1); //resetting flag indicating no need of restore_single

			//below code is only for testing .. please remove it
			//$this->config->set_option('got_files_list_for_restore_to_point', 1); //resetting flag indicating no need of restore_to_point

			$Backuptype = $TypeCheck->backup_type_check($cur_res_b_id);
			dark_debug($Backuptype, "-------Backuptype-------");

			//during restore to point we need to prepare and send the sql files separately by the following function.
			$files_to_restore = $this->processed_files->get_formatted_sql_file_for_restore_to_point_id($cur_res_b_id, $Backuptype);

			dark_debug($files_to_restore, "--------got sql files--------");

			$this->processed_files->add_files_for_restoring($files_to_restore, $Backuptype);
		}

		$this->run_tcdropbox_restore(); //since we are using manual ajax function
	}

	public function run_tcdropbox_restore() {
		if (!$this->config->get_option('is_running_restore')) {
			$this->config->set_option('is_running_restore', true);
			$this->new_restore_execute();
		} else {
			echo json_encode("wptcs_over_wptce");
			exit;
		}
	}

	public function new_restore_execute() {
		dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);

		$this->config->set_time_limit();
		$this->config->set_memory_limit();

		$logger = WPTC_Factory::get('logger');

		$restore_action_id = $this->config->get_option('restore_action_id');
		try {
			if (!$this->dropbox->is_authorized()) {
				WPTC_Factory::get('Debug_Log')->wptc_log_now('Your '.DEFAULT_REPO.' account is not authorized yet.', 'RESTORE');
				$logger->log(__('Your '.DEFAULT_REPO.' account is not authorized yet.', 'wptc'), 'restore_error', $restore_action_id);
				$this->proper_restore_complete_exit(array('error' => 'Your '.DEFAULT_REPO.' account is not authorized yet.'));

				return;
			}

			$result = $this->restore_path();

			dark_debug($result, "--------result restore path--------");

			if (is_array($result) && isset($result['error'])) {
				WPTC_Factory::get('Debug_Log')->wptc_log_now(serialize($result), 'RESTORE');
				$this->config->set_option('last_process_restore', false);
				$this->proper_restore_complete_exit($result);
			}

			$this->config->set_option('total_file_count', $this->processed_file_count);
			WPTC_Factory::get('Debug_Log')->wptc_log_now('Restore completed', 'RESTORE');
			//$this->config->set_option('last_process_restore',true);
			$logger->log(__('Restore complete.', 'wptc'), 'restore_complete', $restore_action_id);
			$logger->log(sprintf(__('A total of %s files were processed.', 'wptc'), $this->processed_file_count), 'restore_complete', $restore_action_id);
			$logger->log(sprintf(
				__('A total of %dMB of memory was used to complete this restore.', 'wptc'),
				(memory_get_usage(true) / 1048576)
			), 'restore_complete', $restore_action_id);

			$root = false;
			if (get_class($this->output) != 'WPTC_Extension_DefaultOutput') {
				$this->output = new WPTC_Extension_DefaultOutput();
				$root = true;
			}

			$is_chunk_alive = $this->chunked_download_check();

			dark_debug($is_chunk_alive, "--------is_chunk_alive--------");

			if (!$this->config->get_option('chunked') && !$is_chunk_alive) {
				//if chunked download is not going ; or if bridge copy is not going do this completion step.
				$this->proper_restore_complete_exit('wptcs_over_wptce');
			} else {
				echo json_encode("wptcs_callagain_wptce");
				exit;
			}
		} catch (Exception $e) {
			WPTC_Factory::get('Debug_Log')->wptc_log_now('Restore failed - '.$e->getMessage(), 'RESTORE');
			if ($e->getMessage() == 'Unauthorized') {
				$logger->log(__('The plugin is no longer authorized with Dropbox.', 'wptc'), 'restore_error', $restore_action_id);
			} else {
				$logger->log(__('A fatal error occured: ', 'wptc') . $e->getMessage(), 'restore_error', $restore_action_id);
			}

			$this->proper_restore_complete_exit(array('error' => $e->getMessage()));
		}
	}

	//Checking the chunked download is in progress or completed
	public function chunked_download_check() {
		global $wpdb;
		//$unfinished_downloads = $wpdb->get_var('SELECT COUNT(*) FROM `' . $wpdb->base_prefix . 'wptc_processed_restored_files` WHERE `uploaded_file_size`!=`offset`  AND `uploaded_file_size` > 4024000');
		$unfinished_downloads = $wpdb->get_var(' SELECT COUNT(*) FROM `' . $wpdb->base_prefix . 'wptc_processed_restored_files` WHERE `offset` > 0 ');
		if ($unfinished_downloads > 0) {
			return true;
		} else {
			return false;
		}
	}

	public function proper_restore_complete_exit($message) {
		$this->config->complete('restore');

		if (!empty($message) && is_array($message)) {
			$this->config->remove_garbage_files(array('is_restore' => true));
			echo json_encode($message);
			die();
		}

		echo json_encode($message);
		die();
	}

	public function restore_path($file = null, $version = null, $dropbox_path = null) {
		if (!$this->config->get_option('in_progress_restore') || $this->config->get_option('in_progress')) {
			dark_debug($account_info, "--------returning by in-progress-restore--------");
			return;
		}

		$restore_action_id = $this->config->get_option('restore_action_id');

		dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);

		$starting_restore_path_time = microtime();
		$dropbox_path = get_tcsanitized_home_path();

		dark_debug($dropbox_path, "--------dropbox_path got dropbox_path--------");

		$this->processed_files = WPTC_Factory::get('processed-restoredfiles', true); //this variable holds all the files which are already restored along with some info.

		$this->processed_file_count = $this->processed_files->get_file_count();

		dark_debug(array(), "--------before--------");

		//if we didnt store all the selected files to restore table, then continue it
		$got_selected_files_to_restore = $this->config->get_option("got_selected_files_to_restore");
		if (!$got_selected_files_to_restore) {
			WPTC_Factory::get('Debug_Log')->wptc_log_now('Filelist preparing for Restoring Selected Files', 'RESTORE');

			//This conditional loop is only for restoring selected files
			$this->process_add_selected_files_to_restore();

			WPTC_Factory::get('Debug_Log')->wptc_log_now('Filelist prepared for Restoring Selected Files', 'RESTORE');

			WPTC_Factory::get('logger')->log(__("Filelist prepared for Restoring Selected Files.", 'wptc'), 'restore_process', $restore_action_id);
		}

		//if we didnt store all the restore to point files to restore table, then continue it
		$got_restore_file_list = $this->config->get_option('got_files_list_for_restore_to_point');
		if (!$got_restore_file_list) {
			//This conditional loop is only for restore to point
			WPTC_Factory::get('logger')->log(__("Starting to prepare the Filelist for Restoring.", 'wptc'), 'restore_process', $restore_action_id);
			WPTC_Factory::get('Debug_Log')->wptc_log_now('got_restore_file_list', 'RESTORE');

			$cur_res_b_id = $this->config->get_option("cur_res_b_id");

			$TypeCheck = new WPTC_Processed_Files();

			if (!empty($cur_res_b_id)) {
				//This conditional loop is for Restore to point.

				$live_files_to_restore_table = $this->config->get_option('live_files_to_restore_table'); //flag for is live_files added to db

				dark_debug($live_files_to_restore_table, "--------live_files_to_restore_table value--------");

				$Backuptype = $TypeCheck->backup_type_check($cur_res_b_id);
				WPTC_Factory::get('Debug_Log')->wptc_log_now('start iterating into database restore files', 'RESTORE');

				if (empty($live_files_to_restore_table)) {
					$Mfile_arr = $this->backup_controller->get_recursive_iterator_objs($dropbox_path);
					$this->iterator_into_db_for_restore($Mfile_arr, 0, $Backuptype);
					$Mfile_arr = null;
				}
				WPTC_Factory::get('Debug_Log')->wptc_log_now('end iterating into database restore files', 'RESTORE');

				$recorded_files_to_restore_table = $this->config->get_option('recorded_files_to_restore_table');

				if (empty($recorded_files_to_restore_table)) {
					$this->get_recorded_files_to_restore_table($Backuptype);
				}

				WPTC_Factory::get('logger')->log(__("Filelist prepared for Restoring.", 'wptc'), 'restore_process', $restore_action_id);

				$this->config->set_option('got_files_list_for_restore_to_point', 1);
			}
		}

		$restore_queue = $this->processed_files->get_limited_restore_queue_from_base();

		//dark_debug($restore_queue, "--------restore_queue--------");
		dark_debug(count($restore_queue), "--------restore_queue_count--------");

		static $is_queue = 0;
		$is_queue = count($restore_queue);
		WPTC_Factory::get('Debug_Log')->wptc_log_now('Start downloading files from cloud', 'RESTORE');

		while ($is_queue) {
			foreach ($restore_queue as $key => $value) {
				$value_array = array();
				$value_array = (array) $value;

				$file = $value_array['file'];
				$version = $value_array['revision_id'];

				$current_processed_files = $uploaded_files = array();

				$processed_file = $this->processed_files->get_file($file);
				// dark_debug($processed_file, '---------$processed_file------------');
				if ((($processed_file && $processed_file->offset == 0 && $processed_file->uploaded_file_size < 4024000 && $processed_file->download_status != "notDone") || ($processed_file && $processed_file->offset >= $processed_file->uploaded_file_size && $processed_file->download_status != "notDone")) && ($processed_file->revision_id == $version)) {
					//if processed_file value is not null , then the file is already restored so it needs to be skipped; but in case of chunked restored we should not skip it until the whole file is restored
					dark_debug($processed_file, "--------already restored ya--------");
					$this->config->set_option('chunked', false);
					continue;
				}
				// dark_debug(array(), '---------Download check------------');
				// dark_debug($file, '--------$file------------');
				$is_same_res = $this->is_file_hash_same($file, $processed_file->file_hash ,$value->uploaded_file_size, $value->mtime_during_upload);
				dark_debug($is_same_res, '---------$is_same_res------------');
				if ($is_same_res) {
					//dark_debug($file, "--------avoided sssm--------");
					$dropboxOutput = true;
				} else {
					// dark_debug($dropbox_path, "--------dropbox_path--------");
					// dark_debug($file, "--------file--------");
					// dark_debug($version, "--------version--------");
					// dark_debug($processed_file, "--------processed_file--------");
					// dark_debug($value, "--------value--------");
					$dropboxOutput = $this->output->drop_download($dropbox_path, $file, $version, $processed_file, (array) $value);
					//dark_debug($dropboxOutput, "--------didnt dropboxOutput--------");
				}

				//dark_debug($dropboxOutput, "--------below download--------");

				if (is_array($dropboxOutput) && isset($dropboxOutput['error'])) {
					$this->config->set_option('in_progress_restore', false);
					$this->config->set_option('is_running_restore', false);
					//$this->config->set_option('last_process_restore', false);
					$this->config->set_option('chunked', false);

					reset_restore_related_settings_wptc();

					echo json_encode($dropboxOutput);
					exit;

				} else if (!empty($result) && is_array($result) && isset($result['too_many_requests'])) {
					dark_debug($result, "--------too_many_requests--------");
					WPTC_Factory::get('logger')->log(__("Limit reached during download : .", 'wptc'), 'restore_process', $restore_action_id);
				} else if ($dropboxOutput) {
					$dbox_array = (array) $dropboxOutput;
					if (!empty($dbox_array['chunked'])) {
						$this->config->set_option('is_running_restore', false);
						//$this->config->set_option('chunked', true);
					} else {
						//$this->config->set_option('chunked', false);
					}

					//adding files to DB here
					$processed_file = $this->processed_files->get_file($file);
					// dark_debug($processed_file, '---------$processed_file------------');
					//sleep(10);

					if ((((!empty($processed_file) && $processed_file->download_status == 'notDone')) || (!empty($processed_file) && $processed_file->download_status == 'done')) && !($this->config->get_option('chunked'))) {
						$value_array['download_status'] = 'done';
						// dark_debug($value_array, '---------$value_array------------');
						$current_processed_files[] = $value_array; //manual
						$this->processed_file_count++;
						$this->processed_files->add_files($current_processed_files); //manual
					}

				}
				//check timeout and echo "wptcs_callagain_wptce`" for each download files
				$this->backup_controller->maybe_call_again_tc();
			}
			$restore_queue = $this->processed_files->get_limited_restore_queue_from_base();
			$is_queue = count($restore_queue);
		}
	}

	public function process_add_selected_files_to_restore() {
		dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);

		$selected_files_temp_restore = $this->config->get_option('selected_files_temp_restore');
		$Backuptype = $this->config->get_option('selected_backup_type_restore');

		if (!$selected_files_temp_restore && !$Backuptype) {
			$this->config->set_option('got_selected_files_to_restore', 1);
			return true;
		}

		$files_to_restore_tmp = unserialize($selected_files_temp_restore);

		dark_debug($files_to_restore_tmp, "--------files_to_restore_tmp--------");

		$Mfile_arr = array();
		foreach ($files_to_restore_tmp as $files_or_folders => $v) {
			if ($files_or_folders == 'files') {
				foreach ($v as $file_dets) {
					$this->backup_controller->check_and_record_not_safe_for_write($file_dets['file_name']);
				}
				$this->check_and_exit_if_safe_for_write_limit_reached();

				$this->processed_files->add_files_for_restoring($v, $Backuptype);
			} else if ($files_or_folders == 'folders') {
				foreach ($v as $file_dets) {
					//$Mfile_arr[] = get_single_iterator_obj($file_dets['file_name']);
					$recorded_this_selected_folder_restore = $this->config->get_option_arr_bool_compat('recorded_this_selected_folder_restore');
					if (is_array($recorded_this_selected_folder_restore) && in_array($file_dets['file_name'], $recorded_this_selected_folder_restore)) {
						continue;
					}
					$this->get_recorded_files_of_this_folder_to_restore_table($file_dets['file_name'], $file_dets['backup_id'], $Backuptype);
				}
			}
		}
		$this->config->set_option('got_selected_files_to_restore', 1);
	}

	public function check_and_exit_if_safe_for_write_limit_reached() {
		//return true; //off for now
		//dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);

		$CHECK_IS_SAFE_FOR_WRITE = $this->config->get_option('check_is_safe_for_write_restore');
		$not_safe_for_write_files = $this->config->get_encoded_not_safe_for_write_files();

		if (!$CHECK_IS_SAFE_FOR_WRITE || empty($not_safe_for_write_files)) {
			return true;
		}

		if (defined('WPTC_RESTORE_FILES_NOT_WRITABLE_COUNT') && (count($not_safe_for_write_files) >= WPTC_RESTORE_FILES_NOT_WRITABLE_COUNT)) {
			echo json_encode((array('not_safe_for_write_limit_reached' => $not_safe_for_write_files)));
			exit;
		}
	}

	public function iterator_into_db_for_restore($TFiles, $starting_backup_path_time = 0, $Backuptype = '') {
		dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);
		manual_debug_wptc('', 'iteratorIntoDbForRestore');
		if (empty($TFiles)) {
			$this->config->set_option('live_files_to_restore_table', 1);
			return;
		}

		global $start_time_tc;

		dark_debug(array(), "--------below processed file object--------");

		$FilesArray = array();
		foreach ($TFiles as $Ofiles) {
			foreach ($Ofiles as $currentfile) {
				$FilesArray[] = $currentfile->getPathname();
			}
		}
		$TFiles = null;
		manual_debug_wptc('', 'afterFilesArrayiteratorIntoDB');

		$mcount = count($FilesArray) % 30;
		if ($mcount > 0) {
			$lastloop = 1;
		} else {
			$lastloop = 0;
		}
		$itercount = count($FilesArray);
		$numofinsert = round($itercount / 30);

		$point = $this->config->get_option('file_list_point_restore');

		dark_debug($itercount, "--------starting FilesArray--------");
		dark_debug($point, "--------point--------");

		$not_safe_for_write_ref = array();

		//looping for every 30 records.
		for ($inloop = 0; $inloop < $numofinsert + $lastloop; $inloop++) {
			if ($point < $itercount) {
				$qry = "";
				$not_yet_comma = true;

				$prepared_file_array = array();

				for ($deep = 0; $deep < 30; $deep++) {
					if (!empty($FilesArray[$point])) {
						if ((basename($FilesArray[$point]) == '.') || (basename($FilesArray[$point]) == '..')) {
							$point++;
							continue;
						}

						$this->backup_controller->check_and_record_not_safe_for_write($FilesArray[$point]);

						$this->delete_future_files_and_get_restorable_files($FilesArray[$point], $prepared_file_array);

						$point++;
					} else {
						continue;
					}
				}

				$this->check_and_exit_if_safe_for_write_limit_reached();

				$this->processed_files->add_files_for_restoring($prepared_file_array, $Backuptype);
				$this->config->set_option('file_list_point_restore', $point);
				//sleep(4);
				$this->backup_controller->maybe_call_again_tc($point);

			} else {
				continue;
			}
		}

		$this->config->set_option('live_files_to_restore_table', 1);
		$this->config->set_option('file_list_point_restore', 0);

		manual_debug_wptc('', 'aftreIteratorIntoDB');
	}

	public function delete_future_files_and_get_restorable_files($file, &$prepared_file_array) {
		if (is_dir($file)) {
			return true;
		}
		if($this->config->get_option('is_staging_running') && stripos($file, '.htaccess') !== false ){
			return ; //do not restore .htaccess if its staging
		}
		// dark_debug($file, '---------$file before------------');
		$file = $this->config->wp_filesystem_safe_abspath_replace($file);
		$file = $this->config->replace_to_original_abspath($file);
		$file = rtrim($file, '/');
		// dark_debug($file, '---------$file after------------');
		$cur_res_b_id = $this->config->get_option("cur_res_b_id");
		// dark_debug($cur_res_b_id, '---------$cur_res_b_id------------');
		$value = $this->processed_files->get_most_recent_revision($file, $cur_res_b_id);
		// dark_debug($value, '---------$value------------');
		//avoiding metadata to be downloaded.
		if (empty($value) || ((strpos($file, 'meta_data') !== false) && (strpos($file, 'wptc-secret') !== false))) {
			$this->safe_unlink_file($file);
		} else {
			$prepared_file_array[$value[0]->revision_id] = array();
			//$prepared_file_array[$value[0]->revision_id]['file_name'] = str_replace("\\", "\\\\", $file);
			$prepared_file_array[$value[0]->revision_id]['file_name'] = wp_normalize_path($file);
			$prepared_file_array[$value[0]->revision_id]['file_size'] = $value[0]->uploaded_file_size;
			$prepared_file_array[$value[0]->revision_id]['mtime_during_upload'] = $value[0]->mtime_during_upload;
			$prepared_file_array[$value[0]->revision_id]['g_file_id'] = $value[0]->g_file_id;
			$prepared_file_array[$value[0]->revision_id]['file_hash'] = $value[0]->file_hash;
		}
	}

	public function get_recorded_files_of_this_folder_to_restore_table($folder_name, $folder_res_id, $Backuptype) {
		dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);

		$processed_files = WPTC_Factory::get('processed-restoredfiles', true);

		$records_queue = $processed_files->get_limited_recorded_files_of_this_folder_from_base($folder_res_id, $folder_name);
		dark_debug($records_queue, "--------records_queue--------");

		$prepared_file_array = array();

		static $is_queue = 0;
		$is_queue = count($records_queue);

		while ($is_queue) {
			foreach ($records_queue as $k => $v) {
				$this->backup_controller->check_and_record_not_safe_for_write($v->file);

				$value = $processed_files->get_most_recent_revision($v->file, $folder_res_id);

				$prepared_file_array[$value[0]->revision_id] = array();
				//$prepared_file_array[$value[0]->revision_id]['file_name'] = str_replace("\\", "\\\\", $file);
				$prepared_file_array[$value[0]->revision_id]['file_name'] = wp_normalize_path($v->file);
				$prepared_file_array[$value[0]->revision_id]['file_size'] = $value[0]->uploaded_file_size;
				$prepared_file_array[$value[0]->revision_id]['mtime_during_upload'] = $value[0]->mtime_during_upload;
				$prepared_file_array[$value[0]->revision_id]['g_file_id'] = $value[0]->g_file_id;
				$prepared_file_array[$value[0]->revision_id]['file_hash'] = $value[0]->file_hash;
			}

			$this->check_and_exit_if_safe_for_write_limit_reached();
			$processed_files->add_files_for_restoring($prepared_file_array, $Backuptype);

			$this->backup_controller->maybe_call_again_tc();

			//below two lines is just for refreshing while loop condition
			$records_queue = $processed_files->get_limited_recorded_files_of_this_folder_from_base($folder_res_id, $folder_name);
			$is_queue = count($records_queue);

		}

		$this->config->append_option_arr_bool_compat('recorded_this_selected_folder_restore', $folder_name);
	}

	public function safe_unlink_file($val) {
		global $wp_filesystem;
		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-024');
				return false;
			}
		}

		$is_skip = false;
		$val = wp_normalize_path($val);

		if (strpos($val, 'wp-time-capsule') !== false || strpos($val, 'tCapsule') !== false || strpos($val, 'error_log') !== false) {
			$is_skip = true;
		}

		if (strpos($val, 'wptc-secret') !== false) {
			$is_skip = true;
		}

		if (false !== strpos($val, '/tCapsule/backups/')) {
			$is_skip = true;
		}

		if (false !== strpos($val, 'DE_cl')) {
			$is_skip = true;
		}

		if (false !== strpos($val, 'wp-tcapsule-bridge')) {
			$is_skip = true;
		}

		if (false !== strpos($val, 'wptc_staging_controller')) {
			$is_skip = true;
		}
		$dirs_to_exclude = get_dirs_to_exculde_wptc();
		if (!empty($dirs_to_exclude)) {
			foreach ($dirs_to_exclude as $this_dir) {
				$good_this_dir = wp_normalize_path($this_dir);
				if (!empty($good_this_dir) && strpos($val, $good_this_dir) !== false) {
					$is_skip = true;
					break;
				}
			}
		}

		$processed_files = WPTC_Factory::get('processed-files');
		// dark_debug($val, '---------$val------------');
		$status = WPTC_Base_Factory::get('Wptc_ExcludeOption')->is_excluded_file($val);
		dark_debug($status, '---------$status------------');

		if ($status) {
			$is_skip = true;
		}

		if (!$is_skip) {
			$fs_safe_dir = $this->config->wp_filesystem_safe_abspath_replace(dirname($val));
			$fs_safe_file_name = $fs_safe_dir . basename($val);

			$wp_filesystem->delete($fs_safe_file_name);
			$wp_filesystem->delete($fs_safe_dir);
		}
	}

	public function get_recorded_files_to_restore_table($Backuptype) {
		manual_debug_wptc('', 'startingGet_recorded_files_to_restore_table');

		$cur_res_b_id = $this->config->get_option("cur_res_b_id");

		$records_queue = $this->processed_files->get_limited_recorded_files_queue_from_base($cur_res_b_id);

		manual_debug_wptc('', 'afterGetttingLimitedRecords');
		//dark_debug($records_queue, "--------records_queue--------");
		//dark_debug(count($records_queue), "--------records_queue_count beginning--------");

		$prepared_file_array = array();

		static $is_queue = 0;
		$is_queue = count($records_queue);

		while ($is_queue) {
			foreach ($records_queue as $k => $v) {
				$value = $this->processed_files->get_most_recent_revision($v->file, $cur_res_b_id);

				$this->backup_controller->check_and_record_not_safe_for_write($v->file);

				$prepared_file_array[$value[0]->revision_id] = array();
				//$prepared_file_array[$value[0]->revision_id]['file_name'] = str_replace("\\", "\\\\", $file);
				$prepared_file_array[$value[0]->revision_id]['file_name'] = wp_normalize_path($v->file);
				$prepared_file_array[$value[0]->revision_id]['file_size'] = $value[0]->uploaded_file_size;
				$prepared_file_array[$value[0]->revision_id]['mtime_during_upload'] = $value[0]->mtime_during_upload;
				$prepared_file_array[$value[0]->revision_id]['g_file_id'] = $value[0]->g_file_id;
				$prepared_file_array[$value[0]->revision_id]['file_hash'] = $value[0]->file_hash;
			}

			$this->check_and_exit_if_safe_for_write_limit_reached();

			//dark_debug($prepared_file_array, "--------prepared file aray--------");

			manual_debug_wptc('', 'beforeAddingFilesForRestoring');

			$this->processed_files->add_files_for_restoring($prepared_file_array, $Backuptype);

			manual_debug_wptc('', 'afterAddingFilesForRestoring');

			$this->backup_controller->maybe_call_again_tc();

			//below two lines is just for refreshing while loop condition
			$records_queue = $this->processed_files->get_limited_recorded_files_queue_from_base($cur_res_b_id);
			$is_queue = count($records_queue);

			//dark_debug($is_queue, "--------is_queue-get_recorded_files_to_restore_table-------");
			//dark_debug(count($is_queue), "--------is_queue-get_recorded_files_to_restore_table_count refreshing-------");
			//exit;
		}

		$this->config->set_option('recorded_files_to_restore_table', 1);
	}

	public function is_file_hash_same($file_path, $prev_file_hash ,$prev_file_size, $prev_file_mtime = 0) {
		if($this->config->get_option('is_staging_running') && stripos($file_path, '.htaccess') !== false ){
			dark_debug(array(), '-----htaccess file so not downloaded in staging----------------');
			return true; //do not restore .htaccess if its staging
		}

		$file_path = $this->config->wp_filesystem_safe_abspath_replace($file_path);
		$file_path = rtrim($file_path, '/');

		if (!file_exists($file_path)) {
			return false;
		}

		if(!is_hash_required($file_path)){
			dark_debug(array(), '---------cannot find has so checking file size and m time------------');
			return $this->is_same_size_and_same_mtime($file_path, $prev_file_size, $prev_file_mtime);
		}

		$new_file_hash = compute_Md5_Hash($file_path);
		dark_debug($new_file_hash, '---------$new_file_hash------------');
		if ($prev_file_hash != $new_file_hash) {
			return false;
		}

		return true;

	}

	private function is_same_size_and_same_mtime($file_path, $prev_file_size, $prev_file_mtime){
		// dark_debug($prev_file_size, '---------$prev_file_size------------');
		// dark_debug($this_new_file_size, '---------$this_new_file_size------------');
		$is_staging_running = $this->config->get_option('is_staging_running');
		// dark_debug($is_staging_running, '---------$is_staging_running------------');
		$new_file_size = @filesize($file_path);
		if ($is_staging_running) {
			if ($new_file_size == $prev_file_size){
				// dark_debug(array(), '---------Same size------------');
				return true;
			}
			// dark_debug(array(), '---------diff size------------');
			return false;
		}
		$this_file_m_time = @filemtime($file_path);
		if (($new_file_size == $prev_file_size) && ($this_file_m_time == $prev_file_mtime)) {
			return true;
		}
		return false;
	}

}