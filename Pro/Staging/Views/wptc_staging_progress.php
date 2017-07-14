<?php

class Staging_Progress{
	protected $config;
	protected $staging;
	public function __construct(){
		$this->config = WPTC_Factory::get('config');
		$this->staging = new Wptc_Staging();
	}

	public function init(){

	}

	public function staging_details_wptc(){
		if ($this->config->get_option('staging_type') === 'internal') {
			$staging_details = $this->staging->get_internal_staging_details();
		} else {
			$staging_details = $this->staging->get_external_staging_details();
		}

		$return_array = array();
		$staging_last_error = $this->config->get_option('staging_last_error');
		if ($staging_last_error) {
			die_with_json_encode(array('error' => $staging_last_error));
		}

		if(empty($staging_details)){
			die_with_json_encode(array());
		}

		$staging_details['status'] = 'backup_on_progress';
		die_with_json_encode($staging_details, 1);
	}

	public function current_staging_status_wptc(){
		// dark_debug(array(), '----------current_staging_status_wptc-----------');
		if ($this->config->get_option('staging_type') === 'internal') {
			$this->internal_staging();
		}
		$this->external_staging();
	}

	private function internal_staging(){
		$return_array['is_staging_running'] = $this->config->get_option('same_server_staging_running');
		$return_array['staging_progress_status'] = $this->config->get_option('same_server_staging_status');
		$staging_completed = false;
		$percentage = 2;
		switch ($return_array['staging_progress_status']) {
			case 'test_bridge_over':
				$message = 'Analyzing tables for cloning.';
				$percentage = 10;
				break;
			case 'db_clone_progress':
				$total_tables = $this->config->get_option('same_server_clone_db_total_tables');
				$completed_tables = $this->config->get_option('same_server_clone_db_completed_tables');
				$message = 'Cloning tables ('.$completed_tables .'/'. $total_tables.')';
				$percentage = 40;
				break;
			case 'db_clone_over':
				$message = 'Analyzing files for cloning.';
				$percentage = 50;
				break;
			case 'copy_files_progress':
				$files = $this->config->get_option('same_server_copy_files_count');
				$message = $files. ' files are cloned';
				$percentage = 90;
				break;
			case 'copy_files_over':
				$message = 'Replacing links in staging site.';
				$percentage = 100;
				break;
			case 'staging_completed':
				$message = 'Site staged successfully';
				$staging_completed = true;
				$percentage = 100;
				break;
		}
		$return_array['message'] = $message;
		$return_array['percentage'] = $percentage;
		$return_array['staging_type'] = 'internal';
		$return_array['is_staging_completed'] = $staging_completed;
		$return_array['on_going_backup_process'] = is_any_ongoing_wptc_backup_process();
		if ($staging_completed) {
			$return_array['details'] = $this->staging->get_internal_staging_details();
		}
		die(json_encode($return_array));
	}

	private function external_staging(){
		$return_array = array();
		$return_array['staging_progress_status'] = $this->config->get_option('staging_progress_status');
		$return_array['is_staging_running'] = $this->config->get_option('is_staging_running');
		$return_array['external_staging_requested'] = $this->config->get_option('external_staging_requested');
		$staging_last_error = $this->config->get_option('staging_last_error', true);
		// dark_debug($staging_last_error, '---------$staging_last_error current_staging_status_wptc------------');
		$staging_completed = $this->config->get_option('staging_completed');
		$message = 'Staging processing...';
		$percentage = 100;
		$need_ajax_call = false;
		$bridge_ajax_url = false;
		switch ($return_array['staging_progress_status']) {
			case 'ready_to_start':
			$message = 'Uploading necessary files...';
			$percentage = 20;
			break;
			case 'bridge_downloaded_n_extracted':
			$message = 'Downloading database file...';
			$percentage = 40;
			break;

			case 'meta_download_completed':
			$message = 'Uploading database file...';
			$percentage = 60;
			break;

			case 'meta_upload_completed':
			case 'meta_data_import_running':
			$message = 'Extracting Database files...';
			$percentage = 80;
			break;

			case 'meta_data_import_completed':
			$message = 'Resetting flags...';
			$percentage = 100;
			break;

			case 'init_restore_completed':
			case 'continue_restore_running':
			$message = 'Downloading Wordpress files...';
			$percentage = 0;
			$need_ajax_call = true;
			break;

			case 'continue_restore_completed':
			case 'continue_copy_running':
			$message = 'Copying files to it\'s respective places...';
			$percentage = 0;
			$need_ajax_call = true;
			break;

			case 'continue_copy_completed':
			$message = 'Resetting staging related setting...';
			$percentage = 100;
			break;

			case 'staging_completed':
			$message = 'Staged your site successfully...!';
			$percentage = 100;
			break;

			default:
			$message = 'Analyzing files for staging';
			$percentage = 0;
			break;
		}
		if ($staging_last_error) {
			die_with_json_encode(
					array(
						'error' => $staging_last_error,
						'percentage' => empty($percentage) ? 1 : $percentage,
					)
				);
		}
		$return_array['message'] = $message;
		$return_array['percentage'] = $percentage;
		$return_array['need_ajax_call'] = $need_ajax_call;
		$return_array['staging_type'] = 'external';
		$return_array['is_staging_completed'] = $staging_completed;
		$return_array['on_going_backup_process'] = is_any_ongoing_wptc_backup_process();
		if ($need_ajax_call) {
			$return_array['bridge_ajax_url'] = WPTC_Pro_Factory::get('Wptc_Staging')->get_staging_details('ftp', 'destination_url').'wp-tcapsule-bridge/restore-progress-ajax.php';
		}
		// if ($staging_completed || $return_array['staging_progress_status'] == 'staging_completed') {
		$return_array['details'] = $this->staging->get_external_staging_details();
		// }
		die(json_encode($return_array));
	}
}