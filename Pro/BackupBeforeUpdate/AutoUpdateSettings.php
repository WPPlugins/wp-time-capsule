<?php

class Wptc_Backup_Before_Auto_Update_Settings {
	protected $config,
			  $logger,
			  $update_common;

	public function __construct() {
		$this->config = WPTC_Pro_Factory::get('Wptc_Backup_Before_Update_Config');
		$this->logger = WPTC_Factory::get('logger');
		$this->update_common = WPTC_Base_Factory::get('Wptc_Update_Common');
	}

	public function get_auto_update_settings(){
		$settings_serialized = $this->config->get_option('wptc_auto_update_settings');
		if (empty($settings_serialized)) {
			return false;
		}

		$settings = unserialize($settings_serialized);
		return $settings['update_settings'];
	}

	public function get_auto_update_settings_html($bbu_setting){
		$auto_updater_settings = $this->get_auto_update_settings();
		// dark_debug($auto_updater_settings, '---------$auto_updater_settings------------');

		$status_auto_update = $auto_updater_settings['status'];
		$enable_auto_update_wptc = $disable_auto_update_wptc = $show_options = '';
		if ($status_auto_update == 'yes') {
			$enable_auto_update_wptc = 'checked';
			$show_options = 'display:block';
		} else {
			$disable_auto_update_wptc = 'checked';
			$show_options = 'display:none';
		}
		$core_major = $auto_updater_settings['core']['major']['status'];
		$core_major_checked = ($core_major) ? 'checked="checked"' : '';

		$core_minor = $auto_updater_settings['core']['minor']['status'];
		$core_minor_checked = ($core_minor) ? 'checked="checked"' : '';

		$plugins = $auto_updater_settings['plugins']['status'];
		$plugins_checked = ($plugins) ? 'checked="checked"' : '';

		$themes = $auto_updater_settings['themes']['status'];
		$themes_checked = ($themes) ? 'checked="checked"' : '';
		dark_debug($bbu_setting, '---------$bbu_setting------------');
		$style = '';
		if ($bbu_setting !== 'always') {
			$style ="";
		}
		$header = '
			<tr '.$style.' id="auto_update_settings_wptc" valign="top">
				<th scope="row"> '.__( 'Backup before auto-updates', 'wp-time-capsule' ).'</th>
				<td>
					<fieldset>
						<label title="Yes">
							<input name="auto_update_wptc_setting"  type="radio" id="enable_auto_update_wptc" '.$enable_auto_update_wptc.' value="yes">
							<span class="">
								'.__( 'Yes', 'wp-time-capsule' ).'
							</span>
						</label>
						<label title="No">
							<input name="auto_update_wptc_setting" type="radio" id="disable_auto_update_wptc" '.$disable_auto_update_wptc.' value="no">
							<span class="">
								'.__( 'No', 'wp-time-capsule' ).'
							</span>
						</label>
					</fieldset>
					<fieldset style="'.$show_options.'" id="enable_auto_update_options_wptc">
						<p><div class="automatic-updater-core-options">'.__( 'Update WordPress Core automatically?', 'wp-time-capsule' ).'</p>
					<fieldset style="margin-left: 30px;">';

		$core_major = '<input type="checkbox" id="wptc_auto_core_major" name="wptc_auto_core_major" value="1" '.$core_major_checked.'>
							<label for="wptc_auto_core_major">'.__( 'Major versions', 'wp-time-capsule' ).'</label><br>';

		$core_minor = '<input type="checkbox" id="wptc_auto_core_minor" name="wptc_auto_core_minor" value="1" '.$core_minor_checked.'>
							<label for="wptc_auto_core_minor">'.__( 'Minor and security versions <strong>(Strongly Recommended)', 'wp-time-capsule' ).'</strong></label>
						</fieldset>	</div>';

		$plugins = '<p>
						<input type="checkbox" id="wptc_auto_plugins" name="wptc_auto_plugins" value="1" '.$plugins_checked.'>
						<label for="wptc_auto_plugins">'.__( 'Update your plugins automatically?', 'wp-time-capsule' ).'
							<div style="display: none;" id="wptc_auto_update_plugins_dw"></div>
							<input style="display: none;" type="hidden" id="auto_include_plugins_wptc" name="auto_include_plugins_wptc"/>
						</label>
					</p>';

		$themes = '<p>
						<input type="checkbox" id="wptc_auto_themes" name="wptc_auto_themes" value="1" '.$themes_checked.'>
							<label for="wptc_auto_themes">	'.__( 'Update your themes automatically?', 'wp-time-capsule' ).'
								<div style="display: none;" id="wptc_auto_update_themes_dw"></div>
								<input style="display: none;" type="hidden" id="auto_include_themes_wptc" name="auto_include_themes_wptc"/>
							</label>
					</p><fieldset></td></tr>';
		//themes removed from the AU
		return $header . $core_major . $core_minor . $plugins;
	}

