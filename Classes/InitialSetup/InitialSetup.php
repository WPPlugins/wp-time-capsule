<?php

class Wptc_InitialSetup extends Wptc_InitialSetup_Init {
	public $tabs;
	private $config,
			$options,
			$JQUERY_UI_JS_PATH,
			$FANCY_TREE_JS_PATH,
			$JQUERY_FILE_TREE_CSS_PATH,
			$FANCY_TREE_CSS_PATH,
			$WPTC_DIALOG_CSS,
			$WPTC_PLANS_CSS,
			$WPTC_PLANS_JS;
	public function __construct(){
		$this->config = WPTC_Base_Factory::get('Wptc_InitialSetup_Config');
		$this->options = WPTC_Factory::get('config');
	}

	public function load_page(){
		$this->define_header_file_paths();
		$this->include_header_files();
	}

	private function define_header_file_paths(){
		$this->JQUERY_UI_JS_PATH = '/treeView/jquery-ui.custom.js';
		$this->FANCY_TREE_JS_PATH = '/treeView/jquery.fancytree.js';
		$this->JQUERY_FILE_TREE_CSS_PATH = '/JQueryFileTree/jqueryFileTree.css';
		$this->FANCY_TREE_CSS_PATH = '/treeView/skin/ui.fancytree.css';
		$this->WPTC_DIALOG_CSS = '/wptc-dialog.css';
		$this->WPTC_PLANS_CSS = '/wptc-plans.css';
		$this->WPTC_PLANS_JS = '/Views/wptc-plans.js';
	}

	private function include_header_files(){
		wp_enqueue_style('wptc-dialog-css', plugins_url() . '/' . WPTC_TC_PLUGIN_NAME . $this->WPTC_DIALOG_CSS, array(), WPTC_VERSION);
		wp_enqueue_style('wptc-plans-css', plugins_url() . '/' . WPTC_TC_PLUGIN_NAME . $this->WPTC_PLANS_CSS, array(), WPTC_VERSION);
		wp_enqueue_script('wptc-jquery-ui-custom-js', plugins_url() . '/' . WPTC_TC_PLUGIN_NAME . $this->JQUERY_UI_JS_PATH, array(), WPTC_VERSION);
		wp_enqueue_script('wptc-fancytree-js', plugins_url() . '/' . WPTC_TC_PLUGIN_NAME . $this->FANCY_TREE_JS_PATH, array(), WPTC_VERSION);
		wp_enqueue_style('wptc-jquery-fileTree-css', plugins_url() . '/' . WPTC_TC_PLUGIN_NAME . $this->JQUERY_FILE_TREE_CSS_PATH, array(), WPTC_VERSION);
		wp_enqueue_style('wptc-fancytree-css', plugins_url() . '/' . WPTC_TC_PLUGIN_NAME . $this->FANCY_TREE_CSS_PATH, array(), WPTC_VERSION);
		// wp_enqueue_script('wptc-plans-js', plugins_url() . '/' . WPTC_TC_PLUGIN_NAME . $this->WPTC_PLANS_JS, array(), WPTC_VERSION);
	}

	public function requirement_check(){
		$requirements = array();
		global $wpdb;

		$requirements[] = array(
			'title'			=>		'PHP Version',
			'min'			=>		'>= 5.4.0',
			'suggestion'	=>		'>= 5.6.0',
			'value'			=>		PHP_VERSION,
			'status'		=>		(version_compare(PHP_VERSION, '5.4.0', '>=')) ? true : false,
		);

		$requirements[] = array(
			'title'			=>		'MySQL Version',
			'min'			=>		'>= 5.0.15',
			'suggestion'	=>		'>= 5.5 (WordPress recommends 5.6+)',
			'value'			=>		$wpdb->db_version(),
			'status'		=>		(version_compare($wpdb->db_version(), '5.0.15', '>=')) ? true : false,
		);

		$requirements[] = array(
			'title'			=>		'cURL Function',
			'min'			=>		'enabled',
			// 'suggestion'	=>		'enabled',
			'value'			=>		(function_exists('curl_version')) ? 'enabled' : 'disabled',
			'status'		=>		(function_exists('curl_version')) ? true : false,
		);

		$required_failed  = false;
		foreach ($requirements as $key => $requirement) {
			if ($requirement['status'] === false) {
				$required_failed = true;
			}
		}

		$requirements['overall_requirements_passed'] = ($required_failed) ? false : true;

		return $requirements;
	}

