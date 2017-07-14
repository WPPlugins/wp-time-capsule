<?php

class Wptc_Vulns extends WPTC_Privileges{
	protected	$config,
				$cron_server_curl,
				$app_functions;

	public function __construct() {
		$this->config = WPTC_Pro_Factory::get('Wptc_Vulns_Config');
		$this->cron_server_curl = WPTC_Base_Factory::get('Wptc_Cron_Server_Curl_Wrapper');
		$this->app_functions = WPTC_Base_Factory::get('Wptc_App_Functions');
	}

	public function init(){
		if ($this->is_privileged_feature(get_class($this)) && $this->is_switch_on()) {
			$supposed_hooks_class = get_class($this) . '_Hooks';
			WPTC_Pro_Factory::get($supposed_hooks_class)->register_hooks();
		}
	}

	private function is_switch_on(){
		return true;
	}

	public function run_vulns_check(){
		//if vulns not enabled in the settings then do not run vulns updates
		if(!$this->is_vulns_enabled()){
			return false;
		}

		$upgradable_plugins = $this->get_upgradable_plugins();
		$upgradable_themes = $this->get_upgradable_themes();
		$upgradable_core_arr = $this->get_upgradable_core();

		$upgradable_core = $upgradable_core_arr['update_data'];
		$upgradable_core_deep_data = $upgradable_core_arr['deep_data'];

		$post_arr = array(
			'plugins_data' => $upgradable_plugins,
			'themes_data' => $upgradable_themes,
			'core_data' => $upgradable_core,
			);

		$raw_response = $this->cron_server_curl->do_call('run-vulns-check', $post_arr);

		if (empty($raw_response)) {
			return false;
		}

		$response = json_decode($raw_response);
		$response = $response->vulns_result;

		dark_debug($response, '--------$response--------');

		$plugins = (array) $response->affectedPlugins;
		$themes = (array) $response->affectedThemes;
		$core = (array) $response->affectedCores;

		$update_plugins = $this->purify_plugins_for_update($plugins, $upgradable_plugins);
		$update_themes = $this->purify_themes_for_update($themes);
		$update_core = $this->purify_core_for_update($core, $upgradable_core_deep_data);

		$this->prepare_bulk_upgrade_structure($update_plugins, $update_themes, $update_core);

	}

	private function prepare_bulk_upgrade_structure($upgrade_plugins, $upgrade_themes, $wp_upgrade){
		$final_upgrade_details = array();

		if (!empty($upgrade_plugins)) {
			$final_upgrade_details['upgrade_plugins']['update_items'] = $upgrade_plugins;
			$final_upgrade_details['upgrade_plugins']['updates_type'] = 'plugin';
			$final_upgrade_details['upgrade_plugins']['is_auto_update'] = '0';
		}

		if (!empty($upgrade_themes)) {
			$final_upgrade_details['upgrade_themes']['update_items'] = $upgrade_themes;
			$final_upgrade_details['upgrade_themes']['updates_type'] = 'theme';
			$final_upgrade_details['upgrade_themes']['is_auto_update'] = '0';

		}

		if (!empty($wp_upgrade)) {
			$final_upgrade_details['wp_upgrade']['update_items'] = $wp_upgrade;
			$final_upgrade_details['wp_upgrade']['updates_type'] = 'core';
			$final_upgrade_details['wp_upgrade']['is_auto_update'] = '0';
		}

		//Translations does not have vulns updates
		/*if (!empty($upgrade_translations)) {
			$final_upgrade_details['upgrade_translations']['update_items'] = $upgrade_translations;
			$final_upgrade_details['upgrade_translations']['updates_type'] = 'translation';
			$final_upgrade_details['upgrade_translations']['is_auto_update'] = '0';
		}*/

		dark_debug($final_upgrade_details, '--------$final_upgrade_details--------');
		if (empty($final_upgrade_details)) {
			return false;
		}
		// return false;
		$this->bulk_update_request($final_upgrade_details);
		$this->config->set_option('is_bulk_update_request', true);
		$this->config->set_option('backup_before_update_details', false);
		$this->config->set_option('is_vulns_updates', true);
		start_fresh_backup_tc_callback_wptc('manual');
	}

	private function bulk_update_request($bulk_update_request){
		dark_debug($bulk_update_request, '--------$bulk_update_request--------');
		if (empty($bulk_update_request)) {
			return $this->config->set_option('bulk_update_request', false);
		}

		$this->config->set_option('bulk_update_request', serialize($bulk_update_request));
	}

