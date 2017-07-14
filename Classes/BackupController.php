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

class WPTC_BackupController {
	private
	$dropbox,
	$config,
	$output,
	$processed_file_count,
	$WptcAutoBackupHooksObj,
	$iter_loop_limit,
	$exclude_class_obj,
	$iter_count_rate_limit,
	$max_allowed_file_size;
	public static function construct() {
		return new self();
	}

	public function __construct($output = null) {
		$this->config = WPTC_Factory::get('config');
		$this->dropbox = WPTC_Factory::get(DEFAULT_REPO);
		$this->output = $output ? $output : WPTC_Extension_Manager::construct()->get_output();
		$this->iter_loop_count = 100;
		$this->iter_count_rate_limit = 1000;
		$this->max_allowed_file_size = 2147483648; // 2GB
		$this->exclude_class_obj = WPTC_Base_Factory::get('Wptc_ExcludeOption');
	}

	public function get_recursive_iterator_objs($path) {
		manual_debug_wptc('', 'beforeStartingFileList');

		$Mfile_arr = array();
		$is_auto_backup = $this->config->get_option('wptc_sub_cycle_running');
		if ($is_auto_backup) {
			$Mfile_arr = apply_filters('add_auto_backup_record_to_backup', '');
		} else {
			dark_debug(array(), "--------not auto backup--------");
			$Mfile_arr[] = get_single_iterator_obj($path);
		}
		return $Mfile_arr;
	}

	public function upload_meta_file($meta_file) {
		$dropboxOutput = $this->output->out_meta_data_backup($meta_file);
		dark_debug($dropboxOutput,'--------------$dropboxOutput-------------');
		if ($dropboxOutput) {
			$processed_files = WPTC_Factory::get('processed-files');
			$this_dropbox_array = (array) $dropboxOutput['body'];
			$version_array = array();
			$version_array['revision_number'] = $this_dropbox_array['revision'];
			$version_array['revision_id'] = $this_dropbox_array['rev'];
			$version_array['uploaded_file_size'] = $this_dropbox_array['bytes'];
			$version_array['g_file_id'] = (empty($this_dropbox_array['g_file_id'])) ? '' : $this_dropbox_array['g_file_id'];

			//refreshing the processed file obj ; this is necessary only for chunked upload
			$processed_file = $processed_files->get_file($meta_file);
			dark_debug($processed_file,'--------------$processed_file-------------');
			//dark_debug($processed_file, "--------get file--------");

			if ($processed_file && $processed_file->offset > 0) {
				$processed_files->file_complete($file);
			}

			$file_with_version = array();

			if (!empty($version_array)) {
				$file_with_version = $version_array;
			}
			$file_with_version['filename'] = $meta_file;
			$file_with_version['mtime_during_upload'] = filemtime($meta_file);

			$current_processed_files[] = $file_with_version; //manual
			dark_debug($current_processed_files,'--------------$current_processed_files-------------');

			$processed_files->add_files($current_processed_files); //manual
		}
		@unlink($meta_file);
	}