	public function update_auto_update_settings($options){
		dark_debug($options, '---------$options------------');
		$settings['update_settings']['status'] = empty($options['auto_update_wptc_setting']) ? "no" : $options['auto_update_wptc_setting'];
		$settings['update_settings']['core']['major']['status'] = empty($options['auto_updater_core_major']) ? 0 : 1;
		$settings['update_settings']['core']['minor']['status'] = empty($options['auto_updater_core_minor']) ? 0 : 1;
		$settings['update_settings']['themes']['status'] = empty($options['auto_updater_themes']) ? 0 : 1;
		$settings['update_settings']['plugins']['status'] = empty($options['auto_updater_plugins']) ? 0 : 1;

		if (!empty($options['auto_updater_plugins_included'])) {
			$plugin_include_array = explode(',', $options['auto_updater_plugins_included']);
			$settings['update_settings']['plugins']['included'] = serialize($plugin_include_array);
		}

		if (!empty($options['auto_updater_themes_included'])) {
			$themes_include_array = explode(',', $options['auto_updater_themes_included']);
			$settings['update_settings']['themes']['included'] = serialize($themes_include_array);
		}
		// dark_debug($settings, '---------$settings------------');
		$result = $this->config->set_option('wptc_auto_update_settings', serialize($settings));
	}

	public function save_default_settings(){
		$default_settings = array(
			'update_settings' => array(
				'status' => 'no',
				'core' => array (
					'major' => array('status' => 0 ),
					'minor' => array('status' => 1 ),
				),
				'themes' => array('status' => 0),
				'plugins' => array('status' => 0),
			),

		);
		// dark_debug($default_settings, '---------$default_settings------------');
		$result = $this->config->set_option('wptc_auto_update_settings', serialize($default_settings));
	}

	public function get_installed_themes(){
		if (!function_exists('wp_get_themes')) {
			include_once ABSPATH . 'wp-includes/theme.php';
		}
		$all_themes = wp_get_themes();
		$themes = array();
		$auto_updater_settings = $this->get_auto_update_settings();
		$included_themes = empty($auto_updater_settings['themes']['included']) ? array() : unserialize($auto_updater_settings['themes']['included']);

		$i=0;
		foreach ($all_themes as $slug => $theme) {
			// $themes[$i]['unselectable'] = $this->update_common->is_free_theme($slug) ? false : true;
			if(!$this->update_common->is_free_theme($slug)){
				//we are not supporting paid themes for AU
				continue;
			}
			$themes[$i]['slug'] = $slug;
			$themes[$i]['name'] = $theme->get('Name');
			$themes[$i]['selected'] = (in_array($slug, $included_themes)) ?  true : false;
			$i++;
		}
		return $this->fancytree_format($themes, 'themes');
	}

	public function get_installed_plugins(){
		if (!function_exists('get_plugins')) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all_plugins = get_plugins();
		$plugins = array();
		$auto_updater_settings = $this->get_auto_update_settings();
		$included_plugins = empty($auto_updater_settings['plugins']['included']) ? array() : unserialize($auto_updater_settings['plugins']['included']);

		$i=0;
		foreach ($all_plugins as $slug => $plugin) {
			// $plugins[$i]['unselectable'] = $this->update_common->is_free_plugin($slug) ? false : true;
			if (!$this->update_common->is_free_plugin($slug)) {
				//we are not supporing paid plugins
				continue;
			}
			$plugins[$i]['slug'] = $slug;
			$plugins[$i]['name'] = $plugin['Name'];
			$plugins[$i]['selected'] = (in_array($slug, $included_plugins)) ?  true : false;
			$i++;
		}
		return $this->fancytree_format($plugins, 'plugins');
	}

