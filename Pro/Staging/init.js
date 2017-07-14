jQuery(document).ready(function($) {
	// check_wptc_staging_status();
	// choose_staging_wptc();
	if (window.location.href.indexOf('wp-time-capsule-staging-options') !== -1 || window.location.href.indexOf('update-core.php') !== -1 || window.location.href.indexOf('plugins.php') !== -1 || window.location.href.indexOf('plugin-install.php') !== -1 || window.location.href.indexOf('themes.php') !== -1 ){
		is_staging_running_wptc();
	}
	if (window.location.href.indexOf('wp-time-capsule-staging-options') !== -1) {
		jQuery('#wpfooter').remove();
	}
	jQuery('#wptc_staging_submit').click(function(){
		clear_staging_flags_php();
		var ftp_host = jQuery('#wptc_staging_ftp_host').val();
		var ftp_port = jQuery('#wptc_staging_ftp_port').val();
		var ftp_usertime = jQuery('#wptc_staging_ftp_username').val();
		var ftp_password = jQuery('#wptc_staging_ftp_password').val();
		var db_host = jQuery('#wptc_staging_db_host').val();
		var db_username = jQuery('#wptc_staging_db_name').val();
		var db_password = jQuery('#wptc_staging_db_password').val();
		var db_port = jQuery('#wptc_staging_db_port').val();
		jQuery.post(ajaxurl, {
			action: 'check_staging_ftp_creds_wptc',
		}, function(data){
			console.log(data);
		});
	});

	jQuery('body').on('mousedown', '.show_password', function(e){
		var passwordInp = jQuery(this).next(".passwords").get(0);
		passwordInp.blur();
		passwordInp.type = 'text';
		jQuery(this).text('Hide');
		e.preventDefault();
	}).on('mouseup mouseleave', '.show_password', function(e){
		jQuery(this).text('Show');
		jQuery(this).next(".passwords").get(0).type = 'password';
	});


	jQuery('body').on('click', '.cpanel_exit', function (){
		tb_remove();
	});

	jQuery('body').on('click', '#yes_delete_site', function (){
		delete_staging_site_wptc();
	});

	jQuery("body").on('click', '#browseFiles_wptc',function() {
		jQuery('#fileTreeContainer').toggle();
		jQuery('#ftp_creds_wrong_wptc').hide();
		load_file_tree_wptc(jQuery('#fileTreeContainer'), jQuery('#fileTreeContainer'));
		jQuery("#placedBackup").show();
		jQuery("#fileTreeContainer").addClass("addScroll");
	});

	jQuery('body').on('click', '#delete_staging, #no_delete_site', function (){
		jQuery("#staging_delete_options").toggle();
	});

	jQuery('body').on('click', '#edit_staging_wptc', function (){
		if(jQuery(this).hasClass('disabled')){
			return false;
		}
		choose_staging_wptc(true);
	});

	jQuery('body').on('click', '.wptc_db_type', function (){
		var current_tab = jQuery(this).val();
		if (current_tab == 'wp_config') {
			jQuery('.wp_config_db_wptc_note').show();
			jQuery('.custom_db_wptc_note').hide();
			jQuery('.new_db_wptc_note').hide();
			jQuery('.common_db_details_wptc').show();
			jQuery('.new_db_details_wptc').hide();
		} else if(current_tab == 'custom'){
			jQuery('.wp_config_db_wptc_note').hide();
			jQuery('.custom_db_wptc_note').show();
			jQuery('.common_db_details_wptc').show();
			jQuery('.new_db_wptc_note').hide();
			jQuery('.new_db_details_wptc').hide();
		} else if(current_tab == 'new_db'){
			jQuery('.wp_config_db_wptc_note').hide();
			jQuery('.custom_db_wptc_note').hide();
			jQuery('.new_db_wptc_note').show();
			jQuery('.common_db_details_wptc').hide();
			jQuery('.new_db_details_wptc').show();
		}
	});

	jQuery('body').on('click', '#connect_cpanel', function (){
		jQuery("#cpanel_error").hide();
		if (jQuery(this).hasClass('disabled')) {
			return false;
		}
		cpanel_submit_obj = this;
		jQuery(this).addClass('disabled').html('connecting...');
		var cpanel_url = jQuery("#cpanel_url_wptc").val();
		var cpanel_username = jQuery("#cpanel_username_wptc").val();
		var cpanel_password = jQuery("#cpanel_password_wptc").val();
		jQuery.post(ajaxurl, {
			data:{'cpanel_url':cpanel_url, 'cpanel_username':cpanel_username, 'cpanel_password': cpanel_password},
			action: 'connect_cpanel_wptc',
		}, function(data){
			jQuery(cpanel_submit_obj).removeClass('disabled').html('submit');
			try{
				var data = jQuery.parseJSON(data)
				if (typeof data.error != 'undefined') {
					console.log('error', data);
					jQuery("#cpanel_error").html(data.error).show();
					return false;
				} else {
					tb_remove();
					jQuery('#dashboard_activity').show();
					jQuery('#ftp_host_wptc').val(data.cpHost);
					jQuery('#ftp_username_wptc').val(data.cpUser);
					jQuery('#ftp_password_wptc').val(data.cpPass);
					jQuery('#select_target_ftp_wptc').val(data.path);
					jQuery('#full_url_ftp_wptc').val(data.destURL);
					is_cpanel_way_wptc = '1';
					if (typeof data.db_prefix != 'undefined' && data.db_prefix) {
						cpanel_db_prefix_wptc = data.db_prefix;
					} else {
						cpanel_db_prefix_wptc = false;
					}
				}
			}catch (err){
					console.log("error", err);
					jQuery("#cpanel_error").html(err).show();
					return false;
			}
		});
	});

	jQuery(document).mouseup(function (e) {
		var container = $(".fileTreeClass");
		if (!container.is(e.target) // if the target of the click isn't the container...
			&& container.has(e.target).length === 0) // ... nor a descendant of the container
		{
			container.hide();
		}
	});

	jQuery('body').on('click', '#validate_db_wptc', function (e){
		if (jQuery(this).hasClass('disabled')) {
			return false;
		}
		e.stopPropagation();
		validate_db_wptc_obj = this;
		var db_type = jQuery('input[name=wptc_db_type]:checked').val();
		if (db_type == 'new_db') {
			create_db_cpanel();
		} else if(db_type == undefined || db_type == "custom" || db_type == "wp_config"){
			validate_database();
		}
	});

	jQuery('body').on('click', '#go_ftp_form_again', function (e){
		remove_prev_activity_ui();
		get_stored_ftp_details();
	});


	jQuery('body').on('click', '#ask_copy_staging_wptc', function (e){
		if (jQuery(this).hasClass('disabled')) {
			return false;
		}
		var head_html = '<div class="theme-overlay wptc_restore_confirmation" style="z-index: 1000;">';

		var head_html_1 = '<div class="theme-wrap wp-clearfix" style="width: 450px;height: 220px;left: 0px;">';
		var title = '<div class="theme-header"><button class="close dashicons dashicons-no"><span class="screen-reader-text">Close details dialog</span></button> <h2 style="margin-left: 127px;">Copy live to staging</h2></div>'

		var body = '<div class="theme-about wp-clearfix"> <h4 style="font-weight: 100;text-align: center;">Clicking on Yes will continue to copy your live site to staging site.<br> Are you sure want to continue ?</h4></div>';
		var footer = '<div class="theme-actions"><div class="active-theme"><a lass="button button-primary customize load-customize hide-if-no-customize">Customize</a><a class="button button-secondary">Widgets</a> <a class="button button-secondary">Menus</a> <a class="button button-secondary hide-if-no-customize">Header</a> <a class="button button-secondary">Header</a> <a class="button button-secondary hide-if-no-customize">Background</a> <a class="button button-secondary">Background</a></div><div class="inactive-theme"><a class="button button-primary load-customize hide-if-no-customize btn_pri" id="copy_staging_wptc" >Yes, COPY</a><a class="button button-secondary activate close btn_sec">No</a></div></div></div></div>';
		var html = head_html+head_html_1+title+body+footer;
		jQuery(".wptc-thickbox").click();
		jQuery('#TB_ajaxContent').hide();
		jQuery('#TB_load').remove();
		jQuery('#TB_window').html(html).removeClass('thickbox-loading');
		jQuery('#TB_title').remove();
	});

	jQuery('body').on('click', '#copy_staging_wptc', function (e){
		tb_remove();
		select_copy_staging_type(true);
	});

	jQuery('body').on('click', '#staging_err_retry', function (e){
		delete stop_staging_calls;
		get_stored_ftp_details(1);
	});

	jQuery('body').on('click', '#ftp_submit_wptc', function (){
		jQuery("#ftp_creds_wrong_wptc, #folder_path_mismatch").hide();
		if (jQuery(this).hasClass('disabled')) {
			return false;
		}
		ftp_submit_wptc_obj = this;
		var host_ssl = 0;
		jQuery(this).addClass('disabled').val('Validating...');
		var ftp_host = jQuery("#ftp_host_wptc").val();
		var ftp_username = jQuery("#ftp_username_wptc").val();
		var ftp_password = jQuery("#ftp_password_wptc").val();
		var ftp_port = jQuery("#ftp_port_wptc").val();
		var ftp_connect_type = jQuery('input[name=wptc_ftp_connection_type]:checked').val();
		var ftp_use_passive = jQuery('input[name=wptc_ftp_use_passive]:checked').val();
		var select_target_ftp = jQuery("#select_target_ftp_wptc").val();
		var full_url_ftp = jQuery("#full_url_ftp_wptc").val();
		if (typeof ftp_use_passive == 'undefined' || ftp_use_passive == undefined ) {
			ftp_use_passive = 0;
		}
		if (ftp_connect_type == 'ftp_ssl') {
			host_ssl = 1;
		}
		jQuery.post(ajaxurl, {
			data:{
				'host':ftp_host,
				'username':ftp_username,
				'password': ftp_password,
				'port': ftp_port,
				'connect_type': ftp_connect_type,
				'host_passive': ftp_use_passive,
				'host_ssl': host_ssl,
				'remote_folder': select_target_ftp,
				'destination_url': full_url_ftp,
				'test_connection': '1',
			},
			action: 'check_ftp_crendtials_wptc',
		}, function(data){
			jQuery(ftp_submit_wptc_obj).removeClass('disabled').val('Next');
			try{
				var data = jQuery.parseJSON(data);
				if (typeof data.error != 'undefined') {
					console.log('error', data);
					jQuery("#ftp_creds_wrong_wptc").html(data.error).show();
					return false;
				} else if (typeof data.folder_mismatch_error != 'undefined') {
					jQuery('#folder_path_mismatch').html(data.folder_mismatch_error).show();
					return false;
				} else if (typeof data.success != 'undefined') {
					remove_prev_activity_ui();
					db_details_wptc();
					if (typeof cpanel_db_prefix_wptc != 'undefined') {
						jQuery(".wptc_staging_db_prefix").html(cpanel_db_prefix_wptc);
					} else {
						jQuery('#new_db_wptc_option, #new_db_wptc, .new_db_wptc').remove();
						jQuery('#new_db_wptc_option').hide();
					}
					if (data.success != 'wp_config_not_found' && typeof data.wp_config_data != 'undefined') {
						var db_details = data.wp_config_data;
						jQuery('#wp_config_wptc').attr("checked", 'checked');
						jQuery("#db_prefix_wptc").val(db_details.TABLE_PREFIX);
						jQuery("#db_host_wptc").val(db_details.DB_HOST);
						jQuery("#db_user_wptc").val(db_details.DB_USER);
						jQuery("#db_password_wptc").val(db_details.DB_PASSWORD);
						jQuery("#db_name_wptc").val(db_details.DB_NAME);
						jQuery('#options_db_wptc').css('margin-left', '244px');
					} else {
						jQuery('#wp_config_wptc, .wp_config_wptc, #wp_config_wptc_option, .wp_config_db_wptc').remove();
						if (typeof cpanel_db_prefix_wptc != 'undefined') {
							jQuery('#custom_db_wptc').attr("checked", 'checked');
							jQuery('#options_db_wptc').css('margin-left', '244px');
							jQuery('.custom_db_wptc_note').show();
							jQuery('.wp_config_db_wptc_note').remove();
						} else {
							jQuery("#db_prefix_wptc").val('wp_');
							jQuery("#db_host_wptc").val('localhost');
							jQuery('.custom_db_wptc, .wp_config_db_wptc_note').remove();
							jQuery('.custom_db_wptc_note').show();
						}
					}
				}
			}catch (err){
					console.log("error", err);
					jQuery("#ftp_creds_wrong_wptc").html(err).show();
					return false;
			}
		});
	});
	jQuery('body').on('click', '.auto_fill_cpanel_wptc', function (){
		var overlay_start = '<div class="theme-overlay" id="wptc_restore_confirmation" style="z-index: 1000;">'
		var header = '<div class="theme-wrap wp-clearfix" style="width: 900px;height: 570px; left:0px"><div class="theme-header"><button class="close dashicons dashicons-no cpanel_exit"><span class="screen-reader-text">Close details dialog</span></button>';
		var title =  '<h2 style="margin-left: 127px;">Auto-fill via Cpanel</h2>';
		var header_name = '</div><div class="theme-about wp-clearfix" style="top: 45px;bottom: 0px; padding-top:0px">';
		var body = '<div class="inner_cont cpanelAutofill"> <div style="color: #435358;font-weight: 700;font-size: 12px;text-transform: uppercase;margin-bottom: 40px;position: relative;text-align:center;top: -25px;">ENTER YOUR CPANEL DETAILS</div><div id="cpanel_error" style=" word-break: break-all;display:none ;min-height: 40px;background: #fef4f4;border-left: 5px solid #e82828;width: 340px; text-align: justify; position: absolute;left: 462px;top: 299px;"><span style="position: relative;left: 5px;top: 10px;word-break: break-word;">Error: Folder Paths mismatch</span></div><input type="text" placeholder="Link to your cPanel" class="regular-text" id="cpanel_url_wptc" name="" style="top: -10px;position: relative;"> <div style="text-align: right;margin: -10px 70px 5px 0px;font-size: 12px;font-style: italic;">To avoid errors, copy &amp; paste from your browser</div> <input name="" type="text" class="regular-text" id="cpanel_username_wptc" placeholder="Enter your cpanel username" style="top: -3px;position: relative;"> <a class="show_password" style="position: absolute;right: 95px;top: 260px;z-index: 10;">Show</a> <input name="" type="password" class="regular-text passwords" id="cpanel_password_wptc" placeholder="Enter your cpanel account password" style="position: relative; top: 7px;"><div style="margin:0px; line-height: 20px;padding: 5px 10px;text-align:left;display:none" class="errorMsg"></div></div>';
		var footer_header = '</div><div class="theme-actions"><div class="active-theme"><a lass="button button-primary customize load-customize hide-if-no-customize">Customize</a><a class="button button-secondary">Widgets</a> <a class="button button-secondary">Menus</a> <a class="button button-secondary hide-if-no-customize">Header</a> <a class="button button-secondary">Header</a> <a class="button button-secondary hide-if-no-customize">Background</a> <a class="button button-secondary">Background</a></div><div class="inactive-theme">';
		var footer_button = '<a style="float:right" class="button button-primary load-customize hide-if-no-customize" id="connect_cpanel">Submit</a></div>';
		var footer_finish_touch = '</div></div></div>';
		var final_html = overlay_start+header+title+header_name+body+footer_header+footer_button+footer_finish_touch;
		// add_thickbox();
		remove_other_thickbox_wptc();
		jQuery("#wptc-content-id").html(final_html);
		jQuery(".wptc-thickbox").click();
		styling_thickbox_tc();
	});

	jQuery('body').on('click', '.fileTreeSelector', function (e){
		var thisFileName = jQuery(this).attr("filename");
		var thisFolderName = jQuery(this).attr("rel");
		var thisType = jQuery(this).attr("type");
		if((thisType != "file")) {
			jQuery("#select_target_ftp_wptc").attr("value", thisFolderName+"/");
			jQuery("#fileTreeContainer").hide();
		}
	});

	jQuery('body').on('click', '#stop_staging_confirm', function (e){
		console.log('delete site called');
		if (jQuery('#stop_staging_wptc').hasClass('disabled')) {
			return false;
		}
		jQuery('#stop_staging_wptc').val('Stopping...').addClass('disabled');
		clear_staging_flags_php();
		wtc_stop_backup_func();
		stop_staging_wptc();
		bp_in_progress = false;
		tb_remove();
	});

	jQuery('body').on('click', '#stop_staging_wptc', function (e){
		var head_html = '<div class="theme-overlay wptc_restore_confirmation" style="z-index: 1000;">';

		var head_html_1 = '<div class="theme-wrap wp-clearfix" style="width: 450px;height: 220px;left: 0px">';
		var title = '<div class="theme-header"><button class="close dashicons dashicons-no"><span class="screen-reader-text">Close details dialog</span></button> <h2 style="margin-left: 167px;">Stop staging</h2></div>'

		var body = '<div class="theme-about wp-clearfix"> <h4 style="font-weight: 100;text-align: center;">It will not delete staging site but may lead to broken staged site.<br> Are you sure want to continue ?</h4></div>';
		var footer = '<div class="theme-actions"><div class="active-theme"><a lass="button button-primary customize load-customize hide-if-no-customize">Customize</a><a class="button button-secondary">Widgets</a> <a class="button button-secondary">Menus</a> <a class="button button-secondary hide-if-no-customize">Header</a> <a class="button button-secondary">Header</a> <a class="button button-secondary hide-if-no-customize">Background</a> <a class="button button-secondary">Background</a></div><div class="inactive-theme"><a class="button button-primary load-customize hide-if-no-customize btn_pri" id="stop_staging_confirm" >Yes, STOP</a><a class="button button-secondary activate close btn_sec">No</a></div></div></div></div>';
		var html = head_html+head_html_1+title+body+footer;
		jQuery(".wptc-thickbox").click();
		jQuery('#TB_ajaxContent').hide();
		jQuery('#TB_window').html(html).removeClass('thickbox-loading');
		jQuery('#TB_load').remove();
		jQuery('#TB_title').remove();
	});

	jQuery('body').on('click', '#refresh-s-status-area-wtpc', function (e){
		if (jQuery(this).hasClass('disabled')) {
            return false;
        }
		is_staging_running_wptc();
	});


	jQuery("body").on("click", ".upgrade-plugins-staging-wptc" ,function(e) {
		handle_plugin_upgrade_request_wptc(this, e , false , true);
	});

	jQuery("body").on("click", ".update-link-plugins-staging-wptc, .update-now-plugins-staging-wptc" ,function(e) {
		handle_plugin_link_request_wptc(this, e , false , true);
	});

	jQuery("body").on("click", ".button-action-plugins-staging-wptc" ,function(e) {
		handle_plugin_button_action_request_wptc(this, e , false, true);
	});

	jQuery("body").on("click", ".upgrade-themes-staging-wptc" ,function(e) {
		handle_themes_upgrade_request_wptc(this, e , false, true);
	});

	jQuery("body").on("click", ".upgrade-core-staging-wptc" ,function(e) {
		handle_core_upgrade_request_wptc(this, e , false, true);
	});

	jQuery("body").on("click", ".upgrade-translations-staging-wptc" ,function(e) {
		handle_translation_upgrade_request_wptc(this, e , false, true);
	});

	jQuery('body').on("click", '.plugin-update-from-iframe-staging-wptc', function(e) {
		handle_iframe_requests_wptc(this, e , false, true);
	});

	jQuery('body').on("click", '#same_server_submit_wptc', function(e) {
		jQuery('#internal_staging_error_wptc').html('');
		if(jQuery(this).hasClass('disabled')){
			return false;
		}

		jQuery(this).addClass('disabled');
		jQuery(this).val('Processing...');
		var path = jQuery('#same_server_path_staging_wptc').val();
		if(path.length < 1){
			jQuery('#internal_staging_error_wptc').html('Error : Staging path cannot be empty.');
			jQuery('#same_server_submit_wptc').val('Start Staging').removeClass('disabled');
			return false;
		}
		staging_type_wptc = 'internal';
		test_same_server_wptc(path);
	});

	jQuery('body').on("click", '#select_same_server_wptc', function(e) {
		if(jQuery(this).hasClass('disabled')){
			return false;
		}
		same_server_wptc();
	});

	jQuery('body').on("click", '#goto_staging_setup_wptc', function(e) {
		//disabled temporarly
		// choose_staging_wptc(true);
	});

	jQuery('body').on("click", '#select_different_server_wptc, #goto_staging_diff_server_ftp_wptc', function(e) {
		if(jQuery(this).hasClass('disabled')){
			return false;
		}
		get_stored_ftp_details();
	});

});

