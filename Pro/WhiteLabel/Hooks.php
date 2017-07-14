<?php

class Wptc_White_Label_Hooks extends Wptc_Base_Hooks {
	public $hooks_handler_obj;
	private $config;
	private $WhiteLabel_obj;

	public function __construct() {
		$supposed_hooks_hanlder_class = get_class($this) . '_Hanlder';
		$this->hooks_handler_obj = WPTC_Pro_Factory::get($supposed_hooks_hanlder_class);
		$this->config = WPTC_Pro_Factory::get('Wptc_White_Label_Config');
		$this->whitelabel_obj = WPTC_Pro_Factory::get('Wptc_White_Label');
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
		//general settings
		add_action('admin_init', array($this,'admin_actions'));

		//hide all updates
		add_action('admin_menu', array($this,'admin_menu_actions'), 999, 1);

		//update settings from service
		add_action('update_white_labling_settings_wptc', array($this->hooks_handler_obj, 'update_settings'), 10, 1);

		add_action('set_user_to_access_wl_wptc', array($this->hooks_handler_obj, 'set_user_to_access'), 10, 1);

	}

	public function admin_actions(){
		$settings = $this->hooks_handler_obj->get_settings();

		if ($settings['status'] == 'normal') return false;

		if($this->hooks_handler_obj->validate_users_access_wptc() === 'authorized') return false;

		//Hiding the view details alone.
		add_filter('plugin_row_meta', array($this->hooks_handler_obj, 'replace_row_meta'), 10, 2);

		//Hiding the wptc update details.
		add_filter('site_transient_update_plugins', array($this->hooks_handler_obj, 'site_transient_update_plugins'), 10, 2);

		//Modifying the link available in plugin's view version details link.
		add_filter('admin_url', array($this->hooks_handler_obj, 'user_admin_url'), 10, 2);

		//Replacing name and other details.
		add_filter('all_plugins', array($this->hooks_handler_obj, 'replace_details'));
	}

	public function admin_menu_actions(){
		$settings = $this->hooks_handler_obj->get_settings();

		if(empty($settings)) return false;

		if($this->hooks_handler_obj->validate_users_access_wptc() === 'authorized') return false;

		if(!empty($settings['hide_updates'])){
			$page = remove_submenu_page( 'index.php', 'update-core.php' );
			// add_filter('site_transient_update_core', array($this, 'remove_core_updates'), 10, 1);
			add_filter('site_transient_update_plugins', array($this->hooks_handler_obj, 'remove_updates'), 10, 1);
			// add_filter('site_transient_update_themes', array($this, 'remove_core_updates'), 10, 1);
		}

		dark_debug($settings, '--------$settings--------');
		if(!empty($settings['hide_edit'])){
			remove_submenu_page('themes.php','theme-editor.php');
			remove_submenu_page('plugins.php','plugin-editor.php');
			dark_debug(array(), '--------hello--------');
			add_filter('plugin_action_links', array($this->hooks_handler_obj, 'replace_action_links'), 10, 2);
		}
	}

	protected function register_filters() {
		add_filter('validate_users_access_wptc', array($this->hooks_handler_obj, 'validate_users_access_wptc'));

	}

	protected function register_wptc_actions() {
	}

	protected function register_wptc_filters() {
		add_filter('is_whitelabling_enabled_wptc', array($this->hooks_handler_obj, 'is_whitelabling_enabled'), 10);
	}

}