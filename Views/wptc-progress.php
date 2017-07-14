<?php
if (!current_user_can('activate_plugins')) {
	die(json_encode(array('Not authorized')));
}

$config = WPTC_Factory::get('config');
global $wpdb;

if (!$config->get_option('in_progress')) {
	spawn_cron();
}

$processed_files = WPTC_Factory::get('processed-files');
$return_array = array();
$return_array['stored_backups'] = $processed_files->get_stored_backups();
$return_array['backup_progress'] = array();
$return_array['starting_first_backup'] = $config->get_option('starting_first_backup');
$return_array['meta_data_backup_process'] = $config->get_option('meta_data_backup_process');
$return_array['backup_before_update_progress'] = $config->get_option('backup_before_update_progress');
$return_array['is_staging_running'] = apply_filters('is_any_staging_process_going_on', '');
$return_array['is_whitelabling_enabled'] = apply_filters('is_whitelabling_enabled_wptc', '');

$cron_status = $config->get_option('wptc_own_cron_status');
if (!empty($cron_status)) {
	$return_array['wptc_own_cron_status'] = unserialize($cron_status);
	$return_array['wptc_own_cron_status_notified'] = (int) $config->get_option('wptc_own_cron_status_notified');
}

$start_backups_failed_server = $config->get_option('start_backups_failed_server');
if (!empty($start_backups_failed_server)) {
	$return_array['start_backups_failed_server'] = unserialize($start_backups_failed_server);
	$config->set_option('start_backups_failed_server', false);
}

$processed_files->get_current_backup_progress($return_array);

$return_array['user_came_from_existing_ver'] = (int) $config->get_option('user_came_from_existing_ver');
$return_array['show_user_php_error'] = $config->get_option('show_user_php_error');
$return_array['bbu_setting_status'] = apply_filters('get_backup_before_update_setting_wptc', '');
$return_array['bbu_note_view'] = apply_filters('get_bbu_note_view', '');
$return_array['staging_status'] = apply_filters('staging_status_wptc', '');
$return_array['user_logged_in'] = $options_helper->get_is_user_logged_in();
$processed_files = WPTC_Factory::get('processed-files');
$last_backup_time = $config->get_option('last_backup_time');
if (!empty($last_backup_time)) {
	$user_time = $config->cnvt_UTC_to_usrTime($last_backup_time);
	$processed_files->modify_schedule_backup_time($user_time);
	$formatted_date = date("M d @ g:i a", $user_time);
	$return_array['last_backup_time'] = $formatted_date;
} else {
	$return_array['last_backup_time']  = 'No Backup Taken';
}

echo json_encode($return_array);
