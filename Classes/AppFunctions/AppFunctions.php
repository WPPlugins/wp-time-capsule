<?php

class Wptc_App_Functions extends Wptc_App_Functions_Init {
	private $config;
	public function __construct(){
		//using common config here for not making config list complex
		$this->config = WPTC_Factory::get('config');
	}

	public function set_user_to_access(){
		if ( ! function_exists( 'wp_get_current_user' ) )
			include_once ABSPATH.'wp-includes/pluggable.php';
		$user_id = $this->get_current_user_id();
		$oneyear = 60 * 60 * 24 * 365;
		dark_debug(array(), '--------User added--------');
		setcookie('wptc_wl_allowed_user_id', $user_id, time() + $oneyear);
	}

	public function get_current_user_id(){
		if ( ! function_exists( 'wp_get_current_user' ) )
			include_once ABSPATH.'wp-includes/pluggable.php';
		$user = wp_get_current_user();
		return $user->data->ID;
	}

	public function shortern_plugin_slug($full_slug){
		if (strpos($full_slug, '/') !== false) {
			return substr($full_slug, 0, strrpos($full_slug, "/"));
		} else if(strpos($full_slug, '.') !== false){
			return substr($full_slug, 0, strrpos($full_slug, "."));
		}
	}

	public function is_user_purchased_this_class($classname = false){
		dark_debug(func_get_args(), "--------" . __FUNCTION__ . "--------");
		if (empty($classname)) return false;

		$data = $this->config->get_option('privileges_wptc');

		if (empty($data)) return false;

		$data = json_decode($data);

		if (empty($data)) return false;

		if (!empty($data->pro)) {
			$pro_arr = $data->pro;
		}

		if (!empty($data->lite)) {
			$pro_arr = $data->lite;
		}

		if (empty($pro_arr)) return false;

		$pro_arr_values = array_values($pro_arr);
		if (empty($pro_arr_values))	return false;

		dark_debug($pro_arr_values, '--------$pro_arr_values--------');

		if (in_array($classname, $pro_arr_values)) {
			dark_debug(array(), '--------YESS--------');
			return true;
		}

		return false;
	}
	public function is_free_user_wptc(){
		if($this->is_user_purchased_this_class('Wptc_Weekly_Backups') || !$this->is_user_purchased_this_class('Wptc_Daily_Backups')){
			return true;
		} else {
			return false;
		}
	}

	public function validate_dropbox_upgrade(){
		if($this->config->get_option('default_repo') != 'dropbox')
			return ;

		//check upgraded is successfull then return here
		if($this->verify_dropbox_api2_upgrade() === true)
			return ;

		//try upgrade if possible
		if($this->upgrade_dropbox_api1_to_api2() === false)
			return $this->remove_dropbox_api1_flags();

		//check upgrade status once again.
		if($this->verify_dropbox_api2_upgrade() === true)
			return ;

		//try upgrade if possible
		if($this->upgrade_dropbox_api1_to_api2() === false)
			return $this->remove_dropbox_api1_flags();

		//check upgrade status once again.
		if($this->verify_dropbox_api2_upgrade() === true)
			return ;

		return $this->remove_dropbox_api1_flags();
	}

	private function remove_dropbox_api1_flags(){
		$this->config->delete_option('access_token');
		$this->config->delete_option('access_token_secret');
		$this->config->delete_option('request_token');
		$this->config->delete_option('request_token_secret');
		$this->config->delete_option('oauth_state');
		$this->config->set_option('default_repo', '');
	}

	private function verify_dropbox_api2_upgrade(){
		//API-2 flags
		if ($this->config->get_option('dropbox_access_token') && $this->config->get_option('dropbox_oauth_state') === 'access' ){
			return true;
		}

		return false;
	}

	private function upgrade_dropbox_api1_to_api2(){
		//API-1 flags
		if (!$this->config->get_option('access_token') || !$this->config->get_option('access_token_secret')){
			return false;
		}

		//try upgrade once again
		$dropbox = WPTC_Factory::get('dropbox');
		$dropbox->migrate_to_v2();
	}
}