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

class WPTC_Processed_Restoredfiles extends WPTC_Processed_Base {
	protected function getTableName() {
		return 'restored_files';
	}

	protected function getProcessType() {
		$options_obj = WPTC_Factory::get('config');
		if (!$options_obj->get_option('is_bridge_process')) {
			return null;
		} else {
			return 'restore';
		}
	}

	protected function getId() {
		return 'file';
	}

	protected function getRevisionId() {
		return 'revision_id';
	}

	protected function getRestoreTableName() {
		return 'restored_files';
	}

	protected function getFileId() {
		return 'file_id';
	}

	protected function getUploadMtime() {
		return 'mtime_during_upload';
	}

	public function get_restored_files_from_base() {
		return $this->get_processed_restores();
	}

	public function get_restore_queue_from_base() {
		return $this->get_all_restores();
	}

	public function get_limited_restore_queue_from_base() {
		return $this->get_limited_restores();
	}

	public function get_limited_recorded_files_of_this_folder_from_base($cur_res_b_id, $folder_name) {
		return $this->get_limited_recorded_files_of_this_folder_for_res_id($cur_res_b_id, $folder_name);
	}

	public function get_limited_recorded_files_queue_from_base($cur_res_b_id) {
		return $this->get_limited_recorded_files_for_res_id($cur_res_b_id);
	}

	public function get_file_count() {
		$this_count = $this->db->get_var(" SELECT COUNT(*) FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()} WHERE download_status = 'done' ");
		return $this_count;
	}

	public function get_file($file_name) {
		//dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);

		$file_name = wp_normalize_path($file_name);

		$prepared_query = $this->db->prepare(" SELECT * FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()} WHERE file = %s ", $file_name);

		$this_file = $this->db->get_results($prepared_query);

		if (!empty($this_file)) {
			return $this_file[0];
		}

		return false;
	}

	public function get_formatted_sql_file_for_restore_to_point_id($backup_id, $type) {
		dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);