	public function success_and_error_flaps(){
		$email = $this->config->get_option('main_account_email');

		if(!empty($_GET['show_plan_success_flap'])){
			return '<div class="bs-callout bs-callout-success flap_boxes_group_wptc wptc_success_box" style="display: none;"></div>';
		} elseif(!empty($_GET['show_plan_error_flap'])){
			return '<div class="bs-callout bs-callout-danger flap_boxes_group_wptc wptc_error_box "><span class="error_label">Error:</span><span class="err_msg">'.$_GET['err_msg'].'</span></div>';
		}
	}

	public function get_s3_creds_box_div() {
		$div = '';
		$sub_div = '<a class="s3_doc_wptc" href="http://wptc.helpscoutdocs.com/article/4-connect-your-amazon-s3-account" target="_blank">See how to connect my AS3 account</a>';

		$sub_div = $sub_div . '<div class="l1"  style="padding: 0px;"> <input type="text" name="as3_access_key" class="wptc_general_inputs" style="width: 45%;" placeholder="Access Key" id="as3_access_key" required value="' . $this->config->get_option('as3_access_key') . '" /> </div>';

		$sub_div = $sub_div . '<div class="l1"  style="padding: 0px;"> <input type="text" name="as3_secure_key" class="wptc_general_inputs" style="width: 45%;" placeholder="Secure Key" id="as3_secure_key" required value="' . $this->config->get_option('as3_secure_key') . '" /> </div>';

		$sub_div = $sub_div . '<div class="l1"  style="padding: 0px;">'
					. $this->get_s3_select_box_div($this->config->get_option('as3_bucket_region')) .
					'</div>';

		$sub_div = $sub_div . '<div class="l1"  style="padding: 0px;"> <input type="text" class="wptc_general_inputs" style="width: 45%;" name="as3_bucket_name" placeholder="Bucket Name" id="as3_bucket_name" required value="' . $this->config->get_option('as3_bucket_name') . '" /> </div>';

		$div = $div . '<div class="l1 s3_inputs creds_box_inputs"  style="padding-bottom: 10px; display:none; margin-top: -36px; position: relative;"><div style="text-align: center; font-size: 13px; padding-bottom: 10px;">' . $sub_div . '</div></div>';

		return $div;
	}

	private function get_s3_select_box_div($selected_bucket_region) {
		$buc_region_arr = array('' => 'Select Bucket Region', '' => 'US Standard', 'us-west-2' => 'US West (Oregon) Region', 'us-west-1' => 'US West (Northern California) Region', 'eu-west-1' => 'EU (Ireland) Region', 'ap-southeast-1' => 'Asia Pacific (Singapore) Region', 'ap-southeast-2' => 'Asia Pacific (Sydney) Region', 'ap-northeast-1' => 'Asia Pacific (Tokyo) Region', 'sa-east-1' => 'South America (Sao Paulo) Region', 'eu-central-1' => 'EU (Frankfurt)', 'cn-north-1' => 'China (Beijing) Region');

		$div = '<select name="as3_bucket_region" id="as3_bucket_region" class="wptc_general_inputs" style="width:45%; height: 38px;">';

		foreach ($buc_region_arr as $k => $v) {
			$selected = '';
			if ($k == $selected_bucket_region) {
				$selected = 'selected';
			}
			$div = $div . '<option value="' . $k . '" ' . $selected . ' class="dropOption" >' . $v . '</option>';
		}
		$div = $div . '</select>';
		return $div;
	}

	public function store_cloud_access_token_wptc(){
		if ((isset($_GET['cloud_auth_action']) && $_GET['cloud_auth_action'] == 'g_drive') && isset($_GET['code']) && !isset($_GET['error'])) {
			$this->config->set_option('oauth_state_g_drive', 'access');
			$req_token_dets['refresh_token'] = $_GET['code'];
			$this->config->set_option('gdrive_old_token', serialize($req_token_dets));
		} else if ((isset($_GET['cloud_auth_action']) && $_GET['cloud_auth_action'] == 'dropbox') && isset($_GET['code']) && !isset($_GET['error'])) {
			$access_token = base64_decode(urldecode($_GET['code']));
			$this->config->set_option('dropbox_access_token', $access_token);
			$this->config->set_option('dropbox_oauth_state', 'access');
		}
	}

