backupclickProgress = false;
started_fresh_backup = false;
last_cron_triggered_time_js_wptc = '';
jQuery(document).ready(function($) {
	get_current_backup_status_wptc(); //Get backup status on page reload
	get_admin_notices_wptc(); //show notices

	jQuery(document).keyup(function(e) {
		if(e.which == 27){
			dialog_close_wptc();
		}
	});
	status_area_wptc = "#bp_progress_bar_note, #staging_progress_bar_note";

	jQuery('body').on('click', '.sub_tree_class', function (){
		if (jQuery(this).hasClass('sub_tree_class') == true) {
			if (!jQuery(this).hasClass('selected')) {
				jQuery(this).addClass('selected');
				var this_file_name = jQuery(this).find('.folder').attr('file_name');
				jQuery.each( jQuery(this).nextAll(), function( key, value ) {
					jQuery.each( jQuery(value).find('.this_leaf_node'), function( key1, value1) {
						var parent_dir = jQuery(value1).find('.file_path').attr('parent_dir');
							if(jQuery(value1).hasClass('this_leaf_node') == true && parent_dir.indexOf(this_file_name) != -1 ){
								jQuery(value1).find('li').addClass('selected');
							}
					});
					jQuery.each( jQuery(value).find('.sub_tree_class'), function( key2, value2) {
						jQuery(value2).addClass('selected');
					});
				});
			} else {
				jQuery(this).removeClass('selected');
				jQuery.each( jQuery(this).nextAll(), function( key, value ) {
					jQuery.each( jQuery(value).find('.this_leaf_node'), function( key1, value1) {
							if(jQuery(value1).hasClass('this_leaf_node') == true){
								jQuery(value1).find('li').removeClass('selected');
							}
					});
					jQuery.each( jQuery(value).find('.sub_tree_class'), function( key2, value2) {
						jQuery(value2).removeClass('selected');
					});
				});
			}
		}
		if(jQuery(this).parents('.bu_files_list_cont').find('.selected').length > 0){
			jQuery(this).parents('.bu_files_list_cont').parent().find('.this_restore').removeClass('disabled');
		} else {
			jQuery(this).parents('.bu_files_list_cont').parent().find('.this_restore').addClass('disabled');
		}
	});

	jQuery('body').on('click', '.this_leaf_node li', function (){
		if(!jQuery(this).hasClass('selected')){
			jQuery(this).addClass('selected');
		} else {
			jQuery(this).removeClass('selected');
		}
		if(jQuery(this).parents('.bu_files_list_cont').find('.selected').length > 0){
				jQuery(this).parents('.bu_files_list_cont').parent().find('.this_restore').removeClass('disabled');
		} else {
				jQuery(this).parents('.bu_files_list_cont').parent().find('.this_restore').addClass('disabled');
		}
	});

	jQuery('body').on('click', '#save_manual_backup_name_wptc', function (){
		if (jQuery(this).hasClass('disabled')) {
			return false;
		}
		var custom_name = jQuery("#manual_backup_custom_name").val();
		if (!custom_name) {
			jQuery("#manual_backup_custom_name").css('border-color','#cc0000').focus();
			return false;
		}
		jQuery(this).addClass('disabled').html('Saving...');
	   save_manual_backup_name_wptc(custom_name);
	});

	jQuery("#report_issue").on("click", function(e) {
		if (jQuery(this).text() == 'Report issue') {
			e.preventDefault();
			e.stopImmediatePropagation();
			issue_repoting_form();
		}
	});

	jQuery("#form_report_close, .close").on("click", function() {
		tb_remove();
	});

	jQuery("body").on("click", ".notice-dismiss", function() {
		jQuery('.notice, #update-nag').remove();
	});

	jQuery("#start_backup_from_settings").on("click", function() {
		if (jQuery("#start_backup_from_settings").hasClass('disabled')) {
			console.log('button disabled');
			return false;
		}
		start_manual_backup_wptc(this);
	});

	jQuery(".test_cron_wptc").on("click", function() {
		test_connection_wptc_cron();
	});

	jQuery('body').on('click', '.dialog_close, .close', function (){
		if (!jQuery(this).hasClass('no_exit_restore_wptc')) {
			dialog_close_wptc();
		}
	});

	jQuery("#show_file_db_exp_for_exc, #exc_files_db_cancel").on("click", function() {
	   change_init_setup_button_state();
	});

	jQuery("#wptc_save_changes, #exc_files_db_save").on("click", function() {
		if (jQuery(this).hasClass('disabled')) {
			return false;
		}
		jQuery('#calculating_file_db_size_temp, #show_final_size').toggle();
		jQuery(this).addClass('disabled').attr('disabled', 'disabled').val('Saving new changes...').html('Saving...');
		jQuery('#exc_files_db_cancel, #exc_files_db_save').css('color','#c4c4c4').bind('click', false);
		save_settings_wptc();
		return false;
	});

	jQuery("body").on("click", "#send_issue_wptc", function() {
		var issueForm = jQuery("#TB_window form").serializeArray();
		if ((issueForm[0]['value'] == "") && (issueForm[1]['value'] == "")) {
			$("input[name='cemail']").css("box-shadow", "0px 0px 2px #FA1818");
			$("textarea[name='desc']").css("box-shadow", "0px 0px 2px #FA1818");
		} else if (issueForm[0]['value'] == "") {
			$("input[name='cemail']").css("box-shadow", "0px 0px 2px #FA1818");
		} else if (issueForm[1]['value'] == "") {
			$("input[name='cemail']").css("box-shadow", "0px 0px 2px #028202");
			$("textarea[name='desc']").css("box-shadow", "0px 0px 2px #FA1818");
		} else {
			$("input[name='cemail']").css("box-shadow", "0px 0px 2px #028202");
			$("textarea[name='desc']").css("box-shadow", "0px 0px 2px #028202");
			sendWTCIssueReport(issueForm);
		}
	});

	jQuery("body").on("click", "#cancel_issue", function() {
		tb_remove();
	});

	jQuery("body").on("click", "#cancel_issue_notice", function() {
		mark_update_pop_up_shown();
		tb_remove();
	});

	jQuery("body").on("click", "#refresh-c-status-area-wtpc", function() {
		if (jQuery(this).hasClass('disabled')) {
			return false;
		}
		get_current_backup_status_wptc();
	});

	jQuery(".report_issue_wptc").on('click', function(e) {
		e.preventDefault();
		e.stopImmediatePropagation();
		jQuery('.notice, #update-nag').remove();
		var log_id = $(this).attr('id');
		if (log_id != "" && log_id != 'undefined') {
			var rdata = {
				log_id: log_id
			};
			jQuery.post(ajaxurl, {
				action: 'get_issue_report_specific_wptc',
				data: rdata
			}, function(data) {
				var data = jQuery.parseJSON(data);
				var form_content = '<div class=row-wptc style="padding: 0 0 49px 0;"><div class="wptc-float-left">Send From</div><div class="wptc-float-right"><input type="text" style="width:96%" name="cemail" value="' + data.cemail + '"></div></div><div class=row-wptc style="padding: 0 0 3px 0;height: 132px;"><div class="wptc-float-left">Description</div><div class="wptc-float-right" style=""><textarea cols="37" rows="5" name="desc"></textarea></div></div><div class="row-wptc" style="padding: 0 0 3px 0;" ><div class="wptc-float-right" style="padding: 0 0 9px 0;">The report and other logs of the task will be sent.</div><input type="hidden" name="issuedata" id="panelHistoryContent" value=\'' + data.idata + '\'><input type="hidden" name="issue_user_name" id="issue_user_name" value=\'' + data.fname + '\'></div><div class="row-wptc" style="padding: 0 0 49px 0;"><div class="wptc-float-right"><input id="send_issue_wptc" class="button button-primary" type="button" value="Send"><input id="cancel_issue" style="margin-left: 3%;" class="button button-primary" type="button" value="Cancel"></div></div>';
				var dialog_content = '<div class="this_modal_div" style="background-color: #f1f1f1;font-family: \'open_sansregular\' !important;color: #444;padding: 0px 35px 35px 35px; width: 450px; left:20%; z-index:1000"><span class="dialog_close" id="form_report_close"></span><div class="pu_title">Send Report</div><form name="issue_form" id="issue_form">' + form_content + '</form></div>';
				remove_other_thickbox_wptc();
				jQuery("#wptc-content-id").html(dialog_content);
				jQuery(".wptc-thickbox").click();
				styling_thickbox_tc('report_issue');
			});
		}
	});

	jQuery("#clear_log").on('click', function() {
		var dialog_content = '<div class="this_modal_div" style="background-color: #f1f1f1;font-family: \'open_sansregular\' !important;color: #444;padding: 0px 34px 26px 34px; left:20%; z-index:1000"><span class="dialog_close"></span><div class="pu_title">Delete Confirmation</div><div class="wcard clearfix" style="width:480px"><div class="l1">Are you sure you want to permanently delete the logs?</div><a style="margin-left: 14%;" class="btn_pri" id="yes_del_log" onclick="yes_delete_logs()">Yes. Delete All Logs</a><a class="btn_sec" id="cancel_issue">Cancel</a></div></div>';
		remove_other_thickbox_wptc();
		jQuery("#wptc-content-id").html(dialog_content); //since it is the first call we are generating thickbox like this
		jQuery(".wptc-thickbox").click();
		styling_thickbox_tc('change_account');
	});

	jQuery("#toggle_exlclude_files_n_folders").on("click", function(e){
		e.stopImmediatePropagation();
		e.preventDefault();
		jQuery("#wptc_exc_files").toggle();
		if (jQuery("#wptc_exc_files").css('display') === 'block') {
			fancy_tree_init_exc_files_wptc();
		}
		return false;
	});

	jQuery("#wptc_init_toggle_files").on("click", function(e){
		e.stopImmediatePropagation();
		e.preventDefault();
		if (jQuery("#wptc_exc_files").css('display') === 'block') {
			return false;
		}
		jQuery("#wptc_exc_files").toggle();
		if (jQuery("#wptc_exc_files").css('display') === 'block') {
			if (typeof wptc_file_size_in_bytes != 'undefined') {
				jQuery("#included_file_size").html(convert_bytes_to_hr_format(wptc_file_size_in_bytes));
				jQuery("#file_size_in_bytes").html(wptc_file_size_in_bytes);
			}
			fancy_tree_init_exc_files_wptc(1);

		}
		return false;
	});

	jQuery("#toggle_wptc_db_tables").on("click", function(e){
		e.stopImmediatePropagation();
		e.preventDefault();
		jQuery("#wptc_exc_db_files").toggle();
		if (jQuery("#wptc_exc_db_files").css('display') === 'block') {
			fancy_tree_init_exc_tables_wptc();
		}
		return false;
	});

	jQuery("#wptc_init_toggle_tables").on("click", function(e){
		e.stopImmediatePropagation();
		e.preventDefault();
		if (jQuery("#wptc_exc_db_files").css('display') === 'block') {
			return false;
		}
		jQuery("#wptc_exc_db_files").toggle();
		if (jQuery("#wptc_exc_db_files").css('display') === 'block') {
			fancy_tree_init_exc_tables_wptc(1);
		}
		return false;
	});

	jQuery('body').on('click', '.resume_backup_wptc', function() {
		resume_backup_wptc();
	});

	jQuery('body').on('click', '.close-image-wptc', function() {
		jQuery(this).remove();
	});

	jQuery('body').on('click', '.change_dbox_user_tc', function(e) {
		if (jQuery(this).hasClass('wptc-link-disabled')) {
			e.stopImmediatePropagation();
			e.preventDefault();
			return false;
		}
	});

	jQuery('#connect_to_cloud, #save_g_drive_refresh_token').on("click", function() {
		jQuery('.cloud_error_mesg, .cloud_error_mesg_g_drive_token').html('');
		var cloud_type_wptc = $(this).attr("cloud_type");
		var auth_url_func = '';
		wptc_gdrive_token_btn = false;
		var data = {};

		if (cloud_type_wptc == 'dropbox') {
			auth_url_func = 'get_dropbox_authorize_url_wptc';
			cloud_type = 'Dropbox';
		} else if (cloud_type_wptc == 'g_drive') {
			if(jQuery('#gdrive_refresh_token_input_wptc').is(':visible') && this.id === 'save_g_drive_refresh_token' ){
				if(jQuery('#gdrive_refresh_token_input_wptc').val().length < 1){
					jQuery('.cloud_error_mesg_g_drive_token').html('Please enter the token !').show();
					return false;
				}
				wptc_gdrive_token_btn = true;
				data['g_drive_refresh_token'] = jQuery('#gdrive_refresh_token_input_wptc').val();
			}
			auth_url_func = 'get_g_drive_authorize_url_wptc';
			cloud_type = 'Google Drive';
		} else if (cloud_type_wptc == 's3') {
			data['as3_access_key']      = jQuery('#as3_access_key').val();
			data['as3_secure_key']      = jQuery('#as3_secure_key').val();
			data['as3_bucket_region']   = jQuery('#as3_bucket_region').val();
			data['as3_bucket_name']     = jQuery('#as3_bucket_name').val();
			auth_url_func = 'get_s3_authorize_url_wptc';
			cloud_type = 'Amazon S3';
		}
		// jQuery(this).attr('disabled', 'disabled').addClass('disabled').val('Redirecting...');
		jQuery('.cloud_error_mesg').removeClass('cloud_acc_connection_error').html('').hide();
		if (auth_url_func != '') {
			jQuery.post(ajaxurl, {
				action: auth_url_func,
				credsData: data
			}, function(data) {
				try{
					var obj = jQuery.parseJSON(data);
				} catch (e){
					// console.log(data);
					if (typeof wptc_gdrive_token_btn != 'undefined' && wptc_gdrive_token_btn) {
						jQuery('.cloud_error_mesg_g_drive_token').addClass('cloud_acc_connection_error').html(data).show();
						delete wptc_gdrive_token_btn;
					} else {
						jQuery('.cloud_error_mesg').addClass('cloud_acc_connection_error').html(data).show();
					}
					jQuery('#connect_to_cloud').removeClass('disabled').removeAttr("disabled").val('Connect to '+cloud_type);
					return false;
				}
				if (typeof obj.error != 'undefined') {
					return false;
				}

				if (typeof obj.status != 'undefined' && obj.status === 'connected') {
					console.log('connected');
				}
				parent.location.assign(obj.authorize_url);
			});
		}
	});

	if (typeof Clipboard != 'undefined') {
		var clipboard = new Clipboard("#copy_gdrive_token_wptc");
		if (clipboard != undefined) {
			clipboard.on("success", function(e) {
				jQuery("#gdrive_token_copy_message_wptc").show();
				setTimeout( function (){
					jQuery("#gdrive_token_copy_message_wptc").hide();
				},1000);
				e.clearSelection();
			});
			clipboard.on("error", function(e) {
				jQuery("#copy_gdrive_token_wptc").remove();
				jQuery("#gdrive_refresh_token_wptc").click(function(){jQuery(this).select();});
			});
		}else{
			jQuery("#gdrive_refresh_token_wptc").click(function(){jQuery(this).select();});
		}
	}

	//Init setup js
	jQuery('#start_backup_from_settings').click(function(){
		if (jQuery("#start_backup_from_settings").hasClass('disabled')) {
			console.log('button disabled');
			return false;
		}
		start_manual_backup_wptc(this);
	});

	jQuery("#backup_type").on('change', function(){
		var cur_backup_type = jQuery(this).val();
		if(cur_backup_type == 'WEEKLYBACKUP' || cur_backup_type == 'AUTOBACKUP'){
			jQuery('#select_wptc_default_schedule').hide();
			jQuery('.init_backup_time_n_zone').html('Timezone');
		} else {
			jQuery('#select_wptc_default_schedule').show();
			jQuery('.init_backup_time_n_zone').html('Backup Schedule and Timezone');
		}
	});

	jQuery("#select_wptc_cloud_storage").on('change', function(){
		jQuery(".creds_box_inputs", this_par).hide();
		jQuery('#connect_to_cloud').show();
		jQuery('#s3_seperate_bucket_note, #see_how_to_add_refresh_token_wptc, #gdrive_refresh_token_input_wptc, #google_token_add_btn, #google_limit_reached_text_wptc').hide();
		jQuery('.dummy_select, .wptc_error_div').remove();

		jQuery(".cloud_error_mesg, .cloud_error_mesg_g_drive_token").hide();
		var cur_cloud = jQuery(this).val();
		if(cur_cloud == ""){
			return false;
		}
		var cur_cloud_label = get_cloud_label_from_val_wptc(cur_cloud);
		var this_par = jQuery(this).closest(".wcard");
		jQuery("#connect_to_cloud, #save_g_drive_refresh_token").attr("cloud_type", cur_cloud);
		jQuery("#connect_to_cloud").val("Connect to " + cur_cloud_label).show();
		jQuery("#mess").show();
		jQuery("#donot_touch_note").show();
		jQuery("#donot_touch_note_cloud").html(cur_cloud_label);

		if(cur_cloud == 's3'){
			jQuery("#mess, #s3_seperate_bucket_note").toggle();
			if (check_cloud_min_php_min_req.indexOf('s3') == -1) {
				jQuery(".cloud_error_mesg").show();
				jQuery(".cloud_error_mesg").html('Amazon S3 requires PHP v5.3.3+. Please upgrade your PHP to use Amazon S3.');
				jQuery('#connect_to_cloud').hide();
				return false;
			}
			jQuery(".s3_inputs", this_par).show();
		}
		else if(cur_cloud == 'g_drive'){
			if (check_cloud_min_php_min_req.indexOf('gdrive') == -1) {
				jQuery(".cloud_error_mesg").show();
				jQuery(".cloud_error_mesg").html('Google Drive requires PHP v5.4.0+. Please upgrade your PHP to use Google Drive.');
				jQuery('#connect_to_cloud').hide();
				return false;
			}
			jQuery('#see_how_to_add_refresh_token_wptc, #gdrive_refresh_token_input_wptc, #google_token_add_btn, #google_limit_reached_text_wptc').show();
			if (jQuery('#google_token_add_btn').length) {
				jQuery("#connect_to_cloud, #save_g_drive_refresh_token").attr("cloud_type", cur_cloud);
				jQuery("#connect_to_cloud").val("Connect to " + cur_cloud_label).show();
			}
			jQuery(".g_drive_inputs", this_par).show();
		}
	});

	jQuery(".wcard").on('keypress', '#wptc_main_acc_email', function(e){
		triggerLoginWptc(e);
	});

	jQuery(".wcard").on('keypress', '#wptc_main_acc_pwd', function(e){
		triggerLoginWptc(e);
	});

});

