<?php

class Wptc_Sentry_Hooks_Handler extends Wptc_Base_Hooks_Handler {
	protected $sentry;

	public function __construct() {
		$this->sentry = WPTC_Base_Factory::get('Wptc_Sentry');
	}
}