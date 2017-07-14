<?php

class Wptc_White_Label_Hooks_Hanlder extends Wptc_Base_Hooks_Handler {
	protected $config;
	protected $WhiteLabel_obj;
	public function __construct() {
		$this->config = WPTC_Pro_Factory::get('Wptc_White_Label_Config');
		$this->whitelabel_obj = WPTC_Pro_Factory::get('Wptc_White_Label');
	}

	public function replace_row_meta($links, $file) {
		return $this->whitelabel_obj->replace_row_meta($links, $file);
	}

	public 	function site_transient_update_plugins($value){
		return $this->whitelabel_obj->site_transient_update_plugins($value);
	}

	public function user_admin_url($value, $path){
		return $this->whitelabel_obj->user_admin_url($value, $path);
	}

	public function replace_details($all_plugins){
		return $this->whitelabel_obj->replace_details($all_plugins);
	}

	public function get_settings(){
		return $this->whitelabel_obj->get_settings();
	}

	public function remove_updates($value){
		return $this->whitelabel_obj->remove_updates($value);
	}

	public function replace_action_links($links, $file){
		return $this->whitelabel_obj->replace_action_links($links, $file);
	}

	public function update_settings($obj){
		return $this->whitelabel_obj->update_settings($obj);
	}

	public function validate_users(){
		return $this->whitelabel_obj->validate_users();
	}

	public function validate_users_access_wptc(){
		return $this->whitelabel_obj->validate_users_access_wptc();
	}

	public function is_whitelabling_enabled(){
		return $this->whitelabel_obj->is_whitelabling_enabled();
	}

	public function set_user_to_access(){
		return $this->whitelabel_obj->set_user_to_access();
	}
}