<?php

class Wptc_Staging extends WPTC_Privileges {
	const CLONE_TMP_FOLDER = 'wptc_staging_controller';
	const BRIDGE_FOLDER = 'wp-tcapsule-bridge';
	protected $processed_files;
	protected $staging_id;
	protected $db_backup;
	protected $meta_file_name;
	protected $meta_dir_name;
	protected $options;
	protected $meta_upload_chunk_size;
	protected $phpseclib_path;
	protected $logger;
	protected $config;
	protected $filesystem;
	protected $plugin_bridge_path;
	protected $same_staging_folder;
	protected $same_staging_bridge_dir;
	protected $ss_large_file_buffer_size;
	protected $processed_db;
	protected $exclude_class_obj;
	protected $file_base;

	public function __construct(){
		$this->options = WPTC_Pro_Factory::get('Wptc_staging_Config');
		$this->db_backup = WPTC_Factory::get('databaseBackup');
		$this->processed_files = WPTC_Factory::get('processed-files');
		$this->exclude_class_obj = WPTC_Base_Factory::get('Wptc_ExcludeOption');
		$this->processed_db = new WPTC_Processed_DBTables();
		$this->meta_file_name = 'wordpress-db_meta_data.sql';
		$this->meta_file_path = 'download'.'/'.$this->meta_file_name;
		$this->meta_upload_chunk_size = 1024 * 1024 * 5;
		$this->phpseclib_path = WPTC_PLUGIN_DIR.'Pro/Staging/phpseclib';
		$this->logger = WPTC_Factory::get('logger');
		$this->config = WPTC_Factory::get('config');
		$this->plugin_bridge_path = dirname(__FILE__) . '/' . 'bridge'.'/';
		$this->ss_large_file_buffer_size = 1024 * 1024 * 2; //20MB
		$this->init_staging_id();
		$this->file_base = new Utils_Base();
		$this->run_updates();
	}