	public function is_allowed_to_auto_update($update_details){
		$type = $this->parse_update_type_details($update_details);
		dark_debug($type, '---------$type------------');
		switch ($type) {
			case 'plugin':
				return $this->check_if_included_plugin($update_details);
			case 'theme':
				return $this->check_if_included_theme($update_details);
			case 'translation':
				return $this->check_if_included_translation($update_details);
			case 'core':
				return $this->check_if_included_core($update_details);
			default:
				return false;
		}
	}

	public function parse_update_type_details($update_details){
		dark_debug($update_details, '---------$update_details------------');
		$array = get_object_vars($update_details);
		$object_properties = array_keys($array);
		dark_debug($object_properties, '---------$object_properties------------');
		if (in_array('plugin', $object_properties)) {
			return 'plugin';
		}

		if (in_array('theme', $object_properties)) {
			return 'theme';
		}

		if (in_array('language', $object_properties)) {
			return 'translation';
		}

		return 'core';
	}

	public function check_if_included_plugin($update_details, $save = false){
		dark_debug(array(), '---------check_if_included_plugin------------');
		$auto_updater_settings = $this->get_auto_update_settings();
		if (!$auto_updater_settings['plugins']['status']) {
			dark_debug(array(), '---------Plugin update is off------------');
			return false;
		}
		$included_plugins = empty($auto_updater_settings['plugins']['included']) ? array() : unserialize($auto_updater_settings['plugins']['included']);
		dark_debug($included_plugins, '---------$included_plugins------------');
		dark_debug($update_details->plugin, '---------Plugin name------------');
		if (empty($included_plugins) || empty($update_details->plugin)) {
			dark_debug(array(), '---------Plugin selected or name is empty------------');
			return false;
		}

		$plugins_data = get_plugins();
		if(version_compare($update_details->new_version, $plugins_data[$update_details->plugin]['Version'])  === 0){
			dark_debug(array(), '--------Current and new version are same so rejecting backup------------');
			return false;
		}

		if (in_array($update_details->plugin, $included_plugins)) {
			dark_debug(array(), '---------Plugin is included------------');
			if ($save) {
				$this->config->set_option('auto_update_queue',
								serialize(
									array(
										'item_type' => 'plugin',
										'update_type' => 'autoupdate',
										'item' => purify_plugin_update_data_wptc(
											array($update_details->plugin)
											)
										)
									)
								);
			}
			return true;
		}
		dark_debug(array(), '---------Plugin is not included------------');
		return false;
	}

	public function check_if_included_theme($update_details, $save = false){
		$auto_updater_settings = $this->get_auto_update_settings();
		if (!$auto_updater_settings['themes']['status']) {
			dark_debug(array(), '---------themes update is off------------');
			return false;
		}
		$included_themes = empty($auto_updater_settings['themes']['included']) ? array() : unserialize($auto_updater_settings['themes']['included']);
		dark_debug($included_themes, '---------$included_Theme------------');
		// dark_debug($update_details->theme, '---------Theme name------------');
		if (empty($included_themes) || empty($update_details->theme)) {
			dark_debug(array(), '---------themes selected or name is empty------------');
			return false;
		}

		$theme_info = wp_get_theme($update_details->theme);
		if(version_compare($update_details->new_version, $theme_info->get( 'Version' ))  === 0){
			dark_debug(array(), '--------Current and new version are same so rejecting backup------------');
			return false;
		}

		if (in_array($update_details->theme, $included_themes)) {
			dark_debug(array(), '---------Theme is included------------');
			if ($save) {
				$this->config->set_option('auto_update_queue',
								serialize(
									array(
										'item_type' => 'theme',
										'update_type' => 'autoupdate',
										'item' => purify_theme_update_data_wptc(
											array($update_details->theme)
											)
										)
									)
								);
			}
			return true;
		}
		dark_debug(array(), '---------Theme is not included------------');
		return false;
	}

	public function check_if_included_translation($update_details = false, $save = false){
		if ($save) {
			$this->config->set_option('auto_update_queue',
								serialize(
									array(
										'item_type' => 'translation',
										'update_type' => 'autoupdate',
										'item' => purify_translation_update_data_wptc(
											true
											)
										)
									)
								);
		}
		return true;
	}