function fancy_tree_init_exc_tables_wptc(call_from_init){
	if (call_from_init) {
		jQuery('#wptc_init_table_div').css('position', 'absolute');
	}
	jQuery("#wptc_exc_db_files").fancytree({
		checkbox: false,
		selectMode: 2,
		icon:false,
		debugLevel:0,
		// clickFolderMode: 3,
		source: {
			url: ajaxurl,
			data: (call_from_init == undefined) ? {"action": "wptc_get_tables"} : {"action": "wptc_get_init_tables"},
		},
		postProcess: function(event, data) {
			data.result = data.response;
			if (typeof wptc_db_size_in_bytes != 'undefined') {
				jQuery("#included_db_size").html(convert_bytes_to_hr_format(wptc_db_size_in_bytes));
				jQuery("#db_size_in_bytes").html(wptc_db_size_in_bytes);
			}

		},
		init: function (event, data) {
			data.tree.getRootNode().visit(function (node) {
				if (node.data.preselected) node.setSelected(true);
			});
		},
		renderNode: function(event, data){

		},
		loadChildren: function(event, ctx) {
			// ctx.node.fixSelection3AfterClick();
			// ctx.node.fixSelection3FromEndNodes();
			last_lazy_load_call = jQuery.now();
		},
		select: function(event, data) {
			//add code when you bring checkbox selection(from 1.8.4)
		},
		dblclick: function(event, data) {
			return false;
		},
		keydown: function(event, data) {
			if( event.which === 32 ) {
				data.node.toggleSelected();
				return false;
			}
		},
		cookieId: "fancytree-Cb3",
		idPrefix: "fancytree-Cb3-"
	}).on("mouseenter", '.fancytree-node', function(event){
		// Add a hover handler to all node titles (using event delegation)
		var node = jQuery.ui.fancytree.getNode(event);
		jQuery(node.span).addClass('fancytree-background-color');
		jQuery(node.span).find('.fancytree-size-key').hide();
		jQuery(node.span).find(".fancytree-table-include-key, .fancytree-table-exclude-key").remove();
		if(node.selected){
			jQuery(node.span).append("<span role='button' class='fancytree-table-exclude-key'><a>Exclude</a></span>");
		} else {
			jQuery(node.span).append("<span role='button' class='fancytree-table-include-key'><a>Include</a></span>");
		}
	}).on("mouseleave", '.fancytree-node' ,function(event){
		// Add a hover handler to all node titles (using event delegation)
		var node = jQuery.ui.fancytree.getNode(event);
		if (node && typeof node.span != 'undefined') {
			jQuery(node.span).find('.fancytree-size-key').show();
			jQuery(node.span).find(".fancytree-table-include-key, .fancytree-table-exclude-key").remove();
			jQuery(node.span).removeClass('fancytree-background-color');
			jQuery(node.span).removeClass('fancytree-background-color');
		}
	}).on("click", '.fancytree-table-exclude-key' ,function(event){
		var node = jQuery.ui.fancytree.getNode(event);
		node.removeClass('fancytree-partsel fancytree-selected');
		node.partsel = node.selected = false;
		jQuery(node.span).find(".fancytree-table-include-key, .fancytree-table-exclude-key").remove();
		save_inc_exc_data_wptc('exclude_table_list_wptc', node.key, false);
	}).on("click", '.fancytree-table-include-key' ,function(event){
		var node = jQuery.ui.fancytree.getNode(event);
		node.addClass('fancytree-selected');
		node.selected = true;
		jQuery(node.span).find(".fancytree-table-include-key, .fancytree-table-exclude-key").remove();
		save_inc_exc_data_wptc('include_table_list_wptc', node.key, false);
	});

}



