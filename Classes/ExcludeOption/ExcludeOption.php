<?php

class Wptc_ExcludeOption extends Wptc_Exclude {
	protected $config;
	protected $logger;
	private $cron_server_curl;
	private $default_wp_folders;
	private $default_wp_files;
	private $db;
	private $default_exclude_files;
	private $processed_files;
	private $bulk_limit;
	private $default_wp_files_n_folders;
	private $excluded_files;
	private $included_files;
	private $excluded_tables;
	private $included_tables;
	public function __construct() {
		$this->db = WPTC_Factory::db();
		$this->bulk_limit = 500;
		$this->processed_files = WPTC_Factory::get('processed-files');
		$this->default_exclude_files = get_dirs_to_exculde_wptc();
		$this->default_wp_folders = array(
						WPTC_ABSPATH.'wp-admin',
						WPTC_ABSPATH.'wp-includes',
						WPTC_WP_CONTENT_DIR,
					);
		$this->default_wp_files = array(
						WPTC_ABSPATH.'favicon.ico',
						WPTC_ABSPATH.'index.php',
						WPTC_ABSPATH.'license.txt',
						WPTC_ABSPATH.'readme.html',
						WPTC_ABSPATH.'robots.txt',
						WPTC_ABSPATH.'sitemap.xml',
						WPTC_ABSPATH.'wp-activate.php',
						WPTC_ABSPATH.'wp-blog-header.php',
						WPTC_ABSPATH.'wp-comments-post.php',
						WPTC_ABSPATH.'wp-config-sample.php',
						WPTC_ABSPATH.'wp-config.php',
						WPTC_ABSPATH.'wp-cron.php',
						WPTC_ABSPATH.'wp-links-opml.php',
						WPTC_ABSPATH.'wp-load.php',
						WPTC_ABSPATH.'wp-login.php',
						WPTC_ABSPATH.'wp-mail.php',
						WPTC_ABSPATH.'wp-settings.php',
						WPTC_ABSPATH.'wp-signup.php',
						WPTC_ABSPATH.'wp-trackback.php',
						WPTC_ABSPATH.'xmlrpc.php',
						WPTC_ABSPATH.'.htaccess',
						WPTC_ABSPATH.'google',
					);
		$this->default_wp_files_n_folders = array_merge($this->default_wp_folders, $this->default_wp_files);
		$this->load_exc_inc_files();
		$this->load_exc_inc_tables();
		$this->config = WPTC_Base_Factory::get('Wptc_Exclude_Config');
	}

	private function load_exc_inc_files(){
		$this->excluded_files = $this->get_exlcuded_files_list();
		$this->included_files = $this->get_included_files_list();
	}

	private function load_exc_inc_tables(){
		$this->excluded_tables = $this->get_exlcuded_tables_list();
		$this->included_tables = $this->get_included_tables_list();
	}

	public function insert_default_excluded_files(){
		$status = $this->config->get_option('insert_default_excluded_files');
		if ($status) {
			return false;
		}
		$files = $this->format_excluded_files($this->default_exclude_files);
		foreach ($files as $file) {
			$this->exclude_file_list($file, true);
		}
		$this->config->set_option('insert_default_excluded_files', true);
	}

	private function format_excluded_files($files){
		$selected_files = array();
		if (empty($files)) {
			return false;
		}
		foreach ($files as $file) {
			if (override_is_dir($file)) {
				$selected_files[] = array(
								"id" => NULL,
								"file" => $file,
								"isdir" => 1,
							);
			} else {
				$selected_files[] = array(
								"id" => NULL,
								"file" => $file,
								"isdir" => 0,
							);
			}
		}
		return $selected_files;
	}

