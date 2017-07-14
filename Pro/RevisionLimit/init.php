<?php

class Wptc_Revision_Limit extends WPTC_Privileges {
	protected $config;

	public function __construct() {
		$this->config = WPTC_Pro_Factory::get('Wptc_Revision_Limit_Config');
	}

	public function init() {
		if ($this->is_privileged_feature(get_class($this)) && $this->is_switch_on()) {
			$supposed_hooks_class = get_class($this) . '_Hooks';
			WPTC_Pro_Factory::get($supposed_hooks_class)->register_hooks();

			$this->update_revision_limit();
		}
	}

	private function is_switch_on(){
		return true;
	}

	private function update_revision_limit(){
		$args = $this->config->get_option('privileges_args');

		if(!empty($args)){
			$args = json_decode($args, true);
			$this_class_name = get_class($this);
			$revision_obj = $args[$this_class_name];
			$revision_days = $revision_obj['days'];
		}

		if(empty($revision_days)){
			$revision_days = WPTC_FALLBACK_REVISION_LIMIT_DAYS;
		}

		$this->config->set_option('revision_limit', $revision_days);
	}

}