	public function backup_path($path, $content_flag, $always_include = null, $backup_id = null) {
		//return;
		global $wpdb, $settings_ajax_start_time;
		$starting_backup_path_time = $settings_ajax_start_time;

		if (!$this->config->get_option('in_progress')) {
			dark_debug($account_info, "--------exiting by !in_progress--------");
			return 'ignored';
		}
		if ($this->config->get_option('in_progress_restore')) {
			dark_debug($account_info, "--------exiting by !in_restore_progress--------");
			return 'ignored';
		}

		$dropbox_path = get_tcsanitized_home_path();
		$dropbox_path = wp_normalize_path($dropbox_path);
		$current_processed_files = $uploaded_files = array();

		$processed_files = WPTC_Factory::get('processed-files');
		$this->processed_file_count = $processed_files->get_processed_files_count();

		if (!file_exists($path)) {
			return false;
		}

		$total_files = 0;
		if (!$this->config->get_option('gotfileslist', true)) {
			WPTC_Factory::get('Debug_Log')->wptc_log_now('Collecting File List', 'BACKUP');
			dark_debug(array(), '---------Got list false------------');

			$Mfile_arr = array();
			$Mfile_arr = $this->get_recursive_iterator_objs($path);

			foreach ($Mfile_arr as $k => $Mfile) {
				$total_files += iterator_count($Mfile);
			}

			$TFiles = $Mfile_arr;

			WPTC_Factory::get('logger')->log(__("Starting File List Iterator.", 'wptc'), 'backup_progress', $backup_id);

			$wpdb->query("SET @@auto_increment_increment=1");

			$this->iterator_into_db($TFiles, $starting_backup_path_time);

			WPTC_Factory::get('logger')->log(__("Ending File List Iterator.", 'wptc'), 'backup_progress', $backup_id);

		} else {
			$total_files = $this->config->get_option('total_file_count');
		}

		manual_debug_wptc('', 'beforeQueueFiles');
		global $current_process_file_id;
		$current_process_file_id = $this->config->get_option('current_process_file_id');
		dark_debug($current_process_file_id, '---------$current_process_file_id------------');
		if (empty($current_process_file_id)) {
			$current_process_file_id = 1;
		}
		WPTC_Factory::get('Debug_Log')->wptc_log_now('Backup Resuming File Count - '. $current_process_file_id, 'BACKUP');
		// $Qfiles = $wpdb->get_results("SELECT * FROM " . $wpdb->base_prefix . "wptc_current_process WHERE status='Q' ORDER BY id DESC  LIMIT 1");
		$Qfiles = $wpdb->get_results("SELECT * FROM " . $wpdb->base_prefix . "wptc_current_process WHERE id = ".$current_process_file_id);

		dark_debug($Qfiles, "--------Qfiles--------");

		// $Qfiles_demo = $wpdb->get_results("SELECT * FROM " . $wpdb->base_prefix . "wptc_current_process WHERE status='Q' ORDER BY file_path DESC");

		// dark_debug($Qfiles_demo, "--------Qfiles_demo--------");

		manual_debug_wptc('', 'afterQueueFiles');

		$ignored_files_count = $this->config->get_option('ignored_files_count');
		//$ignored_files_count = 0;
		$this->config->set_option('total_file_count', $total_files);

		if (empty($Qfiles)) {
			dark_debug(array(), "--------Qfiles empty--------");
			return false;
		}

		$file_list = WPTC_Factory::get('fileList');

		static $is_queue = 0;
		$is_queue = count($Qfiles);

		manual_debug_wptc('', 'startingLoop');

		//while loop is for memory optimization
		while ($is_queue) {
			foreach ($Qfiles as $file_info) {
				$fid = $file_info->id;
				$file_hash = $file_info->file_hash;
				$current_processed_files = $uploaded_files = array();
				$file = $file_info->file_path;
				$file = wp_normalize_path($file);

				if (stripos($file, 'tCapsule') !== FALSE) {
					$always_backup = 1;
				} else {
					$always_backup = 0;
				}

				if ($file_list->in_ignore_list($file) && $always_backup != 1 ) {
					// $this->write_status_of_file($fid, 'S');
					$ignored_files_count += 1;
					// dark_debug(array(), '---------SKIP THIS FILE 2------------');
					continue;
				}

				if (filesize($file) === false) {
					// $this->write_status_of_file($fid, 'S');
					$ignored_files_count += 1;
					// dark_debug(array(), '---------SKIP THIS FILE 3 ------------');
					continue;
				} else if(is_zero_bytes_file($file)){
					dark_debug($file, '---------$file yes zero bytes------------');
					$is_zero_bytes_file = true;
				} else if (filesize($file) > $this->max_allowed_file_size) {
					$ignored_files_count += 1;
					WPTC_Factory::get('logger')->log(__($file." is bigger than allowed size so ignored", 'wptc'), 'backup_progress', $backup_id);
					dark_debug($file, '---------$file more than allowed size------------');
					continue;
				} else {
					$is_zero_bytes_file = false;
				}

				if (!is_readable($file)) {
					// $this->write_status_of_file($fid, 'S');
					$ignored_files_count += 1;
					$error_array = array('file_name' => wp_normalize_path($file));
					$this->config->append_option_arr_bool_compat('mail_backup_errors', $error_array, 'unable_to_read');
					// dark_debug(array(), '---------SKIP THIS FILE 4------------');
					continue;
				}

				if (is_file($file)) {

					$processed_file = $processed_files->get_file($file);
					// dark_debug($processed_file, '---------$processed_file------------');
					$purge_status = $processed_files->is_file_purged($file);

					if (dirname($file) == $this->config->get_backup_dir() && $file != $always_include && strpos($file, 'wptc-secret') === false) {
						$ignored_files_count += 1;
						// $this->write_status_of_file($fid, 'S');
						dark_debug(array(), '---------SKIP THIS FILE 5------------');
						continue;
					}

					$this->config->set_option('ignored_files_count', $ignored_files_count);
					$this->config->set_option('supposed_total_files_count', ($total_files - $ignored_files_count));

					$is_processed_atleast_once_form_beginning = $processed_files->get_file_history($file);
					// dark_debug($is_processed_atleast_once_form_beginning, '---------$is_processed_atleast_once_form_beginning------------');
					$dropboxOutput = $this->output->out($dropbox_path, $file, $file_hash, $processed_file, $is_processed_atleast_once_form_beginning, $starting_backup_path_time, $purge_status);
					// dark_debug($dropboxOutput, '---------$dropboxOutput------------');
					if (!empty($dropboxOutput) && $dropboxOutput != 'exist' && !isset($dropboxOutput['error']) && !isset($dropboxOutput['too_many_requests'])) {
						// $this->write_status_of_file($fid, 'P');
						// dark_debug(array(), '---------FILE PROCESSED------------');
					} else if ($dropboxOutput == 'exist') {
						// $this->write_status_of_file($fid, 'S');
						// dark_debug(array(), '---------SKIP THIS FILE 6------------');
						//$processed_files->record_as_skimmed($processed_file);
						//dark_debug(array(), "--------exists--------");

						continue;
					} else if (isset($dropboxOutput['error'])) {
						WPTC_Factory::get('logger')->log($dropboxOutput['error'], 'backup_error', $backup_id);
						// $this->write_status_of_file($fid, 'E');
						dark_debug(array(), '---------GOT ERROR ON THIS------------');
						continue;
					}

					// dark_debug($file, '---------file-------------');
					if (isset($dropboxOutput['too_many_requests'])) {
						dark_debug(date('g:i:s a'), '---------too_many_requests called -------------');
						// WPTC_Factory::get('logger')->log(__('Limit reached during upload', 'wptc'), 'backup_progress', $backup_id);
						// sleep(3);
						continue;
					} else if ($dropboxOutput) {
						dark_debug($dropboxOutput, '---------$dropboxOutput------------');
						dark_debug(array(), '---------START ADDIGN TO OUT PUT------------');
						// dark_debug($dropboxOutput, '---------dropboxOutput-------------');
						$this_dropbox_array = (array) $dropboxOutput['body'];
						$version_array = array();
						if (DEFAULT_REPO === 'dropbox') {
							$version_array['revision_number'] = $this_dropbox_array['id'];
							$version_array['uploaded_file_size'] = $this_dropbox_array['size'];
						} else {
							$version_array['revision_number'] = $this_dropbox_array['revision'];
							$version_array['uploaded_file_size'] = $this_dropbox_array['bytes'];
						}
						$version_array['revision_id'] = $this_dropbox_array['rev'];
						$version_array['g_file_id'] = (empty($this_dropbox_array['g_file_id'])) ? '' : $this_dropbox_array['g_file_id'];

						//refreshing the processed file obj ; this is necessary only for chunked upload
						$processed_file = $processed_files->get_file($file);
						// dark_debug($processed_file, "--------get file--------");

						if ($processed_file && $processed_file->offset > 0) {
							$processed_files->file_complete($file);
						}

						$file_with_version = array();

						if (!empty($version_array)) {
							$file_with_version = $version_array;
						}
						$file_with_version['filename'] = $file;
						$file_with_version['mtime_during_upload'] = filemtime($file);
						$file_with_version['file_hash'] = $file_hash;

						$current_processed_files[] = $file_with_version; //manual
						$this->processed_file_count++;
						$in_progress = $this->config->get_option('in_progress', true);
						if(empty($in_progress)){
							dark_debug(array(), '-----------Break in middle because force stop 1-------------');
							send_response_wptc('backup_stopped_manually', 'BACKUP');
						}
						dark_debug($current_processed_files, '---------$currentfile------------');
						$processed_files->add_files($current_processed_files); //manual
						save_files_zero_bytes($is_zero_bytes_file, $file);
						if ($processed_files->is_frequently_changed($backup_id, $file) !== false) {
							dark_debug( $file, '----------is_frequently_changed-----------');
							$freq_file_info = $processed_files->get_mtime_size_past_n_current($file);
							$this->config->append_option_arr_bool_compat('frequently_changed_files', $freq_file_info, 'frequently_changed_files');
						}
						// dark_debug(array(), '---------ADDED OUTPUT------------');
					}
				} else {
					$ignored_files_count += 1;
					$this->write_status_of_file($fid, 'S');
				}
			}
			manual_debug_wptc('', 'insideLoop', 100);
			// dark_debug( $current_process_file_id, '---------$current_process_file_id selva 1------------');
			check_timeout_cut_and_exit_wptc($starting_backup_path_time, $current_process_file_id + 1);

			// reload the while loop condition below
			// $Qfiles = $wpdb->get_results("SELECT * FROM " . $wpdb->base_prefix . "wptc_current_process WHERE status='Q' ORDER BY id DESC  LIMIT 1");
			global $current_process_file_id;
			$Qfiles = $wpdb->get_results("SELECT * FROM " . $wpdb->base_prefix . "wptc_current_process WHERE id = ". ++$current_process_file_id);
			// dark_debug($Qfiles, '---------$Qfiles------------');
			$is_queue = count($Qfiles);
		}

		$this->config->set_option('ignored_files_count', $ignored_files_count);
		return false;
	}