	public function update_default_excluded_files_list(){
		$uploadDir = get_uploadDir();
		$upload_dir_path = wp_normalize_path($uploadDir['basedir']);
		$files_index = array(
			'1.5.3' => 'wptc_1_5_3',
			'1.8.0' => 'wptc_1_8_0',
			'1.8.2' => 'wptc_1_8_2',
			'1.9.0' => 'wptc_1_9_0',
			'1.9.4' => 'wptc_1_9_4',
			);
		$wptc_1_5_3 = array(
			trim(WP_CONTENT_DIR)."/nfwlog",
			trim(WP_CONTENT_DIR)."/debug.log",
			trim(WP_CONTENT_DIR)."/wflogs",
			$uploadDir['basedir']."/siteorigin-widgets",
			$uploadDir['basedir']."/wp-hummingbird-cache",
			$uploadDir['basedir']."/wp-security-audit-log",
			$uploadDir['basedir']."/freshizer",
			$uploadDir['basedir']."/db-backup",
			$uploadDir['basedir']."/backupbuddy_backups",
			$uploadDir['basedir']."/vcf",
			$uploadDir['basedir']."/pb_backupbuddy",
			WPTC_ABSPATH."wp-admin/error_log",
			WPTC_ABSPATH."wp-admin/php_errorlog",
			);

		$wptc_1_8_0 = array(
			trim(WP_CONTENT_DIR)."/DE_cl_dev_log_auto_update.txt",
			);

		$wptc_1_8_2 = array(
			trim(WP_CONTENT_DIR)."/Dropbox_Backup",
			trim(WP_CONTENT_DIR)."/backup-db",
			trim(WP_CONTENT_DIR)."/updraft",
			$uploadDir['basedir']."/report-cache",
			);

		$wptc_1_9_0 = array(
			trim(WP_CONTENT_DIR)."/w3tc-config",
			$uploadDir['basedir']."/ithemes-security",
			$uploadDir['basedir']."/cache",
			$uploadDir['basedir']."/et_temp",
			);
		$wptc_1_9_4 = array(
			trim(WP_CONTENT_DIR)."/aiowps_backups",
			);
		$prev_wptc_version =  $this->config->get_option('prev_installed_wptc_version');
		if (empty($prev_wptc_version)) {
			return false;
		}
		$required_files = array();
		foreach ($files_index as $key => $value) {
			if (version_compare($prev_wptc_version, $key, '<') && version_compare(WPTC_VERSION, $key, '>=')) {
				$required_files = array_merge($required_files, ${$files_index[$key]});
			}
		}
		return $required_files;
	}

	public function update_default_excluded_files(){
		$status = $this->config->get_option('update_default_excluded_files');
		if ($status) {
			return false;
		}
		$new_default_exclude_files = $this->update_default_excluded_files_list();
		if (empty($new_default_exclude_files)) {
			$this->config->set_option('update_default_excluded_files', true);
			return false;
		}
		$files = $this->format_excluded_files($new_default_exclude_files);
		foreach ($files as $file) {
			$this->exclude_file_list($file, true);
		}
		$this->config->set_option('update_default_excluded_files', true);
	}

	public function get_tables($exc_wp_tables = false) {
		$tables = $this->processed_files->get_all_tables();
		if ($exc_wp_tables && !$this->config->get_option('non_wp_tables_excluded')) {
			$this->exclude_non_wp_tabes($tables);
			$this->load_exc_inc_tables();
			$this->config->set_option('non_wp_tables_excluded', true);
		}
		$tables_arr = array();
		dark_debug($tables, '---------------$tables-----------------');
		foreach ($tables as $table) {
			$excluded = $this->is_excluded_table($table);
			if ($excluded) {
				$temp = array(
					'title' => $table,
					'key' => $table,
					'size' => $this->processed_files->get_table_size($table),
				);
			} else {
				$temp = array(
					'title' => $table,
					'key' => $table,
					'size' => $this->processed_files->get_table_size($table),
					'preselected' => true,
				);
			}
			$temp['size_in_bytes'] = $this->processed_files->get_table_size($table, 0);
			$tables_arr[] = $temp;
		}
		die(json_encode($tables_arr));
	}

	public function get_root_files($exc_wp_files = false) {
		// $this->got_exclude_files(2);
		$path = get_tcsanitized_home_path();
		$result_obj = $this->get_files_by_path($path);
		if ($exc_wp_files && !$this->config->get_option('non_wp_files_excluded')) {
			$this->exclude_non_wp_files($result_obj);
			$this->load_exc_inc_files();
			$this->config->set_option('non_wp_files_excluded', true);
		}

		$result = $this->format_result_data($result_obj);
		die(json_encode($result));
	}

