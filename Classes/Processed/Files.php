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

class WPTC_Processed_Files extends WPTC_Processed_Base {
	protected function getTableName() {
		return 'files';
	}

	protected function getProcessType() {
		return 'files';
	}

	protected function getRestoreTableName() {
		return 'restored_files';
	}

	protected function getRevisionId() {
		return 'revision_id';
	}

	protected function getId() {
		return 'file';
	}

	protected function getFileId() {
		return 'file_id';
	}

	protected function getUploadMtime() {
		return 'mtime_during_upload';
	}

	public function get_file_count() {
		return $this->db->get_var("SELECT COUNT(*) FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()} WHERE 	download_status = 'done'");
	}

	public function get_file($file_name) {

		$prepared_query = $this->db->prepare(" SELECT * FROM {$this->db->base_prefix}wptc_processed_files WHERE file = %s AND backupID = %s ", $file_name, getTcCookie('backupID'));
		// dark_debug($prepared_query, '---------$prepared_query------------');
		$this_file = $this->db->get_results($prepared_query);

		if (!empty($this_file)) {
			return $this_file[0];
		}

		return false;
	}

	public function is_file_modified_from_before_backup($file_name, $file_size, $file_hash){
		$sql = $this->db->prepare("SELECT * FROM {$this->db->base_prefix}wptc_processed_files WHERE file = %s ORDER BY file_id DESC LIMIT 0,1", $file_name);
		// dark_debug($sql, '--------is_file_modified_from_before_backup-$sql------------');
		$result = $this->db->get_results($sql);
		// dark_debug($result, '-------is_file_modified_from_before_backup--$result------------');
		if (!empty($result)) {
			if(filemtime($file_name) <= $result[0]->backupID){
				// dark_debug(array(), '---------is_file_modified_from_before_backup mtime modified false------------');
				return false;
			}
			dark_debug(array(), '---------is_file_modified_from_before_backup mtime modified true------------');
			if ($file_size != $result[0]->uploaded_file_size && $result[0]->uploaded_file_size != 1) {
				// dark_debug(array(), '---------is_file_modified_from_before_backup file size true------------');
				return true;
			} else {
				// dark_debug(array(), '---------is_file_modified_from_before_backup file size false so check hash ------------');
				if($this->is_file_modified_from_before_backup_by_hash($file_name, $file_hash)){
					return true;
				}
				return false;
			}
		}

		return true;
	}

	public function is_file_modified_from_before_backup_by_hash($file_name, $current_file_hash){
		$is_hash_required = is_hash_required($file_name);
		if (!$is_hash_required) {
			return false;
		}
		$sql = $this->db->prepare("SELECT file_hash FROM {$this->db->base_prefix}wptc_processed_files WHERE file = %s ORDER BY file_id DESC LIMIT 0,1", $file_name);
		$prev_file_hash = $this->db->get_var($sql);
		dark_debug($prev_file_hash, '---------------$prev_file_hash-----------------');
		if (!empty($prev_file_hash)) {
			if($prev_file_hash != $current_file_hash){
				dark_debug(array(), '---------hash modified true------------');
				return true;
			} else {
				dark_debug(array(), '---------hash modified false--------------');
				return false;
			}
		}

		return true;
	}

	public function get_file_history($file_name) {
		$this_history = $this->db->get_results(
			$this->db->prepare(" SELECT * FROM {$this->db->base_prefix}wptc_processed_files WHERE file = %s", $file_name)
		);

		if (!empty($this_history)) {
			return true;
		}

		return false;
	}

	public function is_file_purged($file_name){
		return false; //We dont follow purge status from 1.7.1 so we do not again backup 30 days old files.
		$sql = $this->db->prepare(" SELECT backupID FROM {$this->db->base_prefix}wptc_processed_files WHERE file = %s ORDER BY file_id DESC LIMIT 0,1", $file_name);
		$last_backup_time = $this->db->get_var($sql);
		if (empty($last_backup_time)) {
			return false;
		}
		$default_purge_time = 30 * 24 * 60 * 60; // 30 days
		$check_purge_time = $last_backup_time + $default_purge_time;
		if($check_purge_time >= microtime(true)){
			return false;
		}
		return true;
	}

	public function file_complete($file) {
		$this->update_file($file, 0, 0);
	}

	public function update_file($file, $upload_id, $offset, $s3_part_number = 1, $s3_parts_array = array()) {

		$may_be_stored_file_obj = $this->get_file($file);
		if ($may_be_stored_file_obj) {
			$may_be_stored_file_id = $may_be_stored_file_obj->file_id;
		}

		if (!empty($may_be_stored_file_obj) && !empty($may_be_stored_file_id)) {
			$upsert_array = array(
				'file' => $file,
				'uploadid' => $upload_id,
				'offset' => $offset,
				'backupID' => getTcCookie('backupID'), //get the backup ID from cookie
				'file_id' => $may_be_stored_file_id,
				'mtime_during_upload' => filemtime($file),
				'uploaded_file_size' => filesize($file),
				's3_part_number' => $s3_part_number,
				's3_parts_array' => serialize($s3_parts_array),
			);
		} else {
			$upsert_array = array(
				'file' => $file,
				'uploadid' => $upload_id,
				'offset' => $offset,
				'backupID' => getTcCookie('backupID'),
				'mtime_during_upload' => filemtime($file),
				's3_part_number' => $s3_part_number,
				's3_parts_array' => serialize($s3_parts_array),
			);
		}
		$this->upsert($upsert_array);
	}

