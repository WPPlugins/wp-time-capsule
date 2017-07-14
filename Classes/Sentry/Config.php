<?php

class Wptc_Sentry_Config extends Wptc_Base_Config {
	protected $used_options;
	protected $used_wp_options;

	public function __construct() {
		$this->init();
	}

	private function init() {
		$this->set_used_options();
	}

	protected function set_used_options() {
		$this->used_options = array(
			'appID' => '',
			'main_account_email' => '',
		);

		$this->used_wp_options = array(
		);
	}
}