function copy_internal_staging(){
	jQuery.post(ajaxurl, {
		action: 'copy_same_server_staging_wptc',
		dataType: "json",
	}, function(data) {
		if(data === 'Cron server is failed, Try after sometime.'){
			alert("Cron server is failed, Try after sometime.");
			return false;
		}
		try{
			var data = jQuery.parseJSON(data);
			console.log(data.status);
			if(data.status === 'success'){
				staging_in_progress();
				console.log('yes we started staging');
			}
		} catch(err){
			alert("Something went wrong try again !");
		}
	});
}

function select_copy_staging_type(direct_copy){
	if(direct_copy === undefined){
		var data = {
			bbu_note_view:{
				type: 'message',note:'Update in staging initiated! We will notify you once it\'s completed.',
			},
		};
		show_notification_bar(data);
	}
	if(staging_type_wptc === 'internal'){
		copy_internal_staging();
	} else if(staging_type_wptc === 'external') {
		copy_staging_wptc();
	}
}

function copy_staging_wptc(){
	remove_prev_activity_ui();
	clear_staging_flags_php();
	start_backup_staging_wptc();
	setTimeout(function(){
		staging_in_progress();
	}, 3000)
}

function ftp_details_wptc(){
	remove_prev_activity_ui();
	var html = ftp_details_wptc_template();
	jQuery('#staging_area_wptc').after(html);
}

