<?php

if (!isset($_REQUEST)) {
	$this->send_response(array('error' => "Request is missing"));
}
$bridge = new Wptc_Bridge($_REQUEST);
$bridge->init();


class Wptc_Bridge{
	protected $params;
	protected $secret_code_start;
	protected $secret_code_end;
	protected $options_obj;
	protected $staging_abspath;
	protected $meta_file_name;

	public function __construct($params){
		$this->params = $params;
		$this->secret_code_start = '<WPTCHEADER>';
		$this->secret_code_end = '</ENDWPTCHEADER>';
		$this->staging_abspath = $this->get_staging_abspath();
		$this->meta_file_name = $this->staging_abspath.'wp-tcapsule-bridge/wordpress-db_meta_data.sql';
	}

	public function init(){
		if (!isset($this->params['data'])) {
			$this->send_response(array('error' => "Request data is missing"));
		}
		$this->decode_request_data();
		$this->find_action();
	}

	public function find_action(){
		if (!isset($this->params['action'])){
			$this->send_response(array('error' => "could not find action"));
		}
		$this->define_constants();
		switch ($this->params['action']) {
			case 'test_connection':
				$this->test_connection();
				break;
			case 'check_db_connection':
				$this->check_db_connection();
				break;
			case 'source_destination_check':
				$this->source_destination_check();
				break;
			case 'get_db_wp_config':
				$this->get_db_wp_config();
				break;
			case 'init_staging':
				$this->init_staging();
				break;
			case 'import_meta_file':
				$this->import_meta_file();
				break;
			case 'init_restore':
				$this->init_restore();
				break;
			case 'new_site_changes':
				$this->new_site_changes();
				break;
			case 'delete_staging':
				$this->delete_staging();
				break;
			case 'clear_bridge_files':
				$this->clear_bridge_files();
				break;
			case 'rewrite_permalink_structure':
				$this->rewrite_permalink_structure();
				break;
			case 'update_in_staging':
				$this->update_in_staging();
				break;
			default:
				$this->send_response(array('error' => "action is not found"));
		}
	}

	public function define_constants(){
		if(!defined('WP_DEBUG')){
			define('WP_DEBUG', false);
		}
		if(!defined('WP_DEBUG_DISPLAY')){
			define('WP_DEBUG_DISPLAY', false);
		}
	}

	public function check_db_connection($dont_die = 0, $dont_print = 0){
		$this->include_spl_files();
		$this->test_db_connection($dont_die, $dont_print);
	}

	public function test_connection(){
		if (isset($this->params['get_db_creds_from_wp']) && $this->params['get_db_creds_from_wp'] == 1) {
			$this->get_db_wp_config();
		} else {
			$this->include_spl_files();
		}
		$this->test_db_connection();
	}

	public function init_staging(){
		$this->include_spl_files();
		$this->test_db_connection(1);
		$this->download_n_extract_bridge();
		$this->create_config_file();
		$this->unlink_meta_file();
	}

	public function clear_bridge_files(){
		$this->clear_staging_flags();
		$this->unlink_meta_file();
		$this->delete_bridge_files();
		$this->delete_wptc_db();
	}

	private function delete_wptc_db($dont_print = 0){
		$table_prefix = $this->params['db_prefix'].'wptc_';
		// if ($dont_print) {
		// 	global $wpdb;
		// 	$database = $wpdb->dbname;
		// } else {
		$this->check_db_connection($dont_die = 1, $dont_print = 0);
		$database = $this->params['db_name'];
			// $table_prefix = 'wptc_';
		// }
		global $wpdb;
		$tables_delete_query = "SELECT CONCAT( 'DROP TABLE ', GROUP_CONCAT(table_name) , ';' )
		AS statement FROM information_schema.tables
		WHERE table_schema = '".$database."' AND table_name LIKE '%$table_prefix%';";
		$query_result = $wpdb->get_results($tables_delete_query, ARRAY_N);
		if (isset($query_result[0][0])) {
			$wpdb->query($query_result[0][0]);
		}
	}

	public function unlink_meta_file(){
		$unlink_result = @unlink($this->meta_file_name);
	}

	public function create_config_file(){
		require_once $this->staging_abspath.'wp-tcapsule-bridge/bridge_functions.php';
		include_files();
		create_config_file($this->params, true);
	}
	public function download_n_extract_bridge(){
		// $file_path = $this->downloadURL('http://localhost/bridge/wp-tcapsule-bridge.zip', 'wp-tcapsule-bridge.zip');
		// $file_path = $this->downloadURL('https://s3.eu-central-1.amazonaws.com/thamarai-dev/staging_bridge_files/wp-tcapsule-bridge.zip', 'wp-tcapsule-bridge.zip'); //staging
		$file_path = $this->downloadURL('https://s3-us-west-2.amazonaws.com/wptc-doc/wp-tcapsule-bridge.zip', 'wp-tcapsule-bridge.zip'); // prod
		// $this->downloadURL('http://localhost/wp3/wp-tcapsule-bridge.zip', 'wp-tcapsule-bridge.zip');
		// $this->downloadURL('https://s3-us-west-2.amazonaws.com/wptc-doc/wp-tcapsule-bridge.zip', 'wp-tcapsule-bridge.zip');
		$path_to_restore = dirname(dirname(__FILE__));
		$this->extract_zip($file_path, $path_to_restore);
		$result = chmod($this->addTrailingSlash($path_to_restore).'wp-tcapsule-bridge', 0755);
	}
	public function decode_request_data(){
		$this->params = unserialize(base64_decode($this->params['data']));
		$this->dark_debug_bridge($this->params, '-------decode_request_data-------');
	}

	public function get_db_wp_config(){
		global $config_check, $temp_table_prefix;
		$this->check_wp_config_file_present();
		// $this->params['db_username'] = DB_USER;
		// $this->params['db_password'] = DB_PASSWORD;
		// $this->params['db_name'] = DB_NAME;
		// $this->params['db_host'] = DB_HOST;
		if (empty($config_check)) {
			$this->send_response(array('success' => "not_found"));
		}
		$this->send_response(
				array(
					'DB_USER' => DB_USER,
					'DB_PASSWORD' => DB_PASSWORD,
					'DB_NAME' => DB_NAME,
					'DB_HOST' => DB_HOST,
					'TABLE_PREFIX' => $temp_table_prefix,
		));
	}

