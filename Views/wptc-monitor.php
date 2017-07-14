<?php
/**
* A class with functions the perform a backup of WordPress
*
* @copyright Copyright (C) 2011-2014 Awesoft Pty. Ltd. All rights reserved.
* @author Michael De Wildt (http://www.mikeyd.com.au/)
* @license This program is free software; you can redistribute it and/or modify
*          it under the terms of the GNU General Public License as published by
*          the Free Software Foundation; either version 2 of the License, or
*          (at your option) any later version.
*
*          This program is distributed in the hope that it will be useful,
*          but WITHOUT ANY WARRANTY; without even the implied warranty of
*          MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*          GNU General Public License for more details.
*
*          You should have received a copy of the GNU General Public License
*          along with this program; if not, write to the Free Software
*          Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110, USA.
*/

try {
	$config = WPTC_Factory::get('config');
	$backup = new WPTC_BackupController();
	$config->create_dump_dir(); //creating backup folder in the beginning if its not there
	$schedule_backup = $config->get_option('schedule_backup');
	$auto_backup_main = $config->get_option('wptc_main_cycle_running');
	$auto_backup_sub = $config->get_option('wptc_sub_cycle_running');

	//Checking fresh backup
	global $wpdb;
	$fcount = $wpdb->get_results('SELECT COUNT(*) as files FROM ' . $wpdb->base_prefix . 'wptc_processed_files');
	$fresh = (!($fcount[0]->files > 0)) ? 'yes' : 'no';

	if (array_key_exists('stop_backup', $_POST)) {
		check_admin_referer('wordpress_time_capsule_monitor_stop');
		$backup->stop();
		add_settings_error('wptc_monitor', 'backup_stopped', __('Backup stopped.', 'wptc'), 'updated');
	} else if (array_key_exists('start_backup', $_POST)) {
		if (!$config->get_option('in_progress')) {
			check_admin_referer('wordpress_time_capsule_monitor_stop');
			$backup->backup_now();
			$started = true;
			add_settings_error('wptc_monitor', 'backup_started', __('Backup started.', 'wptc'), 'updated');
		}
	}

	//Initial backup option
	$freshbackupPopUp = false;
	if (isset($_GET['action'])) {
		if ($_GET['action'] == 'initial_setup') {
			$initial_setup = true;
		}
	}
	if (isset($_GET['oauth_token'])) {
		$initial_setup = true;
	}
	if ($fresh == 'yes' && isset($initial_setup) && $initial_setup === true) {
		$freshbackupPopUp = true;
	}
	$start_backup_from_settings = false;
	if (isset($_GET['start_backup_from_settings'])) {
		$start_backup_from_settings = true;
	}
	?>

<link href='<?php echo $uri; ?>/fullcalendar-2.0.2/fullcalendar.css?v=<?php echo WPTC_VERSION; ?>' rel='stylesheet' />
<link href='<?php echo $uri; ?>/fullcalendar-2.0.2/fullcalendar.print.css?v=<?php echo WPTC_VERSION; ?>' rel='stylesheet' media='print' />
<link href='<?php echo $uri; ?>/tc-ui.css?v=<?php echo WPTC_VERSION; ?>' rel='stylesheet' />
<script src='<?php echo $uri; ?>/fullcalendar-2.0.2/lib/moment.min.js?v=<?php echo WPTC_VERSION; ?>'></script>
<script src='<?php echo $uri; ?>/fullcalendar-2.0.2/fullcalendar.js?v=<?php echo WPTC_VERSION; ?>'></script>

<?php add_thickbox();?>

<div class="wrap" id="wptc">
	<?php settings_errors();?>

	<form id="backup_to_dropbox_options" name="backup_to_dropbox_options" action="<?php echo network_admin_url("admin.php?page=wp-time-capsule-monitor"); ?>"  method="post" style=" width: 100%;">
			<h2 style="width: 195px; display: inline;"><?php _e('Backups', 'wptc');?>
			<div class="bp-progress-calender" style="display: none">
				<div class="l1 wptc_prog_wrap">
					<div class="bp_progress_bar_cont">
						<span id="bp_progress_bar_note"></span>
						<div class="bp_progress_bar" style="width:0%"></div>
					</div>
					<span class="rounded-rectangle-box-wptc reload-image-wptc" id="refresh-c-status-area-wtpc"></span><div class="last-c-sync-wptc">Last reload: - </div>
				</div>
			</div>
			</h2>  <?php wp_nonce_field('wordpress_time_capsule_monitor_stop');?>
			<div class="top-links-wptc">
				<a style="position: absolute;right: 5px;top: 10px;" href="https://wptimecapsule.uservoice.com/" target="_blank">Suggest a Feature</a>
				<a style="position: absolute;right: 130px;top: 10px;" href="http://wptc.helpscoutdocs.com/article/7-commonly-asked-questions" target="_blank">Help</a>
				<?php if (defined('WPTC_ENV') && WPTC_ENV != 'production'): ?>
					<a style="position: absolute;right: 170px;top: 10px;display: none;" class="restore_err_demo_wptc">Restore error demo</a>
				<?php endif;?>
				<!--<a class="dashicons-before dashicons-backup" id="report_issue" style="float: right; color: rgb(196, 63, 63); font-style: italic; text-decoration: none; padding: 5px; border-radius: 25px;" href="#">Report issue</a>-->
			</div>
	</form>

	<div id="progress">
		<div class="loading"><?php _e('Loading...')?></div>
	</div>
</div>

<?php
} catch (Exception $e) {
	echo '<h3>Error</h3>';
	echo '<p>' . __('There was a fatal error loading WordPress Time Capsule. Please fix the problems listed and reload the page.', 'wptc') . '</h3>';
	echo '<p>' . __('If the problem persists please re-install WordPress Time Capsule.', 'wptc') . '</h3>';
	echo '<p><strong>' . __('Error message:') . '</strong> ' . $e->getMessage() . '</p>';
}
?>

<script type="text/javascript" language="javascript">
	//initiating Global Variables here

	var sitenameWPTC = '<?php echo get_bloginfo('name'); ?>';
	freshBackupWptc = '<?php echo $fresh; ?>';
	var startBackupFromSettingsWPTC = '<?php echo $start_backup_from_settings; ?>';
	var bp_in_progress = false;
	var wp_base_prefix_wptc = '<?php global $wpdb;
echo $wpdb->base_prefix;?>';		//am sending the prefix ; since it is a bridge
	var this_home_url_wptc = '<?php echo site_url(); ?>' ;

	var defaultDateWPTC = '<?php echo date('Y-m-d', microtime(true)) ?>' ;
	var wptcOptionsPageURl = '<?php echo plugins_url('wp-time-capsule'); ?>' ;
	var this_plugin_url_wptc = '<?php echo plugins_url(); ?>' ;
	var wptcMonitorPageURl = '<?php echo network_admin_url('admin.php?page=wp-time-capsule-monitor'); ?>';
	var wptcPluginURl = '<?php echo plugins_url() . '/' . WPTC_TC_PLUGIN_NAME; ?>';

	var freshbackupPopUpWPTC = '<?php echo ($freshbackupPopUp) ? $freshbackupPopUp : false;?>';

	var on_going_restore_process = false;
	var cuurent_bridge_file_name = seperate_bridge_call = '';

</script>

<?php
wp_enqueue_script('wptc-monitor', plugins_url() . '/' . WPTC_TC_PLUGIN_NAME . '/Views/wptc-monitor.js', array(), WPTC_VERSION);
