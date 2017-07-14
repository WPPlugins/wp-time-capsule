<?php

class Wptc_Backup_Before_Update_Hooks extends Wptc_Base_Hooks {
	public $hooks_handler_obj;
	public $wp_filter_id;

	public function __construct() {
		$supposed_hooks_hanlder_class = get_class($this) . '_Hanlder';
		$this->hooks_handler_obj = WPTC_Pro_Factory::get($supposed_hooks_hanlder_class);
	}

	public function register_hooks() {
		if (current_user_can('activate_plugins')) {
			$this->register_actions();
		}
		$this->register_filters();
		$this->register_wptc_actions();
		$this->register_wptc_filters();
		$this->register_auto_update_settings_filters();
	}

	protected function register_actions() {
		add_action('wp_ajax_get_check_to_show_dialog', array($this->hooks_handler_obj, 'get_check_to_show_dialog_callback_wptc'));
		add_action('wp_ajax_wptc_backup_before_update_setting', array($this->hooks_handler_obj, 'wptc_backup_before_update_setting'));
		add_action('wp_ajax_clear_bbu_notes_wptc', array($this->hooks_handler_obj, 'clear_bbu_notes'));
		add_action('wp_ajax_get_installed_plugins_wptc', array($this->hooks_handler_obj, 'get_installed_plugins'));
		add_action('wp_ajax_get_installed_themes_wptc', array($this->hooks_handler_obj, 'get_installed_themes'));
		add_action('wp_ajax_save_bbu_settings_wptc', array($this->hooks_handler_obj, 'save_bbu_settings'));
		add_action('wp_ajax_validate_free_paid_items_wptc', array($this->hooks_handler_obj, 'validate_free_paid_items'));
	}

	protected function register_filters() {
		add_filter('send_core_update_notification_email', array($this->hooks_handler_obj, 'filter_hanlder'), 1, 2);

		$this->register_filters_may_be_prevent_auto_update();
	}

	protected function register_filters_may_be_prevent_auto_update() {
		//dark_debug(array(), "--------trying to register_filters_may_be_prevent_auto_update --------");

		add_filter('auto_update_core', array($this->hooks_handler_obj, 'may_be_prevent_auto_update'), 99, 3);
		add_filter('auto_update_theme', array($this->hooks_handler_obj, 'may_be_prevent_auto_update'), 99, 3);
		add_filter('auto_update_plugin', array($this->hooks_handler_obj, 'may_be_prevent_auto_update'), 99, 3);
		add_filter('auto_update_translation', array($this->hooks_handler_obj, 'may_be_prevent_auto_update'), 99, 3);
	}

	protected function register_wptc_actions() {
		add_action('just_initialized_fresh_backup_wptc_h', array($this->hooks_handler_obj, 'just_initialized_fresh_backup_wptc_h'));
		add_action('just_completed_fresh_backup_any_wptc_h', array($this->hooks_handler_obj, 'just_completed_fresh_backup_any_wptc_h'));
		add_action('admin_enqueue_scripts', array($this->hooks_handler_obj, 'enque_js_files'));
		add_action('pre_auto_update', array($this->hooks_handler_obj, 'pre_auto_update'));
		add_action('automatic_updates_complete', array($this->hooks_handler_obj, 'automatic_updates_complete'));
		add_action('install_actions_wptc', array($this->hooks_handler_obj, 'install_actions_wptc'));
		add_action('force_trigger_auto_updates_wptc', array($this->hooks_handler_obj, 'force_trigger_auto_updates'));
		add_action('turn_off_auto_update_wptc', array($this->hooks_handler_obj, 'turn_off_auto_update'));
		add_action('auto_update_failed_email_user_wptc', array($this->hooks_handler_obj, 'auto_update_failed_email_user'));
		add_action('turn_off_themes_auto_updates_wptc', array($this->hooks_handler_obj, 'turn_off_themes_auto_updates'));
		add_action('exclude_paid_plugin_from_au_wptc', array($this->hooks_handler_obj, 'exclude_paid_plugin_from_au'));
	}

	protected function register_wptc_filters() {
		add_filter('page_settings_content_wptc', array($this->hooks_handler_obj, 'page_settings_content'), 1);
		add_filter('http_request_args', array($this->hooks_handler_obj, 'site_transient_update_plugins_h'), 10, 2);
		add_filter('get_backup_before_update_setting_wptc', array($this->hooks_handler_obj, 'get_backup_before_update_setting_wptc'), 10, 2);
		add_filter('get_bbu_note_view', array($this->hooks_handler_obj, 'get_bbu_note_view'), 10, 2);
		add_filter('is_upgrade_in_progress_wptc', array($this->hooks_handler_obj, 'is_upgrade_in_progress'), 10, 2);
		add_filter('backup_and_update_wptc', array($this->hooks_handler_obj, 'backup_and_update'), 999, 2);
		add_filter('page_settings_tab_wptc', array($this->hooks_handler_obj, 'page_settings_tab'), 1);
	}

	protected function register_auto_update_settings_filters(){
		$settings = $this->hooks_handler_obj->get_auto_update_settings();
		// dark_debug($settings, '---------$settings------------');
		if ($settings['core']['major']['status']){
			// dark_debug(array(), '---------core major set------------');
			add_filter( 'allow_major_auto_core_updates', '__return_true', 1 );
		} else{
			// dark_debug(array(), '---------core major unset------------');
			add_filter( 'allow_major_auto_core_updates', '__return_false', 1 );
		}

		if ($settings['core']['minor']['status']) {
			// dark_debug(array(), '---------core minor set------------');
			add_filter( 'allow_minor_auto_core_updates', '__return_true', 1 );
		} else {
			// dark_debug(array(), '---------core minor unset------------');
			add_filter( 'allow_minor_auto_core_updates', '__return_false', 1 );
		}

		if ($settings['plugins']['status']){
			// dark_debug(array(), '---------core plugins set------------');
			add_filter( 'auto_update_plugin', '__return_true', 1 );
		} else{
			// dark_debug(array(), '---------core plugins unset------------');
			add_filter( 'auto_update_plugin', '__return_false', 1 );
		}

		if ($settings['themes']['status']){
			// dark_debug(array(), '---------core themes set------------');
			add_filter( 'auto_update_theme', '__return_true', 1 );
		} else{
			// dark_debug(array(), '---------core themes unset------------');
			add_filter( 'auto_update_theme', '__return_false', 1 );
		}
	}
}