	public function check_if_included_core($update_details, $save = false){
		global $wp_version;
		if ((!empty($update_details->response) && $update_details->response === 'development' )){
			dark_debug(array(), '---------development version so return false-----------');
			return false;
		}

		if(empty($update_details->download)) {
			dark_debug(array(), "------download link not available so return false--------");
			return false;
		}

		$offered_ver = $update_details->current;
		$current_version = implode( '.', array_slice( preg_split( '/[.-]/', $wp_version  ), 0, 2 ) ); // x.y
		$new_version     = implode( '.', array_slice( preg_split( '/[.-]/', $offered_ver ), 0, 2 ) ); // x.y

		$auto_updater_settings = $this->get_auto_update_settings();

		//Minor version updates
		if ( $current_version == $new_version ) {
			dark_debug(array(), '---------Minor version------------');
			if ($auto_updater_settings['core']['minor']['status']) {
				if ($save) {
					$this->config->set_option('auto_update_queue',
								serialize(
									array(
										'item_type' => 'core',
										'update_type' => 'autoupdate',
										'item' => purify_core_update_data_wptc(array($offered_ver))
										)
									)
								);
				}
				return true;
			}
		}

		// Major version updates (3.7.0 -> 3.8.0 -> 3.9.1)
		if ( version_compare( $new_version, $current_version, '>' ) ) {
			dark_debug(array(), '---------Major version------------');
			if ($auto_updater_settings['core']['major']['status']) {
				$this->config->set_option('auto_update_queue',
								serialize(
									array(
										'item_type' => 'core',
										'update_type' => 'autoupdate',
										'item' => purify_core_update_data_wptc(array($offered_ver))
										)
									)
								);
				return true;
			}
		}
	}


	public function add_auto_update_queue($update_details) {
		dark_debug('Function :','---------'.__FUNCTION__.'-----------------');

		$type = $this->parse_update_type_details($update_details);
		dark_debug($type, '---------$type------------');
		switch ($type) {
			case 'plugin':
				if($this->check_if_included_plugin($update_details, $save = true));
			case 'theme':
				return $this->check_if_included_theme($update_details, $save = true);
			case 'translation':
				return $this->check_if_included_translation($update_details, $save = true);
			case 'core':
				return $this->check_if_included_core($update_details, $save = true);
			default:
				return false;
		}
	}

	public function fancytree_format($data, $type){
		$format_result = array();
		foreach ($data as $key => $item) {
			$format_result[] = array(
				'title' => $item['name'],
				'key' => $item['slug'],
				'preselected' => $item['selected'],
				'unselectable' => $item['unselectable'],
			);
		}
		return $format_result;
	}

	public function is_backup_required_before_auto_update(){
		$settings = $this->get_auto_update_settings();
		if ($settings['status'] === 'yes') {
			return true;
		}
		return false;
	}

	public function turn_off_auto_update(){
		$settings_serialized = $this->config->get_option('wptc_auto_update_settings');
		if (empty($settings_serialized)) {
			return false;
		}

		$settings = unserialize($settings_serialized);
		$settings['update_settings']['status'] = 'no';
		$this->config->set_option('wptc_auto_update_settings', serialize($settings));
		dark_debug(array(), '---------Turned off auto update------------');
	}

	public function disable_theme_updates(){
		$settings_serialized = $this->config->get_option('wptc_auto_update_settings');

		if (empty($settings_serialized)) {
			return false;
		}

		$settings = unserialize($settings_serialized);
		$settings['update_settings']['themes']['status'] = false;
		$this->config->set_option('wptc_auto_update_settings', serialize($settings));
		dark_debug(array(), '---------Turned off auto update for themes------------');
	}

	public function exclude_paid_plugin_from_au(){
		$settings_serialized = $this->config->get_option('wptc_auto_update_settings');

		if (empty($settings_serialized)) {
			return false;
		}

		$settings = unserialize($settings_serialized);
		$included_plugins = empty($settings['update_settings']['plugins']['included']) ? array() : unserialize($settings['update_settings']['plugins']['included']);

		if (empty($included_plugins)) {
			return false;
		}

		$new_included_plugins = array();
		foreach ($included_plugins as $slug) {
			if ($this->update_common->is_free_plugin($slug)) {
				$new_included_plugins[] = $slug;
			}
		}

		$settings['update_settings']['plugins']['included'] = serialize($new_included_plugins);
		$this->config->set_option('wptc_auto_update_settings', serialize($settings));
		dark_debug(array(), '---------Excluded paid plugins from the AU------------');
	}
}