	public function check_cloud_min_php_min_req() {
		$cloud_eligible = array();
		if (is_php_version_compatible_for_g_drive_wptc()) {
			$cloud_eligible[] = 'gdrive';
		}
		if (is_php_version_compatible_for_s3_wptc()) {
			$cloud_eligible[] = 's3';
		}
		$cloud_eligible[] = 'dropbox'; // available all version of php
		return json_encode($cloud_eligible);
	}

	public function process_GET_request_wptc() {
		if ($this->config->get_option('main_account_login_last_error')) {
			$this->config->set_option('main_account_login_last_error', false);
		}
		if (isset($_GET['error'])) {
			if (isset($_GET['cloud_auth_action'])) {
				$this->config->set_option('last_cloud_error', $_GET['error']);
			} else {
				$last_cloud_error = $this->config->get_option('last_cloud_error');
				if ($last_cloud_error) {
					$this->config->set_option('main_account_login_last_error', $last_cloud_error);
				}
			}
		}
		if (!empty($_POST['wptc_main_acc_email']) && !empty($_POST['wptc_main_acc_pwd'])) {
			$this->process_wptc_login();
		}
		if (!empty($_GET['logout'])) {
			process_wptc_logout('logout');
		}
	}

	private function process_wptc_login() {
		$this->config->set_option('wptc_main_acc_email_temp', base64_encode($_POST['wptc_main_acc_email']));
		$this->config->set_option('wptc_main_acc_pwd_temp', base64_encode(md5(trim( wp_unslash( $_POST[ 'wptc_main_acc_pwd' ] ) ))));
		$this->config->set_option('wptc_token', false);

		$auth_result = $this->options->is_main_account_authorized($_POST['wptc_main_acc_email'], trim( wp_unslash( $_POST[ 'wptc_main_acc_pwd' ] ) ));

		$privileges_wptc = $this->config->get_option('privileges_wptc');
		$privileges_wptc = json_decode($privileges_wptc);

		dark_debug($privileges_wptc, "--------privileges_wptc-----process_wptc_login---");

		if (empty($auth_result)) {
			$_GET['error'] = true;
		}
	}

	public function record_signed_in_repos(&$dropbox) {
		$signed_in_arr = $this->config->get_option('signed_in_repos');
		if (empty($signed_in_arr)) {
			$signed_in_arr = array();
		} else {
			$signed_in_arr = unserialize($signed_in_arr);
		}
		if (empty($dropbox)) {
			return false;
		}
		$this->wipe_out_prev_acc_backups_wptc($signed_in_arr, $this->config->get_option('default_repo'), $dropbox->get_quota_div());
		$signed_in_arr[$this->config->get_option('default_repo')] = $dropbox->get_quota_div();
		$this->config->set_option('signed_in_repos', serialize($signed_in_arr));
	}

	private function wipe_out_prev_acc_backups_wptc($signed_in_arr, $default_repo, $email) {
		if (empty($signed_in_arr) || !is_array($signed_in_arr)) {
			return false;
		}

		if ($default_repo == 'g_drive' && array_key_exists('g_drive', $signed_in_arr) && $signed_in_arr['g_drive'] != $email) {
			dark_debug('google gdrive exisiting account completely wiped out', '-------wipe_out_prev_acc_backups_wptc---------');
			$this->clear_prev_acc_backup_data_wptc();
		} else if ($default_repo == 'dropbox' && array_key_exists('dropbox', $signed_in_arr) && $signed_in_arr['dropbox'] != $email) {
			dark_debug('dropbox exisiting account completely wiped out', '-------wipe_out_prev_acc_backups_wptc---------');
			$this->clear_prev_acc_backup_data_wptc();
		} else if ($default_repo == 's3' && array_key_exists('s3', $signed_in_arr) && $signed_in_arr['s3'] != $email) {
			dark_debug('s3 exisiting account completely wiped out', '-------wipe_out_prev_acc_backups_wptc---------');
			$this->clear_prev_acc_backup_data_wptc();
		}
	}

	private function clear_prev_acc_backup_data_wptc() {
		$backup = new WPTC_BackupController();
		$backup->clear_prev_repo_backup_files_record();
	}

