<?php
/*
Plugin Name: WP Time Capsule
Plugin URI: https://wptimecapsule.com
Description: WP Time Capsule is an incremental automated backup plugin that backups up your website to Dropbox, Google Drive and Amazon S3 on a daily basis.
Author: Revmakx
Version: 1.10.2
Author URI: http://www.revmakx.com
Tested up to: 4.8
/************************************************************
 * This plugin was modified by Revmakx
 * Copyright (c) 2017 Revmakx
 * www.revmakx.com
 ************************************************************/

// Make sure we don't expose any info if called directly
if (!function_exists('add_action')) {
	echo 'Howdy! I am not of much use without MotherShip Dashboard.';
	exit;
}

if (!function_exists('curl_init')) {
	echo 'WP Time Capsule must need curl to get work. Please install CURL in your server';
	// exit;
}

add_action('init','wptc_init');

add_action('wp_loaded', 'init_wptc_cron', 2147483650);

require_once dirname(__FILE__).  DIRECTORY_SEPARATOR  .'wptc-constants.php';
require_once WPTC_CLASSES_DIR.'Sentry/SentryConfig.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
include_once ABSPATH . 'wp-includes/capabilities.php';
require_once WPTC_PLUGIN_DIR.'common-functions.php';
require_once WPTC_PLUGIN_DIR.'wptc-cron-functions.php';
require_once WPTC_PLUGIN_DIR.'Dropbox/Dropbox/API.php';
require_once WPTC_PLUGIN_DIR.'Dropbox/Dropbox/Exception.php';
require_once WPTC_PLUGIN_DIR.'Dropbox/Dropbox/OAuth/Consumer/ConsumerAbstract.php';
require_once WPTC_PLUGIN_DIR.'Dropbox/Dropbox/OAuth/Consumer/Curl.php';
require_once WPTC_PLUGIN_DIR.'utils/g-wrapper-utils.php';
require_once WPTC_CLASSES_DIR.'Extension/Base.php';
require_once WPTC_CLASSES_DIR.'Extension/Manager.php';
require_once WPTC_CLASSES_DIR.'Extension/DefaultOutput.php';
require_once WPTC_CLASSES_DIR.'Processed/Base.php';
require_once WPTC_CLASSES_DIR.'Processed/Files.php';
require_once WPTC_CLASSES_DIR.'Processed/Restoredfiles.php';
require_once WPTC_CLASSES_DIR.'Processed/DBTables.php';
require_once WPTC_CLASSES_DIR.'DatabaseBackup.php';
require_once WPTC_CLASSES_DIR.'FileList.php';
require_once WPTC_CLASSES_DIR.'DropboxFacade.php';
require_once WPTC_CLASSES_DIR.'Config.php';
require_once WPTC_CLASSES_DIR.'BackupController.php';
require_once WPTC_CLASSES_DIR.'Logger.php';
require_once WPTC_CLASSES_DIR.'Factory.php';
require_once WPTC_CLASSES_DIR.'UploadTracker.php';
require_once WPTC_CLASSES_DIR.'ActivityLog.php';
require_once WPTC_CLASSES_DIR.'DebugLog.php';

include_new_files_wptc();

include_primary_files_wptc();
if (is_php_version_compatible_for_g_drive_wptc()) {
	require_once WPTC_PLUGIN_DIR.'Google/autoload.php';
	require_once WPTC_PLUGIN_DIR.'Google/GoogleWPTCWrapper.php';
	require_once WPTC_CLASSES_DIR.'GdriveFacade.php';
}

if (is_php_version_compatible_for_s3_wptc()) {
	require_once WPTC_PLUGIN_DIR.'S3/autoload.php';
	require_once WPTC_PLUGIN_DIR.'S3/s3WPTCWrapper.php';
	require_once WPTC_CLASSES_DIR.'S3Facade.php';
}

function include_new_files_wptc() {
	$old_files = array('Base.php', 'DefaultOutput.php', '.', '..');

	$cur_files = scandir(WPTC_EXTENSIONS_DIR);
	//dark_debug($cur_files, "--------cur_files--------");
	if (!empty($cur_files) && is_array($cur_files)) {
		foreach ($cur_files as $name => $file) {
			if (!in_array($file, $old_files) && file_exists(WPTC_EXTENSIONS_DIR . $file)) {
				require_once WPTC_EXTENSIONS_DIR . $file;
			}
		}
	}
}

function include_php_files_recursive_wptc($folder_name = '') {
	//TBC
	if (empty($folder_name)) {
		return false;
	}
}

function wptc_autoload($className) {
	$fileName = str_replace('_',  '/' , $className) . '.php';
	$temp = $fileName . " - ";
	if (preg_match('/^WPTC/', $fileName)) {
		$fileName = 'Classes' . str_replace('WPTC', '', $fileName);
	} elseif (preg_match('/^Dropbox/', $fileName)) {
		$fileName = 'Dropbox' .  '/'  . $fileName;
	} elseif (preg_match('/^Google/', $fileName)) {
		$fileName = 'Google' .  '/'  . $fileName;
	} elseif (preg_match('/^S3/', $fileName)) {
		$fileName = 'S3' .  '/'  . $fileName;
	} else {
		return false;
	}

	$path = dirname(__FILE__) .  '/'  . $fileName;
	if (file_exists($path)) {
		require_once $path;
	}
}

function include_primary_files_wptc() {

	include_once WPTC_PLUGIN_DIR.'Base/Factory.php';

	include_once WPTC_PLUGIN_DIR.'Base/init.php';
	include_once WPTC_PLUGIN_DIR.'Base/Hooks.php';
	include_once WPTC_PLUGIN_DIR.'Base/HooksHandler.php';
	include_once WPTC_PLUGIN_DIR.'Base/Config.php';

	include_once WPTC_PLUGIN_DIR.'Base/CurlWrapper.php';

	include_once WPTC_CLASSES_DIR.'CronServer/Config.php';
	include_once WPTC_CLASSES_DIR.'CronServer/CurlWrapper.php';

	include_once WPTC_CLASSES_DIR.'WptcBackup/init.php';
	include_once WPTC_CLASSES_DIR.'WptcBackup/Hooks.php';
	include_once WPTC_CLASSES_DIR.'WptcBackup/HooksHandler.php';
	include_once WPTC_CLASSES_DIR.'WptcBackup/Config.php';

	include_once WPTC_CLASSES_DIR.'Common/init.php';
	include_once WPTC_CLASSES_DIR.'Common/Hooks.php';
	include_once WPTC_CLASSES_DIR.'Common/HooksHandler.php';
	include_once WPTC_CLASSES_DIR.'Common/Config.php';

	include_once WPTC_CLASSES_DIR.'Analytics/init.php';
	include_once WPTC_CLASSES_DIR.'Analytics/Hooks.php';
	include_once WPTC_CLASSES_DIR.'Analytics/HooksHandler.php';
	include_once WPTC_CLASSES_DIR.'Analytics/Config.php';
	include_once WPTC_CLASSES_DIR.'Analytics/BackupAnalytics.php';

	include_once WPTC_CLASSES_DIR.'ExcludeOption/init.php';
	include_once WPTC_CLASSES_DIR.'ExcludeOption/Hooks.php';
	include_once WPTC_CLASSES_DIR.'ExcludeOption/HooksHandler.php';
	include_once WPTC_CLASSES_DIR.'ExcludeOption/Config.php';
	include_once WPTC_CLASSES_DIR.'ExcludeOption/ExcludeOption.php';

	include_once WPTC_CLASSES_DIR.'Settings/init.php';
	include_once WPTC_CLASSES_DIR.'Settings/Hooks.php';
	include_once WPTC_CLASSES_DIR.'Settings/HooksHandler.php';
	include_once WPTC_CLASSES_DIR.'Settings/Config.php';
	include_once WPTC_CLASSES_DIR.'Settings/Settings.php';

	include_once WPTC_CLASSES_DIR.'UpdateCommon/init.php';
	include_once WPTC_CLASSES_DIR.'UpdateCommon/Hooks.php';
	include_once WPTC_CLASSES_DIR.'UpdateCommon/HooksHandler.php';
	include_once WPTC_CLASSES_DIR.'UpdateCommon/Config.php';
	include_once WPTC_CLASSES_DIR.'UpdateCommon/UpdateCommon.php';

	include_once WPTC_CLASSES_DIR.'AppFunctions/init.php';
	include_once WPTC_CLASSES_DIR.'AppFunctions/Hooks.php';
	include_once WPTC_CLASSES_DIR.'AppFunctions/HooksHandler.php';
	include_once WPTC_CLASSES_DIR.'AppFunctions/Config.php';
	include_once WPTC_CLASSES_DIR.'AppFunctions/AppFunctions.php';

	include_once WPTC_CLASSES_DIR.'InitialSetup/init.php';
	include_once WPTC_CLASSES_DIR.'InitialSetup/Hooks.php';
	include_once WPTC_CLASSES_DIR.'InitialSetup/HooksHandler.php';
	include_once WPTC_CLASSES_DIR.'InitialSetup/Config.php';
	include_once WPTC_CLASSES_DIR.'InitialSetup/InitialSetup.php';

	include_once WPTC_CLASSES_DIR.'Sentry/init.php';
	include_once WPTC_CLASSES_DIR.'Sentry/Hooks.php';
	include_once WPTC_CLASSES_DIR.'Sentry/HooksHandler.php';
	include_once WPTC_CLASSES_DIR.'Sentry/Config.php';
	include_once WPTC_CLASSES_DIR.'Sentry/Sentry.php';
	if(is_wptc_server_req() || is_admin()) {
		WPTC_Base_Factory::get('Wptc_Base')->init();
	}
}

function include_spl_files_wptc() {
	@include_once WPTC_PRO_DIR.'ProFactory.php';
	@include_once WPTC_PRO_DIR.'Privileges.php';
	@include_once WPTC_PRO_DIR.'init.php';
	@include_once WPTC_PRO_DIR.'Hooks.php';
	@include_once WPTC_PRO_DIR.'HooksHandler.php';

	// @include_once WPTC_PRO_DIR.'AutoBackup/AutoBackup.php';
	// @include_once WPTC_PRO_DIR.'AutoBackup/Hooks.php';
	// @include_once WPTC_PRO_DIR.'AutoBackup/HooksHandler.php';
	// @include_once WPTC_PRO_DIR.'AutoBackup/Config.php';

	@include_once WPTC_PRO_DIR.'BackupBeforeUpdate/TraceableUpdaterSkin.php';
	@include_once WPTC_PRO_DIR.'BackupBeforeUpdate/init.php';
	@include_once WPTC_PRO_DIR.'BackupBeforeUpdate/Hooks.php';
	@include_once WPTC_PRO_DIR.'BackupBeforeUpdate/HooksHandler.php';
	@include_once WPTC_PRO_DIR.'BackupBeforeUpdate/Config.php';
	@include_once WPTC_PRO_DIR.'BackupBeforeUpdate/AutoUpdate.php';
	@include_once WPTC_PRO_DIR.'BackupBeforeUpdate/AutoUpdateSettings.php';

	@include_once WPTC_PRO_DIR.'Staging/init.php';
	@include_once WPTC_PRO_DIR.'Staging/Hooks.php';
	@include_once WPTC_PRO_DIR.'Staging/HooksHandler.php';
	@include_once WPTC_PRO_DIR.'Staging/Config.php';

	@include_once WPTC_PRO_DIR.'RevisionLimit/init.php';
	@include_once WPTC_PRO_DIR.'RevisionLimit/Hooks.php';
	@include_once WPTC_PRO_DIR.'RevisionLimit/HooksHandler.php';
	@include_once WPTC_PRO_DIR.'RevisionLimit/Config.php';

	@include_once WPTC_PRO_DIR.'WhiteLabel/init.php';
	@include_once WPTC_PRO_DIR.'WhiteLabel/Hooks.php';
	@include_once WPTC_PRO_DIR.'WhiteLabel/HooksHandler.php';
	@include_once WPTC_PRO_DIR.'WhiteLabel/Config.php';

	@include_once WPTC_PRO_DIR.'Vulns/init.php';
	@include_once WPTC_PRO_DIR.'Vulns/Hooks.php';
	@include_once WPTC_PRO_DIR.'Vulns/HooksHandler.php';
	@include_once WPTC_PRO_DIR.'Vulns/Config.php';

	if (class_exists('WPTC_Pro_Factory') && (is_wptc_server_req() || is_admin())) {
		WPTC_Pro_Factory::get('WPTC_Pro')->init();
	}
}

function wptc_init(){
	if(!is_wptc_server_req() && !is_admin()) {
		return false;
	}

	include_primary_files_wptc();
	include_spl_files_wptc();
	store_bridge_compatibile_values_wptc();
	do_action('just_initialized_wptc_h', '');
	wptc_init_actions();
	set_default_cron_constant();
	define_default_repo_const_wptc();
}

function wptc_style() {
	//Register stylesheet
	wp_register_style('wptc-style', plugins_url('wp-time-capsule.css', __FILE__));
	wp_enqueue_style('wptc-style', false, array(), WPTC_VERSION);
	wp_enqueue_style('dashicons', false, array(), WPTC_VERSION);
}

function define_default_repo_const_wptc(){
	$config = WPTC_Factory::get('config');
	if (!defined('DEFAULT_REPO')) {
		define('DEFAULT_REPO', $config->get_option('default_repo'));
	}

	$repo_labels_arr = array(
		'g_drive' => 'Google Drive',
		's3' => 'Amazon S3',
		'dropbox' => 'Dropbox',
	);
	if (defined('DEFAULT_REPO')) {
		$this_repo = DEFAULT_REPO;
	}
	if (!empty($this_repo) && !empty($repo_labels_arr[$this_repo])) {
		$supposed_repo_label = $repo_labels_arr[$this_repo];
	} else {
		$supposed_repo_label = 'Cloud';
	}
	if (!defined('DEFAULT_REPO_LABEL')) {
		define('DEFAULT_REPO_LABEL', $supposed_repo_label);
	}
}


/**
 * A wrapper function that adds an options page to setup Dropbox Backup.
 * @return void
 */
function wordpress_time_capsule_admin_menu() {
	$config = WPTC_Factory::get('config');
	$version = $config->get_option('wptc_version');
	$is_authorized = apply_filters('validate_users_access_wptc', '');
	dark_debug($is_authorized, '--------$is_authorized--------');
	if($is_authorized == 'not_authorized') return false;

	$text = __('WP Time Capsule', 'wptc');
	add_menu_page($text, $text, 'activate_plugins', 'wp-time-capsule-monitor', 'wordpress_time_capsule_monitor', 'dashicons-wptc', '80.0564');

	if (version_compare(PHP_VERSION, WPTC_MINUMUM_PHP_VERSION) >= 0) {
		$text = __('Backups', 'wptc');
		add_submenu_page('wp-time-capsule-monitor', $text, $text, 'activate_plugins', 'wp-time-capsule-monitor', 'wordpress_time_capsule_monitor');

		do_action('add_additional_sub_menus_wptc_h', '');

		$text = __('Activity Log', 'wptc');
		add_submenu_page('wp-time-capsule-monitor', $text, $text, 'activate_plugins', 'wp-time-capsule-activity', 'wordpress_time_capsule_activity');
	}

	$text = __('Settings', 'wptc');
	add_submenu_page('wp-time-capsule-monitor', $text, $text, 'activate_plugins', 'wp-time-capsule-settings', 'wptimecapsule_settings_hook');

	$text = __('Initial Setup', 'wptc');
	add_submenu_page(null, $text, $text, 'activate_plugins', 'wp-time-capsule', 'wordpress_time_capsule_admin_menu_contents');
	// remove_submenu_page('wp-time-capsule-monitor','wp-time-capsule');
	if (WPTC_ENV != 'production' || WPTC_DARK_TEST) {
		$text = __('Dev Options', 'wptc');
		add_submenu_page('wp-time-capsule-monitor', $text, $text, 'activate_plugins', 'wp-time-capsule-dev-options', 'wordpress_time_capsule_dev_options');
	}
	if (!WPTC_PRO_PACKAGE) {
		$text = __('WPTC PRO', 'wptc');
		//add_submenu_page('wp-time-capsule-monitor', $text, $text, 'activate_plugins', 'wp-time-capsule-pro', 'wordpress_time_capsule_pro_contents');
	} else {
		$text = __('Advanced Options', 'wptc');
		//add_submenu_page('wp-time-capsule-monitor', $text, $text, 'activate_plugins', 'wp-time-capsule-advanced', 'wordpress_time_capsule_advanced');
	}
}
/**
 * A wrapper function that includes the WP Time Capsule options page
 * @return void
 */
function wordpress_time_capsule_activity() {
	$uri = network_admin_url() . 'admin.php?page=wp-time-capsule-activity';
	include WPTC_PLUGIN_DIR.'Views/wptc-activity.php';
}

function wptimecapsule_settings_hook(){
	include_once WPTC_PLUGIN_DIR . 'Views/wptc-options-helper.php';
	$options_helper = new Wptc_Options_Helper();
	if(!$options_helper->get_is_user_logged_in()){
		wordpress_time_capsule_admin_menu_contents();
		return false;
	}
	include_once WPTC_PLUGIN_DIR . 'Views/wptc-plans.php';
	include WPTC_PLUGIN_DIR.'Views/wptc-settings.php';
}