	public function update_default_files_n_tables(){
		$this->config->set_option('insert_default_excluded_files', false);

		$this->insert_default_excluded_files();

		// //files
		// $path = get_tcsanitized_home_path();
		// $result_obj = $this->get_files_by_path($path);
		// $this->exclude_non_wp_files($result_obj);
		// $this->load_exc_inc_files();

		// //tables
		// $tables = $this->processed_files->get_all_tables();
		// $this->exclude_non_wp_tabes($tables);
		// $this->load_exc_inc_tables();
	}

	private function exclude_non_wp_files($file_obj){
		$selected_files = array();
		foreach ($file_obj as $Ofiles) {
			$file_path = $Ofiles->getPathname();
			$file_name = basename($file_path);
			if ($file_name == '.' || $file_name == '..') {
				continue;
			}
			if(!$this->is_wp_file($file_path)){
				$isdir = override_is_dir($file_path);
				$this->exclude_file_list(array('file'=> $file_path, 'isdir' => $isdir ), true);
			}
		}
	}

	private function exclude_non_wp_tabes($tables){
		foreach ($tables as $table) {
			if (!$this->is_wp_table($table)) {
				$this->exclude_table_list(array('file' => $table), true);
			}
		}
	}

	public function get_files_by_key($path) {
		$result_obj = $this->get_files_by_path($path);
		$result = $this->format_result_data($result_obj);
		die(json_encode($result));
	}

	public function get_files_by_path($path, $deep = 0){
		$path = rtrim($path, '/');
		$source = realpath($path);
		$obj = null;
		$basename = basename($path);
		if ($basename == '..' || $basename == '.') {
			return false;
		}

		if (empty($source)) {
			return false;
		}

		if (!is_readable($source)) {
			return false;
		}

		if(empty($source)){
			return array();
		}

		if($deep){
				$obj =  new RecursiveIteratorIterator(
			  new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD
			);
		}else {
			$obj =  new RecursiveIteratorIterator(
			  new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::CATCH_GET_CHILD
			);
		}

		return $obj;
	}

	private function format_result_data($file_obj){
		$files_arr	= array();
		if (empty($file_obj)) {
			return false;
		}
		foreach ($file_obj as $Ofiles) {
			$file_path = $Ofiles->getPathname();
			$file_name = basename($file_path);
			if ($file_name == '.' || $file_name == '..') {
				continue;
			}
			if (!$Ofiles->isReadable()) {
				continue;
			}
			$file_size = $Ofiles->getSize();
			$temp = array(
					'title' => basename($file_name),
					'key' => $file_path,
					'size' => $this->processed_files->convert_bytes_to_hr_format($file_size),
				);
			$is_dir = override_is_dir($file_path);
			if ($is_dir) {
				$is_excluded = $this->is_excluded_file($file_path, true);
				$temp['folder'] = true;
				$temp['lazy'] = true;
				$temp['size'] = '';
			} else {
				$is_excluded = $this->is_excluded_file($file_path, false);
				$temp['false'] = false;
				$temp['folder'] = false;
				$temp['size_in_bytes'] = $Ofiles->getSize();
			}
			if($is_excluded){
				$temp['partial'] = false;
				$temp['preselected'] = false;
			} else {
				$temp['preselected'] = true;
			}

			$files_arr[] = $temp;
		}
		$this->sort_by_folders($files_arr);
		// dark_debug($files_arr, '---------------$files_arr-----------------');
		return $files_arr;
	}

	private function sort_by_folders(&$files_arr) {
		if (empty($files_arr) || !is_array($files_arr)) {
			return false;
		}
		foreach ($files_arr as $key => $row) {
			$volume[$key]  = $row['folder'];
		}
		array_multisort($volume, SORT_DESC, $files_arr);
	}