	public function get_select_backup_type_setting(){
		$select_start = '<select id="backup_type" name="backup_type">';
		$current_setting = $this->config->get_option('backup_type_setting');
		$daily_backup_selected = '';
		$weekly_backup_selected = '';
		if ($current_setting == 'SCHEDULE') {
			$daily_backup_selected = 'selected';
		} else if($current_setting == 'WEEKLYBACKUP'){
			$weekly_backup_selected = 'selected';
		}
		$body_content = apply_filters('inside_backup_type_settings_wptc_h', '')."<option value='SCHEDULE' ".$daily_backup_selected.">Daily</option>";
		$select_end = '</select>';
		return $select_start.$body_content.$select_end;
	}

	public function get_select_cloud_dialog_div() {
		$div = '';
		$sub_div = '';
		$display_status = $gdrive_not_eligible = $dropbox_not_eligible = $s3_not_eligible = 'display:none';
		// if ((isset($_GET['cloud_auth_action']) && $_GET['cloud_auth_action'] == 'g_drive' && !isset($_GET['code']) && !isset($_GET['error'])) || !isset($_GET['cloud_auth_action']) && !isset($_GET['uid']) || DEFAULT_REPO_LABEL == 'Cloud') {
		$sites_count = $this->config->get_option('connected_sites_count');
		if (!empty($sites_count) && $sites_count >= WPTC_GDRIVE_TOKEN_ON_INIT_LIMIT) {
			$div .= '<div style="text-align: center; padding: 10px 5px; line-height: 22px; display:none" id="google_limit_reached_text_wptc">Google has a limit on the number of sites you can authenticate per app. If you are backing up all sites to the same Google Account, use a previously generated token. <a href="http://docs.wptimecapsule.com/article/23-add-new-site-using-existing-google-drive-token" style="text-decoration:none" target="_blank">Show me how.</a></div>';
		}
		$div .= '<div class="l1"  style="padding-bottom: 10px; padding-top: 10px">
					<select name="select_wptc_cloud_storage" id="select_wptc_cloud_storage" class="wptc_general_inputs" style="width:45%;height: 38px;">
						<option value="" class="dummy_select">Select your cloud storage app</option>';
		if (is_php_version_compatible_for_dropbox_wptc()) {
			$dropbox_not_eligible = 'display:none';
			$div .= '<option value="dropbox" label="Dropbox">Dropbox</option>;';
		} else {
			$dropbox_not_eligible = 'display:block';
			$div .= '<option disabled="disabled" value="dropbox" label="Dropbox">Dropbox</option>;';
		}
		if (is_php_version_compatible_for_g_drive_wptc()) {
			$gdrive_not_eligible = 'display:none';
			$div .= '<option value="g_drive" label="Google Drive">Google Drive</option>';
		} else {
			$div .= '<option disabled="disabled" value="g_drive" label="Google Drive">Google Drive</option>';
			$gdrive_not_eligible = 'display:block';
		}
		if (is_php_version_compatible_for_s3_wptc()) {
			$div .= '<option value="s3" label="Amazon S3" >Amazon S3</option>';
		} else {
			$div .= '<option disabled="disabled" value="s3" label="Amazon S3" >Amazon S3</option>';
		}
		$div .= '</select>
				</div>';
		if (!empty($sites_count) && $sites_count >= WPTC_GDRIVE_TOKEN_ON_INIT_LIMIT) {
			$div .= '<input type="text" id="gdrive_refresh_token_input_wptc" placeholder="Paste token here" style="display:none; width: 45%;position: relative;left: 268px;top: -10px;" class="wptc_general_inputs">';
			$div .= '<a href="http://docs.wptimecapsule.com/article/23-add-new-site-using-existing-google-drive-token" id="see_how_to_add_refresh_token_wptc" target="_blank" style="text-decoration:none; display:none; position: absolute;cursor: pointer;top: 287px;right: 286px;font-size: 12px;">Need help ?</a>';
		}
		if (is_php_version_compatible_for_s3_wptc()) {
			$s3_not_eligible = 'display:none';
			$div .=  $this->get_s3_creds_box_div();
		} else {
			$s3_not_eligible = 'display:block';
		}
		$display_status = ($s3_not_eligible == 'display:block' || $gdrive_not_eligible == 'display:block' || $dropbox_not_eligible == 'display:block') ? 'display:block' : 'display:none';
		if (!empty($sites_count) && $sites_count >= WPTC_GDRIVE_TOKEN_ON_INIT_LIMIT) {
			$div = $div . '<div id="google_token_add_btn" style="display:none"><div class="cloud_error_mesg_g_drive_token"></div><input type="button" id="save_g_drive_refresh_token" class="btn_pri cloud_go_btn" style="margin: 10px 32.9% 20px; width: 330px; text-align: center;" value="Authenticate Token" ><div style="text-align: center; margin-bottom: 20px;">(OR)</div></div>';
		}
		$div = $div . '<div class="cloud_error_mesg"></div><input type="button" id="connect_to_cloud" class="btn_pri cloud_go_btn" style="margin: 0px 32.9% 30px; width: 330px; text-align: center; display: none;" value="Connect my cloud account" >';
		// }
		$div .= '<div style="clear:both"></div>';
		$div .= '<div id="mess" style="text-align: center; font-size: 13px; padding-top: 10px; padding-bottom: 10px; display: none;">You will be redirected to the specific Cloud Site for allowing access to the plugin.<br> Click on <strong>Allow</strong> when prompted.</div>';
		$div .= "<div class='dashicons-before dashicons-warning' id='s3_seperate_bucket_note' style='display:none; font-style: italic; left: 10px; font-size: 13px;'><span style='line-height: 22px'>Please create a separate bucket on Amazon S3 since we will be enabling versioning on that bucket. We create subfolders for each site, so you don't have to create a new bucket everytime.</span></div>";
		$div .= "<div style='height: 60px; position: relative;".$display_status." ' id='php_req_note_wptc'><div class='dashicons-before dashicons-warning' id='dropbox_php_req_note' style='position: absolute;font-size: 12px;top: -27px;width: 100%;font-style: italic;left: 10px;padding-top: 10px;padding-bottom: 10px; ".$dropbox_not_eligible."'><span style='position: absolute;top: 11px;left: 24px; '>Dropbox requires PHP v5.3.1+. Please upgrade your PHP to use Dropbox.</span></div><div class='dashicons-before dashicons-warning' id='g_drive_php_req_note' style='position: absolute;font-size: 12px;top: 0px;width: 100%;font-style: italic;left: 10px;padding-top: 10px;padding-bottom: 10px; ".$gdrive_not_eligible."'><span style='position: absolute;top: 11px;left: 24px; '>Google Drive requires PHP v5.4.0+. Please upgrade your PHP to use Google Drive.</span></div><div class='dashicons-before dashicons-warning' id='s3_php_req_note' style='position: absolute;font-size: 12px;top: 26px;width: 100%;font-style: italic;left: 10px;padding-top: 10px;padding-bottom: 10px; ".$s3_not_eligible."'><span style='position: absolute;top: 11px;left: 24px;''>Amazon S3 requires PHP v5.3.3+. Please upgrade your PHP to use Amazon S3.</span></div></div>";
		if (isset($_GET['uid'])) {
			// $div = $div . '<input type="button" name="continue" id="continue_wptc" class="btn_pri cloud_go_btn" style="margin: 20px 136px 30px; width: 330px; text-align: center; " value="Continue" />';
			//$div = $div . '<input type="button" name="continue" id="continue_to_initial_setup" class="btn_pri cloud_go_btn" style="margin: 25px 220px 10px;width: 330px;text-align: center;top: 0px;position: relative;" value="Continue" />';
		}
		if ((isset($_GET['cloud_auth_action']) && $_GET['cloud_auth_action'] == 's3') && !empty($_GET['as3_access_key']) && !empty($_GET['as3_secure_key']) && !empty($_GET['as3_bucket_name']) && DEFAULT_REPO_LABEL != 'Cloud') {
			// $div = $div . '<input type="button" name="continue" id="continue_to_initial_setup" class="btn_pri cloud_go_btn" style="margin: 25px 220px 10px;width: 330px;text-align: center;top: 0px;position: relative;" value="Continue" />';
		}
		return $div;
	}

	public function is_fresh_backup(){
		global $wpdb;
		$fcount = $wpdb->get_results('SELECT COUNT(*) as files FROM ' . $wpdb->base_prefix . 'wptc_processed_files');
		return (!empty($fcount) && !empty($fcount[0]->files) && $fcount[0]->files > 0) ? 'yes' : 'no';
	}

}