	// public function replace_slashes($directory_name) {
	// 	return str_replace(array("/"), DIRECTORY_SEPARATOR, $directory_name);
	// }

	public function execute($type = '') {

		$this->config->set_option('wptc_profiling_start', microtime(true));
		manual_debug_wptc('', 'backupStart');
		$contents = @unserialize($this->config->get_option('this_cookie'));
		$backup_id = $contents['backupID'];

		$manager = WPTC_Extension_Manager::construct();
		$logger = WPTC_Factory::get('logger');
		$dbBackup = WPTC_Factory::get('databaseBackup');

		if ($this->config->get_option('bbu_upgrade_process_running')) {
			$this->config->complete(null, $ignored_backup);
			return false;
		}

		$this->config->set_backup_time_limit();
		$this->config->set_memory_limit();
		try {
			if ((defined('DEFAULT_REPO') && DEFAULT_REPO) && !$this->dropbox->is_authorized()) {
				if (!$this->config->is_main_account_authorized()) {
					$logger->log(__('Service login failed. Please login again', 'wptc'), 'backup_error', $backup_id);
					$this->proper_backup_force_complete_exit($msg = 'Login Failed so backup stopped');
				} else{
					$logger->log(__('Your ' . DEFAULT_REPO . ' account is not authorized yet.', 'wptc'), 'backup_error', $backup_id);
					$this->proper_backup_force_complete_exit($msg = DEFAULT_REPO . ' account Auth failed so backup stopped');
				}
			}
			$start = microtime(true);

			if (!$this->config->get_option('wptc_sub_cycle_running') && !$this->config->get_option('wptc_db_backup_completed')) {

					$dbStatus = $dbBackup->get_status();

					if (($dbStatus == WPTC_DatabaseBackup::NOT_STARTED) || ($dbStatus == WPTC_DatabaseBackup::IN_PROGRESS)) {
						// if ($dbStatus == WPTC_DatabaseBackup::IN_PROGRESS) {
						// 	$logger->log(__('Resuming SQL backup.', 'wptc'), 'backup_progress', $backup_id);
						// 	WPTC_Factory::get('Debug_Log')->wptc_log_now('Resuming SQL backup.', 'BACKUP');
						// } else {
						// 	WPTC_Factory::get('Debug_Log')->wptc_log_now('Starting SQL backup.', 'BACKUP');
						// 	$logger->log(__('Starting SQL backup.', 'wptc'), 'backup_progress', $backup_id);
						// }
						if (!WPTC_DARK_TEST_SIMPLE) {
							$status = $dbBackup->shell_db_dump();
							dark_debug($status, '---------------$status-----------------');
							if ($status === 'failed') {
							$dbStatus = $dbBackup->get_status();
							if (($dbStatus == WPTC_DatabaseBackup::NOT_STARTED) || ($dbStatus == WPTC_DatabaseBackup::IN_PROGRESS)) {
								if ($dbStatus == WPTC_DatabaseBackup::IN_PROGRESS) {
									$logger->log(__('Resuming SQL backup.', 'wptc'), 'backup_progress', $backup_id);
									WPTC_Factory::get('Debug_Log')->wptc_log_now('Resuming SQL backup.', 'BACKUP');
								} else {
									WPTC_Factory::get('Debug_Log')->wptc_log_now('Starting SQL backup.', 'BACKUP');
									$logger->log(__('Starting SQL backup.', 'wptc'), 'backup_progress', $backup_id);
								}
								if (!WPTC_DARK_TEST_SIMPLE) {
									$dbBackup->execute();
								}
								WPTC_Factory::get('Debug_Log')->wptc_log_now('SQL backup complete. Starting file backup.', 'BACKUP');
								$logger->log(__('SQL backup complete. Starting file backup.', 'wptc'), 'backup_progress', $backup_id);
								$this->config->set_option('wptc_db_backup_completed', true);
							} else if ($status === 'running') {
								dark_debug(array(), '---------------database dump is running-----------------');
								return false;
							} else if($status === 'do_not_continue'){
								$logger->log(__('SQL backup complete. Starting file backup.', 'wptc'), 'backup_progress', $backup_id);
								$this->config->set_option('wptc_db_backup_completed', true);
								dark_debug(array(), '---------------database dump completed but wait for next call-----------------');
								return false;
							}
						}
					}
				}
			}

			$timetaken = microtime(true) - $start;
			$start = microtime(true);
			if ($this->output->start()) {
				$home_path = get_tcsanitized_home_path();

				$content_path = WP_CONTENT_DIR;
				if ($content_path != "") {
					$Check = str_replace($home_path, 'MaTcHeD_cOnTeNt', $content_path);
					$MPos = strpos($Check, 'MaTcHeD_cOnTeNt');
					if ($MPos !== false) {
						$inside = true;
					} else {
						$inside = false;
					}
				}
				$inside = true;

				$ignored_backup = false;

				manual_debug_wptc('', 'beforeBackupPath');

				$result = $this->backup_path(get_tcsanitized_home_path(), $inside, $dbBackup->get_file(), $backup_id);
				$in_progress = $this->config->get_option('in_progress', true);
				if(empty($in_progress)){
					send_response_wptc('backup_stopped_manually', 'BACKUP');
				}

				if(!$this->config->get_option('add_backup_general_data', true)){
					$complete_data['memory_usage'] = (memory_get_usage(true) / 1048576);
					$complete_data['backup_id'] = $backup_id;
					$complete_data['files_count'] = $this->processed_file_count;
					$this->add_backup_general_data($complete_data);
					$this->config->set_option('add_backup_general_data', true);
				}

				if (!defined('WPTC_DONT_BACKUP_META') || $this->config->get_option('is_staging_backup')) {
					WPTC_Factory::get('Debug_Log')->wptc_log_now('Start backing up META DATA', 'BACKUP');
					$meta_file = $dbBackup->execute_backup_db_meta_data($backup_id);
					if (!empty($meta_file) && file_exists($meta_file)) {
						$table_name = WPTC_Factory::db()->prefix . 'wptc_processed_files';
						$table_status_name = $table_name . "_meta_data";
						$this->config->set_option($table_status_name, -1);
						$this->upload_meta_file($meta_file);
					}
				}

				if (!empty($result) && $result === 'ignored') {
					$ignored_backup = true;
				}

				manual_debug_wptc('', 'afterBackupPath');

				$this->output->end();

				$this->config->set_option('total_file_count', $this->processed_file_count);
			}
			do_action('record_auto_backup_complete', $backup_id);

			$timetaken = microtime(true) - $start;
			$manager->complete();
			if ($this->config->get_option('bbu_upgrade_process_running')) {
				$this->config->complete(null, $ignored_backup);
				return false;
			}
			$this->config->set_option('last_process_restore', false);
			$this->wtc_backup_complete_reset_var($complete_data);

			$starting_backup_first_call_time = $this->config->get_option('starting_backup_first_call_time');
			$backup_all_time_taken = microtime(true) - $starting_backup_first_call_time;
			WPTC_Factory::get('Debug_Log')->wptc_log_now('Backup completed', 'BACKUP');
			$logger->log(__('Backup complete.', 'wptc'), 'backup_complete', $backup_id);
			$logger->log(sprintf(__('Total time taken to complete the full backup process is %d secs.', 'wptc'), $backup_all_time_taken), 'backup_complete', $backup_id);
			$logger->log(sprintf(__('A total of %s files were processed.', 'wptc'), $this->processed_file_count), 'backup_complete', $backup_id);
			$logger->log(sprintf(
				__('A total of %dMB of memory was used to complete this backup.', 'wptc'),
				(memory_get_usage(true) / 1048576)
			), 'backup_complete', $backup_id);

			$root = false;
			if (get_class($this->output) != 'WPTC_Extension_DefaultOutput') {
				$this->output = new WPTC_Extension_DefaultOutput();
				$root = true;
			}
			$this->config->set_option('staging_backup_id', $backup_id);
			$dbBackup->clean_up();
			$this->config->complete(null, $ignored_backup);
			$this->clean_up();

			// set_backup_in_progress_server(false);
			// do_action('is_staging_backup_wptc_h', time());

		} catch (Exception $e) {
			if ($e->getMessage() == 'Unauthorized') {
				$logger->log(__('The plugin is no longer authorized with Dropbox.', 'wptc'), 'backup_error', $backup_id);
			} else {
				$logger->log(__('A fatal error occured: ', 'wptc') . $e->getMessage(), 'backup_error', $backup_id);
				if ($e->getMessage() == 'Error closing sql dump file.') {
					$this->proper_backup_complete_exit($backup_id);
				}
				if ($e->getMessage() == 'Cloud API not set') {
					$this->proper_backup_force_complete_exit($msg = 'Cloud API not set');
				}
			}

			backup_proper_exit_wptc();
			// set_backup_in_progress_server(false);

			// $dbBackup->clean_up();
			// $this->config->complete(null, false, true);
			// $this->clean_up();
		}
	}

