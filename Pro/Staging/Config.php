<?php

Class Wptc_staging_Config extends Wptc_Base_Config {
	public function __construct() {
		$this->init();
	}

	private function init() {
		$this->set_used_options();
	}

	protected function set_used_options() {
		$this->used_options = array(
			'staging_progress_status' => 'flushable',
			'meta_chunk_download' => 'flushable',
			'staging_last_error' => 'flushable',
			'is_staging_backup' => 'flushable',
			'is_staging_running' => 'flushable',
			'staging_backup_id' => 'flushable',
			'meta_chunk_upload_running' => 'flushable',
			'meta_chunk_upload_offset' => 'flushable',
			'staging_call_waiting_for_response' => 'flushable',
			'is_staging_completed' => 'flushable',
			'staging_id' => 'flushable',
			'privileges_wptc' => 'retainable',
			'dropbox_location' => '',
			'cpanel_crentials' => '',
			'staging_ftp_details' => '',
			'staging_db_details' => '',
			'appID' => '',
			'wptc_server_connected' => '',
			'wptc_service_request' => '',
			'main_account_email' => '',
			'main_account_pwd' => '',
			'is_user_logged_in' => '',
			'staging_details' => '',
			'default_repo' => '',
			'staging_completed' => '',
			'external_staging_requested' => 'retainable',
			'stage_n_update_details' => 'retainable',
			'same_server_staging_status' => 'retainable',
			'same_server_staging_path' => 'flushable',
			'same_server_staging_running' => 'flushable',
			'same_server_copy_files_count' => 'flushable',
			'same_server_staging_db_prefix' => 'flushable',
			'same_server_staging_full_db_prefix' => 'flushable',
			'same_server_clone_db_total_tables' => 'flushable',
			'same_server_clone_db_completed_tables' => 'flushable',
			'same_server_copy_staging' => 'flushable',
			'same_server_replace_old_url' => 'flushable',
			'same_server_replace_old_url_data' => 'flushable',
			'same_server_staging_details' => 'retainable',
			'same_server_replace_url_multicall_status' => 'flushable',
			'staging_type' => 'retainable',
			'internal_staging_db_rows_copy_limit' => 'retainable',
			'internal_staging_file_copy_limit' => 'retainable',
			'internal_staging_deep_link_limit' => 'retainable',
			'run_staging_updates' => 'retainable',
			'staging_setting' => 'retainable',
		);
	}

}