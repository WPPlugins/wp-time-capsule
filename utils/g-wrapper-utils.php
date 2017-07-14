<?php

class Utils_Base {
	public function getTempFolderFromOutFile($outFile, $mode = '') {
		$options_obj = WPTC_Factory::get('config');
		$is_staging_running = $options_obj->get_option('is_staging_running');
		if($is_staging_running){
			$site_abspath = $options_obj->get_option('site_abspath');
			$this_absbath_length = (strlen($site_abspath) - 1);
		} else{
			$this_absbath_length = (strlen(ABSPATH) - 1);
		}
		$this_temp_file = $options_obj->get_option('backup_db_path');
		$this_temp_file = $options_obj->wp_filesystem_safe_abspath_replace($this_temp_file);
		$this_temp_file = removeTrailingSlash($this_temp_file) . '/tCapsule' . substr($outFile, $this_absbath_length);
		$base_file_name = basename($this_temp_file);
		$base_file_name_pos = strrpos($this_temp_file, $base_file_name);
		$base_file_name_pos = $base_file_name_pos - 1;
		$this_temp_folder = substr($this_temp_file, 0, $base_file_name_pos);

		if(!$is_staging_running){
			$this_temp_folder = $options_obj->wp_filesystem_safe_abspath_replace($this_temp_folder);
		}

		$this->createRecursiveFileSystemFolder($this_temp_folder);
		return $this_temp_file;
	}

	public function createRecursiveFileSystemFolder($this_temp_folder, $this_absbath_length = null) {
		global $wp_filesystem;

		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-033');
				return false;
			}
		}

		$folders = explode('/', $this_temp_folder);
		// dark_debug($folders, '---------$folders------------');
		foreach ($folders as $key => $folder) {
			$current_folder = '';
			for($i=0; $i<=$key; $i++){
				if (empty($folders[$i])) {
					continue;
				}
				if (is_windows_machine_wptc() && empty($current_folder)) {
					$current_folder .= $folders[$i];
				} else {
					$current_folder .= '/'.$folders[$i];
				}
			}
			if (empty($current_folder)) {
				continue;
			}
			if ($wp_filesystem && !$wp_filesystem->is_dir($current_folder)) {
				if (!$wp_filesystem->mkdir($current_folder, 0755)) {
					// dark_debug($current_folder, '---------------could not create retry-----------------');
					// dark_debug(error_get_last(), '--------error_get_last()--------');
					$wp_filesystem->chmod(dirname($current_folder), 0755);
					if(!$wp_filesystem->mkdir($current_folder, 0755)){
						// dark_debug(error_get_last(), '--------error_get_last()--------');
						// dark_debug(array(), '---------------retry also failed-----------------');
					}
				}
			} else {
				// dark_debug($current_folder, '---------found------------');

				if(strpos($current_folder, 'tCapsule') !== false && $wp_filesystem->chmod($current_folder, 0755)){
					// dark_debug(array(), '--------Set 0755 for this folder--------');
				} else {
					// dark_debug(error_get_last(), '--------error_get_last()--------');
					// dark_debug(array(), '--------failed to set 0755 for this folder--------');
				}
			}
		}
	}

	public function prepareOpenSetOutFile($outFile, $mode, &$handle) {
		global $wp_filesystem;
		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-017');
				return false;
			}
		}
		$tempFolderFile = $this->getTempFolderFromOutFile(wp_normalize_path($outFile));
		$chRes = $wp_filesystem->chmod($tempFolderFile, false, true);

		$handle = fopen($tempFolderFile, $mode);
		return $tempFolderFile;
	}

	public function get_formatted_range($isChunkDownload = array()) {
		if (!empty($isChunkDownload)) {
			$this_range = 'bytes=' . $isChunkDownload['c_offset'] . '-' . $isChunkDownload['c_limit'] . '';
		} else {
			$this_range = 'bytes=0-4024000';
		}
		dark_debug($this_range, "--------formatted_range--------");
		return $this_range;
	}
}

class Gdrive_Utils extends Utils_Base {
	public function get_dir_id_from_list_result(&$files) {
		$list_result = array();
		if (!method_exists($files, 'getItems')) {
			return false;
		}
		$list_result = array_merge($list_result, $files->getItems());
		if (empty($list_result)) {
			return array();
		}
		$list_result = (array) $list_result;

		$list_result = (array) $list_result[0];
		$folder_id = $list_result['id'];

		return $folder_id;
	}

	public function formatted_upload_result(&$upload_result, $extra_data = array()) {
		$req_result = new stdclass;
		$req_result->revision = $upload_result->version;
		$req_result->rev = $upload_result->headRevisionId;
		$req_result->bytes = $upload_result->fileSize;
		$req_result->g_file_id = $upload_result->id;
		$req_result->title = $upload_result->title;

		$common_format = array();
		$common_format['body'] = $req_result;

		return $common_format;
	}
}

class S3_Utils extends Utils_Base {
	public function formatted_upload_result($upload_result, $extra_data = array()) {
		$req_result = new stdclass;
		$req_result->revision = $upload_result['VersionId'];
		$req_result->rev = $upload_result['VersionId'];
		$req_result->bytes = $extra_data['filesize'];
		$req_result->g_file_id = '';
		$req_result->title = $extra_data['title'];

		$common_format = array();
		$common_format['body'] = $req_result;

		return $common_format;
	}
}