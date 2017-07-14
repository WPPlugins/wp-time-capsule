function wtc_reload() {
    jQuery('.files').hide();
    jQuery.post(ajaxurl, {
        action: 'progress_wptc'
    }, function(data) {
        if (!data.length) {
            return false;
        }
        process_wtc_reload(data);
    });
}

function process_wtc_reload(data){
    jQuery('#progress').html('<div class="calendar_wrapper"></div>');
    jQuery("#progress").append('<div id="wptc-content-id" style="display:none;"> <p> This is my hidden content! It will appear in ThickBox when the link is clicked. </p></div><a class="thickbox wptc-thickbox" style="display:none" href="#TB_inline?width=500&height=500&inlineId=wptc-content-id&modal=true"></a>');

    if (typeof data == 'undefined' || !data) {
        return;
    }

    jQuery('.calendar_wrapper').fullCalendar({
        theme: false,
        header: {
            left: 'prev,next today',
            center: 'title',
            right: 'month,agendaWeek,agendaDay'
        },
        defaultDate: defaultDateWPTC, //setting from global var
        editable: false,
        events: data.stored_backups,
        eventAfterAllRender: function(){
            var first_one = jQuery('.fc-header-right')[0];
            jQuery(first_one).html('<div class="last-bp-taken-wptc">Last backup on - <span class="last-bp-taken-time">'+data.last_backup_time+'</span> </div>');
        }
    });

    var backup_progress = data.backup_progress;
    if (backup_progress != '') {
        showLoadingDivInCalendarBoxWptc();
    } else {
        resetLoadingDivInCalendarBoxWptc();
    }
}

function getThisDayBackups(backupIds) {
    remove_other_thickbox_wptc();
    jQuery('.notice, #update-nag').remove();
    var loading = '<div class="dialog_cont" style="padding:2%"><div class="loaders"><div class="loader_strip"><div class="wptc-loader_strip_cl" style="background:url(' + wptcOptionsPageURl + '/images/loader_line.gif)"></div></div></div></div>';
    jQuery("#wptc-content-id").html(loading);
    jQuery(".wptc-thickbox").click();
    styling_thickbox_tc();
    registerDialogBoxEventsTC();
    //to show all the backup list when a particular date is clicked
    get_this_day_backups_ajax(backupIds);
}

function registerDialogBoxEventsTC() {
    if (typeof cuurent_bridge_file_name == 'undefined') {
        cuurent_bridge_file_name = '';
    }
    jQuery.curCSS = jQuery.css;
    jQuery('.checkbox_click').on('click', function() {

        if (!(jQuery(this).hasClass("active"))) {
            jQuery(this).addClass("active");
        } else {
            jQuery(this).removeClass("active");
        }
    });

    jQuery('.single_backup_head').on('click', function() {
        var this_obj = jQuery(this).closest(".single_group_backup_content");

        if (!(jQuery(this).hasClass("active"))) {
            jQuery(".single_backup_content_body", this_obj).show();
        } else {
            jQuery(".single_backup_content_body", this_obj).hide();
        }
    });

    //UI actions for the file selection
    jQuery(".toggle_files").on("click", function(e) {
        var par_obj = jQuery(this).closest(".single_group_backup_content");
        if (!jQuery(par_obj).hasClass("open")) {
            //close all other restore tabs ; remove the active items
            jQuery(".this_leaf_node li").removeClass("selected");
            jQuery(".toggle_files.selection_mode_on").click();

            jQuery(par_obj).addClass("open");
            jQuery(".changed_files_count, .this_restore", par_obj).show();
            jQuery(".this_restore_point_wptc", par_obj).hide();
            jQuery(this).addClass("selection_mode_on");
        } else {
            jQuery(par_obj).removeClass("open");
            jQuery(".changed_files_count, .this_restore", par_obj).hide();
            jQuery(".this_restore_point_wptc", par_obj).show();
            jQuery(this).removeClass("selection_mode_on");
        }
        e.stopImmediatePropagation();
        styling_thickbox_tc("");
        return false;
    });

    jQuery(".folder").on("click", function(e) {
        if (jQuery(this).hasClass('disabled')) {
            return false;
        }
        get_sibling_files_wptc(this);
        e.stopImmediatePropagation();
        return false;
    });

    jQuery(".restore_the_db").on("click", function() {
        var par_obj = jQuery(this).closest(".single_group_backup_content");
        if (!jQuery(this).hasClass("selected")) {
            jQuery(".sql_file", par_obj).parent(".this_parent_node").prev(".sub_tree_class").removeClass("selected");
            jQuery(".sql_file li", par_obj).removeClass("selected");
        } else {
            jQuery(".sql_file", par_obj).parent(".this_parent_node").prev(".sub_tree_class").addClass("selected");
            jQuery(".sql_file li", par_obj).addClass("selected");
        }

        if ((!jQuery(".this_leaf_node li", par_obj).hasClass("selected")) && (!jQuery(".sub_tree_class", par_obj).hasClass("selected"))) {
            jQuery(".this_restore", par_obj).addClass("disabled");
        } else {
            jQuery(".this_restore", par_obj).removeClass("disabled");
        }
    });

    jQuery('.this_restore').on('click', function(e) {
        if (jQuery(this).hasClass("disabled")) {
            return false;
        }
        restore_obj = this;
        restore_type = 'selected_files';
        restore_confirmation_pop_up();
        return false;

    });

    jQuery('body').on('click', '#yes_continue_restore_wptc', function (e){
        e.stopImmediatePropagation()
        revert_confirmation_backup_popups();
        if (restore_type == 'selected_files') {
            trigger_selected_files_restore();
        } else if(restore_type == 'to_point'){
            trigger_to_point_restore();
        }
    });

    jQuery('body').on('click', '.no_exit_restore_wptc', function (e){
        e.stopImmediatePropagation()
        revert_confirmation_backup_popups();
    });

    jQuery('.this_restore_point_wptc').on('click', function(e) {
        restore_obj = this;
        restore_type = 'to_point';
        restore_confirmation_pop_up();
        return false;
    });



    jQuery("#TB_overlay").on("click", function() {
        if ((typeof is_backup_started == 'undefined' || is_backup_started == false) && !on_going_restore_process) { //for enabling dialog close on complete
            tb_remove();
            backupclickProgress = false;
        }
    });

    jQuery(".dialog_close").on("click", function() {
        tb_remove();
    });
}