	public function test_db_connection($dont_die = 0, $dont_print = 0) {
		global $wpdb;
		$wpdb = new wpdb($this->params['db_username'], $this->params['db_password'], $this->params['db_name'], $this->params['db_host']);
		if (!empty($wpdb)) {
			$status = $wpdb->check_connection();
			if ($dont_die && $dont_print == 0) {
				echo $status;
			} else {
				$this->send_response(array('success' => $status));
			}
		}
	}

	public function check_wp_config_file_present() {
		global $config_check, $temp_table_prefix;
		if (!file_exists($this->staging_abspath.'wp-config.php')) {
			$this->send_response(array('success' => "not_found"));
		}
		include_once $this->staging_abspath.'wp-config.php';
		global $config_check, $temp_table_prefix;
		$config_check = 1;
		$temp_table_prefix = $table_prefix;
	}

	public function include_spl_files(){
		@include_once "wp-db-custom.php";
		@include_once "wp-modified-functions.php";
	}

	public function dark_debug_bridge($value = null, $key = null, $is_print_all_time = true, $forEvery = 0) {
		try {
			global $every_count;
			$usr_time = time();
			if (function_exists('user_formatted_time_wptc')) {
				$usr_time = user_formatted_time_wptc(time());
			}

			if (empty($forEvery)) {
				@file_put_contents('DE_cl.php', "\n -----$key------------$usr_time----- " . var_export($value, true) . "\n", FILE_APPEND);
			} else {
				$every_count++;
				if ($every_count % $forEvery == 0) {
					@file_put_contents('DE_cl.php', "\n -----$key------- " . var_export($value, true) . "\n", FILE_APPEND);
					return true;
				}
			}
		} catch (Exception $e) {
			@file_put_contents('DE_cl.php', "\n -----$key----------$usr_time------ " . var_export(serialize($value), true) . "\n", FILE_APPEND);
		}
	}

	public function getTempName($fileName = '', $dir = '') {
		if ( empty($dir) )
			$dir = $this->getTempDir();
		$fileName = basename($fileName);
		if ( empty($fileName) )
			$fileName = time();

		$fileName = preg_replace('|\..*$|', '.zip', $fileName);
		$fileName = $dir . $this->getUniqueFileName($dir, $fileName);
		touch($fileName);
		return $fileName;
	}

	public function getTempDir() {
		static $temp;
		if ( defined('TEMP_DIR') )
			return $this->addTrailingSlash(TEMP_DIR);

		if ( $temp )
			return $this->addTrailingSlash($temp);

		$temp = dirname(__FILE__).'/';//dirname(__FILE__) = clone_controller folder
		if ( is_dir($temp) && @is_writable($temp) )
			return $temp;

		if  ( function_exists('sys_get_temp_dir') ) {
			$temp = sys_get_temp_dir();
			if ( @is_writable($temp) )
				return $this->addTrailingSlash($temp);
		}

		$temp = ini_get('upload_tmp_dir');
		if ( is_dir($temp) && @is_writable($temp) )
			return $this->addTrailingSlash($temp);

		$temp = '/tmp/';
		if ( is_dir($temp) && @is_writable($temp) )
			return $this->addTrailingSlash($temp);

		$this->send_response(array('error' => "Unable to write files. Please set 777 permission to 'wptc_staging_folder' directory in the clone destination and try again."));

		return $this->addTrailingSlash($temp);
	}

	public function addTrailingSlash($string) {
		return $this->removeTrailingSlash($string) . '/';
	}

	public function removeTrailingSlash($string) {
		return rtrim($string, '/');
	}

	public function downloadURL($URL, $filename){
		$file = $this->getTempName($filename);
		$downloadResponseHeaders = array();
		$downloaded = false;

		$downloaded = $this->downloadUsingCURL($URL, $file, $downloadResponseHeaders);

		if(!$downloaded){
			//Check fsockopen is allowed
			if (!function_exists('fsockopen')){
				$this->send_response(array('error' => "Please enable fsockopen on your server."));
			}
			$downloaded = $this->downloadUsingFsock($URL, $file, $downloadResponseHeaders);
		}

		if(!$downloaded){
			$this->send_response(array('error' => "The file could not be downloaded. Change directory permission to 777 and try again."));
		}
		else{
			$this->checkdownloadResponseHeaders($downloadResponseHeaders);//it using die when invalid file download
		}
		return $file;
	}