function fancy_tree_init_exc_files_wptc(call_from_init){
	jQuery("#wptc_exc_files").fancytree({
		checkbox: false,
		selectMode: 3,
		clickFolderMode: 3,
		debugLevel:0,
		source: {
			url: ajaxurl,
			data: (call_from_init == undefined) ? {"action": "wptc_get_root_files"} : {"action": "wptc_get_init_root_files"},
		},
		postProcess: function(event, data) {
			data.result = data.response;
		},
		init: function (event, data) {
			data.tree.getRootNode().visit(function (node) {
				if (node.data.preselected) node.setSelected(true);
				if (node.data.partial) node.addClass('fancytree-partsel');
			});
		},
		lazyLoad: function(event, ctx) {
			var key = ctx.node.key;
			ctx.result = {
				url: ajaxurl,
				data: (call_from_init == undefined) ? {"action": "wptc_get_files_by_key", "key" : key} : {"action": "wptc_get_init_files_by_key", "key" : key},
			};
		},
		renderNode: function(event, data){ // called for every toggle
			if (!data.node.getChildren())
				return false;
			if(data.node.expanded === false){
				data.node.resetLazy();
			}
			jQuery.each( data.node.getChildren(), function( key, value ) {
				if (value.data.preselected){
					value.setSelected(true);
				} else {
					value.setSelected(false);
				}
			// 	// if (value.data.partial){value.addClass('fancytree-partsel');} else {value.removeClass('fancytree-partsel');}
			// 	// if (value.getParent().data.partial){value.getParent().addClass('fancytree-partsel');} else {value.getParent().removeClass('fancytree-partsel');}
			});
		},
		loadChildren: function(event, data) {
			data.node.fixSelection3AfterClick();
			data.node.fixSelection3FromEndNodes();
			// if (data.node.data.partial && data.node.selected ){
				// console.log("unselected size : " + data.node.data.size_in_bytes);
				// var current_size = parseInt(jQuery("#file_size_in_bytes").html());
				// var updated_size = current_size - parseInt(data.node.data.size_in_bytes);
				// console.log("updated size  : " + updated_size);
				// jQuery("#file_size_in_bytes").html(updated_size);
				// jQuery("#included_file_size").html(convert_bytes_to_hr_format(updated_size));
			// }
			last_lazy_load_call = jQuery.now();
		},
		select: function(event, data) {
			//add code when you bring checkbox selection(from 1.8.4)
		},
		dblclick: function(event, data) {
			return false;
			// data.node.toggleSelected();
		},
		keydown: function(event, data) {
			if( event.which === 32 ) {
				data.node.toggleSelected();
				return false;
			}
		},
		cookieId: "fancytree-Cb3",
		idPrefix: "fancytree-Cb3-"
	}).on("mouseenter", '.fancytree-node', function(event){
		// Add a hover handler to all node titles (using event delegation)
		var node = jQuery.ui.fancytree.getNode(event);
		if (	node &&
				typeof node.span != 'undefined'
				&& (!node.getParentList().length
						|| node.getParent().selected !== false
						|| node.getParent().partsel !== false
						|| (node.getParent()
							&& node.getParent()[0]
							&& node.getParent()[0].extraClasses
							&& node.getParent()[0].extraClasses.indexOf("fancytree-selected") !== false )
						|| (node.getParent()
							&& node.getParent()[0]
							&&node.getParent()[0].extraClasses
							&& node.getParent()[0].extraClasses.indexOf("fancytree-partsel") !== false )
							 )
				) {
			jQuery(node.span).addClass('fancytree-background-color');
			jQuery(node.span).find('.fancytree-size-key').hide();
			jQuery(node.span).find(".fancytree-file-include-key, .fancytree-file-exclude-key").remove();
			if(node.selected){
				jQuery(node.span).append("<span role='button' class='fancytree-file-exclude-key'><a>Exclude</a></span>");
			} else {
				jQuery(node.span).append("<span role='button' class='fancytree-file-include-key'><a>Include</a></span>");
			}
		}
	}).on("mouseleave", '.fancytree-node' ,function(event){
		// Add a hover handler to all node titles (using event delegation)
		var node = jQuery.ui.fancytree.getNode(event);
		if (node && typeof node.span != 'undefined') {
			jQuery(node.span).find('.fancytree-size-key').show();
			jQuery(node.span).find(".fancytree-file-include-key, .fancytree-file-exclude-key").remove();
			jQuery(node.span).removeClass('fancytree-background-color');
		}
	}).on("click", '.fancytree-file-exclude-key' ,function(event){
		var node = jQuery.ui.fancytree.getNode(event);
		var children = node.getChildren();
		jQuery.each(children, function( index, value ) {
			console.log(value.key);
			console.log(value.selected);
			value.selected = false;
			value.setSelected(false);
			value.removeClass('fancytree-partsel fancytree-selected')
		});
		folder = (node.folder) ? 1 : 0;
		node.removeClass('fancytree-partsel fancytree-selected');
		node.selected = false;
		node.partsel = false;
		jQuery(node.span).find(".fancytree-file-include-key, .fancytree-file-exclude-key").remove();
		save_inc_exc_data_wptc('exclude_file_list_wptc', node.key, folder);
	}).on("click", '.fancytree-file-include-key' ,function(event){
		var node = jQuery.ui.fancytree.getNode(event);
		var children = node.getChildren();
		jQuery.each(children, function( index, value ) {
			console.log(value.key);
			console.log(value.selected);
			value.selected = true;
			value.setSelected(true);
			value.addClass('fancytree-selected')
		});
		folder = (node.folder) ? 1 : 0;
		node.addClass('fancytree-selected');
		node.selected = true;
		jQuery(node.span).find(".fancytree-file-include-key, .fancytree-file-exclude-key").remove();
		save_inc_exc_data_wptc('include_file_list_wptc', node.key, folder);
	});

	return false;
}

function save_inc_exc_data_wptc(request, file, isdir){
	jQuery.post(ajaxurl, {
		action: request,
		data: {file : file, isdir : isdir},
	}, function(data) {
		console.log(data);
	});
}

function dialog_close_wptc(){
		tb_remove();
		if (backupclickProgress) {
			backupclickProgress = false;
		}
		if (typeof update_click_obj_wptc != 'undefined' && update_click_obj_wptc) {
			parent.location.assign(parent.location.href);
		}
}

function disable_refresh_button_wptc(){
	jQuery('#refresh-c-status-area-wtpc, #refresh-s-status-area-wtpc').css('opacity', '0.5').addClass('disabled');
}

function enable_refresh_button_wptc(){
	jQuery('#refresh-c-status-area-wtpc, #refresh-s-status-area-wtpc').css('opacity', '1').removeClass('disabled');
}

function is_email_and_pwd_not_empty_wptc() {
	if ((jQuery("#wptc_main_acc_pwd").val() !== '') && (jQuery("#wptc_main_acc_email").val() !== '')) {
		return true;
	}
	return false;
}

function get_sibling_files_wptc(obj){
	var file_name = jQuery(obj).attr('file_name');
	var backup_id = jQuery(obj).attr('backup_id');
	var recursive_count = parseInt(jQuery(obj).parent().siblings('.this_leaf_node').attr('recursive_count'));
	if(!recursive_count){
		recursive_count = parseInt((jQuery(obj).parent().attr('recursive_count'))) + 1;
	} else {
		recursive_count += 1;
	}
	last_lazy_load = obj;
	pushed_to_dom = 0;
	var trigger_filename = jQuery(obj).attr('file_name');
	var current_filename = '';
	var current_recursive_count = jQuery(obj).parents('.sub_tree_class').attr('recursive_count');
	jQuery.each( jQuery(obj).parents('.sub_tree_class').siblings(), function( key, value ) {
		if(jQuery(value).attr('recursive_count') > current_recursive_count){
			var current_filename = jQuery(value).find('.file_path').attr('parent_dir');
			if (current_filename == undefined) {
				var current_filename = jQuery(value).find('.folder').attr('parent_dir');
			}
			if (current_filename != undefined && current_filename.indexOf(trigger_filename) != -1) {
			   jQuery(value).remove();
			}
		}
	});
	jQuery(obj).parents('.sub_tree_class').find('.this_leaf_node').remove();
	if(jQuery(obj).hasClass('open')){
		jQuery(obj).removeClass('open').addClass('close');
		return false;
	} else {
		jQuery(obj).removeClass('close').addClass('loader');
	}
	jQuery.post(ajaxurl, {
		action: 'get_sibling_files_wptc',
		data: { file_name: file_name, backup_id: backup_id, recursive_count:recursive_count},
	}, function(data) {
	   if (typeof pushed_to_dom != 'undefined' && pushed_to_dom == 0) {
			jQuery(obj).removeClass('loader close').addClass('open');
			jQuery(last_lazy_load).parent().after(data)
			styling_thickbox_tc("");
			pushed_to_dom = 1;
	   }
		registerDialogBoxEventsTC();
		jQuery(obj).removeClass('disabled');
	});
}

function triggerLoginWptc(e) {
	if (!is_email_and_pwd_not_empty_wptc()) {
		return false;
	}
	var key = e.which;
	if (key == 13) {
		jQuery("#wptc_login").click();
		return false;
	}
}


function get_current_backup_status_wptc(dont_push_button) {
	disable_refresh_button_wptc();
	dont_push_button_wptc = dont_push_button;
	jQuery.post(ajaxurl, {
		action: 'progress_wptc'
	}, function(data) {

		enable_refresh_button_wptc();

		if (typeof data == 'undefined' || !data.length) {
			return false;
		}
		data = jQuery.parseJSON(data);

		if (data == 0) {
			delete reloadFuncTimeout;
			return false;
		}

		//Do not show users any notification now
		is_whitelabling_enabled_wptc = false;

		if(data.is_whitelabling_enabled){
			is_whitelabling_enabled_wptc = true;
			return false;
		}

		last_backup_time = data.last_backup_time;
		var progress_val = 0.0;
		var prog_con = '';
		var backup_progress = data.backup_progress;

		//Last backup taken
		if (typeof last_backup_time != 'undefined' && last_backup_time != null && last_backup_time) {
			// jQuery(status_area_wptc).text('Last backup taken : ' + last_backup_time );
		} else {
			// jQuery(status_area_wptc).text('No backups taken');
		}

		show_own_cron_status_wptc(data);

		//Notify backup failed
		if (data.start_backups_failed_server) {
			wptc_backup_start_failed_note(data.start_backups_failed_server);
			return false;
		}

		//get backup type
		if (data.starting_first_backup != 'undefined' && data.starting_first_backup) {
			backup_type = 'starting_backup' ;
		} else {
			backup_type = 'manual_backup';
		}

		if (backup_progress != '') {
			jQuery('.bp-progress-calender').show();
			progress_val = backup_progress.progress_percent;

			//First backup progress bar
			prog_con = '<div class="bp_progress_bar_cont"><span id="bp_progress_bar_note"></span><div class="bp_progress_bar" style="width:' + progress_val + '%"></div></div><span class="rounded-rectangle-box-wptc reload-image-wptc" id="refresh-c-status-area-wtpc"></span><div class="last-c-sync-wptc">Last reload: Processing...</div>';

			//Settings page UI
			disable_settings_wptc();
			disable_pro_button_wptc();
			showLoadingDivInCalendarBoxWptc(); //show calender page details
		} else {

			var this_percent = 0;
			var thisCompletedText = 'Initiating the backup...';

			//Will show after backup completed things
			if (data.progress_complete) {
				if (typeof backup_type != 'undefined' && backup_type != '') { // change after backup completed
					if(typeof backup_started_time_wptc == 'undefined' || (backup_started_time_wptc + 7000) <= jQuery.now()){
						this_percent = 100;
						thisCompletedText = '<span style="top: 3px; font-size: 13px;  position: relative; left: 10px;">Backup Completed</span>';
					}

					//redirect once first backup is done
					if (backup_type == 'starting_backup') {
						backup_type = '';
						// parent.location.assign(adminUrlWptc+'admin.php?page=wp-time-capsule-monitor');
					} else if(backup_type == 'manual_backup'){ //once manual backup is done check for other stuffs like staging.
						backup_type = '';
						setTimeout(function() {
							// tb_remove();
							if(typeof update_click_obj_wptc != 'undefined'){
								delete update_click_obj_wptc;
							}
							// if (typeof is_staging_running_wptc !== 'undefined' && jQuery.isFunction(is_staging_running_wptc)) {
							//     is_staging_running_wptc(true);
							// }
							if(typeof backup_before_update != 'undefined' && backup_before_update == 'yes'){
								delete backup_before_update;
								tb_remove();
								// parent.location.assign(parent.location.href);
							}
						}, 3000);
					}
				}
			}

			//checking some Dom element to show whether backup text needs to shown or not
			if ((progress_val == 0) && (jQuery('.progress_bar').css('width') != '0px') && ( jQuery('.wptc_prog_wrap').length == 0 ||  (jQuery('.bp_progress_bar').css('width') != '0px' && jQuery('.wptc_prog_wrap').length != 0 && jQuery('.bp_progress_bar').css('width') != undefined))){
				this_percent = 100;
				progress_val = 100;
				thisCompletedText = '<span style="top: 3px; font-size: 13px;  position: relative; left: 10px;">Backup Completed</span>';
			}

			//Once backup completed then check for backup before updates
			if (thisCompletedText == '<span style="top: 3px; font-size: 13px;  position: relative; left: 10px;">Backup Completed</span>') {

				if (window.location.href.indexOf('?page=wp-time-capsule&new_backup=set') !== -1 ){
					setTimeout(function(){
						parent.location.assign(adminUrlWptc+'admin.php?page=wp-time-capsule-monitor');
					}, 3000)
				}
				if(jQuery(".this_modal_div .backup_progress_tc .progress_cont").text().indexOf('Updating') === -1){
					jQuery('.bp-progress-calender').hide();
					prog_con = '<div class="bp_progress_bar_cont"><span id="bp_progress_bar_note"></span><div class="bp_progress_bar" style="width:' + this_percent + '%">' + thisCompletedText + '</div></div><span class="rounded-rectangle-box-wptc reload-image-wptc" id="refresh-c-status-area-wtpc"></span><div class="last-c-sync-wptc">Last reload: Processing...</div>';
				} else {
					thisCompletedText = 'Updated successfully.';
					prog_con = '<div class="bp_progress_bar_cont"><span id="bp_progress_bar_note"></span><div class="bp_progress_bar" style="width:' + this_percent + '%">' + thisCompletedText + '</div></div><span class="rounded-rectangle-box-wptc reload-image-wptc" id="refresh-c-status-area-wtpc"></span><div class="last-c-sync-wptc">Last reload: Processing...</div>';
				}
			} else {
				jQuery('.bp-progress-calender').show();
			}

			enable_settings_wptc();
			enable_pro_button_wptc();
			resetLoadingDivInCalendarBoxWptc();
		}

		//show backup percentage in staging area also
		if (typeof show_backup_status_staging !== 'undefined' && jQuery.isFunction(show_backup_status_staging)) {
			show_backup_status_staging(backup_progress, progress_val);
		}

		jQuery('.wptc_prog_wrap').html('');
		jQuery('.wptc_prog_wrap').append(prog_con);
		if (jQuery('.l1.wptc_prog_wrap').hasClass('bp-progress-first-bp')) {
			jQuery('.bp_progress_bar_cont').addClass('bp_progress_bar_cont-first-b-wptc');
			jQuery('.rounded-rectangle-box-wptc').addClass('rounded-rectangle-box-wptc-first-c-wptc');
		}

		//backup before update showing data
		if (typeof bbu_message_update_progress_bar !== 'undefined' && jQuery.isFunction(bbu_message_update_progress_bar)) {
			bbu_message_update_progress_bar(data);
		}

		//Showing all the data here
		process_backup_status(backup_progress, progress_val);

		//Load new update pop up
		load_custom_popup_wptc(data.user_came_from_existing_ver , 'new_updates')

		//show users error if any
		show_users_backend_errors_wptc(data.show_user_php_error)

		//If staging running do not start backup
		stop_starting_new_backups(data);
		show_notification_bar(data);
		if (dont_push_button_wptc !== 1) {
			push_extra_button_wptc(data);
		} else {
			dont_push_button_wptc = 0;
		}
		update_backup_status_in_staging(data);
		update_last_sync_wptc();
		if (typeof process_wtc_reload !== 'undefined' && jQuery.isFunction(process_wtc_reload)) {
			process_wtc_reload(data);
		}
	});
}