	public function exclude_file_list($data, $do_not_die = false){
		if (empty($data['file'])) {
			return false;
		}
		$data['file'] = wp_normalize_path($data['file']);
		if ($data['isdir']) {
			$this->remove_include_files($data['file'], 1);
			$this->remove_exclude_files($data['file'], 1);
		} else {
			$this->remove_exclude_files($data['file']);
			$this->remove_include_files($data['file']);
		}

		$result = $this->db->insert("{$this->db->base_prefix}wptc_excluded_files", $data);

		if($do_not_die){
			return true;
		}

		if ($result) {
			die_with_json_encode(array('status' => 'success'));
		}
		die_with_json_encode(array('status' => 'error'));
	}

	private function remove_include_files($file, $force = false){
		if (empty($file)) {
			return false;
		}
		if ($force) {
			$re_sql = $this->db->prepare("DELETE FROM {$this->db->base_prefix}wptc_included_files WHERE file LIKE '%%%s%%'", $file);
		} else{
			$re_sql = $this->db->prepare("DELETE FROM {$this->db->base_prefix}wptc_included_files WHERE file = %s", $file);
		}
		$result = $this->db->query($re_sql);
	}

	public function include_file_list($data){
		if (empty($data['file'])) {
			return false;
		}
		$data['file'] = wp_normalize_path($data['file']);
		if ($data['isdir']) {
			$this->remove_exclude_files($data['file'], 1);
			$this->remove_include_files($data['file'], 1);
		} else {
			$this->remove_include_files($data['file']);
			$this->remove_exclude_files($data['file']);
		}
		if ($this->is_wp_file($data['file'])) {
			dark_debug(array(), '---------------wordpress folder cannot be inserted ----------------');
			die_with_json_encode(array('status' => 'success'));
			return false;
		}

		$result = $this->db->insert("{$this->db->base_prefix}wptc_included_files", $data);

		if ($result) {
			die_with_json_encode(array('status' => 'success'));
		}
		die_with_json_encode(array('status' => 'error'));
	}

	private function remove_exclude_files($file, $force = false){
		if (empty($file)) {
			return false;
		}

		if ($force) {
			$re_sql = $this->db->prepare("DELETE FROM {$this->db->base_prefix}wptc_excluded_files WHERE file LIKE '%%%s%%'", $file);
		} else{
			$re_sql = $this->db->prepare("DELETE FROM {$this->db->base_prefix}wptc_excluded_files WHERE file = %s", $file);
		}
		$result = $this->db->query($re_sql);
	}

	private function is_wp_file($file){
		if (empty($file)) {
			return false;
		}
		$file = wp_normalize_path($file);
		foreach ($this->default_wp_files_n_folders as $path) {
			if(strpos($file, $path) !== false){
				return true;
			}
		}
		return false;
	}

	public function is_excluded_file($file, $is_dir = false){
		if (empty($file)) {
			return true;
		}
		$file = wp_normalize_path($file);
		$found = false;
		if ($this->is_wp_file($file)) {
			return $this->exclude_file_check_deep($file);
		}
		if (!$this->is_included_file($file)) {
			return true;
		} else {
			return $this->exclude_file_check_deep($file);
		}
	}

	private function exclude_file_check_deep($file){
		foreach ($this->excluded_files as $value) {
			$value = str_replace('(', '-', $value);
			$value = str_replace(')', '-', $value);
			$file = str_replace('(', '-', $file);
			$file = str_replace(')', '-', $file);
			if(strpos($file.'/', $value.'/') === 0){
				return true;
			}
		}
		return false;
	}

	private function get_exlcuded_files_list(){
		$raw_data = $this->db->get_results("SELECT file FROM {$this->db->base_prefix}wptc_excluded_files", ARRAY_N);
		if (empty($raw_data)) {
			return array();
		}
		$result = array();
		foreach ($raw_data as $value) {
			$result[] = $value[0];
		}
		return empty($result) ? array() : $result;
	}

	private function get_included_files_list(){
		$raw_data = $this->db->get_results("SELECT file FROM {$this->db->base_prefix}wptc_included_files", ARRAY_N);
		if (empty($raw_data)) {
			return array();
		}
		$result = array();
		foreach ($raw_data as $value) {
			$result[] = $value[0];
		}
		return empty($result) ? array() : $result;
	}