	private function purify_plugins_for_update($plugins_data, $upgradable_plugins){

		$plugins = array();

		if (empty($plugins_data)) {
			return $plugins;
		}

		foreach ($plugins_data as $key => $plugin_data) {
			$plugins[$upgradable_plugins[$key]['path']] = $upgradable_plugins[$key]['version'];
		}

		return $plugins;

	}

	private function purify_themes_for_update($themes_data){

		$themes = array();

		if (empty($themes_data)) {
			return $themes;
		}

		foreach ($themes_data as $key => $theme_data) {
			$themes[] = $key;
		}

		return $themes;

	}

	private function purify_core_for_update($core_data, $upgradable_core_deep_data){
		if (empty($core_data)) {
			return array();
		}

		return $upgradable_core_deep_data;
	}

	public function get_upgradable_plugins() {
		$current = wptc_mmb_get_transient('update_plugins');

		$upgradable_plugins = array();

		if (empty($current->response)) {
			return array();
		}

		if (!function_exists('get_plugin_data')) {
			include_once ABSPATH.'wp-admin/includes/plugin.php';
		}

		foreach ($current->response as $plugin_path => $plugin_data) {
			$data = get_plugin_data(WP_PLUGIN_DIR.'/'.$plugin_path, false, false);

			if (strlen($data['Name']) > 0 && strlen($data['Version']) > 0) {
				$slug = $this->app_functions->shortern_plugin_slug($plugin_path);
				$upgradable_plugins[$slug] = array(
						'path' => $plugin_path,
						'version' => $data['Version'],
						'slug' => $slug,
					);
			}
		}

		return $upgradable_plugins;
	}

	public function get_upgradable_themes() {
		if (function_exists('wp_get_themes')) {
			$all_themes     = wp_get_themes();
			$upgrade_themes = array();

			$current = wptc_mmb_get_transient('update_themes');

			if (empty($current->response)) {
				return $upgrade_themes;
			}

			foreach ((array)$all_themes as $theme_template => $theme_data) {
				foreach ($current->response as $current_themes => $theme) {

					if ($theme_data->Stylesheet !== $current_themes) {
						continue;
					}

					if (strlen($theme_data->Name) === 0 || strlen($theme_data->Version) === 0) {
						continue;
					}

					$upgrade_themes[$current_themes] = array(
							'slug' => $theme_data->Stylesheet,
							'version' => $theme_data->Version,
						);
				}
			}
		} else {

			$all_themes = get_themes();

			$upgrade_themes = array();

			$current = wptc_mmb_get_transient('update_themes');

			if (empty($current->response)) {
				return $upgrade_themes;
			}

			foreach ((array)$all_themes as $theme_template => $theme_data) {

				if (isset($theme_data['Parent Theme']) && !empty($theme_data['Parent Theme'])) {
					continue;
				}

				if (isset($theme_data['Name']) && in_array($theme_data['Name'], $filter)) {
					continue;
				}

				foreach ($current->response as $current_themes => $theme) {
					if ($theme_data['Template'] != $current_themes) {
						continue;
					}

					if (strlen($theme_data['Name']) == 0 || strlen($theme_data['Version']) == 0) {
						continue;
					}

					$upgrade_themes[$current_themes] = array(
							'slug' => $theme_data->Stylesheet,
							'version' => $theme_data->Version,
						);
				}
			}
		}

		return $upgrade_themes;
	}

	private function get_upgradable_core() {
		global $wp_version;

		$upgrade_core = array(
				'update_data' => '',
				'deep_data' => '',
			);

		$core = wptc_mmb_get_transient('update_core');

		if (!isset($core->updates) || empty($core->updates)) {
			return false;
		}

		$current_transient = $core->updates[0];

		if ($current_transient->response == "development" || version_compare($wp_version, $current_transient->current, '<')) {
			$current_transient->current_version = $wp_version;
			$upgrade_core['update_data'][$wp_version] = array('Version' => $wp_version);
			$upgrade_core['deep_data'] = $current_transient;
		}

		return $upgrade_core;
	}

	public function is_vulns_enabled(){
		return ($this->config->get_option('is_autoupdate_vulns_settings_enabled')) ? true : false;
	}

	public function save_vulns_settings($data){
		dark_debug($data, '--------$data--------');
		$status = empty($data['enable_vulns_settings_wptc']) ? 0 : 1;
		$this->config->set_option('is_autoupdate_vulns_settings_enabled', $status);
		do_action('send_ptc_list_to_server_wptc', time());
	}

	public function get_vulns_settings(){
		return $this->config->get_option('is_autoupdate_vulns_settings_enabled');
	}

}