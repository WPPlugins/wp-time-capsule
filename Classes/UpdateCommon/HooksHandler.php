<?php

class Wptc_Update_Common_Hooks_Handler extends Wptc_Base_Hooks_Handler {
	protected $update;

	public function __construct() {
		$this->update = WPTC_Base_Factory::get('Wptc_Update_Common');
	}

	public function analyse_free_paid_plugins_themes(){
		$this->update->analyse_free_paid_plugins_themes();
	}

	public function analyse_free_paid_after_theme_delete(){
		$this->update->analyse_free_paid_after_theme_delete();
	}
}