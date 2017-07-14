<?php
/**
* A class with functions the perform a backup of WordPress
*
* @copyright Copyright (C) 2011-2014 Awesoft Pty. Ltd. All rights reserved.
* @author Michael De Wildt (http://www.mikeyd.com.au/)
* @license This program is free software; you can redistribute it and/or modify
*          it under the terms of the GNU General Public License as published by
*          the Free Software Foundation; either version 2 of the License, or
*          (at your option) any later version.
*
*          This program is distributed in the hope that it will be useful,
*          but WITHOUT ANY WARRANTY; without even the implied warranty of
*          MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*          GNU General Public License for more details.
*
*          You should have received a copy of the GNU General Public License
*          along with this program; if not, write to the Free Software
*          Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110, USA.
*/

class WPTC_GdriveFacade {
	const PRODUCTION_CLIENT_ID = '321045971658-5n9tr2ofsc5sasdu9qeivqljekrdcork.apps.googleusercontent.com';
	const PRODUCTION_SECRET_KEY = 'VYEiSXvm3pdCv9bM2aBXx1cY';
	const STAGING_CLIENT_ID = '944732689939-tjfssokh6sh6379ighhgeqkmpu0mq05s.apps.googleusercontent.com';
	const STAGING_SECRET_KEY = 'bDeeBEUAWP1zJeYkpEgoOsuy';
	const DEVELOPMENT_CLIENT_ID = '258123763071-n824sii2u4t6qd8nuq2j0g48vpg3ousb.apps.googleusercontent.com';
	const DEVELOPMENT_SECRET_KEY = 'lQ6jOrSL9UBiqjgtQSOod86H';
	const RETRY_COUNT = 3;

	private static $instance = null;

	private $g_drive_wrapper,
	$client,
	$config,
	$access_token,
	$oauth_state,
	$directory_cache = array()
	;
	private $refresh_token = "";

	public $userInfo;
	private $g_drive_email = '';

	public function __construct() {
		$this->init();
	}

	public function init() {
		try {
			$this->config = WPTC_Factory::get('config');
			$this->client = new WPTC_Google_Client();
			if (WPTC_ENV === 'production') {
				$this->client->setClientId(self::PRODUCTION_CLIENT_ID); //need to revert
				$this->client->setClientSecret(self::PRODUCTION_SECRET_KEY); // both
			} else if (WPTC_ENV === 'staging' || WPTC_ENV === 'pre-production' ) {
				$this->client->setClientId(self::STAGING_CLIENT_ID);
				$this->client->setClientSecret(self::STAGING_SECRET_KEY);
			} else {
				$this->client->setClientId(self::DEVELOPMENT_CLIENT_ID);
				$this->client->setClientSecret(self::DEVELOPMENT_SECRET_KEY);
			}
			$this->client->setRedirectUri(get_options_page_url());
			$this->client->setAccessType("offline");
			$this->client->setScopes(array(
				'https://www.googleapis.com/auth/drive',
				'https://www.googleapis.com/auth/userinfo.email',
				'https://www.googleapis.com/auth/userinfo.profile'));

			$this->oauth_state = $this->config->get_option('oauth_state_g_drive');

			if ($this->oauth_state == 'access') {
				$this->refresh_old_token();
			} else if ($this->oauth_state == 'revoke') {
				$refresh_token = $this->get_refresh_old_token();
				$this->client->revokeToken($refresh_token);
			}

			$this->g_drive_wrapper = new Google_Wptc_Wrapper($this->client);
			$this->g_drive_wrapper->setTracker(new WPTC_UploadTracker());
		} catch (Exception $e) {
			return $this->process_exception($e);
		}
	}

	private function is_auth_error($http_code) {
		if ($http_code == 401 || $http_code == 400) {
			return true;
		}
	}

	private function set_fresh_token() {
		$get_hash = $this->config->get_option("gdrive_fresh_hash");
		$this->client->authenticate($get_hash);
		$this->access_token = $this->client->getAccessToken();
		if (!empty($this->access_token)) {
			$this->client->setAccessToken($this->access_token);
		}
		$token_arr = json_decode($this->access_token);
		$req_token_dets['created'] = $token_arr->created;
		if (empty($token_arr->refresh_token)) {
			$req_token_dets['refresh_token'] = $this->refresh_token;
		} else {
			$req_token_dets['refresh_token'] = $token_arr->refresh_token;
		}
		dark_debug($token_arr, "--------token_arr_myyyy--------");
		$this->config->set_option('gdrive_old_token', serialize($req_token_dets));
	}

	private function refresh_old_token() {
		$token = $this->get_refresh_old_token();
		if ($token != false) {
			$this->client->refreshToken($token);
		} else{
			throw new Exception("Token invalid", 401);
		}
	}

	private function get_refresh_old_token() {
		$token_obj = $this->config->get_option('gdrive_old_token');
		if (!empty($token_obj)) {
			$token_arr = unserialize($token_obj);
			return $token_arr['refresh_token'];
		} else {
			return false;
		}
	}

	public function get_authorize_url() {
		return $this->client->createAuthUrl();
	}

	public function is_authorized() {
		if (!$this->config->is_main_account_authorized()) {
			return false;
		}

		try {
			if (!$this->userInfo) {
				$userInfoService = new WPTC_Google_Service_Oauth2($this->client);
				$userInfo = $userInfoService->userinfo->get();
				$this->g_drive_email = $userInfo->email;
				$this->config->set_option('current_g_drive_email', $this->g_drive_email);
				dark_debug($this->g_drive_email, "--------g_drive_email--------");
			}
			$this->ping_server_if_storage_quota_low();

		} catch (Exception $e) {
			dark_debug(array(), "--------google e--------");
			// return $this->process_exception($e);
		}

		return true;
	}