function wordpress_time_capsule_dev_options() {
	include WPTC_PLUGIN_DIR.'Views/wptc-dev-options.php';
}

function wordpress_time_capsule_staging_options() {
	include_once WPTC_PLUGIN_DIR . 'Views/wptc-options-helper.php';
	$options_helper = new Wptc_Options_Helper();
	if(!$options_helper->get_is_user_logged_in()){
		wordpress_time_capsule_admin_menu_contents();
		return false;
	}
	global $uri;
	$uri = rtrim(plugins_url('wp-time-capsule'), '/');
	$config = WPTC_Factory::get('config');
	$is_show_login_box = (!$config->get_option('is_user_logged_in') || !$config->get_option('main_account_email')) ? true : false;
	if ($is_show_login_box) {
		include WPTC_PLUGIN_DIR.'Views/wptc-options.php';
		return false;
	}
	include WPTC_PLUGIN_DIR.'Views/wptc-staging-options.php';
}
/**
 * A wrapper function that includes the WP Time Capsule options page
 * @return void
 */
function wordpress_time_capsule_admin_menu_contents() {
	$uri = rtrim(plugins_url('wp-time-capsule'), '/');
	dark_debug($uri, '---------$uri------------');
	if (version_compare(PHP_VERSION, WPTC_MINUMUM_PHP_VERSION) >= 0) {
		include WPTC_PLUGIN_DIR.'Views/wptc-options.php';
	} else {
		include WPTC_PLUGIN_DIR.'Views/wptc-deprecated.php';
	}
}

/**
 * A wrapper function that includes the pro version of Time Capsule Contents and options
 * @return void
 */
function wordpress_time_capsule_pro_contents() {
	$uri = rtrim(plugins_url('wp-time-capsule-pro'), '/');

	include WPTC_PLUGIN_DIR.'Views/wptc-pro.php';
}

function wordpress_time_capsule_advanced() {
	$uri = network_admin_url() . 'admin.php?page=wp-time-capsule-advanced';
	include WPTC_PLUGIN_DIR.'Views/wptc-advanced.php';
}
/**
 * A wrapper function that includes the WP Time Capsule monitor page
 * @return void
 */
function wordpress_time_capsule_monitor() {
	if (defined('DEFAULT_REPO')) {
		$this_repo = DEFAULT_REPO;
	}
	if (empty($this_repo) || !WPTC_Factory::get($this_repo)->is_authorized()) {
		wordpress_time_capsule_admin_menu_contents();
	} else {
		$uri = rtrim(plugins_url('wp-time-capsule'), '/');
		include WPTC_PLUGIN_DIR.'Views/wptc-monitor.php';
	}
}

/**
 * A wrapper function for the progress AJAX request
 * @return void
 */
function tc_backup_progress_wptc() {
	include_once WPTC_PLUGIN_DIR . 'Views/wptc-options-helper.php';
	$options_helper = new Wptc_Options_Helper();
	include WPTC_PLUGIN_DIR.'Views/wptc-progress.php';
	die();
}

/**
 * A wrapper function for the progress AJAX request
 * @return void
 */
function get_this_day_backups_callback_wptc() {
	//note that we are getting the ajax function data via $_POST.
	$backupIds = $_POST['data'];

	//getting the backups
	$processed_files = WPTC_Factory::get('processed-files');
	echo $processed_files->get_this_backups_html($backupIds);
}

function get_sibling_files_callback_wptc() {
	//note that we are getting the ajax function data via $_POST.
	$file_name = $_POST['data']['file_name'];
	$file_name = wp_normalize_path($file_name);
	$backup_id = $_POST['data']['backup_id'];
	$recursive_count = $_POST['data']['recursive_count'];
	// //getting the backups
	$processed_files = WPTC_Factory::get('processed-files');
	echo $processed_files->get_this_backups_html($backup_id, $file_name, $type = 'sibling', (int) $recursive_count);
}

function get_in_progress_tcbackup_callback_wptc() {
	$in_progress_status = WPTC_Factory::get('config')->get_option('in_progress');
	echo $in_progress_status;
}

function start_backup_tc_callback_wptc($type = '') {
	//for backup during update
	$backup = new WPTC_BackupController();
	$backup->backup_now();

	// store_name_for_this_backup_callback_wptc("Updated on " . date('H-i', time()));
}

function start_fresh_backup_tc_callback_wptc($type = '', $args = null) {
	WPTC_Factory::get('Debug_Log')->wptc_log_now('Starting Manual Backup', 'BACKUP');
	reset_restore_if_long_time_no_ping();
	$config = WPTC_Factory::get('config');
	$result = is_wptc_cron_fine();
	dark_debug($result,'-----------is_wptc_cron_fine----------------');
	if($result == false){
		$config->set_option('in_progress', false);
		dark_debug(array(),'-----------Cron not connected so backup aborted----------------');
		send_response_wptc('declined_by_wptc_cron_not_connected', 'SCHEDULE');
		return false;
	}
	if ($config->get_option('in_progress', true)) {
		set_server_req_wptc(true);
		$config->set_option('recent_backup_ping', time());
		if ($type == 'daily_cycle') {
			send_response_wptc('already_daily_cycle_running', 'SCHEDULE');
		}
		set_backup_in_progress_server(true);
		send_response_wptc('already_backup_running_and_retried', $type);
	}
	// $config->set_option('wptc_profiling_start', microtime(true));
	dark_debug(array(), '-----------in progress set 1-------------');
	$config->set_option('in_progress', true);
	$config->set_option('backup_before_update_details', false);
	WPTC_Factory::get('Debug_Log')->wptc_log_now('Manual Backup flags are set', 'BACKUP');

	dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);

	if (empty($args)) {
		$args = $_POST;
	}

	do_action('just_initialized_fresh_backup_wptc_h', $args);

	$config->create_dump_dir(); //This will initialize wp_filesystem

	if (isset($_REQUEST['type'])) {
		$type = $_REQUEST['type'];
		if ($type == 'manual') {
			$config->set_option('wptc_current_backup_type', 'M');
		}
	}
	dark_debug($_REQUEST,'--------------$_REQUEST-------------');
	do_action('add_staging_req_h', time());
	dark_debug(array(), '---------coming backup 1------------');

	$config->set_option('file_list_point', 0);
	global $wpdb;
	$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_current_process`");

	$backup = new WPTC_BackupController();
	$backup->delete_prev_records();
	dark_debug(array(), '---------coming backup sfdsfs------------');
	//$config->remove_garbage_files();
	$backup->backup_now($type);
}

function stop_restore_tc_callback_wptc() {
	$backup = new WPTC_BackupController();
	$backup->stop('restore');

	add_settings_error('wptc_monitor', 'restore_stopped', __('Restore stopped.', 'wptc'), 'updated');
}

function stop_fresh_backup_tc_callback_wptc($deactivated_plugin = null) {
	//for backup during update
	$backup = new WPTC_BackupController();
	$backup->stop($deactivated_plugin);
	add_settings_error('wptc_monitor', 'backup_stopped', __('Backup stopped.', 'wptc'), 'updated');
}

function store_name_for_this_backup_callback_wptc($this_name = null) {
	if (empty($this_name)) {
		$this_name = $_POST['data'];
	}

	return store_backup_name_wptc($this_name);
	/* //process to store the name in db
		$dbObj = WPTC_Factory::db();
	*/
}

function send_restore_initiated_email_wptc($dev_option = null) {
	$config = WPTC_Factory::get('config');

	$email = $config->get_option('main_account_email');
	if (empty($dev_option)) {
		$current_bridge_file_name = $config->get_option('current_bridge_file_name');
		$resume_restore_link = site_url() . "/" . $current_bridge_file_name . "/index.php?continue=true"; //the link to the bridge init file
	} else {
		$resume_restore_link = site_url() . "/wp-tcapsule-bridge-dev-test/index.php?continue=true";
	}

	$errors = array(
		'type' => 'restore_started',
		'resume_restore_link' => $resume_restore_link,
	);

	error_alert_wptc_server($errors);
}

function start_restore_tc_callback_wptc() {
	WPTC_Factory::get('Debug_Log')->wptc_log_now('Started restore', 'RESTORE');

	global $start_time_tc_bridge;
	$start_time_tc_bridge = microtime(true);

	$config = WPTC_Factory::get('config');
	// $config->set_option('wptc_profiling_start', microtime(true)); //used only for chart

	$data = array();
	if (isset($_POST['data']) && !empty($_POST['data'])) {
		$data = $_POST['data'];
	}

	dark_debug_func_map($data, "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);

	try {
		if (!empty($data) && !empty($data['is_first_call'])) {
			//initializing restore options
			reset_restore_related_settings_wptc();
			$config->set_option('restore_post_data', 0);
			$config->set_option('restore_post_data', serialize($data));
			$config->set_option('restore_action_id', time()); //main ID used througout the restore process
			$config->set_option('in_progress_restore', true);

			if (isset($data['ignore_file_write_check']) && !empty($data['ignore_file_write_check'])) {
				$config->set_option('check_is_safe_for_write_restore', $data['ignore_file_write_check']);
			}

			$current_bridge_file_name = "wp-tcapsule-bridge-" . hash("crc32", microtime(true));
			$config->set_option('current_bridge_file_name', $current_bridge_file_name);

			$config->set_option('check_is_safe_for_write_restore', 1);
		}

		WPTC_Factory::get('Debug_Log')->wptc_log_now('Creating Dump dir', 'RESTORE');
		$config->create_dump_dir(); //This will initialize wp_filesystem
		WPTC_Factory::get('Debug_Log')->wptc_log_now('Dump dir created', 'RESTORE');

		$backup = new WPTC_BackupController();
		WPTC_Factory::get('Debug_Log')->wptc_log_now('Copying Bridge files', 'RESTORE');
		$copy_result = $backup->copy_bridge_files_tc();
		WPTC_Factory::get('Debug_Log')->wptc_log_now('Bridge files copied', 'RESTORE');

		if (!empty($copy_result) && is_array($copy_result) && !empty($copy_result['error'])) {
			echo json_encode($copy_result);
			die();
		}

		send_restore_initiated_email_wptc();
		WPTC_Factory::get('Debug_Log')->wptc_log_now('Restore init email sent', 'RESTORE');
		do_action('turn_off_auto_update_wptc', time());
		WPTC_Factory::get('Debug_Log')->wptc_log_now('Auto update turned off', 'RESTORE');
		echo json_encode(array('restoreInitiatedResult' => array('bridgeFileName' => $config->get_option('current_bridge_file_name'), 'safeToCallPluginAjax' => true)));
		die();
	} catch (Exception $e) {
		echo json_encode(array('error' => $e->getMessage()));
		die();
	}
}

function get_issue_report_data_callback_wptc() {
	$Report_issue = WPTC_BackupController::construct()->wtc_report_issue();
	echo $Report_issue;
	die();
}

function get_issue_report_specific_callback_wptc() {
	if ($_REQUEST['data']['log_id'] != "") {
		$Report_issue = WPTC_BackupController::construct()->wtc_report_issue($_REQUEST['data']['log_id']);
		echo $Report_issue;
	}
	die();
}

function store_backup_name_wptc($backup_name = '', $backup_id = null) {
	dark_debug('Function :','---------'.__FUNCTION__.'-----------------');
	global $wpdb;
	if (empty($backup_id)) {
		$backup_id = getTcCookie('backupID');
		if (empty($backup_id)) {
			return false;
		}
	}
	$sql = "SELECT count(*)
			FROM {$wpdb->base_prefix}wptc_backup_names WHERE backup_id = ".$backup_id; //manual"
	$get_row = $wpdb->get_var($sql);
	dark_debug($sql, '---------$sql------------');
	dark_debug($get_row, '---------$get_row------------');
	if (empty($get_row)) {
		$result = $wpdb->insert("{$wpdb->base_prefix}wptc_backup_names", array('backup_name' => $backup_name, 'backup_id' => $backup_id));
	} else {
		$result = $wpdb->update("{$wpdb->base_prefix}wptc_backup_names", array('backup_name' => $backup_name), array('backup_id' => $backup_id));
	}
	dark_debug($result, '---------$result------------');
	if ($result) {
		return true;
	} else {
		return false;
	}
}

function is_wptc_cron_fine(){
	$config = WPTC_Factory::get('config');
	wptc_own_cron_status();
	$cron_status = $config->get_option('wptc_own_cron_status');
	if (!empty($cron_status)) {
		$cron_status = unserialize($cron_status);
		if ($cron_status['status'] == 'success') {
			return true;
		}
		return false;
	}
}

function execute_tcdropbox_backup_wptc($type = '') {
	dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);

	$config = WPTC_Factory::get('config');
	WPTC_Factory::get('Debug_Log')->wptc_log_now('Backup call is sent to server', 'BACKUP');
	set_backup_in_progress_server(true);

	$backup_id = getTcCookie('backupID');
	$config->set_option('backup_action_id', $backup_id);

	$this_repo = $config->get_option('default_repo');

	WPTC_Factory::get('logger')->delete_log();
	WPTC_Factory::get('logger')->log(sprintf(__('Backup started on %s.', 'wptc'), date("l F j, Y", strtotime(current_time('mysql')))), 'backup_start', $backup_id);
	WPTC_Factory::get('logger')->log(sprintf(__('Connected Repo is %s.', 'wptc'), $this_repo), 'backup_start', $backup_id);
	$time = ini_get('max_execution_time');
	WPTC_Factory::get('logger')->log(sprintf(
		__('Your time limit is %s and your memory limit is %s'),
		$time ? $time . ' ' . __('seconds', 'wptc') : __('unlimited', 'wptc'),
		ini_get('memory_limit')
	), 'backup_progress', $backup_id);
	if (ini_get('safe_mode')) {
		WPTC_Factory::get('logger')->log(__("Safe mode is enabled on your server so the PHP time and memory limit cannot be set by the backup process. So if your backup fails it's highly probable that these settings are too low.", 'wptc'), 'backup_progress', $backup_id);
	}
	dark_debug(array(), '-----------in progress set again-------------');
	$config->set_option('in_progress', true);
	$config->set_option('ignored_files_count', 0);
	$config->set_option('mail_backup_errors', 0);
	$config->set_option('frequently_changed_files', false);
	// $config->set_option('cached_wptc_g_drive_folder_id', 0);
	// $config->set_option('cached_g_drive_this_site_main_folder_id', 0);

	$cur_time = microtime(true);
	$config->set_option('starting_backup_first_call_time', $cur_time);

	if (!wp_next_scheduled('monitor_tcdropbox_backup_hook_wptc')) {
		// schedule_every_min_monitor_backup_hook_wptc();
		// revitalize_monitor_hook_wptc(); 30 seconds cron
	}

	//run_tc_backup_wptc();
}

function monitor_tcdropbox_backup_wptc($args = 0) {
	$config = WPTC_Factory::get('config');

	WPTC_Base_Factory::get('Wptc_Base')->init();

	dark_debug(array(), "----- monitor_tcdropbox_backup_wptc called--------");
	do_action('inside_monitor_backup_pre_wptc_h', '');

	if ($config->get_option('in_progress')) {
		if (apply_filters('is_upgrade_in_progress_wptc', '')) {
			send_response_wptc('UPGRADE_UNDER_PROGRESS_REQUEST_ME_LATER', 'SCHEDULE');
		}
		WPTC_Factory::get('Debug_Log')->wptc_log_now('in_progress is true', 'BACKUP');
		$config->set_option('recent_backup_ping', time());
		// dark_debug(get_backtrace_string_wptc(), '---------get_backtrace_string_wptc-------------');
		dark_debug(date("g:i:s a l F j, Y"), "--------monitor bakcup is accepted --------");

		// $config->set_option('wptc_profiling_start', microtime(true));

		manual_debug_wptc('', 'monitorBackupStart');
		$config->set_option('is_running', false);
		update_prev_backups($config);
		run_tc_backup_wptc();

	} else {
		dark_debug(array(), "----- monitor_tcdropbox_backup_wptc rejected--------");
		set_backup_in_progress_server(false);
		reset_backup_related_settings_wptc();
		do_action('is_staging_backup_wptc_h', time());
		return 'declined';
	}
}

function update_prev_backups(&$config){
	if(!$config->get_option('update_prev_backups_1')){
		return false;
	}
	$processed_files = WPTC_Factory::get('processed-files');
	$processed_files->update_prev_backups_1();
}

function run_tc_backup_wptc($type = '') {
	dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);
	$options = WPTC_Factory::get('config');
	if (is_any_ongoing_wptc_restore_process()) {
		dark_debug(array(), "--------is_any_ongoing_wptc_restore_process--------");
		$options->set_option('recent_restore_ping', time());
		send_response_wptc('declined_by_running_restore', 'SCHEDULE');
		return false;
	}
	if (!$options->get_option('is_running')) {
		$options->create_dump_dir();
		$options->set_option('is_running', true);
		$contents = @unserialize($options->get_option('this_cookie'));
		$backup_id = $contents['backupID'];
		if (!empty($backup_id) && !$options->get_option('bbu_upgrade_process_running')) {
			WPTC_Factory::get('logger')->log(__('Resuming backup.', 'wptc'), 'backup_progress', $backup_id);
		}
		// WPTC_BackupController::construct()->execute($type);
		$backup = new WPTC_BackupController();
		$backup->execute($type);
	}
}

function backup_tc_cron_schedules($schedules) {
	$new_schedules = array(
		'every_min' => array(
			'interval' => 60,
			'display' => 'WPTC - Every one minute',
		),
		'every_two_min' => array(
			'interval' => 120,
			'display' => 'WPTC - Every two minutes',
		),
		'every_ten' => array(
			'interval' => 600,
			'display' => 'WPTC - Every ten minutes',
		),
		'every_twenty' => array(
			'interval' => 1200,
			'display' => 'WPTC - Every twenty minutes',
		),
		'half_hour' => array(
			'interval' => 1800,
			'display' => 'WPTC - Every half hour',
		),
		'every_hour' => array(
			'interval' => 3600,
			'display' => 'WPTC - Every Hour',
		),
		'every_four' => array(
			'interval' => 14400,
			'display' => 'WPTC - Every Four Hours',
		),
		'every_six' => array(
			'interval' => 21600,
			'display' => 'WPTC - Every Six Hours',
		),
		'every_eight' => array(
			'interval' => 28800,
			'display' => 'WPTC - Every Eight Hours',
		),
		'daily' => array(
			'interval' => 86400,
			'display' => 'WPTC - Daily',
		),
		'weekly' => array(
			'interval' => 604800,
			'display' => 'WPTC - Weekly',
		),
		'fortnightly' => array(
			'interval' => 1209600,
			'display' => 'WPTC - Fortnightly',
		),
		'monthly' => array(
			'interval' => 2419200,
			'display' => 'WPTC - Once Every 4 weeks',
		),
		'two_monthly' => array(
			'interval' => 4838400,
			'display' => 'WPTC - Once Every 8 weeks',
		),
		'three_monthly' => array(
			'interval' => 7257600,
			'display' => 'WPTC - Once Every 12 weeks',
		),
	);

	return array_merge($schedules, $new_schedules);
}

function wptc_install() {
	global $wpdb;
	$wpdb = WPTC_Factory::db();

	if (method_exists($wpdb, 'get_charset_collate')) {
		$charset_collate = $wpdb->get_charset_collate();
	}

	if (!empty($charset_collate)) {
		$cachecollation = $charset_collate;
	} else {
		$cachecollation = ' DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci ';
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table_name = $wpdb->base_prefix . 'wptc_options';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
		name varchar(50) NOT NULL,
		value text NOT NULL,
		UNIQUE KEY name (name)
	) " . $cachecollation . " ;");

	$table_name = $wpdb->base_prefix . 'wptc_processed_files';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
	  `file` text DEFAULT NULL,
	  `offset` int(50) NULL DEFAULT '0',
	  `uploadid` text DEFAULT NULL,
	  `file_id` bigint(20) NOT NULL AUTO_INCREMENT,
	  `backupID` double DEFAULT NULL,
	  `revision_number` text DEFAULT NULL,
	  `revision_id` text DEFAULT NULL,
	  `mtime_during_upload` varchar(22) DEFAULT NULL,
	  `uploaded_file_size` bigint(20) DEFAULT NULL,
	  `g_file_id` text DEFAULT NULL,
	  `s3_part_number` int(10) DEFAULT NULL,
	  `s3_parts_array` longtext DEFAULT NULL,
	  `cloud_type` varchar(50) DEFAULT NULL,
	  `parent_dir` TEXT DEFAULT NULL,
	  `is_dir` INT(1) DEFAULT NULL,
	  `file_hash` varchar(128) DEFAULT NULL,
	  `life_span` double DEFAULT NULL,
	  PRIMARY KEY (`file_id`),
	  UNIQUE KEY `file_backup_id` (`file`(191),`backupID`),
	  INDEX `revision_id` (`revision_id`(191)),
	  INDEX `file` (`file`(191))
	) ENGINE=InnoDB  " . $cachecollation . ";");

	$table_name = $wpdb->base_prefix . 'wptc_processed_dbtables';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
		name varchar(255) NOT NULL,
		count int NOT NULL DEFAULT 0,
		UNIQUE KEY `name` (`name`(191))
	) " . $cachecollation . " ;");

	$table_name = $wpdb->base_prefix . 'wptc_excluded_files';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
		id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
		file varchar(255) NOT NULL,
		isdir tinyint(1) NOT NULL,
		UNIQUE KEY `file` (`file`(191))
	) ENGINE=InnoDB " . $cachecollation . " ;");

	$table_name = $wpdb->base_prefix . 'wptc_included_files';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
		id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
		file varchar(255) NOT NULL,
		isdir tinyint(1) NOT NULL,
		UNIQUE KEY `file` (`file`(191))
	) ENGINE=InnoDB " . $cachecollation . " ;");

	$table_name = $wpdb->base_prefix . 'wptc_excluded_tables';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
		id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
		table_name varchar(255) NOT NULL,
		UNIQUE KEY `table_name` (`table_name`(191))
	) ENGINE=InnoDB " . $cachecollation . " ;");

	$table_name = $wpdb->base_prefix . 'wptc_included_tables';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
		id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
		table_name varchar(255) NOT NULL,
		UNIQUE KEY `table_name` (`table_name`(191))
	) ENGINE=InnoDB " . $cachecollation . " ;");


	$table_name = $wpdb->base_prefix . 'wptc_processed_restored_files';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
	  `file` text NOT NULL,
	  `offset` int(50) DEFAULT '0',
	  `uploadid` text DEFAULT NULL,
	  `file_id` bigint(20) NOT NULL AUTO_INCREMENT,
	  `backupID` double DEFAULT NULL,
	  `revision_number` text DEFAULT NULL,
	  `revision_id` text DEFAULT NULL,
	  `mtime_during_upload` varchar(22) DEFAULT NULL,
	  `download_status` text DEFAULT NULL,
	  `uploaded_file_size` text DEFAULT NULL,
	  `process_type` text DEFAULT NULL,
	  `copy_status` text DEFAULT NULL,
	  `g_file_id` text DEFAULT NULL,
	  `file_hash` varchar(128) DEFAULT NULL,
	  PRIMARY KEY (`file_id`),
	  INDEX `revision_id` (`revision_id`(191)),
	  INDEX `file` (`file`(191))
	) ENGINE=InnoDB  " . $cachecollation . ";");

	$table_name = $wpdb->base_prefix . 'wptc_backup_names';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
	  `this_id` int(11) NOT NULL AUTO_INCREMENT,
	  `backup_name` text DEFAULT NULL,
	  `backup_id` text DEFAULT NULL,
	  PRIMARY KEY (`this_id`)
	) ENGINE=InnoDB  " . $cachecollation . ";");

	$table_name = $wpdb->base_prefix . 'wptc_current_process';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
		   `id` bigint(20) NOT NULL AUTO_INCREMENT,
		   `file_path` varchar(600) NOT NULL,
		   `status` char(1) NOT NULL DEFAULT 'Q' COMMENT 'P=Processed, Q= In Queue, S- Skipped',
		   `processed_time` varchar(30) NOT NULL,
		   `file_hash` varchar(128) DEFAULT NULL,
		   PRIMARY KEY (`id`)
		) ENGINE=InnoDB " . $cachecollation . ";");

	$table_name = $wpdb->base_prefix . 'wptc_activity_log';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
			`id` bigint(20) NOT NULL AUTO_INCREMENT,
			`time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`type` varchar(50) NOT NULL,
			`log_data` text NOT NULL,
			`parent` tinyint(1) NOT NULL DEFAULT '0',
			`parent_id` bigint(20) NOT NULL,
			`is_reported` tinyint(1) NOT NULL DEFAULT '0',
			`report_id` varchar(50) NOT NULL,
			`action_id` text NOT NULL,
			`show_user` ENUM('1','0') NOT NULL DEFAULT '1',
			PRIMARY KEY (`id`),
			UNIQUE KEY `id` (`id`),
			INDEX `action_id` (`action_id`(191)),
			INDEX `show_user` (`show_user`)
		  ) ENGINE=InnoDB  " . $cachecollation . ";");

	$table_name = $wpdb->base_prefix . 'wptc_backups';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
			`id` bigint(20) NOT NULL AUTO_INCREMENT,
			`backup_id` varchar(100) NOT NULL,
			`backup_type` char(1) NOT NULL COMMENT 'M = Manual, D = Daily Main Cycle , S- Sub Cycle',
			`files_count` int(11) NOT NULL,
			`memory_usage` text NOT NULL,
			`update_details` text DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `id` (`id`)
		  ) ENGINE=InnoDB  " . $cachecollation . ";");

	$table_name = $wpdb->base_prefix . 'wptc_auto_backup_record';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
		  `ID` int(11) NOT NULL AUTO_INCREMENT,
		  `timestamp` double NOT NULL,
		  `type` enum('upload','plugin-update','theme-update','core-update','other-update','bulk-plugin-update','bulk-theme-update','plugin-install','theme-install','core-install','other-install','file-edit','img-upload','video-upload','other-upload') NOT NULL,
		  `file` text,
		  `backup_status` enum('noted','queued','backed_up') DEFAULT 'noted',
		  `prev_backup_id` double DEFAULT '0',
		  `cur_backup_id` double DEFAULT '0',
		  PRIMARY KEY (`ID`)
		) ENGINE=InnoDB " . $cachecollation . ";");

	$table_name = $wpdb->base_prefix . 'wptc_debug_log';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`user_time` varchar(22) NOT NULL,
		`system_time` varchar(22) NOT NULL,
		`type` varchar(100) DEFAULT NULL,
		`log` text NOT NULL,
		`backup_id` varchar(22) DEFAULT NULL,
		`memory` varchar(20) DEFAULT NULL,
		`peak_memory` varchar(20) DEFAULT NULL,
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB " . $cachecollation . " ;");

	//Ensure that there where no insert errors
	$errors = array();

	global $EZSQL_ERROR;
	if ($EZSQL_ERROR) {
		foreach ($EZSQL_ERROR as $error) {
			if (preg_match("/^CREATE TABLE IF NOT EXISTS {$wpdb->base_prefix}wptc_/", $error['query'])) {
				$errors[] = $error['error_str'];
			}

		}

		delete_option('wptc-init-errors');
		add_option('wptc-init-errors', implode($errors, '<br />'), false, 'no');
	}

	//Only set the DB version if there are no errors
	if (empty($errors) && !get_option('wptc_installed')) {
		WPTC_Factory::get('config')->set_option('database_version', WPTC_DATABASE_VERSION);
		WPTC_Factory::get('config')->set_option('wptc_version', WPTC_VERSION);
		WPTC_Factory::get('config')->set_option('activity_log_lazy_load_limit', '25');
		WPTC_Factory::get('config')->set_option('backup_type_setting', 'SCHEDULE');
		WPTC_Factory::get('config')->set_option('backup_before_update_setting', 'everytime');
		WPTC_Factory::get('config')->set_option('revision_limit', WPTC_FALLBACK_REVISION_LIMIT_DAYS);
		WPTC_Factory::get('config')->set_option('run_init_setup_bbu', true);
		WPTC_Base_Factory::get('Wptc_ExcludeOption')->insert_default_excluded_files();
		WPTC_Base_Factory::get('Wptc_App_Functions')->set_user_to_access();
		WPTC_Factory::get('config')->set_option('internal_staging_db_rows_copy_limit', 1000);
		WPTC_Factory::get('config')->set_option('internal_staging_file_copy_limit', 500);
		WPTC_Factory::get('config')->set_option('dropbox_oauth_upgraded', true);
		WPTC_Factory::get('config')->set_option('is_autoupdate_vulns_settings_enabled', true);
		WPTC_Base_Factory::get('Wptc_Settings')->auto_whitelist_ips();
		add_option('wptc_installed', true);
	}
	dark_debug(array(), "--------installing finished--------");
}