		if ($type == 'D' || $type == 'M') {
			$prepared_query = $this->db->prepare(" SELECT * FROM {$this->db->base_prefix}wptc_processed_files WHERE backupID = %s AND file LIKE '%%-backup.sql%%' AND file LIKE '%%-wptc-secret'", $backup_id);
		} else if ($type == 'S') {
			$prepared_query = $this->db->prepare(" SELECT * FROM {$this->db->base_prefix}wptc_processed_files WHERE backupID = %s AND file LIKE '%%wptc_saved_queries.sql%%'", $backup_id);
		}
		$value = $this->db->get_results($prepared_query);
		if (!empty($value)) {
			$prepared_file_array = array();
			$v = $value[0];

			$prepared_file_array[$value[0]->revision_id] = array();
			//$prepared_file_array[$value[0]->revision_id]['file_name'] = str_replace("\\", "\\\\", $file);
			$prepared_file_array[$value[0]->revision_id]['file_name'] = wp_normalize_path($v->file);
			$prepared_file_array[$value[0]->revision_id]['file_size'] = $value[0]->uploaded_file_size;
			$prepared_file_array[$value[0]->revision_id]['mtime_during_upload'] = $value[0]->mtime_during_upload;
			$prepared_file_array[$value[0]->revision_id]['g_file_id'] = $value[0]->g_file_id;
			$prepared_file_array[$value[0]->revision_id]['file_hash'] = $value[0]->file_hash;

			dark_debug($prepared_file_array, "--------get_formatted_sql_file_for_restore_to_point_id--------");

			return $prepared_file_array;
		}
		return false;
	}

	public function get_copied_file($file_name) {
		//dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);

		$file_name = wp_normalize_path($file_name);

		$prepared_query = $this->db->prepare(" SELECT * FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()} WHERE file = %s AND copy_status = '1'", $file_name);

		$this_file = $this->db->get_results($prepared_query);

		if (!empty($this_file)) {
			return $this_file[0];
		}

		return false;
	}

	public function file_complete($file) {
		$this->update_file($file, 0, 0);
	}

	public function update_file($file, $upload_id = null, $offset = 0, $backupID = 0, $chunked = null) {
		//dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);

		//am adding few conditions to insert the new file with new backup id if the file is modified				//manual
		$may_be_stored_file_obj = $this->get_file($file);

		dark_debug($may_be_stored_file_obj, "--------may_be_stored_file_obj--------");

		if ($may_be_stored_file_obj) {
			$may_be_stored_file_id = $may_be_stored_file_obj->file_id;
		}

		$download_status = 'notDone';

		if (!empty($may_be_stored_file_obj) && !empty($may_be_stored_file_id)) {

			if (!empty($offset) && $offset >= $may_be_stored_file_obj->uploaded_file_size) {
				$offset = 0;
				$download_status = 'done';

				WPTC_Factory::get('config')->set_option('chunked', false);
			} else {
				WPTC_Factory::get('config')->set_option('chunked', true);
			}

			//this condition is to update the tables based on file_id
			$upsert_array = array(
				'file' => $file,
				'offset' => $offset,
				'backupID' => getTcCookie('backupID'), //get the backup ID from cookie
				'file_id' => $may_be_stored_file_id,
				'revision_id' => $may_be_stored_file_obj->revision_id,
				'download_status' => $download_status,
			);
		} else {
			$upsert_array = array(
				'file' => $file,
				'offset' => $offset,
				'download_status' => $download_status,
				'backupID' => getTcCookie('backupID'),
			);
		}
		//dark_debug($upsert_array, "--------restore update--------");
		$this->upsert($upsert_array);
	}

	public function add_files_for_restoring($files_to_restore, $Backuptype) {
		$recorded_query_coming = false;
		if (!empty($files_to_restore)) {
			foreach ($files_to_restore as $revision => $file_dets) {
				dark_debug($file_dets, "--------file_dets_restore--------");
				if (empty($file_dets) || empty($file_dets['file_name'])) {
					continue;
				}
				$this_filename = $file_dets['file_name'];
				if ((false !== strpos($this_filename, "backup.sql")) && (false !== strpos($this_filename, "wptc-secret"))) {
					$this_pos = strripos($this_filename, ".sql") + 4;
					$this_filename = substr($this_filename, 0, $this_pos);
				}
				if ((false !== strpos($this_filename, 'wptc_saved_queries.sql'))) {
					$recorded_query_coming = true;
				} else {
					if ($this_filename != "") {
						if (empty($file_dets['file_hash'])) {
							$file_hash = $this->get_file_hash($this_filename, $file_dets['backup_id']);
						} else {
							$file_hash = $file_dets['file_hash'];
						}
						$revision_id = empty($file_dets['revision_id']) ? $revision : $file_dets['revision_id'];
						$upsert_result = $this->upsert(array(
							'file' => $this_filename,
							'revision_id' => $revision_id,
							'offset' => null,
							'backupID' => getTcCookie('backupID'),
							'uploaded_file_size' => empty($file_dets['file_size']) ? 0 : $file_dets['file_size'],
							//'download_status' => ($file_dets['file_size'] > 4024000) ? 'done' : 'notDone', //am adding an extra condition for chunked download
							'download_status' => 'notDone',
							'g_file_id' => (empty($file_dets['g_file_id'])) ? '' : $file_dets['g_file_id'],
							'mtime_during_upload' => $file_dets['mtime_during_upload'],
							'file_hash' => $file_hash,
						));
					}
				}
			}
			if ($recorded_query_coming && $Backuptype == 'S') {
				$this->insert_files_of_DB_Recording();
			}
		}
	}

	public function add_files($new_files) {
		//dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);
		foreach ($new_files as $file) {
			$this->upsert(array(
				'file' => $file['file'],
				'uploadid' => null,
				'offset' => null,
				'backupID' => getTcCookie('backupID'),
				'revision_number' => $file['revision_number'],
				'revision_id' => $file['revision_id'],
				'mtime_during_upload' => $file['mtime_during_upload'],
				'download_status' => 'done',
				'copy_status' => $file['copy_status'],
			));
		}
		return $this;
	}

	public function getDBrevisionsFiles() {
		global $wpdb;
		$config = WPTC_Factory::get('config');
		$selected_id = $config->get_option("selected_id_for_restore");
		$Processed_files = WPTC_Factory::get('processed-files');
		$Backup_type = $Processed_files->backup_type_check($selected_id);

		//Checking the backup type is S . if not return empty else need to take all revision of file upto near main backup
		if ($Backup_type == 'S') {
			//Getting main backup nearby the sub cycle we are now restoring
			$maincycleID = $wpdb->get_row('SELECT backup_id FROM `' . $wpdb->base_prefix . 'wptc_backups` WHERE ((`backup_type` = "M") OR (`backup_type` = "D")) AND `backup_id` < ' . $selected_id . ' ORDER BY `backup_id` DESC LIMIT 1');
			if (empty($maincycleID) || empty($maincycleID->backup_id)) {
				return array();
			}
			if ($maincycleID->backup_id != '') {
				//getting all db files from mainCycleid and current restore point
				$db_files = $wpdb->get_results('SELECT * FROM `wp_wptc_processed_files` WHERE (`file` LIKE "%wptc_saved_queries.sql%" AND (`backupID` > ' . $maincycleID->backup_id . ' ) AND (`backupID` <= ' . $selected_id . ' )) OR (`file` LIKE "%wptc-secret%" AND file NOT LIKE "%db_meta_data%" AND (`backupID` >= ' . $maincycleID->backup_id . ' ) AND (`backupID` <= ' . $selected_id . ' ) )');
				return $db_files;
			}
		}
		return array();
	}

	public function insert_files_of_DB_Recording() {
		global $wpdb;
		$config = WPTC_Factory::get('config');
		$db_files = $this->getDBrevisionsFiles();
		if (count($db_files) > 0) {
			foreach ($db_files as $ke => $val) {
				$filename = $val->file;
				if ((false !== strpos($filename, "backup.sql")) && (false !== strpos($filename, "wptc-secret"))) {
					$this_pos = strripos($filename, ".sql") + 4;
					$filename = substr($filename, 0, $this_pos);
				}
				if (false !== strpos($filename, "wptc_saved_queries.sql")) {
					$filename = $this->renameSqlfile($filename);
				}

				$insertarr = null;
				$insertarr = array(
					'file' => $filename,
					'revision_id' => $val->revision_id,
					'offset' => null,
					'backupID' => getTcCookie('backupID'),
					'uploaded_file_size' => $val->uploaded_file_size,
					'download_status' => 'notDone',
					'g_file_id' => (empty($val->g_file_id)) ? '' : $val->g_file_id,
				);
				$insert = $wpdb->insert($wpdb->base_prefix . 'wptc_processed_restored_files', $insertarr);
			}
			$config->set_option('start_renaming_sql', false);
		}
	}

	//Function for renaming the sql files for downloading
	public function renameSqlfile($filename) {
		$config = WPTC_Factory::get('config');
		if (!$config->get_option('start_renaming_sql')) {
			$config->set_option('start_renaming_sql', true);
			$config->set_option('rename_count', 1);
			$count = 1;
		} else {
			$rename_count = intval($config->get_option('rename_count'));
			if ($rename_count) {
				$count = $rename_count + 1;
				$config->set_option('rename_count', $count);
			}
		}
		$filename = (substr($filename, 0, -4)) . '_' . $count . '.sql';
		return $filename;
	}

	private function get_file_hash($file_name, $backupID){
		$sql = "SELECT file_hash FROM {$this->db->prefix}wptc_processed_files WHERE file = '".$file_name."' AND backupID=".$backupID." ORDER BY file_id";
		return $this->db->get_var($sql);
	}
}