	public function add_files($new_files) {
		dark_debug($new_files,'--------------$new_files-------------');
		foreach ($new_files as $file) {
			process_parent_dirs_wptc(array(
				'file' => $file['filename'],
				'uploadid' => null,
				'offset' => 0,
				'backupID' => getTcCookie('backupID'),
				'revision_number' => $file['revision_number'],
				'revision_id' => $file['revision_id'],
				'mtime_during_upload' => $file['mtime_during_upload'],
				'uploaded_file_size' => $file['uploaded_file_size'],
				'g_file_id' => $file['g_file_id'],
				'cloud_type' => DEFAULT_REPO,
				'file_hash' => $file['file_hash'],
			), 'process_files');
		}

		return $this;
	}
	public function base_upsert($data){
		$this->upsert($data);
	}
	public function get_this_backups_html($this_backup_ids, $specific_dir = null, $type = null, $treeRecursiveCount = 0) {
		// dark_debug(func_get_args(), '---------get_this_backups_html()------------');
		
		$total_folders = 0;
		$backup_dialog_html = '';
		$backup_datas = $this->get_this_backups($this_backup_ids, $specific_dir);
		// dark_debug($backup_datas, '---------$backup_datas------------');
		$plus = 0;
		$this_day = $this_plural = $backup_type = '';
		foreach ($backup_datas as $key => $value) {
			$db_backup = '';
			if($type != 'sibling'){
				$db_backup = $this->get_db_backup_html($key);
			}
			// dark_debug($db_backup, '---------$db_backup------------');
			$sub_content = '';
			$explodedTreeArray = $this->prapare_tree_like_array($value);
			// dark_debug($explodedTreeArray, '---------$explodedTreeArray------------');
			$sub_content = $this->get_tree_div_recursive($explodedTreeArray, $treeRecursiveCount, '', $db_backup);
			if($type == 'sibling'){
				echo $sub_content;
				die();
			}

			if ($backup_type == 'S' && (!$exist)) {
				$plus ++;
			}

			$res_files_count =  json_decode(json_encode($this->getBackups($key, '', '', 1)), true);
			$res_files_count = ($res_files_count[0]['count(*)']) - $plus;
			if (!empty($db_backup)) {
				if($res_files_count != 0) { $res_files_count -= 1; }
				if ($this->is_meta_found_on_backup($key)) {
				if($res_files_count != 0) { $res_files_count -= 1; }
					if ($res_files_count == 0) {
						$sub_content = '';
					}
				}
				if ($res_files_count == 0) {
					$sub_content = '';
				}
			}

			$config = WPTC_Factory::get('config');
			$local_timezone_time = $config->cnvt_UTC_to_usrTime($key);
			$this->modify_schedule_backup_time($local_timezone_time);
			if (empty($sub_content) || stripos($sub_content, 'this_leaf_node') === FALSE && stripos($sub_content, 'folder close') === FALSE) {
				$backup_dialog_html .= '<li class="single_group_backup_content bu_list" this_backup_id="' . $key . '"><div class="single_backup_head bu_meta"><div class="toggle_files"></div><div class="time">' . date('g:i a', $local_timezone_time) . '</div><div class="bu_name" title="'.$this->get_stored_backup_name($key).'">' . $this->get_stored_backup_name($key) . '</div><a class="this_restore disabled btn_wptc" style="display:none">Restore Selected</a><div class="changed_files_count" style="display:none">' . $res_files_count . ' file' . $this_plural . ' changed</div><a class="btn_wptc this_restore_point_wptc">RESTORE SITE TO THIS POINT</a></div><div class="wptc-clear"></div><div class="bu_files_list_cont">' . $db_backup . ' </div><div class="wptc-clear"></div></li>';
			} else {
				$backup_dialog_html .= '<li class="single_group_backup_content bu_list" this_backup_id="' . $key . '"><div class="single_backup_head bu_meta"><div class="toggle_files"></div><div class="time">' . date('g:i a', $local_timezone_time) . '</div><div class="bu_name" title="'.$this->get_stored_backup_name($key).'">' . $this->get_stored_backup_name($key) . '</div><a class="this_restore disabled btn_wptc" style="display:none">Restore Selected</a><div class="changed_files_count" style="display:none">' . $res_files_count . ' file' . $this_plural . ' changed</div><a class="btn_wptc this_restore_point_wptc">RESTORE SITE TO THIS POINT</a></div><div class="wptc-clear"></div><div class="bu_files_list_cont">' . $db_backup . '<div class="item_label">Files</div><ul class="bu_files_list">' . $sub_content . '</ul></div><div class="wptc-clear"></div></li>';
			}
			$this_day = $local_timezone_time;
		}
		return '<div class="dialog_cont"><span class="dialog_close"></span><div class="pu_title">Backups Taken on ' . date('jS F', $this_day) . '</div><ul class="bu_list_cont">' . $backup_dialog_html. '</ul></div>';
	}