function check_wptc_update() {
	$config = WPTC_Factory::get('config');

	$installed_db_version = $config->get_option('database_version');

	if ($installed_db_version && (version_compare('3.0', $installed_db_version) > 0)) {
		wptc_upgrade_db();
		set_default_repo_for_previous_update_wptc($config);
		$config->set_option('first_backup_started_atleast_once', true);
		process_wptc_logout();
		$config->set_option('database_version', '3.0');
	}
	if ($installed_db_version && (version_compare('4.0', $installed_db_version) > 0)) {
		create_auto_backup_db_wptc();
		$config->set_option('database_version', '4.0');
	}

	if ($installed_db_version && (version_compare('5.0', $installed_db_version) > 0)) {
		wptc_database_changes_5_0();
		$config->set_option('database_version', '5.0');
		$config->set_option('user_came_from_existing_ver', 1);
	}

	if ($installed_db_version && (version_compare('6.0', $installed_db_version) > 0)) {
		wptc_database_changes_6_0();
		$config->set_option('database_version', '6.0');
	}
	if ($installed_db_version && (version_compare('7.0', $installed_db_version) > 0)) {
		wptc_database_changes_7_0();
		$config->set_option('database_version', '7.0');
	}

	if ($installed_db_version && (version_compare('8.0', $installed_db_version) > 0)) {
		wptc_database_changes_8_0();
		$config->set_option('database_version', '8.0');
	}

	if ($installed_db_version && (version_compare('9.0', $installed_db_version) > 0)) {
		wptc_database_changes_9_0();
		$config->set_option('database_version', '9.0');
	}

	if ($installed_db_version && (version_compare('10.0', $installed_db_version) > 0)) {
		wptc_database_changes_10_0();
		$config->set_option('database_version', '10.0');
	}

	if ($installed_db_version && (version_compare('11.0', $installed_db_version) > 0)) {
		wptc_database_changes_11_0();
		$config->set_option('database_version', '11.0');
	}

	if ($installed_db_version && (version_compare('12.0', $installed_db_version) > 0)) {
		wptc_database_changes_12_0();
		$config->set_option('database_version', '12.0');
	}

	if ($installed_db_version && (version_compare('13.0', $installed_db_version) > 0)) {
		wptc_database_changes_13_0();
		$config->set_option('database_version', '13.0');
	}

	$installed_wptc_version = $config->get_option('wptc_version');
	if (empty($installed_wptc_version)) {
		$installed_wptc_version = WPTC_VERSION;
	}
	if (version_compare('1.0.0beta4.2', $installed_wptc_version) > 0) {
		process_wptc_logout();
	}
	if (version_compare('1.0.0beta4.3', $installed_wptc_version) > 0) {
		schedule_every_min_monitor_backup_hook_wptc();
	}
	if (version_compare('1.0.0beta4.4', $installed_wptc_version) > 0) {
		wp_clear_scheduled_hook('wptc_sub_cycle_event');
		schedule_10_min_rounded_off_forced_event_wptc();
	}

	if (version_compare('1.0.0RC1', $installed_wptc_version) > 0) {
		clear_gdrive_backup_data_wptc();
		$table_refactoring = $config->get_option('wptc_update_progress');
		if (empty($table_refactoring)) {
			$config->set_option('wptc_update_progress', 'start');
		}
		wp_clear_scheduled_hook('wptc_sub_cycle_event');
		schedule_10_min_rounded_off_forced_event_wptc();
		wp_clear_scheduled_hook('monitor_tcdropbox_backup_hook_wptc');
	}

	if (version_compare('1.0.0', $installed_wptc_version) > 0) {
		$config->set_option('activity_log_lazy_load_limit', '25');
		// dark_debug(array(), '-----------Updating hooks schedule_every_min_monitor_backup_hook_wptc set-------------');
		wp_clear_scheduled_hook('wptc_sub_cycle_event');
		wp_clear_scheduled_hook('wptc_schedule_cycle_event');
		wp_clear_scheduled_hook('monitor_tcdropbox_backup_hook_wptc');
		signup_wptc_server_wptc();
		wptc_own_cron_status();
		dark_debug(array(), '-----------new cron URL updated-------------');
		// schedule_every_min_monitor_backup_hook_wptc();
	}

	if (version_compare('1.1.1', $installed_wptc_version) > 0) {
		signup_wptc_server_wptc();
		wptc_own_cron_status();
		dark_debug(array(), '-----------new cron URL update once again-------------');
	}

	if (version_compare('1.1.2', $installed_wptc_version) > 0) {
		$backup = new WPTC_BackupController();
		$backup->clear_current_backup();
		dark_debug(array(), '-----------Current package up record removed-------------');
	}

	if (version_compare('1.2.0', $installed_wptc_version) > 0) {
		reset_restore_related_settings_wptc();
		dark_debug(array(), '-----------reset_restore_related_settings_wptc while update-------------');
	}

	if (version_compare('1.3.0', $installed_wptc_version) > 0) {
		$config->set_option('got_exclude_files', true);
		$config->set_option('user_came_from_existing_ver', 1);
		do_action("wptc_got_exclude_files");
		dark_debug(array(), '-----------set got_exclude_files while update-------------');
	}

	if (version_compare('1.3.1', $installed_wptc_version) > 0) {
		$config->delete_option('user_excluded_files_and_folders');
		dark_debug(array(), '-----------user_excluded_files_and_folders deleted-------------');
	}

	if (version_compare('1.4.0', $installed_wptc_version) > 0) {
		if (is_any_ongoing_wptc_backup_process()) {
			$config->set_option('recent_backup_ping', time());
		}
		$config->is_main_account_authorized();
	}

	if (version_compare('1.4.3', $installed_wptc_version) > 0) {
		$config->set_option('insert_default_excluded_files', false);
	}

	if (version_compare('1.4.4', $installed_wptc_version) > 0) {
		do_action('reset_stats', time());
	}


	if (version_compare('1.4.6', $installed_wptc_version) > 0) {
		WPTC_Base_Factory::get('Wptc_ExcludeOption')->get_store_file_and_db_size();
	}

	if (version_compare('1.5.3', $installed_wptc_version) > 0) {
		$config->set_option('update_default_excluded_files', false);
	}

	if (version_compare('1.6.0', $installed_wptc_version) > 0) {
		$config->delete_option('backup_db_path', false);
		$config->set_option('user_came_from_existing_ver', true);
		$config->set_option('backup_type_setting', 'SCHEDULE');
	}

	if (version_compare('1.7.0', $installed_wptc_version) > 0) {
		$config->set_option('backup_type_setting', 'SCHEDULE');
	}

	if (version_compare('1.7.2', $installed_wptc_version) > 0) {
		$config->set_option('update_prev_backups_1', true);
	}

	if (version_compare('1.8.0', $installed_wptc_version) > 0) {
		$config->set_option('existing_users_rev_limit_hold', time());
		$config->set_option('revision_limit', WPTC_FALLBACK_REVISION_LIMIT_DAYS);
		$config->set_option('run_init_setup_bbu', true);
	}

	if (version_compare('1.8.4', $installed_wptc_version) > 0) {
		$config->set_option('internal_staging_db_rows_copy_limit', 1000);
		$config->set_option('internal_staging_file_copy_limit', 1000);
	}

	if (version_compare('1.8.5', $installed_wptc_version) > 0) {
		clear_inc_exc_tables();
		WPTC_Base_Factory::get('Wptc_ExcludeOption')->update_default_files_n_tables();
	}

	if (version_compare('1.9.0', $installed_wptc_version) > 0) {
		$config->set_option('internal_staging_file_copy_limit', 500);
		$config->set_option('run_staging_updates', '1.9.0');
		windows_machine_reset_backups_wptc();
		WPTC_Base_Factory::get('Wptc_ExcludeOption')->update_default_files_n_tables();
	}

	if (version_compare('1.9.1', $installed_wptc_version) > 0) {
		dark_debug($installed_wptc_version, '--------$currnet installed version--------');
		$staging_type = $config->get_option('staging_type');
		dark_debug($staging_type, '--------$staging_type--------');
		if (empty($staging_type) && $installed_wptc_version == '1.9.0' && WPTC_Base_Factory::get('Wptc_App_Functions')->is_user_purchased_this_class('Wptc_Staging')) {
			update_option('blog_public', 1);
		}
	}

	if (version_compare('1.9.3', $installed_wptc_version) > 0) {
		$config->delete_option('update_prev_backups_1', false);
		$config->delete_option('update_prev_backups_1_pointer', false);
	}

	if (version_compare('1.9.4', $installed_wptc_version) > 0) {
		$config->delete_option('gotfileslist_multicall_count', false);
		if($config->get_option('default_repo') === 'dropbox'){
			$dropbox = WPTC_Factory::get('dropbox');
			$dropbox->migrate_to_v2();
			$config->set_option('dropbox_oauth_upgraded', true);
		}
		$config->set_option('update_default_excluded_files', false);
		WPTC_Base_Factory::get('Wptc_ExcludeOption')->update_default_excluded_files();
	}

	if (version_compare('1.10.0', $installed_wptc_version) > 0) {
		$config->set_option('is_autoupdate_vulns_settings_enabled', true);
		do_action('analyse_free_paid_plugins_themes_wptc', time());
		do_action('send_ptc_list_to_server_wptc', time());
		do_action('turn_off_themes_auto_updates_wptc', time());
		do_action('exclude_paid_plugin_from_au_wptc', time());
	}

	if (version_compare('1.10.2', $installed_wptc_version) > 0) {
		WPTC_Base_Factory::get('Wptc_App_Functions')->validate_dropbox_upgrade();
	}

	if (version_compare(WPTC_VERSION, $installed_wptc_version) > 0) {
		//This executes on every update
		$config->set_option('first_backup_started_atleast_once', true);
		$config->set_option('prev_installed_wptc_version', $installed_wptc_version);
		$config->set_option('wptc_version', WPTC_VERSION);
		$Wptc_Backup_Analytics = new Wptc_Backup_Analytics();
		$Wptc_Backup_Analytics->send_basic_analytics();
		$Wptc_Backup_Analytics->send_cloud_account_used();
		do_action('send_backups_data_to_server_wptc', time());
		WPTC_Base_Factory::get('Wptc_Settings')->auto_whitelist_ips();
		wptc_modify_schedule_backup();

		if(is_any_ongoing_wptc_backup_process()){
			set_backup_in_progress_server(true);
		}
	}
}