	public function  proper_backup_force_complete_exit($msg = false){
		$dbBackup = WPTC_Factory::get('databaseBackup');
		$logger = WPTC_Factory::get('logger');
		$backup_id = getTcCookie('backupID');
		$logger->log(__('Backup stopped', 'wptc'), 'backup_error', $backup_id);
		$dbBackup->clean_up();
		$this->config->force_complete(null);
		$this->clean_up();
		if (empty($msg)) {
			send_response_wptc('Backup stopped forcefully');
		} else {
			send_response_wptc($msg);
		}
	}
	public function proper_backup_complete_exit($backup_id) {
		$dbBackup = WPTC_Factory::get('databaseBackup');
		$logger = WPTC_Factory::get('logger');
		$logger->log(__('Backup stopped', 'wptc'), 'backup_error', $backup_id);
		$dbBackup->clean_up();
		$this->config->complete(null);
		$this->clean_up();
		die();
	}

	public function maybe_call_again_tc($point = 0) {
		global $start_time_tc;

		if (!defined('WPTC_TIMEOUT')) {
			define('WPTC_TIMEOUT', 21);
		}

		if ((microtime(true) - $start_time_tc) >= WPTC_TIMEOUT) {
			echo json_encode("wptcs_callagain_wptce");
			exit;
		}
	}

	public function create_config_like_file() {
		dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);
		global $wpdb;
		$contents_to_be_written = "
		<?php
		/** The name of the database for WordPress */
		if(!defined('DB_NAME'))
		define('DB_NAME', '" . DB_NAME . "');

		/** MySQL database username */
		if(!defined('DB_USER'))
		define('DB_USER', '" . DB_USER . "');

		/** MySQL database password */
		if(!defined('DB_PASSWORD'))
		define('DB_PASSWORD', '" . DB_PASSWORD . "');

		/** MySQL hostname */
		if(!defined('DB_HOST'))
		define('DB_HOST', '" . DB_HOST . "');

		/** Database Charset to use in creating database tables. */
		if(!defined('DB_CHARSET'))
		define('DB_CHARSET', '" . DB_CHARSET . "');