function get_admin_notices_wptc(){
	if (window.location.href.indexOf('wp-time-capsule-dev-options') !== -1) {
		return false;
	}
	if (window.location.href.indexOf('wp-time-capsule') === -1) {
		var is_wptc_page = 0;
	} else {
		var is_wptc_page = 1;
	}

	jQuery.post(ajaxurl, {
		action: 'get_admin_notices_wptc',
		is_wptc_page: is_wptc_page,
	}, function(data) {
		if (!data) {
			return false;
		}
		var obj = jQuery.parseJSON(data);
		if (jQuery.isEmptyObject(obj)) {
			return false;
		}
		add_notice_wptc(obj.msg, 1, obj.status);
	});
}

function update_last_sync_wptc(){
	jQuery('.last-c-sync-wptc, .last-s-sync-wptc').html('Last reload: '+gettime_wptc());
}

function stop_starting_new_backups(data){
	if (data.is_staging_running && data.is_staging_running == 1) {
		jQuery("#select_wptc_default_schedule, #wptc_timezone").addClass('disabled').attr('disabled', 'disabled');
		jQuery('#start_backup_from_settings').attr('action', 'disabled').addClass('disabled');
	}
}

function update_backup_status_in_staging(data){
	if (data.is_staging_running && data.is_staging_running == 1) {
		jQuery("#select_wptc_default_schedule, #wptc_timezone").addClass('disabled').attr('disabled', 'disabled');
		jQuery('#start_backup_from_settings').attr('action', 'disabled').addClass('disabled');
		jQuery('.change_dbox_user_tc').addClass('wptc-link-disabled');
		jQuery('.setting_backup_progress_note_wptc').show();
	}
}

function show_notification_bar(data){
	if (data.bbu_note_view) {
		jQuery('.success-bar-wptc, .error-bar-wptc, .warning-bar-wptc, .message-bar-wptc').remove();
		jQuery('.success-bar-wptc, .error-bar-wptc, .warning-bar-wptc, .message-bar-wptc', window.parent.document).remove();
		if(jQuery("#wpadminbar").length > 0){
			var adminbar = "#wpadminbar";
			var iframe = false;
		} else {
			var adminbar = jQuery('#wpadminbar', window.parent.document);
			var iframe = true;
		}
		if (data.bbu_note_view.type === 'success') {
			jQuery(adminbar).after("<div style='display:none' class='success-bar-wptc success-image-wptc close-image-wptc'><span id='bar-note-wptc'>"+data.bbu_note_view.note+"</span></div>");
				setTimeout(function(){
					if(iframe){
					   if(!jQuery('.success-bar-wptc', window.parent.document).is(':visible')){
							jQuery('.success-bar-wptc', window.parent.document).slideToggle(); //sample
					   }
					} else {
					   if(!jQuery('.success-bar-wptc').is(':visible')){
							jQuery('.success-bar-wptc').slideToggle(); //sample
					   }
					}
				   if (typeof clear_bbu_notes !== 'undefined' && jQuery.isFunction(clear_bbu_notes)) {
						clear_bbu_notes();
					}
				}, 1000);
		} else if (data.bbu_note_view.type === 'error') {
			jQuery(adminbar).after("<div style='display:none' class='error-bar-wptc error-image-wptc close-image-wptc'><span id='bar-note-wptc'>"+data.bbu_note_view.note+"</span></div>");
				setTimeout(function(){
					if(iframe){
					   if(!jQuery('.error-bar-wptc', window.parent.document).is(':visible')){
							jQuery('.error-bar-wptc', window.parent.document).slideToggle(); //sample
					   }
					} else {
					   if(!jQuery('.error-bar-wptc').is(':visible')){
							jQuery('.error-bar-wptc').slideToggle(); //sample
					   }
					}

				if (typeof clear_bbu_notes !== 'undefined' && jQuery.isFunction(clear_bbu_notes)) {
					clear_bbu_notes();
					}
				}, 1000);
		} else if (data.bbu_note_view.type === 'warning') {
			jQuery(adminbar).after("<div style='display:none' class='warning-bar-wptc warning-image-wptc close-image-wptc'><span id='bar-note-wptc'>"+data.bbu_note_view.note+"</span></div>");
				setTimeout(function(){
					if(iframe){
					   if(!jQuery('.warning-bar-wptc', window.parent.document).is(':visible')){
							jQuery('.warning-bar-wptc', window.parent.document).slideToggle(); //sample
					   }
					} else {
					   if(!jQuery('.warning-bar-wptc').is(':visible')){
							jQuery('.warning-bar-wptc').slideToggle(); //sample
					   }
					}

				if (typeof clear_bbu_notes !== 'undefined' && jQuery.isFunction(clear_bbu_notes)) {
					clear_bbu_notes();
					}
				}, 1000);
		} else if (data.bbu_note_view.type === 'message') {
			jQuery(adminbar).after("<div style='display:none' class='message-bar-wptc message-image-wptc close-image-wptc'><span id='bar-note-wptc'>"+data.bbu_note_view.note+"</span></div>");
				setTimeout(function(){
					if(iframe){
					   if(!jQuery('.message-bar-wptc', window.parent.document).is(':visible')){
							jQuery('.message-bar-wptc', window.parent.document).slideToggle(); //sample
					   }
					} else {
					   if(!jQuery('.message-bar-wptc').is(':visible')){
							jQuery('.message-bar-wptc').slideToggle(); //sample
					   }
					}

				if (typeof clear_bbu_notes !== 'undefined' && jQuery.isFunction(clear_bbu_notes)) {
					clear_bbu_notes();
					}
				}, 1000);
		}
	}
	// jQuery("#wpadminbar").after("<div style='display:block' class='warning-bar-wptc warning-image-wptc close-image-wptc'><span id='bar-note-wptc'>Just informed you</span></div>");
	// jQuery("#wpadminbar").after("<div style='display:block' class='message-bar-wptc message-image-wptc close-image-wptc'><span id='bar-note-wptc'>Just informed you</span></div>");
	// jQuery("#wpadminbar").after("<div style='display:block' class='error-bar-wptc error-image-wptc close-image-wptc'><span id='bar-note-wptc'>Just informed you</span></div>");
	// jQuery("#wpadminbar").after("<div style='display:block' class='info-bar-wptc error-image-wptc close-image-wptc'><span id='bar-note-wptc'>Just informed you</span></div>");
}

function push_extra_button_wptc(data){
	if (window.location.href.indexOf('update-core.php') === -1 && window.location.href.indexOf('plugins.php') === -1 && window.location.href.indexOf('themes.php') === -1 && window.location.href.indexOf('plugin-install.php') === -1){
		return false;
	}
	if (typeof push_staging_button_wptc !== 'undefined' && jQuery.isFunction(push_staging_button_wptc)) {
		push_staging_button_wptc(data);
	}
	if (typeof push_bbu_button_wptc !== 'undefined' && jQuery.isFunction(push_bbu_button_wptc)) {
		push_bbu_button_wptc(data);
	}
}

function disable_pro_button_wptc(){
	 if (typeof disable_staging_button_wptc !== 'undefined' && jQuery.isFunction(disable_staging_button_wptc)) {
		disable_staging_button_wptc();
	}
}

function enable_pro_button_wptc(){
	 if (typeof enable_staging_button_wptc !== 'undefined' && jQuery.isFunction(enable_staging_button_wptc)) {
		enable_staging_button_wptc();
	}
}

function show_own_cron_status_wptc(data){
	if (!data.wptc_own_cron_status || !data.user_logged_in) {
		return false;
	}
	if(typeof data.wptc_own_cron_status.status != 'undefined'){
		if (data.wptc_own_cron_status.status == 'success') {
			//leave it
		} else if (data.wptc_own_cron_status.status == 'error') {
			load_cron_status_failed_popup(data.wptc_own_cron_status.statusCode, data.wptc_own_cron_status.body, data.wptc_own_cron_status.cron_url, data.wptc_own_cron_status.ips, data.wptc_own_cron_status_notified);
			return false;
		}
	}
}

function disable_settings_wptc(){
	if (jQuery("#start_backup_from_settings").text() != 'Stopping backup...') {
		jQuery("#start_backup_from_settings").attr("action", "stop").text("Stop Backup");
		jQuery("#backup_button_status_wptc").text("Clicking on Stop Backup will erase all progress made in the current backup.");
		jQuery("#select_wptc_default_schedule, #wptc_timezone").addClass('disabled').attr('disabled', 'disabled');
		jQuery('.change_dbox_user_tc').addClass('wptc-link-disabled');
		jQuery('.setting_backup_stop_note_wptc').show();
		jQuery('.setting_backup_start_note_wptc').hide();
		jQuery('.setting_backup_progress_note_wptc').show();
	}
}


