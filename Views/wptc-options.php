<?php
/**
 * This file contains the contents of the Dropbox admin options page.
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

include_once dirname(__FILE__) . '/wptc-plans.php';
include_once dirname(__FILE__) . '/wptc-options-helper.php';

$options_helper = new Wptc_Options_Helper();
$wptc_settings = WPTC_Base_Factory::get('Wptc_Settings');
$initial_setup = WPTC_Base_Factory::get('Wptc_InitialSetup');
// $initial_setup->load_page();

try {
	if ($errors = get_option('wptc-init-errors')) {
		delete_option('wptc-init-errors');
		throw new Exception(__('WordPress Time Capsule failed to initialize due to these database errors.', 'wptc') . '<br /><br />' . $errors);
	}

	$config = WPTC_Factory::get('config');
	$initial_setup->process_GET_request_wptc();

	$dropbox = WPTC_Factory::get(DEFAULT_REPO);

	$is_user_logged_in_var = $config->get_option('is_user_logged_in');
	$main_account_email_var = $config->get_option('main_account_email');

	$backup = new WPTC_BackupController();

	$config->create_dump_dir();

	$tcStartBackupNow = false;

	$fresh = $initial_setup->is_fresh_backup();

	$disable_backup_now = $config->get_option('in_progress');

	if (array_key_exists('unlink', $_POST)) {
		check_admin_referer('wordpress_time_capsule_options_save');
		$backup->unlink_current_acc_and_backups();
		$dropbox->unlink_account()->init();
	} elseif (array_key_exists('clear_history', $_POST)) {
		check_admin_referer('wordpress_time_capsule_options_save');
		$config->clear_history();
	} else if (isset($_GET['new_backup'])) {
		$tcStartBackupNow = true;
		$config->set_option('starting_first_backup', true);
		$config->set_option('first_backup_started_atleast_once', true);
		$config->set_main_cycle_time();
		if (DEFAULT_REPO != $config->get_option('default_repo_history')) {
			$config->set_option('default_repo_history', DEFAULT_REPO);
			$backup->clear_prev_repo_backup_files_record();
		}
	}

	list($unixtime, $frequency) = $config->get_schedule();
	if (!$frequency) {
		$frequency = 'weekly';
	}

	if (!get_settings_errors('wptc_options')) {
		$dropbox_location = $config->get_option('dropbox_location');
	}

	$time = date('H:i', $unixtime);
	$day = date('D', $unixtime);
	add_thickbox();

	//getting schedule options
	$schedule_backup = $config->get_option('schedule_backup');
	$auto_backup = $config->get_option('auto_backup_switch');
	$schedule_interval = $config->get_option('schedule_interval');
	$schedule_day = $config->get_option('schedule_day');
	$schedule_time_str = $config->get_option('schedule_time_str');
	$wptc_timezone = $config->get_option('wptc_timezone');
	$hightlight = '';
	if (isset($_GET['highlight'])) {
		$hightlight = $_GET['highlight'];
	}

	?>

	<link rel="stylesheet" type="text/css" href="<?php echo $uri ?>/wptc-dialog.css"/>
	<link rel="stylesheet" type="text/css" href="<?php echo $uri ?>/wptc-plans.css"/>

	<link rel="stylesheet" type="text/css" href="<?php echo $uri ?>/JQueryFileTree/jqueryFileTree.css"/>
	<script src="<?php echo $uri ?>/JQueryFileTree/jqueryFileTree.js" type="text/javascript" language="javascript"></script>
	<script src="<?php echo $uri ?>/treeView/jquery-ui.custom.js" type="text/javascript" language="javascript"></script>
	<link rel="stylesheet" type="text/css" href="<?php echo $uri ?>/treeView/skin/ui.fancytree.css"/>
	<script src="<?php echo $uri ?>/treeView/jquery.fancytree.js" type="text/javascript" language="javascript"></script>

	<div class="wrap" id="wptc">


	<form id="backup_to_dropbox_options" name="backup_to_dropbox_options" action="<?php echo network_admin_url("admin.php?page=wp-time-capsule"); ?>" method="post">

	<?php

		echo $initial_setup->success_and_error_flaps($config);

		$is_error_empty = empty($_GET['error']);
		$is_user_logged_in = $config->get_option('is_user_logged_in');
		$default_repo_connected = $config->get_option('default_repo');
		$is_uuid = isset($_GET['uid']);
		$is_new_backup = isset($_GET['new_backup']);
		$show_connect_pane = isset($_GET['show_connect_pane']);
		$is_initial_setup = isset($_GET['initial_setup']);
		$is_cloud_auth_action = isset($_GET['cloud_auth_action']);
		$privileges_wptc = $options_helper->get_unserialized_privileges();
		if ($dropbox) {
			$is_auth = $dropbox->is_authorized();
		} else if(empty($dropbox) && DEFAULT_REPO === 'dropbox' ||  DEFAULT_REPO === 's3' ||  DEFAULT_REPO === 'g_drive')  {
			$is_auth = true;
		} else {
			$is_auth = false;
		}

		dark_debug($is_auth, '---------------$is_auth-----------------');
		dark_debug($is_user_logged_in, '---------------$is_user_logged_in-----------------');
		dark_debug($default_repo_connected, '---------------$default_repo_connected-----------------');
		if (	$is_error_empty &&
				$is_user_logged_in &&
				$default_repo_connected &&
				!$is_uuid &&
				!$is_new_backup &&
				!$show_connect_pane &&
				!$is_initial_setup &&
				!$is_cloud_auth_action &&
				($dropbox && $is_auth) ) {

		wptimecapsule_settings_hook();
	} else {
		$privileges_wptc = $options_helper->get_unserialized_privileges();
		$options_helper->reload_privileges_if_empty();

		$is_show_privilege_box = $options_helper->is_show_privilege_box();

		$options_helper->set_valid_user_but_no_plans_purchased(false);

		$is_show_login_box = $options_helper->is_show_login_box();

		$is_show_initial_setup = $options_helper->is_show_initial_setup();

		$is_show_connect_pane = $options_helper->is_show_connect_pane();

		$requirement_check = $initial_setup->requirement_check();

		if ($is_show_login_box) {
			if(!$requirement_check['overall_requirements_passed']){
				$login_title_label = 'Server requirements';
			} else {
				$login_title_label = 'LOGIN TO YOUR ACCOUNT';
			}
		} else {
			$login_title_label = 'Hi ' . $config->get_option('main_account_name') . ' :)';
		}

		if ($is_show_connect_pane) {
			$connect_pane_title_label = 'CONNECT YOUR STORAGE APP';
		} else {
			$connect_pane_title_label = DEFAULT_REPO_LABEL;
		}

		if (empty($connect_pane_title_label) || $connect_pane_title_label == 'Cloud') {
			$connect_pane_title_label = 'Connect your storage app';
		}
		?>
		<div class="pu_title">Welcome to WP Time Capsule</div>

		<div class="wptc_subtitle" style="text-align: center;">
			<div class="block lg <?php if ($is_show_login_box) {echo 'active';}
		?> "><?php echo $login_title_label; ?></div>
			<div class="block pln <?php if ($is_show_privilege_box) {echo 'active';} ?> ">Plans</div>
			<div class="block cn <?php if(isset($_GET['show_connect_pane']) && $_GET['show_connect_pane'] == 'set' || isset($_GET['not_approved']) || $is_show_connect_pane){echo 'active';}
		?> "><?php echo $connect_pane_title_label; ?></div>
			<div class="block fb <?php if (( !isset($_GET['show_connect_pane']) || $_GET['show_connect_pane'] != 'set' && !isset($_GET['not_approved'])) && (isset($_GET['uid']) && isset($_GET['oauth_token']) || (isset($_GET['code']) && $_GET['cloud_auth_action'] == 'g_drive') || isset($_GET['as3_bucket_region']))) {echo 'active';}
		?> "><?php if (isset($_GET['initial_setup'])) {echo "INITIAL SETUP";} else {echo "Initial setup";}?></div>
		<div class="block fb <?php if (isset($_GET['new_backup'])) {echo 'active';}
		?> "><?php if (isset($_GET['new_backup'])) {echo "TAKE FIRST BACKUP";} else {echo "Take first backup";}?></div>
		</div>


		<div class="wcard clearfix" style="width: 980px;">
			<?php if ($is_show_login_box) {
					if (!$requirement_check['overall_requirements_passed']) { ?>
						<br>
						<p class="description"><?php _e( 'Your server does not meet minimum requirements of WP Time Capsule', 'wp-time-capsule' ); ?></p>
						<br>
						<table class="widefat striped">
							<thead>
								<tr class="thead">
								<th>Server Configuration</th>
								<th>Minimum</th>
								<th>Suggestion</th>
								<th>Value</th>
								<th style="width: 60px;">Status</th>
								</tr>
							</thead>
							<tbody>
								<?php
								unset($requirement_check['overall_requirements_passed']);
								foreach ($requirement_check as $key => $data) {
									echo '<tr class="entry-row">
										<td>'.$data['title'].'</td>
										<td>'.$data['min'].'</td>
										<td>'.$data['suggestion'].'</td>
										<td>'.$data['value'].'</td>';
									if ($data['status']) {
										echo '<td><span class="wptc_label wptc_label-success">Pass</span> </td>';
									} else {
										echo '<td><span class="wptc_label wptc_label-error">Fail</span> </td>';
									}

									echo "</tr>";
								}

								?>
							</tbody>
						</table>
						</div>
					<?php } else { ?>

					<form id="wptc_main_acc_login"  action="<?php echo network_admin_url("admin.php?page=wp-time-capsule-settings"); ?>" name="wptc_main_acc_login" method="post">
						<div class="l1 wptc_login_msg_div <?php if (!isset($_GET['error'])) {echo 'active';} ?> ">Login to your WP Time Capsule account below</div>
						<div class="l1 wptc_error_div  <?php if (isset($_GET['error'])) {echo 'active';} ?> "><?php
							echo $config->get_last_login_error_msg();
							$config->set_option('main_account_login_last_error', false);?>
						</div>
						<div class="l1"  style="padding: 0px;">
							<input type="text" id="wptc_main_acc_email" name="wptc_main_acc_email" placeholder="Email" autofocus>
						</div>
						<div class="l1"  style="padding: 0px; position: relative;">
							<input type="password" id="wptc_main_acc_pwd" name="wptc_main_acc_pwd" placeholder="Password" >
							<a href=<?php echo WPTC_APSERVER_URL_FORGET; ?> target="_blank" class="forgot_password">Forgot?</a>
						</div>
						<input type="submit" name="wptc_login" id="wptc_login" class="btn_pri" value="Login" />
						<div style="clear:both"></div>
						<div id="mess" class="wptc_signup_link_div">Dont have an account yet?
							<a href=<?php echo WPTC_APSERVER_URL_SIGNUP; ?> target="_blank" >Signup Now</a>
						</div>
					</form> <?php } ?>
			<?php } elseif ($is_show_privilege_box) {
					$plans_obj = new Wptc_Plans();
					echo $plans_obj->echo_plan_box_div_wptc();

				  } else if (isset($_GET['new_backup'])) {
			do_action('starting_fresh_new_backup_pre_wptc_h', '');
			do_action('send_basic_analytics', time());

			$initial_setup->record_signed_in_repos($dropbox);
			do_action('send_backups_data_to_server_wptc', time());
			?>
					<div class="l1"  style="padding-bottom: 10px;">We will now backup your website to your <?php echo DEFAULT_REPO_LABEL; ?> account. This being the first backup may take hours or days depending on the size of your website.  That's because, we don't zip your backups thus giving your server more space to breathe. The next set of incremental backups will hardly take a few minutes - <a href="http://docs.wptimecapsule.com/article/15-why-does-your-first-backup-take-too-long-to-complete" target="_blank">Know more</a>.</div>
					<div class="l1 wptc_prog_wrap bp-progress-first-bp"></div>
					<div class="l1"  style="padding-bottom: 10px;">You can close the backup window and take care of your errands, we will e-mail you once the backup is completed. In case you want to check the backup status of all your sites, <a href="https://service.wptimecapsule.com/" style="text-decoration:underline; cursor:pointer" target="_blank">click here.</a></div>
				<?php	} else if (
						(isset($_GET['cloud_auth_action']) &&
						($_GET['cloud_auth_action'] == 'g_drive' || $_GET['cloud_auth_action'] == 'dropbox') &&
						isset($_GET['code']) &&
						!isset($_GET['error']) ||
						isset($_GET['uid']) ||
						isset($_GET['as3_access_key'])) &&
						(DEFAULT_REPO_LABEL != 'Cloud') &&
						!isset($_GET['show_connect_pane'])
					) {
					$initial_setup->store_cloud_access_token_wptc();
					?>
					<table style="width: 770px; margin-left: auto; margin-right: auto;">

						<!--- Choose backup type -->
						<tr style="display: none">
							<td>
								<div class="l1"  style="padding-bottom: 10px;">Backup Type <?php echo $initial_setup->get_select_backup_type_setting($config); ?></div>
							</td>
						</tr>

						<!--- Choose Scheduled time and timezone -->
						<tr>
							<td>
								<div class="l1"  style="padding-bottom: 10px;"><span class="init_backup_time_n_zone">Backup Schedule and Timezone</span><select name="select_wptc_default_schedule" id="select_wptc_default_schedule" style="margin-left:4px"> <?php echo $wptc_settings->get_schedule_times_div_wptc(); ?>
									</select>
									<select id="wptc_timezone" name="wptc_timezone"><?php echo $wptc_settings->get_all_timezone_html(); ?></select>								</div>
								<p <?php echo WPTC_Base_Factory::get('Wptc_App_Functions')->is_free_user_wptc() ? "style='display: block; text-align: center;'" : "style='display: none; text-align: center;'"  ?>  class="description"><?php  esc_attr_e('(Note: Sheduled backup will happen every 7 days once.)', 'wp-time-capsule' ); ?></p>
							</td>
						</tr>

						<!--- Inc and Exc title-->
						<tr>
							<td>
								<div  class="l1" style="top: 0px;position: relative;padding-bottom: 10px;" >
									<a id="show_file_db_exp_for_exc" style="position: absolute;top: 19px;cursor: pointer;right: 290px;"> Include/exclude content &#9660;</a>
								</div>
							</td>
						</tr>

						<!--- Inc and Exc content-->
						<tr style="display:none" id="file_db_exp_for_exc_view">
							<td >
								<fieldset style="float: left; margin-top: 20px">
									<button class="button button-secondary wptc_dropdown" id="wptc_init_toggle_files" style="width: 408px; outline:none; text-align: left;">
										<span style="left: 21px; position: relative;">Folders &amp; Files </span>
										<span class="dashicons dashicons-portfolio" style="position: relative;right: 95px;top: 3px;"></span>
										<span class="dashicons dashicons-arrow-down" style="position: relative; top: 2px; left: 255px;"></span>
									</button>
									<div style="display:none" id="wptc_exc_files"></div>
								</fieldset>
								<fieldset style="position: relative; margin-top: 20px">
									<div style="position: relative; top: 0px;left: 30px;" id="wptc_init_table_div">
										<button class="button button-secondary wptc_dropdown" id="wptc_init_toggle_tables" style="width: 408px; outline:none; text-align: left;">
											<span style="left: 21px; position: relative;">Database</span>
											<span class="dashicons dashicons-menu" style="position: relative;right: 65px;top: 3px;"></span>
											<span class="dashicons dashicons-arrow-down" style="position: relative; top: 2px; left: 283px;"></span>
										</button>
										<div style="display:none" id="wptc_exc_db_files"></div>
									</div>
								</fieldset>
							</td>
						</tr>

						<!--- Exclude extension title-->
						<tr class="view-user-exc-extensions" style="display: none">
							<td>
								<div class="l1"  style="padding-bottom: 10px;">Enter file extensions to exclude</div>
							</td>
						</tr>

						<!--- Exclude extension content-->
						<tr class="view-user-exc-extensions" style="display: none">
							<td>
							<?php	$user_excluded_extenstions = $config->get_option('user_excluded_extenstions');?>
							<input type="text" name="user_excluded_extenstions" placeholder="Eg. .mp4, .mov"  style="width: 42%;margin-left: 220px;" value="<?php echo $user_excluded_extenstions; ?>" >
							</td>
						</tr>

						<!--- Skip button -->
						<tr>
							<td>
								<input type="button" id="skip_initial_set_up" class="btn_pri" style="margin: 50px 140px 30px;width: 240px;text-align: center;display: block;position: relative;top: 13px;left: 0px;background: #999;border-color: #fff;color: #FFF;" value="I'll do it later">
								<input type="button" id="continue_wptc" class="btn_pri" style="width: 240px;text-align: center;display: block;position: relative;top: -57px;left: 393px;" value="Save and continue">
							</td>
						</tr>

						<!--- Save button-->
						<tr>
							<td>
								<div class="dashicons-before dashicons-warning" id="donot_touch_note" style="font-size: 12px;font-style: italic;"><span>You can do this setup anytime under WP Time Capsule -&gt; Settings</span>
							</td>
						</tr>

						<!--- Notes-->
						<tr>
							<td>
								<div class="dashicons-before dashicons-warning" id="donot_touch_note" style="font-size: 12px;font-style: italic;"><span >Please do not modify the files backed up on the <span id="donot_touch_note_cloud"><?php echo DEFAULT_REPO_LABEL; ?></span> as it will cause problems during restore. </span></div></div>
							</td>
						</tr>

				</table>

				<?php	}
		else { ?>

				<div class="l1"  style="padding-bottom: 10px;">The backup of this website will be stored in a folder in your <?php echo DEFAULT_REPO_LABEL; ?> app</div>
				<form id="backup_to_dropbox_continue" name="backup_to_dropbox_continue" method="post">
					<?php echo $initial_setup->get_select_cloud_dialog_div(); ?>
				</form>
				<div class="l1 wptc_error_div " style="<?php if (isset($_GET['error']) && !empty($_GET['error'])) {echo "display:block;";} else {echo "display:none;";}
			?>"><?php $last_error_tmp = $config->get_option('last_cloud_error');
			if (empty($last_error_tmp)) {
				echo "Oops. Unable to connect to Cloud. Please check your credentials and try again.";
			} else {
				echo $last_error_tmp;
			}
			$config->set_option('last_cloud_error', false);
			?></div>
			<?php }
	}
} catch (Exception $e) {
	echo '<h3>Error</h3>';
	echo '<p>' . __('There was a fatal error loading WordPress Time Capsule. Please fix the problems listed and reload the page.', 'wptc') . '</h3>';
	echo '<p>' . __('If the problem persists please re-install WordPress Time Capsule.', 'wptc') . '</h3>';
	echo '<p><strong>' . __('Error message:') . '</strong> ' . $e->getMessage() . '</p>';

	dark_debug($e, "--------e errors--------");

}
?>
</div>
<div id="wptc-content-id" style="display:none;"> <p> This is my hidden content! It will appear in ThickBox when the link is clicked. </p></div>
<a style="display:none" href="#TB_inline?width=600&height=550&inlineId=wptc-content-id" class="thickbox wptc-thickbox">View my inline content!</a>
</div>
<script type="text/javascript" language="javascript">

	jQuery(document).ready(function ($) {
		adminUrlWptc = '<?php echo network_admin_url(); ?>';
		freshBackupWptc = '<?php echo $fresh; ?>';
		check_cloud_min_php_min_req = '<?php echo $initial_setup->check_cloud_min_php_min_req(); ?>';
		var tcStartBackupNow = '<?php echo $tcStartBackupNow; ?>';
		var cur_backup_type = $("#backup_type").val();

		if (cur_backup_type == 'WEEKLYBACKUP' || cur_backup_type == 'AUTOBACKUP') {
			jQuery('#select_wptc_default_schedule').hide();
			jQuery('.init_backup_time_n_zone').html('Timezone');
		}

		if(tcStartBackupNow){
			wtc_start_backup_func('');
		}

		jQuery("#select_wptc_default_repo").on('change', function(){
			var newDefaultRepo = '';
			newDefaultRepo = jQuery(this).val();
			if(!newDefaultRepo){
				return false;
			}
			jQuery.post(ajaxurl, { action : 'change_wptc_default_repo', new_default_repo: newDefaultRepo }, function(data) {
				if(typeof data.success != 'undefined'){
					parent.location.assign('<?php echo network_admin_url('admin.php?page=wp-time-capsule'); ?>');
				}
			});
		});

		jQuery('#continue_wptc').click(function(){
			jQuery(this).attr('disabled', 'disabled').addClass('disabled').val('Saving...');
			continue_wptc_obj = this;
			var schedule_time = jQuery( "#select_wptc_default_schedule option:selected" ).val();
			var timezone = jQuery( "#wptc_timezone option:selected" ).val();
			var exclude_extensions = jQuery("input[name='user_excluded_extenstions']").val()
			var backup_type = jQuery('#backup_type').val();
			jQuery.post(
				ajaxurl,{
					action : 'save_initial_setup_data_wptc',
					data : {
						schedule_time : schedule_time,
						timezone:timezone,
						exclude_extensions:exclude_extensions,
						backup_type:backup_type,
					},
				}, function(data) {
					jQuery(continue_wptc_obj).removeClass('disabled').val('Saved');
					parent.location.assign('<?php echo network_admin_url('admin.php?page=wp-time-capsule&new_backup=set'); ?>');
				});
		});

		jQuery('#skip_initial_set_up').click(function(){
			parent.location.assign('<?php echo network_admin_url('admin.php?page=wp-time-capsule&new_backup=set'); ?>');
		});

		jQuery('#continue_to_initial_setup').click(function(){
			jQuery.post(ajaxurl, { action : 'continue_with_wtc' }, function(data) {
				if(data=='authorized'){
					parent.location.assign('<?php echo network_admin_url('admin.php?page=wp-time-capsule&initial_setup=set'); ?>');
				}
				else{
					var data_str = '';
					if(typeof data == 'string'){
						data_str = data;
					}
					parent.location.assign('<?php echo network_admin_url('admin.php?page=wp-time-capsule'); ?>&error='+data_str);;
				}
			});
		});

	});

</script>
<script type="text/javascript" language="javascript">
	var service_url_wptc = '<?php echo WPTC_APSERVER_URL;?>';
	var wptcOptionsPageURl = '<?php echo plugins_url('wp-time-capsule'); ?>' ;
</script>
<script src="<?php echo $uri ?>/Views/wptc-plans.js" type="text/javascript" language="javascript"></script>