function revert_confirmation_backup_popups(){
    jQuery('.wptc_restore_confirmation').remove();
    jQuery('#TB_ajaxContent').show();
}
function wtc_initializeRestore(obj, type) {
    //this function returns the files to be restored ; shows the dialog box ; clear the reload timeout for backup ajax function
    var files_to_restore = {};

    var par_obj = jQuery(obj).closest('.single_group_backup_content');

    if (type == 'all') {
        var is_selected = ''; //a trick to include all files during restoring-at-a-point;

        var sql_obj_wptc = jQuery(".this_leaf_node li.sql_file_li", par_obj);

        var this_revision_id = jQuery(sql_obj_wptc).find(".file_path").attr("revision_id");

        files_to_restore[this_revision_id] = {};
        files_to_restore[this_revision_id]['file_name'] = jQuery(sql_obj_wptc).find(".file_path").attr("file_name");
        files_to_restore[this_revision_id]['file_size'] = jQuery(sql_obj_wptc).find(".file_path").attr("file_size");
        files_to_restore[this_revision_id]['g_file_id'] = jQuery(sql_obj_wptc).find(".file_path").attr("g_file_id");
        files_to_restore[this_revision_id]['mtime_during_upload'] = jQuery(sql_obj_wptc).find(".file_path").attr("mod_time");

    } else {
        var is_selected = '.selected';
        var files_to_restore = {};
        files_to_restore['folders'] = {};
        files_to_restore['files'] = {};
        var folders_count = 0;
        var files_count = 0;
        var selected_items = jQuery(par_obj).find(is_selected);
        jQuery.each(selected_items, function(key, value) {
            if (jQuery(value).hasClass('sub_tree_class') && jQuery(value).hasClass('restore_the_db') == false) {
                files_to_restore['folders'][folders_count] = {};
                files_to_restore['folders'][folders_count]['file_name'] = jQuery(value).children().attr('file_name');
                files_to_restore['folders'][folders_count]['backup_id'] = jQuery(value).children().attr('backup_id');
                folders_count++;
            } else {
                files_to_restore['files'][files_count] = {};
                files_to_restore['files'][files_count]['file_name'] = jQuery(value).children().attr('file_name');
                files_to_restore['files'][files_count]['backup_id'] = jQuery(value).children().attr('backup_id');
                files_to_restore['files'][files_count]['revision_id'] = jQuery(value).children().attr('revision_id');
                files_to_restore['files'][files_count]['mtime_during_upload'] = jQuery(value).children().attr('mod_time');
                files_to_restore['files'][files_count]['g_file_id'] = jQuery(value).children().attr('g_file_id');
                files_count++;
            }
        });
    }

    prepareRestoreProgressDialogWPTC();

    if (typeof reloadFuncTimeout != 'undefined') {
        clearTimeout(reloadFuncTimeout);
    }
    return files_to_restore;
}