function test_same_server_wptc(path){
	jQuery.post(ajaxurl, {
		action: 'test_same_server_staging_wptc',
		path: path,
		dataType: "json",
	}, function(data) {
		jQuery('#same_server_submit_wptc').val('Start Staging').removeClass('disabled');
		if(data === 'Cron server is failed, Try after sometime.'){
			jQuery('#internal_staging_error_wptc').html('Error: Cron server is failed, Try after sometime.');
			return false;
		}
		try{
			var data = jQuery.parseJSON(data);
			console.log(data.status);
			if(data.status === 'success'){
				staging_in_progress();
				console.log('yes we started staging');
			} else if(data.status === 'error'){
				jQuery('#internal_staging_error_wptc').html('Error: '+ data.message);
			} else {
				jQuery('#internal_staging_error_wptc').html('Error: Something went wrong, try again.');
			}
		} catch(err){
			// alert("Cannot make ajax calls");
			jQuery('#internal_staging_error_wptc').html('Error: Something went wrong, try again.');
		}
	});
}

function choose_staging_wptc(dont_disable_button){
	remove_prev_activity_ui();
	jQuery('#dashboard_activity').remove();
	//Choosing staging type disabled until new external staging comes in.
	// var html = choose_staging_template_wptc();
	var html = same_server_template_wptc();
	jQuery('#staging_area_wptc').after(html);
	if(dont_disable_button === undefined){
		progress_staging_button_wptc();
	}
}

function same_server_wptc(){
	remove_prev_activity_ui();
	var template = same_server_template_wptc();
	jQuery('#staging_area_wptc').after(template);
}


function same_server_template_wptc(){
	var head_div = '<div id="dashboard_activity" class="postbox" style="overflow: hidden;margin: 0px; width: 702px; margin: 60px 0px 0px 460px;"><h2 class="hndle ui-sortable-handle">'
	var title = '<span style="margin-left: 15px;position: relative;bottom: 8px;" class="title-bar-staging-wptc"> <span id="goto_staging_setup_wptc" style="cursor: pointer;">Staging Setup </span> >  Same Server</span></h2>';
	var body_start =  '<div class="inside">';
	var inside_block_start = '<div style="position: relative;margin-bottom: 50px;top: 10px; margin-top: 35px;">';
	var content = '<div class="stage-on-the-server">Stage on the same server</div>';
	var input = '<div style="top: 30px;position: relative;left: 23%;"><label class="same-server-staging-path" title='+get_home_url_wptc()+' >Staging Path: <span style="max-width: 200px;display: table-cell;overflow: hidden !important;text-overflow: ellipsis;" >'+get_home_url_wptc()+'</span></label><input id="same_server_path_staging_wptc" type="text" value="staging" class="staging-path-input-wptc"></div><div style=" position: absolute; top: 70px; color: #D54E21; left: 24%;" id="internal_staging_error_wptc"></div>';
	var button = '<div><input id="same_server_submit_wptc" type="submit" value="Start Staging" style="margin: 60px 0px 0px 270px;width: 140px;" class="button-primary"></div>';
	var inside_block_end = '</div>';
	var body_end = '</div>';
	var footer = '';
	return head_div+title+body_start+inside_block_start+content+input+button+inside_block_end+body_end+footer;
}