	private function init_file_system(){
		global $wp_filesystem;
		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-035');
				return false;
			}
		}
		 $this->filesystem = $wp_filesystem;
	}

	public function run_updates(){
		global $wpdb;
		$updates = $this->config->get_option('run_staging_updates');
		if (empty($updates)) {
			return false;
		}
		if (version_compare('1.9.0', $updates) >= 0) {
			dark_debug(array(), '-------------- staging run_updates-----------------');
			$prefix = $this->same_server_get_staging_full_prefix();
			dark_debug($prefix, '--------$prefix stagng run_updates--------');
			if ($prefix !== false && $prefix !== $wpdb->base_prefix) {
				$this->discourage_search_engine($prefix, $reset_permalink = true);
			}
		}
		$this->config->set_option('run_staging_updates', false);
	}

	public function init() {
		if ($this->is_privileged_feature(get_class($this)) && $this->is_switch_on()) {
			$supposed_hooks_class = get_class($this) . '_Hooks';
			WPTC_Pro_Factory::get($supposed_hooks_class)->register_hooks();
		}
	}

	private function is_switch_on(){
		return true;
	}

	public function connect_cpanel_wptc($data){
		require_once 'CpanelWorks.php';
		$cpanel_obj  = new Cpanel_Works();
		$cpanel_obj->auto_fill_cpanel($data);
	}

	public function create_db_cpanel($data){
		require_once 'CpanelWorks.php';
		$cpanel_obj  = new Cpanel_Works();
		$cpanel_obj->create_db_cpanel($data);
	}

	public function validate_database_wptc($data){
		$this->db_check($data);
	}

	public function process_staging_req_wptc_h(){
		global $settings_ajax_start_time;
		if ($this->options->get_option('same_server_staging_running')) {
			$this->same_server_process_staging_req();
		}
		while (!is_wptc_timeout_cut(false , 10)) {
			// dark_debug(array(), '----------process_staging_req_wptc_h-----------');
			$progress_status = $this->options->get_option('staging_progress_status', true);
			// dark_debug($progress_status,'--------------$progress_status-------------');
			if ($this->options->get_option('staging_call_waiting_for_response')) {
				dark_debug(array(), '---------Call already waiting lets sleep for 2 seconds------------');
				sleep(2);
				continue;
			}
			if ($this->is_staging_failed()){
				return ;
			}
			if($progress_status == 'ready_to_start'){
				$this->logger->log("initializing staging data on server", 'staging_progress', $this->staging_id);
				$this->init_staging_in_bridge();
			} else if($progress_status == 'bridge_downloaded_n_extracted' || $progress_status == 'meta_download_running') {
				$this->logger->log("Downloading meta data started", 'staging_progress', $this->staging_id);
				$this->download_meta_data();
			} else if($progress_status == 'meta_download_completed'){
				$this->logger->log("Uploading meta data started", 'staging_progress', $this->staging_id);
				$this->upload_meta_data_to_staging();
			} else if ($progress_status == 'meta_upload_completed' || $progress_status == 'meta_data_import_running') {
				$this->logger->log("Importing meta data on server started", 'staging_progress', $this->staging_id);
				$this->init_meta_import();
			} else if($progress_status == 'meta_data_import_completed'){
				$this->logger->log("Initializing restore on server started", 'staging_progress', $this->staging_id);
				$this->init_restore();
			} else if($progress_status == 'init_restore_completed'){
				$this->logger->log("Downloading files on server started", 'staging_progress', $this->staging_id);
				$this->continue_restore(1);
			} else if($progress_status == 'continue_restore_running'){
				$this->logger->log("Downloading files on server resumed.", 'staging_progress', $this->staging_id);
				$this->continue_restore();
			} else if($progress_status == 'continue_restore_completed'){
				$this->logger->log("Copy files to respective place on server started", 'staging_progress', $this->staging_id);
				$this->init_copy(1);
			} else if($progress_status == 'continue_copy_running'){
				$this->logger->log("Copy files to respective place on server resumed", 'staging_progress', $this->staging_id);
				$this->init_copy();
			} else if($progress_status == 'continue_copy_completed'){
				$this->logger->log("New site changes on server started", 'staging_progress', $this->staging_id);
				$this->new_site_changes();
			} else if($progress_status == 'new_site_changes_done'){
				$this->update_in_staging();
			} else if($progress_status == 'staging_completed'){
				$this->logger->log("Completing staging settings on server", 'staging_progress', $this->staging_id);
				$this->complete_staging();
			}
			if ($this->is_staging_failed()) {
				return ;
			}
		}
	}

	private function init_staging_id(){
		$this->staging_id = $this->options->get_option('staging_id',true);
		if (empty($this->staging_id)) {
			$this->options->set_option('staging_id', time());
			$this->staging_id = $this->options->get_option('staging_id');
		}
	}

	private function is_staging_failed(){
		$error = $this->options->get_option('staging_last_error', true);
		if(!empty($error)) {
			dark_debug($error, '---------Error there------------');
			$this->logger->log("Error: ".$error, 'staging_error', $this->staging_id);
			$this->options->set_option('is_staging_running', false);
			$this->options->set_option('external_staging_requested', false);
			$this->clear_bridge_files();
			return true;
		}
		return false;
	}

	public function stop_staging(){
		if ($this->options->get_option('staging_type') === 'internal') {
			dark_debug(array(), '---------------Stop staging internal-----------------');
			$this->same_staging_stop_process();
		} else {
			$error = $this->options->set_option('staging_last_error', false);
			$this->logger->log("Stopped staging manually", 'staging_error', $this->staging_id);
			$this->options->set_option('is_staging_running', false);
			$this->options->set_option('external_staging_requested', false);
		}
		$this->options->flush();
		// $this->hard_reset_staging_flags();
		dark_debug(array(), '---------Cleared everything------------');
	}

	public function download_meta_data(){
		$result = $this->processed_files->get_last_meta_file();
		if ($this->options->get_option('default_repo') === "s3") {
			$dropbox_path = $this->options->get_option('dropbox_location') . '-meta-data/'.remove_secret($result->file, true);
		} else {
			$dropbox_path = $this->options->get_option('dropbox_location') . '-meta-data';
		}
		$dropbox_path = wp_normalize_path($dropbox_path);
		if (empty($result)) {
			dark_debug(array(),'--------------Could not find the meta file-------------');
			$this->logger->log("Could not find the meta data", 'staging_failed', $this->staging_id);
			$this->options->set_option('staging_last_error',"Could not find the meta data");
			return false;
		}
		$revision_id = $result->revision_id;
		$file = WPTC_ABSPATH.$this->meta_file_path;
		$file = wp_normalize_path($file);
		$output = new WPTC_Extension_DefaultOutput();
		$upload_output = $output->drop_download($dropbox_path, $file, $revision_id, $result, (array) $result, 1);
		$this->process_meta_dowload_status($upload_output);
	}

	private function process_meta_dowload_status($upload_output){
		if (isset($upload_output['error'])) {
			$this->logger->log("Could not download meta data".$upload_output['error'], 'staging_error', $this->staging_id);
			$this->logger->log("Staging stopped", 'staging_error', $this->staging_id);
			$this->options->set_option('staging_last_error', 'Could not download meta data.');
		} else if(isset($upload_output['too_many_requests'])){
			$this->options->set_option('staging_progress_status', '');
			// sleep(3);
		} else if ($upload_output) {
			$dbox_array = (array) $upload_output;
			if (!empty($dbox_array['chunked'])) {
				$this->options->set_option('staging_progress_status', 'meta_download_running');
			} else {
				$this->options->set_option('staging_progress_status', 'meta_download_completed');
				dark_debug(array(), '-----------download completed-------------');
				$this->logger->log("Downloading meta data completed", 'staging_progress', $this->staging_id);
			}
		}
	}

	public function upload_meta_data_to_staging(){
		$connect_type = $this->get_staging_details('ftp', 'connect_type');
		$default_meta_file = $this->db_backup->get_file(1);
		$meta_path = substr($default_meta_file, 0, strpos($default_meta_file, 'backups'));
		$meta_file = $meta_path.$this->meta_file_path;
		dark_debug($meta_path,'--------------$meta_path-------------');
		dark_debug($meta_file,'--------------$meta_file-------------');
		$meta_file_name = basename($meta_file);
		dark_debug($meta_file_name,'--------------$meta_file_name-------------');
		if ($connect_type == 'sftp') {
			dark_debug(array(), '-----------uploading meta in sftp-------------');
			$result = $this->meta_data_upload_sftp($meta_file_name, $meta_file);
		} else{
			dark_debug(array(), '-----------uploading meta in ftp-------------');
			$result = $this->meta_data_upload_ftp($meta_file);
		}
		if ($result) {
			$this->logger->log("Uploading meta data completed", 'staging_progress', $this->staging_id);
			dark_debug(array(), '-----------meta data upload completed-------------');
			$this->options->set_option('staging_progress_status', 'meta_upload_completed');
		} else {
			$this->logger->log("Uploading meta data failed", 'staging_failed', $this->staging_id);
			dark_debug(array(), '-----------meta data upload failed-------------');
		}
		dark_debug($meta_file, '---------$meta_file after upload------------');
		@unlink($meta_file);
		$this->logger->log("meta data deleted on local system", 'staging_progress', $this->staging_id);
	}

	// public function meta_data_upload_sftp($meta_file_name = "rand.zip", $meta_file_path="/opt/lampp/htdocs/test/test.zip"){
	private function meta_data_upload_sftp($meta_file_name, $meta_file_path){
		$start_time = microtime(true);

		$sftp_details = $this->get_staging_details('ftp');

		dark_debug($sftp_details, '---------$sftp_details------------');
		extract($sftp_details);

		$sftp = $this->create_sftp_connection($host, $port, $username, $password);

		if (!$sftp) {
			return false;
		}

		$upload_bridge = $remote_folder.'/'.self::BRIDGE_FOLDER.'/';
		dark_debug($upload_bridge, '---------$upload_bridge------------');
		// $upload_bridge = '/home/selva_sftp/www/';
		$mkdir_re = $sftp->mkdir($upload_bridge, 0755, 1);
		$chmod_r = $sftp->chmod($upload_bridge, 0755);  // octal; correct value of mode
		dark_debug($chmod_r, '---------$chmod_r------------');
		dark_debug($mkdir_re, '---------$mkdir_re------------');
		$chdir_re = $sftp->chdir($upload_bridge);
		dark_debug($chdir_re, '---------$chdir_re------------');
		$read = $meta_file_path;
		if (file_exists($read)) {
			// Set a chunk size

			// For reading through the file we want to copy to the FTP server.
			$read_handle = fopen($read, 'rb');

			// For appending to the destination file.
			$meta_chunk_upload_running = $this->options->get_option('meta_chunk_upload_running');
			if ($meta_chunk_upload_running) {
				$offset = $this->options->get_option('meta_chunk_upload_offset');
			} else {
				$offset = 0;
			}
			dark_debug($offset, '---------$offset init------------');
			// Loop through $read until we reach the end of the file.
			while ($offset < filesize($meta_file_path)){
				dark_debug($offset, '---------$offset------------');
				fseek($read_handle, $offset);
				// Read a chunk of the file we're copying.
				$chunk = fread($read_handle, $this->meta_upload_chunk_size);

				// Write the chunk to the destination file.
				$result = $sftp->put($meta_file_name,  $chunk ,  NET_SFTP_RESUME);
				if (!$result) {
					dark_debug($sftp->getSFTPErrors(), '---------$sftp->getErrors()------------');
					$this->options->set_option('staging_last_error',"Cannot write file in remote");
					return false;
				}
				$offset = ftell($read_handle);
				dark_debug($current_stage, '---------$current_stage------------');
				if (is_wptc_timeout_cut($start_time)) {
					$break = true;
					break;
				}
				sleep(1);
			}
		}

		fclose($read_handle);

		if (isset($break) && $break && $offset < filesize($meta_file_path)) {
			$this->options->set_option('meta_chunk_upload_running', true);
			$this->options->set_option('meta_chunk_upload_offset', $offset );
			// die();
		} else {
			$this->options->set_option('meta_chunk_upload_running', false);
			$this->options->set_option('meta_chunk_upload_offset', false);
			return true;
		}
	}

	private function meta_data_upload_ftp($meta_file_path){

		$ftp_details = $this->get_staging_details('ftp');
		extract($ftp_details);

		$connection = $this->create_ftp_connection($ssl, $host, $port, $username, $password, $passive);

		if (!$connection) {
			return false;
		}

		dark_debug($upload_path,'--------------$upload_path-------------');

		$upload_bridge = $remote_folder.'/'.self::BRIDGE_FOLDER.'/';
		$this->chmod_for_dir($upload_bridge, $connection);
		$upload = $this->ftp_multi_upload($connection, $upload_bridge . '/' . basename($meta_file_path), $meta_file_path, FTP_BINARY);
		@ftp_close($connection);

		if ($upload === false) {
			return false;
		}

		return $upload;
	}

	private function ftp_multi_upload($conn_id, $remote_file, $backup_file, $mode) {
		$start_time = microtime(true);
		//get the filesize of the remote file first
		$file_size = ftp_size($conn_id, $remote_file);
		dark_debug($file_size, '---------$file_size------------');
		if ($file_size == -1 || !$file_size) {
			$file_size = 0;
		}

		//read the parts local file , if it is a second call start reading the file from the left out part which is at the offset of the remote file's filesize.
		$fp = fopen($backup_file, 'r');
		fseek($fp,$file_size);
		$ret = ftp_nb_fput($conn_id, $remote_file, $fp, FTP_BINARY, $file_size);

		if(!$ret || $ret == FTP_FAILED) {
			dark_debug(array(), '---------ftp nb fput not permitted------------');
			$this->options->set_option('staging_last_error',"FTP upload Error. ftp_nb_fput(): Append/Restart not permitted.");
			return false;
		}
		/*
		1.run the while loop as long as FTP_MOREDATA is set
		2.if ret == FTP_FINISHED , it means the ftpUpload is complete .. return as "completed".
		*/
		while ($ret == FTP_MOREDATA) {
			$break = false;
			// Continue upload...
			$ret = ftp_nb_continue($conn_id);
			if (is_wptc_timeout_cut($start_time)) {
				dark_debug($file_size, '---------$file_size upload stopped------------');
				$break = true;
				break;
			}
		}

		fclose($fp);
		if (isset($break) && $break && $file_size < filesize($backup_file) || $ret != FTP_FINISHED) {
			dark_debug(array(), '---------Break or not finished------------');
			$this->options->set_option('meta_chunk_upload_running', true);
			$this->options->set_option('meta_chunk_upload_offset', $file_size );
			die();
		}

		//checking file size and comparing
		$verification_result = $this -> post_upload_verification_meta_data($conn_id, $backup_file, $remote_file);
		if(!$verification_result) {
			dark_debug(array(), '---------verification failed------------');
			$this->options->set_option('staging_last_error',"FTP verification failed: File may be corrupted.");
			return false;
		}
		$this->options->set_option('meta_chunk_upload_running', false);
		$this->options->set_option('meta_chunk_upload_offset', false);
		return true;

	}

	private function post_upload_verification_meta_data($obj, $backup_file, $destFile){
		dark_debug(array(), '---------post_upload_verification_meta_data------------');
		$actual_file_size = filesize($backup_file);
		dark_debug($actual_file_size, '---------$actual_file_size------------');
		$size1 = $actual_file_size-((0.1) * $actual_file_size);
		dark_debug($size1, '---------$size1------------');
		$size2 = $actual_file_size+((0.1) * $actual_file_size);
		dark_debug($size2, '---------$size2------------');
		ftp_chdir ($obj , dirname($destFile));
			$ftp_file_size = ftp_size($obj, basename($destFile));
			dark_debug($ftp_file_size, '---------$ftp_file_size------------');
			if($ftp_file_size > 0)
			{
				if((($ftp_file_size >= $size1 && $ftp_file_size <= $actual_file_size) || ($ftp_file_size <= $size2 && $ftp_file_size >= $actual_file_size) || ($ftp_file_size == $actual_file_size)) && ($ftp_file_size != 0) || $ftp_file_size >= $actual_file_size) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
	}

	public function check_ftp_crendtials_wptc($params){
		if (empty($params)) {
			die_with_json_encode(array('error' => 'FTP Credentials are empty'));
		}
		dark_debug($params,'--------------$params-------------');
		$this->ftp_check($params);
	}

	private function ftp_check($ftp_details, $delete_staging = false){
		dark_debug($ftp_details, '---------$ftp_details------------');
		$ftp_details = $this->add_extra_info_with_creds($ftp_details);
		dark_debug($ftp_details , '---------$ftp_details after------------');

		extract($ftp_details);

		$this->common_check_and_bridge_upload($connect_type, $die_with_err = 1);

		$result = $this->check_source_destination_folder($destination_bridge_url);
		if ($result == true) {
			if ($delete_staging) {
				return true;
			}
			$this->check_wp_config_file_present($destination_bridge_url);
		}
	}

	private function common_check_and_bridge_upload($connect_type = NULL, $die_with_err = 0){
		if (empty($connect_type)) {
			$connect_type = $this->get_staging_details('ftp', 'connect_type');
		}

		if($connect_type && $connect_type == 'sftp') {
			dark_debug(array(), '---------SFTP SELECTED------------');
			$result = $this->connect_and_upload_bridge_sftp($die_with_err);
		} else {
			dark_debug(array(), '---------FTP SELECTED------------');
			$result = $this->connect_and_upload_bridge_ftp($die_with_err);
		}
		if ($result === false) {
			if ($die_with_err) {
				die_with_json_encode(array('error' => 'Bridge upload failed, Unknown reason'));
			}
			$this->options->set_option('staging_last_error', 'Bridge upload failed, Unknown reason');
		}
	}

	private function add_extra_info_with_creds($ftp_details){
		dark_debug($ftp_details, '---------$ftp_details------------');
		$ftp_details['destination_url'] = addTrailingSlash($ftp_details['destination_url']);
		$ftp_details['remote_folder'] = addTrailingSlash($ftp_details['remote_folder']);
		dark_debug($ftp_details, '---------$ftp_details------------');
		$ftp_details['destination_url'] = add_protocol_common($ftp_details['destination_url']);
		$ftp_details['upload_path'] = '/'.trim($ftp_details['remote_folder'], '/').'/'.self::CLONE_TMP_FOLDER.'/';
		$ftp_details['destination_bridge_url'] = trim($ftp_details['destination_url'], '/').'/'.self::CLONE_TMP_FOLDER.'/bridge.php';
		$this->options->set_option('staging_ftp_details', serialize($ftp_details));
		return $ftp_details;
	}

	private function check_wp_config_file_present($destination_bridge_url){
		$request_params['action'] = 'get_db_wp_config';
		$raw_response = $this->config->doCall($destination_bridge_url, $request_params);
		dark_debug($raw_response,'--------------$raw_response-------------');
		$response = $this->decode_response($raw_response);
		if(isset($response['error'])){
			$this->options->set_option('staging_last_error', $response['error']);
		}
		dark_debug($response, '---------$response------------');
		if ($response['success'] === 'not_found') {
			$status = true;
		}
		dark_debug($status,'--------------$status-------------');
		if ($status || !isset($response['DB_USER'])) {
			die_with_json_encode(array('success' => 'wp_config_not_found'));
		} else {
			dark_debug(array(), '-----------successfully got wp cofnig data-------------');
			die_with_json_encode(array('wp_config_data' => $response, 'success' => 1));
		}
	}

	private function check_source_destination_folder($destination_bridge_url){
		$request_params['action'] = 'source_destination_check';
		dark_debug($destination_bridge_url,'--------------$destination_bridge_url-------------');
		$raw_response = $this->config->doCall($destination_bridge_url, $request_params);
		dark_debug($raw_response,'--------------$raw_response-------------');
		$response = $this->decode_response($raw_response);
		if(isset($response['error'])){
			$this->options->set_option('staging_last_error', $response['error']);
		}
		dark_debug($response, '---------$response------------');
		$status = false;
		if ($response['success'] === 'hand_shake_done') {
			$status = true;
		}
		dark_debug($status,'--------------$status-------------');
		if ($status === false) {
			die_with_json_encode(array('folder_mismatch_error' => 'Error: Folder Paths mismatch'));
		}
		dark_debug(array(), '-----------Successfully folder matched-------------');
		return true;
	}

	private function db_check($db_details){
		dark_debug($db_details,'--------------$db_details-------------');
		extract($db_details);
		$destination_bridge_url = $this->get_staging_details('ftp', 'destination_bridge_url');
		dark_debug($destination_bridge_url,'--------------$destination_bridge_url-------------');
		$this->do_call_db_check($destination_bridge_url, 0, $db_host, $db_user, $db_password, $db_name, $db_prefix);
		// if (empty($db_host) && empty($db_user) && empty($db_password) && empty($db_name)) {
		// 	$this->do_call_db_check($destination_url, 1);
		// }
	}

	private function connect_and_upload_bridge_sftp($die_with_err = 0){
		dark_debug(array(), '-----------connect_sftp-------------');
		$sftp_details = $this->get_staging_details('ftp');
		if (empty($sftp_details)) {
			dark_debug(array(), '---------SFTP Details Empty------------');
			return false;
		}
		dark_debug($sftp_details, '---------$sftp_details------------');
		extract($sftp_details);

		$sftp = $this->create_sftp_connection($host, $port, $username, $password, $die_with_err);

		if (!$sftp) {
			return false;
		}

		$this->upload_bridge_sftp($sftp, $upload_path, $die_with_err);
		return true;
	}

	private function create_sftp_connection($host, $port, $username, $password, $die_with_err = 0){
		set_include_path(get_include_path() . PATH_SEPARATOR . $this->phpseclib_path);
		include_once('Net/SFTP.php');
		$sftp = new Net_SFTP($host, $port);
		if(!$sftp) {
			dark_debug(array(), '-----------Connection to the SFTP Host failed. Check your host_name.-------------');
			if ($die_with_err) {
				die_with_json_encode(array('error' => 'Connection to the SFTP host failed'));
			}
			$this->options->set_option('staging_last_error', 'Connection to the SFTP host failed');
			return false;
		}
		dark_debug($username, '---------$username------------');
		dark_debug($password, '---------$password------------');
		$login = $sftp->login($username, $password);
		dark_debug($login, '---------$login------------');
		if (!$login) {
			// dark_debug($sftp->getSFTPErrors(), '---------$ssh->getErrors()------------');
			dark_debug(array(), '-----------Could not login to SFTP. Please check the credentials.-------------');
			if ($die_with_err) {
				die_with_json_encode(array('error' => 'Could not login to SFTP'));
			}
			$this->options->set_option('staging_last_error', 'Could not login to SFTP');
			return false;
		}
		return $sftp;
	}

	private function upload_bridge_sftp(&$sftp, $upload_path, $die_with_err = 0){
		$file_wp_db					= "wp-db-custom.php";
		$file_bridge 				= "bridge.php";
		$file_wp_modified_functions	= "wp-modified-functions.php";
		$file_pclzip				= "pclzip.php";

		$sftp->mkdir($upload_path,-1,true);
		$sftp->chdir($upload_path);
		$sftp->chmod(0777, $upload_path, true);

		$upload_wp_db 	= @$sftp->put(basename($file_wp_db),  WPTC_PLUGIN_DIR .'wp-tcapsule-bridge/'.$file_wp_db, NET_SFTP_LOCAL_FILE);
		$upload_bridge 	= @$sftp->put(basename($file_bridge),  WPTC_PLUGIN_DIR .'Pro/Staging/bridge/'.$file_bridge, NET_SFTP_LOCAL_FILE);
		$upload_wp_modified_functions 	= @$sftp->put(basename($file_wp_modified_functions),  WPTC_PLUGIN_DIR .'wp-tcapsule-bridge/'.$file_wp_modified_functions, NET_SFTP_LOCAL_FILE);
		$upload_pclzip	= @$sftp->put(basename($file_pclzip),  WPTC_PLUGIN_DIR .'Pro/Staging/bridge/'.$file_pclzip, NET_SFTP_LOCAL_FILE);
		if(!$upload_wp_db || !$upload_bridge || !$upload_wp_modified_functions || !$upload_pclzip){
			dark_debug(array(), '-----------SFTP Upload failed.-------------');
			if ($die_with_err) {
				die_with_json_encode(array('error' => 'SFTP upload failed. Please make sure that above credentials has rights to create / delete files and folders.'));
			} else {
				$this->options->set_option('staging_last_error', 'SFTP Bridge upload failed');
			}
			return false;
		}

		$sftp->chmod(0777, $upload_path, true);

		//Seems does not necessary now
		// dark_debug(array(), '-----------First part Uploaded successfully-------------');

		// $sftp->mkdir($upload_path.'/phpseclib/Crypt',-1,true);
		// $sftp->mkdir($upload_path.'/phpseclib/File',-1,true);
		// $sftp->mkdir($upload_path.'/phpseclib/Math',-1,true);
		// $sftp->mkdir($upload_path.'/phpseclib/Net/SFTP',-1,true);
		// $sftp->mkdir($upload_path.'/phpseclib/System',-1,true);

		// $sftp->chdir($upload_path.'/phpseclib/Crypt');
		// @$sftp->put('AES.php', 		$this->phpseclib_path."/lib/phpseclib/Crypt/AES.php", NET_SFTP_LOCAL_FILE);
		// @$sftp->put('Base.php', 	$this->phpseclib_path."/lib/phpseclib/Crypt/Base.php", NET_SFTP_LOCAL_FILE);
		// @$sftp->put('Blowfish.php', $this->phpseclib_path."/lib/phpseclib/Crypt/Blowfish.php", NET_SFTP_LOCAL_FILE);
		// @$sftp->put('DES.php', 		$this->phpseclib_path."/lib/phpseclib/Crypt/DES.php", NET_SFTP_LOCAL_FILE);
		// @$sftp->put('Hash.php', 	$this->phpseclib_path."/lib/phpseclib/Crypt/Hash.php", NET_SFTP_LOCAL_FILE);
		// @$sftp->put('Random.php', 	$this->phpseclib_path."/lib/phpseclib/Crypt/Random.php", NET_SFTP_LOCAL_FILE);
		// @$sftp->put('RC2.php', 		$this->phpseclib_path."/lib/phpseclib/Crypt/RC2.php", NET_SFTP_LOCAL_FILE);
		// @$sftp->put('RC4.php', 		$this->phpseclib_path."/lib/phpseclib/Crypt/RC4.php", NET_SFTP_LOCAL_FILE);
		// @$sftp->put('Rijndael.php', $this->phpseclib_path."/lib/phpseclib/Crypt/Rijndael.php", NET_SFTP_LOCAL_FILE);
		// @$sftp->put('RSA.php', 		$this->phpseclib_path."/lib/phpseclib/Crypt/RSA.php", NET_SFTP_LOCAL_FILE);
		// @$sftp->put('TripleDES.php',$this->phpseclib_path."/lib/phpseclib/Crypt/TripleDES.php", NET_SFTP_LOCAL_FILE);
		// @$sftp->put('Twofish.php', 	$this->phpseclib_path."/lib/phpseclib/Crypt/Twofish.php", NET_SFTP_LOCAL_FILE);

		// $sftp->chdir($upload_path.'/phpseclib/File');
		// @$sftp->put('ANSI.php', $this->phpseclib_path."/lib/phpseclib/File/ANSI.php", NET_SFTP_LOCAL_FILE);
		// @$sftp->put('ASN1.php', $this->phpseclib_path."/lib/phpseclib/File/ASN1.php", NET_SFTP_LOCAL_FILE);
		// @$sftp->put('X509.php', $this->phpseclib_path."/lib/phpseclib/File/X509.php", NET_SFTP_LOCAL_FILE);

		// $sftp->chdir($upload_path.'/phpseclib/Math');
		// @$sftp->put('BigInteger.php', $this->phpseclib_path."/lib/phpseclib/Math/BigInteger.php", NET_SFTP_LOCAL_FILE);

		// $sftp->chdir($upload_path.'/phpseclib/Net');
		// @$sftp->put('SCP.php', 	$this->phpseclib_path."/lib/phpseclib/Net/SCP.php", NET_SFTP_LOCAL_FILE);
		// @$sftp->put('SFTP.php', $this->phpseclib_path."/lib/phpseclib/Net/SFTP.php", NET_SFTP_LOCAL_FILE);
		// @$sftp->put('SSH1.php', $this->phpseclib_path."/lib/phpseclib/Net/SSH1.php", NET_SFTP_LOCAL_FILE);
		// @$sftp->put('SSH2.php', $this->phpseclib_path."/lib/phpseclib/Net/SSH2.php", NET_SFTP_LOCAL_FILE);

		// $sftp->chdir($upload_path.'/phpseclib/Net/SFTP');
		// @$sftp->put('Stream.php', $this->phpseclib_path."/lib/phpseclib/Net/SFTP/Stream.php", NET_SFTP_LOCAL_FILE);

		// $sftp->chdir($upload_path.'/phpseclib/System');
		// @$sftp->put('SSH_Agent.php', $this->phpseclib_path."/lib/phpseclib/System/SSH_Agent.php", NET_SFTP_LOCAL_FILE);

		// $sftp->chdir($upload_path.'/phpseclib');
		// @$sftp->put('openssl.cnf', $this->phpseclib_path."/lib/phpseclib/openssl.cnf", NET_SFTP_LOCAL_FILE);

		// $sftp->chdir($upload_path);
		// dark_debug(array(), '-----------All files Uploaded successfully-------------');

		return true;

	}

	private function connect_and_upload_bridge_ftp($die_with_err = 0){
		dark_debug(array(), '-----------connect_ftp-------------');
		$ftp_details = $this->get_staging_details('ftp');

		if (empty($ftp_details)) {
			dark_debug(array(), '---------FTP Details Empty------------');
			return false;
		}

		dark_debug($ftp_details, '---------$ftp_details connect_and_upload_bridge_ftp------------');
		extract($ftp_details);

		$connection = $this->create_ftp_connection($ssl, $host, $port, $username, $password, $passive, $die_with_err);
		if (!$connection) {
			return false;
		}

		dark_debug($upload_path,'--------------$upload_path-------------');
		$this->upload_bridge_ftp($connection, $upload_path, $die_with_err = 1);

		@ftp_close($connection);
		dark_debug(array(), '-----------result returned-------------');
		return true;
	}

	private function create_ftp_connection($ssl, $name, $port, $username, $password, $passive, $die_with_err = 0){
		if(!empty($ssl) && function_exists('ftp_ssl_connect')){
			dark_debug(array(), '-----------Coming inside host ssl-------------');
			$connection = @ftp_ssl_connect($name, $port);
		} else{
			$connection = @ftp_connect($name, $port);
		}

		if (!$connection){
			dark_debug(array(), '-----------Connection to the Host failed. Check your FTP Host.-------------');
			if ($die_with_err) {
				die_with_json_encode(array('error' => 'Connection to the host failed. Check your FTP hostname'));
			}
			$this->options->set_option('staging_last_error', 'Connection to the host failed');
			return false;
		}

		$login = @ftp_login($connection, $username, $password);

		if (!$login) {
			dark_debug(array(), '-----------Could not login to FTP. Please check the credentials.-------------');
			if ($die_with_err) {
				die_with_json_encode(array('error' => 'Could not login to FTP. Please check the credentials.'));
			}
			$this->options->set_option('staging_last_error', 'Could not login to FTP');
			return false;
		} else {
			dark_debug($passive,'--------------$passive-------------');
			if(!empty($passive)){
				dark_debug(array(), '-----------Coming in -------------');
				@ftp_pasv($connection, true);
			}
		}
		$connection_ref = &$connection;
		return $connection_ref;
	}

	private function upload_bridge_ftp(&$connection, $upload_path, $die_with_err = 0){
		$result= $this->chmod_for_dir($upload_path, $connection);
		$upload_path = rtrim($upload_path, '/');
		dark_debug($result, '---------$result------------');
		if ($result === false) {
			dark_debug(array(), '---------dying.------------');
			$this->options->set_option('staging_last_error', 'Bridge upload failed');
			die_with_json_encode(array('error' => 'FTP upload failed. Please make sure that above credentials has rights to create / delete files and folders.'));
		}
		$file_wp_db 					= "/wp-db-custom.php";
		$file_bridge 					= "/bridge.php";
		$file_pclzip 					= "/pclzip.php";
		$file_wp_modified_functions  	= "/wp-modified-functions.php";

		$upload_wp_db 	= @ftp_put($connection, $upload_path.$file_wp_db,  WPTC_PLUGIN_DIR .'wp-tcapsule-bridge'.$file_wp_db, FTP_ASCII);
		$upload_bridge 	= @ftp_put($connection, $upload_path.$file_bridge,  WPTC_PLUGIN_DIR .'Pro/Staging/bridge'.$file_bridge, FTP_ASCII);
		$upload_wp_modified_functions 	= @ftp_put($connection, $upload_path.$file_wp_modified_functions,  WPTC_PLUGIN_DIR .'wp-tcapsule-bridge'.$file_wp_modified_functions, FTP_ASCII);
		$upload_pclzip 	= @ftp_put($connection, $upload_path.$file_pclzip,  WPTC_PLUGIN_DIR .'Pro/Staging/bridge'.$file_pclzip, FTP_ASCII);
		dark_debug($upload_wp_db,'--------------$upload_wp_db-------------');
		dark_debug($upload_bridge,'--------------$upload_bridge-------------');
		dark_debug($upload_wp_modified_functions,'--------------$upload_wp_modified_functions-------------');
		dark_debug($upload_pclzip,'--------------$upload_pclzip-------------');
		$this->chmod_for_dir($upload_path, $connection);
		if (!$upload_wp_db || !$upload_bridge || !$upload_wp_modified_functions || !$upload_pclzip) {
			dark_debug(array(), '-------------FTP upload failed-----------');
			if ($die_with_err) {
				die_with_json_encode(array('error' => 'FTP upload failed. Please make sure that above credentials has rights to create / delete files and folders.'));
			}
			$this->options->set_option('staging_last_error', 'Bridge upload failed');
		} else {
			dark_debug(array(), '-------------FTP upload successfully-----------');
		}
	}

	private function chmod_for_dir($cloneTempPath, &$connection){

		$parts = explode("/", $cloneTempPath);
		$countParts = count($parts);
		$return = true;
		$fullpath = "";
		$i = 0;
		foreach($parts as $part){
			$i++;
			if(empty($part)){
				$fullpath .= "/";
				continue;
			}
			$fullpath .= $part."/";
			if(@ftp_chdir($connection, $fullpath)){
				ftp_chdir($connection, $fullpath);
			} else{
				if(@ftp_mkdir($connection, $part)){
					ftp_chdir($connection, $part);
					if($countParts == $i){//$countParts == $i to make sure it is last folder
						if (function_exists('ftp_chmod') ){
							@ftp_chmod($connection, 0777, $fullpath);
						}
						else{
							@ftp_site($connection, sprintf('CHMOD %o %s', 0777, $fullpath));
						}
					}
				} else{
					$return = false;
				}
			}
		}
		return $return;

		// $return = true;
		// if(ftp_chdir($connection, $fullpath)){
		// 	dark_debug(array(), '---------1------------');
		// 	ftp_chdir($connection, $fullpath);
		// } else{
		// 	dark_debug(array(), '---------2------------');
		// 	if(ftp_mkdir($connection, $fullpath)){
		// 		dark_debug(array(), '---------3------------');
		// 		ftp_chdir($connection, $fullpath);
		// 		dark_debug(array(), '---------4------------');
		// 		if (function_exists('ftp_chmod') ){
		// 			dark_debug(array(), '---------5------------');
		// 			ftp_chmod($connection, 0777, $fullpath);
		// 		} else{
		// 			dark_debug(array(), '---------6------------');
		// 			ftp_site($connection, sprintf('CHMOD %o %s', 0777, $fullpath));
		// 		}
		// 	} else{
		// 		$return = false;
		// 	}
		// }
		// if($return == false){
		// 	return false;
		// }

	}

	private function do_call_db_check($destination_url, $get_db_creds_from_wp, $db_host = null, $db_user = null, $db_password = null, $db_name= null, $db_prefix = null){
		// $destination_url = $destination_url.self::CLONE_TMP_FOLDER.'/bridge.php';
		if ($get_db_creds_from_wp == 1) {
			$request_params['get_db_creds_from_wp'] = 1;
		} else {
			global $wpdb;
			$request_params['db_host'] = $db_host;
			$request_params['db_username'] = $db_user;
			$request_params['db_password'] = $db_password;
			$request_params['db_name'] = $db_name;
			$request_params['db_prefix'] = $db_prefix;
		}
		$request_params['default_repo'] = $this->options->get_option('default_repo');

		$request_params['action'] = 'check_db_connection';

		dark_debug($request_params,'--------------$request_params-------------');
		dark_debug($destination_url,'--------------$destination_url-------------');
		$raw_response = $this->config->doCall($destination_url, $request_params);
		$response = $this->decode_response($raw_response);
		if(isset($response['error'])){
			$this->options->set_option('staging_last_error', $response['error']);
		}
		dark_debug($response,'--------------$response-------------');
		if ($response['success'] == 1) {
			$this->options->set_option('staging_db_details', serialize($request_params));
			dark_debug(array(), '-----------successfully Db connected-------------');
			die_with_json_encode(array('success' => '1'));
		} else {
			if (stripos($raw_response, "Access denied") !== false ) {
				dark_debug(array(), '----------Access denied. Please check your credentials and try again.--------------');
				die_with_json_encode(array('error' => 'Access denied.'));
			} else if(stripos($raw_response, "host") !== false ) {
				dark_debug(array(), '----------No such host is known. Please check host name and try again..--------------');
				die_with_json_encode(array('error' => 'No such host is known.'));
			} else {
				dark_debug(array(), '-----------Could create db connection-------------');
				die_with_json_encode(array('error' => 'No such database is known.'));
			}
		}
	}

	public function init_staging_in_bridge(){
		$db_details = $this->get_staging_details('db');
		dark_debug($db_details,'--------------$db_details-------------');
		extract($db_details);
		global $wpdb;
		$request_params['db_host'] = $db_host;
		$request_params['db_username'] = $db_username;
		$request_params['db_password'] = $db_password;
		$request_params['db_name'] = $db_name;
		$request_params['db_prefix'] = $db_prefix;
		$request_params['default_repo'] = $this->options->get_option('default_repo');

		$request_params['action'] = 'init_staging';
		$destination_bridge_url = $this->get_staging_details('ftp', 'destination_bridge_url');
		dark_debug($request_params,'--------------$request_params-------------');
		dark_debug($destination_bridge_url,'--------------$destination_bridge_url-------------');
		$raw_response = $this->config->doCall($destination_bridge_url, $request_params);
		// $response = $this->decode_response($raw_response);
		$response = $raw_response;
		dark_debug($response,'--------------$response-------------');
		if ($response == 1) {
			$this->options->set_option('staging_progress_status', 'bridge_downloaded_n_extracted');
			dark_debug(array(), '-----------successfully Db connected-------------');
		} else if(isset($response['error'])){
			$this->options->set_option('staging_last_error', $response['error']);
		} else {
			if (stripos($raw_response, "Access denied") !== false ) {
				$this->options->set_option('staging_last_error', 'Access denied. Please check your credentials and try again.');
				dark_debug(array(), '----------Access denied. Please check your credentials and try again.--------------');
			} else if(stripos($raw_response, "host") !== false ) {
					$this->options->set_option('staging_last_error', 'No such host is known. Please check host name and try again.');
				dark_debug(array(), '----------No such host is known. Please check host name and try again..--------------');
			} else {
				$this->options->set_option('staging_last_error', 'Could not create db connections');
				dark_debug(array(), '-----------Could create db connection-------------');
			}
		}
	}

	public function init_meta_import(){
		dark_debug('Function :','---------'.__FUNCTION__.'-----------------');
		$db_details = $this->get_staging_details('db');
		extract($db_details);
		$destination_bridge_url = $this->get_staging_details('ftp', 'destination_bridge_url');
		global $wpdb;
		$request_params['db_host'] = $db_host;
		$request_params['db_username'] = $db_user;
		$request_params['db_password'] = $db_password;
		$request_params['db_name'] = $db_name;
		// $request_params['db_prefix'] = $wpdb->base_prefix;
		$request_params['current_db_prefix'] = $wpdb->base_prefix;
		$request_params['new_db_prefix'] = $db_prefix;
		$request_params['action'] = 'import_meta_file';
		dark_debug($destination_bridge_url,'--------------$destination_bridge_url-------------');
		$raw_response = $this->config->doCall($destination_bridge_url, $request_params);
		dark_debug($raw_response,'--------------$raw_response-------------');
		$response = $this->decode_response($raw_response);
		if(isset($response['error'])){
			$this->options->set_option('staging_last_error', $response['error']);
		}
		dark_debug($response,'--------------$response-------------');
		if (empty($response)) {
			$this->options->set_option('staging_last_error',"Importing meta data on server failed");
			$this->logger->log("Importing meta data on server failed", 'staging_failed', $this->staging_id);
			$this->logger->log(serialize(htmlentities($raw_response)), 'staging_failed', $this->staging_id);
			return $this->options->set_option('staging_last_error', 'Importing meta data on server failed');
		} else if ($response['success'] == 'completed') {
			$this->logger->log("Importing meta data on server completed", 'staging_progress', $this->staging_id);
			return $this->options->set_option('staging_progress_status', 'meta_data_import_completed');
		} else if(isset($response['last_index'])){
			dark_debug($response['last_index'],'--------------meta_data_last_index-------------');
			return $this->options->set_option('staging_progress_status', 'meta_data_import_running');
		} else{
			$this->logger->log(serialize(htmlentities($raw_response)), 'staging_failed', $this->staging_id);
		}
	}

	private function decode_response($response){
		remove_response_junk($response);
		dark_debug($response,'--------------$response after junk-------------');
		if (empty($response)) {
			return false;
		}
		$decode_data = base64_decode($response);
		if (empty($decode_data)) {
			return false;
		}
		$unserialized_response = @unserialize($decode_data);
		if (empty($unserialized_response)) {
			return false;
		}
		return $unserialized_response;

	}

	public function get_internal_staging_details($param = null){
		$serialized_details = $this->options->get_option('same_server_staging_details');
		if (empty($serialized_details)) {
			return false;
		}

		$unserialized_details = unserialize($serialized_details);
		if (empty($unserialized_details)) {
			return false;
		}
		if (empty($param)) {
			return $unserialized_details;
		}
		return $unserialized_details[$param];
	}

	public function query_external_staging_details($type, $param){
		if ($type == 'ftp') {
			$staging_ftp_details_raw = $this->options->get_option('staging_ftp_details');
			if (empty($staging_ftp_details_raw)) {
				return false;
			}
			$staging_ftp_details = unserialize($staging_ftp_details_raw);
			if (empty($param)) {
				return $staging_ftp_details;
			}
			dark_debug($param,'--------------$param-------------');
			dark_debug($staging_ftp_details,'--------------$staging_ftp_details-------------');
			return $staging_ftp_details[$param];
		} else if($type == 'db'){
			$staging_db_details_raw = $this->options->get_option('staging_db_details');
			if (empty($staging_db_details_raw)) {
				return false;
			}
			$staging_db_details = unserialize($staging_db_details_raw);
			if (empty($param)) {
				return $staging_db_details;
			}
			dark_debug($param,'--------------$param-------------');
			dark_debug($staging_db_details,'--------------$staging_db_details-------------');
			return $staging_db_details[$param];
		}
	}

	public function get_staging_details($type, $param = null){
		if ($this->config->get_option('staging_type') === 'internal') {
			return $this->get_internal_staging_details($param);
		}
		return $this->query_external_staging_details($type, $param);
	}

	public function get_external_staging_details(){
		$staging_details_raw = $this->config->get_option('staging_details');
		if (!$staging_details_raw) {
			return false;
		}

		$staging_details = @unserialize($staging_details_raw);
		if (!$staging_details) {
			return false;
		}

		return $staging_details;
	}

	public function init_restore(){
		dark_debug('Function :','---------'.__FUNCTION__.'-----------------');
		global $wpdb;
		$db_details = $this->get_staging_details('db');
		extract($db_details);
		$destination_bridge_url = $this->get_staging_details('ftp', 'destination_bridge_url');
		dark_debug($destination_bridge_url,'--------------$destination_bridge_url-------------');
		$request_params['action'] = 'init_restore';
		$request_params['cur_res_b_id'] = $this->options->get_option('staging_backup_id');
		$request_params['current_db_prefix'] = $wpdb->base_prefix;
		$request_params['new_db_prefix'] = $db_prefix;
		$request_params['files_to_restore'] = '';
		dark_debug($request_params,'--------------init_restore here request_params-------------');
		$this->options->set_option('staging_call_waiting_for_response', true);
		$raw_response = $this->config->doCall($destination_bridge_url, $request_params);
		$this->options->set_option('staging_call_waiting_for_response', false);
		dark_debug($raw_response,'--------------$raw_response-------------');
		$result = json_decode(trim($raw_response), true);
		dark_debug($result,'--------------$result-------------');
		if (isset($result['restoreInitiatedResult'])) {
			dark_debug(array(), '-----------restoreInitiatedResult present-------------');
			$this->logger->log("Initializing restore on server completed", 'staging_progress', $this->staging_id);
			$this->options->set_option('staging_progress_status', 'init_restore_completed');
		} else{
			$this->logger->log("Initializing restore on server failed", 'staging_failed', $this->staging_id);
			$this->logger->log(serialize(htmlentities($raw_response)), 'staging_failed', $this->staging_id);
			$this->options->set_option('staging_last_error',"Initializing restore on server failed");
			dark_debug(array(), '-----------restoreInitiatedResult not present-------------');
		}
	}

	public function continue_restore($initialize = null){
		$db_details = $this->get_staging_details('db');
		extract($db_details);
		$destination_url = $this->get_staging_details('ftp', 'destination_url');
		$destination_url = $destination_url.self::BRIDGE_FOLDER.'/wptc-ajax.php';
		dark_debug($destination_url,'--------------$destination_url-------------');
		$request_params = array();
		if (!empty($initialize)) {
			$request_params['initialize'] = true;
			dark_debug(array(), '-----------First call-------------');
		} else {
			dark_debug(array(), '-----------not first call-------------');
		}
		$this->options->set_option('staging_call_waiting_for_response', true);
		$raw_response = $this->config->doCall($destination_url, $request_params);
		$this->options->set_option('staging_call_waiting_for_response', false);
		dark_debug($raw_response,'--------------$raw_response-------------');
		$status = stripos($raw_response, 'callAgain');
		if ($status === false) {
			$status = stripos($raw_response, 'over');
			if($status === false){
				dark_debug($status,'--------------$status-------------');
				dark_debug(array(), '-----------something went wrong-------------');
				$this->logger->log("Downloading files on server failed. ", 'staging_failed', $this->staging_id);
				$this->logger->log(serialize(htmlentities($raw_response)), 'staging_failed', $this->staging_id);
				$this->options->set_option('staging_last_error',"Downloading files on server failed.");
				// $this->options->set_option('staging_progress_status', 'continue_restore');
			} else {
				dark_debug(array(), '-----------over-------------');
				$this->options->set_option('staging_progress_status', 'continue_restore_completed');
				$this->logger->log("Downloading files on server completed.", 'staging_progress', $this->staging_id);
			}
		} else {
			// $this->continue_restore();
			$this->options->set_option('staging_progress_status', 'continue_restore_running');
			$this->logger->log("Downloading files on server paused.", 'staging_progress', $this->staging_id);
		}
	}

	public function init_copy($initialize = null){
		$db_details = $this->get_staging_details('db');
		extract($db_details);
		$destination_url = $this->get_staging_details('ftp', 'destination_url');
		$destination_url = $destination_url.self::BRIDGE_FOLDER.'/tc-init.php';
		dark_debug($destination_url,'--------------$destination_url-------------');
		$request_params = array();
		$request_params['action'] = 'staging_restore';
		if (!empty($initialize)) {
			$request_params['initialize'] = true;
			dark_debug(array(), '-----------First call-------------');
		} else {
			dark_debug(array(), '-----------not first call-------------');
		}
		$this->options->set_option('staging_call_waiting_for_response', true);

		$raw_response = $this->config->doCall($destination_url, $request_params);
		$this->options->set_option('staging_call_waiting_for_response', false);

		dark_debug($raw_response,'--------------$raw_response-------------');
		$status = stripos($raw_response, 'callAgain');
		if ($status === false) {
			$status = stripos($raw_response, 'over');
			if($status === false){
				dark_debug($status,'--------------$status-------------');
				dark_debug(array(), '-----------something went wrong-------------');
				$this->logger->log("Copy files to respective place on server failed ", 'staging_failed', $this->staging_id);
				$this->logger->log(serialize(htmlentities($raw_response)), 'staging_failed', $this->staging_id);
				$this->options->set_option('staging_last_error',"Copy files to respective place on server failed");
				// $this->options->set_option('staging_progress_status', 'continue_copy_f');
			} else {
				dark_debug(array(), '-----------over-------------');
				$this->options->set_option('staging_progress_status', 'continue_copy_completed');
				$this->logger->log("Copy files to respective place on server completed", 'staging_progress', $this->staging_id);
			}
		} else {
			// $this->init_copy();
			$this->options->set_option('staging_progress_status', 'continue_copy_running');
			$this->logger->log("Copy files to respective place on server paused", 'staging_progress', $this->staging_id);
		}
	}

	public function new_site_changes(){
		$db_details = $this->get_staging_details('db');
		extract($db_details);
		$destination_url_temp = $destination_url;
		$destination_url = $this->get_staging_details('ftp', 'destination_url');
		$destination_bridge_url = $this->get_staging_details('ftp', 'destination_bridge_url');
		$request_params = array();
		$request_params['action'] = 'new_site_changes';
		$request_params['db_host'] = $db_host;
		$request_params['db_username'] = $db_username;
		$request_params['db_password'] = $db_password;
		$request_params['db_name'] = $db_name;
		$request_params['db_prefix'] = $db_prefix;
		$request_params['destination_url'] = $destination_url;
		$raw_response = $this->config->doCall($destination_bridge_url, $request_params);
		dark_debug($raw_response,'--------------$raw_response-------------');
		$response = $this->decode_response($raw_response);
		if (isset($response['success'])) {
			$this->logger->log("New site changes on server completed", 'staging_progress', $this->staging_id);
			$this->options->set_option('staging_progress_status', 'new_site_changes_done');
		} else if(isset($response['error'])){
			$this->logger->log("New site changes on server failed", 'staging_failed', $this->staging_id);
			$this->logger->log(serialize(htmlentities($raw_response)), 'staging_failed', $this->staging_id);
			$this->options->set_option('staging_last_error', $response['error']);
		} else {
			$this->logger->log(serialize(htmlentities($raw_response)), 'staging_failed', $this->staging_id);
			$this->options->set_option('staging_last_error', "Replacing staging site meta failed.");
		}
	}

	public function init_staging_wptc_h($flag) {
		dark_debug($flag,'-------------- init_staging_wptc_h-------------');
		$app_id = $this->options->get_option('appID');
		if ($this->options->get_option('wptc_server_connected') && $this->options->get_option('wptc_service_request') == 'yes' && !empty($app_id)) {
			$email = trim($this->options->get_option('main_account_email', true));
			$emailhash = md5($email);
			$email_encoded = base64_encode($email);

			$pwd = trim($this->options->get_option('main_account_pwd', true));
			$pwd_encoded = base64_encode($pwd);

			$cron_type = ($flag) ? 'STAGING' : DEFAULT_CRON_TYPE_WPTC;

			$post_arr = array(
				'app_id' => $app_id,
				'email' => $email_encoded,
				'pwd' => $pwd_encoded,
				'cronType' => $cron_type,
			);

			dark_debug($post_arr, "--------post_string-init_staging_wptc_h-------");

			$push_result = do_cron_call_wptc('process-backup', $post_arr);
			dark_debug($push_result, "--------pushresult init_staging_wptc_h--------");

			process_cron_error_wptc($push_result);
			process_cron_backup_response_wptc($push_result);

		} else {
			$this->logger->log("Site not connected to node", 'staging_failed', $this->staging_id);
			$this->options->set_option('is_user_logged_in', false);
			$this->options->set_option('wptc_server_connected', false);
		}
	}

	public function is_staging_backup_wptc_h(){
		$is_staging_backup = $this->options->get_option('is_staging_backup', true);
		$is_staging_running = $this->options->get_option('is_staging_running', true);

		dark_debug($is_staging_backup,'--------------$is_staging_backup-------------');
		if (!empty($is_staging_backup) || !empty($is_staging_running)) {
			$this->options->set_option('staging_progress_status', 'ready_to_start');
			$this->options->set_option('is_staging_running', true);
			do_action('init_staging_wptc_h', time());
		}
		$this->options->set_option('is_staging_backup', false);
	}

	public function is_any_staging_process_going_on(){
		if (empty($this->options)) {
			$this->options = WPTC_Pro_Factory::get('Wptc_staging_Config');
		}
		if ($this->options->get_option('is_staging_running', true) || $this->options->get_option('same_server_staging_running', true) ) {
			return true;
		}
		return false;
	}

	public function complete_staging(){
		dark_debug(array(), '-----------completed staging-------------');
		$this->logger->log("Site staged successfully", 'staging_complete', $this->staging_id);
		$this->options->set_option('external_staging_requested', false);
		$this->clear_bridge_files();
		$this->options->flush();
		$this->upsert_staging_details();
		$this->init_staging_wptc_h(false);
		$this->staging_completed_email();
	}

	public function staging_completed_email($type = false){
		if ($type === 'internal') {
			$email_data = array(
				'type' => 'staging_completed',
				'staging_url' => $this->get_internal_staging_details('destination_url'),
			);
		} else {
			$email_data = array(
				'type' => 'staging_completed',
				'staging_url' => $this->get_staging_details('ftp', 'destination_url'),
			);
		}
		error_alert_wptc_server($email_data);
	}

	private function upsert_staging_details(){
		dark_debug(array(), '----------upsert_staging_details--------------');
		$old_staging_details_raw = $this->options->get_option('staging_details');
		if ($old_staging_details_raw) {
			$staging_details = unserialize($old_staging_details_raw);
			if (!empty($staging_details)) {
				$staging_details['total_staged_count'] = (int) $staging_details['total_staged_count'] + 1;
			} else {
				$staging_details['total_staged_count'] = 1;
			}
		} else {
			$staging_details['total_staged_count'] = 1;
		}
		$staging_details['completed_time'] = microtime(true);
		$staging_details['human_completed_time'] = user_formatted_time_wptc(microtime(true));
		$staging_details['destination_url'] = $this->get_staging_details('ftp', 'destination_url');
		dark_debug($staging_details,'-------------$staging_details in upsert--------------');
		$this->options->set_option('staging_details', serialize($staging_details));
		$this->options->set_option('staging_completed', true);
	}

	// public function check_wptc_staging_status(){
	// 	$staging_last_error = $this->options->get_option('staging_last_error');
	// 	if ($staging_last_error) {
	// 		die_with_json_encode(
	// 				array(
	// 					'error' => $staging_last_error,
	// 					'percentage' => empty($percentage) ? 0 : $percentage,
	// 				)
	// 			);
	// 	}

	// 	$staging_details = $this->options->get_option('staging_details');
	// 	$staging_progress_status = $this->options->get_option('staging_progress_status');
	// 	$is_staging_backup = $this->options->get_option('is_staging_backup');
	// 	if (is_any_ongoing_wptc_backup_process()) {
	// 		if ($is_staging_backup) {
	// 			die_with_json_encode( array( 'status' => 'backup_progress'));
	// 		} else {
	// 			if ($staging_details) {
	// 				die_with_json_encode( array( 'status' => 'completed' ) , 1);
	// 			} else {
	// 				die_with_json_encode( array( 'status' => 'not_started' ));
	// 			}
	// 		}
	// 	} else if($this->is_any_staging_process_going_on()) {
	// 		die_with_json_encode( array( 'status' => 'progress', 'current_status' => $staging_progress_status ));
	// 	} else {
	// 		if ($staging_details) {
	// 			die_with_json_encode( array( 'status' => 'completed' ) , 1);
	// 		} else {
	// 			die_with_json_encode( array( 'status' => 'not_started' ));
	// 		}
	// 	}
	// }

	public function delete_staging_wptc(){
		$this->init_file_system();
		if ($this->config->get_option('staging_type') === 'internal') {
			$this->delete_internal_staging();
		} else {
			$this->delete_external_staging();
		}
	}

	private function delete_internal_staging($dont_print = false, $hard_delete = false){
		$db = $this->delete_internal_staging_db($hard_delete);
		$files = $this->delete_internal_staging_files($hard_delete);
		$flags = $this->delete_internal_staging_flags();
		dark_debug($db, '---------$db------------');
		dark_debug($files, '---------$files------------');
		if ($dont_print) {
			return false;
		}
		if ($db && $files) {
			die_with_json_encode(array('status' => 'success', 'deleted' => 'both'));
		} else if ($db) {
			die_with_json_encode(array('status' => 'success', 'deleted' => 'db'));
		} else if($files){
			die_with_json_encode(array('status' => 'success', 'deleted' => 'files'));
		} else {
			die_with_json_encode(array('status' => 'error', 'deleted' => 'none'));
		}
	}

	private function delete_internal_staging_flags(){
		$this->options->set_option('staging_type', false);
		$this->options->set_option('same_server_staging_details', false);
		$this->options->set_option('same_server_staging_status', false);
		$this->options->set_option('is_staging_completed', false);
		$this->options->set_option('staging_id', false);
		$this->hard_reset_staging_flags();
	}

	private function delete_external_staging(){
		$this->options->set_option('staging_completed', false);
		$this->common_check_and_bridge_upload();
		$this->delete_staging_do_call();
		$this->hard_reset_staging_flags();
	}

	private function hard_reset_staging_flags(){
		$this->options->set_option('staging_details', false);
		$this->options->set_option('cpanel_crentials', false);
		$this->options->set_option('staging_ftp_details', false);
		$this->options->set_option('staging_db_details', false);
		$this->options->set_option('staging_type', false);
		$this->options->set_option('is_staging_completed', false);
		$this->options->set_option('staging_completed', false);
		$this->options->set_option('staging_id', false);
	}

	public function get_stored_ftp_details_wptc($remove_err = null){
		// $staging_creds['db'] = $this->get_staging_details('db');
		$staging_ftp_details = $this->get_staging_details('ftp');
		if ($remove_err) {
			dark_debug(array(), '---------Removed------------');
			$this->options->set_option('staging_last_error', false);
			$this->options->flush();
		}
		die(json_encode($staging_ftp_details));
	}

	public function clear_staging_flags_wptc($not_force = 0){
		if (!empty($not_force)) {
			return false;
		}
		$this->options->flush();
		$this->init_staging_wptc_h(false);
		if ($this->config->get_option('staging_type') === 'internal') {
			$this->same_staging_stop_process();
		} else {
			$this->clear_bridge_files();
		}
	}

	private function same_staging_stop_process(){
		$this->delete_internal_staging(false, $hard_delete = 1);
		if($this->options->get_option('same_server_staging_details')){
				$this->options->set_option('same_server_staging_status', 'staging_completed');
		} else {
			dark_debug(array(), '---------------else stop process-----------------');
			$this->options->set_option('same_server_staging_status', false);
		}
	}

	public function add_staging_req_h($type){
		$this->options->set_option('external_staging_requested', true);
		dark_debug(array(), '-----------add_staging_req_h----------');
		$this->options->set_option('staging_completed', false);
		if ($type == 'incremental') {
			$this->common_check_and_bridge_upload();
		}
		$this->options->set_option('is_staging_backup', true);
	}

	public function clear_bridge_files(){
		$destination_bridge_url = $this->get_staging_details('ftp', 'destination_bridge_url');
		$db_details = $this->get_staging_details('db');
		extract($db_details);
		$request_params = array();
		$request_params['action'] = 'clear_bridge_files';
		$request_params['db_host'] = $db_host;
		$request_params['db_username'] = $db_username;
		$request_params['db_password'] = $db_password;
		$request_params['db_name'] = $db_name;
		$request_params['db_prefix'] = $db_prefix;
		$raw_response = $this->config->doCall($destination_bridge_url, $request_params);
		// dark_debug($raw_response, '---------clear_bridge_files response------------');
	}

	public function get_staging_url_wptc(){
		dark_debug(array(), '-----------get_staging_url_wptc----------');
		if ($this->config->get_option('staging_type') === 'internal') {
			$destination_url = $this->same_server_staging_url();
		} else {
			$destination_url = $this->get_staging_details('ftp', 'destination_url');
		}
		die_with_json_encode(array('destination_url' => $destination_url), 1);
	}

	private function delete_staging_do_call(){
		$db_details = $this->get_staging_details('db');
		dark_debug($db_details,'--------------$db_details-------------');
		$destination_bridge_url = $this->get_staging_details('ftp', 'destination_bridge_url');
		$this->hard_reset_staging_flags();
		$this->options->flush();
		extract($db_details);
		global $wpdb;
		$request_params = array();
		$request_params['action'] = 'delete_staging';
		$request_params['db_host'] = $db_host;
		$request_params['db_username'] = $db_username;
		$request_params['db_password'] = $db_password;
		$request_params['db_name'] = $db_name;
		$request_params['db_prefix'] = $db_prefix;
		dark_debug($request_params,'--------------$request_params-------------');
		$raw_response = $this->config->doCall($destination_bridge_url, $request_params);
		dark_debug($raw_response,'--------------$raw_response-------------');
		$db_deleted = false;
		$files_deleted = false;
		if (stripos($raw_response, 'database_deleted_wptc') !== false) {
			$db_deleted = true;
		}
		if (stripos($raw_response, 'files_deleted_wptc') !== false) {
			$files_deleted = true;
		}
		if ($db_deleted && $files_deleted) {
			die_with_json_encode(array('status' => 'success', 'deleted' => 'both'));
		} else if ($db_deleted) {
			die_with_json_encode(array('status' => 'success', 'deleted' => 'db'));
		} else if ($files_deleted) {
			die_with_json_encode(array('status' => 'success', 'deleted' => 'files'));
		} else {
			die_with_json_encode(array('status' => 'error', 'deleted' => 'none'));
		}
	}

	public function save_stage_n_update($data) {
		dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);
		$update_items = $data['update_items'];
		$type = $data['type'];
		if ($type == 'plugin') {
			$upgrade_details = purify_plugin_update_data_wptc($update_items);
		} else if ($type == 'theme') {
			$upgrade_details = purify_theme_update_data_wptc($update_items);
		} else if ($type == 'core') {
			if (!$is_auto_update) {
				$upgrade_details = purify_core_update_data_wptc($update_items);
			} else {
				$upgrade_details = $update_items;
			}
		} else if ($type == 'translation') {
			$upgrade_details = purify_translation_update_data_wptc($update_items);
		}

		$this->update_formated_stage_n_update_details($type, $upgrade_details);
	}

	private function update_formated_stage_n_update_details($update_ptc_type, $upgrade_details) {
		dark_debug($upgrade_details, '---------upgrade_details-------------');

		$upgrade_details_data['update_items'] = $upgrade_details;
		$upgrade_details_data['updates_type'] = $update_ptc_type;
		dark_debug($upgrade_details_data, '--------update_formated_stage_n_update_details-----------');
		if (!$upgrade_details_data) {
			return false;
		}
		$this->config->set_option('stage_n_update_details', serialize($upgrade_details_data));
	}

	public function same_server_test($path) {
		$this->init_file_system();
		dark_debug($path, '---------path-------------');
		$this->delete_internal_staging($dont_print = true);
		$this->options->flush();
		$this->processed_db->truncate();
		$this->init_staging_id();
		$this->options->set_option('staging_type', 'internal');
		$this->same_server_copy_bridge_files($path);
	}

	public function copy_same_server_test() {
		$this->init_file_system();
		$this->options->set_option('staging_type', 'internal');
		$this->options->flush();
		$this->processed_db->truncate();
		$this->init_staging_id();
		$this->options->set_option('same_server_copy_staging', true);
		$this->same_server_copy_bridge_files();
	}

	private function same_server_process_staging_req(){
		$this->init_file_system();
		$this->same_server_set_staging_path();
		$status = $this->options->get_option('same_server_staging_status');
		dark_debug($status, '---------$status------------');
		if ($status === 'test_bridge_over' || $status === 'db_clone_progress') {
			if ($status === 'test_bridge_over') {
				$this->logger->log("Starting to clone tables.", 'staging_progress', $this->staging_id);
			}
			$this->do_copy_staging_actions();
			$this->same_server_update_keys('same_server_staging_status', 'db_clone_progress');
			$this->same_server_clone_db();
			$this->same_server_update_keys('same_server_staging_status', 'db_clone_over');
			$this->logger->log("DB has been cloned successfully", 'staging_progress', $this->staging_id);
			$status = 'db_clone_over';
			if(is_wptc_timeout_cut()){
				send_response_wptc('db_clone_over', array());
			}
		}

		if($status === 'db_clone_over' || $status === 'copy_files_progress'){
			if ($status === 'db_clone_over') {
				$this->logger->log("Copying Files is started.", 'staging_progress', $this->staging_id);
			}
			$this->same_server_update_keys('same_server_staging_status', 'copy_files_progress');
			$iter_count = $this->same_server_copy(WPTC_ABSPATH , $this->same_staging_folder);
			$this->same_server_update_keys('same_server_staging_status', 'copy_files_over');
			$status = 'copy_files_over';
			$this->logger->log("All files are copied to staging location succesfully.", 'staging_progress', $this->staging_id);
			send_response_wptc('same_server_copy_over', array('same_server' => array('iter_count' => $iter_count)));
		}

		if($status === 'copy_files_over'){
			$this->same_server_replace_links();
			$this->same_server_update_keys('same_server_staging_status', 'replace_links_over');
			$this->logger->log("Replaced links in the staging site successfully", 'staging_progress', $this->staging_id);
			$status = 'replace_links_over';
		}

		if($status === 'replace_links_over'){
			$this->rewrite_permalink_structure();
			$this->update_in_staging('internal');
			$this->internal_staging_delete_bridge();
			$this->processed_db->truncate();
			$this->logger->log("Site staged succesfully.", 'staging_progress', $this->staging_id);
			$this->same_server_update_keys('same_server_staging_status', 'staging_completed');
			$staging_path = $this->options->get_option('same_server_staging_path');
			$db_prefix = $this->unique_prefix_gen();
			$this->options->set_option('same_server_staging_details', serialize(array('destination_url' => get_home_url().'/'.$staging_path.'/', 'human_completed_time' => user_formatted_time_wptc(time()), 'timestamp' => time(), 'db_prefix' => $db_prefix.'_', 'staging_folder' => $staging_path)));
			$this->options->flush();
		}
		$this->init_staging_wptc_h(false);
		$this->staging_completed_email($type = 'internal');
		send_response_wptc('staging_completed', array());
	}

	private function do_copy_staging_actions(){
		if(!$this->options->get_option('same_server_copy_staging')){
			return false;
		}
		$this->delete_internal_staging_db();
	}

	private function same_server_update_keys($key, $value){
		if(!$this->is_same_server_running()){
			return false;
		}
		$this->options->set_option($key, $value);
	}

	private function is_same_server_running(){
		return $this->options->get_option('same_server_staging_running', true);
	}

	public function get_external_staging_db_details(){
		die_with_json_encode($this->get_staging_details('db'));
	}

	public function delete_internal_staging_db($hard_delete = false){
		$db_prefix = $this->get_internal_staging_details('db_prefix');
		if (empty($db_prefix)) {
			if ($hard_delete) {
				$db_prefix = $this->options->get_option('same_server_staging_db_prefix');
				global $prev_staging_path;
				$prev_staging_path = $this->options->get_option('same_server_staging_path');
			} else {
				return false;
			}
		}
		dark_debug($db_prefix, '---------------$db_prefix-----------------');
		return $this->processed_files->drop_tables_with_prefix($db_prefix);
	}

	public function delete_internal_staging_files($hard_delete = false){
		$staging_folder = $this->get_internal_staging_details('staging_folder');
		if (empty($staging_folder)) {
			global $prev_staging_path;
			$staging_folder = $prev_staging_path;
			dark_debug($staging_folder, '---------------$staging_folder global-----------------');
			if (empty($staging_folder)) {
				return false;
			}
		}
		$staging_path = get_home_path_wptc() . $staging_folder . '/';
		$staging_path = wp_normalize_path($staging_path);
		if (trailingslashit(wp_normalize_path(get_home_path_wptc())) == trailingslashit($staging_path) || trailingslashit(WPTC_ABSPATH) == trailingslashit($staging_path)) { //check its live site before deleting it
			return false;
		}
		dark_debug($staging_path, '---------$staging_path------------');
		return $this->filesystem->delete($staging_path, true);
	}

	public function get_update_in_staging(){
		$raw_upgrade_details = $this->options->get_option('stage_n_update_details');
		$this->options->set_option('stage_n_update_details', false);
		dark_debug($raw_upgrade_details, '---------$raw_upgrade_details------------');
		if (empty($raw_upgrade_details)) {
			dark_debug(array(), '---------No data update_in_staging------------');
			$this->logger->log("No update requests for staging", 'staging_progress', $this->staging_id);
			return false;
		}
		$upgrade_details = unserialize($raw_upgrade_details);
		dark_debug($upgrade_details, '---------$upgrade_details------------');
		if (empty($upgrade_details) || !is_array($upgrade_details)) {
			$this->logger->log("Update request data is corrupted so skipped updates in the staging", 'staging_progress', $this->staging_id);
			dark_debug(array(), '---------corrupted data update_in_staging-----------');
			return false;
		}

		$type_of_update = $upgrade_details['updates_type'];
		$update_items = $upgrade_details['update_items'];
		if (empty($type_of_update) || empty($update_items)) {
			if($type_of_update != 'translation'){
				$this->logger->log("Update request data is corrupted so skipped updates in the staging", 'staging_progress', $this->staging_id);
				dark_debug(array(), '---------corrupted data update_in_staging-----------');
				return false;
			}
		}
		return array('type' => $type_of_update, 'update_items' => $update_items);
	}

	public function update_in_staging($staging_type = false){
		$update_details = $this->get_update_in_staging();
		if($update_details === false){
			$this->options->set_option('staging_progress_status', 'staging_completed');
			return false;
		}

		if(is_wptc_timeout_cut(false, 10)){
			send_response_wptc('replace_links_over', array());
		}

		$request_params = array();
		$request_params['action'] = 'update_in_staging';
		$request_params['type'] = $type_of_update =$update_details['type'];
		$request_params['update_items'] = $update_details['update_items'];

		dark_debug($request_params, '---------$request_params------------');
		if ($staging_type === 'internal') {
			$destination_bridge_url = $this->same_server_staging_bridge_url();
		} else {
			$destination_bridge_url = $this->get_staging_details('ftp', 'destination_bridge_url');
		}
		dark_debug($destination_bridge_url, '---------$destination_bridge_url------------');
		$raw_response = $this->config->doCall($destination_bridge_url, $request_params);
		dark_debug($raw_response, '---------update_in_staging response------------');
		$response = $this->decode_response($raw_response);
		dark_debug($response, '---------$response------------');
		$this->options->set_option('staging_progress_status', 'staging_completed');

		if (empty($response)) {
			return $this->logger->log("Update failed in the staging.", 'staging_progress', $this->staging_id);

		} else if(isset($response['error'])){
			return $this->logger->log("Update failed - ". $response['error'], 'staging_progress', $this->staging_id);
		}

		if ($type_of_update == 'plugin') {
			if (!isset($response['upgraded'])) {
				return $this->logger->log("Updating plugin in the staging failed - response corrupted", 'staging_progress', $this->staging_id);
			}
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once WPTC_ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugins_data = get_plugins();
			$plugins_count = $plugins_success = $plugins_failure = 0;
			foreach ($response['upgraded'] as $key => $value) {
				$plugins_count++;
				if ($value === 1) {
					$plugins_success++;
					$this->logger->log("Plugin ".$plugins_data[$key]['Name']. " updated successfully", 'staging_progress', $this->staging_id);
					$this->config->set_option('bbu_note_view', serialize(array('type' => 'success', 'note' => $plugins_data[$key]['Name'].' updated successfully in the staging site. :)')));
				} else if($value['error']){
					$plugins_failure++;
					$this->logger->log("Plugin ".$plugins_data[$key]['Name'] . ' update failed - '.$value['error'], 'staging_progress', $this->staging_id);
					$this->config->set_option('bbu_note_view', serialize(array('type' => 'error', 'note' => $plugins_data[$key]['Name'].' update failed in the staging site.')));
				} else if($value['error_code']){
					$this->logger->log("Plugin ".$plugins_data[$key]['Name'] . ' update failed - '.$value['error_code'], 'staging_progress', $backup_id);
					$this->config->set_option('bbu_note_view', serialize(array('type' => 'error', 'note' => $plugins_data[$key]['Name'].' update failed in the staging site.')));
					$plugins_failure++;
				}
			}
			if ($plugins_count > 1) {
				if ($plugins_success === $plugins_count) {
					$this->config->set_option('bbu_note_view', serialize(array('type' => 'success', 'note' => $plugins_count.' plugins updated successfully in the staging site :)')));
				} else if ($plugins_failure === $plugins_count) {
					$this->config->set_option('bbu_note_view', serialize(array('type' => 'error', 'note' => $plugins_count.' plugin updates failed in the staging site.')));
				} else {
					$this->config->set_option('bbu_note_view', serialize(array('type' => 'warning', 'note' => $plugins_success.' plugin updated successfully and '.$plugins_failure.' plugin updates failed in the staging site.')));
				}
			}
		} else if($type_of_update == 'theme'){
			if (!isset($response['upgraded'])) {
				return $this->logger->log("Updating theme in the staging failed - response corrupted", 'staging_progress', $this->staging_id);
			}
			$themes_count = $themes_success = $themes_failure = 0;
			foreach ($response['upgraded'] as $key => $value) {
				$theme_info = wp_get_theme($key);
				if (!empty($theme_info)) {
					$theme_name = $theme_info->get( 'Name' );
				}
				$themes_count++;
				if ($value === 1) {
					$themes_success++;
					$this->logger->log("Theme " . $theme_name ." updated successfully.", 'staging_progress', $this->staging_id);
					$this->config->set_option('bbu_note_view', serialize(array('type' => 'success', 'note' => $theme_name .' updated successfully in the staging site :)')));
				} else if($value['error']){
					$themes_failure++;
					$this->logger->log("Theme " . $theme_name . ' update failed - '.$value['error'], 'staging_progress', $this->staging_id);
					$this->config->set_option('bbu_note_view', serialize(array('type' => 'error', 'note' => $theme_name .' update failed in the staging site.')));
				} else if($value['error_code']){
					$this->logger->log("Theme " . $theme_name . ' update failed - '.$value['error_code'], 'staging_progress', $backup_id);
					$this->config->set_option('bbu_note_view', serialize(array('type' => 'error', 'note' => $theme_name .' update failed in the staging site.')));
					$themes_failure++;
				}
			}
			if ($themes_count > 1) {
				if ($themes_success === $themes_count) {
					$this->config->set_option('bbu_note_view', serialize(array('type' => 'success', 'note' => $themes_count.' themes updated successfully in the staging site :)')));
				} else if ($themes_failure === $themes_count) {
					$this->config->set_option('bbu_note_view', serialize(array('type' => 'error', 'note' => $themes_count.' theme updates failed in the staging site.')));
				} else {
					$this->config->set_option('bbu_note_view', serialize(array('type' => 'warning', 'note' => $themes_success.' theme updated successfully and '.$themes_failure.' theme updates failed in the staging site.')));
				}
			}
		}else if ($type_of_update == 'core') {
			if (!isset($response['upgraded'])) {
				$this->config->set_option('bbu_note_view', serialize(array('type' => 'error', 'note' => 'Latest version of WordPress update failed in the staging site.')));
				return $this->logger->log("Updating wordpress in the staging failed - response corrupted", 'staging_progress', $this->staging_id);
			}
			$this->logger->log("Wordpress updated successfully", 'staging_progress', $this->staging_id);
			$this->config->set_option('bbu_note_view', serialize(array('type' => 'success', 'note' => 'Latest version of WordPress updated successfully in the staging site :)')));
		} else if ($type_of_update == 'translation') {
			if (!isset($response['upgraded'])) {
			$this->config->set_option('bbu_note_view', serialize(array('type' => 'error', 'note' => 'Translation updates failed in the staging site')));
				return $this->logger->log("Updating translation in the staging failed - response corrupted", 'staging_progress', $this->staging_id);
			}
			$this->logger->log("Translations updated successfully", 'staging_progress', $this->staging_id);
			$this->config->set_option('bbu_note_view', serialize(array('type' => 'success', 'note' => 'Translations updated successfully in the staging site :)')));
		}
	}

	private function rewrite_permalink_structure(){

		$request_params = array();
		$request_params['action'] = 'rewrite_permalink_structure';

		$destination_bridge_url = $this->same_server_staging_bridge_url();

		dark_debug($destination_bridge_url, '---------$destination_bridge_url------------');

		$raw_response = $this->config->doCall($destination_bridge_url, $request_params);
		dark_debug($raw_response, '--------rewrite_permalink_structure------------');
		$response = $this->decode_response($raw_response);
		dark_debug($response, '---------------$response-----------------');
		if (empty($response)) {
			return $this->logger->log("rewrite permalink structure failed.", 'staging_progress', $this->staging_id);
		} else if($response['status'] == 'success'){
			return $this->logger->log("rewrite permalink structure successfully done.", 'staging_progress', $this->staging_id);
		}

	}

	private function same_server_copy_bridge_files($path = false){
		if ($path === false) {
			$path = $this->get_internal_staging_details('staging_folder');
		}
		dark_debug($path, '---------$path same_server_copy_bridge_files------------');
		$path = wp_normalize_path($path);
		$this->same_server_set_staging_path($path);
		$this->options->set_option('same_server_staging_status', 'test_bridge');
		$this->same_server_mkdir($this->same_staging_bridge_dir);
		if ($this->check_folder_exist($this->same_staging_bridge_dir) !== true) {
			$this->options->set_option('same_server_staging_status', 'test_bridge_folder_create_failed');
			$this->logger->log("Testing connection by uploading bridge file is failed", 'staging_failed', $this->staging_id);
			die_with_json_encode(array('status' => 'error' , 'message' => 'Cannot create staging folder.'));
		}

		$cp_result = $this->filesystem->copy($this->plugin_bridge_path . 'bridge.php', $this->same_staging_bridge_dir . 'bridge.php', true, FS_CHMOD_FILE);
		if(!$cp_result){
			$this->options->set_option('same_server_staging_status', 'test_bridge_file_copy_failed');
			$this->logger->log("Testing connection by uploading bridge file is failed", 'staging_failed', $this->staging_id);
			die_with_json_encode(array('status' => 'error' , 'message' => 'Cannot create copy bridge file.'));
		}
		$this->logger->log("Tested connection succesfully.", 'staging_progress', $this->staging_id);
		$this->init_staging_wptc_h(true);
		$this->options->set_option('same_server_staging_status', 'test_bridge_over');
		$this->options->set_option('same_server_staging_path', $path);
		$this->options->set_option('same_server_staging_running', true);
		$this->options->set_option('same_server_copy_files_count', 0);
		die_with_json_encode(array('status' => 'success' , 'message' => 'Test bridge copied.'));
	}

	private function check_folder_exist($dir){
		if (is_dir($dir)) {
			return true;
		}

		return false;
	}

	private function same_server_copy($from, $to){
		$file_obj = get_single_iterator_obj($from);
		$file_list = WPTC_Factory::get('fileList');
		$start_index = $this->options->get_option('same_server_copy_files_count');
		$start_index = empty($start_index) ? 0 : $start_index;
		dark_debug($start_index, '---------$start_index------------');
		$iter_count = 0;
		$check_time_count = 0;
		$check_time_limit = $this->options->get_option('internal_staging_file_copy_limit');
		if (empty($check_time_limit)) {
			$check_time_limit = 500; //fallback to default value
		}
		foreach ($file_obj as $file) {
			$current_pathname = $file->getPathname();
			$current_pathname = wp_normalize_path($current_pathname);
			$basename = basename($current_pathname);
			if ($basename == '.' || $basename == '..'){
				continue;
			}
			if (stripos($current_pathname , $this->same_staging_folder) !== false || is_wptc_file($current_pathname)) {
				continue;
			}
			if ($iter_count++ < $start_index ) {
				continue;
			}
			if ($this->exclude_class_obj->is_excluded_file($current_pathname)) {
				dark_debug($current_pathname, '---------------$current_pathname Exlcluded-----------------');
				continue;
			}
			if(!$file->isReadable()){
				continue;
			}

			$check_time_count++;
			$staging_pathname = $this->same_server_replace_pathname($current_pathname);
			if ($file->isFile()){
				$size = $file->getSize();
				if ($size > $this->ss_large_file_buffer_size) {
					$copy_status = $this->same_server_copy_large_file($current_pathname, $staging_pathname);
					if (!$copy_status) {
						$this->logger->log('Could not copy this file - '.$current_pathname, 'staging_progress', $this->staging_id);
					}
					if(is_wptc_timeout_cut()){
						$this->same_server_update_keys('same_server_copy_files_count', $iter_count);
						dark_debug($iter_count, '---------$iter_count------------');
						dark_debug(array(), '---------Time out------------');
						$this->logger->log($iter_count . ' files copied', 'staging_progress', $this->staging_id);
						send_response_wptc('same_server_copy_progress', array('same_server' => array('iter_count' => $iter_count)));
					}
				} else {
					$result = $this->filesystem->copy($current_pathname, $staging_pathname, true, FS_CHMOD_FILE);
					if (!$result) {
						$this->logger->log('Could not copy this file - '.$current_pathname, 'staging_progress', $this->staging_id);
					}

				}
				// dark_debug($result, '---------$result file------------');
			} else {
				if (($current_pathname .'/') !== $this->same_staging_folder) {
					$this->same_server_mkdir($staging_pathname);
					$result = $this->check_folder_exist($staging_pathname);
					if (!$result) {
						$this->logger->log('Could not create folder - '.$staging_pathname, 'staging_progress', $this->staging_id);
					}
				}
				// dark_debug($result, '---------$result folder------------');
			}
			if($check_time_count >= $check_time_limit){
				$check_time_count = 0;
				$this->same_server_update_keys('same_server_copy_files_count', $iter_count);
				$this->logger->log($iter_count . ' files copied', 'staging_progress', $this->staging_id);
				if(is_wptc_timeout_cut()){
					dark_debug($iter_count, '---------$iter_count------------');
					dark_debug(array(), '---------Time out------------');
					send_response_wptc('same_server_copy_progress', array('same_server' => array('iter_count' => $iter_count)));
				}
			}
		}
		$this->logger->log($iter_count . ' files copied', 'staging_progress', $this->staging_id);
		dark_debug($result, '---------$result------------');
		return $iter_count;
	}

	private function same_server_copy_large_file($src, $dst) {
		$src = fopen($src, 'r');
		$dest = fopen($dst, 'w');

		// Try first method:
		while (! feof($src)){
			if (false === fwrite($dest, fread($src, $this->ss_large_file_buffer_size))){
				$error = true;
			}
		}
		// Try second method if first one failed
		if (isset($error) && ($error === true)){
			while(!feof($src)){
				stream_copy_to_stream($src, $dest, 1024 );
			}
			fclose($src);
			fclose($dest);
			return true;
		}
		fclose($src);
		fclose($dest);
		return true;
	}

	private function internal_staging_delete_bridge(){
		dark_debug('Function :','---------'.__FUNCTION__.'-----------------');
		dark_debug($this->same_staging_bridge_dir, '------$this->same_staging_bridge_dir---------------');
		return $this->filesystem->delete($this->same_staging_bridge_dir, true);
	}

	private function same_server_add_completed_table(){
		$completed_tables = $this->options->get_option('same_server_clone_db_completed_tables');
		if (empty($completed_tables)) {
			return $this->options->set_option('same_server_clone_db_completed_tables', 1);
		}
		return $this->options->set_option('same_server_clone_db_completed_tables', $completed_tables + 1);
	}

	private function same_server_clone_db() {
		global $wpdb;
		$limit = $this->options->get_option('internal_staging_db_rows_copy_limit');
		if (empty($limit)) {
			$limit = 1000; //fallback to default value
		}
		$wp_tables = $this->processed_files->get_all_tables();
		$this->options->set_option('same_server_clone_db_total_tables', count($wp_tables));
		dark_debug($wp_tables, '---------$wp_tables------------');
		foreach ($wp_tables as $table) {
			if (!$this->is_same_server_running()) {
				send_response_wptc('Staging stopped manually');
			}
			dark_debug($table, '---------$table------------');
			$unique_prefix = $this->unique_prefix_gen();
			$new_table =  $unique_prefix . '_' . $table;
			dark_debug($new_table, '---------$new_table------------');
			$unique_prefix = (string) $unique_prefix;

			if ($this->exclude_class_obj->is_excluded_table($table) || stripos($table, $unique_prefix) !== false) {
				dark_debug($table, '---------------$table excluded from staging-----------------');
				$this->same_server_add_completed_table();
				continue;
			}

			dark_debug($new_table, '---------$new_table------------');
			if (!$this->processed_db->is_complete($table)) {
				if (is_wptc_table($table)) {
					$this->processed_db->update_table($table, -1); //Done
					dark_debug(array(), '---------Done already so skipping------------');
					$this->same_server_add_completed_table();
					continue;
				}

				$table_data = $this->processed_db->get_table($table);
				if ($table_data) {
					$offset = $table_data->count;
					$is_new = false;
				} else {
					$offset = 0;
					$is_new = true;
				}
			} else {
				dark_debug(array(), '--------skiipped---------');
				$this->same_server_add_completed_table();
				continue;
			}
			if ($is_new) {
				$existing_table = $wpdb->get_var(
					$wpdb->prepare(
						'show tables like %s',
						$new_table
					)
				);
				if ($existing_table == $new_table){
					$wpdb->query("drop table $new_table");
				}
				$is_cloned = $wpdb->query("create table `$new_table` like `$table`");
				if (!$is_cloned) {
					$this->logger->log('Creating table ' . $table . ' has been failed', 'staging_failed', $this->staging_id);
					dark_debug('Creating table ' . $table . ' has been failed.', '--------Failed-------------');
				} else {
					$this->logger->log("Created table " . $table, 'staging_progress', $this->staging_id);
				}
			}
			while(true){
				$inserted_rows = 0;
				dark_debug("insert `$new_table` select * from `$table` limit $offset, $limit", '---------sql------------');
				$inserted_rows = $wpdb->query(
					"insert `$new_table` select * from `$table` limit $offset, $limit"
				);
				dark_debug($inserted_rows, '---------$inserted_rows------------');
				if ($inserted_rows !== false) {
					if ($offset != 0) {
						$this->logger->log('Copy database table: ' . $table . ' DB rows: ' . $offset , 'staging_progress', $this->staging_id);
					}
					$offset = $offset + $inserted_rows;
					if ($inserted_rows < $limit) {
						$this->processed_db->update_table($table, -1); //Done
						break;
					}
					if(is_wptc_timeout_cut()){
						$this->processed_db->update_table($table, $offset);
						send_response_wptc('same_server_db_clone_progress', array('same_server' => array('table' => $$table, 'offset' => $offset)));
					}
				} else {
					$this->processed_db->update_table($table, -1); //Done
					break;
					$this->logger->log('Error: '.$wpdb->error.'inserting rows failed! Rows will be skipped. Offset: ' . $offset, 'staging_progress', $this->staging_id);
					dark_debug('Error: '.$wpdb->error.'Table ' . $new_table . ' has been created, but inserting rows failed! Rows will be skipped. Offset: ' . $offset , '--------Failed-------------');
				}
			}
			$this->same_server_add_completed_table();
		}
	}

	public function same_server_replace_links() {
		global $wpdb;
		$same_server_replace_old_url = $this->options->get_option('same_server_replace_old_url');
		dark_debug($same_server_replace_old_url , '-------------$same_server_replace_old_url -------------------');
		if(empty($same_server_replace_old_url)){
			$this->replace_old_url();
		}
		$this->create_default_htaccess();
		$this->logger->log('.htaccess has been modified.' . $error, 'staging_progress', $this->staging_id);

		$new_prefix = $this->unique_prefix_gen('full_prefix');

		//discourage indexing
		$this->create_robot_txt();
		$this->discourage_search_engine($new_prefix, $reset_permalink = true);
		$this->logger->log('Enabled discouraging search engines from indexing', 'staging_progress', $this->staging_id);

		$result = $wpdb->query(
			$wpdb->prepare(
				'update ' . $new_prefix . 'options set option_value = %s where option_name = \'siteurl\' or option_name = \'home\'',
				$this->same_server_staging_url()
			)
		);
		if (!$result) {
			$error = isset($wpdb->error) ? $wpdb->error : '';
			$this->logger->log('Replacing site url has been failed.' . $error, 'staging_progress', $this->staging_id);
			dark_debug('Replacing site url has been failed. ' . $error, '--------FAILED----------');
		} else {
			$this->logger->log('Replacing siteurl has been done succesfully', 'staging_progress', $this->staging_id);
			dark_debug('Replacing siteurl has been done succesfully', '--------SUCCESS----------');
		}

		//Update rewrite_rules in clone options table
		$result = $wpdb->query(
			$wpdb->prepare(
				'update ' . $new_prefix . 'options set option_value = %s where option_name = \'rewrite_rules\'',
				''
			)
		);

		if (!$result) {
			dark_debug("Updating option[rewrite_rules] not successfull, likely the main site is not using permalinks", '--------FAILED-------------');
		} else {
			dark_debug("Updating option [rewrite_rules] has been done succesfully", '--------SUCCESS-------------');
		}

		$result = $wpdb->query(
			$wpdb->prepare(
				"update  ". $new_prefix . "options set option_name = '" . $new_prefix . "user_roles' where option_name = '".$wpdb->prefix."user_roles' limit 1", ''
			)
		);

		if (!$result) {
			$error = isset($wpdb->error) ? $wpdb->error : '';
			// $this->logger->log('User roles modification has been failed.' . $error, 'staging_progress', $this->staging_id);
			dark_debug("User roles modification has been failed", '--------FAILED-------------');
		} else {
			$this->logger->log('User roles has been modified succesfully', 'staging_progress', $this->staging_id);
			dark_debug("User roles has been modified succesfully", '--------SUCCESS-------------');
		}

		//deactivate wptc
		$all_active_plugins = $wpdb->get_results(
				"select option_value from " . $new_prefix . "options where option_name='active_plugins'", ARRAY_N
		);
		dark_debug($all_active_plugins, '---------------$all_active_plugins-----------------');

		$active_plugins = @unserialize($all_active_plugins[0][0]);
		dark_debug($active_plugins, '---------------$active_plugins-----------------');

		$key = array_search('wp-time-capsule/wp-time-capsule.php', $active_plugins);
		dark_debug($key, '---------------$key-----------------');

		if($key !== false && $key !== NULL){
			unset($active_plugins[$key]);
		}

		dark_debug($active_plugins, '---------------$active_plugins-----------------');
		$active_plugins = @serialize($active_plugins);

		dark_debug($active_plugins, '---------------$active_plugins 2-----------------');
		$result = $wpdb->query(
			"update " . $new_prefix . "options set option_value = '$active_plugins' where option_name='active_plugins'"
		);

		if (!$result) {
			$error = isset($wpdb->error) ? $wpdb->error : '';
			$this->logger->log('Failed to deactivate WP time capsule plugin.' . $error, 'staging_progress', $this->staging_id);
			dark_debug("Failed to deactivate WP time capsule plugin.", '--------FAILED-------------');
		} else {
			$this->logger->log('WP time capsule plugin deactivated succesfully', 'staging_progress', $this->staging_id);
			dark_debug("WP time capsule plugin deactivated succesfully", '--------SUCCESS-------------');
		}

		//replace table prefix in meta_keys
		$usermeta_sql = $wpdb->prepare(
				'update ' . $new_prefix . 'usermeta set meta_key = replace(meta_key, %s, %s) where meta_key like %s',
				$wpdb->base_prefix,
				$new_prefix,
				$wpdb->base_prefix . '_%'
			);
		dark_debug($usermeta_sql, '--------$usermeta_sql--------');
		$result_usermeta = $wpdb->query( $usermeta_sql );
		dark_debug($result_usermeta, '--------$result_usermeta--------');

		$options_sql = $wpdb->prepare(
				'update ' . $new_prefix . 'options set option_name = replace(option_name, %s, %s) where option_name like %s',
				$wpdb->base_prefix,
				$new_prefix,
				$wpdb->base_prefix . '_%'
			);
		$result_options = $wpdb->query( $options_sql );
		dark_debug($options_sql, '--------$options_sql--------');
		dark_debug($result_options, '--------$result_options--------');

		if ($result_options === false || $result_usermeta === false) {
			$this->logger->log("Updating table $new_prefix has been failed.". $wpdb->last_error, 'staging_progress', $this->staging_id);
			dark_debug("Updating db prefix $new_prefix has been failed.". $wpdb->last_error, '-----------FAILED----------');
		} else {
			$this->logger->log('Updating db prefix "' . $wpdb->base_prefix . '" to  "' . $new_prefix . '" has been done succesfully.', 'staging_progress', $this->staging_id);
			dark_debug('Updating db prefix "' . $wpdb->base_prefix . '" to  "' . $new_prefix . '" has been done succesfully.', '--SUCCESS-------------------');
		}

		//multisite changes
		if (is_multisite()) {
			$this->same_server_multi_site_db_changes($new_prefix);
		}

		//replace $table_prefix in wp-config.php
		$this->modify_wp_config($new_prefix);

		//change admin bar style
		// $this->change_admin_bar_color($this->same_staging_folder . 'wp-includes/css/admin-bar-rtl.min.css');
		$this->change_admin_bar_color($this->same_staging_folder . 'wp-includes/css/admin-bar.min.css');

		// Replace path in index.php
		$this->same_server_reset_index_php();

		dark_debug(array(), '---------COMPLETED------------');
	}

	private function same_server_multi_site_db_changes($new_prefix){
		global $wpdb;
		$staging_args    = parse_url($this->same_server_staging_url());
		$staging_path  = rtrim($staging_args['path'], "/"). "/";
		$live_args = parse_url(get_home_url());
		$live_path = rtrim($live_args['path'], "/")."/";

		//update site table
		$result = $wpdb->query(
			$wpdb->prepare(
				'update ' . $new_prefix . 'site set path = %s',
				$staging_path
			)
		);
		if (!$result) {
			$error = isset($wpdb->error) ? $wpdb->error : '';
			$this->logger->log('modifying site table is failed.' . $error, 'staging_progress', $this->staging_id);
			dark_debug('modifying site table is failed. ' . $error, '--------FAILED----------');
		} else {
			$this->logger->log('modifying site table is successfully done.', 'staging_progress', $this->staging_id);
			dark_debug('modifying site table is successfully done.', '--------SUCCESS----------');
		}

		//update blogs table
		$sql = "update " . $new_prefix . "blogs set path = replace(path, '".$live_path."', '".$staging_path."') where path like '%".$live_path."%'";
		dark_debug($sql, '---------------$sql-----------------');
		$result = $wpdb->query($sql);
		if (!$result) {
			$error = isset($wpdb->error) ? $wpdb->error : '';
			$this->logger->log('modifying blogs table is failed.' . $error, 'staging_progress', $this->staging_id);
			dark_debug('modifying blogs table is failed. ' . $error, '--------FAILED----------');
		} else {
			$this->logger->log('modifying blogs table is successfully done.', 'staging_progress', $this->staging_id);
			dark_debug('modifying blogs table is successfully done.', '--------SUCCESS----------');
		}


	}

	private function change_admin_bar_color($path){
		$content = file_get_contents($path);
		$str = 'background:#23282d';
		$update_str = 'background:#4D1E89';
		if ($content) {
			$content = str_replace($str, $update_str, $content); // replace admin bar color
		}
		if (FALSE === file_put_contents($path, $content)) {
			dark_debug(array(), '---------------Color changing failed-----------------');
		}else {
			dark_debug(array(), '---------------Color changing successfully-----------------');
		}
	}

	private function modify_wp_config($new_prefix){
		global $wpdb;
		$path = $this->same_staging_folder . 'wp-config.php';
		$content = file_get_contents($path);
		if ($content) {
			$content = str_replace('$table_prefix', '$table_prefix = \'' . $new_prefix . '\';//', $content); // replace table prefix
			$content = str_replace(site_url(), $this->same_server_staging_url() , $content); // replace any url
			$content = str_replace('define(\'DB_NAME\'', 'define(\'DB_NAME\', \'' . $wpdb->dbname . '\');//', $content);
			$content = str_replace('define(\'DB_USER\'', 'define(\'DB_USER\', \'' . $wpdb->dbuser . '\');//', $content);
			$content = str_replace('define(\'DB_PASSWORD\'', 'define(\'DB_PASSWORD\', \'' . $wpdb->dbpassword . '\');//', $content);
			$content = str_replace('define(\'DB_HOST\'', 'define(\'DB_HOST\', \'' . $wpdb->dbhost . '\');//', $content);
			if (is_multisite()) {
					$staging_args    = parse_url($this->same_server_staging_url());
					$staging_path  = rtrim($staging_args['path'], "/"). "/";
				$content = str_replace('define(\'PATH_CURRENT_SITE\'', 'define(\'PATH_CURRENT_SITE\', \'' . $staging_path . '\');//', $content);
			}
			$content = $this->remove_unwanted_data_from_wp_config($content);

			if (FALSE === file_put_contents($path, $content)) {
				$this->logger->log($path . ' is not readable.', 'staging_progress', $this->staging_id);
				dark_debug(array(), '---------WP CONFIG NOT WRITABLE------------');
			} else{
				$this->logger->log('wp-config has been successfully modified!', 'staging_progress', $this->staging_id);
				dark_debug(array(), '---------WP CONFIG REWROTE SUCCESSFULLY------------');
			}
		} else {
			$this->logger->log($path . ' is not readable.', 'staging_progress', $this->staging_id);
			dark_debug($path . ' is not readable.', '---------FAILED------------');
		}
	}

	private function remove_unwanted_data_from_wp_config($content){
		$unwanted_words_match = array("WP_SITEURL", "WP_HOME");
		foreach ($unwanted_words_match as $words) {
			$replace_match = '/^.*' . $words . '.*$(?:\r\n|\n)?/m';
			$content = preg_replace($replace_match, '', $content);
		}

		return $content;
	}

	// public function unset_safe_path($path) {
	// 	return str_replace("/", "\\", $path);
	// }

	private function replace_old_url(){
		$this->same_server_set_staging_path();
		global $wpdb;
		$raw_result = $this->options->get_option('same_server_replace_old_url_data');
		if (!empty($raw_result)) {
			$result = @unserialize($raw_result);
		}
		dark_debug($result, '---------------$result beginning-----------------');
		$old_url = site_url();
		$new_url = $this->same_server_staging_url();
		$old_file_path = WPTC_ABSPATH;
		$new_file_path = wp_normalize_path($this->same_staging_folder);

		dark_debug($old_url, '---------------$old_url-----------------');
		dark_debug($new_url, '---------------$new_url-----------------');
		dark_debug($old_file_path, '---------------$old_file_path-----------------');
		dark_debug($new_file_path, '---------------$new_file_path-----------------');

		$url_old_json = str_replace('"', "", json_encode($old_url));
		$url_new_json = str_replace('"', "", json_encode($new_url));
		$path_old_json = str_replace('"', "", json_encode($old_file_path));
		$path_new_json = str_replace('"', "", json_encode($new_file_path));


		$replace_list = array();

		array_push($replace_list,
				array('search' => $old_url,			 'replace' => $new_url),
				array('search' => $old_file_path,			 'replace' => $new_file_path),
				array('search' => $url_old_json,				 'replace' => $url_new_json),
				array('search' => $path_old_json,				 'replace' => $path_new_json),
				array('search' => urlencode($old_file_path), 'replace' => urlencode($new_file_path)),
				array('search' => urlencode($old_url),  'replace' => urlencode($new_url)),
				array('search' => rtrim(wp_normalize_path($old_file_path), '\\'), 'replace' => rtrim($new_file_path, '/'))
		);

		array_walk_recursive($replace_list, '_dupx_array_rtrim');
		dark_debug($replace_list, '---------------$replace_list-----------------');

		$table_prefix = $this->unique_prefix_gen().'_';
		dark_debug($table_prefix, '---------------$table_prefix-----------------');
		if (empty($result)) {
			$result = $wpdb->get_results( 'SHOW TABLES LIKE "'.$table_prefix.'%"', ARRAY_N);
			dark_debug($result, '---------------$result replace_old_url inside-----------------');
		}
		dark_debug($result, '---------------$result replace_old_url-----------------');
		foreach ($result as $key => $value) {
			dark_debug($value[0], '---------------$value replace_old_url-----------------');
			$this->replace_old_url_depth($replace_list, array($value[0]), true);
			dark_debug("Table ".$value[0]." URL content updated.", '-------------STATUS-------------------');
			unset($result[$key]);
			if (count($result) === 0) {
				$this->options->get_option('same_server_replace_old_url', true);
			} else {
				$this->options->set_option('same_server_replace_old_url_data', serialize($result));
			}
			if(is_wptc_timeout_cut()){
				dark_debug($result, '---------------$result-----------------');
			}
		}
	}

	public function replace_old_url_depth($list = array(), $tables = array(), $fullsearch = false) {
		// dark_debug(__FUNCTION__, '----function name------');
		// dark_debug(func_get_args(), '---------------arguments-----------------');
		global $wpdb;
		$report = array(
			'scan_tables' => 0,
			'scan_rows' => 0,
			'scan_cells' => 0,
			'updt_tables' => 0,
			'updt_rows' => 0,
			'updt_cells' => 0,
			'errsql' => array(),
			'errser' => array(),
			'errkey' => array(),
			'errsql_sum' => 0,
			'errser_sum' => 0,
			'errkey_sum' => 0,
			'time' => '',
			'err_all' => 0
		);

		$walk_function = create_function('&$str', '$str = "`$str`";');


		if (is_array($tables) && !empty($tables)) {

			foreach ($tables as $table) {
				$report['scan_tables']++;
				$columns = array();
				// dark_debug($table, '---------------$table-----------------');
				$fields = $wpdb->get_results('DESCRIBE ' . $table); //modified
				// dark_debug($fields, '---------------$fields-----------------');
				foreach ($fields as $key => $column) {
					$columns[$column->Field] = $column->Key == 'PRI' ? true : false;
				}
				// dark_debug($columns, '---------------$columns-----------------');
				$row_count =  $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
				// dark_debug($row_count, '---------------$row_count-----------------');
				if ($row_count == 0) {
					continue;
				}
				$page_size = $this->options->get_option('internal_staging_deep_link_limit');
				if (empty($page_size)) {
					$page_size = 25000; //fallback to default value
				}
				$offset = ($page_size + 1);
				$pages = ceil($row_count / $page_size);
				// dark_debug($pages, '---------------$pages-----------------');
				$colList = '*';
				$colMsg  = '*';
				if (! $fullsearch) {
					$colList = $this->get_text_columns($table);
					if ($colList != null && is_array($colList)) {
						array_walk($colList, $walk_function);
						$colList = implode(',', $colList);
					}
					$colMsg = (empty($colList)) ? '*' : '~';
				}

				if (empty($colList)) {
					continue;
				}
				$prev_table_data = $this->same_server_deep_link_status($table);
				// dark_debug($prev_table_data, '---------------$prev_table_data-----------------');
				if (!$prev_table_data) {
					$prev_table_data = 0;
				}
				//Paged Records
				for ($page = $prev_table_data; $page < $pages; $page++) {
					$current_row = 0;
					// dark_debug($prev_table_data, '---------------$prev_table_data-----------------');
					// dark_debug($current_row, '---------------$current_row-----------------');
					// if ($prev_table_data && $report['scan_rows'] === 0) {
					// 	dark_debug(array(), '---------------come in-----------------');
					// 	$current_row = $prev_table_data[0];
					// 	$start = $prev_table_data[0];
					// 	dark_debug($current_row, '---------------$current_row inside-----------------');
					// 	dark_debug($start, '---------------$start isinde-----------------');
					// } else {
					$start = $page * $page_size;
					// }
					$end   = $start + $page_size;
					$sql = sprintf("SELECT {$colList} FROM `%s` LIMIT %d, %d", $table, $start, $offset);
					// dark_debug($start, '---------------$start-----------------');
					// dark_debug($sql, '---------------$sql-----------------');
					$data  = $wpdb->get_results($sql);
					// dark_debug($data, '---------------$data-----------------');
					if (empty($data)){
						//$report['errsql'][] = mysqli_error($conn);
						$scan_count = ($row_count < $end) ? $row_count : $end;
						// dark_debug($scan_count, '---------------$scan_count-----------------');
					}

				foreach ($data as $key => $row) {

						$report['scan_rows']++;
						$current_row++;
						$upd_col = array();
						$upd_sql = array();
						$where_sql = array();
						$upd = false;
						$serial_err = 0;
						// dark_debug($row, '---------------$row-----------------');

						foreach ($columns as $column => $primary_key) {
							$report['scan_cells']++;
							$edited_data = $data_to_fix = $row->$column;
							$base64coverted = false;
							$txt_found = false;


							if (!empty($row->$column) && !is_numeric($row->$column)) {
								//Base 64 detection
								if (base64_decode($row->$column, true)) {
									$decoded = base64_decode($row->$column, true);
									if ($this->is_serialized($decoded)) {
										$edited_data = $decoded;
										$base64coverted = true;
									}
								}

								//Skip table cell if match not found
								foreach ($list as $item) {
									if (strpos($edited_data, $item['search']) !== false) {
										$txt_found = true;
										break;
									}
								}
								if (! $txt_found) {
									continue;
								}

								//Replace logic - level 1: simple check on any string or serlized strings
								foreach ($list as $item) {
									// dark_debug($item, '---------------$item-----------------');
									// dark_debug($edited_data, '---------------$edited_data-----------------');
									$edited_data = $this->recursive_unserialize_replace($item['search'], $item['replace'], $edited_data);
								}

								//Replace logic - level 2: repair serilized strings that have become broken
								$serial_check = $this->fix_serial_string($edited_data);
								if ($serial_check['fixed']) {
									$edited_data = $serial_check['data'];
								} else if ($serial_check['tried'] && !$serial_check['fixed']) {
									$serial_err++;
								}
							}

							//Change was made
							if ($edited_data != $data_to_fix || $serial_err > 0) {
								$report['updt_cells']++;
								//Base 64 encode
								if ($base64coverted) {
									$edited_data = base64_encode($edited_data);
								}
								$upd_col[] = $column;
								$upd_sql[] = $column . ' = "' . $wpdb->_real_escape($edited_data) . '"';
								$upd = true;
							}

							if ($primary_key) {
								$where_sql[] = $column . ' = "' . $wpdb->_real_escape($data_to_fix) . '"';
							}
						}

						if ($upd && !empty($where_sql)) {

							$sql = "UPDATE `{$table}` SET " . implode(', ', $upd_sql) . ' WHERE ' . implode(' AND ', array_filter($where_sql));
							// dark_debug($sql, '---------------$sql-----------------');
							$result = $wpdb->query($sql);

							if ($result) {
								if ($serial_err > 0) {
									$report['errser'][] = "SELECT " . implode(', ', $upd_col) . " FROM `{$table}`  WHERE " . implode(' AND ', array_filter($where_sql)) . ';';
								}
								$report['updt_rows']++;
							}
						} elseif ($upd) {
							$report['errkey'][] = sprintf("Row [%s] on Table [%s] requires a manual update.", $current_row, $table);
						}
					}
					if(is_wptc_timeout_cut()){
						$this->options->set_option('same_server_replace_url_multicall_status', serialize(array($table =>($page+1))));
						dark_debug(array(), '---------------DEEP LINK NESTED TIMEOUT-----------------');
						dark_debug(array('table' => $table, 'start' => $start, 'offset' => $offset , 'page' =>($page+1)), '---------------DEEP LINK NESTED TIMEOUT data-----------------');
						send_response_wptc('DEEP LINKS ARE PROCESSING', array('status' => 'Deep link updating', 'details' => array('table' => $table, 'start' => $start, 'offset' => $offset) ));
					}

				}

				if ($upd) {
					$report['updt_tables']++;
				}
			}
		}

		$report['errsql_sum'] = empty($report['errsql']) ? 0 : count($report['errsql']);
		$report['errser_sum'] = empty($report['errser']) ? 0 : count($report['errser']);
		$report['errkey_sum'] = empty($report['errkey']) ? 0 : count($report['errkey']);
		$report['err_all'] = $report['errsql_sum'] + $report['errser_sum'] + $report['errkey_sum'];
		// dark_debug($report, '---------------$report-----------------');
		return $report;
	}

	private function same_server_deep_link_status($table){
		dark_debug(array(), '---------------same_server_deep_link_status-----------------');
		$data = $this->options->get_option('same_server_replace_url_multicall_status');
		dark_debug($data, '---------------$data-----------------');
		if (empty($data)) {
			return false;
		}

		$unserialized_data = @unserialize($data);
		dark_debug($unserialized_data, '---------------$unserialized_data-----------------');
		if (empty($unserialized_data)) {
			return false;
		}
		if(!isset($unserialized_data[$table])){
			return false;
		}

		return $unserialized_data[$table];

	}

	private function fix_serial_string($data) {
		$result = array('data' => $data, 'fixed' => false, 'tried' => false);
		if (preg_match("/s:[0-9]+:/", $data)) {
			if (!$this->is_serialized($data)) {
				$regex = '!(?<=^|;)s:(\d+)(?=:"(.*?)";(?:}|a:|s:|b:|d:|i:|o:|N;))!s';
				$serial_string = preg_match('/^s:[0-9]+:"(.*$)/s', trim($data), $matches);
				//Nested serial string
				if ($serial_string) {
					$inner = preg_replace_callback($regex, 'DBUpdateEngine::fix_string_callback', rtrim($matches[1], '";'));
					$serialized_fixed = 's:' . strlen($inner) . ':"' . $inner . '";';
				} else {
					$serialized_fixed = preg_replace_callback($regex, 'DBUpdateEngine::fix_string_callback', $data);
				}
				if ($this->is_serialized($serialized_fixed)) {
					$result['data'] = $serialized_fixed;
					$result['fixed'] = true;
				}
				$result['tried'] = true;
			}
		}
		return $result;
	}

	private function is_serialized($data){
		$test = @unserialize(($data));
		return ($test !== false || $test === 'b:0;') ? true : false;
	}

	private function recursive_unserialize_replace($from = '', $to = '', $data = '', $serialised = false) {

		try {
			if (is_string($data) && ($unserialized = @unserialize($data)) !== false) {
				$data = $this->recursive_unserialize_replace($from, $to, $unserialized, true);
			} else if (is_array($data)) {
				$_tmp = array();
				foreach ($data as $key => $value) {
					$_tmp[$key] = $this->recursive_unserialize_replace($from, $to, $value, false);
				}
				$data = $_tmp;
				unset($_tmp);
			} else if (is_object($data)) {

				$_tmp = $data;
				$props = get_object_vars( $data );
				foreach ($props as $key => $value) {
					$_tmp->$key = $this->recursive_unserialize_replace( $from, $to, $value, false );
				}
				$data = $_tmp;
				unset($_tmp);
			} else {
				if (is_string($data)) {
					$data = str_replace($from, $to, $data);
				}
			}

			if ($serialised)
				return serialize($data);

		} catch (Exception $error){

		}
		return $data;
	}

	private function get_text_columns($table) {
		global $wpdb;

		$type_where  = "type NOT LIKE 'tinyint%' AND ";
		$type_where .= "type NOT LIKE 'smallint%' AND ";
		$type_where .= "type NOT LIKE 'mediumint%' AND ";
		$type_where .= "type NOT LIKE 'int%' AND ";
		$type_where .= "type NOT LIKE 'bigint%' AND ";
		$type_where .= "type NOT LIKE 'float%' AND ";
		$type_where .= "type NOT LIKE 'double%' AND ";
		$type_where .= "type NOT LIKE 'decimal%' AND ";
		$type_where .= "type NOT LIKE 'numeric%' AND ";
		$type_where .= "type NOT LIKE 'date%' AND ";
		$type_where .= "type NOT LIKE 'time%' AND ";
		$type_where .= "type NOT LIKE 'year%' ";

		$result = $wpdb->get_results("SHOW COLUMNS FROM `{$table}` WHERE {$type_where}", ARRAY_N);
		if (empty($result)) {
			return null;
		}
		$fields = array();
		if (count($result) > 0 ) {
			foreach ($result as $key => $row) {
				$fields[] = $row['Field'];
			}
		}

		$result =  $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_N);
		if (count($result) > 0) {
			foreach ($result as $key => $row) {
				$fields[] = $row['Column_name'];
			}
		}

		return (count($fields) > 0) ? $fields : null;
	}

	private function create_default_htaccess(){
		if (is_multisite()) {
			dark_debug(array(), '---------------Multi site htaccess-----------------');
			return $this->multi_site_default_htaccess();
		}
		return $this->normal_site_default_htaccess();
	}

	private function multi_site_default_htaccess(){
		$args    = parse_url($this->same_server_staging_url());
		$string  = rtrim($args['path'], "/");
		$data = "\nRewriteBase ".$string."/\nRewriteRule ^index\.php$ - [L]\n\n ## add a trailing slash to /wp-admin\nRewriteRule ^([_0-9a-zA-Z-]+/)?wp-admin$ $1wp-admin/ [R=301,L]\n\nRewriteCond %{REQUEST_FILENAME} -f [OR]\nRewriteCond %{REQUEST_FILENAME} -d\nRewriteRule ^ - [L]\nRewriteRule ^([_0-9a-zA-Z-]+/)?(wp-(content|admin|includes).*) $2 [L]\nRewriteRule ^([_0-9a-zA-Z-]+/)?(.*\.php)$ $2 [L]\nRewriteRule . index.php [L]";
		@file_put_contents($this->same_staging_folder.'.htaccess', $data);
	}

	private function normal_site_default_htaccess(){
		$args    = parse_url($this->same_server_staging_url());
		$string  = rtrim($args['path'], "/");
		$data = "# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase ".$string."/\nRewriteRule ^index\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . ".$string."/index.php [L]\n</IfModule>\n# END WordPress";
		@file_put_contents($this->same_staging_folder.'.htaccess', $data);
	}

	private function discourage_search_engine($new_prefix, $reset_permalink = false){
		dark_debug(array(), '--------discourage_search_engine started--------');
		global $wpdb;
		$result = $wpdb->query(
			$wpdb->prepare(
				'update ' . $new_prefix . 'options set option_value = %s where option_name = \'blog_public\'',
				0
			)
		);

		if (!is_multisite()) {
			return false;
		}
		$new_prefix = (string) $new_prefix;
		$wp_tables = $this->processed_files->get_all_tables();
		foreach ($wp_tables as $table) {
			if (stripos($table, 'options') === false || stripos($table, $new_prefix) === false) {
				continue;
			}
			dark_debug($table, '--------$table for turn of indexing--------');
			$wpdb->query(
				$wpdb->prepare(
					'update ' . $table . ' set option_value = %s where option_name = \'blog_public\'',
					0
				)
			);

			if (!$reset_permalink) {
				continue;
			}

			$wpdb->query(
				$wpdb->prepare(
					'update ' . $table . ' set option_value = %s where option_name = \'permalink_structure\'',
					false
				)
			);
		}
	}

	private function create_robot_txt(){
		$data = "User-agent: *\nDisallow: /\n";
		@file_put_contents($this->same_staging_folder.'robots.txt', $data);
	}

	private function same_server_reset_index_php(){
		$path = $this->same_staging_folder . 'index.php';
		$content = file_get_contents($path);

		if ($content) {

			$pattern = "/(require(.*)wp-blog-header.php' \);)/";
			if ( !preg_match($pattern, $content, $matches) ){
				dark_debug(array(), '---------Fatal error: wp-blog-header.php not------------');
			}
			$pattern2 = "/require(.*) dirname(.*) __FILE__ (.*) \. '(.*)wp-blog-header.php'(.*);/";
			$replace = "require( dirname( __FILE__ ) . '/wp-blog-header.php' ); // " . $matches[0] . " // Changed by WP Time Capsule";
			$content = preg_replace($pattern2, $replace, $content);

			if (FALSE === file_put_contents($path, $content)) {
				dark_debug($path . ' is not writable', '-------FAILED--------------');
			} else {
				dark_debug('Index file updated successfully', '-------FAILED--------------');
			}
		} else {
			dark_debug($path . ' is not writable', '-------FAILED--------------');
		}
	}

	private function same_server_mkdir($path, $recursive = true){
		$path = wp_normalize_path($path);
		$this->file_base->createRecursiveFileSystemFolder($path);
	}

	private function same_server_replace_pathname($path){
		return wp_normalize_path(str_replace(WPTC_ABSPATH , $this->same_staging_folder, $path));
	}

	private function same_server_set_staging_path($path = false){
		$path = empty($path) ? $this->options->get_option('same_server_staging_path') : $path;
		$this->same_staging_folder = wp_normalize_path(get_home_path_wptc() . $path . '/');
		$this->same_staging_bridge_dir = wp_normalize_path($this->same_staging_folder . self::CLONE_TMP_FOLDER . '/');
	}

	private function unique_prefix_gen($type = 'prefix'){
		global $wpdb;
		$prefix = $this->options->get_option('same_server_staging_db_prefix');
		if (empty($prefix)) {
			$prefix = time();
			$full_prefix = $prefix . '_' . $wpdb->base_prefix;
			$this->options->set_option('same_server_staging_db_prefix', $prefix);
			$this->options->set_option('same_server_staging_full_db_prefix', $full_prefix);
		}
		if ($type === 'prefix') {
			return $prefix;
		}
		return $this->options->get_option('same_server_staging_full_db_prefix');
	}

	public function same_server_get_staging_full_prefix(){
		global $wpdb;
		$prefix = $this->get_staging_details('', 'db_prefix');
		$full_prefix = $prefix . $wpdb->base_prefix;
		if (empty($prefix) || $prefix == $wpdb->base_prefix) {
			return false;
		}
		return $full_prefix;
	}

	private function same_server_staging_url(){
		return get_home_url() . '/' . $this->options->get_option('same_server_staging_path');
	}

	private function same_server_staging_bridge_url(){
		return get_home_url() . '/' . $this->options->get_option('same_server_staging_path') . '/' . self::CLONE_TMP_FOLDER . '/' . 'bridge.php' ;
	}

	public function save_staging_settings($data){

		if (!empty($data['db_rows_clone_limit_wptc'])) {
			$this->config->set_option('internal_staging_db_rows_copy_limit', $data['db_rows_clone_limit_wptc']);
		}

		if (!empty($data['files_clone_limit_wptc'])) {
			$this->config->set_option('internal_staging_file_copy_limit', $data['files_clone_limit_wptc']);
		}

		if (!empty($data['deep_link_replace_limit_wptc'])) {
			$this->config->set_option('internal_staging_deep_link_limit', $data['deep_link_replace_limit_wptc']);
		}
	}
}