function enable_settings_wptc(){
	if (jQuery("#start_backup_from_settings").text() != 'Starting backup...') {
		jQuery("#start_backup_from_settings").attr("action", "start").text("Backup now");
		jQuery("#backup_button_status_wptc").text("Click Backup Now to backup the latest changes.");
		jQuery("#select_wptc_default_schedule, #wptc_timezone").removeClass('disabled').removeAttr('disabled');
		jQuery('.change_dbox_user_tc').removeClass('wptc-link-disabled');
		jQuery('.setting_backup_stop_note_wptc').hide();
		jQuery('.setting_backup_start_note_wptc').show();
		jQuery('.setting_backup_progress_note_wptc').hide();
	}
}

function show_backup_progress_dialog(obj, type) {
	//this function updates the progress bar in the dialog box ; during backup
	// jQuery(window.parent.document.body).find('iframe').remove();
	remove_other_thickbox_wptc();
	if (jQuery("#wptc-content-id").length === 0) {
		jQuery("#wptc-content-id").remove();
		jQuery(".wrap").append('<div id="wptc-content-id" style="display:none;"> <p> hidden cont. </p></div><a class="thickbox wptc-thickbox" style="display:none" href="#TB_inline?width=500&height=500&inlineId=wptc-content-id&modal=true"></a>');
	}

	// var dialog_content = '<div class="this_modal_div" style="background-color: #f1f1f1;font-family: \'open_sansregular\' !important;color: #444;padding: 0px 35px 35px 35px; width: 450px; left:20%; z-index:1000"><span class="dialog_close" style="display:none"></span><div class="wcard hii backup_progress_tc" style="height:60px; padding:0; margin-top:27px; display:none;"><div class="progress_bar" style="width:0%;"></div> <div class="progress_cont">Backing up files before updating...</div></div><div style="display:none;font-size: 12px; text-align:center">If you want to check the overall backup status of all your sites, <a href="https://service.wptimecapsule.com/" style="text-decoration:underline; cursor:pointer" target="_blank">click here</a>.</div><div id="manual_backup_name_div" style="font-size: 12px;text-align:center;padding-top: 27px;"><input type="text" id="manual_backup_custom_name" placeholder="Give this backup a name" style="border-radius: 3px;height: 35px;width: 50%;z-index: 1;position: relative;"><a class="button button-primary" id="save_manual_backup_name" style="margin: 1px 0px 0px -3px;height: 34px;z-index: 2;width: 15%;font-weight: 700;position: relative;border-top-right-radius: 5px;border-top-left-radius: 0px;line-height: 30px;border-bottom-left-radius: 0px;border-bottom-right-radius: 5px;">Save</a>'+bbu_note+'</div></div>';
	var dialog_content = ask_backup_name_wptc(type);
	if (dialog_content === false) {
		return false;
	}
	if(jQuery('.tc_backup_before_update').length > 0){
		tb_remove();
	} else if(jQuery('.wcard.clearfix').length != 0 || jQuery('#backup_to_dropbox_options h2').text() == 'Backups'){
		return;
	}
	if (type == 'fresh' && jQuery('.this_modal_div').is(':visible') === false) {
		jQuery("#wptc-content-id").html(dialog_content); //since it is the first call we are generating thickbox like this
		jQuery(".wptc-thickbox").click().removeClass('thickbox-loading');
		jQuery(".bp_progress_bar").text("Initiating the backup...");
		styling_thickbox_tc('progress');
	} else if ((type == 'bbu' || type == 'incremental' )&& jQuery('.this_modal_div').is(':visible') === false) {
		if (jQuery('#TB_window').length == 0 ) {
			jQuery("#wptc-content-id").html(dialog_content); //since it is the first call we are generating thickbox like this
		} else {
			if(jQuery('body').find('iframe').length){
				jQuery('body').find('iframe').remove();
				jQuery('body').find('#TB_window').remove();
				jQuery('body').find('#TB_overlay').remove();
				jQuery("#wptc-content-id").html(dialog_content); //since it is the first call we are generating thickbox like this
			}
		}
		jQuery(".wptc-thickbox").click().removeClass('thickbox-loading');
		jQuery(".bp_progress_bar").text("Initiating the backup...");
		styling_thickbox_tc('progress');
	} else if(jQuery('.this_modal_div').is(':visible') === false){
		jQuery("#TB_ajaxContent").html(dialog_content);
		styling_thickbox_tc('backup_yes');
	}
	jQuery('#manual_backup_custom_name').focus();

	jQuery('#manual_backup_custom_name').keypress(function (e) {
		var key = e.which;
		console.log(key);
		 if(key == 13) {
			jQuery('#save_manual_backup_name_wptc').click();
		  }
	});
	// tb_remove();
	// ask_backup_name_wptc();
	jQuery("#TB_load").remove();
	jQuery("#TB_window").css({'margin-left':'', 'left':'40%'});

	jQuery("#TB_overlay").on("click", function() {
		if (typeof is_backup_completed != 'undefined' && is_backup_completed == true && !on_going_restore_process) { //for enabling dialog close on complete
			tb_remove();
		}
	});

	jQuery(".dialog_close").on("click", function() {
		tb_remove();
		if (typeof update_click_obj_wptc != 'undefined' && update_click_obj_wptc) {
			parent.location.assign(parent.location.href);
		}
	});
}

function ask_backup_name_wptc(type){
	var bbu_note = '';
	var height = '88px';
	if (type === 'bbu') {
		// var bbu_note = '<div style=" text-align: center; top: 18px; position: relative;">We will notify you once the backup and updates are completed.</div>';
		// var height = '118px';
		var data = {
			bbu_note_view:{
				type: 'message',note:'We will notify you once the backup and updates are completed.',
			},
		};
		show_notification_bar(data);
		return false;
	}
	var html = '<div class="theme-overlay" style="z-index: 1000;"><div class="inside" id="backup_custom_name_model" style="height: '+height+';"><div id="manual_backup_name_div" style="font-size: 12px;text-align:center;padding-top: 27px;"><input type="text" id="manual_backup_custom_name" placeholder="Give this backup a name" style="border-radius: 3px;height: 35px;width: 50%;z-index: 1;position: relative;"><a class="button button-primary" id="save_manual_backup_name_wptc" style="margin: 1px 0px 0px -3px;height: 34px;z-index: 2;width: 15%;font-weight: 700;position: relative;border-top-right-radius: 5px;border-top-left-radius: 0px;line-height: 30px;border-bottom-left-radius: 0px;border-bottom-right-radius: 5px;">Save</a>'+bbu_note+'</div></div></div>';
	return html;
	// jQuery('#TB_load').remove();
	// jQuery('#TB_window').append(html).removeClass('thickbox-loading');
	// jQuery('#TB_ajaxContent').hide();
}

function wtc_start_backup_func(type, update_items, update_ptc_type, backup_before_update_always) {
	bp_in_progress = true;
	backup_started_time_wptc = jQuery.now();
	var is_staging_req = 0;
	get_current_backup_status_wptc();
	if (type == 'from_setting') {
		show_backup_progress_dialog('', 'incremental');
	} else if (type == 'from_staging'){
		if (typeof copy_staging_wptc != 'undefined' && copy_staging_wptc) {
			is_staging_req = 2;
		} else{
			is_staging_req = 1;
		}
	} else if(type == 'from_bbu'){
		show_backup_progress_dialog('', 'bbu');
	}
	database_backup_competed = processing_files_completed = '';
	jQuery.post(ajaxurl, {
		action: 'start_fresh_backup_tc_wptc',
		type: 'manual',
		backup_before_update : update_items,
		update_ptc_type: update_ptc_type,
		is_auto_update: 0,
		is_staging_req : is_staging_req,
		backup_before_update_setting: backup_before_update_always,
	}, function(data) {
		if (typeof freshBackupWptc != 'undefined' && freshBackupWptc == 'yes') {
			//calling the progress bar function
			//show_backup_progress_dialog('', 'fresh');
			started_fresh_backup = true;
			var inicontent = '<div style="margin-top: 24px; background: none repeat scroll 0% 0% rgb(255, 255, 255); padding: 0px 7px; box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.2);"><p style="text-align: center; line-height: 24px;">This is your first backup. This might take any where between 20 minutes to a few hours based on your site\'s size. <br>Subsequent backups will be instantaneous since they are incremental. <br>Please be patient and don\'t close the window.</p></div>';
			jQuery(".backup_progress_tc").first().parent().append(inicontent);
			//initial_backup_name_store();    //may require this when we want to rename the backups so dont remove this comment
		} else if(jQuery('#staging_area_wptc').length > 0) {
			//staging area
		} else {
			if (window.location.href.indexOf('page=wp-time-capsule') !== -1){
				show_backup_progress_dialog('', 'incremental');
			}
			started_fresh_backup = false;
		}
		backup_end = true;
		start_backup_from_settings = false;
		trigger_wtc_settings_reload = setTimeout(function() {
			bp_in_progress = force_trigger_ajax_load = true;
			get_current_backup_status_wptc();
		}, 5000);
	});
}

function wtc_stop_backup_func() {
	var this_obj = jQuery(this);
	backup_type = '';
	jQuery.post(ajaxurl, {
		action: 'stop_fresh_backup_tc_wptc'
	}, function(data) {
		jQuery('#start_backup').text("Stop Backup");
		jQuery(this_obj).hide();
		bp_in_progress = false;
		get_current_backup_status_wptc();
		//window.location = adminUrlWptc + '?page=wp-time-capsule';
	});
}

function wtc_stop_restore_func() {
	var this_obj = jQuery(this);
	jQuery.post(ajaxurl, {
		action: 'stop_restore_tc_wptc'
	}, function(data) {
		jQuery(this_obj).hide();
		//window.location = adminUrlWptc + '?page=wp-time-capsule-monitor';
	});
}

function showLoadingDivInCalendarBoxWptc() {
	jQuery('.tc_backup_before_update').addClass('disabled backup_is_going');
	bp_in_progress = true;
	return;
	resetLoadingDivInCalendarBoxWptc();
	jQuery('.fc-today div').hide();
	jQuery('.fc-today').append('<div class="tc-backingup-loading"></div>');
}

function resetLoadingDivInCalendarBoxWptc() {
	bp_in_progress = false;
	jQuery('.tc_backup_before_update').removeClass('disabled backup_is_going');
	return;
	jQuery('.tc-backingup-loading').remove();
	jQuery('.fc-today div').show();
}

