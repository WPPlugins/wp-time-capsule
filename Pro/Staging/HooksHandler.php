<?php

class Wptc_Staging_Hooks_Hanlder extends Wptc_Base_Hooks_Handler{
	const JS_URL = '/Pro/Staging/init.js';
	const CSS_URL = '/Pro/Staging/style.css';
	protected $staging;
	protected $config;

	public function __construct() {
		$this->staging = WPTC_Pro_Factory::get('Wptc_Staging');
		$this->config = WPTC_Pro_Factory::get('Wptc_staging_Config');
	}

	public function page_settings_tab($tabs){
		$tabs['staging'] = __( 'Staging', 'wp-time-capsule' );
		return $tabs;
	}

	public function check_staging_ftp_creds_wptc($args) {
		dark_debug($args, '-------check_staging_ftp_creds_wptc--------');
		// $this->staging->initiate_staging($args);
		$this->staging->send_cloud_creds();
	}

	public function connect_cpanel_wptc($args){
		if (empty($_POST['data'])) {
			die(array('error' => 'credentials are empty' ));
		}
		$data = $_POST['data'];
		$this->staging->connect_cpanel_wptc($data);
	}

	public function check_ftp_crendtials_wptc($args){
		if (empty($_POST['data'])) {
			die(array('error' => 'credentials are empty' ));
		}
		if (is_any_ongoing_wptc_backup_process()) {
			die(json_encode(array('error' => 'backup is running. please wait till get finished.')));
		}
		$data = $_POST['data'];
		$this->staging->check_ftp_crendtials_wptc($data);
	}

	public function create_db_cpanel($args){
		if (empty($_POST['data'])) {
			die(array('error' => 'credentials are empty' ));
		}
		$data = $_POST['data'];
		dark_debug(array(), '--------create_db_cpanel----------------');
		$this->staging->create_db_cpanel($data);
	}

	public function validate_database_wptc($args){
		if (empty($_POST['data'])) {
			die(array('error' => 'credentials are empty' ));
		}
		if (is_any_ongoing_wptc_backup_process()) {
			die(json_encode(array('error' => 'backup is running. please wait till get finished.')));
		}
		$data = $_POST['data'];
		dark_debug(array(), '--------create_db_cpanel----------------');
		$this->staging->validate_database_wptc($data);
	}

	public function init_staging_wptc_h(){
		dark_debug(array(), '-----------init_staging_wptc_h-------------');
		$this->staging->init_staging_wptc_h(true);
	}

	public function current_staging_status_wptc(){
		require_once WPTC_PLUGIN_DIR.'Pro/Staging/Views/wptc_staging_progress.php';
		$obj = new Staging_Progress();
		$obj->current_staging_status_wptc();
	}

	public function staging_details_wptc(){
		dark_debug(array(), '-----------staging_details_wptc-------------');
		require_once WPTC_PLUGIN_DIR.'Pro/Staging/Views/wptc_staging_progress.php';
		$obj = new Staging_Progress();
		if (is_any_ongoing_wptc_backup_process()) {
			$obj->staging_details_wptc($backup_progress = 1);
			// die(json_encode(array('status' => 'backup_on_progress')));
		} else {
			$obj->staging_details_wptc($backup_progress = 1);
		}
	}

	public function stop_staging_wptc_h(){
		dark_debug(array(), '-----------stop_staging_wptc_h-------------');
		$this->staging->init_staging_wptc_h(false);
	}

	public function stop_staging_wptc(){
		dark_debug(array(), '-----------stop_staging_wptc_h-------------');
		$this->staging->stop_staging();
		$this->staging->init_staging_wptc_h(false);
	}

	public function send_response_node_staging_wptc_h(){
		$progress_status = $this->config->get_option('staging_progress_status', true);
		$return_array = array('progress_status' => $progress_status);
		send_response_wptc('progress', 'STAGING', $return_array);
	}

	public function process_staging_req_wptc_h(){
		$is_staging_running = $this->config->get_option('is_staging_running');
		$same_server_staging_running = $this->config->get_option('same_server_staging_running');
		if (!$is_staging_running && !$same_server_staging_running) {
			dark_debug(array(), '-----------process_staging_req_wptc_h rejected-------------');
			$this->staging->init_staging_wptc_h(false);
			send_response_wptc('Staging not running and trigger schedule', 'STAGING');
		}
		$result = $this->staging->process_staging_req_wptc_h();
		if ($result === false) {
			$return_array = array('staging_error' => $this->config->get_option('staging_last_error'));
			send_response_wptc('progress', 'STAGING', $return_array);
		}
	}

	public function continue_sequence(){
		if (!is_wptc_timeout_cut($start_time, 10) && !$this->config->get_option('staging_last_error', true)) {
			$this->process_staging_req_wptc_h();
		}
	}

	public function is_staging_backup_wptc_h(){
		dark_debug(array(), '-----------Hooks called of is_staging_backup_wptc_h-------------');
		$this->staging->is_staging_backup_wptc_h(true);
	}

	// public function check_wptc_staging_status(){
	// 	dark_debug(array(), '---------check_wptc_staging_status---------------');
	// 	$this->staging->check_wptc_staging_status();
	// }

	public function delete_staging_wptc(){
		dark_debug(array(), '---------delete_staging_wptc---------------');
		$this->staging->delete_staging_wptc();
	}

	public function get_stored_ftp_details_wptc(){
		dark_debug(array(), '---------get_stored_ftp_details_wptc---------------');
		dark_debug($_REQUEST, '---------$_REQUEST------------');
		if ($_REQUEST['remove_error']) {
			$this->staging->get_stored_ftp_details_wptc(1);
		} else {
			$this->staging->get_stored_ftp_details_wptc();
		}
	}