function prepareRestoreProgressDialogWPTC(){
    var this_html = '<div class="this_modal_div" style="background-color: #f1f1f1; color: #444;padding: 0px 34px 26px 34px; left:20%; z-index:1000 "><div class="pu_title">Restoring ' + sitenameWPTC + '</div><div class="wcard progress_reverse" style="height:60px; padding:0;"><div class="progress_bar" style="width:0%;"></div>  <div class="progress_cont">Preparing files to restore...</div></div><div style="padding: 10px; text-align: center;">Note: Please do not close this tab until restore completes.</div></div>';

    jQuery("#TB_ajaxContent").html(this_html);
    styling_thickbox_tc('restore');
}

function getAndStoreBridgeURL() {
    var this_plugin_url = this_plugin_url_wptc;
    var this_data = '';
    var post_array = {};
    post_array['getAndStoreBridgeURL'] = 1;
    this_data = jQuery.param(post_array);

    jQuery.post(ajaxurl, {
        action: 'start_restore_tc_wptc',
        data: post_array
    }, function(request) {
        if ((typeof request != 'undefined') && request != null) {
            cuurent_bridge_file_name = request;
        }
    });
}

function dialogOpenCallBack() {

}

function stripquotes(a) {
    if (a.charAt(0) === "'" && a.charAt(a.length - 1) === "'") {
        return a.substr(1, a.length - 2);
    }
    return a;
}

function startBackupFromSettingsPageWPTC() {
    wtc_start_backup_func('');
}

function getTcRestoreProgress() {
    var this_plugin_url = wptcPluginURl;
    var this_data = '';
    
    if (typeof seperate_bridge_call == 'undefined' || seperate_bridge_call != 1) {
    var this_url = this_home_url_wptc + '/' + cuurent_bridge_file_name + '/restore-progress-ajax.php'; //cuurent_bridge_file_name is a global variable and is set already
    } else {
        var this_url = 'restore-progress-ajax.php';
    }
    jQuery.ajax({
        traditional: true,
        type: 'post',
        url: this_url,
        dataType: 'json',
        data: this_data,
        success: function(request) {
            if ((typeof request != 'undefined') && request != null) {
                if (typeof request['total_files_count'] != 'undefined' && typeof request['downloaded_files_percent'] != 'undefined' && request['downloaded_files_percent'] == 0 ) {
                    jQuery('.progress_reverse .progress_cont').html('Preparing files to restore (' + request['total_files_count'] + ')' );
                }else if (typeof request['downloaded_files_percent'] != 'undefined') {
                    jQuery('.progress_reverse .progress_cont').html('Warping back in time... Hold on tight!');
                    jQuery('.progress_reverse .progress_bar').css('width', request['downloaded_files_percent']+'%');
                }else if (typeof request['copied_files_percent'] != 'undefined') {
                    jQuery('.progress_reverse .progress_cont').html('Copying Files... Hold on tight!');
                    jQuery('.progress_reverse .progress_bar').css('width', request['copied_files_percent']+'%');
                }
            } else if (request == null) {
                if (typeof getRestoreProgressTimeout != 'undefined') {
                    clearTimeout(getRestoreProgressTimeout);
                }
            }
        },
        error: function() {

        }
    });
    getRestoreProgressTimeout = setTimeout(function() {
        getTcRestoreProgress();
    }, 5000);
}

function startRestore(files_to_restore, cur_res_b_id, selectedID, is_first_call) {
    start_time_tc = Date.now(); //global variable which will be used to see the activity so as to trigger new call when there is no activity for 60secs
    on_going_restore_process = true;

    if (typeof reloadFuncTimeout != 'undefined') {
        clearTimeout(reloadFuncTimeout);
    }

    var this_plugin_url = wptcOptionsPageURl;
    var this_data = '';

    var post_array = {};
    post_array['cur_res_b_id'] = cur_res_b_id;
    post_array['files_to_restore'] = files_to_restore;
    post_array['selectedID'] = selectedID;
    post_array['is_first_call'] = is_first_call;

    jQuery.post(ajaxurl, {
        action: 'start_restore_tc_wptc',
        data: post_array,
        dataType: 'json',
    }, function(request) {
        if ((typeof request != 'undefined') && request != null) {
            if (request.indexOf("wptcs_callagain_wptce") != -1) {
                startRestore();
            } else if (request.indexOf("restoreInitiatedResult") != -1) {
                request = jQuery.parseJSON(request);
                if (typeof request['restoreInitiatedResult'] != 'undefined' && typeof request['restoreInitiatedResult']['bridgeFileName'] != 'undefined' && request['restoreInitiatedResult']['bridgeFileName']) {
                    cuurent_bridge_file_name = request['restoreInitiatedResult']['bridgeFileName'];
                    getTcRestoreProgress();
                    startBridgeDownload({ initialize: true });
                    checkIfNoResponse('startBridgeDownload');
                } else {
                    show_error_dialog_and_clear_timeout({ error: 'Didnt get required values to initiated restore.' });
                }
            } else if (request.indexOf("error") != -1) {
                request = jQuery.parseJSON(request);
                if (typeof request['error'] != 'undefined') {
                    show_error_dialog_and_clear_timeout(request);
                }
            } else {
                show_error_dialog_and_clear_timeout({ error: 'Initiating Restore failed.' });
            }
        }
    });
}