function clear_gdrive_backup_data_wptc() {
	$config = WPTC_Factory::get('config');
	if ($config->get_option('default_repo') == 'g_drive') {
		$backup = new WPTC_BackupController();
		$backup->clear_prev_repo_backup_files_record();
	}
}

function schedule_every_min_monitor_backup_hook_wptc(){
	dark_debug(array(), '-----------Hook set-------------');
	$adjusted_time =  (round(time()/60) * 60) - 2 ;
	wp_clear_scheduled_hook('monitor_tcdropbox_backup_hook_wptc');
	wp_schedule_event($adjusted_time, 'every_min', 'monitor_tcdropbox_backup_hook_wptc');
}

function process_parent_dirs_wptc($result, $type) {
	if (empty($type)) {
		$result = json_decode(json_encode($result), True);
	}
	$wp_path = WPTC_ABSPATH;
	$break = false;
	$path = $result['file'];
	$backup_id = $result['backupID'];
	$path = str_replace($wp_path, '', $path);
	$dirs = explode('/', $path);
	$breadcrumb = '';
	$new_file = '';
	$parent_dir = '';
	$path = str_replace($wp_path, '', $path);
	$dirs = explode('/', $path);
	$breadcrumb = '';
	$cacheArray = array();
	while (count($dirs) > 0) {
		$link = '/' . implode($dirs, '/');
		$text = array_pop($dirs);
		$link = ltrim($link, '/');
		if (empty($type)) {
			$new_file = wp_normalize_path($wp_path . $link);
		} else {
			$new_file = $wp_path . $link;
		}
		if (empty($type)) {
			$parent_dir = wp_normalize_path(get_parent_dir_from_path_wptc($link, $wp_path));
		} else {
			$parent_dir = get_parent_dir_from_path_wptc($link, $wp_path);
		}
		$cacheCheck = $new_file . '/' . $backup_id . '/' . $parent_dir;
		if (is_array($cacheArray)) {
			if (in_array($cacheCheck, $cacheArray)) {
				break;
			}
		}
		if (empty($type)) {
			lazy_load_insert_or_update_row_wptc($new_file, $backup_id, $parent_dir);
		} else if ($type == 'process_files') {
			$processed_files = WPTC_Factory::get('processed-files');
			if (is_dir($new_file)) {
				$is_dir = 1;
				$processed_files->base_upsert(array(
					'file' => $new_file,
					'uploadid' => null,
					'offset' => 0,
					'backupID' => $result['backupID'],
					'revision_number' => null,
					'revision_id' => null,
					'mtime_during_upload' => null,
					'uploaded_file_size' => null,
					'cloud_type' => null,
					'parent_dir' => $parent_dir,
					'is_dir' => $is_dir,
					'file_hash' => '',
				));
			} else {
				$is_dir = 0;
				$processed_files->base_upsert(array(
					'file' => $new_file,
					'uploadid' => $result['uploadid'],
					'offset' => $result['offset'],
					'backupID' => $result['backupID'],
					'revision_number' => $result['revision_number'],
					'revision_id' => $result['revision_id'],
					'mtime_during_upload' => $result['mtime_during_upload'],
					'uploaded_file_size' => $result['uploaded_file_size'],
					'g_file_id' => $result['g_file_id'],
					'cloud_type' => $result['cloud_type'],
					'parent_dir' => $parent_dir,
					'is_dir' => $is_dir,
					'file_hash' => $result['file_hash'],
				));
			}
		}
		$cacheArray[] = $new_file . '/' . $backup_id . '/' . $parent_dir;
	}
}

function get_parent_dir_from_path_wptc($link, $wp_path) {
	$breadcrumb = substr($link, 0, strrpos($link,  '/' ));
	if (empty($breadcrumb)) {
		$parent_dir = rtrim($wp_path,  '/' );
	} else {
		$parent_dir = $wp_path . $breadcrumb;
	}
	return $parent_dir;
}

function lazy_load_insert_or_update_row_wptc($new_file, $backup_id, $parent_dir) {
	global $wpdb;
	if (is_dir($new_file)) {

		$is_dir = 1;
	} else {
		$is_dir = 0;
	}
	$sqlTmp = "SELECT * FROM " . $wpdb->base_prefix . "wptc_processed_files WHERE backupID = '$backup_id' AND file = '$new_file' LIMIT 1";
	$row_exist = $wpdb->get_results($sqlTmp);
	if (count($row_exist) == 0) {
		$mysql_query = "INSERT INTO " . $wpdb->base_prefix . "wptc_processed_files (`file`, `offset`, `uploadid`, `file_id`, `backupID` ,`revision_number`, `revision_id`, `mtime_during_upload`, `uploaded_file_size`, `g_file_id`, `s3_part_number`, `s3_parts_array`, `cloud_type`, `parent_dir`, `is_dir`) VALUES ('$new_file', 0, NULL, NULL, $backup_id, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$parent_dir', '$is_dir' )";
		$result_temp = $wpdb->query($mysql_query);
		if ($wpdb->last_error !== '') {
			$wpdb->print_error();
		}

	} else {
		$wpdb->query("UPDATE " . $wpdb->base_prefix . "wptc_processed_files SET parent_dir = '$parent_dir', is_dir = '$is_dir' WHERE backupID = '$backup_id' AND file = '$new_file'");
	}
}

function create_auto_backup_db_wptc() {
	global $wpdb;

	if (method_exists($wpdb, 'get_charset_collate')) {
		$charset_collate = $wpdb->get_charset_collate();
	}

	if (!empty($charset_collate)) {
		$cachecollation = $charset_collate;
	} else {
		$cachecollation = ' DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci ';
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table_name = $wpdb->base_prefix . 'wptc_auto_backup_record';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
		  `ID` int(11) NOT NULL AUTO_INCREMENT,
		  `timestamp` double NOT NULL,
		  `type` enum('upload','plugin-update','theme-update','core-update','other-update','bulk-plugin-update','bulk-theme-update','plugin-install','theme-install','core-install','other-install','file-edit','img-upload','video-upload','other-upload') NOT NULL,
		  `file` text,
		  `backup_status` enum('noted','queued','backed_up') DEFAULT 'noted',
		  `prev_backup_id` double DEFAULT '0',
		  `cur_backup_id` double DEFAULT '0',
		  PRIMARY KEY (`ID`)
		) ENGINE=InnoDB " . $cachecollation . ";");
}

function wptc_database_changes_5_0() {
	global $wpdb;
	$wptc_processed_files = $wpdb->base_prefix . 'wptc_processed_files';
	$add_column_result = $wpdb->query('ALTER TABLE `' . $wptc_processed_files . '` ADD `parent_dir` TEXT DEFAULT NULL , ADD `is_dir` INT(1) DEFAULT NULL;');
	$add_index_key_result = $wpdb->query('ALTER TABLE `' . $wptc_processed_files . '` ADD KEY `file_backup_id` (`file`(191),`backupID`);');
	$modify_option_value = $wpdb->query('ALTER TABLE `' . $wptc_processed_files . '` MODIFY COLUMN `value` TEXT;');
	$add_index_revision_id = $wpdb->query('ALTER TABLE `' . $wptc_processed_files . '` ADD INDEX `revision_id` (`revision_id`(191))');
	$add_index_file = $wpdb->query('ALTER TABLE `' . $wptc_processed_files . '` ADD INDEX `file` (`file`(191))');

	$wptc_processed_restored_files = $wpdb->base_prefix . 'wptc_processed_restored_files';
	$add_index_revision_id_restore_table = $wpdb->query('ALTER TABLE `' . $wptc_processed_restored_files . '` ADD INDEX `revision_id` (`revision_id`(191))');
	$add_index_file_restore_table = $wpdb->query('ALTER TABLE `' . $wptc_processed_restored_files . '` ADD INDEX `file` (`file`(191))');
}

function wptc_database_changes_6_0() {
	global $wpdb;
	$wptc_options = $wpdb->base_prefix . 'wptc_options';

	$modify_option_value = $wpdb->query('ALTER TABLE `' . $wptc_options . '` MODIFY COLUMN `value` TEXT;');
}

function wptc_database_changes_7_0() {
	$config = WPTC_Factory::get('config');
	$config->set_option('backup_before_update_setting', 'everytime');

	global $wpdb;
	$wptc_activity_log = $wpdb->base_prefix . 'wptc_activity_log';
	$modify_activity_log = $wpdb->query("ALTER TABLE `" . $wptc_activity_log . "` ADD `show_user` ENUM('1','0') NOT NULL DEFAULT '1' ");
	$add_index_activity_id_activity_log = $wpdb->query('ALTER TABLE `' . $wptc_activity_log . '` ADD INDEX `action_id` (`action_id`(191))');
	$add_index_show_user_activity_log = $wpdb->query('ALTER TABLE `' . $wptc_activity_log . '` ADD INDEX `show_user` (`show_user`)');
}

function wptc_database_changes_8_0() {
	global $wpdb;

	if (method_exists($wpdb, 'get_charset_collate')) {
		$charset_collate = $wpdb->get_charset_collate();
	}

	if (!empty($charset_collate)) {
		$cachecollation = $charset_collate;
	} else {
		$cachecollation = ' DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci ';
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table_name = $wpdb->base_prefix . 'wptc_excluded_tables';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
		id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
		table_name varchar(255) NOT NULL,
		UNIQUE KEY `table_name` (`table_name`(191))
	) ENGINE=InnoDB " . $cachecollation . " ;");
	$wptc_exc_tables = $wpdb->base_prefix . 'wptc_excluded_files';

	$table_name = $wpdb->base_prefix . 'wptc_included_tables';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
		id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
		table_name varchar(255) NOT NULL,
		UNIQUE KEY `table_name` (`table_name`(191))
	) ENGINE=InnoDB " . $cachecollation . " ;");

	$table_name = $wpdb->base_prefix . 'wptc_included_files';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
		id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
		file varchar(255) NOT NULL,
		isdir tinyint(1) NOT NULL,
		UNIQUE KEY `file` (`file`(191))
	) ENGINE=InnoDB " . $cachecollation . " ;");

	$wptc_exc_tables = $wpdb->base_prefix . 'wptc_excluded_files';
	$add_id_exc_files = $wpdb->query('ALTER TABLE `' . $wptc_exc_tables . '` ADD `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;');
}

function wptc_database_changes_9_0() {
	global $wpdb;
	$wptc_current_process = $wpdb->query("ALTER TABLE `" . $wpdb->base_prefix . "wptc_current_process` ADD `file_hash` varchar(128) NULL");
	$wptc_processed_files = $wpdb->query("ALTER TABLE `" . $wpdb->base_prefix . "wptc_processed_files` ADD `file_hash` varchar(128) NULL");
}

function wptc_database_changes_10_0() {
	global $wpdb;
	$wptc_processed_files = $wpdb->query("ALTER TABLE `" . $wpdb->base_prefix . "wptc_processed_files` ADD `life_span` double NULL");
}

