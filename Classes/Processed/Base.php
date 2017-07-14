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

abstract class WPTC_Processed_Base {
	protected
	$db,
	$default_life_span,
	$config,
	$processed = array()
	;

	public function __construct() {
		$this->default_life_span = 60 * 60 * 24 * 30; //30 days default life span
		$this->existing_users_rev_limit_hold = 60 * 60 * 24 * 15; //30 days default life span
		$this->default_revison_limit = 30; //30 days
		$this->db = WPTC_Factory::db();
		$this->config = WPTC_Factory::get('config');
	}

	abstract protected function getTableName();

	abstract protected function getProcessType();

	abstract protected function getRestoreTableName();

	abstract protected function getId();

	abstract protected function getRevisionId();

	abstract protected function getFileId(); //file column is not unique now .. so we should update using file_id

	abstract protected function getUploadMtime();

	protected function getBackupID() {
		return 'backupID';
	}

	protected function getLifeSpan() {
		return 'life_span';
	}

	protected function getVar($val) {
		return $this->db->get_var(
			$this->db->prepare("SELECT * FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()} WHERE {$this->getId()} = %s", $val)
		);
	}

	protected function get_processed_restores($this_backup_id = null) {
		$all_restores = $this->db->get_results(
			$this->db->prepare("
				SELECT *
				FROM {$this->db->base_prefix}wptc_processed_{$this->getRestoreTableName()} ")
		);

		return $all_restores;
	}

	protected function getBackups($this_backup_id = null, $backups_view = false, $specific_dir = null, $get_files_count = null) {
		$current_time = microtime(true);
		$config = WPTC_Factory::get('config');
		if($this->is_existing_users_rev_limit_hold_expired()){
			$revision_limit = $config->get_option('revision_limit');
		} else {
			$revision_limit = $this->default_revison_limit;
		}
		// dark_debug($revision_limit, '---------$revision_limit------------');
		if (empty($revision_limit)) {
			$revision_limit = WPTC_FALLBACK_REVISION_LIMIT_DAYS;
			$config->set_option('revision_limit', WPTC_FALLBACK_REVISION_LIMIT_DAYS);
		}
		// dark_debug($revision_limit, '---------$revision_limit------------');
		$last_month_time = strtotime(date('Y-m-d', strtotime('today - '.$revision_limit.' days')));
		// dark_debug($last_month_time, '---------$last_month_time------------');
		if (!empty($get_files_count)) {
			$sql = $this->db->prepare("
					SELECT count(*)
					FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()}
					WHERE {$this->getBackupID()} = %s AND is_dir = 0 ", $this_backup_id);
			$all_backups = $this->db->get_results($sql);
		} else if ($backups_view == true && !empty($this_backup_id)) {
			$sql = $this->db->prepare("
					SELECT *
					FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()}
					WHERE {$this->getBackupID()} = %s AND parent_dir = %s ", $this_backup_id, rtrim(wp_normalize_path($specific_dir), '/'));
			$all_backups = $this->db->get_results($sql);
		} else if (empty($this_backup_id)) {
			$all_backups = $this->db->get_results(
				$this->db->prepare("
				SELECT DISTINCT backupID
				FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()}
				WHERE {$this->getBackupID()} > %s ", $last_month_time)
			);
		} else {
			$all_backups = $this->db->get_results(
				$this->db->prepare("
				SELECT *
				FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()}
				WHERE {$this->getBackupID()} = %s ", $this_backup_id)
			);
		}
		return $all_backups;
	}

	protected function get_db_backups($this_backup_id, $path) {
		$sql = $this->db->prepare("
					SELECT *
					FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()}
					WHERE {$this->getBackupID()} = %s AND parent_dir = %s AND file NOT LIKE '%%db_meta_data%%'", $this_backup_id, $path);
		$all_backups = $this->db->get_results($sql);
		if (!empty($all_backups)) {
			$all_result = json_decode(json_encode($all_backups), true);
		}
		return $all_backups;
	}

	protected function get_all_restores() {
		$all_restores = $this->db->get_results("
			SELECT *
			FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()}  "//manual
		);
		return $all_restores;
	}

	protected function get_last_backup_id() {
		$all_restores = $this->db->get_var("
			SELECT backup_id
			FROM {$this->db->base_prefix}wptc_backup_names ORDER BY this_id DESC LIMIT 0,1"//manual
		);
		return $all_restores;
	}