function startRestore_bridge(files_to_restore, cur_res_b_id, selectedID, ignore_file_write_check) {
    start_time_tc = Date.now(); //global variable which will be used to see the activity so as to trigger new call when there is no activity for 60secs
    on_going_restore_process = true;
    if (typeof reloadFuncTimeout != 'undefined') {
        clearTimeout(reloadFuncTimeout);
    }

    var this_plugin_url = wptcOptionsPageURl;
    var this_data = '';

    var post_array = {};
    post_array['cur_res_b_id'] = cur_res_b_id;
    post_array['files_to_restore'] = files_to_restore;
    post_array['selectedID'] = selectedID;
    var this_url = 'index.php';
    jQuery.post(this_url, {
        traditional: true,
        type: 'post',
        url: this_url,
        data: post_array,
        dataType: 'json',
    }, function(request) {
        if ((typeof request != 'undefined') && request != null) {
            if (request.indexOf("restoreInitiatedResult") != -1) {
                request = jQuery.parseJSON(request);
                if (typeof request['restoreInitiatedResult'] != 'undefined' && typeof request['restoreInitiatedResult']['bridgeFileName'] != 'undefined' && request['restoreInitiatedResult']['bridgeFileName']) {
                    cuurent_bridge_file_name = request['restoreInitiatedResult']['bridgeFileName'];
                    getTcRestoreProgress();
                    startBridgeDownload({ initialize: true });
                    checkIfNoResponse('startBridgeDownload');
                } else {
                    show_error_dialog_and_clear_timeout({ error: 'Didnt get required values to initiated restore.' });
                }
            } else if (request.indexOf("error") != -1) {
                request = jQuery.parseJSON(request);
                if (typeof request['error'] != 'undefined') {
                    show_error_dialog_and_clear_timeout(request);
                }
            } else {
                show_error_dialog_and_clear_timeout({ error: 'Initiating Restore failed.' });
            }
        }
    });
}

function show_error_dialog_and_clear_timeout(request) {
    hard_reset_restore_settings_wptc();
    if (typeof checkIfNoResponseTimeout != 'undefined') {
        clearTimeout(checkIfNoResponseTimeout);
    }
    if (typeof getRestoreProgressTimeout != 'undefined') {
        clearTimeout(getRestoreProgressTimeout);
    }

    var this_html = '<div class="this_modal_div" style="background-color: #f1f1f1; color: #444;padding: 0px 34px 26px 34px; left:20%; z-index:1000"><div class="pu_title">ERROR DURING RESTORE</div><div class="wcard progress_reverse error" style="overflow: scroll;max-height: 210px; padding:0;">  <div class="" style="text-overflow: ellipsis;word-wrap: break-word;text-align: center;padding-top: 19px;padding-bottom: 19px;">' + request['error'] + '</div></div><div style="padding: 10px; text-align: center;">Note: Please do not close this tab until restore completes.</div></div>';
    jQuery("#TB_ajaxContent").html(this_html);
}

function hard_reset_restore_settings_wptc(){
     if (typeof seperate_bridge_call == 'undefined' || seperate_bridge_call != 1) {
        var this_url = this_home_url_wptc + '/' + cuurent_bridge_file_name + '/wptc-ajax.php'; //cuurent_bridge_file_name is a global variable and is set already
    } else {
        var this_url = 'wptc-ajax.php'; //cuurent_bridge_file_name is a global variable and is set already
    }
    var post_data = {};
    post_data['action'] = 'reset_restore_settings';
    jQuery.ajax({
        traditional: true,
        type: 'post',
        url: this_url,
        dataType: 'json',
        data: post_data,
        success: function(request) {
            console.log(request);
        },
    });
}