function styling_thickbox_tc(styleType) {
	jQuery("#TB_window").removeClass("thickbox-loading");
	jQuery("#TB_title").hide();
	if (styleType == 'progress') {
		jQuery("#TB_window").width("518px");
		jQuery("#TB_window").height("auto");
		jQuery("#TB_ajaxContent").width("518px");
		jQuery("#TB_ajaxContent").css("padding", "0px");
		jQuery("#TB_ajaxContent").css("overflow", "hidden");
		//jQuery("#TB_ajaxContent").css("max-height", "322px");
		jQuery("#TB_ajaxContent").css("height", "auto");
	} else if (styleType == 'backup_yes') {
		jQuery("#TB_window").width("578px");
		jQuery("#TB_ajaxContent").width("578px");
		jQuery("#TB_window").height("auto");
		jQuery("#TB_ajaxContent").css("padding", "0px");
		jQuery("#TB_ajaxContent").css("overflow", "hidden");
		jQuery("#TB_window").css("height", "auto");
		jQuery("#TB_ajaxContent").css("height", "auto");
		jQuery("#TB_window").css("margin-top", "66px");
		jQuery("#TB_ajaxContent").css("max-height", "322px");
		jQuery("#TB_window").css("max-height", "322px");
	} else if (styleType == 'backup_yes_no') {
		jQuery("#TB_window").width("578px");
		jQuery("#TB_window").height("auto");
		jQuery("#TB_ajaxContent").width("578px");
		jQuery("#TB_ajaxContent").css("padding", "0px");
		jQuery("#TB_ajaxContent").css("overflow", "hidden");
		jQuery("#TB_ajaxContent").css("height", "auto");
		jQuery("#TB_window").css("height", "auto");
		jQuery("#TB_window").css("margin-top", "66px");
		jQuery("#TB_window").css("max-height", "274px");
		jQuery("#TB_ajaxContent").css("max-height", "274px");
		jQuery("#TB_window").css("max-width", "578px");
	} else if (styleType == 'restore') {
		jQuery("#TB_window").width("518px");
		jQuery("#TB_window").height("auto");
		jQuery("#TB_ajaxContent").width("518px");
		jQuery("#TB_ajaxContent").css("padding", "0px");
		jQuery("#TB_ajaxContent").css("overflow", "hidden");
		jQuery("#TB_ajaxContent").css("max-height", "322px");
		jQuery("#TB_ajaxContent").css("height", "auto");
	} else if (styleType == 'change_account') {
		jQuery("#TB_window").width("578px");
		jQuery("#TB_window").height("auto");
		jQuery("#TB_ajaxContent").width("578px");
		jQuery("#TB_ajaxContent").css("padding", "0px");
		jQuery("#TB_ajaxContent").css("overflow", "hidden");
		jQuery("#TB_ajaxContent").css("max-height", "500px");
		jQuery("#TB_ajaxContent").css("height", "auto");
	} else if (styleType == 'report_issue') {
		jQuery("#TB_window").width("518px");
		jQuery("#TB_window").height("auto");
		jQuery("#TB_ajaxContent").width("518px");
		jQuery("#TB_ajaxContent").css("padding", "0px");
		jQuery("#TB_ajaxContent").css("overflow", "hidden");
		jQuery("#TB_ajaxContent").css("max-height", "600px");
		jQuery("#TB_ajaxContent").css("height", "auto");
	} else if (styleType == 'initial_backup') {
		jQuery("#TB_window").width("630px");
		jQuery("#TB_window").height("auto");
		jQuery("#TB_ajaxContent").width("630px");
		jQuery("#TB_ajaxContent").css("padding", "0px");
		jQuery("#TB_ajaxContent").css("overflow", "hidden");
		jQuery("#TB_ajaxContent").css("max-height", "500px");
		jQuery("#TB_ajaxContent").css("min-height", "225px");
		jQuery("#TB_ajaxContent").css("height", "auto");
		jQuery("#TB_overlay").attr("onclick", "tb_remove()");
	} else if(styleType == 'backup_before'){
		jQuery("#TB_window").width("518px");
		jQuery("#TB_ajaxContent").width("518px");
		jQuery("#TB_ajaxContent").css("padding", "0px");
		jQuery("#TB_window").css("height", "220px");
		jQuery("#TB_ajaxContent").css("overflow", "hidden");
	}else if(styleType == 'backup_before'){
		jQuery("#TB_window").width("518px");
		jQuery("#TB_ajaxContent").width("518px");
		jQuery("#TB_ajaxContent").css("padding", "0px");
		jQuery("#TB_window").css("height", "220px");
		jQuery("#TB_ajaxContent").css("overflow", "hidden");
	} else if(styleType == 'staging_db'){
		 jQuery("#TB_window").width("612px");
		 jQuery("#TB_window").css("margin-top", "-245px");
		jQuery("#TB_window").height("auto");
		jQuery("#TB_ajaxContent").width("627px");
		jQuery("#TB_ajaxContent").css("padding", "0px");
		jQuery("#TB_ajaxContent").css("height", "auto");
		jQuery("#TB_window").height("auto");
		jQuery("#TB_window").css("top", "300px");
		//jQuery("#TB_window").css("overflow", "hidden");
		var this_height = (jQuery(window).height() * .9) + "px";
		jQuery("#TB_ajaxContent").css("max-height", this_height);
	} else {
		jQuery("#TB_window").width("791px");
		jQuery("#TB_window").height("auto");
		jQuery("#TB_ajaxContent").width("791px");
		jQuery("#TB_ajaxContent").css("padding", "0px");
		jQuery("#TB_ajaxContent").css("height", "auto");
		jQuery("#TB_window").height("auto");
		jQuery("#TB_window").css("top", "300px");
		//jQuery("#TB_window").css("overflow", "hidden");
		var this_height = (jQuery(window).height() * .8) + "px";
		jQuery("#TB_ajaxContent").css("max-height", this_height);
		// var win_height = (jQuery("#TB_ajaxContent").height() / 4) + "px";
		// jQuery("#TB_window").css("margin-top", "-" + win_height);
	}
	jQuery("#TB_window").css('margin-bottom', '0px');

}

function issue_repoting_form() {
	jQuery.post(ajaxurl, {
		action: 'get_issue_report_data_wptc'
	}, function(data) {
		var data = jQuery.parseJSON(data);
		var form_content = '<div class=row-wptc style="padding: 0 0 49px 0;"><div class="wptc-float-left">Name</div><div class="wptc-float-right"><input type="text" style="width:96%" name="uname" value="' + data.lname + '"></div></div><div class=row-wptc style="padding: 0 0 49px 0;"><div class="wptc-float-left">Title</div><div class="wptc-float-right"><input type="text" style="width:96%" name="title"></div></div><div class="row-wptc" style="height: 132px;"><div class="wptc-float-left">Issue Data</div><div class="wptc-float-right"><textarea name="issuedata" id="panelHistoryContent" cols="37" rows="5" readonly class="disabled">' + data.idata + '</textarea></div></div><div class=row-wptc style="padding: 0 0 49px 0;"><div class="wptc-float-right"><input id="send_issue_wptc" class="button button-primary" type="button" value="Send"><input id="cancel_issue" style="margin-left: 3%;" class="button button-primary" type="button" value="Cancel"></div></div>';
		var dialog_content = '<div class="this_modal_div" style="background-color: #f1f1f1;font-family: \'open_sansregular\' !important;color: #444;padding: 0px 35px 35px 35px; width: 450px;left:20%; z-index:1000"><span class="dialog_close" id="form_report_close"></span><div class="pu_title">Send Report</div><form name="issue_form" id="issue_form">' + form_content + '</form></div>';
		remove_other_thickbox_wptc();
		jQuery("#wptc-content-id").html(dialog_content);
		jQuery(".wptc-thickbox").click();
		styling_thickbox_tc('report_issue');
	});

}

function sendWTCIssueReport(issueData) {
	var email = issueData[0]['value'];
	var desc = issueData[1]['value'];
	var issue = issueData[2]['value'];
	var fname = issueData[3]['value'];
	var idata = {
		'email': email,
		'desc': desc,
		'issue_data': issue,
		'name': fname
	};
	jQuery.post(ajaxurl, {
		action: 'send_wtc_issue_report_wptc',
		data: idata
	}, function(response) {
		if (response == "sent") {
			jQuery("#issue_form").html("");
			jQuery("#issue_form").html("<div class='wptc-success_issue'>Issue submitted successfully<div>");
		} else {
			jQuery("#issue_form").html("");
			jQuery("#issue_form").html("<div class='wptc-fail-issue'>Issue sending failed.Try after sometime<div>");
		}
	});
}

function yes_delete_logs() {
	jQuery.post(ajaxurl, {
		action: 'clear_wptc_logs'
	}, function(response) {
		if (response == "yes") {
			jQuery(".this_modal_div").html("");
			jQuery(".this_modal_div").css('padding', '26px 34px 26px');
			jQuery(".this_modal_div").html("<div class='wptc-success_issue'>Log's are Removed<div>");
			parent.location.assign(parent.location.href);
		} else {
			jQuery(".this_modal_div").html("");
			jQuery(".this_modal_div").css('padding', '26px 34px 26px');
			jQuery(".this_modal_div").html("<div style='margin-top:10px' class='wptc-fail-issue'>Failed to remove logs from Database<div>");
		}
	});
}

function reload_monitor_page() {
	parent.location.assign(parent.location.href);
}

function freshBackupPopUpShow() {
	var StartBackup = jQuery('#start_backup').html();
	var StopBackup = jQuery('#stop_backup').html();
	if (StartBackup != "Stop Backup" && StopBackup != "Stop Backup") {
		var dialog_content = '<div class="this_modal_div" style="background-color: #f1f1f1;font-family: \'open_sansregular\' !important;color: #444;padding: 0px 34px 26px 34px; left:20%; z-index:1000"><span class="dialog_close"></span><div class="pu_title">Your first backup</div><div class="wcard clearfix" style="width:480px"><div class="l1">Do you want to backup your site now?</div><a style="margin-left: 29px;" class="btn_pri" onclick="initialSetupBackup()">Yes. Backup now.</a><a class="btn_sec" id="no_change" onclick="tb_remove()">No. I will do it later.</a></div></div>';
		setTimeout(function() {
			remove_other_thickbox_wptc();
			jQuery("#wptc-content-id").html(dialog_content);
			jQuery(".wptc-thickbox").click();
			styling_thickbox_tc('initial_backup');
		}, 3000);
	}
}

function initialSetupBackup() {
	jQuery('#start_backup').click()
	tb_remove();
}

function show_get_name_dialog_tc() {
	var this_content = '<div class="wcard clearfix backup_name_dialog" style="margin-top:30px;">  <div class="l1" style="padding-top: 0px;">Do you want to name this backup?</div>  <input type="text" placeholder="Backup Name" class="backup_name_tc"><a class="btn_pri backup_name_enter">SAVE</a>  <a class="skip">NO, SKIP THIS</a> </div>';

	jQuery(".backup_progress_tc").parent().append(this_content);
	jQuery(".skip").on("click", function() {
		jQuery(".backup_name_dialog").remove();
	});
	jQuery(".backup_name_enter").on("click", function() {
		store_this_name_tc();
	});
}

function store_this_name_tc() {
	var this_name = jQuery(".backup_name_tc").val();
	jQuery.post(ajaxurl, {
		action: 'store_name_for_this_backup_wptc',
		data: this_name
	}, function(data) {
		if (data) {
			jQuery(".backup_name_dialog").hide();
		}
	});
}

function process_backup_status(backup_progress, prog_percent) {
	if (backup_progress != '') {
	   if(backup_progress.db.running){
			update_backup_status('synchingDB', backup_progress.db.progress);
		} else if(backup_progress.files.processing.running){
			update_backup_status('checkingForChanges', backup_progress.files.processing.progress);
		} else if(backup_progress.files.processed.running) {
			update_backup_status('synchingFiles', prog_percent);
		}
	} else {
		update_backup_status('backupCompleted');
	}
}

function update_backup_status(type, progPercent) {
	if (type == 'checkingForChanges') {
		jQuery(status_area_wptc).html(' Processing files (' + progPercent + ' files)');
	} else if (type == 'synchingDB') {
		jQuery(status_area_wptc).html(' Syncing database (' + progPercent + ' tables)');
	} else if (type == 'synchingFiles') {
		jQuery(status_area_wptc).html(' Syncing changed files ' + progPercent + '%');
		jQuery('.staging_progress_bar').css('width', progPercent+'%');
	} else {
		if (typeof last_backup_time != 'undefined' && last_backup_time != null && last_backup_time) {
			// jQuery(status_area_wptc).html('Last backup taken : ' + last_backup_time );
		} else {
			// jQuery(status_area_wptc).html('No backups taken');
		}
	}
}

function get_this_day_backups_ajax(backupIds){
	remove_other_thickbox_wptc();
	 jQuery.post(ajaxurl, {
			action: 'get_this_day_backups_wptc',
			data: backupIds
		}, function(data) {
			jQuery(".dialog_cont").remove();
			jQuery("#wptc-content-id").html(data);
			jQuery(jQuery("#wptc-content-id").find('.bu_name')).each(function( index ) {
				if(jQuery(this).text().indexOf('Updated on') == 0)
				{
					jQuery(this).hide();
				}
			});
			jQuery(".wptc-thickbox").click();

			styling_thickbox_tc();
			registerDialogBoxEventsTC();
			//do the UI action to hide the folders, display the folders based on tree
			jQuery(".this_parent_node .sub_tree_class").hide();
			jQuery(".this_parent_node .this_leaf_node").hide();
			jQuery(".this_leaf_node").show();
			jQuery(".sub_tree_class.sl0").show();

			//for hiding the backups folder and its sql-file
			var sqlFileParent = jQuery(".sql_file").parent(".this_parent_node");
			jQuery(sqlFileParent).hide();
			//jQuery(sqlFileParent).parent(".this_parent_node").hide();
			//jQuery(sqlFileParent).parent(".this_parent_node").prev(".sub_tree_class").hide();
			jQuery(sqlFileParent).prev(".sub_tree_class").hide();
		});
}