function choose_staging_template_wptc(){
	var head_div = '<div id="dashboard_activity" class="postbox" style="overflow: hidden;margin: 0px; width: 702px; margin: 60px 0px 0px 460px;"><h2 class="hndle ui-sortable-handle">'
	var title = '<span style="margin-left: 15px;position: relative;bottom: 8px;"  class="title-bar-staging-wptc">Staging Setup</span></h2>';
	var body_start =  '<div class="inside">';
	var border = '<div class="staging-border-wptc" style="position: relative; left: 49%;"></div>';
	var inside_block_start = '<div style="position: relative;">';
	var same_server_content = '<div class="staging-same-server-block" style="position: absolute;top: -160px;left: 88px;"><span class="stage-on-the-server">Stage on the same server</span><div class="staging-recommended">(Recommended)</div><input id="select_same_server_wptc" type="submit" value="Stage Now" style="position: absolute; margin: 50px 0px 0px -146px;width: 140px;" class="button-primary"><div class="staging-speed-note">Faster!</div></div>';
	var diff_server_content = '<div class="staging-different-server-block" style="position: absolute;top: -160px;right: 91px;"><span class="stage-on-the-server">Stage on different server</span><input id="select_different_server_wptc" type="submit" value="Stage Now" style="margin: 50px 0px 0px -146px;width: 140px; position: absolute;" class="button-primary"><div class="staging-speed-note">Slower...</div></div>';
	var inside_block_end = '</div>';
	var body_end = '</div>';
	var footer = '';
	return head_div+title+body_start+border+inside_block_start+same_server_content+diff_server_content+inside_block_end+body_end+footer;
}

function get_home_url_wptc() {
  var href = window.location.href;
  var index = href.indexOf('/wp-admin');
  var homeUrl = href.substring(0, index);
  return homeUrl+'/';
}

function ftp_details_wptc_template(){
	var header = '<div id="dashboard_activity" class="postbox" style="overflow: hidden;margin: 0px; width: 702px; margin: 60px 0px 0px 460px;"><div class="handlediv" style="font-style: italic;width: 70px;margin: 10px 0px 10px 10px; ">Step 1 0f 2</div><h2 class="hndle ui-sortable-handle title-bar-staging-wptc"><span style="margin-left: 15px;position: relative;bottom: 6px;"><span id="goto_staging_setup_wptc" style="cursor: pointer;">Staging Setup </span> > Different Server > FTP Details </span></span></h2>';
	var inside_head =  '<div class="inside"> <div> <a class="button auto_fill_cpanel_wptc" href="#" style="float: right;top: 0px;position: relative;left: 60px;">Auto-fill via Cpanel</a> </div> <h4 style="position: relative; margin: 0px 0px 0px 300px;top: 35px;">FTP Details</h4>';
	var inside_table = '<table class="wptc-form" style="border-collapse: separate;border-spacing: 0 10px;margin: 0px 0px 10px 110px;padding-top: 40px;position: relative;"> <tbody> <tr><td></td><td><div id="ftp_creds_wrong_wptc" style="min-height: 22px;background: rgb(254, 244, 244);max-width: 340px;padding-left: 8px; display:none"><span style="position: relative;left: 5px;top: 10px;word-break: break-word;">Error: Invalid username</span></div></td></tr> <tr> <td><label>FTP Host :</label></td><td><input id="ftp_host_wptc" type="text" class="regular-text " style=" width: 240px;"></td> <td> <label class="port_db_staging_label">Port :</label></td><td> <input type="text" id="ftp_port_wptc" value="21" class="regular-text port_db_staging_input"></td></tr> <tr><td> <label>FTP Username :</label></td><td> <input id="ftp_username_wptc" type="text" class="regular-text "></td></tr> <tr><td> <label>FTP Password :</label></td> <td style="position:relative"> <a class="show_password" style="position: absolute;right: 9px;top: 5px;z-index: 10;">Show</a> <input id="ftp_password_wptc" type="password" class="regular-text passwords"></td> </tr> <tr>  <td>Connection type :</td> <td>  <input class="wptc_ftp_connection_type"  style="margin-left: 0px !important;" type="radio" name="wptc_ftp_connection_type" checked value="ftp"> FTP <input name="wptc_ftp_connection_type" class="wptc_ftp_connection_type" type="radio" value="ftp_ssl"> FTP SSL <input name="wptc_ftp_connection_type" class="wptc_ftp_connection_type"  type="radio" value="sftp"> SFTP</td></tr> <tr> <td></td> <td><input class="wptc_ftp_use_passive" name="wptc_ftp_use_passive" checked type="checkbox" value="1"> Use Passive Mode</td> </tr> </tbody> </table><h4 style="position: relative;margin: 0px 0px 0px 300px;top: -5px;">Target Folder</h4><div id="folder_path_mismatch" style="display:none; min-height: 22px;background: rgb(254, 244, 244);padding-left: 5px;width: 401px;position: relative;left: 217px;top: 7px;"><span style="position: relative;left: 5px;top: 10px;word-break: break-word;">Error: Folder Paths mismatch</span></div> <div style="position:relative;padding-bottom: 80px;margin-left: 215px;"> <div style="border-left: 1px solid rgb(183, 190, 192); position: absolute; left: -15px; top: 32px; height: 120px;"></div> <div style="border: 1px solid #b7bec0;border-radius: 3px;margin-bottom: -2px;width: 406px;top: 15px;position: relative;"><div class="browseFilesIC" id="browseFiles_wptc"></div><input type="text" style="border:none; height:27px" name="" id="select_target_ftp_wptc" class="regular-text" value=""><div class="clear-both"></div></div><div class="fileTreeClass" id="fileTreeContainer" style="display:none">click here to select the folder</div><div style="border-top: 1px solid rgb(183, 190, 192); position: absolute; width: 15px; left: -15px; height: 44px; top: 31px;"></div>  <div style="float: left;position: absolute;left: -180px;positi;te;top: 21px;z-index: 0;">Select Your Target Folder :</div> <div style="float: left;position: absolute;left: -200px;positi;te;top: 91px;z-index: 0;">Full URL to the Target Folder :</div>  <div style="position: relative;left: 2px;top: 15px;font-size: 11px;font-style: italic;">To create a new folder, enter the new folder named after the parent folder</div><div style="border-top: 1px solid rgb(183, 190, 192); position: absolute; width: 15px; left: -15px; height: 44px; top: 103px;"></div> <input id="full_url_ftp_wptc" type="text" placeholder="http://yourdomain.com/path/" class="regular-text " style="position:relative;top: 41px;width:406px"> <div style="position: relative;left: 4px;top: 40px;font-size: 11px;font-style: italic;">Example:http://yourdomain.com/path/to/targetFolder/</div>  <!-- <div style="border: 1px solid rgb(183, 190, 192); position: absolute; left: -25px; color: rgb(147, 152, 162); padding: 6px; border-radius: 5px; top: 151px;">These 2 should point to the same folder</div> -->  <div style="border-top: 1px solid rgb(183, 190, 192);position: absolute;width: 26px;left: -15px;height: 44px;top: 152px;"></div> <div style="position: relative;left: 4px;top: 34px;color: rgb(183,190, 192);font-size: 55px;">.</div>   <div style="position: relative;left: 19px;top: 33px;font-style: italic;color: rgb(183,190, 192);font-size: 11px;">These 2 should point to the same folder</div> <div class="placedBackupAraea" style="display:none"><div class="label">Placed Backup File name</div> <input name="" type="text" id="placedBackup" value="" class="formVal required" style=""></div>  </div> </div>';
	var footer = '<div style="width: 702px;height: 50px;background: #f3f3f3;border-top: 1px solid rgba(114, 119, 124, 0.08);">  <input id="ftp_submit_wptc" type="submit" class="button-primary" value="Next" style="float:right; margin:10px"> </div> </div>';
	var final_html = header + inside_head+ inside_table + footer;
	return final_html;
}