function startBridgeDownload(data) {

    start_time_tc = Date.now();
    if (typeof getRestoreProgressTimeout == 'undefined') {
        getTcRestoreProgress();
    }
    if(jQuery('.restore_process').length == 0 && jQuery('#TB_ajaxContent').length == 0){
        jQuery('body').append("<div class='restore_process'><div id='TB_ajaxContent'><div class='pu_title'>Restoring your website</div><div class='wcard progress_reverse' style='height:60px; padding:0;'><div class='progress_bar' style='width: 0%;'></div>  <div class='progress_cont'>Preparing files to restore...</div></div><div style='padding: 10px; text-align: center;'>Note: Please do not close this tab until restore completes.</div></div>");
    }
    var this_data = '';

    if (window.location.href.indexOf('wp-tcapsule-bridge') === -1) {
        if (typeof seperate_bridge_call == 'undefined' || seperate_bridge_call != 1) {
            var this_url = this_home_url_wptc + '/' + cuurent_bridge_file_name + '/index.php?continue=true&position=beginning'; //cuurent_bridge_file_name is a global variable and is set already
        } else {
            var this_url = '/index.php?continue=true&position=beginning'; //cuurent_bridge_file_name is a global variable and is set already
        }
        window.location.assign(this_url);
        return false;
    }

    if (typeof seperate_bridge_call == 'undefined' || seperate_bridge_call != 1) {
        var this_url = this_home_url_wptc + '/' + cuurent_bridge_file_name + '/wptc-ajax.php'; //cuurent_bridge_file_name is a global variable and is set already
    } else {
        var this_url = 'wptc-ajax.php'; //cuurent_bridge_file_name is a global variable and is set already
    }

    if (typeof data != 'undefined') {
        this_data = jQuery.param(data);
    }
    jQuery.ajax({
        traditional: true,
        type: 'post',
        url: this_url,
        dataType: 'json',
        data: this_data,
        success: function(request) {
            if (typeof request != 'undefined' && request != null) {
                // jsonParsedRequest = jQuery.parseJSON(request);

                if (request == 'wptcs_callagain_wptce') {
                    startBridgeDownload();
                } else if (request == 'continue_from_email') {
                    if (typeof checkIfNoResponseTimeout != 'undefined') {
                        clearTimeout(checkIfNoResponseTimeout);
                    }

                    startBridgeCopy(start_bridge_copy);
                    checkIfNoResponse('startBridgeCopy');
                } else if (request == 'wptcs_over_wptce') {
                   startBridgeDownloadOver();
                } else if (typeof request.error != 'undefined') {
                    //request = jQuery.parseJSON(request);
                    show_error_dialog_and_clear_timeout(request);
                } else if (typeof request.not_safe_for_write_limit_reached != 'undefined' && request.not_safe_for_write_limit_reached) {
                    //request = jQuery.parseJSON(request);
                    show_safe_files_limit_dialog_and_clear_timeout(request.not_safe_for_write_limit_reached);
                }
            }
        },
        error: function(errData) {
                        console.log(errData);
            if (errData.responseText.indexOf('wptcs_callagain_wptce') !== -1) {
                console.log('got response from err data');
                startBridgeDownload();
                return false;
            }else if (errData.responseText.indexOf('wptcs_over_wptce') !== -1) {
                console.log('got response from err data');
                startBridgeDownloadOver();
                return false;
            }
            if(!wptc_restore_retry_limit_checker()){
                if (errData.status != 200) {
                    var deep_err_check = errData.responseText.replace(/\s+/, "");
                     if(deep_err_check == ''){
                        var fomatted_err_msg = 'Ajax call returned error: '+errData.statusText;
                     } else {
                        var fomatted_err_msg = 'Ajax call returned error: '+errData.responseText;
                    }
                    show_error_dialog_and_clear_timeout({ error: fomatted_err_msg });
                } else {
                    show_error_dialog_and_clear_timeout({ error: 'unknown error occured  :-(' });
                }
				return false;
            }
            if (typeof errData.responseText == 'undefined' ||errData.responseText == undefined || !errData.responseText) {
                setTimeout(function(){
                    startBridgeDownload();
                }, 5000);
                return false;
            } else {
                var deep_err_check = errData.responseText.replace(/\s+/, "");
                if (!deep_err_check || deep_err_check == 'undefined' || deep_err_check == '') {
                    setTimeout(function(){
                        startBridgeDownload();
                    }, 5000);
                    return false;
                }
            }
            if (errData.status == 404) {
                return false;
            }
            if(deep_err_check == ''){
                var fomatted_err_msg = 'Ajax call returned error: '+errData.statusText;
            } else {
                var fomatted_err_msg = 'Ajax call returned error: '+errData.responseText;
            }
            show_error_dialog_and_clear_timeout({ error: fomatted_err_msg });
        }
    });

}

