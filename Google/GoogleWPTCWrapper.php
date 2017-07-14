<?php

class Google_Wptc_Wrapper {
	private $client,
	$service,
	$wptc_folder_id,
	$service_dets,
	$handle,
	$processed_files;

	public function __construct($client) {
		$this->client = $client;
		$this->service = new WPTC_Google_Service_Drive($client);
		$this->utils = new Gdrive_Utils();
		$this->processed_files = WPTC_Factory::get('processed-restoredfiles');
	}

	public function get_service_dets() {
		if (!$this->service_dets) {
			$this->service_dets = $this->service->about->get();
		}
		return $this->service_dets;
	}

	public function quota_bytes_left() {
		$about = $this->get_service_dets();

		$total = $about->getQuotaBytesTotal();
		$used = $about->getQuotaBytesUsed();

		$remaining = $total - $used;

		return $remaining;
	}

	public function setTracker($tracker) {
		$this->tracker = $tracker;
	}

	public function shortcut_to_find_parent_id($parent_dir, $dir_path, $site_main_folder_title){
		if (empty($parent_dir)) {
			$site_main_folder_title = $site_main_folder_title.'/';
		    $parent_dir_like	= str_replace($site_main_folder_title, '', $dir_path);
			$parent_dir = ABSPATH.$parent_dir_like;
		}
		$grand_parent_dir = $this->processed_files->get_parent_details($parent_dir);
		if (empty($grand_parent_dir['is_present'])) {
			return false;
		} else if(empty($grand_parent_dir['g_file_id'])){
			return false;
		} else {
			$prev_parent_id = $grand_parent_dir['g_file_id'];
		}
		$folder = basename($parent_dir);
		$parameters = array();
		$parameters['q'] = "title = '$folder' and trashed = false and '$prev_parent_id' in parents and 'me' in owners and mimeType = 'application/vnd.google-apps.folder'";

		try {
			$files = $this->service->files->listFiles($parameters);
		} catch (Exception $e) {
			dark_debug($e->getMessage(), "--------exception list files--------");
			$err_reason = $e->getMessage();
			$err_code = $e->getCode();
			if (stripos($err_reason, 'Exceeded') !== false || $this->is_too_many_request_error($err_code)) {
				throw $e;
				// return array('too_many_requests' => $e->getMessage());
			} else if($err_code == 400) {
				// return array('error'=> 'Failed to parse Content-Range header- (400) ');
				throw $e;
			}
		}

		if (!method_exists($files, 'getItems')) {
		dark_debug('getItems', '---------getItems get_exact_parent_id_of_cur_file 2-------------');
			return false;
		}

		$prev_parent_result = $this->utils->get_dir_id_from_list_result($files);
		if (empty($prev_parent_result)) {
			$prev_parent_id = $this->create_new_sub_folder($folder, $prev_parent_id);
		} else {
			$prev_parent_id = $prev_parent_result;
		}

		if (!empty($parent_dir)) {
			$this->processed_files->insert_g_file_id($parent_dir, $prev_parent_id);
		}
		dark_debug($prev_parent_id,'--------------$prev_parent_id returned-------------');
		return $prev_parent_id;
	}

	public function get_exact_parent_id_of_cur_file($dir_path, $parent_dir = false) {
		dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);
		if (empty($dir_path)) {
			return false;
		}
		$site_main_folder_title = WPTC_Factory::get('config')->get_option('dropbox_location');
		$prev_parent_id_temp = $this->shortcut_to_find_parent_id($parent_dir, $dir_path, $site_main_folder_title);
		if (stripos($dir_path, 'meta_data') === false) {
			dark_debug($prev_parent_id_temp,'--------------shortcut_to_find_parent_id result-------------');
			if (!empty($prev_parent_id_temp)) {
				return $prev_parent_id_temp;
			}
		}
		$path_arr = explode('/', $dir_path);
		if (empty($path_arr)) {
			return false;
		}