	protected function get_limited_restores() {
		$all_restores = $this->db->get_results("
			SELECT *
			FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()} WHERE download_status != 'done' LIMIT 1"//manual
		);
		return $all_restores;
	}

	protected function get_limited_recorded_files_of_this_folder_for_res_id($cur_res_b_id, $parent_folder) {
		$prepared_query = $this->db->prepare(" SELECT file, revision_id	FROM {$this->db->base_prefix}wptc_processed_files A WHERE backupID = %f AND file NOT LIKE '%%-wptc-secret%%' AND file NOT LIKE '%%db_meta_data%%' AND file NOT LIKE '%%wptc_saved_queries.sql' AND file LIKE '%%%s%%' AND is_dir = '0' AND NOT EXISTS (SELECT file, revision_id FROM {$this->db->base_prefix}wptc_processed_restored_files B WHERE A.file = B.file) LIMIT 30 ", $cur_res_b_id, $parent_folder); //tricky; used like to get  childs from path.
		$all_restores = $this->db->get_results($prepared_query);
		return $all_restores;
	}

	protected function get_limited_recorded_files_for_res_id($cur_res_b_id) {
		$prepared_query = $this->db->prepare(" SELECT file, revision_id	FROM {$this->db->base_prefix}wptc_processed_files A WHERE backupID <= %f AND file NOT LIKE '%%-wptc-secret%%' AND file NOT LIKE '%%wptc_saved_queries.sql' AND file NOT LIKE '%%db_meta_data%%' AND  is_dir = '0' AND NOT EXISTS (SELECT file, revision_id FROM {$this->db->base_prefix}wptc_processed_restored_files B WHERE A.file = B.file) LIMIT 30 ", $cur_res_b_id);

		//dark_debug($prepared_query, "-------get_limited_recorded_files_for_res_id-prepared_queryY--------");

		$all_restores = $this->db->get_results($prepared_query);

		return $all_restores;
	}

	protected function upsert($data) {
		//dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);
		if (!empty($data[$this->getUploadMtime()])) {
			//am introducing this condition to avoid conflicts with multipart upload     manual
			//am adding an extra condition to check the modified time (if the modified time is different then add the values to DB or else leave it)
			$exists = $this->db->get_var(
				$this->db->prepare("SELECT * FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()} WHERE {$this->getId()} = %s AND {$this->getBackupID()} = %s", $data[$this->getId()], $data['backupID']));
		} else {

			if (isset($data['is_dir']) && $data['is_dir'] == 1) {
				$exists = $this->db->get_var(
					$this->db->prepare("SELECT * FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()} WHERE {$this->getId()} = %s  AND {$this->getBackupID()} = %s", $data[$this->getId()], $data['backupID']));
			} else {
				$exists = $this->db->get_var(
					$this->db->prepare("SELECT * FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()} WHERE {$this->getId()} = %s  AND {$this->getRevisionId()} = %s", $data[$this->getId()], $data[$this->getRevisionId()])); //must be used only for restoring , i guess
			}
		}

		$last_restore = $this->config->get_option('last_process_restore');
		$restore_progress = $this->config->get_option('in_progress_restore');

		$upsert_result = false;
		if (is_null($exists) || ($last_restore && !$restore_progress)) {
			if (!$this->config->get_option('starting_first_backup') && !$restore_progress && $this->getTableName() != 'dbtables') {
				$this->update_life_span($data);
			}
			// dark_debug($data, '---------$data------------');
			// dark_debug($this->getTableName(), '---------$this->getTableName()------------');
			if ($this->getTableName() === 'dbtables') {
				$result = $this->db->delete("{$this->db->base_prefix}wptc_processed_{$this->getTableName()}", array( 'name' => $data['name'] ));
				// dark_debug($result, '---------delete $result------------');
				$upsert_result = $this->db->insert("{$this->db->base_prefix}wptc_processed_{$this->getTableName()}", $data);
			} else {
				$upsert_result = $this->db->insert("{$this->db->base_prefix}wptc_processed_{$this->getTableName()}", $data);
			}

		} else {
			if (isset($data['is_dir']) && $data['is_dir'] == 1) {

				$upsert_result = $this->db->update("{$this->db->base_prefix}wptc_processed_{$this->getTableName()}", $data, array($this->getId() => $data[$this->getId()], 'backupID' => $data['backupID'])); //am changing the whole update process to file_id
				global $wpdb;
			} else {
				if (!empty($data['file_id'])) {
					$upsert_result = $this->db->update("{$this->db->base_prefix}wptc_processed_{$this->getTableName()}", $data, array($this->getFileId() => $data[$this->getFileId()])); //am changing the whole update process to file_id
				} else {
					if (isset($data[$this->getRevisionId()]) && isset($data['uploaded_file_size']) && ($data['uploaded_file_size'] > 4024000)) {
						$upsert_result = $this->db->update("{$this->db->base_prefix}wptc_processed_{$this->getTableName()}", $data, array($this->getId() => $data[$this->getId()] , 'backupID' => $data['backupID'])); //am changing the whole update process to file_id
					} else {
						$temp_data = $data;
						unset($temp_data['file']);
						$upsert_result = $this->db->update("{$this->db->base_prefix}wptc_processed_{$this->getTableName()}", $temp_data, array($this->getId() => $data[$this->getId()], $this->getRevisionId() => $data[$this->getRevisionId()])); //am changing the whole update process to file_id
					}
				}
			}
		}
	}

	public function update_life_span($data){
		// dark_debug($data, '---------update_life_span $data------------');
		if (empty($data[$this->getId()]) || (isset($data['is_dir']) && $data['is_dir'] !== 0)) {
			return false;
		}

		$file_id = 	$this->db->get_var(
						$this->db->prepare(
								"SELECT file_id FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()} WHERE {$this->getId()} = %s AND {$this->getBackupID()} < %s ORDER BY `file_id` DESC",$data[$this->getId()], $data[$this->getBackupID()]
						)
					); //to get previous version of file

		dark_debug($file_id, '---------prev revision of file $file_id------------');

		if (empty($file_id)) {
			return false;
		}

		$new_life_span = $data[$this->getBackupID()] + $this->default_life_span; // 30 days life span
		$update_life_span = array($this->getLifeSpan() => $new_life_span);
		$this->db->update("{$this->db->base_prefix}wptc_processed_{$this->getTableName()}", $update_life_span , array($this->getFileId() => $file_id)); //am updating new life span for prev revision
	}

	public function truncate() {
		dark_debug(get_backtrace_string_wptc(), "--------truncate--------");

		$this->db->query("TRUNCATE {$this->db->base_prefix}wptc_processed_{$this->getTableName()}");
	}

	public function get_stored_backup_name($backup_id = null) {
		$this_name = $this->db->get_results("SELECT backup_name FROM {$this->db->base_prefix}wptc_backup_names WHERE backup_id = '$backup_id'");
		if (isset($this_name[0])) {
			return $this_name[0]->backup_name;
		} else {
			return '';
		}
	}

	public function get_backup_id_details($backup_id) {
		$this_name = $this->db->get_results("SELECT * FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()} WHERE backup_id = '$backup_id'");
	}

	public function delete_expired_life_span_backups($days = null, $backup_id = null) {
		$current_time = time();
		$this_name = $this->db->get_results("DELETE FROM {$this->db->base_prefix}wptc_processed_files WHERE life_span IS NOT NULL AND life_span < $current_time");
	}

	public function delete_last_month_backups($days = null, $backup_id = null) {
		$revision_limit = WPTC_Factory::get('config')->get_option('revision_limit');
		if(!$this->is_existing_users_rev_limit_hold_expired()){
			return false;
		}
		if (empty($revision_limit)) {
			$revision_limit = WPTC_FALLBACK_REVISION_LIMIT_DAYS;
			WPTC_Factory::get('config')->set_option('revision_limit', WPTC_FALLBACK_REVISION_LIMIT_DAYS);
		}

		$rev_limit_time = strtotime("-$revision_limit days");

		$wptc_backups = $this->db->query("DELETE FROM {$this->db->base_prefix}wptc_backups WHERE backup_id < '$rev_limit_time'");
		dark_debug($wptc_backups, '-----wptc_backups deleted rows count-----------');

		$wptc_backup_names = $this->db->query("DELETE FROM {$this->db->base_prefix}wptc_backup_names WHERE backup_id < '$rev_limit_time'");
		dark_debug($wptc_backup_names, '-----wptc_backup_names deleted rows count-----------');

		$wptc_processed_files = $this->db->query("DELETE FROM {$this->db->base_prefix}wptc_processed_files WHERE is_dir = 1 AND backupID < '$rev_limit_time'");
		dark_debug($wptc_processed_files, '-----wptc_processed_files deleted rows count-----------');

		$limit_time = strtotime("-15 days");
		$wptc_activity_log = $this->db->query("DELETE FROM {$this->db->base_prefix}wptc_activity_log WHERE action_id < '$limit_time'");
		dark_debug($wptc_activity_log, '-----wptc_activity_log deleted rows count-----------');
	}

	private function is_existing_users_rev_limit_hold_expired(){
		$existing_users_updated_time = WPTC_Factory::get('config')->get_option('existing_users_rev_limit_hold');
		if (empty($existing_users_updated_time)) {
			return true;
		}
		if (time() > ($existing_users_updated_time + $this->existing_users_rev_limit_hold)) {
			// dark_debug(array(), '---------expired------------');
			return true;
		}
		// dark_debug(array(), '---------Not expired------------');
		return false;
	}

	public function hide_db_backup_folder($parent_dir, $backup_id) {
		$parent_dir = wp_normalize_path($parent_dir);
		$sql = "SELECT count(file) FROM {$this->db->base_prefix}wptc_processed_files WHERE parent_dir = '$parent_dir' AND backupID = '$backup_id'";
		$count = $this->db->get_var($sql);
		if ($count <= 1) {
			return true;
		}
		return false;
	}

	public function get_future_delete_files($backup_id) {
		$delete_files = $this->db->get_results("SELECT DISTINCT file FROM {$this->db->base_prefix}wptc_processed_files WHERE backupID > '$backup_id'");
		return $delete_files;
	}

	public function get_most_recent_revision($file, $backup_id = '') {
		$sql = $this->db->prepare(" SELECT revision_id,uploaded_file_size,mtime_during_upload,g_file_id,file_hash FROM {$this->db->base_prefix}wptc_processed_files WHERE file = %s AND backupID <= %s ORDER BY file_id DESC LIMIT 0,1 ", $file, $backup_id);
		// dark_debug($sql, '---------$sql get_most_recent_revision------------');
		$this_revision = $this->db->get_results($sql);
		return $this_revision;
	}

	public function get_past_replace_files($backup_id) {
		//$replace_files = $this->db->get_results("SELECT DISTINCT file FROM {$this->db->base_prefix}wptc_processed_files WHERE NOT EXISTS (SELECT DISTINCT file FROM {$this->db->base_prefix}wptc_processed_files WHERE backupID > '$backup_id')  AND backupID < '$backup_id'" );
		$replace_files = $this->db->get_results("SELECT DISTINCT file FROM {$this->db->base_prefix}wptc_processed_files WHERE backupID < '$backup_id'");

		return $replace_files;
	}

	public function get_all_processed_files() {
		$unknown_files = $this->db->get_results("SELECT DISTINCT file FROM {$this->db->base_prefix}wptc_processed_files", ARRAY_N);
		return $unknown_files;
	}

	public function get_all_processed_files_from_and_before_now($backup_id) {
		$unknown_files = $this->db->get_results("SELECT DISTINCT file FROM {$this->db->base_prefix}wptc_processed_files WHERE backupID <= '$backup_id'", ARRAY_N);
		return $unknown_files;
	}

	public function get_file_uploaded_file_size($file, $g_file_id) {
		$sql = $this->db->prepare(" SELECT uploaded_file_size FROM {$this->db->base_prefix}wptc_processed_files WHERE file = %s AND g_file_id <= %s ORDER BY file_id DESC LIMIT 0,1 ", $file, $g_file_id);
		$uploaded_file_size = $this->db->get_var($sql);
		return $uploaded_file_size;
	}

	public function get_parent_details($file){
		$parent_dir = $this->get_parent_dir($file);
		$is_present = $this->is_file_present_in_backup($parent_dir);
		if (!empty($is_present)) {
			$g_file_id = $this->get_g_file_id($parent_dir);
		} else {
			$g_file_id = false;
		}
		return array('parent_dir' => $parent_dir, 'is_present' => $is_present, 'g_file_id' => $g_file_id);
	}

	public function get_g_file_id($file){
		if (empty($file)) {
			return false;
		}
		$sql = $this->db->prepare("
					SELECT g_file_id
					FROM {$this->db->base_prefix}wptc_processed_files
					WHERE file = %s AND g_file_id != 'NULL'", wp_normalize_path($file));
		$g_file_id = $this->db->get_var($sql);
		return $g_file_id;
	}

	public function get_parent_dir($file) {
		return dirname($file);
	}

	public function is_file_present_in_backup($file) {
		$sql = $this->db->prepare("
					SELECT count(file)
					FROM {$this->db->base_prefix}wptc_processed_files
					WHERE file = %s ", wp_normalize_path($file));
		$count = $this->db->get_var($sql);
		return $count;
	}

	public function update_g_file_id($file, $g_file_id){
		$data['file'] = $file;
		$data['g_file_id'] = $g_file_id;
		$upsert_result = $this->db->query("UPDATE `{$this->db->base_prefix}wptc_processed_files` SET g_file_id = '$g_file_id' WHERE file = '$file'");
		dark_debug($upsert_result,'--------$upsert_result-------------------');
	}

	public function insert_g_file_id($file, $g_file_id){
		$data['file'] = $file;
		$data['g_file_id'] = $g_file_id;
		$data['backupID'] = getTcCookie('backupID');
		$insert_result = $this->db->replace("{$this->db->base_prefix}wptc_processed_files", $data);
		// dark_debug($insert_result,'--------------$insert_result-------------');
	}

	public function record_as_skimmed($file_dets) {
		foreach ($file_dets as $file) {
			$upsert_array = array(
				'file' => $file->file,
				'backupID' => getTcCookie('backupID'),
			);
			$this->db->insert("{$this->db->base_prefix}wptc_skimmed_files", $upsert_array);
		}
	}

	public function record_as_deleted($file_dets) {
		foreach ($file_dets as $file) {
			$upsert_array = array(
				'file' => $file->file,
				'backupID' => getTcCookie('backupID'),
			);
			$this->db->insert("{$this->db->base_prefix}wptc_skimmed_files", $upsert_array);
		}
	}

	public function get_last_meta_file(){
		// $sql = "SELECT * FROM {$this->db->base_prefix}wptc_processed_files WHERE file LIKE '%selva%' ORDER BY file DESC";
		$sql = "SELECT * FROM {$this->db->base_prefix}wptc_processed_files WHERE file LIKE '%db_meta_data%' ORDER BY file_id DESC";
		$result = $this->db->get_results($sql);
		if (!empty($result) && is_array($result)) {
			return $result[0];
		} else {
			return false;
		}
	}

	public function update_prev_backups_1(){
		global $settings_ajax_start_time;
		// $settings_ajax_start_time = time() - 20 ;
		$prev_count = $this->config->get_option('update_prev_backups_1_pointer');
		$pointer = empty($prev_count) ? 0 : $prev_count;
		$loop_limit_count = 0;
		while($loop_limit_count++ < 100){
			$query = "SELECT file, file_id, backupID FROM {$this->db->base_prefix}wptc_processed_files WHERE is_dir = 0 ORDER BY file_id ASC LIMIT ".($pointer++)." , 1";
			dark_debug($pointer, '---------$pointer------------');
			dark_debug($query, '---------$query------------');
			$result = $this->db->get_results($query);
			dark_debug($result, '---------$result------------');
			if (empty($result)) {
				dark_debug(array(), '---------All files are updated------------');
				$this->config->delete_option('update_prev_backups_1');
				$this->config->delete_option('update_prev_backups_1_pointer');
				send_response_wptc('updating_prev_backups_1_pointer : '.$pointer, 'UPDATING BACKUPS COMPLETED');
				break;
			}
			$data = array(
				'file' => $result[0]->file,
				'file_id' => $result[0]->file_id,
				'backupID' => $result[0]->backupID,
				'is_dir' => 0,
				);
			$this->update_life_span($data);
			if ($loop_limit_count >= 100) {
				dark_debug($loop_limit_count, '---------$loop_limit_count crossed here------------');
				if (is_wptc_timeout_cut($settings_ajax_start_time)) {
					$this->config->set_option('update_prev_backups_1_pointer', $pointer);
					send_response_wptc('updating_prev_backups_1_pointer : '.$pointer, 'UPDATING BACKUPS PROCESSING');
				}
				$loop_limit_count = 0;
			}
		}
	}

}