function startBridgeDownloadOver(){
     if (typeof checkIfNoResponseTimeout != 'undefined') {
        clearTimeout(checkIfNoResponseTimeout);
    }
    var start_bridge_copy = {};
    start_bridge_copy['initialize'] = true;
    start_bridge_copy['wp_prefix'] = wp_base_prefix_wptc; //getting from global var

    startBridgeCopy(start_bridge_copy);
    checkIfNoResponse('startBridgeCopy');
}

function wptc_restore_retry_limit_checker(){
    console.log('wptc_restore_retry_limit_checker');

    var max_retry = 3;
    if (typeof wptc_restore_retry_count == 'undefined') {
        wptc_restore_retry_count = 1;
    } else {
        wptc_restore_retry_count++;
    }
    console.log("wptc_restore_retry_count: ", wptc_restore_retry_count);
    if (wptc_restore_retry_count >= max_retry) {
        console.log("wptc_restore_retry_limit_checker :", "limit reached");
        get_last_php_error();
        return false;
    } else {
        console.log("wptc_restore_retry_limit_checker :", "under control");
        return true;
    }
}

function get_last_php_error(){
    if (typeof seperate_bridge_call == 'undefined' || seperate_bridge_call != 1) {
        var this_url = this_home_url_wptc + '/' + cuurent_bridge_file_name + '/wptc-ajax.php'; //cuurent_bridge_file_name is a global variable and is set already
    } else {
        var this_url = 'wptc-ajax.php'; //cuurent_bridge_file_name is a global variable and is set already
    }
    console.log("get_last_php_error");

    var post_data = {};
    post_data['action'] = 'get_last_php_error';
    jQuery.ajax({
        traditional: true,
        type: 'post',
        url: this_url,
        data: post_data,
        success: function(request) {
    console.log("get_last_php_error response");

            console.log(request);
            var deep_err_check = request.replace(/\s+/, "");
            if (request && deep_err_check) {
                show_error_dialog_and_clear_timeout({ error: request });
            } else {
                show_error_dialog_and_clear_timeout({ error: 'unknown error occured  :-(' });
            }
        },
    });
}

function show_safe_files_limit_dialog_and_clear_timeout(filesObj) {
    if (typeof checkIfNoResponseTimeout != 'undefined') {
        clearTimeout(checkIfNoResponseTimeout);
    }
    if (typeof getRestoreProgressTimeout != 'undefined') {
        clearTimeout(getRestoreProgressTimeout);
    }

    jQuery("#TB_ajaxContent").html('');

    var files_div = '';
    jQuery.each(filesObj, function(k, v) {
        files_div += '<p> - ' + k + '</p>';
    });

    var btn_div = '';
    btn_div = '<input type="button" class="button-primary resume_restore_ignore_file_write_wptc" value="Skip these files &amp; restore" style="float: right;">';
    btn_div += '<input type="button" class="button-primary resume_restore_restart_file_write_wptc" value="Try Again">';

    var this_html = '';
    this_html += '<div class="this_modal_div" style="background-color: #f1f1f1;color: #444;padding: 0px 34px 26px 34px; left:20%; z-index:1000">';
    this_html += '<div class="pu_title">FILES NOT WRITABLE FOR RESTORE</div>'+
    '<div style="line-height: 22px; margin-bottom: 20px;">The following files are not writable. Please change the file permissions or enable FTP for this restore - <a href="http://docs.wptimecapsule.com/article/10-enable-ftp-file-permissions" target="_blank"> Check how? </a></div>'+
    '<div class="wcard progress_reverse error" style="overflow: scroll;max-height: 210px; padding:0;width: auto;margin: 0 auto 20px;padding-left: 10px;">';
    this_html += '<div class="error_files_cont_wptc">' + files_div + '</div>';
    this_html += '</div>';
    this_html += '<div class="error_files_btn_wptc">' + btn_div + '</div>';
    this_html += '</div>';

    jQuery("#TB_ajaxContent").html(this_html);
}