function wptc_database_changes_11_0() {
	global $wpdb;

	if (method_exists($wpdb, 'get_charset_collate')) {
		$charset_collate = $wpdb->get_charset_collate();
	}

	if (!empty($charset_collate)) {
		$cachecollation = $charset_collate;
	} else {
		$cachecollation = ' DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci ';
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table_name = $wpdb->base_prefix . 'wptc_debug_log';
	dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`user_time` varchar(22) NOT NULL,
		`system_time` varchar(22) NOT NULL,
		`type` varchar(100) DEFAULT NULL,
		`log` text NOT NULL,
		`backup_id` varchar(22) DEFAULT NULL,
		`memory` varchar(20) DEFAULT NULL,
		`peak_memory` varchar(20) DEFAULT NULL,
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB " . $cachecollation . " ;");
}

function wptc_database_changes_12_0() {
	global $wpdb;
	$processed_restored_files = $wpdb->query("ALTER TABLE `" . $wpdb->base_prefix . "wptc_processed_restored_files` ADD `file_hash` varchar(128) NULL");
}

function wptc_database_changes_13_0() {
	global $wpdb;
	$wp_wptc_backups = $wpdb->query("ALTER TABLE `" . $wpdb->base_prefix . "wptc_backups` ADD `update_details` text DEFAULT NULL");
}

function wptc_upgrade_db() {
	WPTC_Factory::get('logger')->log('Altering WPTC DB');

	modify_files_table_wptc();
	modify_restore_files_table_wptc();

	WPTC_Factory::get('logger')->log('Finished Altering WPTC DB');
}

function process_wptc_logout($deactivated_plugin = null) {

	stop_fresh_backup_tc_callback_wptc($deactivated_plugin);

	$config = WPTC_Factory::get('config');

	$config->set_option('is_user_logged_in', false);
	$config->set_option('wptc_server_connected', false);
	$config->set_option('signup', false);
	$config->set_option('appID', false);
	$config->set_option('main_account_email', 0);
	$config->set_option('main_account_pwd', 0);
	$config->set_option('privileges_wptc', false);

	$config->set_option('wptc_token', false);

	reset_restore_related_settings_wptc();

	reset_backup_related_settings_wptc();
}

function set_default_repo_for_previous_update_wptc(&$config) {
	$config->set_option('default_repo', 'dropbox');

	$signed_in_arr['dropbox'] = 'Dropbox';
	$config->set_option('signed_in_repos', serialize($signed_in_arr));
}

function modify_files_table_wptc() {
	global $wpdb;

	$table_name = $wpdb->base_prefix . "wptc_processed_files";

	if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
		$sql = array();

		$sql[] = "alter table " . $table_name . " ADD file text ;";

		$sql[] = "alter table " . $table_name . " change offset offset int(50) DEFAULT '0';";

		$sql[] = "alter table " . $table_name . " change uploadid uploadid TEXT NULL;";

		$sql[] = "alter table " . $table_name . " change file_id file_id bigint(20) NOT NULL AUTO_INCREMENT ;";

		$sql[] = "alter table " . $table_name . " change backupID backupID double DEFAULT NULL ;";

		$sql[] = "alter table " . $table_name . " change revision_number revision_number text DEFAULT NULL ;";

		$sql[] = "alter table " . $table_name . " change revision_id revision_id text DEFAULT NULL ;";

		$sql[] = "alter table " . $table_name . " change uploaded_file_size uploaded_file_size bigint(20) DEFAULT NULL ;";

		$sql[] = "alter table " . $table_name . " ADD g_file_id text DEFAULT NULL ;";

		$sql[] = "alter table " . $table_name . " ADD s3_part_number int(10) DEFAULT NULL ;";

		$sql[] = "alter table " . $table_name . " ADD s3_parts_array longtext DEFAULT NULL ;";

		$sql[] = "alter table " . $table_name . " ADD cloud_type varchar(50) DEFAULT NULL ;";

		$this_return = array();
		foreach ($sql as $v) {
			$this_return[] = $wpdb->query($v);
		}
	}
}

function modify_restore_files_table_wptc() {
	global $wpdb;

	$table_name = $wpdb->base_prefix . "wptc_processed_restored_files";

	if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
		$sql = array();

		$sql[] = "alter table " . $table_name . " change file file TEXT NOT NULL;";

		$sql[] = "alter table " . $table_name . " change uploadid uploadid TEXT NULL;";

		$sql[] = "alter table " . $table_name . " change file_id file_id bigint(20) NOT NULL AUTO_INCREMENT ;";

		$sql[] = "alter table " . $table_name . " change revision_number revision_number text DEFAULT NULL ;";

		$sql[] = "alter table " . $table_name . " change revision_id revision_id text DEFAULT NULL ;";

		$sql[] = "alter table " . $table_name . " ADD g_file_id text DEFAULT NULL ;";

		$this_return = array();
		foreach ($sql as $v) {
			$this_return[] = $wpdb->query($v);
		}
	}
}

function wptc_init_flags() {
	try {
		check_wptc_update();
		if (defined('FS_METHOD') && FS_METHOD === 'direct') {
			global $wp_filesystem;
			if (!$wp_filesystem) {
				initiate_filesystem_wptc();
				if (empty($wp_filesystem)) {
					send_response_wptc('FS_INIT_FAILED-034');
					return false;
				}
			}
		}
		if(is_wptc_server_req() || current_user_can('activate_plugins')){
			WPTC_Factory::get('config')->choose_db_backup_path();
		}

		if (!get_option('wptc-premium-extensions')) {
			add_option('wptc-premium-extensions', array(), false, 'no');
		}

		if (!WPTC_Factory::get('config')->get_option('before_backup')) {
			WPTC_Factory::get('config')->set_option('before_backup', 'yes_no');
		}

		if (!WPTC_Factory::get('config')->get_option('anonymous_datasent')) {
			WPTC_Factory::get('config')->set_option('anonymous_datasent', 'no');
		}

		if (!WPTC_Factory::get('config')->get_option('auto_backup_interval')) {
			WPTC_Factory::get('config')->set_option('auto_backup_interval', 'every_ten');
		}

		if (!WPTC_Factory::get('config')->get_option('auto_backup_switch')) {
			WPTC_Factory::get('config')->set_option('auto_backup_switch', 'off');
		}

		if (!WPTC_Factory::get('config')->get_option('schedule_backup')) {
			WPTC_Factory::get('config')->set_option('schedule_backup', 'off');
		}

		if (!WPTC_Factory::get('config')->get_option('wptc_timezone')) {
			if (get_option('timezone_string') != "") {
				WPTC_Factory::get('config')->set_option('wptc_timezone', get_option('timezone_string'));
			} else {
				WPTC_Factory::get('config')->set_option('wptc_timezone', 'UTC');
			}
		}

		if (!WPTC_Factory::get('config')->get_option('schedule_day')) {
			WPTC_Factory::get('config')->set_option('schedule_day', 'sunday');
		}
		if (!get_option('is_wptc_activation_redirected', false)) {
			add_option('is_wptc_activation_redirected', true);
			wp_safe_redirect(network_admin_url() . '?page=wp-time-capsule-monitor');
		}
		if (!WPTC_Factory::get('config')->get_option('wptc_service_request')) {
			WPTC_Factory::get('config')->set_option('wptc_service_request', 'no');
		}
	} catch (Exception $e) {
		error_log($e->getMessage());
	}
}

function my_tcadmin_notice_wptc() {
	$options_obj = WPTC_Factory::get('config');
	if (!$options_obj->get_option('restore_completed_notice')) {
		//do nothing
	} else {
		$options_obj->set_option('restore_completed_notice', false);
		/* $notice_message = "<div class='updated'> <p> "._e( 'Restored Successfully', 'my-text-domain' )."</p> </div>";
	echo $notice_message; */
	}
}

function send_wtc_issue_report_wptc() {
	$options_obj = WPTC_Factory::get('config');
	$data = $_REQUEST['data'];
	$random = generate_random_string_wptc();
	if (empty($data['name'])) {
		$data['name'] = 'Admin';
	}
	global $wpdb;
	$report_issue_data['server']['PHP_VERSION'] 	= phpversion();
	$report_issue_data['server']['PHP_CURL_VERSION']= curl_version();
	$report_issue_data['server']['PHP_WITH_OPEN_SSL'] = function_exists('openssl_verify');
	$report_issue_data['server']['PHP_MAX_EXECUTION_TIME'] =  ini_get('max_execution_time');
	$report_issue_data['server']['PHP_MEMORY_LIMIT'] =  ini_get('memory_limit');
	$report_issue_data['server']['MYSQL_VERSION'] 	= $wpdb->get_var("select version() as V");
	$report_issue_data['server']['OS'] =  php_uname('s');
	$report_issue_data['server']['OSVersion'] =  php_uname('v');
	$report_issue_data['server']['Machine'] =  php_uname('m');
	$report_issue_data['server']['PHP_DISABLED_FUNCTIONS'] = explode(',', ini_get('disable_functions'));
	array_walk($report_issue_data['server']['PHP_DISABLED_FUNCTIONS'], 'trim_value_wptc');

	$report_issue_data['server']['PHP_DISABLED_CLASSES'] = explode(',', ini_get('disable_classes'));
	array_walk($report_issue_data['server']['PHP_DISABLED_CLASSES'], 'trim_value_wptc');

	$report_issue_data['server']['browser'] = $_SERVER['HTTP_USER_AGENT'];
	$report_issue_data['server']['reportTime'] = time();
	$plugin_data['url'] = home_url();
	$plugin_data['main_account_email'] = $options_obj->get_option('main_account_email');
	$plugin_data['appID'] = $options_obj->get_option('appID');
	$plugin_data['wptc_version'] = $options_obj->get_option('wptc_version');
	$plugin_data['wptc_database_version'] = $options_obj->get_option('database_version');

	$logs['issue']['issue_data'] = $data['issue_data'];
	$logs['issue']['plugin_info'] = serialize($plugin_data);
	dark_debug($report_issue_data, '--------$report_issue_data--------');
	$logs['issue']['server_info'] = serialize($report_issue_data);
	$final_log = serialize($logs);
	dark_debug($logs,'--------------$report_issue_data-------------');
	$post_arr = array(
		'type' => 'issue',
		'issue' => $final_log,
		'useremail' => $data['email'],
		'title' => $data['desc'],
		'rand' => $random,
		'name' => $data['name'],
	);


	// dark_debug($post_arr, '--------$post_arr--------');
	dark_debug( http_build_query($post_arr, '', '&'), "--------http_build_query--------");

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, WPTC_APSERVER_URL . "/report_issue/index.php");
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
	// curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_arr, '', '&'));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

	$result = curl_exec($ch);

	dark_debug($result, "--------curl result report issue--------");

	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$curlErr = curl_errno($ch);

	dark_debug($httpCode, '--------$httpCode--------');
	dark_debug($curlErr, '--------$curlErr--------');

	curl_close($ch);

	if ($curlErr || ($httpCode == 404)) {
		WPTC_Factory::get('logger')->log("Curl Error no : $curlErr - While Sending the Report data to server", 'connection_error');
		echo "fail";
		exit();
	} else {
		if ($result == 'insert_success') {
			echo "sent";
			die();
		} else {
			echo "insert_fail";
			die();
		}
		exit();
	}
}

function set_wtc_content_type($content_type) {
	return 'text/html';
}
/**
 * On an early action hook, check if the hook is scheduled - if not, schedule it.
 */
function wptc_setup_schedule() {
	if (!wp_next_scheduled('wptc_anonymous_event')) {
		wp_schedule_single_event(time(), 'wptc_anonymous_event');
		wp_schedule_event(time(), 'weekly', 'wptc_anonymous_event');
	}
}
/**
 * On the scheduled action hook, run a function.
 */
function anonymous_event_wptc() {
	$config = WPTC_Factory::get('config');
	$anonymous_flag = $config->get_option('anonymous_datasent');
	if ($anonymous_flag == "yes") {
		$app_hash = $config->get_option('wptc_app_hash');
		if ($app_hash == '' || $app_hash == null) {
			$salt = time();
			$current_user = get_option('admin_email');
			$app_hash = sha1($current_user . $salt);
			$config->set_option('wptc_app_hash', $app_hash);
		}
		$anonymousData = serialize(WPTC_BackupController::construct()->wtc_get_anonymous_data());
		$random = generate_random_string_wptc();

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, WPTC_APSERVER_URL . "/anonymous_data/index.php");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		// curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "type=anonymous&data=" . $anonymousData . "&app_hash=" . $app_hash . "&rand=" . $random);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

		curl_exec($ch);
		$curlErr = curl_errno($ch);
		curl_close($ch);
		if ($curlErr) {
			WPTC_Factory::get('logger')->log("Curl Error no: $curlErr - While Sending the Anonymous data to server", 'connection_error');
		}
	}
}

