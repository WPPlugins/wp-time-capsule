<?php

class Wptc_Vulns_Hooks extends Wptc_Base_Hooks {
	public $hooks_handler_obj;

	public function __construct() {
		$supposed_hooks_hanlder_class = get_class($this) . '_Hanlder';
		$this->hooks_handler_obj = WPTC_Pro_Factory::get($supposed_hooks_hanlder_class);
	}

	public function register_hooks() {
		if (current_user_can('activate_plugins')) {
			$this->register_actions();
		}
		$this->register_filters();
		$this->register_wptc_actions();
		$this->register_wptc_filters();
	}

	protected function register_actions() {
		add_action('run_vulns_check_wptc', array($this->hooks_handler_obj, 'run_vulns_check'));
		add_action('wp_ajax_save_vulns_settings_wptc', array($this->hooks_handler_obj, 'save_vulns_settings'));
		add_filter('get_vulns_settings_wptc', array($this->hooks_handler_obj, 'get_vulns_settings'));
	}

	protected function register_filters() {
	}

	protected function register_wptc_actions() {
	}

	protected function register_wptc_filters() {
		add_filter('page_settings_tab_wptc', array($this->hooks_handler_obj, 'page_settings_tab'), 1);
		add_filter('page_settings_content_wptc', array($this->hooks_handler_obj, 'page_settings_content'), 1);
	}

}