	public function get_db_backup_html($key){
		$db_data = $meta_data = '';
		$backup_type = $this->backup_type_check($key);
		$config = WPTC_Factory::get('config');
		if ($backup_type == 'M' || $backup_type == 'D') {
			$path = $config->get_default_backup_dir();
			$path .= '/tCapsule/backups';
			$path = wp_normalize_path($path);
			$db_data = $this->get_db_backups($key, $path);
			if (empty($db_data)) {
				$path = $config->get_alternative_backup_dir();
				$path .= '/tCapsule/backups';
				$path = wp_normalize_path($path);
				$db_data = $this->get_db_backups($key, $path);
			}
		} else if($backup_type == 'S'){

			$path = $config->get_default_backup_dir();
			$path .= '/wptcrquery';
			$path = wp_normalize_path($path);
			$db_data = $this->get_db_backups($key, $path);
			if (empty($db_data)) {
				$path = $config->get_alternative_backup_dir();
				$path .= '/wptcrquery';
				$path = wp_normalize_path($path);
				$db_data = $this->get_db_backups($key, $path);
			}
		}
		$result = $this->prapare_tree_like_array($db_data);
		if(!empty($result)){
			foreach ($result as $file_name => $file_meta) {
				$meta_data = $this->convert_arr_string($file_meta);
			}
		}
		// if (empty($meta_data)) {
		// 	return false;
		// }
		if ($backup_type == '') {
			$db_backup = '<div class="item_label">Database</div><ul class="bu_files_list "><li class="restore_the_db sub_tree_class"><div class="file_path" ' . $meta_data . '>Restore the database</div></li></ul><div class="wptc-clear"></div>';
		} else if ($backup_type == 'S') {
			$exist = false;
			foreach ($db_data as $qkey => $qvalue) {
				if ((strpos($qvalue->file, 'wptc_saved_queries.sql') !== false) || (strpos($qvalue->file, 'wordpress-db_meta_data.sql') !== false)) {
					$exist = true;
					break;
				}
			}
			if ($exist) {
				$db_backup = '<div class="item_label">Database</div><ul class="bu_files_list "><li class="restore_the_db sub_tree_class"><div class="file_path"	' . $meta_data . '>Restore the database</div></li></ul><div class="wptc-clear"></div>';
			} else {
				$db_backup = '';
			}
		} else {
			$db_backup = '<div class="item_label">Database</div><ul class="bu_files_list "><li class="restore_the_db sub_tree_class"><div class="file_path" ' . $meta_data . '>Restore the database</div></li></ul><div class="wptc-clear"></div>';
		}
		return $db_backup;
	}

	public function get_tree_div_recursive($explodedTreeArray, $treeRecursiveCount = 0, $total_sub_content = '', $is_backup_present = '') {
		foreach ($explodedTreeArray as $top_tree_name => $sublings_array) {
		if ($sublings_array['file_name'] === WPTC_WP_CONTENT_DIR.'/uploads') {
			$hide_this_file = $this->hide_db_backup_folder($sublings_array['file_name'], $sublings_array['backup_id']);
			if ($hide_this_file) {
				continue;
			}
		}
			if (is_array($sublings_array)) {
				if($sublings_array['is_dir'] == 1){
					if ($top_tree_name == 'wptcrquery' || $top_tree_name == 'tCapsule' || $top_tree_name == 'db_meta_data') {
						$displ = 'display:none;';
					} else {
						$displ = '';
					}
					if ($top_tree_name == basename(WPTC_WP_CONTENT_DIR) && $is_backup_present) {
						$wp_content_count = $this->is_need_to_show_wp_content($sublings_array['backup_id']);
						if($wp_content_count == 1){
							continue;
						}
					}
					$str = $this->convert_arr_string($sublings_array);
					$total_sub_content .= '<li class="sl' . $treeRecursiveCount . ' sub_tree_class" recursive_count = "'.$treeRecursiveCount.'" style="margin-left:' . (($treeRecursiveCount * 50) - $treeRecursiveCount) . 'px;' . $displ . '" ><div class="folder close" '.$str.'></div><div class="file_path" style="width:70%; word-break:break-all;">' . $top_tree_name . '</div></li>';
				} else {
					$is_sql_class = "";
				$is_sql_li = "";
				if ((strpos($top_tree_name, 'wptc-secret') !== false) || (strpos($top_tree_name, 'wptc_saved_queries') !== false)) {
					$is_sql_class = "sql_file";
					$is_sql_li = "sql_file_li";
				}
				$str = $this->convert_arr_string($sublings_array);
				// dark_debug($str, '---------$str------------');
				$total_sub_content .= '<div class="this_leaf_node ' . $is_sql_class . ' leaf_' . $treeRecursiveCount . '" recursive_count = "'.$treeRecursiveCount.'"><li class="sl' . $treeRecursiveCount . ' ' . $is_sql_li . '" style="margin-left:' . (($treeRecursiveCount * 50) - $treeRecursiveCount) . 'px; word-break: break-all;"><div class="file_path" ' . $str . ' style="width:70%; word-break:break-all;">' . $top_tree_name . '</div></li></div>';
				}
			}
		}
		return '<div class="this_parent_node" recursive_count = "'.$treeRecursiveCount.'">' . $total_sub_content . '</div>';
	}

