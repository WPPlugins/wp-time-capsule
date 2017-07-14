<?php

abstract class Wptc_Base_Curl_Wrapper {
	protected $domain_url;
	protected $post_type;
	protected $cron_posts_always_needed;

	public function __construct() {
		$this->init();
	}

	private function init() {

	}

	protected abstract function set_defaults();

	public function do_call($route_path, $post_arr) {

		$post_arr = array_merge($this->cron_posts_always_needed, $post_arr);
		$wptc_token = WPTC_Factory::get('config')->get_option('wptc_token');
		$post_arr['version'] = WPTC_VERSION;
		$post_arr['source'] = 'WPTC';
		if (WPTC_DARK_TEST) {
			server_call_log_wptc($post_arr, '----REQUEST-----', WPTC_CRSERVER_URL . "/" . $route_path);
		}
		$chb = curl_init();
		// dark_debug($post_arr,'--------------$post_arr-------------');
		curl_setopt($chb, CURLOPT_URL, $this->domain_url . "/" . ltrim($route_path, '/'));
		curl_setopt($chb, CURLOPT_CUSTOMREQUEST, $this->post_type);
		// curl_setopt($chb, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($chb, CURLOPT_POSTFIELDS, http_build_query($post_arr));
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
}