<?php

class Wptc_Sentry_Hooks extends Wptc_Base_Hooks {
	public $hooks_handler_obj;

	public function __construct() {
		$supposed_hooks_hanlder_class = get_class($this) . '_Handler';
		$this->hooks_handler_obj = WPTC_Base_Factory::get($supposed_hooks_hanlder_class);
	}

	public function register_hooks() {
		$this->register_actions();
		$this->register_filters();
		$this->register_wptc_actions();
		$this->register_wptc_filters();
	}

	protected function register_actions() {

	}

	protected function register_filters() {
	}

	protected function register_wptc_actions() {
		// add_action('wptc_start_sentry_listeners', array($this->hooks_handler_obj, 'wptc_start_sentry_listeners'));
	}

	protected function register_wptc_filters() {
	}

}