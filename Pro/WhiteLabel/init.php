<?php

class Wptc_White_Label extends WPTC_Privileges {

	protected $config;
	protected $settings;
	protected $wptc_base_file;
	protected $wptc_readme_file;
	protected $app_functions;

	public function __construct() {
		$this->config = WPTC_Pro_Factory::get('Wptc_White_Label_Config');
		$this->wptc_base_file = 'wp-time-capsule/wp-time-capsule.php';
		$this->wptc_readme_file = 'wp-time-capsule/readme.txt';
		$this->app_functions = WPTC_Base_Factory::get('Wptc_App_Functions');
	}

	public function init() {
		if ($this->is_privileged_feature(get_class($this)) && $this->is_switch_on()) {
			$supposed_hooks_class = get_class($this) . '_Hooks';
			WPTC_Pro_Factory::get($supposed_hooks_class)->register_hooks();
		}
	}

	private function is_switch_on(){
		return true;
	}

	public function get_settings(){
		if ($this->settings) return $this->settings;

		$data = $this->config->get_option('white_lable_details');

		if (empty($data)) return false;

		$this->settings = unserialize($data);

		return $this->settings;
	}

	public function replace_row_meta($links, $file) {
		//Hiding the view details alone.
		if($file == $this->wptc_base_file){
			if(!empty($links[2])){
				unset($links[2]);
			}
		}

		return $links;
	}

	public 	function site_transient_update_plugins($value){
		if(empty($value->response[$this->wptc_base_file]))	return $value;

		$settings = $this->get_settings();

		if(empty($settings) || !is_array($settings))	return $value;

		if(empty($settings['name']))	return $value;

		$file_traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$called_by_file = array_pop($file_traces);
		$called_by_file = basename($called_by_file['file']);
		if($called_by_file == "update-core.php"){
			//Hiding the updates available in updates dashboard section
			unset($value->response[$this->wptc_base_file]);
		} else if($called_by_file == "plugins.php"){
			//Hiding the updates available in plugins section
			$value->response[$this->wptc_base_file]->slug = $settings['name'];
			$value->response[$this->wptc_base_file]->Name = $settings['name'];
		}

		return $value;
	}

	public function user_admin_url($value, $path){
		if(strpos($path, 'plugin-install.php?tab=plugin-information&plugin') === false) return $value;

		$settings = $this->get_settings();

		if(empty($settings) || !is_array($settings))	return $value;

		if(empty($settings['name']))	return $value;

		$search_str = 'plugin-install.php?tab=plugin-information&plugin='.$settings['name'].'&section=changelog';
		if(strpos($path, $search_str) === false){
			return $value;
		}

		//Modifying the link available in plugin's view version details link.
		$return_var = plugins_url( '/'.$this->wptc_readme_file ) . 'TB_iframe=true&width=600&height=550';

		return  $return_var;
	}

	public function replace_details($all_plugins){
		$settings = $this->get_settings();

		if(empty($settings) || !is_array($settings))	return $all_plugins;

		if ($settings['status'] === 'change_details') {
			$all_plugins[$this->wptc_base_file]['Name'] = $settings['name'];
			$all_plugins[$this->wptc_base_file]['Title'] = $settings['name'];
			$all_plugins[$this->wptc_base_file]['Description'] = $settings['description'];
			$all_plugins[$this->wptc_base_file]['AuthorURI'] = $settings['author_url'];
			$all_plugins[$this->wptc_base_file]['Author'] = $settings['author'];
			$all_plugins[$this->wptc_base_file]['AuthorName'] = $settings['author'];
			$all_plugins[$this->wptc_base_file]['PluginURI'] = '';
		}

		if($settings['status'] !== 'hide_from_plugin_list') return $all_plugins;

		if (!function_exists('get_plugins')) include_once(ABSPATH . 'wp-admin/includes/plugin.php');

		$activated_plugins = get_option('active_plugins');

		if (!$activated_plugins) return $all_plugins;

		if(in_array($this->wptc_base_file,$activated_plugins)) 	unset($all_plugins[$this->wptc_base_file]);

		return $all_plugins;
	}

	public function remove_updates($value){

		if(isset($value->response)){
			unset($value->response[$this->wptc_base_file]);
		}

		if(isset($value->updates)){
			unset($value->updates[$this->wptc_base_file]);
		}

		return $value;
	}

	public function replace_action_links($links, $file){

		//Hiding edit on plugins page.
		if(!empty($links['edit'])){
			unset($links['edit']);
		}
		return $links;
	}

	public function update_settings($obj){

		if (!isset($obj->white_label_settings)) {
			return false;
		}

		if ($obj->white_label_settings == false || empty($obj->white_label_settings)) {
			$settings = array(
				'status' 		=> 'normal',
				'name' 			=> '',
				'author' 		=> '',
				'author_url' 	=> '',
				'description' 	=> '',
				'hide_updates' 	=> '',
				'hide_edit' 	=> '',
			);
			return $this->config->set_option('white_lable_details', serialize($settings));
		}

		$settings = array(
			'status' 		=> isset($obj->white_label_settings->wl_select_action)	? $obj->white_label_settings->wl_select_action  : '',
			'name' 			=> isset($obj->white_label_settings->plugin_name) 		? $obj->white_label_settings->plugin_name 		: '',
			'author' 		=> isset($obj->white_label_settings->author_name) 		? $obj->white_label_settings->author_name 		: '',
			'author_url' 	=> isset($obj->white_label_settings->author_url) 		? $obj->white_label_settings->author_url 		: '',
			'description' 	=> isset($obj->white_label_settings->plugin_description)? $obj->white_label_settings->plugin_description: '',
			'hide_updates' 	=> isset($obj->white_label_settings->hide_updates) 		? $obj->white_label_settings->hide_updates 		: '',
			'hide_edit' 	=> isset($obj->white_label_settings->hide_edit) 		? $obj->white_label_settings->hide_edit 		: '',
		);

		$this->config->set_option('white_lable_details', serialize($settings));
	}

	public function validate_users(){

		if (!isset($_GET['wptc_wl_code'])) return false;

		$wptc_wl_code = base64_decode(urldecode($_GET['wptc_wl_code']));

		dark_debug($wptc_wl_code, '--------$wptc_wl_code--------');

		if (empty($wptc_wl_code)) return false;

		if (md5($this->config->get_option('uuid')) != $wptc_wl_code) return false;

		WPTC_Base_Factory::get('Wptc_App_Functions')->set_user_to_access();
	}

	public function is_whitelabling_enabled(){
		$settings = $this->get_settings();
		if (empty($settings) || $settings['status'] == 'normal') {
			return false;
		}

		return ($this->validate_users_access_wptc() === 'authorized') ? false : true;
	}

	public function validate_users_access_wptc(){
		$this->validate_users();
		$settings = $this->get_settings();

		//if settings empty then user not enabled whitelabling so authorize all users
		if (empty($settings)) return 'authorized';

		//Whitelabling is not restricted so authorize all users
		if ($settings['status'] == 'normal') return 'authorized';

		$user_id = $this->app_functions->get_current_user_id();

		$allowed_user_id = isset($_COOKIE['wptc_wl_allowed_user_id']) ? $_COOKIE['wptc_wl_allowed_user_id'] : false ;

		return ($user_id != $allowed_user_id) ? 'not_authorized' : 'authorized';
	}

}