function load_pop_up(){
	remove_other_thickbox_wptc();
	if(jQuery('#cancel_issue_notice').css('display') != 'none' && jQuery('#cancel_issue_notice').css('display') != undefined || location.href.indexOf("page=wp-time-capsule") == -1){
		return false;
	}
	if(location.href.toLowerCase().indexOf('wp-time-capsule') === -1){
		return false;
	}
	jQuery('.notice, #update-nag').remove();
	var form_content = '<div class=row-wptc style="padding: 0 0 49px 0;"><ul><li style="text-align: justify;">Starting v1.3.0 we will backup only the WordPress core files, folders & tables. If you want to include or exclude more files, go to Settings -> Exclude / Include from Backup.</li></ul><br><input id="cancel_issue_notice" style="margin-left: 40%;" class="button button-primary" type="button" value="Okay, got it"></div>';
	var dialog_content = '<div class="this_modal_div" style="background-color: #f1f1f1;font-family: \'open_sansregular\' !important;color: #444;padding: 0px 35px 0px 35px; width: 450px;left:20%; z-index:1000"><div class="pu_title">Updates to existing backup mechanism</div><form name="issue_form" id="issue_form">' + form_content + '</form></div>';
	jQuery("#wptc-content-id").html(dialog_content);
	jQuery(".wptc-thickbox").click();
	styling_thickbox_tc('progress');
	mark_update_pop_up_shown();
}

function mark_update_pop_up_shown(){
	jQuery.post(ajaxurl, {
			action: 'plugin_update_notice_wptc',
		}, function(data) {
			//tb_remove();
		});
}

function load_cron_status_failed_popup(status_code, err_msg, cron_url, ips,pop_up_status){
	jQuery('.notice, #update-nag').remove();
	if(jQuery('#cancel_issue_notice').css('display') != 'none' && jQuery('#cancel_issue_notice').css('display') != undefined){
		return false;
	}
	if (pop_up_status == '') {
		pop_up_status = 0;
	}
	if (ips == undefined || !ips) {
		ips = '52.33.122.174';
	}
	var note = 'Note: Please whitelist the IP Address ('+ips+') to access the URL : <a style="text-decoration: none;" href="'+cron_url+'">'+cron_url+'</a> or get in touch with your hosting provider to have it whitelisted. You can check the Cron Status anytime under WPTC -> Settings or email us at <a href="mailto:help@wptimecapsule.com?Subject=Contact" target="_top">help@wptimecapsule.com</a>';
	var header_div = '<div class="theme-overlay" style="z-index: 1000;"><div class="theme-wrap wp-clearfix" style="width: 620px;height: 410px;left: 10px;">';
	var header_text ='<div class="theme-header"><button class="close dashicons dashicons-no"><span class="screen-reader-text">Close details dialog</span></button> <h3 style="margin-left: 20px;">WPTC\'s Server could not contact your WordPress site.</h3></div>';
	var body_text = '<div class="theme-about wp-clearfix"> <div><span style="margin: 10px;position: absolute;" id="wptc_test_connection_error"></span></div></h4><div><span style="margin: 0px;top: 175px;position: absolute;"></span></div></div><div class="theme-actions"><div class="inactive-theme" style=" padding-top: 0px;">'+note+'</div></div>';
	var dialog_content = header_div+header_text+body_text;
	if (typeof load_cron_status_failed_popup_shown == 'undefined' || pop_up_status == 0) {
		load_cron_status_failed_popup_shown = 0;
	}

	if (load_cron_status_failed_popup_shown == 0 && window.location.href.indexOf('wp-time-capsule') != -1 && pop_up_status == 0) {
		jQuery(".this_modal_div").hide();
		jQuery("#start_backup_from_settings").attr('action', 'start').html('Backup now');
		jQuery("#backup_button_status_wptc").text("Click Backup Now to backup the latest changes.");
		jQuery("#wptc-content-id").html(dialog_content);
		jQuery("#wptc_test_connection_error").text(err_msg);
		jQuery(".wptc-thickbox").click();
		styling_thickbox_tc('progress');
		add_notice_wptc(note);
		load_cron_status_failed_popup_shown = 1;
		update_test_connection_err_shown_wptc();
		delete reloadFuncTimeout;
	}
}

function add_notice_wptc(note, all_page, status){
	var status_class = '';
	if (!status) {
		status_class = 'notice-warning';
	} else {
		switch(status){
			case 'success':
			status_class = 'notice-success';
			break;
			case 'error':
			status_class = 'notice-error';
			break;
			case 'warning':
			status_class = 'notice-warning';
			break;
		}
	}

	var notice = '<div class="notice '+status_class+'  is-dismissible" > <p>'+note+'</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>'
	if (all_page) {
		jQuery('.wrap').before(notice);
	} else {
		jQuery('#wptc').before(notice);
	}
}


function load_custom_popup_wptc(show, type, show_all_page, title, msg, footer){
	if (!show) {
		return false;
	}
	if(location.href.toLowerCase().indexOf('wp-time-capsule') === -1 && !show_all_page){
		return false;
	}
	if (type == 'new_updates') {
		var header = 'Check out our Latest feature - Backup before Update !';
		var message = '<ul style="text-align: justify;"> <li>With the current release, we are wrapping up our free feature set :)</li><li><ul style="padding-left: 20px;"><li style="list-style: disc outside none; display: list-item; margin-left: 1em;">Automated Daily Backups.</li><li style="list-style: disc outside none; display: list-item; margin-left: 1em;">Backup Before Auto Update and Manual Update.</li></ul></li><li>Pro features are scheduled to be released by the End of January 2017.</li><li>Should you have any questions or concerns, please mail us to <a href="mailto:help@wptimecapsule.com">help@wptimecapsule.com</a></li></ul>';
		var width = '570px';
		var height = '270px';
		var footer = '';
	}

	if (type == 'cannot_update_paid_items') {
		var header = title;
		var message = msg;
		var width = '570px';
		var height = '300px';
		var footer = footer;
	}

	jQuery('.notice, #update-nag').remove();
	var header_div = '<div class="theme-overlay" style="z-index: 1000;"><div class="theme-wrap wp-clearfix" style="width: '+width+';height: '+height+';left: 10px;">';
	var header_text ='<div class="theme-header"><button class="close dashicons dashicons-no"><span class="screen-reader-text">Close details dialog</span></button> <h3 style="text-align:center">'+header+'</h3></div>';
	var body_text = '<div class="theme-about wp-clearfix">'+message+'<div><span style="margin: 0px;top: 175px;position: absolute;"></span></div></div><div class="theme-actions"><div class="active-theme">     <a class="button button-secondary">Background</a></div><div class="inactive-theme"><span>'+footer+'</span></div></div>';
	var dialog_content = header_div+header_text+body_text;
	setTimeout(function() {
		jQuery("#wptc-content-id").html(dialog_content);
		jQuery(".wptc-thickbox").click();
		styling_thickbox_tc('progress');
		mark_update_pop_up_shown();
	}, 2000);
}

function start_manual_backup_wptc(obj, type, update_items, update_ptc_type, backup_before_update_always){
	freshBackupWptc = '';
	if(type == 'from_bbu'){
		backup_started_wptc = 'from_bbu';
		wtc_start_backup_func('from_bbu', update_items, update_ptc_type, backup_before_update_always);
		return false;
	}
	if(jQuery(obj).attr("action") == 'start'){
		jQuery(obj).text('Starting backup...');
		backup_started_wptc = 'from_setting';
		wtc_start_backup_func('from_setting');
	} else if(jQuery(obj).attr("action") == 'stop'){
		jQuery(obj).text('Stopping backup...');
		wtc_stop_backup_func();
	}
}

function update_sycn_db_view_wptc(){
	 jQuery.post(ajaxurl, {
			action: 'update_sycn_db_view_wptc',
		}, function(data) {});
}

function update_test_connection_err_shown_wptc(){
	 jQuery.post(ajaxurl, {
			action: 'update_test_connection_err_shown_wptc',
		}, function(data) {});
}

function showed_processing_files_view_wptc(){
	 jQuery.post(ajaxurl, {
			action: 'show_processing_files_view_wptc',
		}, function(data) {});
}

function test_connection_wptc_cron(){
	if (jQuery('.test_cron_wptc').hasClass('disabled')) {
		return false;
	}
	jQuery('.cron_current_status').html('Waiting for response').css('color', '#444');
	jQuery('.test_cron_wptc').addClass('disabled').html('Connecting...');
	jQuery.post(ajaxurl, {
			action: 'test_connection_wptc_cron',
		}, function(data) {
			 try{
					var obj = jQuery.parseJSON(data);
					if (typeof obj.status != 'undefined' && obj.status == "success") {
						jQuery('#wptc_cron_status_failed').hide();
						// jQuery('.test_cron_wptc').hide();
						jQuery('.test_cron_wptc').removeClass('disabled').html('Test again');
						jQuery('#wptc_cron_status_passed').show();
						jQuery('.cron_current_status').html('Success').css('color', '');
					} else {
						load_cron_status_failed_popup(obj.status, obj.err_msg, obj.cron_url, obj.ips, '');
						jQuery('#wptc_cron_status_failed').show();
						jQuery('.test_cron_wptc').show();
						jQuery('.test_cron_wptc').removeClass('disabled').html('Test again');
						jQuery('#wptc_cron_failed_note').html('Failed').css('color', '');
						jQuery('#wptc_cron_status_passed').hide();
					}
				} catch (e){
					jQuery('.test_cron_wptc').removeClass('disabled').html('Test again');
					jQuery('#wptc_cron_failed_note').html('Failed');
					return false;
				}
	});
}

function wptc_backup_start_failed_note(failed_backups){
	jQuery("#start_backup_from_settings").attr('action', 'start').html('Backup now');
	jQuery("#backup_button_status_wptc").text("Click Backup Now to backup the latest changes.");
	tb_remove();
	var total_failed_count = jQuery(failed_backups).length;
	var backup_text = (total_failed_count > 1) ? 'backups have ' : 'backup has ';
	var note = 'The plugin is not able to communicate with the server hence backups have been stopped. This is applicable to manual , scheduled backups and Staging. The following '+backup_text+' been stopped due to lack of communication between the plugin and server.<br>';
	var backup_list = '';
	jQuery(failed_backups).each(function( index ) {
		backup_list = backup_list + failed_backups[index] +"<br>";
	});
	note = note + backup_list;
	jQuery('.notice, #update-nag').remove();
	var notice = '<div class="update-nag  notice is-dismissible" id="setting-error-tgmpa"> <h4>WP Time Capsule</h4> <p>'+note+'</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>'
	jQuery('.wrap').before(notice);
}

function save_settings_wptc(){
	var hash = window.location.hash;
	switch(hash){
		case '#wp-time-capsule-tab-general':
		save_general_settings_wptc();
		break;
		case '#wp-time-capsule-tab-backup':
		save_backup_settings_wptc();
		break;
		case '#wp-time-capsule-tab-bbu':
		save_bbu_settings_wptc();
		break;
		case '#wp-time-capsule-tab-vulns':
		save_vulns_settings_wptc();
		break;
		case '#wp-time-capsule-tab-staging':
		save_staging_settings_wptc();
		break;
		default:
		jQuery("#wptc_save_changes, #exc_files_db_save").removeAttr('disabled').removeClass('disabled').val("Hash does not match !").html("Hash does not match !");	
		disable_settings_button_wptc();
	}

}

function disable_settings_button_wptc(){
	jQuery("#wptc_save_changes, #exc_files_db_save").removeAttr('disabled').removeClass('disabled').val("Save Changes").html("Save");
	jQuery('#exc_files_db_cancel, #exc_files_db_save').css('color','#0073aa').unbind('click', false);
}