	public function convert_arr_string($arr){
		$str = ' g_file_id="' . $arr['g_file_id'] . '" file_name="' . $arr['file_name'] . '" file_size="' . $arr['file_size'] . '" revision_id="' . $arr['revision_id'] . '" mod_time="' . $arr['mod_time'] .'" is_dir="' . $arr['is_dir'] . '" backup_id="' . $arr['backup_id'] . '" parent_dir="' . $arr['parent_dir'] . '" '; //am appending file_size,revision_id,mod_time
		return $str;
	}

	public function prapare_tree_like_array($this_file_name_array) {
		$stripped_file_name_array = array();

		if (empty($this_file_name_array)) {
			return $stripped_file_name_array;
		}

		foreach ($this_file_name_array as $k => $v) {
			$this_removed_abs_file_name = wp_normalize_path($v->file);
			$stripped_file_name_array[basename($this_removed_abs_file_name)] =array (
											'backup_id' => $v->backupID,
											'g_file_id' => $v->g_file_id,
											'file_name' => $v->file,
											'file_size' => $v->uploaded_file_size,
											'revision_id' => $v->revision_id,
											'mod_time' => $v->mtime_during_upload,
											'parent_dir' => $v->parent_dir,
											'is_dir' => $v->is_dir ); //am appending file_size,revision_id,mod_time

		}
		return $stripped_file_name_array;
	}

	public function explodeTree($array, $delimiter = '_', $baseval = false) {
		if (!is_array($array)) {
			return false;
		}

		$splitRE = '/' . preg_quote($delimiter, '/') . '/';
		$returnArr = array();
		foreach ($array as $key => $val) {
			// Get parent parts and the current leaf
			$parts = preg_split($splitRE, $key, -1, PREG_SPLIT_NO_EMPTY);
			$leafPart = array_pop($parts);

			// Build parent structure
			// Might be slow for really deep and large structures
			$parentArr = &$returnArr;
			foreach ($parts as $part) {
				if (!isset($parentArr[$part])) {
					$parentArr[$part] = array();
				} elseif (!is_array($parentArr[$part])) {
					if ($baseval) {
						$parentArr[$part] = array('__base_val' => $parentArr[$part]);
					} else {
						$parentArr[$part] = array();
					}
				}
				$parentArr = &$parentArr[$part];
			}

			// Add the final part to the structure
			if (empty($parentArr[$leafPart])) {
				$parentArr[$leafPart] = $val;
			} elseif ($baseval && is_array($parentArr[$leafPart])) {
				$parentArr[$leafPart]['__base_val'] = $val;
			}
		}
		return $returnArr;
	}

	// public function remove_abs_path($this_file_name) {
	// 	//this function will remove the abspath value from the filename path and then replace the "\\" with "/";
	// 	$proper_abs_path = substr(ABSPATH, 0, -1); //for removing (/) in the end of ABSPATH
	// 	$abs_path_pos = strpos($this_file_name, $proper_abs_path);
	// 	if ($abs_path_pos !== false) {
	// 		$abs_path_length = strlen($proper_abs_path); //for removing (//)
	// 		$rem_file_name = substr($this_file_name, $abs_path_length);
	// 		$split_array = explode("\\", $rem_file_name);
	// 		$implode_string = implode("/", $split_array);
	// 		return $implode_string;
	// 	}
	// }

	public function get_this_backups($this_backup_ids, $specific_dir = null) {
		dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);
		if(empty($specific_dir)){
			$specific_dir = WPTC_ABSPATH;
		} else {
			$specific_dir = wp_normalize_path($specific_dir);
		}
		//getting all the backups for each backup IDs and then prepare the html for displaying in dialog box
		$backups_for_backupIds = array();
		if (!empty($this_backup_ids)) {
			$backup_id_array = explode(",", $this_backup_ids);
			if (empty($backup_id_array)) {
				$backup_id_array[0] = $this_backup_ids;
			}
			foreach ($backup_id_array as $key => $value) {
				$single_backups = array();
				$single_backups = $this->getBackups($value, true, $specific_dir);
				if (!empty($single_backups)) {
					$backups_for_backupIds[$value] = $single_backups;
				}
			}
		}