		/** The Database Collate type. Don't change this if in doubt. */
		if(!defined('DB_COLLATE'))
		define('DB_COLLATE', '" . DB_COLLATE . "');

		if(!defined('DB_PREFIX_WPTC'))
		define('DB_PREFIX_WPTC', '" . $wpdb->base_prefix . "');

		if(!defined('DEFAULT_REPO'))
		define('DEFAULT_REPO', '" . DEFAULT_REPO . "');

		if(!defined('BRIDGE_NAME_WPTC'))
		define('BRIDGE_NAME_WPTC', '" . $this->config->get_option('current_bridge_file_name') . "');

		if (!defined('WP_MAX_MEMORY_LIMIT')) {
			define('WP_MAX_MEMORY_LIMIT', '256M');
		}

		if(!defined('WP_DEBUG'))
		define('WP_DEBUG', false);

		if(!defined('WP_DEBUG_DISPLAY'))
		define('WP_DEBUG_DISPLAY', false);

		if ( !defined('MINUTE_IN_SECONDS') )
		define('MINUTE_IN_SECONDS', 60);
		if ( !defined('HOUR_IN_SECONDS') )
		define('HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS);
		if ( !defined('DAY_IN_SECONDS') )
		define('DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS);
		if ( !defined('WEEK_IN_SECONDS') )
		define('WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS);
		if ( !defined('YEAR_IN_SECONDS') )
		define('YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS);



		/** Absolute path to the WordPress directory. */
		if ( !defined('ABSPATH') )
        define('ABSPATH', dirname(dirname(__FILE__)) . '/');

    	if ( !defined('WP_CONTENT_DIR') )
		define('WP_CONTENT_DIR', ABSPATH . '".basename(WPTC_WP_CONTENT_DIR)."');

		if ( !defined('WP_LANG_DIR') )
		define('WP_LANG_DIR', ABSPATH . '".basename(WPTC_WP_CONTENT_DIR)."/lang');

		if(!defined('WP_PLUGIN_DIR'))
		define('WP_PLUGIN_DIR', '" . WPTC_PLUGIN_DIR . "');

              ";

		if (defined('FS_METHOD')) {
			$contents_to_be_written .= "
		define('FS_METHOD', '" . FS_METHOD . "');
			";
		}
		if (defined('FTP_BASE')) {
			$contents_to_be_written .= "
		define('FTP_BASE', '" . FTP_BASE . "');
			";
		}
		if (defined('FTP_USER')) {
			$contents_to_be_written .= "
		define('FTP_USER', '" . FTP_USER . "');
			";
		}
		if (defined('FTP_PASS')) {
			$contents_to_be_written .= "
		define('FTP_PASS', '" . FTP_PASS . "');
			";
		}
		if (defined('FTP_HOST')) {
			$contents_to_be_written .= "
		define('FTP_HOST', '" . FTP_HOST . "');
			";
		}
		if (defined('FTP_SSL')) {
			$contents_to_be_written .= "
		define('FTP_SSL', '" . FTP_SSL . "');
			";
		}
		if (defined('FTP_CONTENT_DIR')) {
			$contents_to_be_written .= "
		define('FTP_CONTENT_DIR', '" . FTP_CONTENT_DIR . "');
			";
		}
		if (defined('FTP_PLUGIN_DIR')) {
			$contents_to_be_written .= "
		define('FTP_PLUGIN_DIR', '" . FTP_PLUGIN_DIR . "');
			";
		}
		if (defined('FTP_PUBKEY')) {
			$contents_to_be_written .= "
		define('FTP_PUBKEY', '" . FTP_PUBKEY . "');
			";
		}
		if (defined('FTP_PRIKEY')) {
			$contents_to_be_written .= "
		define('FTP_PRIKEY', '" . FTP_PRIKEY . "');
			";
		}
		global $wp_filesystem;


		$dump_dir = $this->config->get_backup_dir();
		dark_debug($dump_dir, '---------$dump_dir------------');
		$dump_dir = $this->config->wp_filesystem_safe_abspath_replace($dump_dir);

		$dump_dir_parent = trailingslashit(dirname($dump_dir));

		$this_config_like_file = $dump_dir_parent . 'config-like-file.php';

		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-002');
				return false;
			}
		}

		$result = $wp_filesystem->put_contents($this_config_like_file, $contents_to_be_written, 0644);
		if (!$result) {
			dark_debug($this_config_like_file, "--------create_config_like_file--------");
			return false;
		}
		return $this_config_like_file;
	}

	public function copy_bridge_files_tc() {
		dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);
		global $wp_filesystem;

		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-003');
				return false;
			}
		}

		set_time_limit(0);

		$restore_id = WPTC_Factory::get('config')->get_option('restore_action_id');
		$logger = WPTC_Factory::get('logger');

		$plugin_path_tc = $wp_filesystem->wp_plugins_dir() . WPTC_TC_PLUGIN_NAME;
		$plugin_path_tc = trailingslashit($plugin_path_tc);

		$this_config_like_file = $this->create_config_like_file();
		if (!$this_config_like_file) {
			$logger->log('Error Creating config like file.', 'restore_error', $restore_id);
			return array('error' => 'Error Creating config like file.');
		}

		$current_bridge_file_name = $this->config->get_option('current_bridge_file_name');
		$root_bridge_file_path = $wp_filesystem->abspath() . $current_bridge_file_name;
		$root_bridge_file_path = trailingslashit($root_bridge_file_path);

		if (!$wp_filesystem->is_dir($root_bridge_file_path)) {
			if (!$wp_filesystem->mkdir($root_bridge_file_path, FS_CHMOD_DIR)) {
				$logger->log('Failed to create bridge directory while restoring . Check your folder permissions', 'restore_error', $restore_id);
				return array('error' => 'Cannot create Bridge Directory in root.');
			}
		}

		//call the copy function to copy the bridge folder files
		$plugin_bridge_file_path = trailingslashit($plugin_path_tc . 'wp-tcapsule-bridge');
		$copy_res = $this->config->tc_file_system_copy_dir($plugin_bridge_file_path, $root_bridge_file_path, array('multicall_exit' => true));
		if (!$copy_res) {
			$logger->log('Failed to Copy Bridge files', 'restore_error', $restore_id);
			return array('error' => 'Cannot copy Bridge Directory.');
		}

		$plugin_folders_to_copy = array('Classes', 'Base', 'Dropbox', 'S3', 'Google', 'utils');
		foreach ($plugin_folders_to_copy as $v) {
			$plugin_folder = trailingslashit($plugin_path_tc . $v);
			$root_bridge_file_path_sub = trailingslashit($root_bridge_file_path . $v);

			if (!$wp_filesystem->is_dir($root_bridge_file_path_sub)) {
				if (!$wp_filesystem->mkdir($root_bridge_file_path_sub, FS_CHMOD_DIR)) {
					$logger->log('Failed to create bridge directory while restoring . Check your folder permissions', 'restore_error', $restore_id);
					return array('error' => 'Cannot create Plugin Directory in bridge.');
				}
			}

			$copy_res = $this->config->tc_file_system_copy_dir($plugin_folder, $root_bridge_file_path_sub, array('multicall_exit' => true));
			if (!$copy_res) {
				$logger->log('Failed to Copy Bridge files', 'restore_error', $restore_id);
				return array('error' => 'Cannot copy Plugin Directory(' . $plugin_folder . ').');
			}
		}

		$files_other_than_bridge = array();
		$files_other_than_bridge['wp-tc-config.php'] = $this_config_like_file; //config-like-file which was prepared already
		$files_other_than_bridge['common-functions.php'] = $plugin_path_tc . '/common-functions.php';
		$files_other_than_bridge['wptc-constants.php'] = $plugin_path_tc . '/wptc-constants.php';
		$files_other_than_bridge['restore-progress-ajax.php'] = $plugin_path_tc . '/restore-progress-ajax.php';
		$files_other_than_bridge['wptc-monitor.js'] = $plugin_path_tc . '/Views/wptc-monitor.js';
		if(WPTC_ENV != 'production'){
			$files_other_than_bridge['wptc-env-parameters.php'] = $plugin_path_tc . '/wptc-env-parameters.php';
		}
		// $files_other_than_bridge['Factory.php'] = $plugin_path_tc . 'Classes/Factory.php';
		// $files_other_than_bridge['FileList.php'] = $plugin_path_tc . 'Classes/FileList.php';
		// $files_other_than_bridge['tc-config.php'] = $plugin_path_tc . 'Classes/Config.php';
		// $files_other_than_bridge['BackupController.php'] = $plugin_path_tc . 'Classes/BackupController.php';
		// $files_other_than_bridge['Files.php'] = $plugin_path_tc . 'Classes/Processed/Files.php';
		// $files_other_than_bridge['Base.php'] = $plugin_path_tc . 'Classes/Processed/Base.php';
		// $files_other_than_bridge['Restoredfiles.php'] = $plugin_path_tc . 'Classes/Processed/Restoredfiles.php';
		// $files_other_than_bridge['DBTables.php'] = $plugin_path_tc . 'Classes/Processed/DBTables.php';

		foreach ($files_other_than_bridge as $key => $value) {
			$copy_result = $this->config->tc_file_system_copy($value, $root_bridge_file_path . $key, true);
			if (!$copy_result) {
				return array('error' => 'Cannot copy Bridge files(' . $value . ').');
			}
		}

		$logger->log('Bridge Files are prepared successfully', 'restore_error', $restore_id);

		//return array('error' => 'Copy success.');

		return true;
	}

	public function backup_now($type) {
		dark_debug($type, '---------backup_now $type------------');
		$old_cookie = getTcCookie('backupID');
		deleteTcCookie();

		setTcCookieNow("backupID");

		$config = WPTC_Factory::get('config');

		if ($config->get_option('schedule_backup_running') || $type == 'daily_cycle') {
			store_name_for_this_backup_callback_wptc("Schedule Backup");
		} else if ($config->get_option('wptc_main_cycle_running') || $config->get_option('wptc_sub_cycle_running') || $type == 'sub_cycle') {
			store_name_for_this_backup_callback_wptc("Auto Backup");
		} else {
			store_name_for_this_backup_callback_wptc("Updated on " .user_formatted_time_wptc(time(), $format = 'g:i a')); //time
		}

		execute_tcdropbox_backup_wptc($type);
	}

	public function check_and_record_not_safe_for_write($this_file) {
		//dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);

		global $wp_filesystem;

		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-004');
				return false;
			}
		}

		$this_file = $this->config->wp_filesystem_safe_abspath_replace($this_file);

		if($wp_filesystem->is_dir($this_file)){
			return true;
		}
		$this_file = rtrim($this_file, '/');

		if ($wp_filesystem->exists($this_file) && !$wp_filesystem->is_writable($this_file)) {
			$chmod_result = $wp_filesystem->chmod($this_file, 0644);
			if (!$chmod_result) {
				$this->config->save_encoded_not_safe_for_write_files($this_file);
				return false;
			}
		}
		return true;
	}

	public function delete_prev_records() {
		//call the function to delete the prev records records
		$processed_files = WPTC_Factory::get('processed-files');
		$processed_files->delete_expired_life_span_backups();
		$processed_files->delete_last_month_backups(); //commented for now.
		WPTC_Factory::get('Debug_Log')->delete_old_debug_logs();
	}

	public function stop($process = null) {
		dark_debug($process,'--------------$stop process data-------------');
		if ($process == 'restore' || $process == 'logout' ) {
			$this->config->complete($process);
			$this->clean_up();
		} else {
			$config = WPTC_Factory::get('config');
			$in_progress = $config->get_option('in_progress', true);
			if (empty($in_progress)) {
				dark_debug(array(), '-----------Progress is not set-------------');
				return false;
			}
			$logger = WPTC_Factory::get('logger');
			$cur_backup_id = getTcCookie('backupID');
			$logger->log(__('You stopped this backup manually from settings', 'wptc'), 'backup_error',$cur_backup_id);
			$this->config->force_complete();
			$this->clean_up();
		}
	}

	private function clean_up() {
		WPTC_Factory::get('databaseBackup')->clean_up();
		WPTC_Extension_Manager::construct()->get_output()->clean_up();
	}

	public function unlink_current_acc_and_backups() {

		//delete backup history and data
		$logger = WPTC_Factory::get('logger');
		$logger->log('Current account unlinked and backups data were deleted', 'unlink_current_acc_and_backups');

		global $wpdb;

		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_processed_dbtables`");
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_processed_files`");
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_processed_restored_files`");
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_backup_names`");
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_activity_log`");
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_current_process`");
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_options`");

		$this->config->set_option('in_progress', 0);
		$this->config->set_option('is_running', 0);
		$this->config->set_option('cached_g_drive_this_site_main_folder_id', false);
		$this->config->set_option('cached_wptc_g_drive_folder_id', false);
	}

	public function clear_prev_repo_backup_files_record($reset_inc_exc = false) {
		$logger = WPTC_Factory::get('logger');
		$logger->log('Current Repo Backup Files were Removed', 'clear_prev_repo_backup_files_record');

		$this->config->set_option('cached_g_drive_this_site_main_folder_id', false);
		$this->config->set_option('cached_wptc_g_drive_folder_id', false);

		global $wpdb;

		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_processed_dbtables`");
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_processed_files`");
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_processed_restored_files`");
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_current_process`");
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_backup_names`");
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_backups`");

		if(!$reset_inc_exc){
			return false;
		}
		//reset inc exc
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_included_tables`");
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_included_files`");
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_excluded_tables`");
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_excluded_files`");
	}

	public function clear_current_backup(){
		global $wpdb;
		reset_backup_related_settings_wptc();
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_processed_dbtables`");
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_processed_restored_files`");
		$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_current_process`");
	}
	public function write_status_of_file($id, $status) {
		global $wpdb;
		$in = $wpdb->query("UPDATE `" . $wpdb->base_prefix . "wptc_current_process`
				SET status = '$status'
				WHERE id = $id");
	}

	public function iterator_into_db($TFiles, $starting_backup_path_time){
		manual_debug_wptc('', 'iteratorIntoDB');
		if (empty($TFiles)) {
			$this->config->set_option('gotfileslist', 1);
			return;
		}

		global $wpdb;
		global $wp_filesystem;

		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-001');
				return false;
			}
			dark_debug(array(), '---------------return false iterator_into_db-----------------');
		}

		$file_list = WPTC_Factory::get('fileList');
		$backup_type = $this->config->get_option('wptc_current_backup_type');
		$is_auto_backup = $this->config->get_option('wptc_sub_cycle_running');

		manual_debug_wptc('', 'afterFilesArrayiteratorIntoDB');

		$prev_total_iter_count = $this->config->get_option('file_list_point');
		if (empty($prev_total_iter_count)) {
			$prev_total_iter_count = 0;
		}
		dark_debug($prev_total_iter_count, "--------prev_total_iter_count--------");

		$prev_list_point_obj_iter =	$this->config->get_option('file_list_point_obj_iteration');
		if (empty($prev_list_point_obj_iter)) {
			$prev_list_point_obj_iter = 0;
		}
		dark_debug($prev_list_point_obj_iter, "--------prev_list_point_obj_iter--------");
		manual_debug_wptc('', 'iterator_into_db starts');

		$FilesArray = array();
		$iter_obj_count = 0; // get prev from db
		foreach ($TFiles as $Ofiles) {
			$iter_obj_count++;
			if ($iter_obj_count <= $prev_list_point_obj_iter) {
				dark_debug($iter_obj_count, '---------$iter_obj_count------------');
				dark_debug(array(), '---------1 continue------------');
				continue;
			}
			$cur_iter_count = $loop_limit = 0;
			$not_yet_comma = true;
			$qry = '';
			$prev_iter_count = get_common_multicall_key_status('file_list_point_obj', $iter_obj_count);
			if (empty($prev_iter_count)) {
				$prev_iter_count = 0;
			}
			dark_debug($prev_iter_count, '---------$prev_iter_count------------');
			foreach ($Ofiles as $currentfile) {
				if($cur_iter_count++ < $prev_iter_count){
					continue;
				}
				$prev_total_iter_count++;

				$file_path = $currentfile->getPathname();
				$file_path = wp_normalize_path($file_path);

				//skip if folder actions
				if (basename($file_path) == '.' || basename($file_path) == '..' || override_is_dir($file_path)) {
					continue;
				}

				//always include backup and backup-meta files
				if (stripos($file_path, 'backup.sql') !== FALSE || stripos($file_path, 'meta-data') !== FALSE) {
					$always_backup = 1;
				} else {
					$always_backup = 0;
				}

				//skip if its excluded file
				$excluded = $this->exclude_class_obj->is_excluded_file($file_path);
				if ($excluded && $always_backup == 0) {
					// dark_debug($file_path, '---------------excluded-----------------');
					continue;
				}

				//skip if wptc's file
				$is_wptc_file = is_wptc_file($file_path);
				if ($is_wptc_file) {
					// dark_debug($file_path, '-------$file--------------');
					// dark_debug($is_wptc_file, '---------wptc file so exlcuded by default------------');
					continue;
				}

				//skip auto backup files if its not an auto backup
				if (!$is_auto_backup) {
					if (strpos($file_path, 'wptc_saved_queries.sql') !== false) {
						$wp_filesystem->delete($file_path);
						continue;
					}
					if (strpos($file_path, 'wptcrquery') !== false) {
						continue;
					}
				}
				$loop_limit++;
				//form query
				if ($not_yet_comma) {
					$qry .= "('";
					$not_yet_comma = false;
				} else {
					$qry .= ",('";
				}
				manual_debug_wptc('', 'compute_Md5_Hash start');
				$md5_hash = compute_Md5_Hash($file_path, $qry);
				manual_debug_wptc('', 'compute_Md5_Hash end');
				$qry .= wp_normalize_path($file_path) . "', 'Q', '".$md5_hash."')";

				if ($loop_limit >= $this->iter_loop_count) {
					if (!empty($qry)) {
						$this->insert_into_current_process($qry);
					}
					//save iterator count for multicall
					// $this->config->set_option('file_list_point', $cur_iter_count);
					if (is_wptc_timeout_cut($starting_backup_path_time)) {
						$this->config->set_option('file_list_point', $prev_total_iter_count);
						update_common_multicall_key_status('file_list_point_obj', $iter_obj_count, $cur_iter_count);
						dark_debug(array(), "--------exiting in file list loop--------");
						backup_proper_exit_wptc();
					}
					$loop_limit = 0;
					$qry = '';
					$not_yet_comma = true;
				}

			}
			if (!empty($qry)) {
				$this->insert_into_current_process($qry);
			}
			// clear_common_multicall_key_status('file_list_point_obj', $iter_obj_count);
			$this->config->set_option('file_list_point_obj_iteration', $iter_obj_count);
			$this->config->set_option('file_list_point', $prev_total_iter_count);
		}

		if (!WPTC_DARK_TEST_SIMPLE) {
			dark_debug(array(), '---------Got files list set true------------');
			$this->config->set_option('gotfileslist', 1);
			do_action('update_auto_backup_record_db');
		}
		manual_debug_wptc('', 'aftreIteratorIntoDB');
		WPTC_Factory::get('Debug_Log')->wptc_log_now('Files list collected', 'BACKUP');
		dark_debug(array(), '---------iterator_into_db finished-----------');

	}

	public function insert_into_current_process($qry){
		global $wpdb;
		// dark_debug($qry, "--------qry--------");
		$sql = "insert into " . $wpdb->base_prefix . "wptc_current_process (file_path, status, file_hash) values $qry";
		// dark_debug($sql, '---------$sql------------');
		$result = $wpdb->query($sql);
		// dark_debug($result, "--------result--------");
	}

	public function check_and_record_as_deleted($file_arr) {
		//$wpdb->query("insert into ".$wpdb->base_prefix."wptc_current_process (file_path, status) values $qry");
	}

	public function wtc_report_issue($id = null) {
		global $wpdb;
		$config = WPTC_Factory::get('config');
		if ($id == null) {
			$logger = WPTC_Factory::get('logger');
			$logs = $logger->get_log();
			if ($logs && !empty($logs)) {
				$report = array();
				foreach ($logs as $log) {
					$record = explode('@', $log);
					$Recordtime = new DateTime($record[0]);
					$Today = new DateTime(date('Y-m-d 00:00:00'));
					if ($Recordtime > $Today) {
						$report[] = $record;
					}
				}
			}
		} else {
			$specficlog = $wpdb->get_row('SELECT * FROM ' . $wpdb->base_prefix . 'wptc_activity_log WHERE id = ' . $id, OBJECT);
			if ($specficlog) {
				if ($specficlog->action_id != '') {
					$action_log = $wpdb->get_results('SELECT * FROM ' . $wpdb->base_prefix . 'wptc_activity_log WHERE action_id = ' . $specficlog->action_id, OBJECT);
					if (count($action_log) > 0) {
						foreach ($action_log as $all) {
							$report[] = unserialize($all->log_data);
						}
					} else {
						$report = unserialize($specficlog->log_data);
					}
				} else {
					$report = unserialize($specficlog->log_data);
				}

			}
		}
		$email_id = $config->get_option('main_account_email');
		$reportIssue = array();

		$reportIssue['reportTime'] = time();
		$reportIssue['log'] = $report;

		$result['lname'] = $config->get_option('main_account_name');
		$result['cemail'] = $email_id;

		$result['admin_email'] = $email_id;
		$result['idata'] = str_replace("'", '`', serialize($reportIssue));

		dark_debug($result, "--------wtc_report_issue--------");

		return json_encode($result);
	}

	public function wtc_get_anonymous_data() {
		global $wpdb;
		$anonymous = array();
		$anonymous['server']['PHP_VERSION'] = phpversion();
		$anonymous['server']['PHP_CURL_VERSION'] = curl_version();
		$anonymous['server']['PHP_WITH_OPEN_SSL'] = function_exists('openssl_verify');
		$anonymous['server']['PHP_MAX_EXECUTION_TIME'] = ini_get('max_execution_time');
		$anonymous['server']['MYSQL_VERSION'] = $wpdb->get_var("select version() as V");
		$anonymous['server']['OS'] = php_uname('s');
		$anonymous['server']['OSVersion'] = php_uname('v');
		$anonymous['server']['Machine'] = php_uname('m');

		$anonymous['server']['PHPDisabledFunctions'] = explode(',', ini_get('disable_functions'));
		array_walk($anonymous['server']['PHPDisabledFunctions'], 'trim_value_wptc');

		$anonymous['server']['PHPDisabledClasses'] = explode(',', ini_get('disable_classes'));
		array_walk($anonymous['server']['PHPDisabledClasses'], 'trim_value_wptc');

		return $anonymous;
	}

	//Reset the config values after completing backup
	public function wtc_backup_complete_reset_var() {
		global $wpdb;
		global $wp_filesystem;

		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-005');
				return false;
			}
		}

		$config = WPTC_Factory::get('config');
		$userTimenow = $config->get_wptc_user_today_date_time('Y-m-d');
		//Daily Main cycle complete process
		if ($config->get_option('wptc_main_cycle_running')) {
			$config->set_option('wptc_today_main_cycle', $userTimenow);
			$config->set_option('wptc_main_cycle_running', false);
		}
		//Regular Sub Cycle complete process
		if ($config->get_option('wptc_sub_cycle_running')) {
			$config->set_option('wptc_sub_cycle_running', false);
		}
		$wptcrquery_secret = $config->get_option('wptcrquery_secret');
		if (!empty($wp_filesystem) && $wp_filesystem->exists($config->get_option('backup_db_path') . '/wptcrquery/wptc_saved_queries.sql.' . $wptcrquery_secret)) {
			$in_progress = $config->get_option('in_progress', true);
			if(empty($in_progress)){
				dark_debug(array(), '-----------Break in middle because force stop new-------------');
				// send_response_wptc('backup_stopped_manually', 'BACKUP');
			} else {
				$wp_filesystem->delete($config->get_option('backup_db_path') . '/wptcrquery/wptc_saved_queries.sql.' . $wptcrquery_secret);
				$config->set_option('wptcrquery_secret', false);
			}
		}
		//resetting common Config values
		$config->set_option('total_file_count', 0);
	}

	function add_backup_general_data($complete_data = null){
		//Add Backup general data into DB
		if ($complete_data != null) {
			if ($complete_data['files_count'] > 0) {
				$config = WPTC_Factory::get('config');
				$Btype = $config->get_option('wptc_current_backup_type');
				global $wpdb;
				$wpdb->insert($wpdb->base_prefix . 'wptc_backups', array('backup_id' => $complete_data['backup_id'], 'backup_type' => $Btype, 'files_count' => $complete_data['files_count'], 'memory_usage' => $complete_data['memory_usage']));
			}
		}
	}
}