//Clear the WPTC log's completely
function clear_wptc_logs() {
	global $wpdb;
	if ($wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_activity_log`")) {
		echo 'yes';
	} else {
		echo 'no';
	}
	die();
}

function clear_inc_exc_tables(){
	global $wpdb;
	$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_included_files`");
	$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_excluded_files`");
	$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_included_tables`");
	$wpdb->query("TRUNCATE TABLE `" . $wpdb->base_prefix . "wptc_excluded_tables`");
}

function dropbox_auth_check_wptc() {
	//Dropbox auth checking for continue process
	dark_debug(DEFAULT_REPO, "--------dropbox_auth_check_wptc--------");
	$dropbox = WPTC_Factory::get(DEFAULT_REPO);
	if ( !empty($dropbox)  && $dropbox->is_authorized()) {
		reset_backup_related_settings_wptc();
		WPTC_Factory::get('config')->set_option('last_cloud_error', false);
		echo 'authorized';
		die();
	} else {
		$err_msg = WPTC_Factory::get('config')->get_option('last_cloud_error');
		echo "not authorized " . $err_msg;
		die();
	}

}

function wptc_main_cycle() {
	//wptc_main_cycle is for daily full backup
	$config = new WPTC_Config();
	$usertime = $config->get_wptc_user_today_date_time('Y-m-d');
	//weekly backup validate
	// if (( is_weekly_backup_eligible($config)  || ($config->get_option('wptc_today_main_cycle') != $usertime) ) && ($config->get_option('wptc_main_cycle_running') != true) && $config->get_option('first_backup_started_atleast_once') ) {
	if ($config->get_option('wptc_today_main_cycle') != $usertime && ($config->get_option('wptc_main_cycle_running') != true) && $config->get_option('first_backup_started_atleast_once') ) {
		$config->set_option('wptc_main_cycle_running', true);
		wptc_main_cycle_event();
	} else {
		if ($config->get_option('wptc_main_cycle_running') && $config->get_option('first_backup_started_atleast_once') && !is_any_ongoing_wptc_backup_process()) {
				$config->set_option('wptc_main_cycle_running', true);
				$config->get_option('wptc_main_cycle_running', false);
				dark_debug(array(), '---------declined_by_wptc_main_cycle RARE case------------');
		}
		dark_debug(array(), '---------declined_by_wptc_main_cycle------------');
		send_response_wptc('declined_by_wptc_main_cycle', 'SCHEDULE');
	}
}

function wptc_main_cycle_event() {
	$options = WPTC_Factory::get('config');
	if (!$options->get_option('in_progress_restore') && !$options->get_option('is_running') && !$options->get_option('is_bridge_process') && !$options->get_option('is_running_restore') && !$options->get_option('is_staging_running')) {
		do_action('just_starting_main_schedule_backup_wptc_h', '');
		$options->set_option('wptc_current_backup_type', 'D');
		start_fresh_backup_tc_callback_wptc('daily_cycle');
	} else {
		if (!$options->get_option('in_progress_restore')) {
			$options->set_option('recent_restore_ping', time());
			send_response_wptc('declined_by_progress_restore', 'SCHEDULE');
		}
		if (!$options->get_option('is_running')) {
			send_response_wptc('declined_by_is_running', 'SCHEDULE');
		}
		if (!$options->get_option('is_bridge_process')) {
			$options->set_option('recent_restore_ping', time());
			send_response_wptc('declined_by_bridge_process', 'SCHEDULE');
		}
		if (!$options->get_option('is_running_restore')) {
			$options->set_option('recent_restore_ping', time());
			send_response_wptc('declined_by_running_restore', 'SCHEDULE');
		}
	}
}

function reset_restore_if_long_time_no_ping(){
	$options = WPTC_Factory::get('config');
	$recent_restore_ping = $options->get_option('recent_restore_ping');
	dark_debug($recent_restore_ping,'--------------$recent_restore_ping-------------');
	if (empty($recent_restore_ping)) {
		return false;
	}
	$current_time = time();
	$min_idle_time = $recent_restore_ping + (30 * 60); // 30 mins
	dark_debug($current_time,'--------------$current_time-------------');
	dark_debug($min_idle_time,'--------------$min_idle_time-------------');
	if ($min_idle_time < $current_time) {
		WPTC_Factory::get('Debug_Log')->wptc_log_now('No restore calls for long time so reset restore', 'BACKUP');
		dark_debug(array(), '-----------there is no chance just reverrt it-------------');
		reset_restore_related_settings_wptc();
	} else {
		WPTC_Factory::get('Debug_Log')->wptc_log_now('There is possible for restore retry', 'BACKUP');
		dark_debug(array(), '-----------thre is chance retry-------------');
	}
}

function reset_backup_if_long_time_no_ping($restart = 0, $return = 0, $type = null){
	$options = WPTC_Factory::get('config');
	$recent_backup_ping = $options->get_option('recent_backup_ping');
	dark_debug($recent_backup_ping,'--------------$recent_backup_ping-------------');
	if (empty($recent_backup_ping)) {
		return false;
	}
	$current_time = time();
	$min_idle_time = $recent_backup_ping + (60 * 60); // 80 mins
	dark_debug($current_time,'--------------$current_time-------------');
	dark_debug($min_idle_time,'--------------$min_idle_time-------------');
	if ($min_idle_time < $current_time) {
		dark_debug(array(), '-----------there is no chance just reverrt it-------------');
		if ($return) {
			return true;
		}
		$backup = new WPTC_BackupController();
		$backup->stop();
	} else {
		dark_debug(array(), '-----------thre is chance retry-------------');
		if ($return) {
			return false;
		}
	}
	if ($restart) {
		if (empty($type)) {
			$type = get_recent_backup_type();
		}
		dark_debug($type, '---------$type restart ------------');
		start_fresh_backup_tc_callback_wptc($type);
	}
}

function get_recent_backup_type(){
	$config = WPTC_Factory::get('config');
	if ($config->get_option('schedule_backup_running')) {
		return 'daily_cycle';
	} else if ($config->get_option('wptc_main_cycle_running') || $config->get_option('wptc_sub_cycle_running')) {
		return 'sub_cycle';
	} else {
		return 'manual';
	}

}

function sub_cycle_event_func_wptc($request_type = null) {
	// Sub cycle event trigger the backup (file and DB incremental process)
	$options = WPTC_Factory::get('config');
	if (!defined('DEFAULT_CRON_TYPE_WPTC')) {
		set_default_cron_constant();
	}
	if(empty($request_type) || $options->get_option('backup_type_setting') != DEFAULT_CRON_TYPE_WPTC){
		send_response_wptc('wrong_cron_type', DEFAULT_CRON_TYPE_WPTC);
	}

	reset_restore_if_long_time_no_ping();
	// reset_backup_if_long_time_no_ping(1, 0, $request_type);

	$tt = time();
	$usertime_full_stamp = $options->cnvt_UTC_to_usrTime($tt);
	$usertime_full = date('j M, g:ia', $usertime_full_stamp);

	dark_debug($usertime_full, "--------auto backup coming--------");

	$cur_time = date('Y-m-d H:i:s');
	dark_debug($cur_time, "--------trying to trigger sub cycle event--------");
	dark_debug(error_get_last(), "--------last error--------");

	$options->set_option('last_cron_triggered_time', $usertime_full);
	$first_backup_started_atleast_once = $options->get_option('first_backup_started_atleast_once');
	dark_debug($first_backup_started_atleast_once,'--------------$first_backup_started_atleast_once-------------');
	if (is_any_ongoing_wptc_restore_process() || is_any_ongoing_wptc_backup_process() || is_any_other_wptc_process_going_on() || !$options->get_option('default_repo') || !$options->get_option('main_account_email') || !$options->get_option('is_user_logged_in')) {
		dark_debug(array(), '-----------False returned-------------');
		if (is_any_ongoing_wptc_restore_process()) {
			$options->set_option('recent_restore_ping', time());
			send_response_wptc('declined_restore_in_progress', DEFAULT_CRON_TYPE_WPTC);
		} else if (is_any_ongoing_wptc_backup_process()) {
			$options->set_option('recent_backup_ping', time());
			set_backup_in_progress_server(true);
			send_response_wptc('declined_backup_in_progress_and_retried', DEFAULT_CRON_TYPE_WPTC);
		} else if (is_any_other_wptc_process_going_on()) {
			do_action('init_staging_wptc_h', time());
			send_response_wptc('declined_staging_processes_in_progress_and_retried', DEFAULT_CRON_TYPE_WPTC);
		} else if(!$options->get_option('default_repo')) {
			send_response_wptc('declined_default_repo_empty', DEFAULT_CRON_TYPE_WPTC);
		} else if(!$options->get_option('main_account_email')) {
			send_response_wptc('declined_main_account_email_empty', DEFAULT_CRON_TYPE_WPTC);
		} else if(!$options->get_option('is_user_logged_in')) {
			send_response_wptc('declined_user_logged_out', DEFAULT_CRON_TYPE_WPTC);
		}
		return false;
	}

	$first_backup_started_but_not_completed = $options->get_option('starting_first_backup'); // true first backup started but not completed

	if($options->get_option('backup_type_setting') == 'WEEKLYBACKUP' && $request_type == 'WEEKLYBACKUP'){
		if (is_weekly_backup_eligible($options) && !$first_backup_started_but_not_completed) {
			wptc_main_cycle();
		} else {
			send_response_wptc('declined_by_time', DEFAULT_CRON_TYPE_WPTC);
		}
	} else {
		$usertime = $options->get_wptc_user_today_date_time('Y-m-d');
		$wptc_today_main_cycle = $options->get_option('wptc_today_main_cycle');
		if ($wptc_today_main_cycle == $usertime && !$first_backup_started_but_not_completed) {
			if (defined('WPTC_AUTO_BACKUP') && WPTC_AUTO_BACKUP === true && $request_type == 'AUTOBACKUP' && $options->get_option('backup_type_setting') == 'AUTOBACKUP') {
				dark_debug(array(), '----------Trying start to auto backup---------');
				do_action('start_auto_backup');
			} else {
				send_response_wptc('declined_by_time', DEFAULT_CRON_TYPE_WPTC);
			}
		} else {
			dark_debug(array(), '---------auto backup else------------');
			if ($request_type == 'SCHEDULE' && $options->get_option('backup_type_setting') == 'SCHEDULE' || $request_type == 'AUTOBACKUP' && $options->get_option('backup_type_setting') == 'AUTOBACKUP') {
				if ($wptc_today_main_cycle == $usertime) {
				dark_debug(array(), '---------auto backup triggered schedule------------');
					send_response_wptc('declined_by_time', DEFAULT_CRON_TYPE_WPTC);
				} else {
					if (is_eligible_for_daily_backup($options)) {
						wptc_main_cycle();
					} else {
						send_response_wptc('declined_by_time', DEFAULT_CRON_TYPE_WPTC);
					}
				}
				dark_debug(array('current_time' => $options->get_some_min_advanced_current_hour_of_the_day_wptc(), 'schedule_time' => $options->get_option('schedule_time_str')), "--------deciding schedule--------");
			} else {
				send_response_wptc('declined_by_conditions', DEFAULT_CRON_TYPE_WPTC);
			}
		}
	}
}

function is_eligible_for_daily_backup($options){
	//if pro users then allow daily backup
	if (!WPTC_Base_Factory::get('Wptc_App_Functions')->is_free_user_wptc())
		return true;

	//free user and allow weekly backups every 7 days
	if(is_weekly_backup_eligible($options))
		return true;

	//free user and not allow before 7 days
	return false;
}

function is_weekly_backup_eligible(&$options){
	$last_schedule_ran = $options->get_option('wptc_today_main_cycle');

	//assuming users has not completed first backup yet
	if (empty($last_schedule_ran)) {
		return true;
	}

	// $last_schedule_ran =date_create($last_schedule_ran);
	if(!function_exists('date_create') || !function_exists('date_diff')){
		send_response_wptc('date_create or date_diff functions not exist', DEFAULT_CRON_TYPE_WPTC);
	}

	$last_schedule_ran =date_create($last_schedule_ran);

	$now = $options->get_wptc_user_today_date_time('Y-m-d');

	$now = date_create($now);

	$diff = date_diff($last_schedule_ran, $now);

	dark_debug($diff->d, '--------$days--------');

	if ($diff->d >= 7) {
		return true;
	}

	return false;
}

function is_any_ongoing_wptc_backup_process() {
	$options = WPTC_Factory::get('config');
	if (($options) && ($options->get_option('in_progress') || $options->get_option('is_running') || $options->get_option('wptc_main_cycle_running') || $options->get_option('wptc_sub_cycle_running') || $options->get_option('wptc_update_progress') == 'start')) {
			//dark_debug(array(), "--------on going backup process--------");
			return true;
		}
	return false;
}

function is_any_ongoing_wptc_restore_process() {
	$options = WPTC_Factory::get('config');
	if ($options->get_option('in_progress_restore') || $options->get_option('is_bridge_process') || $options->get_option('is_running_restore') || $options->get_option('wptc_update_progress') == 'start') {
		dark_debug(array(), "--------On going restore process--------");
		return true;
	}
	return false;
}



function cron_handler_options_table_wptc($option_name, $old, $new) {
	//dark_debug($type, "--------start_fresh_backup_tc_callback_wptc--------");
	if ($option_name == 'cron') {
		//dark_debug(func_get_args(), "--------cron options updated--------");
		//dark_debug(get_backtrace_string_wptc(20), "--------cron_handler_options_table_wptc backtrace--------");
	}
}

register_activation_hook(__FILE__, 'wptc_install');


//load_plugin_textdomain('wptc', false, 'wp-time-capsule/Languages/');

function register_the_js_events_wptc($hook) {
	wp_enqueue_style('tc-ui', plugins_url() . '/' . basename(dirname(__FILE__)) . '/tc-ui.css', array(), WPTC_VERSION);
	wp_enqueue_style('opentip', plugins_url() . '/' . basename(dirname(__FILE__)) . '/css/opentip.css', array(), WPTC_VERSION);
	wp_enqueue_script('jquery', false, array(), WPTC_VERSION);
	wp_enqueue_script('time-capsule-update-actions', plugins_url() . '/' . basename(dirname(__FILE__)) . '/time-capsule-update-actions.js', array(), WPTC_VERSION);
	wp_enqueue_script('ProCommonListener', plugins_url() . '/' . basename(dirname(__FILE__)) . '/js/ProCommonListener.js', array(), WPTC_VERSION);
	wp_enqueue_script('wptc-opentip-jquery', plugins_url() . '/' . basename(dirname(__FILE__)) . '/js/opentip-jquery.js', array(), WPTC_VERSION);
	wp_enqueue_script('wptc-clipboard-js', plugins_url() . '/' . basename(dirname(__FILE__)) . '/js/clipboard.min.js', array(), WPTC_VERSION);
}

add_action('admin_enqueue_scripts', 'register_the_js_events_wptc');

function wptc_init_actions(){
	if (is_admin() && current_user_can('activate_plugins')) {
		//Custom filters and actions
		if(is_multisite()){
			add_action('network_admin_notices', 'my_tcadmin_notice_wptc');
		} else {
			add_action('admin_notices', 'my_tcadmin_notice_wptc');
		}
		// add_filter('cron_schedules', 'backup_tc_cron_schedules');
		add_action('wptc_anonymous_event', 'anonymous_event_wptc');
		//Auto backup hooks disabled now
		// add_action('wptc_sub_cycle_event', 'sub_cycle_event_func_wptc');
		// add_action('wptc_schedule_cycle_event', 'sub_cycle_event_func_wptc');
		add_action('monitor_tcdropbox_backup_hook_wptc', 'monitor_tcdropbox_backup_wptc');
		add_action('run_tc_backup_hook_wptc', 'run_tc_backup_wptc');
		add_action('execute_periodic_drobox_backup_wptc', 'execute_tcdropbox_backup_wptc');
		add_action('execute_instant_drobox_backup_wptc', 'execute_tcdropbox_backup_wptc');
		add_action('schedule_backup_event_wptc', 'schedule_backup_wptc');
		// add_action('admin_bar_menu', 'wptc_admin_bar_icons', 71); disabled from v1.7.3

		if (WPTC_ENV != 'production') {
			add_action('updated_option', 'cron_handler_options_table_wptc', 10, 3);
		}

		//add_action('admin_init', 'wptc_init_flags');
		add_action('admin_enqueue_scripts', 'wptc_style');
		add_action('wp', 'wptc_setup_schedule');

		do_action('add_query_filter_wptc', time());
		add_action('load-index.php', 'admin_notice_on_dashboard_wptc');

		//WordPress filters and actions
		add_action('wp_ajax_progress_wptc', 'tc_backup_progress_wptc');
		add_action('wp_ajax_get_this_day_backups_wptc', 'get_this_day_backups_callback_wptc');
		add_action('wp_ajax_get_sibling_files_wptc', 'get_sibling_files_callback_wptc');
		add_action('wp_ajax_get_in_progress_backup_wptc', 'get_in_progress_tcbackup_callback_wptc');
		add_action('wp_ajax_start_backup_tc_wptc', 'start_backup_tc_callback_wptc');
		add_action('wp_ajax_store_name_for_this_backup_wptc', 'store_name_for_this_backup_callback_wptc');
		add_action('wp_ajax_start_fresh_backup_tc_wptc', 'start_fresh_backup_tc_callback_wptc');
		add_action('wp_ajax_stop_fresh_backup_tc_wptc', 'stop_fresh_backup_tc_callback_wptc');
		add_action('wp_ajax_stop_restore_tc_wptc', 'stop_restore_tc_callback_wptc');
		add_action('wp_ajax_start_restore_tc_wptc', 'start_restore_tc_callback_wptc');
		add_action('wp_ajax_get_issue_report_data_wptc', 'get_issue_report_data_callback_wptc');
		add_action('wp_ajax_send_wtc_issue_report_wptc', 'send_wtc_issue_report_wptc');
		add_action('wp_ajax_get_issue_report_specific_wptc', 'get_issue_report_specific_callback_wptc');
		add_action('wp_ajax_clear_wptc_logs', 'clear_wptc_logs');
		add_action('wp_ajax_continue_with_wtc', 'dropbox_auth_check_wptc');
		add_action('wp_ajax_signup_service_wptc', 'signup_wptc_server_wptc');
		add_action('wp_ajax_subscribe_me_wptc', 'wptc_subscribe_me');
		add_action('wp_ajax_get_dropbox_authorize_url_wptc', 'get_dropbox_authorize_url_wptc');
		add_action('wp_ajax_get_g_drive_authorize_url_wptc', 'get_g_drive_authorize_url_wptc');
		add_action('wp_ajax_get_s3_authorize_url_wptc', 'get_s3_authorize_url_wptc');
		add_action('wp_ajax_change_wptc_default_repo', 'change_wptc_default_repo');
		add_action('wp_ajax_plugin_update_notice_wptc', 'plugin_update_notice_wptc');
		add_action('wp_ajax_lazy_load_activity_log_wptc', 'lazy_load_activity_log_wptc');
		add_action('wp_ajax_update_sycn_db_view_wptc', 'update_sycn_db_view_wptc');
		add_action('wp_ajax_save_initial_setup_data_wptc', 'save_initial_setup_data_wptc');
		// add_action('wp_ajax_update_test_connection_err_shown_wptc', 'update_test_connection_err_shown_wptc');
		add_action('wp_ajax_test_connection_wptc_cron', 'test_connection_wptc_cron');
		add_action('wp_ajax_save_general_settings_wptc', 'save_general_settings_wptc');
		add_action('wp_ajax_save_backup_settings_wptc', 'save_backup_settings_wptc');
		add_action('wp_ajax_resume_backup_wptc', 'resume_backup_wptc');
		add_action('wp_ajax_proceed_to_pay_wptc', 'proceed_to_pay_wptc');
		add_action('wp_ajax_save_manual_backup_name_wptc', 'save_manual_backup_name_wptc');
		add_action('wp_ajax_is_backup_running_wptc', 'is_backup_running_wptc');
		add_action('wp_ajax_clear_show_users_backend_errors_wptc', 'clear_show_users_backend_errors_wptc');
		add_action('wp_ajax_get_admin_notices_wptc', 'get_admin_notices_wptc');
		if (defined('MULTISITE') && MULTISITE) {
			add_action('network_admin_menu', 'wordpress_time_capsule_admin_menu');
		} else {
			add_action('admin_menu', 'wordpress_time_capsule_admin_menu');
		}
	}
}
		$scheduled_time_string = WPTC_DEFAULT_SCHEDULE_TIME_STR;

function wptc_activation() {
	$logger = WPTC_Factory::get('logger');
	$logger->log('WP Time Capsule Activated', 'activated_plugin');
}

function wptc_deactivation() {
	stop_wptc_server();
	process_wptc_logout('deactivated_plugin');
	$logger = WPTC_Factory::get('logger');
	$logger->log('WP Time Capsule Deactivated', 'deactivated_plugin');
	delete_option('is_wptc_activation_redirected');
	wp_clear_scheduled_hook('execute_periodic_drobox_backup_wptc');
	wp_clear_scheduled_hook('execute_instant_drobox_backup_wptc');
	wp_clear_scheduled_hook('monitor_tcdropbox_backup_hook_wptc');
	wp_clear_scheduled_hook('wptc_sub_cycle_event');
	wp_clear_scheduled_hook('wptc_schedule_cycle_event');
	wp_clear_scheduled_hook('wp_scheduled_auto_draft_delete');
	wp_clear_scheduled_hook('wptc_anonymous_event');
}

register_activation_hook(__FILE__, 'wptc_activation');
register_deactivation_hook(__FILE__, 'wptc_deactivation');

//Add or modify the schedule backup in wptc
function wptc_modify_schedule_backup() {
	$config = WPTC_Factory::get('config');

	if (!$config->get_option('schedule_time_str')) {
		$config->set_option('schedule_time_str', WPTC_DEFAULT_SCHEDULE_TIME_STR);
	}
	if ($config->get_option('wptc_server_connected') && $config->get_option('wptc_service_request') == 'yes') {
		push_settings_wptc_server();
	}
}

function init_auto_backup_settings_wptc(&$config) {
	$config->set_option('auto_backup_switch', 'on');
	$config->set_option('auto_backup_interval', 'every_ten');
	$config->set_option('wptc_service_request', 'yes');

	$scheduled_time_string = $config->get_option('schedule_time_str');
	if (!$scheduled_time_string) {
		$scheduled_time_string = WPTC_DEFAULT_SCHEDULE_TIME_STR;
	}
	$config->set_option('schedule_time_str', $scheduled_time_string);

	// if (WPTC_LOCAL_AUTO_BACKUP) {
	// 	if (!wp_next_scheduled('wptc_sub_cycle_event')) {
	// 		// schedule_10_min_rounded_off_forced_event_wptc();
	// 	}
	// }
}

function schedule_10_min_rounded_off_forced_event_wptc() {
	// $adjusted_time = mktime(0, 0, 0) - (2 * 60);

	// wp_clear_scheduled_hook('wptc_sub_cycle_event');
	// wp_schedule_event($adjusted_time, 'every_ten', 'wptc_sub_cycle_event');
}

//schedule backup running
function schedule_backup_wptc() {
	$options = WPTC_Factory::get('config');
	if (!$options->get_option('is_running')) {
		$options->set_option('schedule_backup_running', true);
		// $options->set_option('is_running', true);
		start_fresh_backup_tc_callback_wptc('manual');
	}
}

//Generate Random keys
function generate_random_string_wptc($length = 10) {
	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$charactersLength = strlen($characters);
	$randomString = '';
	for ($i = 0; $i < $length; $i++) {
		$randomString .= $characters[rand(0, $charactersLength - 1)];
	}
	return $randomString;
}

//Subscribe me
function wptc_subscribe_me() {
	$email = $_REQUEST['email'];
	$current_user = wp_get_current_user();
	$fname = $current_user->user_firstname;
	$lname = $current_user->user_lastname;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, WPTC_APSERVER_URL . "/subscribe_me/index.php");
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
	// curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, "email=" . $email . "&fname=" . $fname . "&lname=" . $lname);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

	// grab URL and pass it to the browser
	$result = curl_exec($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	if ($httpCode == 404) {
		echo "insert_fail";
		die();
	}
	$curlErr = curl_errno($ch);
	curl_close($ch);
	if ($curlErr) {
		WPTC_Factory::get('logger')->log("Curl Error no: $curlErr - While Subscribe the WP Time Capsule PRO", 'connection_error');
		echo "fail";
		die();
	} else {
		if ($result == '1') {
			subscribed_wptc($email);
			echo "added";
			die();
		} else {
			if ($result == '2') {
				subscribed_wptc($email);
				echo "exist";
			} else {
				echo "fail";
			}
			die();
		}
		exit();
	}
}

//Add Subscribed email into DB
function subscribed_wptc($email) {
	$options = WPTC_Factory::get('config');
	$list = $options->get_option('wptc_subscribe');
	$inserStop = false;
	if ($list != "" && $list != null) {
		$subscribers = unserialize($list);
		if (in_array($email, $subscribers)) {
			$insertStop = true;
		} else {
			array_push($subscribers, $email);
		}
	} else {
		$subscribers[] = $email;
	}
	if (!$insertStop) {
		$str = serialize($subscribers);
		$options->set_option('wptc_subscribe', $str);
	}
}

function initial_setup_notices_wptc() {
	global $wpdb;
	$fcount = $wpdb->get_results('SELECT COUNT(*) as files FROM ' . $wpdb->base_prefix . 'wptc_processed_files');

	if (!($fcount[0]->files > 0)) {
		?>
			<div class="updated">
				<p>WP Time Capsule is ready to use. <a href="<?php echo network_admin_url() . 'admin.php?page=wp-time-capsule-monitor&action=initial_setup' ?>">Take your first backup now</a>.</p>
			</div>
		<?php
	}
}

function admin_notice_on_dashboard_wptc() {
	if(is_multisite()){
		add_action('network_admin_notices', 'initial_setup_notices_wptc');
	} else {
		add_action('admin_notices', 'initial_setup_notices_wptc');
	}
}

function get_dropbox_authorize_url_wptc() {
	$config = WPTC_Factory::get('config');

	$config->set_option('default_repo', 'dropbox');
	$config->set_option('dropbox_oauth_state', 'request');
	$config->set_option('dropbox_access_token', false);

	$dropbox = WPTC_Factory::get('dropbox');

	// // if ($dropbox->is_authorized()) {
	// 	$dropbox->unlink_account()->init();
	// // }

	$result['authorize_url'] = $dropbox->get_authorize_url();
	dark_debug($result, '--------$dauthorize_url--------');
	echo json_encode($result);
	die();
}

function get_g_drive_authorize_url_wptc() {
	set_server_req_wptc();
	$config = WPTC_Factory::get('config');
	if(set_refresh_token_g_drive($config) !== false){
		die(json_encode(array('status' => 'connected')));
	}
	$g_drive_status = $config->get_option('oauth_state_g_drive');
	if ($g_drive_status == 'access') {
		$config->set_option('oauth_state_g_drive', 'revoke');
		$cloud_obj = WPTC_Factory::get('g_drive');
		$cloud_obj->reset_oauth_config()->init();
	}
	$config->set_option('default_repo', 'g_drive');
	$email = trim($config->get_option('main_account_email', true));
	$wptc_redirect_url = urlencode(base64_encode(network_admin_url() . 'admin.php?page=wp-time-capsule&wptc_account_email='.$email));
	$dauthorize_url = WPTC_G_DRIVE_AUTHORIZE_URL . '?wptc_redirect_url=' . $wptc_redirect_url .'&WPTC_ENV='.WPTC_ENV;
	$result['authorize_url'] = $dauthorize_url;
	echo json_encode($result);
	die();
}

function set_refresh_token_g_drive(&$config){
	if (empty($_POST['credsData'])) {
		return false;
	}

	if (empty($_POST['credsData']['g_drive_refresh_token'])) {
		return false;
	}
	dark_debug($_POST, '---------------$_POST-----------------');
	dark_debug(wp_unslash($_POST['credsData']['g_drive_refresh_token']), '---------------wp_unslash($_POST[credsData g_drive_refresh_token])-----------------');
	$config->set_option('default_repo', 'g_drive');
	$config->set_option('oauth_state_g_drive', 'access');
	$config->set_option('gdrive_old_token', wp_unslash($_POST['credsData']['g_drive_refresh_token']));
	$connected_obj = WPTC_Factory::get('g_drive');
	$email = trim($config->get_option('main_account_email', true));
	$refresh_token_arr = unserialize(wp_unslash($_POST['credsData']['g_drive_refresh_token']));
	$result['authorize_url'] = network_admin_url() . 'admin.php?page=wp-time-capsule&wptc_account_email='.$email. '&cloud_auth_action=g_drive&code='.$refresh_token_arr['refresh_token'];
	die(json_encode($result));
	return true;
}

function get_s3_authorize_url_wptc() {
	if (empty($_POST['credsData'])) {
		echo json_encode(array('error' => 'Didnt get credentials.'));
		die();
	}

	$as3_access_key = $_POST['credsData']['as3_access_key'];
	$as3_secure_key = $_POST['credsData']['as3_secure_key'];
	$as3_bucket_region = $_POST['credsData']['as3_bucket_region'];
	$as3_bucket_name = $_POST['credsData']['as3_bucket_name'];

	if (empty($as3_access_key) || empty($as3_secure_key) || empty($as3_bucket_name)) {
		echo json_encode(array('error' => 'Didnt get credentials.'));
		die();
	}

	$config = WPTC_Factory::get('config');
	$config->set_option('as3_access_key', $as3_access_key);
	$config->set_option('as3_secure_key', $as3_secure_key);
	$config->set_option('as3_bucket_region', $as3_bucket_region);
	$config->set_option('as3_bucket_name', $as3_bucket_name);
	$config->set_option('default_repo', 's3');
	$result['authorize_url'] = network_admin_url() . 'admin.php?page=wp-time-capsule&cloud_auth_action=s3&as3_access_key=' . $as3_access_key . '&as3_secure_key=' . $as3_secure_key . '&as3_bucket_region=' . $as3_bucket_region . '&as3_bucket_name=' . $as3_bucket_name . '';
	WPTC_Factory::get('S3Facade');
	echo json_encode($result);
	die();
}

function change_wptc_default_repo() {
	$new_default_repo = $_POST['new_default_repo'];
	if (empty($new_default_repo)) {
		echo json_encode(array('error' => 'Couldnt assign new repo.'));
		die();
	}

	$config = WPTC_Factory::get('config');
	$config->set_option('default_repo', $new_default_repo);

	echo json_encode(array('success' => $new_default_repo));
	die();
}

//Function for wptc cron service signup
function signup_wptc_server_wptc() {
	$config = WPTC_Factory::get('config');

	$email = trim($config->get_option('main_account_email', true));
	$emailhash = md5($email);
	$email_encoded = base64_encode($email);

	$pwd = trim($config->get_option('main_account_pwd', true));
	$pwd_encoded = base64_encode($pwd);

	if (empty($email) || empty($pwd)) {
		return false;
	}

	dark_debug($email, "--------email--------");

	$name = trim($config->get_option('main_account_name'));
	// $cron_url = site_url('wp-cron.php'); //wp cron commented because of new cron
	$cron_url = get_wptc_cron_url();

	$app_id = 0;
	if ($config->get_option('appID')) {
		$app_id = $config->get_option('appID');
	}

	//$post_string = "name=" . $name . "&emailhash=" . $emailhash . "&cron_url=" . $cron_url . "&email=" . $email_encoded . "&pwd=" . $pwd_encoded . "&site_url=" . home_url();

	$post_arr = array(
		'email' => $email_encoded,
		'pwd' => $pwd_encoded,
		'cron_url' => $cron_url,
		'site_url' => home_url(),
		'name' => $name,
		'emailhash' => $emailhash,
		'app_id' => $app_id,
	);

	$result = do_cron_call_wptc('signup', $post_arr);

	$resarr = json_decode($result);

	dark_debug($resarr, "--------resarr-node reply--------");

	if (!empty($resarr) && $resarr->status == 'success') {
		$config->set_option('wptc_server_connected', true);
		$config->set_option('signup', 'done');
		$config->set_option('appID', $resarr->appID);

		init_auto_backup_settings_wptc($config);
		$set = push_settings_wptc_server($resarr->appID, 'signup');
		if (WPTC_ENV !== 'production') {
			// echo $set;
		}

		$to_url = network_admin_url() . 'admin.php?page=wp-time-capsule';
		return true;
	} else {
		$config->set_option('last_service_error', $result);
		$config->set_option('appID', false);

		if (WPTC_ENV !== 'production') {
			echo "Creating Cron service failed";
		}

		return false;
	}
}

//Push the wptc (Auto/Scheduled) backup settings to wptc-server
function push_settings_wptc_server($app_id = "", $type = "") {
	dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);

	$config = WPTC_Factory::get('config');
	if ($config->get_option('wptc_service_request') == 'yes' || $type == 'signup') {
		if ($app_id == "") {
			$app_id = $config->get_option('appID');
		}

		$email = trim($config->get_option('main_account_email', true));
		$emailhash = md5($email);
		$email_encoded = base64_encode($email);

		$pwd = trim($config->get_option('main_account_pwd', true));
		$pwd_encoded = base64_encode($pwd);

		$scheduled_time_string = $config->get_option('schedule_time_str');
		if (!$scheduled_time_string) {
			$config->set_option('schedule_time_str', WPTC_DEFAULT_SCHEDULE_TIME_STR);
		}

		$time_zone = $config->get_option('wptc_timezone');
		$current_cron_type = $config->get_option('backup_type_setting');
		$autobackup = ($current_cron_type == 'AUTOBACKUP') ? 1 : 0;
		$cron_url = get_wptc_cron_url();

		$post_arr = array(
			'app_id' => $app_id,
			'email' => $email_encoded,
			'schedule' => $scheduled_time_string,
			'frequency' => WPTC_DEFAULT_CRON_FREQUENCY,
			'cronType' => $current_cron_type,
			'timeZone' => $time_zone,
			'emailhash' => $emailhash, //below 5 settings are used only for old cron
			'cron_url' => $cron_url, // wptc own cron
			'autobackup_settings' => $autobackup,
			'schedulebackup' => 0,
			'scheduled_unixtime' => 0,
			'scheduled_interval' => 0,
		);

		// dark_debug($post_arr, "--------post_string--------");

		$push_result = do_cron_call_wptc('push-settings', $post_arr);

		$is_error = process_cron_error_wptc($push_result, $no_reset = 1);
		if ($is_error) {
			return "push_failed";
		}

		$push_arr = json_decode($push_result);
		if ($push_arr->status == 'success') {
			return "success";
		} else {
			return "push_failed";
		}
	}
}

