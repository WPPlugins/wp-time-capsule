<?php
header('Access-Control-Allow-Origin: *');
/* echo json_encode('over');
exit; */

//ajax file to handle restore progress
@require_once "wp-tc-config.php";
@require_once "wp-db-custom.php";
@require_once 'Classes/Config.php';
@require_once 'Classes/Factory.php';
@require_once 'utils/g-wrapper-utils.php';
@require_once 'common-functions.php';
@require_once "wp-modified-functions.php";
define('WPTC_BRIDGE', true); //used in wptc-constants.php
require_once 'wptc-constants.php';

global $wpdb;
$wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);

//setting the prefix from post value;
$wpdb->prefix = $wpdb->base_prefix = DB_PREFIX_WPTC;


//initialize a db object and config object
$config = WPTC_Factory::get('config');

$echo_array = array();
if (!$config->get_option('is_bridge_process')) {
	if ($config->get_option('in_progress_restore')) {
		//query to get all the files which are to be downloaded
		$total_files_array = $wpdb->get_results("SELECT * FROM {$wpdb->base_prefix}wptc_processed_restored_files ");
		$total_files_count = count($total_files_array);
		//query to get all the files which are downloaded
		$downloaded_files_array = $wpdb->get_results("SELECT * FROM {$wpdb->base_prefix}wptc_processed_restored_files WHERE download_status = 'done'");
		$downloaded_files_count = count($downloaded_files_array);
		if (!empty($total_files_count)) {
			$downloaded_files_percent = (($downloaded_files_count / $total_files_count) * 100);
		} else {
			$downloaded_files_percent = 0;
		}

		$echo_array['total_files_count'] = $total_files_count;
		$echo_array['downloaded_files_count'] = $downloaded_files_count;
		$echo_array['downloaded_files_percent'] = $downloaded_files_percent;
	}
} else {
	//query to get all the files which are to be copied
	$total_files_array = $wpdb->get_results("SELECT * FROM {$wpdb->base_prefix}wptc_processed_restored_files ");
	$total_files_count = count($total_files_array);
	$echo_array['total_files_count'] = 0;
	$echo_array['copied_files_count'] = 0;
	$echo_array['copied_files_percent'] = 0;

	if (empty($total_files_count)) {
		return $echo_array;
	}

	//query to get all the files which are copied
	$copied_files_array = $wpdb->get_results("SELECT * FROM {$wpdb->base_prefix}wptc_processed_restored_files WHERE copy_status = true");
	$copied_files_count = count($copied_files_array);

	$copied_files_percent = (($copied_files_count / $total_files_count) * 100);

	$echo_array['total_files_count'] = $total_files_count;
	$echo_array['copied_files_count'] = $copied_files_count;
	$echo_array['copied_files_percent'] = $copied_files_percent;
}
echo json_encode($echo_array);