function save_general_settings_wptc(){
	var anonymouse = jQuery('input[name=anonymous_datasent]:checked').val();
	save_settings_ajax_request_wptc('save_general_settings_wptc', {'anonymouse' : anonymouse});
}

function save_settings_ajax_request_wptc(action, data){
	jQuery.post(ajaxurl, {
			action: action,
			data : data,
	}, function(data) {
		console.log(data);
		if (data != undefined) {
			jQuery("#wptc_save_changes, #exc_files_db_save").removeAttr('disabled').removeClass('disabled').val("Done :)").html("Done :)");
		} else {
			jQuery("#wptc_save_changes, #exc_files_db_save").removeAttr('disabled').removeClass('disabled').val("Failed !").html("Failed !");
		}

		setTimeout(function(){
			disable_settings_button_wptc();
		}, 1000);
	});
}

function save_backup_settings_wptc(){
	var scheduled_time = '';
	if (jQuery("#select_wptc_default_schedule").hasClass('disabled') === false) {
		var scheduled_time  = jQuery("#select_wptc_default_schedule").val();
	}

	var timezone = '';
	if (jQuery("#wptc_timezone").hasClass('disabled') === false) {
		var timezone  = jQuery("#wptc_timezone").val();
	}

	var user_excluded_extenstions  = jQuery("#user_excluded_extenstions").val();

	if (scheduled_time && timezone) {
		var request_params = { "scheduled_time": scheduled_time,
								"timezone" : timezone,
								"user_excluded_extenstions" : user_excluded_extenstions
							 };
	} else {
		var request_params = { "user_excluded_extenstions": user_excluded_extenstions }
	}
	save_settings_ajax_request_wptc('save_backup_settings_wptc', request_params);
}

function save_bbu_settings_wptc(){

	var backup_before_update_setting = jQuery('input[name=backup_before_update_setting]:checked').val();
	var backup_type = jQuery('#backup_type').val();
	var auto_update_wptc_setting = jQuery('input[name=auto_update_wptc_setting]:checked').val();
	var auto_updater_core_major = jQuery('input[name=wptc_auto_core_major]:checked').val();
	var auto_updater_core_minor = jQuery('input[name=wptc_auto_core_minor]:checked').val();
	var auto_updater_plugins = jQuery('input[name=wptc_auto_plugins]:checked').val();
	var auto_updater_plugins_included = jQuery('#auto_include_plugins_wptc').val();
	var auto_updater_themes = jQuery('input[name=wptc_auto_themes]:checked').val();
	var auto_updater_themes_included = jQuery('#auto_include_themes_wptc').val();

	var request_params = {
							"backup_before_update_setting" : backup_before_update_setting,
							"auto_update_wptc_setting" : auto_update_wptc_setting,
							"auto_updater_core_major" : (auto_updater_core_major) ? auto_updater_core_major : 0,
							"auto_updater_core_minor" : (auto_updater_core_minor) ? auto_updater_core_minor : 0,
							"auto_updater_plugins" : (auto_updater_plugins) ? auto_updater_plugins : 0,
							"auto_updater_plugins_included" : (auto_updater_plugins_included) ? auto_updater_plugins_included : '',
							"auto_updater_themes" : (auto_updater_themes) ? auto_updater_themes : 0,
							"auto_updater_themes_included" : (auto_updater_themes_included) ? auto_updater_themes_included : '',
						}
	save_settings_ajax_request_wptc('save_bbu_settings_wptc', request_params);
}

function save_staging_settings_wptc(){
	var db_rows_clone_limit_wptc =jQuery("#db_rows_clone_limit_wptc").val();
	var files_clone_limit_wptc =jQuery("#files_clone_limit_wptc").val();
	var deep_link_replace_limit_wptc =jQuery("#deep_link_replace_limit_wptc").val();
	var request_params = {  "db_rows_clone_limit_wptc": db_rows_clone_limit_wptc,
							"files_clone_limit_wptc" : files_clone_limit_wptc,
							"deep_link_replace_limit_wptc" : deep_link_replace_limit_wptc
						 };
	save_settings_ajax_request_wptc('save_staging_settings_wptc', request_params);
}

function save_vulns_settings_wptc(){
	var enable_vulns_settings_wptc = jQuery('input[name=enable_vulns_settings_wptc]:checked').val();
	var request_params = { "enable_vulns_settings_wptc": enable_vulns_settings_wptc };
	save_settings_ajax_request_wptc('save_vulns_settings_wptc', request_params);
}

function update_included_file_db_size(save_settings){
	save_settings = save_settings;
	 jQuery.post(ajaxurl, {
			action: 'wptc_get_included_file_db_size',
			dataType: 'json',
	}, function(data) {
		// console.log("wptc_get_included_file_db_size", data);
		data = jQuery.parseJSON(data);
		jQuery("#calculating_file_db_size").hide();
		jQuery("#got_file_db_size").show();
		jQuery("#included_db_size").html(data.db);
		jQuery("#included_file_size").html(data.files);
		if (save_settings != 1) {
			jQuery("#continue_wptc, #skip_initial_set_up").removeAttr('disabled').removeClass('disabled')
		} else {
			jQuery('#calculating_file_db_size_temp, #show_final_size').toggle();
		}
	});
}

function resume_backup_wptc(){
	jQuery(".resume_backup_wptc").addClass("disabled").attr('style', 'cursor:auto; color:gray').text('Reconnecting...');
	jQuery.post(ajaxurl, {
			action: 'resume_backup_wptc',
	}, function(data) {

		if (data != undefined) {
			try{
			var obj = jQuery.parseJSON(data);
			if (typeof obj.status != 'undefined' && obj.status == "success") {
					jQuery(".resume_backup_wptc").text('Backup Resumed').removeAttr('style').attr('style', 'color:green;');
					setTimeout(function(){
						jQuery("#wptc_cron_status_div").show();
						jQuery("#wptc_cron_status_paused").hide();
					}, 1000);
				} else {
					load_cron_status_failed_popup(obj.status, obj.err_msg, obj.cron_url, obj.ips, '');
					jQuery(".resume_backup_wptc").text('Failed to resume backup');
					setTimeout(function(){
						jQuery(".resume_backup_wptc").removeClass("disabled").removeAttr('style').attr('style', 'cursor:pointer;').text('Resume backup');
					}, 1000);
				}
			} catch (e){
				jQuery(".resume_backup_wptc").text('Failed to resume backup');
				setTimeout(function(){
						jQuery(".resume_backup_wptc").removeClass("disabled").removeAttr('style').attr('style', 'cursor:pointer;').text('Resume backup');
					}, 1000);
				return false;
			}
		}
	});
}

function basename_wptc(path) {
   return path.split('/').reverse()[0];
}

function change_init_setup_button_state(){
	jQuery("#file_db_exp_for_exc_view").toggle();
	jQuery(".view-user-exc-extensions").toggle();
	jQuery("#wptc_init_toggle_tables").click();
	jQuery("#wptc_init_toggle_files").click();
	// jQuery("#exc_files_db_save").toggle();
	// jQuery("#exc_files_db_cancel").toggle();
	// jQuery("#continue_wptc, #skip_initial_set_up").removeAttr('disabled').removeClass('disabled')
	// jQuery("#continue_wptc, #skip_initial_set_up").attr('disabled', 'disabled').toggleClass('disabled')
}

function convert_bytes_to_hr_format(size){
	if (1024 > size) {
		return size + ' B';
	} else if (1048576 > size) {
		return ( (size / 1024)).toFixed(2) + ' KB';
	} else if (1073741824 > size) {
		return ((size / 1024) / 1024).toFixed(2) + ' MB';
	} else if (1099511627776 > size) {
		return (((size / 1024) / 1024) / 1024).toFixed(2) + ' GB';
	}
}

function save_manual_backup_name_wptc(name){
	jQuery.post(ajaxurl, {
			action: 'save_manual_backup_name_wptc',
			name: name,
	}, function(data) {
		console.log(data)
		var obj = jQuery.parseJSON(data);
		if(obj.status && obj.status == 'success'){
			jQuery("#backup_custom_name_model").css('height', '88px');
			jQuery("#manual_backup_name_div").text('Backup name saved :-)').css({'color':'green', 'padding-top': '36px'});
			setTimeout(function(){
				jQuery("#manual_backup_name_div").hide();
				tb_remove();
				if(typeof backup_started_wptc != 'undefined' && backup_started_wptc == 'from_setting'){
					location.assign(adminUrlWptc+'admin.php?page=wp-time-capsule-monitor');
				}
			},3000);
		}
	});
}

function remove_other_thickbox_wptc(){
	jQuery('.thickbox').each(function(index){
		if(!jQuery(this).hasClass("wptc-thickbox") && !jQuery(this).hasClass("open-plugin-details-modal")){
			jQuery(this).remove();
		}
	});
}

function is_backup_running_wptc(type){
	backup_running_wptc_check_type_wptc = type;
	jQuery.post(ajaxurl, {
			action: 'is_backup_running_wptc',
			name: name,
	}, function(data) {
		var obj = jQuery.parseJSON(data);
		console.log(obj)
		if (backup_running_wptc_check_type_wptc == 'staging_not_started') {
			if (obj.status == 'not_running' ) {
				remove_prev_activity_ui();
				ftp_details_wptc();
			} else {
				if(window.location.href.indexOf('wp-time-capsule-staging-options') !== -1){
					jQuery("#staging_area_wptc").removeClass('postbox').html('<div id="dashboard_activity" style="overflow: hidden;margin: 0px; width: 702px; margin: 60px 0px 0px 460px;"><div class="notice notice-warning notice-alt notice-large"><h3 class="notice-title">Backup running</h3><p><strong>Backup is running, Please wait until backup finish to stage.</strong></p></div></div>');
				}
		   }
		} else if (backup_running_wptc_check_type_wptc == 'staging_completed') {
			if (obj.status == 'running' ) {
				jQuery('#ask_copy_staging_wptc').addClass('disabled');
				jQuery('#edit_staging_wptc').addClass('disabled').css('color','gray');
		   }
		}
	});
}

function gettime_wptc() {
	var d = new Date();
	var month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	var date = d.getDate() + " " + month[d.getMonth()];
	var nowHour = d.getHours();
	var nowMinutes = d.getMinutes();
	var nowSeconds = d.getSeconds();
	var suffix = nowHour >= 12 ? "PM" : "AM";
	nowHour = (suffix == "PM" & (nowHour > 12 & nowHour < 24)) ? (nowHour - 12) : nowHour;
	nowHour = nowHour == 0 ? 12 : nowHour;
	nowMinutes = nowMinutes < 10 ? "0" + nowMinutes : nowMinutes;
	nowSeconds = nowSeconds < 10 ? "0" + nowSeconds : nowSeconds;
	var currentTime = nowHour + ":" + nowMinutes + ":" + nowSeconds + ' ' + suffix;
	return date + ' '+currentTime;
}

function show_users_backend_errors_wptc(error){
	if (error) {
		var note = 'WP Time Capsule Error : '+ error + '   <br>If you are not sure what went wrong, please email us at <a href="mailto:help@wptimecapsule.com?Subject=Contact" target="_top">help@wptimecapsule.com</a>';
		add_notice_wptc(note, true);
		clear_show_users_backend_errors_wptc();
	}
}

function clear_show_users_backend_errors_wptc(){
	jQuery.post(ajaxurl, {
			action: 'clear_show_users_backend_errors_wptc',
	}, function(data) {
		//data
	});
}

function get_cloud_label_from_val_wptc(val){
	if(typeof val == 'undefined' || val == ''){
		return 'Cloud';
	}
	var cloudLabels = {};
	cloudLabels['g_drive'] = 'Google Drive';
	cloudLabels['s3'] = 'Amazon S3';
	cloudLabels['dropbox'] = 'Dropbox';

	return cloudLabels[val];
}