function wptc_own_cron_status() {
	dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);
	$config = WPTC_Factory::get('config');
	$config->set_option('wptc_own_cron_status_notified', '0');
	if ($config->get_option('wptc_service_request') == 'yes') {
		$app_id = $config->get_option('appID');
		$email = trim($config->get_option('main_account_email', true));
		$emailhash = md5($email);
		$email_encoded = base64_encode($email);

		$pwd = trim($config->get_option('main_account_pwd', true));
		$pwd_encoded = base64_encode($pwd);

		$post_arr = array(
			'app_id' => $app_id,
			'email' => $email_encoded,
		);

		$push_result = do_cron_call_wptc('status', $post_arr, 'GET');

		dark_debug($push_result,'--------------test connection result-------------');
		$is_error = '';
		if ($is_error) {
			dark_debug(array(), '-----------Push failed-------------');
			return "push_failed";
		}

		$push_arr = json_decode($push_result);
		dark_debug($push_arr,'--------------test connection decoded-------------');
		if (!empty($push_arr) && !empty($push_arr->msg) && $push_arr->msg == 'success') {
			$test_connection_status = array('status' => 'success');
			$config->set_option('wptc_own_cron_status', serialize($test_connection_status));
			return "success";
		} else {
			if (empty($push_arr->res_desc->statusCode)) {
				$status_code = '7';
			} else {
				$status_code = $push_arr->res_desc->statusCode;
			}
			if (empty($push_arr->res_desc->response->body)) {
				$body = 'Connection failed';
			} else{
				$body = $push_arr->res_desc->response->body;
			}
			if (empty($push_arr->res_desc->ips)) {
				$ips = '';
			} else {
				$ips = $push_arr->res_desc->ips;
			}

			$test_connection_status = array('status' => 'error',
											'statusCode' => $status_code,
											'body'=> $body,
											'ips' => $ips,
											'cron_url' => get_wptc_cron_url());
			dark_debug($test_connection_status,'--------------$test_connection_status-------------');
			$config->set_option('wptc_own_cron_status', serialize($test_connection_status));
			return "push_failed";
		}
	}
}

//notify to the wptc server -currently backup process is running (For fast and successful backup)
function set_backup_in_progress_server($flag, $cron_type = null) {

	dark_debug(get_backtrace_string_wptc(),'---------set_backup_in_progress_server------------------');
	$config = WPTC_Factory::get('config');
	$app_id = $config->get_option('appID');
	if ($config->get_option('wptc_server_connected') && $config->get_option('wptc_service_request') == 'yes' && !empty($app_id)) {
		$email = trim($config->get_option('main_account_email', true));
		$emailhash = md5($email);
		$email_encoded = base64_encode($email);

		$pwd = trim($config->get_option('main_account_pwd', true));
		$pwd_encoded = base64_encode($pwd);

		if (empty($cron_type)) {
			$cron_type = ($flag) ? 'BACKUP' : DEFAULT_CRON_TYPE_WPTC;
		}

		if ($cron_type == 'BACKUP') {
			$config->set_option('recent_backup_ping', time());
		}

		$post_arr = array(
			'app_id' => $app_id,
			'email' => $email_encoded,
			'cronType' => $cron_type,
		);

		dark_debug($post_arr, "--------post_string-set_backup_in_progress_server-------");

		$push_result = do_cron_call_wptc('process-backup', $post_arr);

		dark_debug($push_result, "--------pushresultset_backup_in_progress_server--------");

		process_cron_error_wptc($push_result);
		process_cron_backup_response_wptc($push_result);

	} else {
		$config->set_option('is_user_logged_in', false);
		$config->set_option('wptc_server_connected', false);
	}
}

function stop_wptc_server() {
	$config = WPTC_Factory::get('config');
	if ($config->get_option('wptc_server_connected')) {
		$app_id = $config->get_option('appID');

		$email = trim($config->get_option('main_account_email', true));
		$email_encoded = base64_encode($email);

		$pwd = trim($config->get_option('main_account_pwd', true));
		$pwd_encoded = base64_encode($pwd);

		$post_arr = array(
			'app_id' => $app_id,
			'email' => $email_encoded,
		);
		$push_result = do_cron_call_wptc('stop-service', $post_arr);
	}
}

function remove_wptc_server() {
	$config = WPTC_Factory::get('config');
	if ($config->get_option('wptc_server_connected')) {

		$email = trim($config->get_option('main_account_email', true));
		$email_encoded = base64_encode($email);

		$pwd = trim($config->get_option('main_account_pwd', true));
		$pwd_encoded = base64_encode($pwd);

		$post_arr = array(
			'email' => $email_encoded,
			'site_url' => home_url(),
		);

		$push_result = do_cron_call_wptc('remove-site', $post_arr);
	}
}