function db_details_wptc(){
	remove_prev_activity_ui();
	var html = db_details_wptc_template();
	jQuery('#staging_area_wptc').after(html);
	fill_staging_db_details_wptc();
}
function db_details_wptc_template(){
	var header = '<div id="dashboard_activity" class="postbox pop_up_db_stging_wptc" style="width: 600px; margin: 60px 0px 0px 460px;"><div class="handlediv" style="font-style: italic;width: 70px;margin: 10px 0px 10px 10px;">Step 2 0f 2</div><h2 class="hndle ui-sortable-handle title-bar-staging-wptc"><span style="margin-left: 15px;position: relative;bottom: 6px;"><span id="goto_staging_setup_wptc" style="cursor: pointer;">Staging Setup </span> > <span id="goto_staging_diff_server_ftp_wptc" style="cursor: pointer;">Different Server > FTP Details </span> > DB Details</span></span></span></h2>';
	var inside = '<div class="inside"><div style="margin-left: -20px;"><h4 style="position: relative;margin: 0px 0px 30px 250px;top: 20px;">Database Details</h4><div id="options_db_wptc" style="margin-left: 184px;top: 10px;position: relative;"><label for="wp_config_wptc" id="wp_config_wptc_option"> <input class="wptc_db_type" id="wp_config_wptc" name="wptc_db_type" style="margin-left: 0px !important;" type="radio" value="wp_config"/> WP-Config </label><label class="custom_db_wptc" for="custom_db_wptc"><input class="wptc_db_type" name="wptc_db_type" id="custom_db_wptc" type="radio" value="custom"/> Custom </label><label id="new_db_wptc_option" for="new_db_wptc"><input class="wptc_db_type" name="wptc_db_type" id="new_db_wptc" type="radio" value="new_db"/>Create New DB</label></div><div class="wp_config_db_wptc_note" style="margin-left: 130px;font-size: 12px;top: 25px;color: gray;font-style: italic;position: relative;">We fetched the database details from wp-config.php using your FTP details.</div><div class="custom_db_wptc_note" style="margin-left: 210px;font-size: 12px;top: 25px;color: gray;font-style: italic;position: relative; display:none">Enter details of existing database here.</div><table class="wptc-form common_db_details_wptc" style="border-collapse: separate;border-spacing: 0 10px;margin: 40px 0px 50px 140px;position: relative;"><tbody><tr><td></td><td><div style="min-height: 22px;background: rgb(254, 244, 244);padding-left: 5px; display:none" id="db_common_wptc_error"><span style="position: relative;left: 5px;top: 10px;word-break: break-word;" >Error: Invalid username</span></div></td></tr><tr><td><label>DB Host :</label></td><td><input id="db_host_wptc" type="text" class="wptc-normal-input" value="localhost"> </td></tr><tr><td> <label>DB Name :</label></td><td> <input id="db_name_wptc" type="text" class="wptc-normal-input"></td></tr><tr><td> <label>Username :</label></td><td> <input id="db_user_wptc" type="text" class="wptc-normal-input"></td></tr><tr><td> <label>Password :</label></td><td>  <input id="db_password_wptc" type="text" class="wptc-normal-input"></td></tr><tr><td> <label>DB Prefix :</label></td><td>  <input type="text" id="db_prefix_wptc" class="wptc-normal-input"></td></tr></tbody></table><table class="wptc-form new_db_details_wptc" style="display:none;border-collapse: separate;border-spacing: 0px 10px;margin: 10px 0px 50px 120px;position: relative;"><tbody><tr><td></td><td><div style="min-height: 0px;max-width: 250px;background: rgb(254, 244, 244);display:none" id="db_new_wptc_error"><span style="position: relative;left: 5px;top: 10px;word-break: break-word;" >Error: Invalid username</span></div></td></tr><tr><td><label>New Database: <span style="font-weight:600" class="wptc_staging_db_prefix"></span></label></td><td><input id="new_db_name" type="text" class="wptc-normal-input"> </td></tr><tr><td> <label>Username: <span style="font-weight:600" class="wptc_staging_db_prefix"></span></label></td><td> <input type="text" id="new_db_username" class="wptc-normal-input"></td></tr><tr><td> <label>Password :</label></td><td style=" position: relative;"> <a class="show_password" style="position: absolute;right: 13px;top: 5px;z-index: 10;">Show</a> <input type="password" id="new_db_password" class="wptc-normal-input passwords"></td></tr></tbody></table></div></div>';
	var footer = '<div style="width: 610px;height: 50px;background: #f3f3f3;border-top: 1px solid rgba(114, 119, 124, 0.08);"><input type="submit" class="button-primary" id="validate_db_wptc" value="Next" style="float:right;margin: 10px;margin-right: 20px;"><input type="submit" class="button-primary" id="go_ftp_form_again" value="Previous" style="float:right; margin:10px"></div></div>';
	var final_html = header + inside + footer;
	return final_html;

}

function staging_in_progress(){
	if(jQuery('.wptc_prog_wrap_staging').length === 0){
		remove_prev_activity_ui();
		var html = staging_in_progress_template();
		jQuery('#staging_area_wptc').after(html);
		if(typeof staging_type_wptc != 'undefined' && staging_type_wptc === 'internal'){
			// jQuery('#stop_staging_wptc').hide();
			jQuery('#stop_staging_wptc').val('Stop and clear staging');
		}
		get_staging_url();
	}
}
 function staging_in_progress_template(){
	var header = '<div id="dashboard_activity" class="postbox " style="width: 700px;margin: 60px 0px 0px 460px;"> <h2 class="hndle ui-sortable-handle title-bar-staging-wptc"><span style="margin-left: 15px;position: relative;bottom: 8px;">Staging Progress</span><input id="stop_staging_wptc" type="submit" class="button-primary" value="Stop Staging" style="float:right;position: relative;bottom: 11px;right: 19px;display:block"><span style="margin-left: 15px;position: relative;bottom: 8px;float: right;right: 35px; display:none" id="staging_err_retry"><a style="cursor: pointer;text-decoration: underline; font-size: 14px; float: right;">Try again</a></span></h2><div class="inside" style="width: 500px; height: 180px;">';
	var inside = '<div class="l1" style="margin: 0px 0px 10px 100px;text-align: center;width: 100%;position: relative;top: 15px;">Your site will be staged to <span class="staging_completed_dest_url"> </span></div> <div style="min-height: 40px;background: #fef4f4;border-left: 5px solid #e82828;width: 330px;position: absolute;left: 102px;top: 21px; display:none"><span style="position: relative;left: 5px;top: 10px;word-break: break-word;">Error: Folder Paths mismatch</span></div> <div class="l1 wptc_prog_wrap_staging" style=" top: 40px;position: relative; margin: 0px 0px 0px 90px; width: 100% !important;"><div class="staging_progress_bar_cont"><span id="staging_progress_bar_note">Syncing changes</span><div class="staging_progress_bar" style="width:0%"></div><span class="rounded-rectangle-box-wptc reload-image-wptc" id="refresh-s-status-area-wtpc"></span><div class="last-s-sync-wptc">Last reload: Processing...</div></div></div>';
	var footer = '<div class="l1" style="position: relative;top: 90px;text-align: center;left: 100px;">This might take time, feel free to close the tab. We will email you once it’s done.</div></div></div><?php';
	var final_html = header + inside + footer;
	return final_html;
}

function staging_completed_wptc(completed_time, destination_url){
	remove_prev_activity_ui();
	var html = staging_in_completed_template();
	jQuery('#staging_area_wptc').after(html);
	jQuery("#staging_completed_time").html(completed_time);
	jQuery(".staging_completed_dest_url").html("<a href='"+destination_url+"' target='_blank'>"+destination_url+"</a>");
}

function staging_in_completed_template(){
	var html = '<div id="dashboard_activity" style="margin: 40px 20px 30px 150px;"><div style="margin: 0px 0px 10px 0px;"><strong>Staging status:</strong><span style="color:#0aa018;margin-left: 10px;">Successfully Completed !</span> </div> <div style="margin: 0px 0px 10px 0px;">Last staging was taken on <span id="staging_completed_time">Jun 24, 2016 @7:34PM</span>. Access it here <span class="staging_completed_dest_url"> </span></div> <div style="margin: 0px 0px 10px 0px; position:relative"><a class="wptc_link" id="edit_staging_wptc">Edit Staging</a> | <a class="wptc_link" style="color: #e95d5d;" id="delete_staging">Delete</a> <div id="staging_delete_options" style="top: 0px;position: absolute;left: 190px; display:none">Are you sure you want to delete the staging site? <span id="delete_staging_progress"><a style="color: #e95d5d;" class="wptc_link" id="yes_delete_site">Yes</a></span> | <span><a class="wptc_link" id="no_delete_site">No</a></div> </div> <div style="margin: 0px 0px 10px 0px;"><a id="ask_copy_staging_wptc" class="button button-primary load-customize hide-if-no-customize">Copy site from live to stage</a></div> </div>';
	 return html;
}

