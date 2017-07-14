<?php

class Wptc_Update_Common extends Wptc_Update_Common_Init {
	private $cron_server_curl,
			$config,
			$app_functions;

	public function __construct(){
		$this->config = WPTC_Base_Factory::get('Wptc_Update_Common_Config');
		$this->cron_server_curl = WPTC_Base_Factory::get('Wptc_Cron_Server_Curl_Wrapper');
		$this->app_functions = WPTC_Base_Factory::get('Wptc_App_Functions');
	}

	public function analyse_free_paid_plugins_themes($plugin_data = false, $theme_data = false){

		if (empty($plugin_data)) {
			$plugin_data = $this->get_plugin_slugs();
		}

		if(empty($theme_data)){
			$theme_slugs = $this->get_theme_slugs();
		}

		$post_arr = array(
			'plugin_slugs' => $plugin_data['plugin_slugs'],
			'theme_slugs' => $theme_slugs,
		);

		$result = $this->cron_server_curl->do_call('free-paid-plugins-themes', $post_arr);
		$result = json_decode($result, true);

		$this->update_plugins_status($result['free_paid_plugins'], $plugin_data['plugin_paths']);
		$this->update_themes_status($result['free_paid_themes']);
	}

	public function analyse_free_paid_after_theme_delete(){
		dark_debug($_POST, '--------$_POST--------');
		if ((!isset($_POST['slug']) || !isset($_POST['action']) || $_POST['action'] != 'delete-theme')){
			dark_debug(array(), '--------calls rejected--------');
			return false;
		}

		dark_debug(array(), '--------Calsl accepeted--------');
		$theme_slugs = $this->get_theme_slugs();
		dark_debug($theme_slugs, '--------$theme_slugs--------');
		if(($key = array_search($_POST['slug'], $theme_slugs)) !== false) {
			unset($theme_slugs[$key]);
		}
		dark_debug($theme_slugs, '--------$theme_slugs after--------');
		$this->analyse_free_paid_plugins_themes(false, $theme_slugs);
	}

	private function get_plugin_slugs(){
		if (!function_exists('get_plugins')) include_once ABSPATH . 'wp-admin/includes/plugin.php';

		$all_plugins = get_plugins();
		$plugin_slugs = $plugin_paths = array();

		foreach ($all_plugins as $key => $plugin) {
			$plugin_slugs[] = $this->app_functions->shortern_plugin_slug($key);
			$plugin_paths[] = $key;
		}

		return array(
			'plugin_slugs' => $plugin_slugs,
			'plugin_paths' => $plugin_paths,
			);
	}

	private function get_theme_slugs(){
		if (!function_exists('wp_get_themes')) 	include_once ABSPATH . 'wp-includes/theme.php';

		$all_themes = wp_get_themes();
		$themes = array();

		foreach ($all_themes as $slug => $theme) {
			$themes[] = $slug;
		}

		return $themes;;
	}

	private function update_plugins_status($free_paid_plugins, $plugin_paths){
		$free_plugins = $free_paid_plugins['free'];
		$paid_plugins = $free_paid_plugins['paid'];

		$plugin_data = array();

		if (!empty($free_plugins)) {
			foreach ($free_plugins as $free_plugin) {
				foreach ($plugin_paths as $plugin_path) {
					if (strpos($plugin_path, $free_plugin) !== false) {
						$plugin_data[$plugin_path]['slug'] = $plugin_path;
						$plugin_data[$plugin_path]['type'] = 'free';
						break;
					}
				}
			}
		}

		if (!empty($paid_plugins)) {
			foreach ($paid_plugins as $paid_plugin) {
				foreach ($plugin_paths as $plugin_path) {
					if (strpos($plugin_path, $paid_plugin) !== false) {
						$plugin_data[$plugin_path]['slug'] = $plugin_path;
						$plugin_data[$plugin_path]['type'] = 'paid';
						break;
					}
				}
			}
		}

		$this->config->set_option('free-paid-plugins', serialize($plugin_data));
	}

	private function update_themes_status($free_paid_themes){
		$free_themes = !empty($free_paid_themes['free']) ? $free_paid_themes['free'] : array();
		$paid_themes = !empty($free_paid_themes['paid']) ? $free_paid_themes['paid'] : array();

		$theme_data = array();

		if (!empty($free_themes)) {
			foreach ($free_themes as $theme) {
				$theme_data[$theme]['slug'] = $theme;
				$theme_data[$theme]['type'] = 'free';
			}
		}

		if (!empty($paid_themes)) {
			foreach ($paid_themes as $theme) {
				$theme_data[$theme]['slug'] = $theme;
				$theme_data[$theme]['type'] = 'paid';
			}
		}

		$this->config->set_option('free-paid-themes', serialize($theme_data));
	}

	private function get_option($key){
		$data = $this->config->get_option($key);
		dark_debug($key, '--------$key--------');
		if (empty($data)) {
			return false;
		}

		return unserialize($data);
	}

	public function is_free_theme($slug){
		$themes_data = $this->get_option('free-paid-themes');

		if (empty($themes_data) || !array_key_exists($slug, $themes_data)) {
			dark_debug(array(), '--------Not found so refreshing--------');
			//Not found from the data so refresh data from server
			$this->analyse_free_paid_plugins_themes();

			//second retry
			$themes_data = $this->get_option('free-paid-themes');
			if (!array_key_exists($slug, $themes_data)) {
				//Could not find this theme so assume its paid
				dark_debug(array(), '--------Not found seconds retry--------');
				return false;
			}
		}

		return ($themes_data[$slug]['type'] === 'free') ? true : false;
	}

	public function is_free_plugin($slug){
		$plugins_data = $this->get_option('free-paid-plugins');
		// dark_debug($plugins_data, '--------$plugins_data--------');
		if (empty($plugins_data) || !array_key_exists($slug, $plugins_data)) {
			//Not found from the data so refresh data from server
			dark_debug(array(), '--------Not found so refreshing--------');
			$this->analyse_free_paid_plugins_themes();

			//second retry
			$plugins_data = $this->get_option('free-paid-plugins');
			if (!array_key_exists($slug, $plugins_data)) {
				//Could not find this theme so assume its paid
				dark_debug(array(), '--------Not found seconds retry--------');
				return false;
			}
		}

		return ($plugins_data[$slug]['type'] === 'free') ? true : false;
	}

	public function get_plugin_name_by_slug($slug){
		if(!function_exists('wp_get_theme')) @include_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugin_info = get_plugin_data(WPTC_WP_CONTENT_DIR.'/plugins/'.$slug);
		dark_debug($plugin_info, '--------$plugin_info--------');
		if (empty($plugin_info)) {
			return $slug;
		}

		return $plugin_info['Name'];
	}

	public function get_theme_name_by_slug($slug){
		if(!function_exists('wp_get_theme')) @include_once ABSPATH . 'wp-admin/includes/theme.php';

		$theme_info = wp_get_theme($slug);

		if (empty($theme_info)) {
			return $slug;
		}

		return $theme_info->get( 'Name' );
	}
}