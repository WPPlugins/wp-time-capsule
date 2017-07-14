<?php

class Wptc_Update_Common_Hooks extends Wptc_Base_Hooks {
	public $hooks_handler_obj;

	public function __construct() {
		$supposed_hooks_hanlder_class = get_class($this) . '_Handler';
		$this->hooks_handler_obj = WPTC_Base_Factory::get($supposed_hooks_hanlder_class);
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
		//manual triggers
		add_action('analyse_free_paid_plugins_themes_wptc', array($this->hooks_handler_obj, 'analyse_free_paid_plugins_themes'));
		//After every new plugin/theme installed or plugin/theme gets updated
		add_action('upgrader_process_complete', array($this->hooks_handler_obj, 'analyse_free_paid_plugins_themes'));
		//after deleted a plugin
		add_action('deleted_plugin', array($this->hooks_handler_obj, 'analyse_free_paid_plugins_themes'));
		//after delete theme
		add_action('delete_site_transient_update_themes', array($this->hooks_handler_obj, 'analyse_free_paid_after_theme_delete'));

	}

	protected function register_filters() {
	}

	protected function register_wptc_actions() {
	}

	protected function register_wptc_filters() {
	}
}