function do_cron_call_wptc($route_path, $post_arr, $type = 'POST') {
	$post_arr['version'] = WPTC_VERSION;
	$post_arr['source'] = 'WPTC';

	$wptc_token = WPTC_Factory::get('config')->get_option('wptc_token');
	if (WPTC_DARK_TEST) {
		server_call_log_wptc($post_arr, '----REQUEST-----', WPTC_CRSERVER_URL . "/" . $route_path);
	}

	$chb = curl_init();

	curl_setopt($chb, CURLOPT_URL, WPTC_CRSERVER_URL . "/" . $route_path);
	curl_setopt($chb, CURLOPT_CUSTOMREQUEST, $type);
	// curl_setopt($chb, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($chb, CURLOPT_POSTFIELDS, htmlspecialchars_decode(http_build_query($post_arr)));
	curl_setopt($chb, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($chb, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($chb, CURLOPT_SSL_VERIFYHOST, FALSE);

	if(!empty($wptc_token)){
		curl_setopt($chb, CURLOPT_HTTPHEADER, array(
			"Authorization: $wptc_token",
		));
	}

	if (!defined('WPTC_CURL_TIMEOUT')) {
		define('WPTC_CURL_TIMEOUT', 20);
	}
	curl_setopt($chb, CURLOPT_TIMEOUT, WPTC_CURL_TIMEOUT);

	$pushresult = curl_exec($chb);
	if (WPTC_DARK_TEST) {
		server_call_log_wptc($pushresult, '-----RESPONSE-----');
	}
	return $pushresult;
}

function process_cron_backup_response_wptc($push_result_raw = null){
	dark_debug($push_result_raw,'--------------$push_result_raw-------------');
	$config = WPTC_Factory::get('config');
	$cron_error = true;
	$push_result = json_decode($push_result_raw, true);
	dark_debug($push_result,'--------------$push_result-------------');
	if (isset($push_result['status']) && $push_result['status'] == 'success') {
		$cron_error = false;
	}
	dark_debug($cron_error,'--------------$cron_error-------------');
	if ($cron_error) {
		$time = user_formatted_time_wptc(microtime(true));
		dark_debug($time,'--------------$time-------------');
		reset_restore_related_settings_wptc();
		reset_backup_related_settings_wptc();
		log_failed_startup_backups();
		backup_proper_exit_wptc('Backup / Staging failed to notify, Try again after sometimes.');
	}
}

function log_failed_startup_backups(){
	$time = user_formatted_time_wptc(microtime(true));
	$config = WPTC_Factory::get('config');
	$logs = $config->get_option('start_backups_failed_server');
	if (empty($logs)) {
		$history[] = $time;
	} else {
		$history = unserialize($logs);
		$history[] = $time;
	}
	dark_debug($history,'--------------$history-------------');
	$serialized_history_log = serialize($history);
	$config->set_option('start_backups_failed_server', $serialized_history_log);
}

function process_cron_error_wptc($push_result = null, $no_reset = null) {
	// dark_debug($no_reset, '---------$no_reset------------');
	$config = WPTC_Factory::get('config');
	$cron_error = false;
	if (!$push_result) {
		$cron_error = true;
	} else {
		$full_push_result = json_decode($push_result, true);
		dark_debug($full_push_result, "--------full_push_result--------");
		if (isset($full_push_result) && (!empty($full_push_result['error']) || $full_push_result['status'] == 'error')) {
			$cron_error = true;
		}
	}

	if ($cron_error) {
		dark_debug($cron_error, "--------cron_error--------");
		$config->set_option('main_account_login_last_error', 'Cron Service is removed for this URL.');

		if (WPTC_ENV == 'local') {
			// $config->set_option('is_user_logged_in', false);
			// $config->set_option('wptc_server_connected', false);
			// $config->set_option('signup', false);
			//$config->set_option('appID', false);
			//$config->set_option('main_account_email', 0);
			//$config->set_option('main_account_pwd', 0);
		}
		if (empty($no_reset)) {
			reset_restore_related_settings_wptc();
			reset_backup_related_settings_wptc();
			log_failed_startup_backups();
			backup_proper_exit_wptc("Cron server is failed, Try after sometime.");
		}
	}
	return $cron_error;
}

function wptc_admin_bar_icons(WP_Admin_Bar $bar) {
	return false; //disabled from 1.7.3
	$parse_url = parse_url(network_admin_url());
	if (!is_admin() || !current_user_can('activate_plugins')) {
		return false;
	}
	$bar->add_node(array(
		'id' => 'wptc-dash-icons',
		'title' => '<span class="wptc-dash-status dashicons-before dashicons-image-rotate rotate"></span><span class="wptc-dash-text">Checking backup status...</span>',
		'href' => network_admin_url() . 'admin.php?page=wp-time-capsule-monitor',
		'meta' => array(
			'target' => '',
			// 'class' => 'wptc-dash-main wptc_logo_status_bar', //status bar wptc logo remove for now
			'class' => 'wptc-dash-main',
			// 'title' => __('Backup Completed', 'some-textdomain'),
			'html' => '',
		),
	));
}

function check_timeout_cut_and_exit_wptc($start_time, $current_process_file_id = null) {
	if (empty($start_time)) {
		return true;
	}
	if (is_wptc_timeout_cut($start_time)) {
		backup_proper_exit_wptc('', $current_process_file_id);
	}
}

function backup_proper_exit_wptc($msg = '', $current_process_file_id = null) {
	$config = WPTC_Factory::get('config');
	if ($config->get_option('in_progress')) {
		global $wpdb;
		if (!empty($current_process_file_id)) {
			WPTC_Factory::get('config')->set_option('current_process_file_id', $current_process_file_id);
			dark_debug($current_process_file_id, '---------$current_process_file_id check_timeout_cut_and_exit_wptc selva 2------------');
		}
		$backup_id = getTcCookie('backupID');
		manual_debug_wptc('', 'beforeHalting');
		// $countS = $wpdb->get_var("SELECT count(*) FROM " . $wpdb->base_prefix . "wptc_current_process WHERE status='P' OR status='E' OR status='S'");
		WPTC_Factory::get('logger')->log(__("Preparing for next call from server.", 'wptc'), 'backup_progress', $backup_id);

		$config->set_option('is_running', false);

		// dark_debug(, "--------beforeHalting processed files--------");
		// revitalize_monitor_hook_wptc();
	}
	WPTC_Factory::get('Debug_Log')->wptc_log_now('This call over and waiting for next one', 'Inc-Exc');
	if (empty($msg)) {
		send_current_backup_response_to_server();
	}
	if(is_wptc_server_req()){
		exit($msg);
	} else {
		$config->set_option('show_user_php_error', $msg);
	}
}


function send_current_backup_response_to_server(){
	$return_array = array();
	$processed_files = WPTC_Factory::get('processed-files');
	$processed_files->get_current_backup_progress($return_array);
	send_response_wptc('progress', DEFAULT_CRON_TYPE_WPTC, $return_array);
}
/*
*30 seconds cron commented do not delete
*
function revitalize_monitor_hook_wptc() {
	if (!wp_next_scheduled('monitor_tcdropbox_backup_hook_wptc')) {
		dark_debug(date('g:i:s a'), "--------revitalize_monitor_hook_wptc--------");

		// wp_clear_scheduled_hook('monitor_tcdropbox_backup_hook_wptc');
		wp_schedule_single_event(365, 'monitor_tcdropbox_backup_hook_wptc', array('time' => time()));

		delete_transient('doing_cron');
	}
}
*/

function plugin_update_notice_wptc() {
	$config = WPTC_Factory::get('config');
	$config->set_option('user_came_from_existing_ver', 0);
}

function update_sycn_db_view_wptc() {
	$config = WPTC_Factory::get('config');
	$config->set_option('show_sycn_db_view_wptc', false);
}

function show_processing_files_view_wptc() {
	$config = WPTC_Factory::get('config');
	$config->set_option('show_processing_files_view_wptc', false);
}

function update_test_connection_err_shown() {
	$config = WPTC_Factory::get('config');
	dark_debug(array(), '-----------cupdate_test_connection_err_shown-------------');
	$config->set_option('wptc_own_cron_status_notified', '1');
}

function test_connection_wptc_cron() {
	wptc_cron_status();
}

function wptc_cron_status($return = false){
	$config = WPTC_Factory::get('config');
	wptc_own_cron_status();
	$status = array();
	$cron_status = $config->get_option('wptc_own_cron_status');
	if (!empty($cron_status)) {
		$cron_status = unserialize($cron_status);
		dark_debug($cron_status,'--------------$cron_status-------------');
		if ($cron_status['status'] == 'success') {
			if ($return === 2) {
				return true;
			}
			$status['status'] = 'success';
		} else {
			if ($return === 2) {
				return false;
			}
			$status['status'] = 'failed';
			$status['status_code'] = $cron_status['statusCode'];
			$status['err_msg'] = $cron_status['body'];
			$status['cron_url'] = $cron_status['cron_url'];
			$status['ips'] = $cron_status['ips'];
		}
		if ($return == 1) {
			return $status;
		}
		echo json_encode($status);
		exit;
	}
}

function save_initial_setup_data_wptc() {
	if (!isset($_POST) && !isset($_POST['data'])) {
		return false;
	}
	$data = $_POST['data'];
	$config = WPTC_Factory::get('config');
	$schedule_time = (isset($data['schedule_time'])) ? $data['schedule_time'] : false;
	$timezone = (isset($data['timezone'])) ? $data['timezone'] : false;
	$exclude_extensions = (isset($data['exclude_extensions'])) ? $data['exclude_extensions'] : false;
	$backup_type_setting = (isset($data['backup_type'])) ? $data['backup_type'] : false;

	if (!empty($schedule_time)) {
		$config->set_option('schedule_time_str', $schedule_time);
	}
	if (!empty($backup_type_setting)) {
		$config->set_option('backup_type_setting', $backup_type_setting);
	}
	if (!empty($timezone)) {
		$config->set_option('wptc_timezone', $timezone);
	}
	if (!empty($exclude_extensions)) {
		$config->set_option('user_excluded_extenstions', $exclude_extensions);
	}
	wptc_modify_schedule_backup();
	dropbox_auth_check_wptc();
}

function save_general_settings_wptc(){
	$config = WPTC_Factory::get('config');
	$data = $_POST['data'];
	$config->set_option('anonymous_datasent', $data['anonymouse']);
	die("over");
}

function save_backup_settings_wptc(){
	$config = WPTC_Factory::get('config');
	$data = $_POST['data'];
	$config->set_option('user_excluded_extenstions', $data['user_excluded_extenstions']);

	if(!empty($data['scheduled_time']) && !empty($data['timezone']) ){
		$config->set_option('wptc_timezone', $data['timezone']);
		$config->set_option('schedule_time_str', $data['scheduled_time']);
		wptc_modify_schedule_backup();
	}

	die("over");
}

function resume_backup_wptc(){
	$status = wptc_cron_status(2);
	if ($status) {
		$push_result =resume_backup_call_to_server();
		dark_debug($push_result, '---------$push_result decoded------------');
		if(isset($push_result['msg']) && $push_result['msg'] === 'success'){
			dark_debug(array(), '---------come in------------');
			$options = WPTC_Factory::get('config');
			$options->set_option('recent_backup_ping', time());
			$response_arr['status'] = 'success';
		} else {
			wptc_cron_status();
		}
	} else {
		dark_debug(array(), '---------comes in else------------');
		wptc_cron_status();
	}
	die(json_encode($response_arr));
}


function resume_backup_call_to_server() {
	$config = WPTC_Factory::get('config');
	if ($config->get_option('wptc_server_connected')) {
		$app_id = $config->get_option('appID');

		$email = trim($config->get_option('main_account_email', true));
		$email_encoded = base64_encode($email);

		$pwd = trim($config->get_option('main_account_pwd', true));
		$pwd_encoded = base64_encode($pwd);

		$post_arr = array(
			'app_id' => $app_id,
			'email' => $email_encoded,
			'pwd' => $pwd_encoded,
		);

		$push_result = do_cron_call_wptc('users/resume', $post_arr);
		dark_debug($push_result, '---------$push_result------------');
		return json_decode($push_result, true);
	}
}

function is_backup_paused_wptc(){
	$current_status = reset_backup_if_long_time_no_ping(0, 1);
	dark_debug($current_status, '---------$current_status------------');
	return $current_status;
}

function proceed_to_pay_wptc()
{
	$data = array();
	if (!empty($_POST['data'])) {
		$data = $_POST['data'];
	} else {
		echo json_encode(array('error' => 'Post Data is missing.'));
		die();
	}

	$data['sub_action'] = "process_subscription_from_plugin";
	$data['current_site_url'] = WPTC_Factory::get('config')->get_option('site_url_wptc');
	$data['site_url'] = WPTC_Factory::get('config')->get_option('site_url_wptc');
	$data['email'] = WPTC_Factory::get('config')->get_option('main_account_email');
	$data['pwd'] = WPTC_Factory::get('config')->get_option('main_account_pwd');
	$data['password'] = WPTC_Factory::get('config')->get_option('main_account_pwd');
	$data['version'] = WPTC_VERSION;

	dark_debug($data, "--------post data----proceed_to_pay_wptc----");

	$rawResponseData = WPTC_Factory::get('config')->doCall(WPTC_USER_SERVICE_URL, $data, 20, array('normalPost' => 1));

	echo $rawResponseData;
}

function set_default_cron_constant(){
	$already_set = apply_filters('set_default_backup_constant_wptc_h', '');
	if ($already_set) {
		return false;
	}
	$config = WPTC_Factory::get('config');
	$current_backup_type = $config->get_option('backup_type_setting');
	if (defined('DEFAULT_CRON_TYPE_WPTC')) {
		return false;
	}
	if ($current_backup_type == 'SCHEDULE') {
		define('DEFAULT_CRON_TYPE_WPTC', 'SCHEDULE'); //subject to change
	} else if ($current_backup_type == 'WEEKLYBACKUP') {
		define('DEFAULT_CRON_TYPE_WPTC', 'WEEKLYBACKUP'); //subject to change
	} else {
		$config->set_option('backup_type_setting', 'SCHEDULE');
		define('DEFAULT_CRON_TYPE_WPTC', 'SCHEDULE'); //if nothing set then set schedule is default cron type
	}
}

function save_manual_backup_name_wptc(){
	$processed_files = WPTC_Factory::get('processed-files');
	$processed_files->save_manual_backup_name_wptc($_POST['name']);
}

function is_backup_running_wptc(){
	if(is_any_ongoing_wptc_backup_process()){
		die(json_encode(array('status' => 'running' )));
	}
	die(json_encode(array('status' => 'not_running' )));
}

function check_cloud_in_auth_state(){
	$config = WPTC_Factory::get('config');
	$state = $config->get_option('oauth_state');
	$default_repo = $config->get_option('default_repo');
	if ($state === 'request' && $default_repo === 'dropbox') {
		send_response_wptc('CLOUD_IN_REQUEST_STATE');
	}
}

function clear_show_users_backend_errors_wptc(){
	$config = WPTC_Factory::get('config');
	$result = $config->set_option('show_user_php_error', false);
	if ($result) {
		die(json_encode(array('status' => 'success' )));
	}
	die(json_encode(array('status' => 'failed' )));
}

function windows_machine_reset_backups_wptc(){
	if(!is_windows_machine_wptc()){
		return false;
	}
	dark_debug(array(), '--------Yes windows machine--------');
	$backup = new WPTC_BackupController();
	if (is_any_ongoing_wptc_backup_process()) {
		$backup->stop();
	}
	reset_backup_related_settings_wptc();
	$backup->clear_prev_repo_backup_files_record($reset_inc_exc = true);
	$config = WPTC_Factory::get('config');
	$prev_date = $config->get_wptc_user_today_date_time('Y-m-d', (time() - 259200));
	dark_debug($prev_date, '--------$prev_date--------');
	$config->set_option('wptc_today_main_cycle', $prev_date);
}


function set_admin_notices_wptc($msg, $status, $strict_wptc_page){
	$config = WPTC_Factory::get('config');
	$notice = array(
		'msg' => $msg,
		'status' => $status,
		'strict_wptc_page' => $strict_wptc_page,
	);
	$config->set_option('admin_notices', serialize($notice));
}

function get_admin_notices_wptc(){
	if(apply_filters('is_whitelabling_enabled_wptc', '')){
		die_with_json_encode(array());
	}

	$config = WPTC_Factory::get('config');
	$notice = $config->get_option('admin_notices');
	if (empty($notice)) {
		die_with_json_encode(array(), 1);
	} else {
		$notice = unserialize($notice);
	}

	if($notice['strict_wptc_page']){
		if($_POST['is_wptc_page']){
			$config->delete_option('admin_notices');
		} else {
			$notice = array();
		}
	} else {
		$config->delete_option('admin_notices');
	}

	die_with_json_encode($notice, 1);
}