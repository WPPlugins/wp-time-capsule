<?php
$config = WPTC_Factory::get('config');
if ($_POST['wptc_save_changes']) {
	$config->set_option('auto_backup_switch', $_POST['auto_backup_switch']);
	$config->set_option('auto_backup_interval', $_POST['auto_backup_interval']);
	$config->set_option('interval_changed', 'yes');
	$config->set_option('wptc_service_request', $_POST['wptc_service_request']);
	if ($_POST['wptc_service_request'] == 'no') {
		stop_wptc_server();
	}
	add_settings_error('general', 'settings_updated', __('Settings saved.'), 'updated');
	auto_backup_settings_changed($_POST);
}
$auto_backup_switch = $config->get_option('auto_backup_switch');
$wptc_service_request = $config->get_option('wptc_service_request');
$wptc_sign_up = $config->get_option('sign_up');
$schedule_backup = $config->get_option('schedule_backup');
add_thickbox();
?>
<script type="text/javascript">
    jQuery(document).ready(function(){

        jQuery('#auto_backup_switch').change(function(){
           if(jQuery("#auto_backup_switch option:selected").val()=='on'){
              jQuery('#sch_opt').show();
           }
           if(jQuery("#auto_backup_switch option:selected").val()=='off'){
               jQuery('#sch_opt').hide();
           }
        });

        jQuery("#auto_backup_switch").trigger('change');

        //Cron Service
		jQuery(".wptc_service_request").change(function(){
			var opt = jQuery(this).val();
			if(opt=='yes'){
				jQuery('#sign_up:not(.sdone)').show();
			}
			else{
				jQuery('#sign_up').hide();
			}
		});

		jQuery('#sign_up').click(function(){
		   jQuery('#load_strip').show();
		   jQuery.post(ajaxurl, { action : 'signup_service_wptc' }, function(data) {
			   jQuery('#load_strip').hide();
			   if(data=='success'){
				jQuery('#sign_up').addClass('sdone');
				jQuery('#sign_up').hide();
			   }
			});
			return false;
		});

		<?php	if ($wptc_service_request == 'yes') {
	if ($wptc_sign_up != 'done') {?>
					jQuery('#sign_up').show();
		<?php	}
} else {?>
				jQuery('#sign_up').hide();
        <?php }
?>
    });
</script>
<h2>Advanced Options <small>(Auto & Schedule Backup)</small></h2>


<div class="wrap" id="wptc">
    <form id="advanced_wtc_options" name="advanced_wtc_options" action="admin.php?page=wp-time-capsule-advanced" method="post">
        <?php settings_errors();?>
        <table class="form-table">
            <tbody>
                <tr valign="top">
                    <th scope="row">
                        <label for="auto_backup_switch">Auto Backup </label>
                    </th>
                    <td>
                        <select name="auto_backup_switch" id="auto_backup_switch" <?php if ($schedule_backup == 'on') {
	echo 'disabled';
}
?>>
                            <option <?php if ($config->get_option('auto_backup_switch') == 'on') {
	echo 'selected';
}
?> value="on">On</option>
                            <option <?php if ($config->get_option('auto_backup_switch') == 'off') {
	echo 'selected';
}
?> value="off">Off</option>
                        </select>
                         <?php if ($schedule_backup == 'on') {
	echo '<p class="description">Schedule backup is currently on.Please Turn off schedule backup for using Auto Backup<p>';
}
?>
                    </td>
                </tr>
		<tr valign="top" id="sch_opt">
                    <th scope="row">
                        <label for="auto_backup_interval">Time interval for Auto Backup</label>
                    </th>
                    <td>
                        <select name="auto_backup_interval" id="auto_backup_interval">
							<option <?php if ($config->get_option('auto_backup_interval') == 'every_min') {
	echo 'selected';
}
?> value="every_min">Every One Minute</option>
                            <option <?php if ($config->get_option('auto_backup_interval') == 'every_ten') {
	echo 'selected';
}
?> value="every_ten">Every Ten Minutes</option>
                            <option <?php if ($config->get_option('auto_backup_interval') == 'every_twenty') {
	echo 'selected';
}
?> value="every_twenty">Every Twenty Minutes</option>
                            <option <?php if ($config->get_option('auto_backup_interval') == 'every_hour') {
	echo 'selected';
}
?> value="every_hour">Every hour</option>
                            <option <?php if (($config->get_option('auto_backup_interval') == 'every_four') || ($config->get_option('auto_backup_interval') == '')) {echo 'selected';}
?> value="every_four">Every four hours</option>
                            <option <?php if ($config->get_option('auto_backup_interval') == 'every_six') {
	echo 'selected';
}
?> value="every_six">Every six hours</option>
                            <option <?php if ($config->get_option('auto_backup_interval') == 'every_eight') {
	echo 'selected';
}
?> value="every_eight">Every eight hours</option>
                        </select>
                        <p class="description"></p>
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="submit">
            <input type="submit" id="wptc_save_changes" name="wptc_save_changes" class="button-primary" value="<?php _e('Save Changes', 'wptc');?>">
        </p>
    </form>



<style>
/*    .subsubsub{
        background: none repeat scroll 0% 0% rgba(0, 0, 0, 0.1);
    }
    .subsubsub li a{
        position: relative;
        -webkit-transition : border 500ms ease-out;
        -moz-transition : border 500ms ease-out;
        -o-transition : border 500ms ease-out;
        transition : border 500ms ease-out;
        border-bottom: 5px solid #000 !important;
        border-radius: 0px;
        border-top: 5px solid #000;
    }
    .subsubsub li a:hover{
         border-bottom :5px solid #d3d3d3 !important;
    }*/
</style>