	public function downloadUsingCURL($URL, $file, &$downloadResponseHeaders){

		if (!function_exists('curl_init') || !function_exists('curl_exec')) return false;
		$fp = fopen ($file, 'w');
		if(!$fp){ return false; }
		$ch = curl_init($URL);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:16.0) Gecko Firefox/16.0');
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
													'Connection: Keep-Alive',
													'Keep-Alive: 115'
												));

		$callResponse = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);
		fclose($fp);

		if($callResponse == 1){
			$downloadResponseHeaders[] = "HTTP/1.1 ".$info['http_code']." SOMETHING";
			$downloadResponseHeaders[] = "Content-Type: ".$info['content_type'];
			return true;
		}
		return false;

	}

	function downloadUsingFsock($infile, $outfile, &$downloadResponseHeaders){
		$chunksize = 1024 * 1024;  // 1 Meg

		/**
		 * parse_url breaks a part a URL into it's parts, i.e. host, path,
		 * query string, etc.
		 */
		$parts     = parse_url($infile);
		$i_handle  = fsockopen($parts['host'], 80, $errstr, $errcode, 5);
		$o_handle  = fopen($outfile, 'wb');

		if ($i_handle == false || $o_handle == false) {
			return false;
		}

		if (!empty($parts['query'])) {
			$parts['path'] .= '?' . $parts['query'];
		}

		/**
		 * Send the request to the server for the file
		 */
		$request = "GET {$parts['path']} HTTP/1.0\r\n";
		$request .= "Host: {$parts['host']}\r\n";
		$request .= "User-agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:16.0) Gecko Firefox/16.0". "\r\n";
		$request .= "Keep-Alive: 115\r\n";
		$request .= "Connection: keep-alive\r\n\r\n";
		fwrite($i_handle, $request);

		/**
		 * Now read the headers from the remote server. We'll need
		 * to get the content length.
		 */
		$headers = array();
		while (!feof($i_handle)) {
			$line = fgets($i_handle);
			if ($line == "\r\n")
				break;
			$headers[] = $line;
		}
		$downloadResponseHeaders = $headers;

	  /**
		 * Look for the Content-Length header, and get the size
		 * of the remote file.
		 */
		$length = 0;
		foreach ($headers as $header) {
			if (stripos($header, 'Content-Length:') === 0) {
				$length = (int) str_replace('Content-Length: ', '', $header);
				break;
			}
		}

		/**
		 * Start reading in the remote file, and writing it to the
		 * local file one chunk at a time.
		 */
		$cnt = 0;
		while (!feof($i_handle)) {
			$buf   = '';
			$buf   = fread($i_handle, $chunksize);
			$bytes = fwrite($o_handle, $buf);
			if ($bytes == false) {
				return false;
			}
			$cnt += $bytes;

			/**
			 * We're done reading when we've reached the content length
			 */
			if ($length && $cnt >= $length)
				break;
		}
		fclose($i_handle);
		fclose($o_handle);
		return $cnt;
	}

	public function checkdownloadResponseHeaders($headers){
		$httpCodeChecked = false;
		foreach($headers as $line){
		if(!$httpCodeChecked && stripos($line, 'HTTP/') !== false){
			$matches = array();
			preg_match('#HTTP/\d+\.\d+ (\d+)#', $line, $matches);
			$httpCode = (int)$matches[1];
			if($httpCode != 200){
				$this->send_response(array('error' => "Error while downloading the zip file HTTP error: "));
			}
			$httpCodeChecked = true;
		}

		if(stripos($line, 'Content-Type') !== false){
			//$contentType = trim(str_ireplace('Content-Type:', '', $line));
			//if(strtolower($contentType) != 'application/zip')
			if(stripos($line, 'application/zip') === false){
				//die("Invalid zip type, please check file is downloadable.");
				$GLOBALS['downloadPossibleError'] = " Please check file is downloadable.";
			}
		}
		}
		return true;
	}
	public function getUniqueFileName( $dir, $fileName) {

		// separate the fileName into a name and extension
		$info = pathinfo($fileName);
		$ext = !empty($info['extension']) ? '.' . $info['extension'] : '';
		$name = basename($fileName, $ext);

		// edge case: if file is named '.ext', treat as an empty name
		if ( $name === $ext )
			$name = '';

		// Increment the file number until we have a unique file to save in $dir. Use callback if supplied.

		$number = '';

		// change '.ext' to lower case
		if ( $ext && strtolower($ext) != $ext ) {
			$ext2 = strtolower($ext);
			$fileName2 = preg_replace( '|' . preg_quote($ext) . '$|', $ext2, $fileName );

			// check for both lower and upper case extension or image sub-sizes may be overwritten
			while ( file_exists($dir . "/$fileName") || file_exists($dir . "/$fileName2") ) {
				$newNumber = $number + 1;
				$fileName = str_replace( "$number$ext", "$newNumber$ext", $fileName );
				$fileName2 = str_replace( "$number$ext2", "$newNumber$ext2", $fileName2 );
				$number = $newNumber;
			}
			return $fileName2;
		}

		while ( file_exists( $dir . "/$fileName" ) ) {
			if ( '' == "$number$ext" )
				$fileName = $fileName . ++$number . $ext;
			else
				$fileName = str_replace( "$number$ext", ++$number . $ext, $fileName );
		}


		return $fileName;
	}

	public function extract_zip($filename, $path_to_extract){
		require_once 'pclzip.php';
		$archive   = new PclZip($filename);
		$extracted = $archive->extract(PCLZIP_OPT_PATH, $path_to_extract, PCLZIP_OPT_TEMP_FILE_THRESHOLD, 1);
		if (!$extracted || $archive->error_code) {
			$this->send_response(array('error' => "Error: Failed to extract backup file (" . $archive->error_string . ")".$GLOBALS['downloadPossibleError'].""));
		}
	}

	private function include_init_js(){
		require_once $this->staging_abspath.'wp-tcapsule-bridge/tc-init.php';
	}

	private function include_config_like(){
		require_once $this->staging_abspath.'wp-tcapsule-bridge/wp-tc-config.php';
	}

	public function import_meta_file(){
		$this->include_init_js();
		$this->options_obj = WPTC_Factory::get('config');
		$is_restore_completed = $this->options_obj->get_option('meta_restore_completed');
		if ($is_restore_completed) {
			$this->send_response(array('success' => 'completed'));
		}
		$is_multicall = $this->options_obj->get_option('restore_db_index');
		if (empty($is_multicall)) {
			$result = tc_database_restore($this->meta_file_name, 1, array('current_db_prefix' => $this->params['current_db_prefix'], 'new_db_prefix' => $this->params['new_db_prefix'] ));
		} else {
			$result = tc_database_restore($this->meta_file_name, 2);
		}
		if($result == 'meta restore completed'){
			if ($this->verify_meta_import()) {
				$this->send_response(array('success' => 'completed'));;
			} else {
				global $wpdb;
				$last_error = $wpdb->last_error;
				if (empty($last_error)) {
					$last_error = 'Meta data import failed.';
				}
				$this->send_response(array('error' => $last_error ));
			}
		} else {
			$this->send_response(array('last_index' => $result));
		}
	}

	private function verify_meta_import(){
		global $wpdb;
		$tables = $wpdb->get_results("SHOW TABLES LIKE '%wptc%'", ARRAY_N);
		if (empty($tables)) {
			return false;
		}
		$wptc_options = $wptc_processed_files = $wptc_processed_restored_files = $wptc_backups = $wptc_backup_names = 0;
		foreach ($tables as $table) {
			if (stripos($table, 'wptc_options') !== false) {
				$wptc_options = 1;
			}
			if (stripos($table, 'wptc_processed_files') !== false) {
				$wptc_processed_files = 1;
			}
			if (stripos($table, 'wptc_processed_restored_files') !== false) {
				$wptc_processed_restored_files = 1;
			}
			if (stripos($table, 'wptc_backups') !== false) {
				$wptc_backups = 1;
			}
			if (stripos($table, 'wptc_backup_names') !== false) {
				$wptc_backup_names = 1;
			}
		}
		if ($wptc_options && $wptc_processed_files && $wptc_processed_restored_files &&  $wptc_backups && $wptc_backup_names) {
			dark_debug(array(), '---------Meta verified------------');
			return true;
		} else {
			return false;
			dark_debug(array(), '---------Meta verify failed------------');
		}

	}

	public function send_response($data){
		$response_data = $this->secret_code_start.base64_encode(serialize($data)).$this->secret_code_end;
		die($response_data);
	}

	public function init_restore(){
		require_once $this->staging_abspath.'wp-tcapsule-bridge/bridge_functions.php';
		$data['data'] = $this->params;
		include_config();
		include_files();
		initiate_database();
		initiate_filesystem();
		$this->options_obj = WPTC_Factory::get('config');
		$this->tweak_for_staging();
		start_restore_tc_callback_bridge($data);
	}

	public function tweak_for_staging(){
		$this->add_staging_options();
		$this->remove_unwanted_flags();
	}

	public function add_staging_options(){
		$this->options_obj->set_option('staging_abspath', $this->staging_abspath);
		$this->options_obj->set_option('is_staging_running', 1);
		$this->options_obj->set_option('current_staging_bridge_file_name', 'wptc_staging_controller');
		$this->options_obj->set_option('current_db_prefix', $this->params['current_db_prefix']);
		$this->options_obj->set_option('new_db_prefix', $this->params['new_db_prefix']);
	}

	private function remove_unwanted_flags(){
		$this->options_obj->set_option('is_running', false);
		$this->options_obj->set_option('in_progress', false);
	}

	public function get_staging_abspath(){
		return dirname(dirname(__FILE__)). '/';
	}

	private function new_site_changes(){
		$this->write_new_config_file();
		$this->change_table_prefix_config_file();
		$this->create_default_htaccess();
		$this->discourage_search_engine();
		$this->modify_new_db_changes();
		$this->send_response(array('success' => "staging completed"));
	}

	private function include_wp_config(){
		@include_once $this->staging_abspath.'wp-config.php';
		@include_once $this->staging_abspath.'wp-admin/includes/file.php';
	}

	private function update_in_staging(){
		$this->perform_updates();
	}

	private function rewrite_permalink_structure(){
		$this->include_wp_config();
		global $wp_rewrite;
		$permalink_structure = false;
		$wp_rewrite->set_permalink_structure( $permalink_structure );
		flush_rewrite_rules();
		$this->change_blogname();
		// $this->flush_rewrite_rules_everywhere();
		$this->send_response(array('status' => "success"));
	}

	// private function flush_rewrite_rules_everywhere() {
	// 	if (!is_multisite()) {
	// 		return false;
	// 	}
	// 	global $wp_rewrite;
	// 	$sites = wp_get_sites( array( 'limit' => false ) );
	// 	dark_debug($sites, '--------$sites--------');
	// 	foreach ( $sites as $site_id ) {
	// 		switch_to_blog( $site_id );
	// 		$wp_rewrite->init();

	// 		flush_rewrite_rules();

	// 		restore_current_blog();
	// 	}
	// 	$wp_rewrite->init();
	// }

	private function change_blogname(){
		update_option('blogname', 'STAGING - ' . get_bloginfo( 'name' ));
	}

	private function perform_updates(){
		$this->include_wp_config();
		$this->do_update_after_backup_wptc($this->params['type'], $this->params['update_items']);
	}

	private function do_update_after_backup_wptc($type, $update_items) {
		echo $type;
		print_r($update_items);
		if ($type == 'plugin') {
			$return = $this->upgrade_plugin_wptc($update_items);
		} else if ($type == 'theme') {
			$return = $this->upgrade_theme_wptc($update_items);
		} else if ($type == 'core') {
			$return = $this->upgrade_core_wptc($update_items);
		} else if ($type == 'translation') {
			$return = $this->upgrade_translation_wptc($update_items);
		}
		$this->send_response($return);
	}

	private function wptc_mmb_get_transient($option_name) {
		if (trim($option_name) == '') {
			return FALSE;
		}
		global $wp_version;
		$transient = array();
		if (version_compare($wp_version, '2.7.9', '<=')) {
			return get_option($option_name);
		} else if (version_compare($wp_version, '2.9.9', '<=')) {
			$transient = get_option('_transient_' . $option_name);
			return apply_filters("transient_" . $option_name, $transient);
		} else {
			$transient = get_option('_site_transient_' . $option_name);
			return apply_filters("site_transient_" . $option_name, $transient);
		}
	}

	private function wptc_mmb_get_error($error_object) {
		if (!is_wp_error($error_object)) {
			return $error_object != '' ? $error_object : '';
		} else {
			$errors = array();
			if(!empty($error_object->error_data))  {
				foreach ($error_object->error_data as $error_key => $error_string) {
					$errors[] = str_replace('_', ' ', ucfirst($error_key)) . ': ' . $error_string;
				}
			} elseif (!empty($error_object->errors)){
				foreach ($error_object->errors as $error_key => $err) {
					$errors[] = 'Error: '.str_replace('_', ' ', strtolower($error_key));
				}
			}
			return implode('<br />', $errors);
		}
	}

	private function upgrade_plugin_wptc($plugins, $plugin_details = false) {


		if (!$plugins || empty($plugins)) {
			return array(
				'error' => 'No plugin files for upgrade.', 'error_code' => 'no_plugin_files_for_upgrade'
			);
		}

		@include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		@include_once ABSPATH . 'wp-admin/includes/plugin.php';


		$current = $this->wptc_mmb_get_transient('update_plugins');

		$versions = array();
		if (!empty($current)) {
			foreach ($plugins as $plugin => $data) {
				if (isset($current->checked[$plugin])) {
					$versions[$current->checked[$plugin]] = $plugin;
				} else if (isset($current->response[$plugin])) {
					$versions[$plugin] =  $current->response[$plugin];
				}
			}
		}

		$return = array();

		if (class_exists('Plugin_Upgrader') && class_exists('Bulk_Plugin_Upgrader_Skin')) {
			if (!function_exists('wp_update_plugins'))
				include_once(ABSPATH . 'wp-includes/update.php');

			@wp_update_plugins();
			$upgrader = new Plugin_Upgrader(new Bulk_Plugin_Upgrader_Skin(compact('nonce', 'url')));
			$result = $upgrader->bulk_upgrade(array_keys($plugins));

			$current = $this->wptc_mmb_get_transient('update_plugins');

			if (!empty($result)) {
				foreach ($result as $plugin_slug => $plugin_info) {
					if (!$plugin_info || is_wp_error($plugin_info)) {
						$return[$plugin_slug] = array('error' => $this->wptc_mmb_get_error($plugin_info), 'error_code' => 'upgrade_plugins_wp_error');
					} else {
						if(
							!empty($result[$plugin_slug])
							|| (
									isset($current->checked[$plugin_slug])
									&& version_compare(array_search($plugin_slug, $versions), $current->checked[$plugin_slug], '<') == true
								)
						){
							$return[$plugin_slug] = 1;
						} else {
							$return[$plugin_slug] = array('error' => 'Could not refresh upgrade transients, please reload website data', 'error_code' => 'upgrade_plugins_could_not_refresh_upgrade_transients_please_reload_website_data');
						}
					}
				}
				ob_end_clean();
				return array(
					'upgraded' => $return
				);
			} else {
				return array(
					'error' => 'Upgrade failed.', 'error_code' => 'upgrade_failed_upgrade_plugins'
				);
			}
		} else {
			ob_end_clean();
			return array(
				'error' => 'WordPress update required first.', 'error_code' => 'upgrade_plugins_wordPress_update_required_first'
			);
		}
	}

	private function upgrade_theme_wptc($themes, $theme_details = false) {
		if (!$themes || empty($themes)) {
			return array(
				'error' => 'No theme files for upgrade.', 'error_code' => 'no_theme_files_for_upgrade'
			);
		}

		@include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		@include_once ABSPATH . 'wp-admin/includes/theme.php';

		$current = $this->wptc_mmb_get_transient('update_themes');

		$versions = array();
		if (!empty($current)) {
			foreach ($themes as $theme) {
				if (isset($current->checked[$theme])) {
					$versions[$current->checked[$theme]] = $theme;
				} else if (isset($current->response[$theme])) {
					$versions[$theme] =  $current->response[$theme];
				}
			}
		}

		if (class_exists('Theme_Upgrader') && class_exists('Bulk_Theme_Upgrader_Skin')) {
			$upgrader = new Theme_Upgrader(new Bulk_Theme_Upgrader_Skin(compact('title', 'nonce', 'url', 'theme')));
			$result = $upgrader->bulk_upgrade($themes);

			if (!function_exists('wp_update_themes')) {
				include_once ABSPATH . 'wp-includes/update.php';
			}

			@wp_update_themes();
			$current = $this->wptc_mmb_get_transient('update_themes');
			$return = array();

			if (!empty($result)) {
				foreach ($result as $theme_tmp => $theme_info) {
					 if (is_wp_error($theme_info) || empty($theme_info)) {
						$return[$theme_tmp] = array('error' => $this->wptc_mmb_get_error($theme_info), 'error_code' => 'upgrade_themes_wp_error');
					}  else {
						if(!empty($result[$theme_tmp]) || (isset($current->checked[$theme_tmp]) && version_compare(array_search($theme_tmp, $versions), $current->checked[$theme_tmp], '<') == true)){
							$return[$theme_tmp] = 1;
						} else {
							$return[$theme_tmp] = array('error' => 'Could not refresh upgrade transients, please reload website data', 'error_code' => 'upgrade_themes_could_not_refresh_upgrade_transients_reload_website');
						}
					}
				}
				return array(
					'upgraded' => $return
				);
			} else {
				return array(
					'error' => 'Upgrade failed.', 'error_code' => 'upgrade_failed_upgrade_themes'
				);
			}
		} else {
			ob_end_clean();
			return array(
				'error' => 'WordPress update required first', 'error_code' => 'wordPress_update_required_first_upgrade_themes'
			);
		}
	}

	private function upgrade_core_wptc($current) {

		if (!$current || empty($current)) {
			return array(
				'error' => 'No core data for upgrade.', 'error_code' => 'no_core_files_for_upgrade'
			);
		}

		@include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		@include_once ABSPATH . 'wp-admin/includes/file.php';
		@include_once ABSPATH . 'wp-admin/includes/misc.php';
		@include_once ABSPATH . 'wp-admin/includes/template.php';

		if (!function_exists('wp_version_check') || !function_exists('get_core_checksums')) {
			include_once ABSPATH . '/wp-admin/includes/update.php';
		}

		@wp_version_check();

		$current_update = false;
		ob_end_flush();
		ob_end_clean();
		$core = $this->wptc_mmb_get_transient('update_core');

		if (isset($core->updates) && !empty($core->updates)) {
			$updates = $core->updates[0];
			$updated = $core->updates[0];
			if (!isset($updated->response) || $updated->response == 'latest') {
				return array(
					'upgraded' => 'updated'
				);
			}

			if ($updated->response == "development" && $current->response == "upgrade") {
				return array(
					'error' => '<font color="#900">Unexpected error. Please upgrade manually.</font>', 'error_code' => 'unexpected_error_please_upgrade_manually'
				);
			} else if ($updated->response == $current->response || ($updated->response == "upgrade" && $current->response == "development")) {
				if ($updated->locale != $current->locale) {
					foreach ($updates as $update) {
						if ($update->locale == $current->locale) {
							$current_update = $update;
							break;
						}
					}
					if ($current_update == false) {
						return array(
							'error' => ' Localization mismatch. Try again.', 'error_code' => 'localization_mismatch'
						);
					}
				} else {
					$current_update = $updated;
				}
			} else {
				return array(
					'error' => ' Transient mismatch. Try again.', 'error_code' => 'transient_mismatch'
				);
			}
		} else {
			return array(
				'error' => ' Refresh transient failed. Try again.', 'error_code' => 'refresh_transient_failed'
			);
		}
		if ($current_update != false) {
			global $wp_filesystem, $wp_version;

			if (version_compare($wp_version, '3.1.9', '>')) {
				if (!class_exists('Core_Upgrader')) {
					include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
				}
				$core = new Core_Upgrader();
				$result = $core->upgrade($current_update);
				$this->wptc_mmb_maintenance_mode(false);
				if (is_wp_error($result)) {
					return array(
						'error' => $this->wptc_mmb_get_error($result), 'error_code' => 'maintenance_mode_upgrade_core'
					);
				} else {
					return array(
						'upgraded' => 'updated'
					);
				}
			} else {
				if (!class_exists('WP_Upgrader')) {
					include_once ABSPATH . 'wp-admin/includes/update.php';
					if (function_exists('wp_update_core')) {
						$result = wp_update_core($current_update);
						if (is_wp_error($result)) {
							return array(
								'error' => $this->wptc_mmb_get_error($result), 'error_code' => 'wp_update_core_upgrade_core'
							);
						} else {
							return array(
								'upgraded' => 'updated'
							);
						}
					}
				}
				if (class_exists('WP_Upgrader')) {
					$upgrader_skin = new WP_Upgrader_Skin();
					$upgrader_skin->done_header = true;

					$upgrader = new WP_Upgrader($upgrader_skin);

					// Is an update available?
					if (!isset($current_update->response) || $current_update->response == 'latest') {
						return array(
							'upgraded' => 'updated'
						);
						return false;
					}
					$res = $upgrader->fs_connect(array(
						ABSPATH,
						WP_CONTENT_DIR,
					));
					if (is_wp_error($res)) {
						return array(
							'error' => $this->wptc_mmb_get_error($res), 'error_code' => 'upgrade_core_wp_error_res'
						);
					}
					$wp_dir = trailingslashit($wp_filesystem->abspath());

					$core_package = false;
					if (isset($current_update->package) && !empty($current_update->package)) {
						$core_package = $current_update->package;
					} elseif (isset($current_update->packages->full) && !empty($current_update->packages->full)) {
						$core_package = $current_update->packages->full;
					}

					$download = $upgrader->download_package($core_package);
					if (is_wp_error($download)) {
						return array(
							'error' => $this->wptc_mmb_get_error($download), 'error_code' => 'download_upgrade_core'
						);
					}

					$working_dir = $upgrader->unpack_package($download);
					if (is_wp_error($working_dir)) {
						return array(
							'error' => $this->wptc_mmb_get_error($working_dir), 'error_code' => 'working_dir_upgrade_core'
						);
					}

					if (!$wp_filesystem->copy($working_dir . '/wordpress/wp-admin/includes/update-core.php', $wp_dir . 'wp-admin/includes/update-core.php', true)) {
						$wp_filesystem->delete($working_dir, true);
						return array(
							'error' => 'Unable to move update files.', 'error_code' => 'unable_to_move_update_files'
						);
					}

					$wp_filesystem->chmod($wp_dir . 'wp-admin/includes/update-core.php', FS_CHMOD_FILE);

					require ABSPATH . 'wp-admin/includes/update-core.php';

					$update_core = update_core($working_dir, $wp_dir);

					$this->wptc_mmb_maintenance_mode(false);
					if (is_wp_error($update_core)) {
						return array(
							'error' => $this->wptc_mmb_get_error($update_core), 'error_code' => 'upgrade_core_wp_error'
						);
					}
					ob_end_flush();
					return array(
						'upgraded' => 'updated'
					);
				} else {
					return array(
						'error' => 'failed', 'error_code' => 'failed_WP_Upgrader_class_not_exists'
					);
				}
			}
		} else {
			return array(
				'error' => 'failed', 'error_code' => 'failed_current_update_false'
			);
		}
	}

	private function wptc_mmb_maintenance_mode($enable = false, $maintenance_message = '') {
		global $wp_filesystem;
		if (!$wp_filesystem) {
			initiate_filesystem_wptc();
			if (empty($wp_filesystem)) {
				send_response_wptc('FS_INIT_FAILED-015');
				return false;
			}
		}
		$maintenance_message .= '<?php $upgrading = ' . time() . '; ?>';

		$file = $wp_filesystem->abspath() . '.maintenance';
		if ($enable) {
			$wp_filesystem->delete($file);
			$wp_filesystem->put_contents($file, $maintenance_message, FS_CHMOD_FILE);
		} else {
			$wp_filesystem->delete($file);
		}
	}


	private function initiate_filesystem_wptc() {
		$creds = request_filesystem_credentials("", "", false, false, null);
		if (false === $creds) {
			return false;
		}

		if (!WP_Filesystem($creds)) {
			return false;
		}
	}

	private function upgrade_translation_wptc($data = false) {

		@include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		@include_once ABSPATH . 'wp-admin/includes/file.php';
		@include_once ABSPATH . 'wp-admin/includes/misc.php';
		@include_once ABSPATH . 'wp-admin/includes/template.php';
		@include_once ABSPATH . 'wp-admin/includes/plugin.php';
		@include_once ABSPATH . 'wp-admin/includes/theme.php';

		if (!function_exists('wp_version_check') || !function_exists('get_core_checksums')) {
			include_once ABSPATH . '/wp-admin/includes/update.php';
		}

		$upgrader = new Language_Pack_Upgrader(new Language_Pack_Upgrader_Skin(compact('url', 'nonce', 'title', 'context')));
		$result = $upgrader->bulk_upgrade();
		$upgradeFailed = false;

		if (!empty($result)) {
			foreach ($result as $translate_tmp => $translate_info) {
				if (is_wp_error($translate_info) || empty($translate_info)) {
					$upgradeFailed = true;
					$return = array('error' => $this->wptc_mmb_get_error($translate_info), 'error_code' => 'upgrade_translations_wp_error');
					break;
				}
			}
			if (!$upgradeFailed) {
				$update_message = 'Translations are updated successfully ';
				$return = 'updated';
			}
			return array('upgraded' => $return);
		} else {
			return array(
				'error' => 'Upgrade failed.', 'error_code' => 'unable_to_update_translations_files'
			);
		}
	}

	private function clear_staging_flags(){
		require_once $this->staging_abspath.'wp-tcapsule-bridge/bridge_functions.php';
		include_config();
		include_files();
		initiate_database();
		initiate_filesystem();
		$this->options_obj = WPTC_Factory::get('config');
		$this->options_obj->set_option('meta_restore_completed', false);
		$this->options_obj->set_option('restore_db_index', false);
		$this->options_obj->set_option('last_index', false);
	}

	private function create_default_htaccess(){
		$staging_abspath = $this->options_obj->get_option('staging_abspath');
		echo "staging_abspath".$staging_abspath;
		$args    = parse_url($this->params['destination_url']);
		echo "args :" .$args;
		$string  = rtrim($args['path'], "/");
		$data = "# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase ".$string."/\nRewriteRule ^index\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . ".$string."/index.php [L]\n</IfModule>\n# END WordPress";
		@file_put_contents($staging_abspath. '/' .'.htaccess', $data);
	}

	private function discourage_search_engine(){
		$staging_abspath = $this->options_obj->get_option('staging_abspath');
		$data = "User-agent: *\nDisallow: /\n";
		@file_put_contents($staging_abspath. '/' .'robots.txt', $data);
	}

	private function delete_bridge_files($new_site_change = 0){
		require_once $this->staging_abspath.'wp-tcapsule-bridge/bridge_functions.php';
		include_config();
		include_files();
		initiate_database();
		initiate_filesystem();
		$this->options_obj = WPTC_Factory::get('config');
		$current_bridge_file_name = $this->options_obj->get_option('current_bridge_file_name');
		$current_staging_bridge_file_name = $this->options_obj->get_option('current_staging_bridge_file_name');
		if (!empty($current_bridge_file_name)) {
			$root_bridge_file_path = $this->staging_abspath . $current_bridge_file_name;
			$this->options_obj->delete_files_by_path($root_bridge_file_path, $options);
		}
		if (!empty($current_staging_bridge_file_name)) {
			$root_bridge_file_path = $this->staging_abspath . $current_staging_bridge_file_name;
			$this->options_obj->delete_files_by_path($root_bridge_file_path, $options);
		}
		if($new_site_change){
			$this->delete_wptc_db($dont_print = 0);
		} else {
			$this->delete_wptc_db($dont_print = 1);
		}
	}

	private function change_table_prefix_config_file() {
		//select wp-config-sample.php
		// not yet tested - please test it before use this function
		require_once $this->staging_abspath.'wp-tcapsule-bridge/bridge_functions.php';
		include_config();
		include_files();
		initiate_database();
		$this->options_obj = WPTC_Factory::get('config');
		$db_table_prefix = $this->options_obj->get_option('new_db_prefix');
		$wp_config_file = $this->staging_abspath. '/wp-config.php';

		if (@rename($wp_config_file, $this->staging_abspath.'/wp-config-temp.php')) {
			$lines = file($this->staging_abspath.'/wp-config-temp.php');
			@unlink($this->staging_abspath.'/wp-config-temp.php');
		} else {
			$lines = @file($this->staging_abspath.'/wp-config-sample.php');
		}

		@unlink($this->staging_abspath.'/wp-config.php');

		if (empty($lines)){
			$this->send_response(array('error' => "Error: Cannot recreate wp-config.php file."));
		}

		$file_success = false;

		foreach ($lines as $line) {
			if ($db_table_prefix && strstr($line, '$table_prefix')) {
				$line         = "\$table_prefix = '$db_table_prefix';\n";
				$file_success = true;
			}
			if (file_put_contents($this->staging_abspath.'/wp-config.php', $line, FILE_APPEND) === FALSE){
				$this->send_response(array('error' => "Error: Cannot write wp-config.php file"));
			}
		}
		return $file_success;
	}

	private function write_new_config_file(){
		$this->include_config_like();
		$lines = @file($this->staging_abspath.'/wp-config.php');
		if(empty($lines)){
			$lines = @file($this->staging_abspath.'/wp-config-sample.php');
		}
		@unlink($this->staging_abspath.'/wp-config.php'); // Unlink if a config already exists
		if(empty($lines)){
				$this->send_response(array('error' => "Please replace wp-config.php. It seems missing"));
		}
		foreach ($lines as $line) {
			if (strstr($line, 'DB_NAME')){
				$line = "define('DB_NAME', '".DB_NAME."');\n";
			}
			if (strstr($line, 'DB_USER')){
				$line = "define('DB_USER', '".DB_USER."');\n";
			}
			if (strstr($line, 'DB_PASSWORD')){
				$line = "define('DB_PASSWORD', '".DB_PASSWORD."');\n";
			}
			if (strstr($line, 'DB_HOST')){
				$line = "define('DB_HOST', '".DB_HOST."');\n";
			}
			if (strstr($line, 'WP_HOME') || strstr($line, 'WP_SITEURL')){
				$line = "";
			}
			if(file_put_contents($this->staging_abspath.'/wp-config.php', $line, FILE_APPEND) === FALSE){
				$this->send_response(array('error' => "Permission denied to write the config file."));
			}
		}
	}

	private function modify_new_db_changes(){
		require_once $this->staging_abspath.'wp-tcapsule-bridge/bridge_functions.php';
		include_config();
		include_files();
		initiate_database();
		$this->options_obj = WPTC_Factory::get('config');
		$db_table_prefix = $this->options_obj->get_option('new_db_prefix');
		$old_table_prefix = $this->options_obj->get_option('current_db_prefix');
		$new_url = $this->params['destination_url'];
		global $wpdb;

		$query =  "SELECT option_value FROM " . $db_table_prefix . "options  WHERE option_name = 'siteurl' LIMIT 1";
		$result = $wpdb->get_var($query);
		$old_url = $this->removeTrailingSlash($result);
		$query = "UPDATE " . $db_table_prefix . "options SET option_value = '".$new_url."' WHERE option_name = 'home'";
		$update_result = $wpdb->query($query);

		$query = "UPDATE " . $db_table_prefix . "options  SET option_value = '".$new_url."' WHERE option_name = 'siteurl'";
		$siteurl = $wpdb->query($query);


		//Replace the post contents
		$query = "UPDATE " . $db_table_prefix . "posts SET post_content = REPLACE (post_content, '$old_url','$new_url') WHERE post_content REGEXP 'src=\"(.*)$old_url(.*)\"' OR post_content REGEXP 'href=\"(.*)$old_url(.*)\"'";
		$post_changes = $wpdb->query($query);

		//Reset media upload settings
		$query = "UPDATE " . $db_table_prefix . "options SET option_value = '' WHERE option_name = 'upload_path' OR option_name = 'upload_url_path'";
		$wpdb->query($query);

		//change prefix for user capabilities
		$query = "UPDATE " . $db_table_prefix . "options SET option_name = '" . $db_table_prefix . "user_roles'	WHERE option_name = '" . $old_table_prefix . "user_roles'	LIMIT 1";
		$wpdb->query($query);

		$query = "UPDATE " . $db_table_prefix . "usermeta SET meta_key = CONCAT('" . $db_table_prefix . "', SUBSTR(meta_key, CHAR_LENGTH('" . $old_table_prefix . "') + 1))	WHERE meta_key LIKE '" . $old_table_prefix . "%'";
		$wpdb->query($query);
	}

	private function source_destination_check(){
		$this->send_response(array('success' => "hand_shake_done"));
	}

	private function delete_staging(){
		$this->delete_staging_db();
		$this->delete_staging_site();
	}

	private function delete_staging_db(){
		$table_prefix = $this->params['db_prefix'];
		$this->check_db_connection($dont_die = 1);
		global $wpdb;
		$tables_delete_query = "SELECT CONCAT( 'DROP TABLE ', GROUP_CONCAT(table_name) , ';' )
		AS statement FROM information_schema.tables
		WHERE table_schema = '".$this->params['db_name']."' AND table_name LIKE '$table_prefix%';";
		$query_result = $wpdb->get_results($tables_delete_query, ARRAY_N);
		if (isset($query_result[0][0])) {
			$wpdb->query($query_result[0][0]);
			// echo $query_result[0][0];
		}
		echo "database_deleted_wptc";
	}

	private function delete_staging_site(){
		$site_parent_folder = dirname(dirname(__FILE__));
		$this->delete($site_parent_folder, true);
		echo "files_deleted_wptc";
	}

	private function delete($file, $recursive = false, $type = false) {
		if ( empty($file) ) //Some filesystems report this as /, which can cause non-expected recursive deletion of all files in the filesystem.
			return false;
		$file = str_replace('\\', '/', $file); //for win32, occasional problems deleting files otherwise
		if ( 'f' == $type || $this->isFile($file) ){
			return @unlink($file);
		}
		if ( ! $recursive && $this->isDir($file) ){
			return @rmdir($file);
		}

		//At this point its a folder, and we're in recursive mode
		$file = $this->addTrailingSlash($file);
		$fileList = $this->dirList($file, true);

		$retval = true;
		if ( is_array($fileList) ) //false if no files, So check first.
			foreach ($fileList as $fileName => $fileinfo){
				echo "recursive_delete \n ". $file.' '.$fileName."\n";
				if ( ! $this->delete($file . $fileName, $recursive, $fileinfo['type']) )
					$retval = false;
			}
		if ( file_exists($file) && ! @rmdir($file) )
			$retval = false;
		return $retval;
	}

	private function isFile($file) {
		return @is_file($file);
	}

	private function isDir($path) {
		return @is_dir($path);
	}

	private function dirList($path, $includeHidden = true, $recursive = false) {
		if ( $this->isFile($path) ) {
			$limitFile = basename($path);
			$path = dirname($path);
		} else {
			$limitFile = false;
		}

		if ( ! $this->isDir($path) )
		return false;

		$dir = @dir($path);
		if ( ! $dir )
		return false;

		$ret = array();

		while (false !== ($entry = $dir->read()) ) {
			$struc = array();
			$struc['name'] = $entry;

			if ( '.' == $struc['name'] || '..' == $struc['name'] )
			continue;

			if ( ! $includeHidden && '.' == $struc['name'][0] )
			continue;

			if ( $limitFile && $struc['name'] != $limitFile)
			continue;

			$struc['perms']   = $this->getHChmod($path.'/'.$entry);
			$struc['permsn']  = $this->getNumChmodFromH($struc['perms']);
			$struc['number']  = false;
			$struc['owner']     = $this->owner($path.'/'.$entry);
			$struc['group']     = $this->group($path.'/'.$entry);
			$struc['size']      = $this->size($path.'/'.$entry);
			$struc['lastmodunix']= $this->mtime($path.'/'.$entry);
			$struc['lastmod']   = @date('M j',$struc['lastmodunix']);
			$struc['time']      = @date('h:i:s',$struc['lastmodunix']);
			$struc['type']    = $this->isDir($path.'/'.$entry) ? 'd' : 'f';

			if ( 'd' == $struc['type'] ) {
				if ( $recursive )
					$struc['files'] = $this->dirList($path . '/' . $struc['name'], $includeHidden, $recursive);
				else
					$struc['files'] = array();
			}

			$ret[ $struc['name'] ] = $struc;
		}
		$dir->close();
		unset($dir);
		return $ret;
	}

	private function size($file) {
		return @filesize($file);
	}

	private function mtime($file) {
		return @filemtime($file);
	}

	private function getNumChmodFromH($mode) {
		$realMode = '';
		$legal =  array('', 'w', 'r', 'x', '-');
		$attArray = preg_split('//', $mode);

		for ($i=0; $i < count($attArray); $i++)
		if ($key = array_search($attArray[$i], $legal))
			$realMode .= $legal[$key];

		$mode = str_pad($realMode, 9, '-');
		$trans = array('-'=>'0', 'r'=>'4', 'w'=>'2', 'x'=>'1');
		$mode = strtr($mode,$trans);

		$newmode = '';
		$newmode .= $mode[0] + $mode[1] + $mode[2];
		$newmode .= $mode[3] + $mode[4] + $mode[5];
		$newmode .= $mode[6] + $mode[7] + $mode[8];
		return $newmode;
	}

	private function group($file) {
		$gid = @filegroup($file);
		if ( ! $gid )
			return false;
		if ( ! function_exists('posix_getgrgid') )
			return $gid;
		$grouparray = posix_getgrgid($gid);
		return $grouparray['name'];
	}

	private function owner($file) {
		$owneruid = @fileowner($file);
		if ( ! $owneruid )
			return false;
		if ( ! function_exists('posix_getpwuid') )
			return $owneruid;
		$ownerarray = posix_getpwuid($owneruid);
		return $ownerarray['name'];
	}

	private function getChmod($file) {
		return substr(decoct(@fileperms($file)),3);
	}

	private function getHChmod($file){
		$perms = $this->getChmod($file);
		if (($perms & 0xC000) == 0xC000) // Socket
			$info = 's';
		elseif (($perms & 0xA000) == 0xA000) // Symbolic Link
			$info = 'l';
		elseif (($perms & 0x8000) == 0x8000) // Regular
			$info = '-';
		elseif (($perms & 0x6000) == 0x6000) // Block special
			$info = 'b';
		elseif (($perms & 0x4000) == 0x4000) // Directory
			$info = 'd';
		elseif (($perms & 0x2000) == 0x2000) // Character special
			$info = 'c';
		elseif (($perms & 0x1000) == 0x1000) // FIFO pipe
			$info = 'p';
		else // Unknown
			$info = 'u';

		// Owner
		$info .= (($perms & 0x0100) ? 'r' : '-');
		$info .= (($perms & 0x0080) ? 'w' : '-');
		$info .= (($perms & 0x0040) ?
		(($perms & 0x0800) ? 's' : 'x' ) :
		(($perms & 0x0800) ? 'S' : '-'));

		// Group
		$info .= (($perms & 0x0020) ? 'r' : '-');
		$info .= (($perms & 0x0010) ? 'w' : '-');
		$info .= (($perms & 0x0008) ?
		(($perms & 0x0400) ? 's' : 'x' ) :
		(($perms & 0x0400) ? 'S' : '-'));

		// World
		$info .= (($perms & 0x0004) ? 'r' : '-');
		$info .= (($perms & 0x0002) ? 'w' : '-');
		$info .= (($perms & 0x0001) ?
		(($perms & 0x0200) ? 't' : 'x' ) :
		(($perms & 0x0200) ? 'T' : '-'));
		return $info;
	}
}