	public function clear_staging_flags_wptc(){
		// dark_debug(array(), '---------clear_staging_flags_wptc---------------');
		if (empty($_POST['not_force'])) {
			$this->staging->clear_staging_flags_wptc();
		}
		$this->staging->clear_staging_flags_wptc($not_force = 1);
	}

	public function get_staging_url_wptc(){
		dark_debug(array(), '---------get_staging_url_wptc---------------');
		$this->staging->get_staging_url_wptc();
	}

	public function add_staging_req_h(){
		if (!isset($_REQUEST['is_staging_req']) || empty($_REQUEST['is_staging_req'])) {
			return false;
		}
		if ($_REQUEST['is_staging_req']) {
			if($_REQUEST['is_staging_req'] == 2) {
				$this->staging->add_staging_req_h('incremental');
			} else if($_REQUEST['is_staging_req'] == 1) {
				$this->staging->add_staging_req_h('fresh');
			}
		}
	}

	public function list_ftp_file_sys_wptc(){
		require_once WPTC_PLUGIN_DIR.'Pro/Staging/Views/fileTree.php';
		dark_debug(array(), '---------list_ftp_file_sys_wptc---------------');
		$data = $_POST;
		dark_debug($_POST, '---------$_POST------------');
		$obj = new file_Tree_WPTC($_POST);
	}

	public function add_additional_sub_menus_wptc_h($value=''){
		$text = __('Staging', 'wptc');
		add_submenu_page('wp-time-capsule-monitor', $text, $text, 'activate_plugins', 'wp-time-capsule-staging-options', 'wordpress_time_capsule_staging_options');
	}

	public function is_any_staging_process_going_on($value=''){
		// dark_debug(array(), '---------is_any_staging_process_going_on---------------');
		return $this->staging->is_any_staging_process_going_on();
	}

	public function get_internal_staging_db_prefix($value=''){
		// dark_debug(array(), '---------get_internal_staging_db_prefix---------------');
		return $this->staging->get_internal_staging_details('db_prefix');
	}

	public function staging_status($value=''){
		// dark_debug(array(), '---------is_any_staging_process_going_on---------------');
		return true;
	}

	public function enque_js_files() {
		wp_enqueue_style('wptc-staging-style', plugins_url() . '/' . WPTC_TC_PLUGIN_NAME . self::CSS_URL, array(), WPTC_VERSION);
		wp_enqueue_script('wptc-staging', plugins_url() . '/' . WPTC_TC_PLUGIN_NAME . self::JS_URL, array(), WPTC_VERSION);
	}

	public function save_stage_n_update() {
		dark_debug($_POST, '---------$_POST------------');
		if (empty($_POST['update_items'])) {
			die_with_json_encode(array('status' => 'failed'));
		}
		return $this->staging->save_stage_n_update($_POST);
	}

	public function same_server_test() {
		if (empty($_POST['path'])) {
			die_with_json_encode(array('status' => 'path is missing'));
		}
		return $this->staging->same_server_test($_POST['path']);
	}

	public function copy_same_server_test() {
		return $this->staging->copy_same_server_test();
	}

	public function get_external_staging_db_details() {
		return $this->staging->get_external_staging_db_details();
	}

	public function save_staging_settings() {
		$data = $_POST['data'];
		return $this->staging->save_staging_settings($data);
	}

	public function page_settings_content($more_tables_div, $dets1 = null, $dets2 = null, $dets3 = null) {

		$internal_staging_db_rows_copy_limit = $this->config->get_option('internal_staging_db_rows_copy_limit');
		$internal_staging_db_rows_copy_limit = ($internal_staging_db_rows_copy_limit) ? $internal_staging_db_rows_copy_limit : 1000 ;

		$internal_staging_file_copy_limit = $this->config->get_option('internal_staging_file_copy_limit');
		$internal_staging_file_copy_limit = ($internal_staging_file_copy_limit) ? $internal_staging_file_copy_limit : 500 ;

		$internal_staging_deep_link_limit = $this->config->get_option('internal_staging_deep_link_limit');
		$internal_staging_deep_link_limit = ($internal_staging_deep_link_limit) ? $internal_staging_deep_link_limit : 25000 ;

		$more_tables_div .= '
		<div class="table ui-tabs-hide" id="wp-time-capsule-tab-staging"> <p></p>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="db_rows_clone_limit_wptc">DB rows cloning limit</label>
					</th>
					<td>
						<input name="db_rows_clone_limit_wptc" type="number" min="0" step="1" id="db_rows_clone_limit_wptc" value="'.$internal_staging_db_rows_copy_limit.'" class="medium-text">
					<p class="description">'. __( 'Reduce this number by a few hundred when staging process hangs at <CODE>DB cloning status</CODE>', 'wp-time-capsule' ).' </p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="files_clone_limit_wptc">Files cloning limit</label>
					</th>
					<td>
						<input name="files_clone_limit_wptc" type="number" min="0" step="1" id="files_clone_limit_wptc" value="'.$internal_staging_file_copy_limit.'" class="medium-text">
					<p class="description">'. __( 'Reduce this number by a few hundred when staging process hangs at <CODE>Copying files</CODE>', 'wp-time-capsule' ).' </p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="deep_link_replace_limit_wptc">Deep Link replacing limit</label>
					</th>
					<td>
						<input name="deep_link_replace_limit_wptc" type="number" min="0" step="1" id="deep_link_replace_limit_wptc" value="'.$internal_staging_deep_link_limit.'" class="medium-text">
					<p class="description">'. __( 'Reduce this number by a few hundred when staging process hangs at <CODE>Replace links</CODE>', 'wp-time-capsule' ).' </p>
					</td>
				</tr>
				';

		return $more_tables_div. '</table> </div>';
	}

}