	private function is_included_file($file, $is_dir = false){
		$found = false;
		$file = wp_normalize_path($file);
		foreach ($this->included_files as $value) {
			$value = str_replace('(', '-', $value);
			$value = str_replace(')', '-', $value);
			$file = str_replace('(', '-', $file);
			$file = str_replace(')', '-', $file);
			if(strpos($file.'/', $value.'/') === 0){
				$found = true;
				break;
			}
		}
		return $found;
	}

	private function is_included_file_deep($file, $is_dir = false){
		$found = false;
		foreach ($this->included_files as $value) {
			if ($value === $file) {
				$found = true;
				break;
			}
		}
		return $found;
	}

	//table related functions
	public function exclude_table_list($data, $do_not_die = false){
		if (empty($data['file'])) {
			return false;
		}

		$this->remove_exclude_table($data['file']);
		$this->remove_include_table($data['file']);

		$table_arr['id'] = NULL;
		$table_arr['table_name'] = $data['file'];
		$result = $this->db->insert("{$this->db->base_prefix}wptc_excluded_tables", $table_arr);
		if ($do_not_die) {
			return false;
		}
		if ($result) {
			die_with_json_encode(array('status' => 'success'));
		}
		die_with_json_encode(array('status' => 'error'));
	}

	private function remove_include_table($table, $force = false){
		if (empty($table)) {
			return false;
		}
		$re_sql = $this->db->prepare("DELETE FROM {$this->db->base_prefix}wptc_included_tables WHERE table_name = %s", $table);
		$result = $this->db->query($re_sql);
	}

	public function include_table_list($data){
		if (empty($data['file'])) {
			return false;
		}
		$this->remove_exclude_table($data['file']);
		$this->remove_include_table($data['file']);
		if ($this->is_wp_table($data['file'])) {
			dark_debug($data['file'], '---------------Wordpress table so cannot be inserted-----------------');
			die_with_json_encode(array('status' => 'success'));
		}
		$table_arr['id'] = NULL;
		$table_arr['table_name'] = $data['file'];
		$result = $this->db->insert("{$this->db->base_prefix}wptc_included_tables", $table_arr);
		if ($result) {
			die_with_json_encode(array('status' => 'success'));
		}
		die_with_json_encode(array('status' => 'error'));
	}

	private function remove_exclude_table($table, $force = false){
		if (empty($table)) {
			return false;
		}

		$re_sql = $this->db->prepare("DELETE FROM {$this->db->base_prefix}wptc_excluded_tables WHERE table_name = %s", $table);
		$result = $this->db->query($re_sql);
	}

	private function is_wp_table($table){
		if (preg_match('#^'.$this->db->base_prefix.'#', $table) === 1) {
			return true;
		}
		return false;
	}

	private function get_exlcuded_tables_list(){
		$raw_data = $this->db->get_results("SELECT table_name FROM {$this->db->base_prefix}wptc_excluded_tables", ARRAY_N);
		if (empty($raw_data)) {
			return array();
		}
		$result = array();
		foreach ($raw_data as $value) {
			$result[] = $value[0];
		}
		return empty($result) ? array() : $result;
	}

	private function get_included_tables_list(){
		$raw_data = $this->db->get_results("SELECT table_name FROM {$this->db->base_prefix}wptc_included_tables", ARRAY_N);
		if (empty($raw_data)) {
			return array();
		}
		$result = array();
		foreach ($raw_data as $value) {
			$result[] = $value[0];
		}
		return empty($result) ? array() : $result;
	}

	public function is_excluded_table($table){
		if (empty($table)) {
			return true;
		}
		if($this->is_wp_table($table)){
			return $this->exclude_table_check_deep($table);
		}
		if ($this->is_included_table($table)) {
			return false;
		}
		return true;
	}

	private function exclude_table_check_deep($table){
		foreach ($this->excluded_tables as $value) {
			if (preg_match('#^'.$value.'#', $table) === 1) {
				return true;
			}
		}
		return false;
	}

	private function is_included_table($table){
		foreach ($this->included_tables as $value) {
			if (preg_match('#^'.$value.'#', $table) === 1) {
				return true;
			}
		}
	}

}