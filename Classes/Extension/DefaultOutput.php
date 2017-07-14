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

class WPTC_Extension_DefaultOutput extends WPTC_Extension_Base {
	const MAX_ERRORS = 100;

	private
	$error_count,
	$root,
	$processed_files
	;

	public function set_root($root) {
		$this->root = $root;
		return $this;
	}

	public function out($source, $file, $file_hash, $processed_file = null, $is_processed_atleast_once_form_beginning = null, $starting_backup_path_time = false, $purge_status = false) {
		// dark_debug(func_get_args(), '---------out()------------');
		if ($this->error_count > self::MAX_ERRORS) {
			WPTC_Factory::get('logger')->log(sprintf(__("The backup is having trouble uploading files to " . DEFAULT_REPO_LABEL . ", it has failed %s times and is aborting the backup.", 'wptc'), self::MAX_ERRORS), 'backup_error');
			throw new Exception(sprintf(__('The backup is having trouble uploading files to ' . DEFAULT_REPO_LABEL . ', it has failed %s times and is aborting the backup.', 'wptc'), self::MAX_ERRORS));
			$backup = new WPTC_BackupController();
			$backup->proper_backup_force_complete_exit();
		}
		if (!$this->dropbox) {
			WPTC_Factory::get('logger')->log(sprintf(__("The backup is having trouble uploading files to " . DEFAULT_REPO_LABEL . ", it has failed %s times and is aborting the backup.", 'wptc'), self::MAX_ERRORS), 'backup_error');
			throw new Exception(__("" . DEFAULT_REPO_LABEL . " API not set"));
			$backup = new WPTC_BackupController();
			$backup->proper_backup_force_complete_exit();
		}
		$dropbox_path = $this->config->get_cloud_path($source, $file, $this->root);
		$dropbox_path = wp_normalize_path($dropbox_path);
		if (empty($this->processed_files)) {
			$this->processed_files = WPTC_Factory::get('processed-files');
		}
		try {
			$drop_time = microtime(true);
			$endTimeDrop = microtime(true) - $drop_time;
			$file_size = filesize($file);
			if (!$is_processed_atleast_once_form_beginning ||
				(!empty($processed_file->uploadid) && $processed_file->offset <= $file_size) ||
				$purge_status == true ||
				($this->processed_files->is_file_modified_from_before_backup($file, $file_size, $file_hash))
			) {

				if ($file_size > $this->get_chunked_upload_threashold()) {

					$msg = __("Uploading large file '%s' (%sMB) in chunks", 'wptc');

					if ($processed_file && $processed_file->offset > 0) {
						$msg = __("Resuming upload of large file '%s'", 'wptc');
					}

					WPTC_Factory::get('logger')->log(sprintf($msg, basename($file), round($file_size / 1048576, 1)));
					return $this->dropbox->chunk_upload_file($dropbox_path, $file, $processed_file, $starting_backup_path_time);
				} else {
					return $this->dropbox->upload_file($dropbox_path, $file);
				}
			} else {
				return 'exist';
			}

		} catch (Exception $e) {
			WPTC_Factory::get('logger')->log(__("Error uploading to Cloud " . $e->getMessage(), 'wptc'), 'backup_progress', $this->backup_id);

			$this->error_count++;

			//if there is any error we are showing it via ajax
			$error_array = array();
			$temp_file = wp_normalize_path($file);
			$error_array['file_name'] = $temp_file;
			$error_array['error'] = strip_tags($e->getMessage());
			$this->config->append_option_arr_bool_compat('mail_backup_errors', $error_array, 'unable_to_upload');

			return $error_array;
		}
	}
	public function out_meta_data_backup($file) {
		try {
			global $wptc_profiling_start;
			// $starting_backup_path_time = $this->config->get_option('wptc_profiling_start');
			$starting_backup_path_time = $wptc_profiling_start;
			$dropbox_path = $this->config->get_option('dropbox_location') . '-meta-data';
			if (filesize($file) >= WPTC_CHUNKED_UPLOAD_THREASHOLD) {
				$s3_part_number = $meta_data_upload_id = $s3_parts_array = '';
				$meta_data_offset = $this->config->get_option('meta_data_upload_offset');
				if (empty($meta_data_offset)) {
					$meta_data_offset = 0;
				} else if ($meta_data_offset == -1) {
					return false;
				} else {
					$meta_data_upload_id = $this->config->get_option('meta_data_upload_id');
					$s3_part_number = $this->config->get_option('meta_data_upload_s3_part_number');
					$s3_parts_array = $this->config->get_option('meta_data_upload_s3_parts_array');
					if (!empty($s3_part_number)) {
						$s3_parts_array = unserialize($s3_parts_array);
					}
				}
				$processed_file['offset'] = $meta_data_offset;
				$processed_file['upload_id'] = $meta_data_upload_id;
				$processed_file['s3_part_number'] = $s3_part_number;
				$processed_file['s3_parts_array'] = $s3_parts_array;
				return $this->dropbox->chunk_upload_file($dropbox_path, $file, $processed_file, $starting_backup_path_time, 1);
			} else {
				return $this->dropbox->upload_file($dropbox_path, $file);
			}
		} catch (Exception $e) {
			WPTC_Factory::get('logger')->log(__("Error uploading to Cloud " . $e->getMessage(), 'wptc'), 'backup_progress', $this->backup_id);

			$this->error_count++;

			//if there is any error we are showing it via ajax
			$error_array = array();
			$error_array['message'] = "Error uploading $file to " . DEFAULT_REPO_LABEL . "";
			$error_array['error'] = strip_tags($e->getMessage());

			$this->config->append_option_arr_bool_compat('mail_backup_errors', $error_array, 'unable_to_upload');
			$this->config->set_option('is_meta_data_backup_failed', 'yes');
			@unlink($file);
			return $error_array;
		}
	}