	public function ping_server_if_storage_quota_low() {
		$remaining_quota = $this->g_drive_wrapper->quota_bytes_left();

		dark_debug($remaining_quota, "--------remaining_quota--------");

		if (!empty($remaining_quota) && $remaining_quota <= 51200) {
			$name = $this->name;
			$connectedEmail = $this->g_drive_email;
			$cloudAccount = $this->config->get_option('default_repo');

			$err_info = array(
				'name' => $name,
				'cloudAccount' => $cloudAccount,
				'connectedEmail' => $connectedEmail,
				'type' => 'limit_exceed',
			);

			error_alert_wptc_server($err_info);
		}

		return true;
	}

	public function unlink_account() {
		return $this;
	}

	public function reset_oauth_config() {
		$this->config->set_option('oauth_state_g_drive', null);
		$this->config->set_option('gdrive_fresh_hash', null);
		$this->config->set_option('gdrive_old_token', null);

		return $this;
	}

	public function get_directory_contents($path) {
		return array();
	}

	public function get_quota_div() {
		//$account_info = $this->get_account_info();

		$userInfoService = new WPTC_Google_Service_Oauth2($this->client);
		$userInfo = null;
		$userInfo = $userInfoService->userinfo->get();
		$g_drive_email = $userInfo->email;

		$return_var = '';
		$return_var = 'Google Drive - ' . $g_drive_email;
		return $return_var;
	}

	public function upload_file($path, $file) {
		$i = 0;
		$backup_id = getTcCookie('backupID');
		while ($i++ < self::RETRY_COUNT) {
			try {
				//dark_debug(array($path, $file), "--------upload_file gFacade--------");
				return $this->g_drive_wrapper->putFile($file, remove_secret($file), $path);
			} catch (Exception $e) {
				if ($i > self::RETRY_COUNT) {
					$base_name_file = basename($file);
					throw $e;
					return array('error' => "File upload error ($file).");
				} else{
					dark_debug($e->getMessage(), '-----------Retry uploading-------------');
					dark_debug($file,'--------------$file-------------');
					WPTC_Factory::get('logger')->log(__("Retry uploading " . $e->getMessage(), 'wptc'), 'backup_progress', $backup_id);
				}
			}
		}
	}

	public function chunk_upload_file($path, $file, $processed_file, $starting_backup_path_time = false, $meta_data_backup = null) {
		$offest = $upload_id = null;
		if ($meta_data_backup == 1) {
			$offest = $processed_file['offset'];
			$upload_id = $processed_file['upload_id'];
		} else if ($processed_file) {
			$offest = $processed_file->offset;
			$upload_id = $processed_file->uploadid;
		}

		return $this->g_drive_wrapper->chunkedUpload($file, remove_secret($file), $path, true, $offest, $upload_id, $starting_backup_path_time);
	}

	public function download_file($path, $file, $revision = '', $isChunkDownload = null, $g_file_id = null) {
		$i = 0;
		$restore_action_id = $this->config->get_option('restore_action_id');
		while ($i++ < self::RETRY_COUNT) {
			try {
				return $this->g_drive_wrapper->getFile($path, $file, $revision, null, $g_file_id);
			} catch (Exception $e) {
				if ($i > self::RETRY_COUNT) {
					$base_name_file = basename($file);
					return array('error' => $e->getMessage()." - File chunk download error ($file).");
				} else {
					WPTC_Factory::get('logger')->log(__("Retry downloading " . $e->getMessage(), 'wptc'), 'restore_process', $restore_action_id);
				}
			}
		}
		throw $e;
	}

	public function chunk_download_file($path, $file, $revision = '', $isChunkDownload = null, $g_file_id = null, $meta_file_download = null) {
		$i = 0;
		$restore_action_id = $this->config->get_option('restore_action_id');
		while ($i++ < self::RETRY_COUNT) {
			try {
				return $this->g_drive_wrapper->chunkedDownload($path, $file, $revision, $isChunkDownload, $g_file_id, $meta_file_download );
			} catch (Exception $e) {
				if ($i > self::RETRY_COUNT) {
					$base_name_file = basename($file);
					return array('error' => $e->getMessage()." - File chunk download error ($file).");
				} else {
					WPTC_Factory::get('logger')->log(__("Retry chunk downloading " . $e->getMessage(), 'wptc'), 'restore_process', $restore_action_id);
				}
			}
		}
	}

	private function process_exception($e){
		if (!$this->config->get_option('is_user_logged_in')){
			return false;
		}
		if(!$this->config->get_option('main_account_email')) {
			return false;
		}
		$err_msg = $e->getMessage();
		$http_code = $e->getCode();

		dark_debug($err_msg, "--------e err_msg  google--------");
		dark_debug($http_code, "--------e http_code google--------");
		$this->config->set_option('last_cloud_error', $err_msg);

		if ($this->is_auth_error($http_code)) {
			$this->unlink_account();
			$this->config->set_option('default_repo', false);
			$this->config->set_option('oauth_state_g_drive', false);
			$this->config->set_option('gdrive_old_token', false);
		}
		if(is_wptc_server_req()){
			backup_proper_exit_wptc($err_msg);
		} else {
			$this->config->set_option('show_user_php_error', '(HTTP Code :'.$http_code.')'.$err_msg);
			return 'TEMPORARY_CONNECTION_ISSUE';
		}
	}
}