		$prev_parent_id = $this->get_wptc_folder_id_or_create_it();
		if (empty($prev_parent_id)) {
			return false;
		} else if(isset($prev_parent_id['too_many_requests']) || isset($prev_parent_id['error']) ){
			return $prev_parent_id;
		}


		if ($path_arr[0] == $site_main_folder_title) {
			$new_parent_id = $this->get_this_site_main_folder_id_or_create_it($prev_parent_id);
			if(isset($new_parent_id['too_many_requests']) || isset($new_parent_id['error']) ){
				return $new_parent_id;
			}
			if (!empty($new_parent_id)) {
				$prev_parent_id = $new_parent_id;
				$path_arr = array_slice($path_arr, 1);
			}
		}
		$count = 0;
		foreach ($path_arr as $k => $v) {
			$count++;
			$parameters = array();
			$parameters['q'] = "title = '$v' and trashed = false and '$prev_parent_id' in parents and 'me' in owners and mimeType = 'application/vnd.google-apps.folder'";

			try {
				$files = $this->service->files->listFiles($parameters);
			} catch (Exception $e) {
				dark_debug($e->getMessage(), "--------exception list files--------");
				$err_reason = $e->getMessage();
				$err_code = $e->getCode();
				if (stripos($err_reason, 'Exceeded') !== false || $this->is_too_many_request_error($err_code)) {
					throw $e;
					// return array('too_many_requests' => $e->getMessage());
				} else if($err_code == 400) {
					throw $e;
					// return array('error'=> 'Failed to parse Content-Range header- (400) ');
				}
			}

			if (!method_exists($files, 'getItems')) {
				return false;
			}

			$prev_parent_result = $this->utils->get_dir_id_from_list_result($files);
			if (empty($prev_parent_result)) {
				$prev_parent_id = $this->create_new_sub_folder($v, $prev_parent_id);
			} else {
				$prev_parent_id = $prev_parent_result;
			}
			if (stripos($v, 'meta-data') === false) {
				$this->store_sub_folder_values($dir_path, $prev_parent_id, $count, $site_main_folder_title);
			}
		}
		if (!empty($parent_dir)) {
			$this->processed_files->update_g_file_id($parent_dir, $prev_parent_id);
		}
		return $prev_parent_id;
	}
	public function store_sub_folder_values($dir_path, $prev_parent_id, $count, $site_main_folder_title){
		$site_main_folder_title = $site_main_folder_title.'/'.
		$final_path = str_replace($site_main_folder_title, '', $dir_path);
		$path_arr = explode('/', $final_path);
		$path_arr = array_values(array_filter($path_arr));
		$base_name = '';
		for ($i=0; $i <$count ; $i++) {
			if (empty($base_name)) {
				$base_name .= $path_arr[$i];
			} else {
				$base_name .= '/'.$path_arr[$i];
			}
		}

		$needed_file = ABSPATH.$base_name;

		$this->processed_files->insert_g_file_id($needed_file, $prev_parent_id);
	}
	function is_too_many_request_error($err_code){
		dark_debug($err_code, '---------err_code in is_too_many_request_error-------------');
		$status_codes = array(500, 503, 429);
		if (in_array($err_code, $status_codes)) {
			return true;
		}
		return false;
	}
	private function get_this_site_main_folder_id_or_create_it($wptc_g_drive_folder_id) {
		if (WPTC_Factory::get('config')->get_option('cached_g_drive_this_site_main_folder_id')) {
			return WPTC_Factory::get('config')->get_option('cached_g_drive_this_site_main_folder_id');
		}

		$site_main_folder_title = WPTC_Factory::get('config')->get_option('dropbox_location');
		$prev_parent_id = $wptc_g_drive_folder_id;
		$parameters = array();
		$parameters['q'] = "title = '$site_main_folder_title' and trashed = false and '$prev_parent_id' in parents and 'me' in owners and mimeType = 'application/vnd.google-apps.folder'";

		try{
			$files = $this->service->files->listFiles($parameters);
		} catch (Exception $e) {
			dark_debug($e->getMessage(), "--------exception list files--------");
			$err_reason = $e->getMessage();
			$err_code = $e->getCode();
			if (stripos($err_reason, 'Exceeded') !== false || $this->is_too_many_request_error($err_code)) {
				throw $e;
				//return array('too_many_requests' => $e->getMessage());
			} else if($err_code == 400) {
				throw $e;
				//return array('error'=> 'Failed to parse Content-Range header- (400) ');
			}
		}
		if (!method_exists($files, 'getItems')) {
			return false;
		}

		$prev_parent_result = $this->utils->get_dir_id_from_list_result($files);

		if (empty($prev_parent_result)) {
			$prev_parent_id = $this->create_new_sub_folder($site_main_folder_title, $prev_parent_id);
		} else {
			$prev_parent_id = $prev_parent_result;
		}
		WPTC_Factory::get('config')->set_option('cached_g_drive_this_site_main_folder_id', $prev_parent_id);
		return $prev_parent_id;
	}

	private function get_wptc_folder_id_or_create_it() {
		if (WPTC_Factory::get('config')->get_option('cached_wptc_g_drive_folder_id')) {
			return WPTC_Factory::get('config')->get_option('cached_wptc_g_drive_folder_id');
		}

		$prev_parent_id = 'root';
		$parameters = array();
		$parameters['q'] = "title = 'WP Time Capsule' and trashed = false and '$prev_parent_id' in parents and 'me' in owners and mimeType = 'application/vnd.google-apps.folder'";
		try{
			$files = $this->service->files->listFiles($parameters);
		} catch (Exception $e) {
			dark_debug($e->getMessage(), "--------exception list files--------");
			$err_reason = $e->getMessage();
			$err_code = $e->getCode();
			if (stripos($err_reason, 'Exceeded') !== false || $this->is_too_many_request_error($err_code)) {
				throw $e;
				//return array('too_many_requests' => $e->getMessage());
			} else if($err_code == 400) {
				throw $e;
				//return array('error'=> 'Failed to parse Content-Range header- (400) ');
			}
		}
		if (!method_exists($files, 'getItems')) {
			return false;
		}
		$prev_parent_result = $this->utils->get_dir_id_from_list_result($files);

		if (empty($prev_parent_result)) {
			$prev_parent_id = $this->create_new_sub_folder('WP Time Capsule', $prev_parent_id);
		} else {
			$prev_parent_id = $prev_parent_result;
		}
		WPTC_Factory::get('config')->set_option('cached_wptc_g_drive_folder_id', $prev_parent_id);
		return $prev_parent_id;
	}

	public function create_new_sub_folder($dir_name, $parent_id) {
		$file = new WPTC_Google_Service_Drive_DriveFile();
		$file->setTitle($dir_name);
		$file->setMimeType('application/vnd.google-apps.folder');

		$parent = new WPTC_Google_Service_Drive_ParentReference();
		$parent->setId($parent_id);
		$file->setParents(array($parent));
		try{
			$createdFolder = $this->service->files->insert($file, array(
				'mimeType' => 'application/vnd.google-apps.folder',
			));
		} catch (Exception $e) {
			dark_debug($e->getMessage(), "--------exception list files--------");
			$err_reason = $e->getMessage();
			$err_code = $e->getCode();
			if (stripos($err_reason, 'Exceeded') !== false || $this->is_too_many_request_error($err_code)) {
				throw $e;
				//return array('too_many_requests' => $e->getMessage());
			} else if($err_code == 400) {
				throw $e;
				//return array('error'=> 'Failed to parse Content-Range header- (400) ');
			}
		}
		if ($createdFolder) {
			$createdFolder = (array) $createdFolder;
			$sub_folder_id = $createdFolder['id'];
		}
		return $sub_folder_id;
	}

	public function putFile($file, $filename = false, $path = '', $overwrite = true, $offset = 0, $uploadID = null) {
		try {
			$cur_parent_details = $this->processed_files->get_parent_details($file);
		if (stripos($file, 'db_meta_data') !== false) {
			$cur_parent_id = $this->get_exact_parent_id_of_cur_file($path, false);
		} else if (empty($cur_parent_details['is_present'])) {
			$cur_parent_id = $this->get_exact_parent_id_of_cur_file($path, false);
		} else if(empty($cur_parent_details['g_file_id'])){
			$cur_parent_id = $this->get_exact_parent_id_of_cur_file($path, $cur_parent_details['parent_dir']);
		} else {
			$cur_parent_id = $cur_parent_details['g_file_id'];
		}

		if (empty($cur_parent_id)) {
			$error_mesg = "Unable to get parent folder ID for " . $file;
			sleep(3);
			return array('too_many_requests' => 'limit reached');
			//return array('error' => $error_mesg);
		} else if(isset($cur_parent_id['too_many_requests']) || isset($cur_parent_id['error']) ){
			sleep(3);
			return $cur_parent_id;
		}

		$filename = (is_string($filename)) ? $filename : basename($file);

		$file_id = $this->isFileAlreadyExists($filename, $cur_parent_id);
		if (empty($file_id)) {
			$file_obj = new WPTC_Google_Service_Drive_DriveFile();
		} else {
			$file_obj = $this->service->files->get($file_id);
		}

		$file_obj->setTitle(basename($filename));

		$parent = new WPTC_Google_Service_Drive_ParentReference();
		$parent->setId($cur_parent_id);
		$file_obj->setParents(array($parent));

		$this->client->setDefer(true);

		if (empty($file_id)) {
			$request = $this->service->files->insert($file_obj);
		} else {
			$request = $this->service->files->update($file_id, $file_obj, array('newRevision' => true, 'uploadType' => 'multipart'));
		}
		$upload_file_block_size = 1 * 1024 * 1024;

		$media = new WPTC_Google_Http_MediaFileUpload($this->client, $request, '', null, true, $upload_file_block_size);
		if (is_zero_bytes_file($file)) {
			dark_debug(array(), '---------------Zero bytesss-----------------');
			$is_zero_bytes_file = true;
			$media->setFileSize(1);
		} else {
			// dark_debug(array(), '---------------Not zero bytes-----------------');
			$media->setFileSize(filesize($file));
			$is_zero_bytes_file = false;
		}

		$handle = fopen($file, "rb");
		$fileSizeUploaded = 0;
		fseek($handle, $fileSizeUploaded);
		$complete_backup_result = array();
		while (!feof($handle)) {
			$chunk = fread($handle, $upload_file_block_size);
			if ($is_zero_bytes_file) {
				dark_debug(array(), '---------------Chunk updated-----------------');
				$chunk = ' ';
			}
			$complete_backup_result = $media->nextChunk($chunk);

			if ($this->tracker) {
				//$this->tracker->track_upload($file, $uploadID, $offset);
			}
		}

		fclose($handle);
		$this->client->setDefer(false);

		return $this->utils->formatted_upload_result($complete_backup_result);
	}catch (Exception $e) {
		$err_reason = $e->getMessage();
		dark_debug($err_reason, '---------err_reason-------------');
		$err_code = $e->getCode();
		if (stripos($err_reason, 'Exceeded') !== false || $this->is_too_many_request_error($err_code)) {
			return array('too_many_requests' => $e->getMessage());
		} else if($err_code == 400) {
			return array('error'=> 'Failed to parse Content-Range header- (400) ');
		}
		throw $e;
		}
	}

	public function isFileAlreadyExists($filename, $cur_parent_id) {
		$filename = basename($filename);
		$parameters = array();
		$parameters['q'] = "title = '$filename' and trashed = false and '$cur_parent_id' in parents and 'me' in owners";
		try{
			$files = $this->service->files->listFiles($parameters);
			// dark_debug($files,'--------------$files-------------');
		} catch (Exception $e) {
			dark_debug($e->getMessage(), "--------isFileAlreadyExists --------");
			$err_reason = $e->getMessage();
			$err_code = $e->getCode();
			if (stripos($err_reason, 'Exceeded') !== false || $this->is_too_many_request_error($err_code)) {
				dark_debug(array(), '-----------too many request 2-------------');
				throw $e;
				//return array('too_many_requests' => $e->getMessage());
			} else if($err_code == 400) {
				dark_debug(array(), '-----------400 error 1-------------');
				throw $e;
				// return array('error'=> 'Failed to parse Content-Range header- (400) ');
			}
		}
		if (!method_exists($files, 'getItems')) {
			return false;
		}
		$file_id = $this->utils->get_dir_id_from_list_result($files);
		return $file_id;
	}

	public function chunkedUpload($file, $filename = false, $path = '', $overwrite = true, $offset = 0, $uploadID = null, $starting_backup_path_time = false) {
		//return $this->putFile($file, $filename, $path, $overwrite, $offset, $uploadID);
		try {
			$cur_parent_details = $this->processed_files->get_parent_details($file);
			if (stripos($file, 'db_meta_data') !== false) {
				$cur_parent_id = $this->get_exact_parent_id_of_cur_file($path, false);
			} else if (empty($cur_parent_details['is_present'])) {
				$cur_parent_id = $this->get_exact_parent_id_of_cur_file($path, false);
			} else if(empty($cur_parent_details['g_file_id'])){
				$cur_parent_id = $this->get_exact_parent_id_of_cur_file($path, $cur_parent_details['parent_dir']);
			} else {
				$cur_parent_id = $cur_parent_details['g_file_id'];
			}
			if (empty($cur_parent_id)) {
				return array('error' => 'Unable to get parent folder ID for ' . $file);
			} else if(isset($cur_parent_id['too_many_requests']) || isset($cur_parent_id['error']) ){
				return $cur_parent_id;
			}

			$filename = (is_string($filename)) ? $filename : basename($file);
			$file_id = $this->isFileAlreadyExists($filename, $cur_parent_id);
			if (empty($file_id)) {
				$file_obj = new WPTC_Google_Service_Drive_DriveFile();
			} else {
				$file_obj = $this->service->files->get($file_id);
			}

			$file_obj->setTitle(basename($filename));
			$parent = new WPTC_Google_Service_Drive_ParentReference();
			$parent->setId($cur_parent_id);
			$file_obj->setParents(array($parent));

			$this->client->setDefer(true);

			if (empty($file_id)) {
				$request = $this->service->files->insert($file_obj);
			} else {
				$request = $this->service->files->update($file_id, $file_obj, array('newRevision' => true, 'uploadType' => 'multipart'));
			}
			$upload_file_block_size = 5 * 1024 * 1024;

			$media = new WPTC_Google_Http_MediaFileUpload($this->client, $request, '', null, true, $upload_file_block_size);
			$media->setFileSize(filesize($file));
			$handle = fopen($file, "rb");
			$complete_backup_result = array();

			$to_exit = false;

			while (empty($complete_backup_result)) {
				if ($uploadID) {
					$media->resume($uploadID);
				}

				fseek($handle, $offset);
				dark_debug($offset, '---------$offset------------');
				dark_debug($upload_file_block_size, '---------$upload_file_block_size------------');
				dark_debug($uploadID, '---------$uploadID------------');
				$chunk = fread($handle, $upload_file_block_size);
				$complete_backup_result = $media->nextChunk($chunk);
				$uploadID = $media->getResumeUri();
				$offset = ftell($handle);
				if ($this->tracker) {
					$this->tracker->track_upload($file, $uploadID, $offset);
				}

				if ((microtime(true) - $starting_backup_path_time) >= WPTC_TIMEOUT) {
					$to_exit = true;
					break;
				}
			}

			fclose($handle);
			$this->client->setDefer(false);

			if ($to_exit) {
				dark_debug(array(), "--------exitng by backup path time--------");
				backup_proper_exit_wptc();
			}

			dark_debug(array(), "--------must have uploaded--------");
			if (strrpos($file, 'wordpress-db_meta_data.sql') !== false) {
				$config = WPTC_Factory::get('config');
				$config->set_option('meta_data_upload_offset', -1);
				$config->set_option('meta_data_upload_id', '');
			}
			return $this->utils->formatted_upload_result($complete_backup_result);

		} catch (Exception $e) {
			$err_reason = $e->getMessage();
			$err_code = $e->getCode();
			dark_debug($err_reason,'--------------$err_reason catch 1-------------');
			if (stripos($err_reason, 'Exceeded') !== false || $this->is_too_many_request_error($err_code)) {
				dark_debug(array(), '-----------too many request 3-------------');
				return array('too_many_requests' => $e->getMessage());
			} else if($err_code == 400) {
				dark_debug(array(), '-----------400 error 2-------------');
				return array('error'=> 'Failed to parse Content-Range header- (400) ');
			}
			return array(
				'error' => $e->getMessage(),
			);
		}
	}

	public function getFile($file, $outFile = false, $revision = null, $isChunkDownload = array(), $g_file_id = null) {
		$handle = null;
		try {

			if ($outFile !== false) {
				//$tempFolderFile = $this->utils->getTempFolderFromOutFile(stripslashes($outFile));
				$this->utils->prepareOpenSetOutFile($outFile, 'wb', $handle);
			}

			$download_file_dets = array();
			$download_file_dets['outFile'] = $outFile;
			$download_file_dets['g_file_id'] = $g_file_id;
			$download_file_dets['revision_id'] = $revision;

			$process_download_result = $this->process_download($handle, $download_file_dets);

			if ($handle) {
				fclose($handle);
			}
			return $process_download_result;
		} catch (Exception $e) {
			throw $e;
		}
	}

	public function process_download(&$handle, $download_file_dets) {
		dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);
		try {

			$file = $this->service->revisions->get($download_file_dets['g_file_id'], $download_file_dets['revision_id']);

			$downloadUrl = $file->getDownloadUrl();
			if ($downloadUrl) {
				$request = new WPTC_Google_Http_Request($downloadUrl, 'GET', null, null);

				$signHttpRequest = $this->client->getAuth()->sign($request);
				$httpRequest = $this->client->getIo()->makeRequest($signHttpRequest);
				dark_debug($download_file_dets, '---------------$download_file_dets-----------------');
				dark_debug($handle, '---------------$handle-----------------');
				if ($httpRequest->getResponseHttpCode() == 200) {
					if($this->is_zero_bytes_file($download_file_dets['outFile'], $download_file_dets['g_file_id'])){
						fwrite($handle, '');
					} else {
						fwrite($handle, $httpRequest->getResponseBody());
					}
					return true;
				} else {
					dark_debug(array(), '--------google_error_bad_response_code--------');
					return true;
					//file failed but do not stop because of this one file failure.
					// return array("error" => "There is some error.", "error_code" => "google_error_bad_response_code");
				}
			} else {
				return array("error" => "Google Drive file doesnt have nay content.", "error_code" => "google_error_download_url");
			}
		} catch (Exception $e) {
			dark_debug($e->getMessage(), "--------excepion g dirve downlaod--------");
			dark_debug($e->getCode(), "--------excepion g dirve getCode--------");
			$err_reason = $e->getMessage();
			$err_code = $e->getCode();
			if (stripos($err_reason, 'Exceeded') !== false || $this->is_too_many_request_error($err_code)) {
				throw $e;
				// return array('too_many_requests' => $e->getMessage());
			} else if($err_code == 400) {
				throw $e;
				// return array('error'=> 'Failed to parse Content-Range header- (400) ');
			}
			if ($e->getCode() == 400 || $e->getCode() == 404 || $e->getCode() == 0) {
				return array("error" => $e->getMessage(), "error_code" => $e->getCode());
			}
			throw $e;
		}
	}

	private function is_zero_bytes_file($file, $g_file_id){
		$uploaded_file_size = $this->processed_files->get_file_uploaded_file_size($file, $g_file_id);
		dark_debug($uploaded_file_size, '---------------$uploaded_file_size-----------------');
		if ($uploaded_file_size != 1) {
			return false;
		}
		return is_file_in_zero_bytes_list($file);
	}

	public function chunkedDownload($file, $outFile = false, $revision = null, $isChunkDownload = array(), $g_file_id = null, $meta_file_download = null) {
		$handle = null;
		$tempFolder = $this->utils->getTempFolderFromOutFile(wp_normalize_path($outFile));
		if ($outFile !== false) {
			if ($isChunkDownload['c_offset'] == 0) {
				//while restoring ... first
				$tempFolderFile = $this->utils->prepareOpenSetOutFile($outFile, 'wb', $handle);
			} else {
				$tempFolderFile = $this->utils->prepareOpenSetOutFile($outFile, 'rb+', $handle);
			}
		}

		$download_file_dets = array();
		$download_file_dets['outFile'] = $outFile;
		$download_file_dets['g_file_id'] = $g_file_id;
		$download_file_dets['revision_id'] = $revision;
		fseek($handle, $isChunkDownload['c_offset']);
		$result = $this->process_multipart_download($handle, $download_file_dets, $isChunkDownload);

		if ($result && !isset($result['error'])) {
			$offset = ftell($handle);
			if (empty($meta_file_download)) {
				if ($this->tracker) {
					$this->tracker->track_download($outFile, false, $offset, $isChunkDownload);
				}
			} else {
				$this->tracker->track_meta_download($offset, $isChunkDownload);
			}
			if ($handle) {
				fclose($handle);
			}
			return array(
				'name' => ($outFile) ? $outFile : basename($file),
				'chunked' => true,
			);
		} else if (!empty($result) && is_array($result) && isset($result['too_many_requests'])) {
			return $result;
		} else {
			if ($handle) {
				fclose($handle);
			}

			return array('error' => $result['error']);
		}

	}

	public function process_multipart_download(&$handle, $download_file_dets, $isChunkDownload) {
		try {

			$file = $this->service->revisions->get($download_file_dets['g_file_id'], $download_file_dets['revision_id']);

			$downloadUrl = $file->getDownloadUrl();
			dark_debug($downloadUrl, "--------downloadUrl--------");
			if ($downloadUrl) {
				$request = new WPTC_Google_Http_Request($downloadUrl, 'GET', array('Range' => $this->utils->get_formatted_range($isChunkDownload)), null);

				$signHttpRequest = $this->client->getAuth()->sign($request);
				$httpRequest = $this->client->getIo()->makeRequest($signHttpRequest);
				if ($httpRequest->getResponseHttpCode() == 200 || $httpRequest->getResponseHttpCode() == 206) {
					fwrite($handle, $httpRequest->getResponseBody());
					return true;
				} else {
					return array("error" => "There is some error.");
				}
			} else {
				return array("error" => "Google Drive file doesnt have nay content.");
			}
		} catch (Exception $e) {
			dark_debug($e->getMessage(), "--------excepion g dirve downlaod--------");
			dark_debug($e->getCode(), "--------excepion g dirve getCode--------");
			$err_reason = $e->getMessage();
			if (stripos($err_reason, 'Exceeded') !== false ) {
				throw $e;
				// return array('too_many_requests' => $e->getMessage());
			}
			if ($e->getCode() == 400 || $e->getCode() == 404 || $e->getCode() == 0) {
				throw $e;
				// return array("error" => $e->getMessage(), "error_code" => $e->getCode());
			}
			throw $e;
		}
	}
}