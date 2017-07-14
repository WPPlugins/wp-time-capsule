<?php

class Wptc_Settings_Config extends Wptc_Base_Config {
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
			'main_account_email' => 'retainable',
			'signed_in_repos' => 'retainable',
			'anonymous_datasent' => 'retainable',
			'wptc_timezone' => 'retainable',
			'schedule_time_str' => 'retainable',
			'user_excluded_extenstions' => 'retainable',
			'gdrive_old_token' => 'retainable',
		);
		$this->used_wp_options = array(
			//
		);
	}
}