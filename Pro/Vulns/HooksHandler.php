<?php

class Wptc_Vulns_Hooks_Hanlder extends Wptc_Base_Hooks_Handler {
	protected $vulns;
	protected $config;
	public function __construct() {
		$this->vulns = WPTC_Base_Factory::get('Wptc_Vulns');
		$this->config = WPTC_Pro_Factory::get('Wptc_Vulns_Config');
	}

	public function run_vulns_check(){
		$this->vulns->run_vulns_check();
	}

	public function page_settings_tab($tabs){
		$tabs['vulns'] = __( 'Vulnerability Updates', 'wp-time-capsule' );
		return $tabs;
	}

	public function page_settings_content($more_tables_div, $dets1 = null, $dets2 = null, $dets3 = null) {

		$current_setting = $this->config->get_option('is_autoupdate_vulns_settings_enabled');
		dark_debug($current_setting, '--------$current_setting--------');
		$vulns_status = ($current_setting) ? 'checked="checked"' : '';

		$more_tables_div .= '
		<div class="table ui-tabs-hide" id="wp-time-capsule-tab-vulns"> <p></p>
			<table class="form-table">
				<tr>
					<th scope="row"> '.__( 'Vulnerability updates', 'wp-time-capsule' ).'
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">Vulnerability Updates</legend>
							<input type="checkbox" id="enable_vulns_settings_wptc" name="enable_vulns_settings_wptc" value="1" '.$vulns_status.'>
							<label for="enable_vulns_settings_wptc">'.__( 'Send email', 'wp-time-capsule' ).'</label>
							<p class="description">'.__( 'Plugins and WordPress core updates will be automatically updated. If you have enabled the auto updates in Backup Before Update settings.', 'wp-time-capsule' ).'</p>
						</fieldset>
					</td>
				</tr>';
		$more_tables_div .= '</table>
		</div>';
		return $more_tables_div;
	}

	public function save_vulns_settings(){
		$data = isset($_POST['data']) ? $_POST['data'] : array() ;
		return $this->vulns->save_vulns_settings($data);
	}

	public function get_vulns_settings(){
		return $this->vulns->get_vulns_settings();
	}
}