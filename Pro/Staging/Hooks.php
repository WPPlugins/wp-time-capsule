<?php
class Wptc_Staging_Hooks extends Wptc_Base_Hooks {
	public $hooks_handler_obj;
	public $wp_filter_id;

	public function __construct() {
		$this->hooks_handler_obj = WPTC_Pro_Factory::get('Wptc_Staging_Hooks_Hanlder');
		//$this->hooks_handler_obj = new Wptc_Backup_Before_Update_Hooks_Hanlder();
	}

	public function register_hooks() {
		$this->register_actions();
		$this->register_filters();
		$this->register_wptc_actions();
		$this->register_wptc_filters();
	}

	public function register_actions() {
		add_action('wp_ajax_check_staging_ftp_creds_wptc', array($this->hooks_handler_obj, 'check_staging_ftp_creds_wptc'));
		add_action('wp_ajax_connect_cpanel_wptc', array($this->hooks_handler_obj, 'connect_cpanel_wptc'));
		add_action('wp_ajax_check_ftp_crendtials_wptc', array($this->hooks_handler_obj, 'check_ftp_crendtials_wptc'));
		add_action('wp_ajax_create_db_cpanel', array($this->hooks_handler_obj, 'create_db_cpanel'));
		add_action('wp_ajax_validate_database_wptc', array($this->hooks_handler_obj, 'validate_database_wptc'));
		add_action('wp_ajax_validate_database_wptc', array($this->hooks_handler_obj, 'validate_database_wptc'));
		add_action('wp_ajax_current_staging_status_wptc', array($this->hooks_handler_obj, 'current_staging_status_wptc'));
		add_action('wp_ajax_staging_details_wptc', array($this->hooks_handler_obj, 'staging_details_wptc'));
		add_action('wp_ajax_check_wptc_staging_status', array($this->hooks_handler_obj, 'check_wptc_staging_status'));
		add_action('wp_ajax_delete_staging_wptc', array($this->hooks_handler_obj, 'delete_staging_wptc'));
		add_action('wp_ajax_get_stored_ftp_details_wptc', array($this->hooks_handler_obj, 'get_stored_ftp_details_wptc'));
		add_action('wp_ajax_list_ftp_file_sys_wptc', array($this->hooks_handler_obj, 'list_ftp_file_sys_wptc'));
		add_action('wp_ajax_clear_staging_flags_wptc', array($this->hooks_handler_obj, 'clear_staging_flags_wptc'));
		add_action('wp_ajax_get_staging_url_wptc', array($this->hooks_handler_obj, 'get_staging_url_wptc'));
		add_action('wp_ajax_save_stage_n_update_wptc', array($this->hooks_handler_obj, 'save_stage_n_update'));
		add_action('wp_ajax_test_same_server_staging_wptc', array($this->hooks_handler_obj, 'same_server_test'));
		add_action('wp_ajax_copy_same_server_staging_wptc', array($this->hooks_handler_obj, 'copy_same_server_test'));
		add_action('wp_ajax_get_external_staging_db_details', array($this->hooks_handler_obj, 'get_external_staging_db_details'));
		add_action('wp_ajax_stop_staging_wptc', array($this->hooks_handler_obj, 'stop_staging_wptc'));
		add_action('wp_ajax_save_staging_settings_wptc', array($this->hooks_handler_obj, 'save_staging_settings'));
	}

	public function register_filters() {
		add_filter('send_core_update_notification_email', array($this->hooks_handler_obj, 'filter_hanlder'), 1, 2);
	}

	public function register_filters_may_be_prevent_auto_update() {

	}

	public function register_wptc_actions() {
		add_action('add_additional_sub_menus_wptc_h', array($this->hooks_handler_obj, 'add_additional_sub_menus_wptc_h'));
		add_action('is_staging_backup_wptc_h', array($this->hooks_handler_obj, 'is_staging_backup_wptc_h'));
		add_action('init_staging_wptc_h', array($this->hooks_handler_obj, 'init_staging_wptc_h'));
		add_action('stop_staging_wptc_h', array($this->hooks_handler_obj, 'stop_staging_wptc_h'));
		add_action('process_staging_req_wptc_h', array($this->hooks_handler_obj, 'process_staging_req_wptc_h'));
		add_action('add_staging_req_h', array($this->hooks_handler_obj, 'add_staging_req_h'));
		add_action('clear_staging_flags_wptc', array($this->hooks_handler_obj, 'clear_staging_flags_wptc'));
		add_action('send_response_node_staging_wptc_h', array($this->hooks_handler_obj, 'send_response_node_staging_wptc_h'));
		add_action('admin_enqueue_scripts', array($this->hooks_handler_obj, 'enque_js_files'));
	}

	public function register_wptc_filters() {
		add_filter('staging_status_wptc', array($this->hooks_handler_obj, 'staging_status'), 10);
		add_filter('is_any_staging_process_going_on', array($this->hooks_handler_obj, 'is_any_staging_process_going_on'), 10);
		add_filter('get_internal_staging_db_prefix', array($this->hooks_handler_obj, 'get_internal_staging_db_prefix'), 10);
		add_filter('page_settings_tab_wptc', array($this->hooks_handler_obj, 'page_settings_tab'), 10);
		add_filter('page_settings_content_wptc', array($this->hooks_handler_obj, 'page_settings_content'), 10);
	}

}