function create_db_cpanel(){
	jQuery(validate_db_wptc_obj).addClass('disabled').val('Creating Database...');
	jQuery("#db_new_wptc_error").hide();
	var db_name = cpanel_db_prefix_wptc+jQuery("#new_db_name").val();
	var db_username = cpanel_db_prefix_wptc+jQuery("#new_db_username").val();
	var db_password = jQuery("#new_db_password").val();
	jQuery.post(ajaxurl, {
		action: 'create_db_cpanel',
		data: { 'db_name' : db_name, 'db_username': db_username, 'db_password':db_password },
	}, function(data){
		jQuery(validate_db_wptc_obj).removeClass('disabled').val('Next');
		var data = jQuery.parseJSON(data);
		if (typeof data.error != 'undefined') {
			console.log('error', data);
			jQuery("#db_new_wptc_error").html(data.error).show();
			return false;
		} else if (typeof data.success != 'undefined') {
			jQuery("#validate_db_wptc").val("Database Created !.");
			start_backup_staging_wptc();
			setTimeout(function(){
				staging_in_progress();
			}, 5000);
		}
	});
}

function fill_staging_db_details_wptc(){
	jQuery.post(ajaxurl, {
		action: 'get_external_staging_db_details',
	}, function(data){
		var data = jQuery.parseJSON(data);
		if(!data){
			return false;
		}
		jQuery('#db_host_wptc').val(data.db_host);
		jQuery('#db_name_wptc').val(data.db_name);
		jQuery('#db_user_wptc').val(data.db_username);
		jQuery('#db_password_wptc').val(data.db_password);
		jQuery('#db_prefix_wptc').val(data.db_prefix);

	});
}

function stop_staging_wptc(){
	jQuery.post(ajaxurl, {
		action: 'stop_staging_wptc',
	}, function(data){
		is_staging_running_wptc();
	});
}

function validate_database(){
	jQuery(validate_db_wptc_obj).addClass('disabled').val('Validating...');
	jQuery("#db_common_wptc_error").hide();
	var db_host = jQuery("#db_host_wptc").val();
	var db_name = jQuery("#db_name_wptc").val();
	var db_user = jQuery("#db_user_wptc").val();
	var db_password = jQuery("#db_password_wptc").val();
	var db_prefix = jQuery("#db_prefix_wptc").val();
	jQuery.post(ajaxurl, {
		action: 'validate_database_wptc',
		data: {
			'db_host' : db_host,
			'db_name' : db_name,
			'db_user': db_user,
			'db_password': db_password,
			'db_prefix': db_prefix,
		},
	}, function(data){
		jQuery(validate_db_wptc_obj).removeClass('disabled').val('Next');
		var data = jQuery.parseJSON(data);
		if (typeof data.error != 'undefined') {
			console.log('error', data);
			jQuery("#db_common_wptc_error").html(data.error).show();
			return false;
		} else if (typeof data.success != 'undefined') {
			jQuery(validate_db_wptc_obj).removeClass('disabled').val('Completed');
			jQuery("#validate_db_wptc").val("Database Created !.");
			start_backup_staging_wptc();
			setTimeout(function(){
				staging_in_progress();
			}, 5000);
		}
	});
}

function load_file_tree_wptc(currentForm , e){
	e.prepend('<div class="loadingFileTree">loading</div>');
	ftp_details = {} ;
	ftp_details['hostName'] = jQuery("#ftp_host_wptc").val();
	ftp_details['hostPort'] = jQuery("#ftp_port_wptc").val();
	ftp_details['hostUserName'] = jQuery("#ftp_username_wptc").val();
	ftp_details['hostPassword'] = jQuery("#ftp_password_wptc").val();
	var is_sftp = jQuery(".wptc_ftp_connection_type:checked").val();
	if (is_sftp === 'sftp') {
		ftp_details['useSftp'] = 1;
	} else {
		ftp_details['useSftp'] = '';
	}
	ftp_details['root'] = jQuery("#select_target_ftp_wptc").val();
	if(!ftp_details['root']){
		ftp_details['root'] = '';
	} else {
		var lastChar = ftp_details['root'].substr(-1);
		if (lastChar != '/') {
			ftp_details['root'] = ftp_details['root'] + '/';// Append a slash to it.
		}
	}
	var fileTreePath = 'wp-content/plugins/wp-time-capsule/lib/JqueryfileTree/connectors/jqueryFileTree.php';
	jQuery(e).fileTree({e_object: e,root: ftp_details['root'], script: ajaxurl,action: 'list_ftp_file_sys_wptc' ,ftp_details: ftp_details }, function() { 
		//
	});
}

function get_staging_url(){
	if (window.location.href.indexOf('wp-time-capsule-staging-options') === -1 ){
		return false;
	}
	jQuery.post(ajaxurl, {
		action: 'get_staging_url_wptc',
	}, function(data) {
		var data = jQuery.parseJSON(data);
		console.log(data);
		jQuery(".staging_completed_dest_url").html(' '+data.destination_url);
	});
}

function clear_staging_flags_php(not_force){
	jQuery.post(ajaxurl, {
		action: 'clear_staging_flags_wptc',
		not_force : not_force ? not_force: 0,
	}, function(data) {
		console.log(data);
	});
}

function process_staging_status_wptc(data){
	console.log("process_staging_status_wptc", data);
	if (typeof data.error != 'undefined') {
		staging_in_progress();
		add_tool_tip_staging_wptc('staging_error');
		console.log(data.error, 'last error');
		// clear_staging_flags_php(1);
		jQuery("#staging_progress_bar_note").html(data.error);
		if (!data.percentage) {
			data.percentage = 0;
		}
		jQuery(".staging_progress_bar").css('width', data.percentage+'%');
		jQuery(".staging_progress_bar").css('background-color', '#ff5722');
		jQuery('#staging_err_retry').show();
		return false;
	}

	if (data.is_staging_running == undefined && !data.is_staging_running && (data.is_staging_completed || data.details) && !data.external_staging_requested) {
		console.log('staging is not running');
		if(data.on_going_backup_process){
			add_tool_tip_staging_wptc('backup_progress');
		} else {
			add_tool_tip_staging_wptc('staging_completed');
		}
		redirect_to_stage_complete(data);
		return false;
	} else if ((data.is_staging_running || data.external_staging_requested ) && !data.is_staging_completed && (data.external_staging_requested && (data.details || data.is_staging_running) || data.staging_type == "internal")) {
		add_tool_tip_staging_wptc('staging_running');
		get_current_backup_status_wptc(1);
		staging_in_progress();
	}

	if (data.staging_progress_status != undefined && !data.staging_progress_status) {
		console.log('staging_progress_status is empty');
		return false;
	}

	if (jQuery('#staging_progress_bar_note').html() && jQuery('#staging_progress_bar_note').html().toLowerCase().indexOf('Processing') === -1 && jQuery('#staging_progress_bar_note').html().toLowerCase().indexOf('Downloading Files') === -1) {
		jQuery("#staging_progress_bar_note").html(data.message);
	}

	jQuery(".staging_progress_bar").css('width', data.percentage+'%');

	if (data.need_ajax_call == 1) {
		do_call_bridge_ajax(data.bridge_ajax_url);
	}
}

function redirect_to_stage_complete(data){
	if(data){
		staging_completed_wptc(data.details.human_completed_time, data.details.destination_url);
		is_backup_running_wptc('staging_completed');
		return false;
	}
	jQuery.post(ajaxurl, {
		action: 'staging_details_wptc',
		dataType: 'json',
	}, function(data) {
		var data = jQuery.parseJSON(data);
		staging_completed_wptc(data.human_completed_time, data.destination_url);
		is_backup_running_wptc('staging_completed');
	});
}

