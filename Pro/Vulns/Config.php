<?php

class Wptc_Vulns_Config extends Wptc_Base_Config {
	protected $config;
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
			'is_autoupdate_vulns_settings_enabled' => 'retainable',
			'backup_before_update_details' => 'retainable',
			'bulk_update_request' => 'retainable',
			'is_bulk_update_request' => 'retainable',
			'is_vulns_updates' => 'retainable',
		);
		$this->used_wp_options = array(
		);
	}
}