		return $backups_for_backupIds;
	}

	public function modify_schedule_backup_time(&$time){
		$this_day = date("Y-m-d H:i a", $time);
		$hours = date('H', $time);
		$minutes = date('i', $time);
		$meridian = date('a', $time);
		if ($meridian === 'pm') {
			if ($hours == '23') {
				if ($minutes >= 55) {
					$add_remaining = (60 - $minutes) * 60;
					$time = $time + $add_remaining;
				}
			}
		}
	}

	public function get_stored_backups($this_backup_ids = null) {
		$all_backups = $this->getBackups();
		$formatted_backups = array();
		$backupIDs = array();
		if (empty($all_backups) || !is_array($all_backups)) {
			return array();
		}
		foreach ($all_backups as $key => $value) {
			$value_array = (array) $value;
			$formatted_backups[$value_array['backupID']][] = $value_array;
		}
		$backups_count = count($formatted_backups);
		$calendar_format_values = array();
		$all_days = array();
		$all_backup_id = array();

		$config = WPTC_Factory::get('config');

		foreach ($formatted_backups as $k => $v) {
			//this loop is only to calculate the number of backups in a particular day
			$tk = $config->cnvt_UTC_to_usrTime($k);
			$this->modify_schedule_backup_time($tk);
			$this_day = date("Y-m-d", $tk);
			$is_day_exists = array_key_exists($this_day, $all_days);
			if ($is_day_exists) {
				if (!empty($all_days[$this_day])) {
					$all_days[$this_day] += 1;
					$all_backup_id[$this_day][] = $k;
				}
			} else {
				$all_days[$this_day] = 1;
				$all_backup_id[$this_day][] = $k;
			}
		}

		$this_count = 0;
		foreach ($all_days as $key => $value) {
			asort($all_backup_id[$key]);
			if ($value < 2) {
				$this_plural = '';
			} else {
				$this_plural = 's';
			}

			$calendar_format_values[$this_count]['title'] = $value . " Restore point" . $this_plural;
			$calendar_format_values[$this_count]['start'] = $key;
			$calendar_format_values[$this_count]['end'] = $key;
			$calendar_format_values[$this_count]['backupIDs'] = implode(",", array_reverse($all_backup_id[$key])); //am adding an extra value here to pass an ID
			$this_count += 1;
		}
		unset($this_count);
		return $calendar_format_values;
	}

	public function process_last_backup_id(){
		return $this->get_last_backup_id();
	}

	public function get_point_details($stored_backups){
		$total_recent_point = 0;
		$required_restore_points = 10;
		$stored_backups = array_reverse ($stored_backups);
		$temp_stored_backup = array();
		if (empty($stored_backups) || !is_array($stored_backups)) {
			echo "<div class='pu_title'>No restore points found</div>";
			exit;
		}
		foreach ($stored_backups as $key => $backup) {
			if ($total_recent_point >= $required_restore_points) {
				break;
			}
			$temp_stored_backup[$key]['title'] = $stored_backups[$key]['title'];
			$temp_stored_backup[$key]['time'] = $stored_backups[$key]['end'];
			$temp_stored_backup[$key]['backupIDs'] = $stored_backups[$key]['backupIDs'];
			$backup_ids = explode(',', $backup['backupIDs']);
			$i = 0;
			if (empty($backup_ids) || !is_array($backup_ids)) {
				continue;
			}
			foreach ($backup_ids as $backup_id) {
				if ($total_recent_point >= $required_restore_points) {
					break;
				}
				$i++;
				$total_recent_point++;
				$backup_name = $this->get_stored_backup_name($backup_id);
				$config = WPTC_Factory::get('config');
				$local_timezone_time = $config->cnvt_UTC_to_usrTime($backup_id);
				$backup_time= date('g:i a', $local_timezone_time);
				$temp_stored_backup[$key]['backup_details'][$i]['backup_id'] = $backup_id;
				$temp_stored_backup[$key]['backup_details'][$i]['backup_name'] = $backup_name[0]->backup_name;
				$temp_stored_backup[$key]['backup_details'][$i]['backup_time'] = $backup_time;
			}
			$local_daily_time = $config->cnvt_UTC_to_usrTime($backup_id);
			$temp_stored_backup[$key]['time'] = date('jS F', $local_daily_time);
			$temp_stored_backup['total_backup_points'] = $total_recent_point;
		}
		return $temp_stored_backup;
	}

	public function get_bridge_html($data){
		$config = WPTC_Factory::get('config');
		$html = '<div class="show_restores"><div class="pu_title">Last '.$data['total_backup_points'].' restore points</div> <ul class="bu_list_ul">';
		if (empty($data) || !is_array($data)) {
			return "<div class='pu_title'>No restore points found</div>";
		}
		foreach ($data as $key => $backups) {
			if (empty($backups) || !is_array($backups)) {
				continue;
			}
			$html .= "<li><span>".$backups['time']."</span><span>".count($backups['backup_details'])." restore points</span><a class='btn_wptc show_restore_points'>SHOW RESTORE POINT</a>";
			foreach ($backups['backup_details'] as $key => $backup) {
				$html .= "<div style='display:none' class='rp'><span>".$backup['backup_time']."</span><a class='btn_wptc bridge_restore_now' backup_id = '".$backup['backup_id']."'>RESTORE SITE TO THIS POINT</a></div>";
			}
			$html .= "</li>";
		}
		$html .= '</ul></div><div style="display:none" class="restore_process"><div id="TB_ajaxContent"><div class="pu_title">Restoring your website</div><div class="wcard progress_reverse" style="height:60px; padding:0;"><div class="progress_bar" style="width: 0%;"></div>  <div class="progress_cont">Preparing files to restore...</div></div><div style="padding: 10px; text-align: center;">Note: Please do not close this tab until restore completes.</div></div></div>';
		return $html;
	}

	public function get_overall_tables(){
		$tables = $this->get_all_tables();
		$count = 0;
		foreach ($tables as $table) {
			if (!WPTC_Base_Factory::get('Wptc_ExcludeOption')->is_excluded_table($table)) {
				$count ++;
			}
		}
		return $count;
	}

	public function is_need_to_show_wp_content($backup_id){
		return $this->db->get_var("SELECT count(*) FROM {$this->db->base_prefix}wptc_processed_files WHERE is_dir = '0' AND backupID = ".$backup_id." AND file LIKE '%".basename(WPTC_WP_CONTENT_DIR)."%'");
	}

	public function is_meta_found_on_backup($backup_id){
		$db_meta_data_count = $this->db->get_var("SELECT count(*) FROM {$this->db->base_prefix}wptc_processed_files WHERE is_dir = '0' AND backupID = ".$backup_id." AND file LIKE '%db_meta_data%'");
		if ($db_meta_data_count) {
			return true;
		}
		return false;
	}


	public function get_all_tables(){
		$staging_db_prefix = apply_filters('get_internal_staging_db_prefix', '');
		if ($staging_db_prefix) {
			$sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME NOT LIKE '%wptc_%' AND TABLE_NAME NOT LIKE '%".$staging_db_prefix."%' AND TABLE_SCHEMA = '".DB_NAME."'";
		} else {
			$sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME NOT LIKE '%wptc_%' AND TABLE_SCHEMA = '".DB_NAME."'";
		}
		$result_obj = $this->db->get_results($sql, ARRAY_N);
		foreach ($result_obj as $table) {
			$tables[] = $table[0];
		}
		return $tables;
	}

	public function get_all_included_tables(){
		$all_tables = $this->get_all_tables();
		$tables = array();
		foreach ($all_tables as $key => $table) {
			if (!WPTC_Base_Factory::get('Wptc_ExcludeOption')->is_excluded_table($table)) {
				$tables[] = $table;
			}
		}
		return $tables;
	}

	public function drop_tables_with_prefix($prefix){
		if(empty($prefix)){
			dark_debug($prefix, '--------------prefix empty----------------');
			return false;
		}
		if ($prefix == $this->db->base_prefix ) {
			dark_debug(array(), '---------staging prefix is same as live prefix so cannot be deleted------------');
			return false;
		}
		$sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE '%".$prefix."%' AND TABLE_SCHEMA = '".DB_NAME."'";
		$result_obj = $this->db->get_results($sql, ARRAY_N);
		foreach ($result_obj as $table) {
			$tables[] = $table[0];
		}
		dark_debug($tables, '---------$tables------------');
		if (empty($table)) {
			return true;
		}
		$str = implode (", ", $tables);
		dark_debug($str, '---------$str------------');
		$sql = "DROP TABLES ".$str;
		dark_debug($sql, '---------$sql------------');
		$result_obj = $this->db->query($sql);
		dark_debug($result_obj, '---------$result_obj------------');
		return $result_obj;
	}

	public function get_only_wp_tables(){
		$sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME like '%".$this->db->base_prefix."%' AND TABLE_NAME NOT LIKE '%wptc_%' AND TABLE_SCHEMA = '".DB_NAME."'";
		// $sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME like '%".$this->db->base_prefix."%' AND TABLE_SCHEMA = '".DB_NAME."'";
		$result_obj = $this->db->get_results($sql, ARRAY_N);
		foreach ($result_obj as $table) {
			if (strpos($table[0], $this->db->base_prefix) !== FALSE) {
				$tables[] = $table[0];
			}
		}
		return $tables;
	}

	public function get_table_size($table_name, $return = 1){
		$sql = "SHOW TABLE STATUS LIKE '".$table_name."'";
		$result = $this->db->get_results($sql);
		if (isset($result[0]->Data_length) && isset($result[0]->Index_length) && $return) {
			return $this->convert_bytes_to_hr_format(($result[0]->Data_length) + ($result[0]->Index_length));
		} else {
			return $result[0]->Data_length + $result[0]->Index_length;
		}
		return '0 B';
	}

	public function convert_bytes_to_hr_format($size){
		if (1024 > $size) {
			return $size.' B';
		} else if (1048576 > $size) {
			return round( ($size / 1024) , 2). ' KB';
		} else if (1073741824 > $size) {
			return round( (($size / 1024) / 1024) , 2). ' MB';
		} else if (1099511627776 > $size) {
			return round( ((($size / 1024) / 1024) / 1024) , 2). ' GB';
		}
	}

	public function is_table_included($table){
		$check_exist_sql = $this->db->prepare("
					SELECT count(*)
					FROM {$this->db->base_prefix}wptc_included_tables
					WHERE `table_name` = %s ", $table);
		$count = $this->db->get_var($check_exist_sql);
		if ($count) {
			return true;
		}
		return false;
	}

	public function get_processed_tables(){
		$processed_tables = $this->db->get_var("SELECT count(*) FROM {$this->db->base_prefix}wptc_processed_dbtables WHERE count = '-1' AND name != 'header'");
		$processed_tables = empty($processed_tables) ? 0 : $processed_tables;
		return $processed_tables;
	}

	public function get_overall_files(){
		return $this->db->get_var("SELECT count(*) FROM {$this->db->base_prefix}wptc_current_process ");
	}

	public function get_processed_files(){
		$current_process_file_id = $this->config->get_option('current_process_file_id');
		if (empty($current_process_file_id)) {
			$current_process_file_id = $this->config->get_option('current_process_file_id');
			return 0;
		}
		return $current_process_file_id;
		return $this->db->get_var("SELECT count(*) FROM {$this->db->base_prefix}wptc_current_process WHERE status='P' OR status='E' OR status='S'");
	}

	public function get_current_backup_progress(&$return_array){
		$config = WPTC_Factory::get('config');
		if ($config->get_option('in_progress', true)) {
			$current_backup_ID = getTcCookie('backupID');

			$current_process_file_id = $config->get_option('current_process_file_id');
			$processed_files_total = empty( $current_process_file_id ) ? 0 : $current_process_file_id;
			$processed_files_current = $this->get_processed_files_count($current_backup_ID);

			//Process database backup status
			$overall_tables = $this->get_overall_tables();
			$processed_tables = $this->get_processed_tables();
			$overall_files = $this->get_overall_files();

			$return_array['backup_progress']['db']['overall'] = (int) $overall_tables;
			$return_array['backup_progress']['db']['processed'] = (int) $processed_tables;
			$return_array['backup_progress']['db']['progress'] = $processed_tables.'/'.$overall_tables;

			if (empty($processed_files_total)) {
				$return_array['backup_progress']['files']['processing']['running'] = true;
				$return_array['backup_progress']['files']['processed']['running'] = false;
			} else {
				$return_array['backup_progress']['files']['processed']['running'] = true;
				$return_array['backup_progress']['files']['processing']['running'] = false;
			}

			$return_array['backup_progress']['files']['processed']['total'] = (int) $processed_files_total;
			$return_array['backup_progress']['files']['processed']['current'] = (int) $processed_files_current;

			$total_file_count = (int) $config->get_option('total_file_count');
			$file_list_point = (int) $config->get_option('file_list_point');

			$return_array['backup_progress']['files']['processing']['total'] = (int) $total_file_count;
			$return_array['backup_progress']['files']['processing']['current'] = (int) $file_list_point;
			$return_array['backup_progress']['files']['processing']['overall'] = (int) $overall_files;
			if (!empty($total_file_count) && !empty($file_list_point)) {
				$return_array['backup_progress']['files']['processing']['progress'] = $file_list_point.'/'.$total_file_count;
			} else {
				$return_array['backup_progress']['files']['processing']['progress'] = $overall_files;
			}

			$progress_percent = 0;

			if (!empty($processed_files_current) && !empty($overall_files)) {
				$progress_percent = ($processed_files_total / $overall_files) * 100;
				if ($progress_percent > 99) {
					$progress_percent = 0;
				}
			}

			$return_array['backup_progress']['progress_percent'] = round($progress_percent, 2);

			if (($overall_tables > $processed_tables || ($overall_tables == 0 && $processed_tables == 0) ) && $progress_percent === 0) {
				$return_array['backup_progress']['db']['running'] = true;
			} else if($overall_tables == $processed_tables) {
				$return_array['backup_progress']['db']['running'] = false;
			}

		} else {
			$return_array['progress_complete'] = true;
		}
	}

	public function get_processed_files_count($backup_id = null) {
		if (empty($backup_id)) {
			$backup_id = getTcCookie('backupID');
		}

		$count = $this->db->get_var($this->db->prepare("
				SELECT COUNT(*)
				FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()}
				WHERE {$this->getBackupID()} = %s AND is_dir = %s ", $backup_id, 0));

		return $count;
	}

	public function is_frequently_changed($backup_id = null, $file) {
		if (empty($backup_id)) {
			$backup_id = getTcCookie('backupID');
		}
		if (empty($file)) {
			return false;
		}
		$last_backup_id = $this->get_last_n_th_backup_id(3);
		$sql = $this->db->prepare(" SELECT COUNT(*)	FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()} WHERE {$this->getBackupID()} >= %s AND {$this->getBackupID()} <= %s AND file = %s",$last_backup_id, $backup_id, $file);
		$count = $this->db->get_results($sql, ARRAY_N);
		dark_debug($count, '---------is_frequently_changed count------------');
		if ($count[0][0] >= 3) {
			return true;
		}
		return false;
	}

	public function get_mtime_size_past_n_current($file){
		$last_backup_id = $this->get_last_n_th_backup_id(2);
		$sql = $this->db->prepare(" SELECT mtime_during_upload, uploaded_file_size FROM {$this->db->base_prefix}wptc_processed_{$this->getTableName()} WHERE {$this->getBackupID()} = %s AND file = %s",$last_backup_id, $file);
		$result = $this->db->get_results($sql);
		dark_debug($result[0], '---------get_mtime_size_past_n_current------------');
		dark_debug($result[0]->mtime_during_upload, '---------$result[0]->mtime_during_upload------------');
		dark_debug($result[0]->uploaded_file_size, '---------$result[0]->uploaded_file_size------------');
		return array(
			'file_name' => wp_normalize_path($file),
			'size' => array(
						'past' => (int) $result[0]->uploaded_file_size,
						'current' => filesize($file)
						),
			'mtime' => array(
						'past' => (int) $result[0]->mtime_during_upload,
						'current' => filemtime($file)
						),
		);
	}

	public function get_last_n_th_backup_id($n = 3){
		global $last_n_th_backup_id;
		if (isset($last_n_th_backup_id) && !empty($last_n_th_backup_id)) {
			return $last_n_th_backup_id;
		}
		$sql = $this->db->prepare("SELECT backup_id FROM {$this->db->base_prefix}wptc_backup_names ORDER BY this_id DESC LIMIT %d, %d", ($n-1), 1);
		$backup_id = $this->db->get_var($sql);
		$last_n_th_backup_id = $backup_id;
		// dark_debug($last_n_th_backup_id, '---------$last_n_th_backup_id------------');
		return $last_n_th_backup_id;
	}

	public function backup_type_check($backup_id) {
		global $wpdb;
		$type = $wpdb->get_row('SELECT backup_type from ' . $wpdb->base_prefix . 'wptc_backups WHERE backup_id =' . $backup_id);
		if ($type != "") {
			return $type->backup_type;
		} else {
			return "";
		}

	}

	public function save_manual_backup_name_wptc($backup_name) {
		global $wpdb;
		$backup_id = getTcCookie('backupID');
		$query = $wpdb->prepare("UPDATE {$wpdb->base_prefix}wptc_backup_names SET backup_name = %s WHERE `backup_id` = %s", $backup_name, $backup_id);
		dark_debug($query, '---------$query------------');
		$query_result = $wpdb->query($query);
		// if ($query_result) {
			die(json_encode(array('status'=>'success')));
		// } else {
			// die(json_encode(array('status'=>'failed')));
		// }
	}

	public function get_no_of_backups() {
		global $wpdb;
		$count = $wpdb->get_var('SELECT count(*) from ' . $wpdb->base_prefix . 'wptc_backup_names');
		if (empty($count)) {
			return 0;
		}
		return $count;
	}

	public function get_backups_meta() {
		global $wpdb;
		$final_data = array();
		$backups_meta = $wpdb->get_results('SELECT backup_id, backup_type, files_count, update_details from ' . $wpdb->base_prefix . 'wptc_backups');
		$backup_names = $wpdb->get_results('SELECT backup_id, backup_name from ' . $wpdb->base_prefix . 'wptc_backup_names');
		// dark_debug($backups_meta, '--------$backups_meta--------');
		// dark_debug($backup_names, '--------$backup_names--------');
		$i = 0;
		foreach ($backups_meta as $meta) {
			$final_data[$i]['id'] = $meta->backup_id;
			$final_data[$i]['type'] = $meta->backup_type;
			$final_data[$i]['files_count'] = $meta->files_count;
			if (!empty($backup_names[$i]) &&  !empty($backup_names[$i]->backup_name)) {
				$final_data[$i]['name'] = $backup_names[$i]->backup_name;
			} else {
				$final_data[$i]['name'] = '';
			}

			if (empty($meta)) {
				$final_data[$i]['update_details'] = NULL;
			} else {
				$final_data[$i]['update_details'] = unserialize($meta->update_details);
			}
			$i++;
		}

		// dark_debug($final_data, '--------$final_data--------');

		return $final_data;
	}

	public function save_PTC_update_response($formatted_response){
		global $wpdb;
		// dark_debug($formatted_response, '--------$formatted_response--------');
		$backup_id = getTcCookie('backupID');
		$query = $wpdb->prepare("UPDATE {$wpdb->base_prefix}wptc_backups SET update_details = %s WHERE `backup_id` = ".$backup_id."", serialize($formatted_response));
		$query_result = $wpdb->query($query);
		// dark_debug($query_result, '--------$query_result--------');
	}

}