function do_call_bridge_ajax(url){

	jQuery.ajax({
		traditional: true,
		type: 'post',
		url: url,
		dataType: 'json',
		success: function(response) {
			if ((typeof response != 'undefined') && response != null && typeof response['downloaded_files_percent'] != 'undefined' && response['downloaded_files_percent'] != undefined) {
				download_result_shown = true;
				if (response['downloaded_files_count'] == 0) {
					jQuery("#staging_progress_bar_note").html('Processing Files('+response['total_files_count']+')');
					return false;
				} else {
					jQuery("#staging_progress_bar_note").html('Downloading Files...');
				}
				jQuery(".staging_progress_bar").css('width', response['downloaded_files_percent']+'%');
				console.log(response['total_files_count'],' / ', response['downloaded_files_count']);
			} else if(typeof response['copied_files_percent'] != 'undefined' && response['copied_files_percent'] != undefined) {
				// is_staging_running_wptc(true);
				jQuery("#staging_progress_bar_note").html("Copying files to it\'s respective places...");
				jQuery(".staging_progress_bar").css('width', response['copied_files_percent']+'%');
				console.log(response['total_files_count'],' / ', response['copied_files_count']);
				copy_result_shown = true;
			} else if(typeof download_result_shown != 'undefined' && download_result_shown && typeof copy_result_shown == 'undefined'){
				// jQuery(".staging_progress_bar").css('width', '100%');
				console.log("request from download");
				// is_staging_running_wptc();
			} else if(typeof copy_result_shown != 'undefined'){
				// jQuery(".staging_progress_bar").css('width', '100%');
				// is_staging_running_wptc();
			} else {
				console.log("un handled case");
			}
		},
		error: function(err) {
			if (err.status == 404 || err.status == 200) {
				is_staging_running_wptc();
			}
			console.log(err);
		}
	});
}

function get_stored_ftp_details(remove_error){
	jQuery.post(ajaxurl, {
		action: 'get_stored_ftp_details_wptc',
		remove_error: remove_error,
	}, function(data) {
		var data = jQuery.parseJSON(data);
		console.log(data);
		remove_prev_activity_ui();
		ftp_details_wptc();
		if(!data){
			return false;
		}
		jQuery("#ftp_host_wptc").val(data.host);
		jQuery("#ftp_username_wptc").val(data.username);
		jQuery("#ftp_password_wptc").val(data.password);
		jQuery("#ftp_port_wptc").val(data.port);
		jQuery("#select_target_ftp_wptc").val(data.remote_folder);
		jQuery("#full_url_ftp_wptc").val(data.destination_url);
		jQuery(":radio").removeAttr('checked');
		jQuery(":radio[value="+data.connect_type+"]").attr('checked', 'checked');
		jQuery(".wptc_ftp_use_passive").removeAttr('checked');
		if (data.passive) {
			jQuery(".wptc_ftp_use_passive").attr('checked', 'checked');
		}
	});
}

function delete_staging_site_wptc(){
	jQuery('#staging_delete_options').html('Removing database and files...');
	jQuery.post(ajaxurl, {
		action: 'delete_staging_wptc',
	}, function(data) {
		var data = jQuery.parseJSON(data);
		console.log(data);
		if (data == undefined || !data) {
			jQuery('#staging_current_progress').html('I cannot work without data');
			return false;
		}
		console.log(data);
		if (data.status === 'success') {
			jQuery('#staging_delete_options').addClass('success_wptc');
			if (data.deleted === 'both') {
				jQuery('#staging_delete_options').html('Staging site deleted completely !');
			} else if (data.deleted === 'files') {
				jQuery('#staging_delete_options').html('Files deleted completely but we cannot delete database !');
			} else if (data.deleted === 'db') {
				jQuery('#staging_delete_options').html('Database deleted completely but we cannot delete files !');
			} else {
				jQuery('#staging_delete_options').removeClass('.success_wptc').addClass('error_wptc');
				jQuery('#staging_delete_options').html('We could not delete staging site, please do it manually');
			}
			setTimeout(function(){
				parent.location.assign(parent.location.href);
			}, 3000);
		} else {
			jQuery('#staging_delete_options').addClass('error_wptc');
			jQuery('#staging_delete_options').html('We could not delete staging site, please do it manually');
		}
	});
}

function remove_prev_activity_ui(){
	if(window.location.href.indexOf('wp-time-capsule-staging-options') !== -1){
		jQuery('#dashboard_activity, .postbox').remove();
	}
}

function start_backup_staging_wptc(){
	wtc_start_backup_func('from_staging', 1);
}

function is_staging_running_wptc(flag){
	do_staging_status_call();
}

function do_staging_status_call(){
	disable_refresh_button_wptc();
	jQuery.post(ajaxurl, {
		action: 'current_staging_status_wptc',
	}, function(data) {
		enable_refresh_button_wptc();
        var data = jQuery.parseJSON(data);
		console.log('do_staging_status_call');
		console.log(data);
		if(data.staging_type === 'internal'){
			staging_type_wptc = 'internal';
		} else {
			staging_type_wptc = 'external';
		}
		if ((typeof data == 'undefined' && !data || (!data.message && !data.error) || (data.message == 'Analyzing files for staging' && !data.external_staging_requested && !data.is_staging_completed && !data.details) )) {
			console.log('staging is not installed here');
			update_last_sync_wptc();
			choose_staging_wptc();
			add_tool_tip_staging_wptc('not_staged_yet');
			return false;
		} else if(data.message == 'Analyzing files for staging' && data.external_staging_requested && !data.details && !data.is_staging_completed){
			staging_in_progress();
			update_last_sync_wptc();
			get_current_backup_status_wptc(1);
			add_tool_tip_staging_wptc('backup_progress');
			return false;
		}
		process_staging_status_wptc(data);
		update_last_sync_wptc();
	});
}

function show_backup_status_staging(backup_progress, progress_val){
	console.log(progress_val);
	console.log(backup_progress);
	if (jQuery('#staging_area_wptc').length > 0 && backup_progress != '') {
		if(progress_val == 100 ){
			if(jQuery('.staging_progress_bar').css('width') != '0px'){
				jQuery(".staging_progress_bar").css('width', progress_val+'%');
			}
		} else {
			setTimeout(function(){
				jQuery(".staging_progress_bar").css('width', progress_val+'%');
			}, 500)
		}
	}
}