	public function drop_download($source, $file, $revision = null, $processed_file = null, $restore_single_file = null, $meta_file_download = null) {
		dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);
		if (!$this->dropbox) {
			if($meta_file_download == 1){
				$this->dropbox = WPTC_Factory::get(DEFAULT_REPO);
			} else {
				WPTC_Factory::get('logger')->log(__(" API not set"), 'restore_error');
				throw new Exception(__(" API not set"));
			}
		}

		$fileindrop = $file;
		if (strpos($file, 'wptc_saved_queries') !== false) {
			$fileindrop = $this->delete_all_between('wptc_saved_queries', '.sql', $file);
		}
		$dropbox_path = $this->config->get_cloud_path($source, $fileindrop, $this->root);
		dark_debug($dropbox_path, '---------$dropbox_path drop_download------------');
		try {
			$dropbox_source_file = $dropbox_path . '/' . basename($fileindrop);
			dark_debug($dropbox_source_file, '---------$dropbox_source_file------------');
			if (!empty($meta_file_download)) {
				$dropbox_source_file = $source;
			}

			if ($restore_single_file['uploaded_file_size'] < 4194304) {
				return $this->dropbox->download_file($dropbox_source_file, $file, $revision, null, $restore_single_file['g_file_id']);
			} else {
				$isChunkDownload['c_offset'] = (!empty($processed_file->offset)) ? $processed_file->offset : 0; //here am getting the restored files offset ..
				if (!$processed_file) {
					$isChunkDownload['c_limit'] = 4194304;
				} else {
					$isChunkDownload['c_limit'] = (($isChunkDownload['c_offset'] + 4194304) > $processed_file->uploaded_file_size) ? ($processed_file->uploaded_file_size) : ($isChunkDownload['c_offset'] + 4194304);
				}
				if ($meta_file_download == 1) {
					$meta_data_status = $this->config->get_option('meta_chunk_download');
					if (!empty($meta_data_status)) {
						$meta_data_status = unserialize($meta_data_status);
						$download_status = $meta_data_status['download_status'];
						if ($download_status == 'done') {
							return 'already completed';
						}
						$offset = $meta_data_status['c_offset'];
						$isChunkDownload['c_offset'] = $offset;
						$isChunkDownload['c_limit'] = (($isChunkDownload['c_offset'] + 4194304) > $processed_file->uploaded_file_size) ? ($processed_file->uploaded_file_size) : ($isChunkDownload['c_offset'] + 4194304);
					} else {
						$isChunkDownload['c_limit'] = 4194304;
					}
				}
				return $this->dropbox->chunk_download_file($dropbox_source_file, $file, $revision, $isChunkDownload, $restore_single_file['g_file_id'], $meta_file_download);
			}

		} catch (Exception $e) {
			dark_debug($e->getMessage(), "--------default output exception--------");
			WPTC_Factory::get('logger')->log(sprintf(__("Error downloading '%s' : %s", 'wptc'), $file, strip_tags($e->getMessage())), 'restore_error');

			reset_restore_related_settings_wptc();

			$this->error_count++;

			$error_array = array();
			$error_array['error'] = strip_tags($e->getMessage());
			echo json_encode($error_array);

			exit;
		}
	}

	public function delete_all_between($beginning, $end, $string) {
		$beginningPos = strrpos($string, $beginning) + strlen($beginning);
		$endPos = strrpos($string, $end);
		$len = $endPos - $beginningPos;
		if ($beginningPos === false || $endPos === false) {
			return $string;
		}
		$textToDelete = substr($string, $beginningPos, $len);
		dark_debug($textToDelete, "--------textToDelete--------");
		return str_replace($textToDelete, '', $string);
	}

	public function start() {
		return true;
	}

	public function end() {}
	public function complete() {}
	public function failure() {}

	public function get_menu() {}
	public function get_type() {}

	public function is_enabled() {}
	public function set_enabled($bool) {}
	public function clean_up() {}
}