function startBridgeCopy(data) {
    start_time_tc = Date.now();

    var this_data = '';

    if (typeof seperate_bridge_call == 'undefined' || seperate_bridge_call != 1) {
        var this_url = this_home_url_wptc + '/' + cuurent_bridge_file_name + '/tc-init.php'; //cuurent_bridge_file_name is a global variable and is set already
    } else {
        var this_url = 'tc-init.php'; //cuurent_bridge_file_name is a global variable and is set already
    }

    if (typeof data != 'undefined') {
        this_data = jQuery.param(data);
    }
    jQuery.ajax({
        traditional: true,
        type: 'post',
        url: this_url,
        dataType: 'json',
        data: this_data,
        success: function(request) {
            if (typeof request != 'undefined' && request != null) {
                if (request == 'wptcs_callagain_wptce') {
                    startBridgeCopy({ wp_prefix: wp_base_prefix_wptc }); //getting from global variable
                } else if (request == 'wptcs_over_wptce') {
                    startBridgeCopyOver();
                } else if (typeof request['error'] != 'undefined') {
                    show_error_dialog_and_clear_timeout(request);
                } else {
                    show_error_dialog_and_clear_timeout({ error: 'Fatal error during Bridge Process.' });
                }
            }
        },
        error: function(errData) {
            console.log(errData);
            if (errData.responseText.indexOf('wptcs_callagain_wptce') !== -1) {
                startBridgeCopy({ wp_prefix: wp_base_prefix_wptc }); //getting from global variable
                return false;
            } else if (errData.responseText.indexOf('wptcs_over_wptce') !== -1) {
                console.log('got response from err data');
                startBridgeCopyOver();
                return false;
            }
            if(!wptc_restore_retry_limit_checker()){
                return false;
            }
            if (typeof errData.responseText == 'undefined' ||errData.responseText == undefined || !errData.responseText) {
                setTimeout(function(){
	                startBridgeCopy({ wp_prefix: wp_base_prefix_wptc }); //getting from global variable
                }, 5000);
                return false;
            } else {
                var deep_err_check = errData.responseText.replace(/\s+/, "");
                if (!deep_err_check || deep_err_check == 'undefined' || deep_err_check == '') {
					setTimeout(function(){
						startBridgeCopy({ wp_prefix: wp_base_prefix_wptc }); //getting from global variable
					}, 5000);
                    return false;
                }
            }
            var fomatted_err_msg = 'Ajax call returned error: '+errData.responseText;
            show_error_dialog_and_clear_timeout({ error: fomatted_err_msg });
        }
    });
}

function startBridgeCopyOver(){
    clearTimeout(checkIfNoResponseTimeout);
    if (typeof getRestoreProgressTimeout != 'undefined') {
        clearTimeout(getRestoreProgressTimeout);
    }

    var this_html = '<div class="this_modal_div" style="background-color: #f1f1f1;  color: #444;padding: 0px 34px 26px 34px; left:20%; z-index:1000"><span class="dialog_close"></span><div class="pu_title">DONE</div><div class="wcard clearfix" style="width:375px">  <div class="l1">Your website was restored successfully. Yay! <br> Redirecting in 5 secs...</div>  </div></div>';
    jQuery("#TB_ajaxContent").html(this_html);
    if(location.href.toLowerCase().indexOf('wp-tcapsule-bridge') !== -1){
         jQuery('.this_modal_div').css('left','40%');
     }
    jQuery("#TB_ajaxContent").html(this_html);
    if (typeof redirect_to_plugin_home == 'undefined') {
        redirect_to_plugin_home = setTimeout(function() {
            parent.location.assign(wptcMonitorPageURl);
        }, 3000);
    }
}

function checkIfNoResponse(this_func) {
    if (typeof this_func != 'undefined' && this_func != null) {
        ajax_function_tc = this_func;
    }

    var this_time_tc = Date.now();
    if ((this_time_tc - start_time_tc) >= 60000) {
        if (ajax_function_tc == 'startBridgeCopy') {
            var continue_bridge = {};
            continue_bridge['wp_prefix'] = wp_base_prefix_wptc; //am sending the prefix ; since it is a bridge
            startBridgeCopy(continue_bridge);
        } else if (ajax_function_tc == 'startBridgeDownload') {
            startBridgeDownload();
        }
    }
    if (typeof checkIfNoResponseTimeout != 'undefined') {
        clearTimeout(checkIfNoResponseTimeout);
    }
    checkIfNoResponseTimeout = setTimeout(function() {
        checkIfNoResponse();
    }, 15000);
}

function dialogCloseCallBack() {

}

function initial_backup_name_store() {
    jQuery.post(ajaxurl, {
        action: 'store_name_for_this_backup_wptc',
        data: 'Initial Backup'
    }, function(data) {});
}

