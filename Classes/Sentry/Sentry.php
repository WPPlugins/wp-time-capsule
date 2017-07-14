<?php

class Wptc_Sentry extends Wptc_Sentry_Init {
	public function __construct() {
		$this->db = WPTC_Factory::db();
		$this->config = WPTC_Base_Factory::get('Wptc_Sentry_Config');
		$this->add_user_info_sentry();
	}

	public function add_user_info_sentry(){
		global $sentry_client;
		$sentry_client->user_context(
			array(
				'app_id' => $this->config->get_option('appID'),
				'email' => $this->config->get_option('main_account_email'),
			));
	}
}