function push_staging_button_wptc(){
	var extra_class = '';
	if(typeof staging_status_wptc == 'undefined' || staging_status_wptc === false){
		var extra_class = 'disabled button-disabled-staging-4-wptc';
	} else if(staging_status_wptc == 'progress' || staging_status_wptc == 'error'){
		var extra_class = 'disabled button-disabled-staging-1-wptc';
	} else if(staging_status_wptc == 'not_started'){
		var extra_class = 'disabled button-disabled-staging-2-wptc';
	} else if(staging_status_wptc == 'backup_progress'){
		var extra_class = 'disabled button-disabled-staging-3-wptc';
	}

	var current_path = window.location.href;
	if (current_path.toLowerCase().indexOf('update-core') !== -1) {
		jQuery('.upgrade-plugins-staging-wptc, .upgrade-themes-staging-wptc, .upgrade-translations-staging-wptc, .upgrade-core-staging-wptc, .plugin-update-from-iframe-staging-wptc').remove();
		var update_plugins = '&nbsp; <input class="upgrade-plugins-staging-wptc button '+extra_class+'" type="submit" value="Update in staging">';
		var update_themes = '&nbsp; <input class="upgrade-themes-staging-wptc button  '+extra_class+'" type="submit" value="Update in staging">';
		var update_translations = '&nbsp;<input class="upgrade-translations-staging-wptc button  '+extra_class+'" type="submit" value="Update in staging">';
		var update_core = '&nbsp;<input type="submit" class="upgrade-core-staging-wptc button button regular  '+extra_class+'" value="Update in staging">';
		var iframe_update = '<a class="plugin-update-from-iframe-staging-wptc button button-primary right  '+extra_class+'" style=" margin-right: 10px;">Update in staging</a>';
		jQuery('form[name=upgrade-plugins]').find('input[name=upgrade]').after(update_plugins);
		jQuery('form[name=upgrade-themes]').find('input[name=upgrade]').after(update_themes);
		jQuery('form[name=upgrade]').find('input[name=upgrade]').after(update_core);
		jQuery('form[name=upgrade-translations]').find('input[name=upgrade]').after(update_translations);
		setTimeout(function(){
			jQuery("#TB_iframeContent").contents().find(".plugin-update-from-iframe-staging-wptc").remove();
			jQuery("#TB_iframeContent").contents().find("#plugin_update_from_iframe").after(iframe_update);
			if(jQuery("#TB_iframeContent").contents().find(".plugin-update-from-iframe-staging-wptc").length > 0){
				add_tool_tip_staging_wptc();
			}
		}, 5000);
	} else if(current_path.toLowerCase().indexOf('plugins.php') !== -1){
		jQuery('.wptc-span-spacing-staging , .update-link-plugins-staging-wptc , .button-action-plugins-staging-wptc').remove();
		var in_app_update = '<span class="wptc-span-spacing-staging">&nbsp;or</span> <a href="#" class="update-link-plugins-staging-wptc  '+extra_class+'">Update in staging</a>';
		var selected_update = '<span class="wptc-span-spacing-staging">&nbsp</span><input type="submit" class="button-action-plugins-staging-wptc button  '+extra_class+'" value="Update in staging">';
		var iframe_update = '<a class="plugin-update-from-iframe-staging-wptc button button-primary right  '+extra_class+'" style=" margin-right: 10px;">Update in staging</a>';
		jQuery('form[id=bulk-action-form]').find('.update-link').after(in_app_update);
		jQuery('form[id=bulk-action-form]').find('.button.action').after(selected_update);
		setTimeout(function(){
			jQuery("#TB_iframeContent").contents().find(".plugin-update-from-iframe-staging-wptc").remove();
			jQuery("#TB_iframeContent").contents().find("#plugin_update_from_iframe").after(iframe_update);
			if(jQuery("#TB_iframeContent").contents().find(".plugin-update-from-iframe-staging-wptc").length > 0){
				add_tool_tip_staging_wptc();
			}
			add_tool_tip_staging_wptc();
		}, 5000);
	} else if(current_path.toLowerCase().indexOf('plugin-install.php') !== -1){
		jQuery('.update-now-plugins-staging-wptc, .plugin-update-from-iframe-staging-wptc').remove();
		var in_app_update = '<li><a class="button update-now-plugins-staging-wptc '+extra_class+'" href="#">Update in staging</a></li>';
		var iframe_update = '<a class="plugin-update-from-iframe-staging-wptc button button-primary right  '+extra_class+'" style=" margin-right: 10px;">Update in staging</a>';
		setTimeout(function(){
			jQuery("#TB_iframeContent").contents().find(".plugin-update-from-iframe-staging-wptc").remove();
			jQuery("#TB_iframeContent").contents().find("#plugin_update_from_iframe").after(iframe_update);
			add_tool_tip_staging_wptc();
			if(jQuery("#TB_iframeContent").contents().find(".plugin-update-from-iframe-staging-wptc").length > 0){
				add_tool_tip_staging_wptc();
			}
		}, 5000);
		jQuery('.plugin-action-buttons .update-now.button').parents('.plugin-action-buttons').append(in_app_update);
	} else if(current_path.toLowerCase().indexOf('themes.php?theme=') !== -1){
		var update_link = jQuery('.wptc-span-spacing-staging ~ #update-theme-staging-wptc');
		var spacing = jQuery('.wptc-span-spacing-staging ~ #update-theme-staging-wptc').siblings('.wptc-span-spacing-staging');
		jQuery(update_link).remove();
		jQuery(spacing).remove();
		var popup_update = '<span class="wptc-span-spacing-staging">&nbsp;or</span> <a href="#" id="update-theme-staging-wptc" class=" '+extra_class+'">Update in staging</a>';
		jQuery('#update-theme').after(popup_update);
		add_tool_tip_staging_wptc();
	} else if(current_path.toLowerCase().indexOf('themes.php') !== -1){
		jQuery('.button-link-themes-staging-wptc, .button-action-plugins-staging-wptc , #update-theme-staging-wptc, .wptc-span-spacing-staging, .button-action-plugins-staging-wptc').remove();
		var in_app_update = '<span class="wptc-span-spacing-staging">&nbsp;or </span><button class="button-link-themes-staging-wptc button-link  '+extra_class+'" type="button">Update in staging</button>';
		var selected_update = '<span class="wptc-span-spacing-staging">&nbsp;</span><input type="submit" class="button-action-plugins-staging-wptc button  '+extra_class+'" value="Update in staging">';
		jQuery('.button-link[type=button]').not('.wp-auth-check-close, .button-link-themes-staging-wptc, .button-link-themes-bbu-wptc').after(in_app_update);
		jQuery('form[id=bulk-action-form]').find('.button.action').after(selected_update);
	}
	setTimeout(function (){
		jQuery('.theme').on('click', '.button-link-themes-staging-wptc , #update-theme', function(e) {
			handle_theme_button_link_request_wptc(this, e, false, true);
		});
	}, 1000);

	setTimeout(function (){
		jQuery('#update-theme-staging-wptc').on('click', function(e) {
			handle_theme_link_request_wptc(this, e, false, true)
		});
	}, 500);
	is_staging_running_wptc();
}

function save_stage_n_update_wptc(update_items, type){
	jQuery.post(ajaxurl, {
		action: 'save_stage_n_update_wptc',
		update_items: update_items,
		type: type,
	}, function(data) {
		console.log(data);
	});
}

function add_tool_tip_staging_wptc(type){
	if(type){
		add_tool_tip_staging_wptc_type = type;
	} else {
		if(typeof add_tool_tip_staging_wptc_type != 'undefined'){
			type = add_tool_tip_staging_wptc_type;
		}
	}
	var class_staging_in_update = '.upgrade-plugins-staging-wptc, .upgrade-themes-staging-wptc, .upgrade-translations-staging-wptc, .upgrade-core-staging-wptc, .update-link-plugins-staging-wptc, .button-action-plugins-staging-wptc, .plugin-update-from-iframe-staging-wptc , .update-now-plugins-staging-wptc, .button-link-themes-staging-wptc, .button-action-plugins-staging-wptc, #update-theme-staging-wptc';
	var class_bbu_in_update = "#update-theme-bbu-wptc, .update-link-plugins-bbu-wptc , .upgrade-plugins-bbu-wptc, .upgrade-themes-bbu-wptc, .upgrade-translations-bbu-wptc, .upgrade-core-bbu-wptc, .plugin-update-from-iframe-bbu-wptc, .update-link-plugins-bbu-wptc, .button-action-plugins-bbu-wptc, .update-now-plugins-bbu-wptc, .button-link-themes-bbu-wptc, .button-action-plugins-bbu-wptc";
	if(type === 'staging_running'){
		jQuery(class_staging_in_update).each(function(tagElement , key) {
			jQuery(key).opentip('Staging is running. Please wait until it finishes.', { style: "dark" });
		});

		jQuery(class_bbu_in_update).each(function(tagElement , key) {
			jQuery(key).addClass('disabled button-disabled-bbu-from-staging-wptc');
			jQuery(key).opentip('Staging is running. Please wait until it finishes.', { style: "dark" });
		});
	} else if(type === 'not_staged_yet'){
		jQuery(class_staging_in_update).each(function(tagElement , key) {
			jQuery(key).opentip('Set up a staging in WP Time Capsule -> Staging.', { style: "dark" });
		});
	} else if(type === 'backup_progress'){
		jQuery(class_staging_in_update).each(function(tagElement , key) {
			jQuery(key).opentip('Backup in progress. Please wait until it finishes', { style: "dark" });
		});
	} else if(type === 'staging_error'){
		jQuery(class_staging_in_update).each(function(tagElement , key) {
			jQuery(key).opentip('Previous staging failed. Please fix it.', { style: "dark" });
		});
	} else if (type === 'staging_completed'){
		jQuery(class_staging_in_update).removeClass('disabled button-disabled-staging-1-wptc button-disabled-staging-2-wptc button-disabled-staging-3-wptc button-disabled-staging-4-wptc');
	} else {
		jQuery(class_staging_in_update).each(function(tagElement , key) {
			jQuery(key).opentip('You cannot stage now, Please try after sometime.', { style: "dark" });
		});
	}
}

function disable_staging_button_wptc(){
	// jQuery('.opentip-container').remove();
	jQuery('#select_same_server_wptc, #select_different_server_wptc, #same_server_submit_wptc').addClass('disabled').css('cursor', 'not-allowed');
	if(jQuery('#select_same_server_wptc').length > 0)
		jQuery('#select_same_server_wptc').opentip('Backup in progress. Please wait until it finishes', { style: "dark" });
	if(jQuery('#select_different_server_wptc').length > 0)
		jQuery('#select_different_server_wptc').opentip('Backup in progress. Please wait until it finishes', { style: "dark" });
	if(jQuery('#same_server_submit_wptc').length > 0)
		jQuery('#same_server_submit_wptc').opentip('Backup in progress. Please wait until it finishes', { style: "dark" });
}

function enable_staging_button_wptc(){
	setTimeout(function(){
		// jQuery('.opentip-container').remove();
		jQuery('#select_same_server_wptc, #select_different_server_wptc, #same_server_submit_wptc').removeClass('disabled').css('cursor', 'pointer');
	}, 2000)
}

function progress_staging_button_wptc(){
	// jQuery('.opentip-container').remove();
	jQuery('#select_same_server_wptc, #select_different_server_wptc, #same_server_submit_wptc').addClass('disabled').css('cursor', 'progress');
}