function restore_confirmation_pop_up(){
    var html = '<div class="theme-overlay wptc_restore_confirmation" style="z-index: 1000;"><div class="theme-wrap wp-clearfix" style="width: 450px;height: 220px;left: 0px;"><div class="theme-header"><button class="close dashicons dashicons-no no_exit_restore_wptc"><span class="screen-reader-text">Close details dialog</span></button> <h2 style="margin-left: 127px;">Restore your website</h2></div><div class="theme-about wp-clearfix"> <h4 style="font-weight: 100;text-align: center;">Clicking on Yes will continue to restore your website. <br> Are you sure want to continue ?</h4></div><div class="theme-actions"><div class="active-theme"><a lass="button button-primary customize load-customize hide-if-no-customize">Customize</a><a class="button button-secondary">Widgets</a> <a class="button button-secondary">Menus</a> <a class="button button-secondary hide-if-no-customize">Header</a> <a class="button button-secondary">Header</a> <a class="button button-secondary hide-if-no-customize">Background</a> <a class="button button-secondary">Background</a></div><div class="inactive-theme"><a class="button button-primary load-customize hide-if-no-customize" id="yes_continue_restore_wptc">Yes</a><a class="close button button-secondary activate no_exit_restore_wptc">No</a></div></div></div></div>';
    jQuery('#TB_load').remove();
    jQuery('#TB_window').append(html).removeClass('thickbox-loading');
    jQuery('#TB_ajaxContent').hide();
}

function trigger_selected_files_restore(){
    var selectedID = jQuery('.open').attr('this_backup_id');
    var files_to_restore = {};
    files_to_restore = wtc_initializeRestore(jQuery(restore_obj), 'single');
    startRestore(files_to_restore, false, selectedID, true);
    checkIfNoResponse('startRestore');
    // console.log(files_to_restore);
    // console.log(selectedID);
    // e.stopImmediatePropagation();
    return false;
}

function trigger_to_point_restore(){
    var cur_res_b_id = jQuery(restore_obj).closest(".single_group_backup_content").attr("this_backup_id");
    var files_to_restore = {};
    files_to_restore = wtc_initializeRestore(jQuery(restore_obj), 'all');
    // console.log(files_to_restore);
    // console.log(cur_res_b_id);
    startRestore(false, cur_res_b_id, '', true);
    // e.stopImmediatePropagation();
    return false;
}

jQuery(document).ready(function() {

    if (startBackupFromSettingsWPTC == true) {
        startBackupFromSettingsPageWPTC();
    }

    jQuery('#start_backup').on('click', function() {
        if (jQuery(this).text() != 'Stop Backup') {
            is_backup_started = true; //setting global variable for backup status
            jQuery(this).text("Stop Backup");
            wtc_start_backup_func('');
        } else {
            wtc_stop_backup_func();
        }
    });

    jQuery('#stop_backup').on('click', function() {
        if (jQuery(this).text() != 'Stop Backup') {
            jQuery(this).text("Stop Backup");
            wtc_start_backup_func('');
        } else {
            wtc_stop_backup_func();
        }
    });

    jQuery('body').on('click', '.bridge_restore_now',function(e) {
        var cur_res_b_id = jQuery(this).attr('backup_id');
        var files_to_restore = {};

        jQuery('.show_restores').hide();
        jQuery('.restore_process').show();

        startRestore_bridge(files_to_restore, cur_res_b_id);

        // checkIfNoResponse('startRestore');
        // getTcRestoreProgress();
        // getAndStoreBridgeURL();

        e.stopImmediatePropagation();
        return false;
    });

    jQuery('body').on('click', '.resume_restore_ignore_file_write_wptc', function(e) {
        prepareRestoreProgressDialogWPTC();

        getTcRestoreProgress();

        startBridgeDownload({ignore_file_write_check: 1});

        checkIfNoResponse('startBridgeDownload');

        e.stopImmediatePropagation();
        return false;
    });

    jQuery('body').on('click', '.resume_restore_restart_file_write_wptc', function(e) {
        prepareRestoreProgressDialogWPTC();

        getTcRestoreProgress();

        startBridgeDownload({ignore_file_write_check: 0});

        checkIfNoResponse('startBridgeDownload');

        e.stopImmediatePropagation();
        return false;
    });

    jQuery('#stop_restore_tc').on('click', function() {
        wtc_stop_restore_func();
    });

    jQuery('#stop_restore_tc').on('click', '.this_modal_div', function(e) {
        wtc_stop_restore_func();
    });

    jQuery('.restore_err_demo_wptc').on('click', function(e) {
        jQuery('.notice, #update-nag').remove();
        var yo_files = {
            'wp-content/dark/wp-file-1': 1,
            'wp-content/dark/wp-file-2': 1,
            'wp-content/dark/wp-file-3': 1,
            'wp-content/dark/wp-file-4': 1,
        };
        jQuery(".wptc-thickbox").click();
        styling_thickbox_tc();
        show_safe_files_limit_dialog_and_clear_timeout(yo_files);
    });

    if (freshbackupPopUpWPTC) {
        freshBackupPopUpShow();
    }
});
