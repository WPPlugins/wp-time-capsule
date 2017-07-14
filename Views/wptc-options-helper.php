<?php

class Wptc_Options_Helper
{

	private $privileges_wptc = array();
	private $is_show_privilege_box = false;
	private $is_show_login_box = false;
	private $is_show_connect_pane = false;
	private $is_show_initial_setup = false;
	private $valid_user_but_no_plans_purchased = false;

	//GET Vars
	private $cloud_auth_action = false;
	private $show_connect_pane = false;
	private $error = false;

	function __construct()
	{
		$this->config = WPTC_Factory::get('config');

		$this->cloud_auth_action = (isset($_GET['cloud_auth_action'])) ? $_GET['cloud_auth_action'] : false;
		$this->show_connect_pane = (isset($_GET['show_connect_pane'])) ? $_GET['show_connect_pane'] : false;
		$this->error = (isset($_GET['error'])) ? $_GET['error'] : false;
	}

	//Basic settings

	function get_is_user_logged_in(){
		$is_user_logged_in = $this->config->get_option('is_user_logged_in');

		return $is_user_logged_in;
	}

	function get_default_repo(){
		$default_repo_connected = $this->config->get_option('default_repo');

		return $default_repo_connected;
	}

	function get_main_account_email()
	{
		$main_account_email = $this->config->get_option('main_account_email');

		return $main_account_email;
	}

	function get_valid_user_but_no_plans_purchased()
	{
		$valid_user_but_no_plans_purchased = $this->config->get_option('valid_user_but_no_plans_purchased');

		return $valid_user_but_no_plans_purchased;
	}

	function set_valid_user_but_no_plans_purchased($value = false){
		$this->config->set_option('valid_user_but_no_plans_purchased', $value);
	}

	//For privileges

	function get_unserialized_privileges()
	{
		$this->privileges_wptc = $this->config->get_option('privileges_wptc');
		$this->privileges_wptc = json_decode($this->privileges_wptc, true);

		return $this->privileges_wptc;
	}

	function get_unserialized_subs_info()
	{
		$this->subscription_info = $this->config->get_option('subscription_info');
		$this->subscription_info = json_decode($this->subscription_info, true);

		return $this->subscription_info;
	}

	function reload_privileges_if_empty(){
		if(empty($this->privileges_wptc)){
			$this->config->is_main_account_authorized();
			$this->privileges_wptc = $this->get_unserialized_privileges();
		}
	}

	function is_show_privilege_box(){

		$get_valid_user_but_no_plans_purchased = $this->get_valid_user_but_no_plans_purchased();

		$this->is_show_privilege_box = ($get_valid_user_but_no_plans_purchased && empty($this->privileges_wptc)) ? true : false;

		return $this->is_show_privilege_box;
	}

	function is_show_login_box()
	{
		$is_user_logged_in = $this->get_is_user_logged_in();
		$main_account_email = $this->get_main_account_email();

		$this->is_show_login_box = ((!$is_user_logged_in || !$main_account_email) && !$this->is_show_privilege_box) ? true : false;

		return $this->is_show_login_box;
	}

	function is_show_connect_pane()
	{
		$is_user_logged_in = $this->get_is_user_logged_in();
		$main_account_email = $this->get_main_account_email();
		$default_repo = $this->get_default_repo();

		$this->is_show_connect_pane = (
			!$this->is_show_initial_setup &&
			!$this->is_show_privilege_box &&
			$is_user_logged_in &&
			( !$default_repo || $this->cloud_auth_action || $this->show_connect_pane || !empty($this->error)) ) ? true : false;

		return $this->is_show_connect_pane;
	}


	function is_show_initial_setup(){
		$this->is_show_initial_setup = (
				(isset($_GET['cloud_auth_action']) && 
				$_GET['cloud_auth_action'] == 'g_drive' && 
				isset($_GET['code']) && 
				!isset($_GET['error']) || 
				isset($_GET['uid']) || 
				isset($_GET['as3_access_key'])) && 
				(DEFAULT_REPO_LABEL != 'Cloud') && 
				!isset($_GET['show_connect_pane'])
			) ? true : false;

		return $this->is_show_initial_setup;
	}

	function get_plan_name_from_privileges(){
		$privileges_wptc = $this->get_unserialized_privileges();

		dark_debug($privileges_wptc, "--------privileges_wptc---get_plan_name_from_privileges-----");

		if(empty($privileges_wptc) || !(is_array($privileges_wptc))){
			return '';
		}

		$plan_name = key($privileges_wptc);

		return $plan_name;
	}

	function get_plan_interval_from_subs_info(){
		$subscription_info = $this->get_unserialized_subs_info();

		dark_debug($subscription_info, "--------subscription_info---get_plan_name_from_privileges-----");

		if(empty($subscription_info) || !(is_array($subscription_info)) || $subscription_info['plan_name'] == 'Lite'){
			return '';
		}

		return ' '.$subscription_info['plan_interval'];
	}

}