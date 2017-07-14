jQuery(document).ready(function($) {
	if (window.location.href.indexOf('themes.php?theme=') !== -1) {
		listen_theme_more_info_model();
		listen_theme_more_info_model_before_4_3();
	}
	// same as you'd pass it to bind()
	// [fn] is the handler function
	jQuery.fn.bindFirst = function(name, fn) {
		// bind as you normally would
		// don't want to miss out on any jQuery magic
		this.on(name, fn);

		// Thanks to a comment by @Martin, adding support for
		// namespaced events too.
		this.each(function() {
			var handlers = jQuery._data(this, 'events')[name.split('.')[0]];
			console.log(handlers);
			// take out the handler we just inserted from the end
			var handler = handlers.pop();
			// move it at the beginning
			handlers.splice(0, 0, handler);
		});
	};

	jQuery("#plugin_update_from_iframe, #plugin-information-footer a").bindFirst('click', function(e) {
		handle_iframe_requests_wptc(this, e , false);
	});

	jQuery('body').on("click", '.plugin-update-from-iframe-bbu-wptc', function(e) {
		handle_iframe_requests_wptc(this, e , true);
	});

	//Update page theme and plugin update also plugin normal update
	jQuery(".plugin-update-tr .update-message a, .update-now.button, .upgrade .button, .plugin-action-buttons .update-now").on("click", function(e) {
		if (!jQuery(this).hasClass('thickbox')) {
			if (jQuery(this).hasClass('update-link') || jQuery(this).parents('td').hasClass('plugin-update') || (jQuery(this).parents('ul').hasClass('plugin-action-buttons') && jQuery(this).hasClass('update-now'))) {
				handle_plugin_link_request_wptc(this, e, false);
			} else if (this.id.toLowerCase().indexOf('upgrade-plugins') !== -1){
				handle_plugin_upgrade_request_wptc(this, e, false);
			} else if (this.id.toLowerCase().indexOf('upgrade-themes') !== -1){
				handle_themes_upgrade_request_wptc(this, e, false);
			}
		}
	});


	//Plugins page bulk update
	jQuery("#bulk-action-form .button, .bulkactions .button").on("click", function(e) {
		if(window.location.href.toLowerCase().indexOf('plugins.php') !== -1 && jQuery(this).prev('select').val().toLowerCase().indexOf('update') !== -1){
			if (is_current_update_action_set()) {
				clear_current_update_action();
			} else {
				if(jQuery(this).prev('select').val().toLowerCase().indexOf('update') !== -1){
					handle_plugin_button_action_request_wptc(this, e , false);
				}
			}
		}


	});

	//core update
	jQuery("form #upgrade").on("click", function(e) {
		handle_core_upgrade_request_wptc(this, e , false);
	});

	//translation update
	jQuery('form.upgrade .button').on("click", function(e) {
		handle_translation_upgrade_request_wptc(this, e, false);
	});

	//theme update
	setTimeout(function (){
		jQuery('.theme-screenshot , .more-details, .theme-update').on("click", function(e) {
			listen_theme_more_info_model();
			listen_theme_more_info_model_before_4_3();
		});
	}, 500);

	//wordpress 4.5
	jQuery('body').on("click", '.theme-info .notice-warning a', function(e) {
		if (!jQuery(this).hasClass('thickbox')) {
			if (is_current_update_action_set()) {
				clear_current_update_action();
			} else {
				prevent_action_propagation(e);
				var update_items = [];
				update_items.push(jQuery(this).attr('data-slug'));
				if (update_items.length > 0) {
					new_theme_update_listener = 1;
					check_to_show_dialog(jQuery(this), update_items, 'theme');
				}
			}
		}
	});

	//registering the events
	jQuery("body").on("click" , ".tc_backup_before_update", function(e) {
		if (jQuery(this).hasClass('disabled')) {
			return false;
		}
		jQuery('#TB_window').removeClass('thickbox-loading');
		var checkbox_selected = jQuery('input[name="backup_before_update"]:checked').val();
		if (checkbox_selected == 'on') {
			start_manual_backup_wptc(wptc_bbu_obj, 'from_bbu', wptc_update_items, wptc_update_ptc_type, 'always');
		} else {
			start_manual_backup_wptc(wptc_bbu_obj, 'from_bbu', wptc_update_items, wptc_update_ptc_type);
		}
	});

	jQuery("body").on("click", ".tc_no_backup, .tc_no_backup#no_backup_just_update, #no_backup_just_update", function(e) {
		prevent_action_propagation(e);
		if (wptc_update_ptc_type == 'theme' && typeof new_theme_update_listener != 'undefined' && new_theme_update_listener == 1) {
				jQuery.each(_wpThemeSettings.themes, function( key, value ) {
					console.log(value);
					if(value.id == update_required_theme_wptc){
					   parent.location.assign(jQuery(value.update).find("#update-theme").attr('href'));
					}
				});
		}
		var checkbox_selected = jQuery('input[name="backup_before_update"]:checked').val();
		if (checkbox_selected == 'on') {
			update_backup_before_update_never();
		}
		if (typeof wptc_this_update_link != "undefined" && wptc_this_update_link != '' && wptc_this_update_link != "undefined") {
			parent.location.assign(wptc_this_update_link);
		} else {
			tb_remove();
			current_update_action = 'no'; //this global variable is used only for upgrade-input related updates; continuing update after backup process
			jQuery(wptc_bbu_obj).click();
		}
	});

	jQuery("body").on("click", ".dialog_close" ,function() {
		tb_remove();
	});

	jQuery("#enable_auto_update_wptc").on("click", function() {
		jQuery("#enable_auto_update_options_wptc").show();
	});

	jQuery("#disable_auto_update_wptc").on("click", function() {
		jQuery("#enable_auto_update_options_wptc").hide();
	});

	jQuery("input[name=wptc_auto_plugins]:checkbox").change(function(){
		jQuery('#wptc_auto_update_plugins_dw').hide();
		if(jQuery(this).is(':checked')){
			jQuery('#wptc_auto_update_plugins_dw').show();
			fancy_tree_init_auto_update_plugins_wptc();
		}
	});

	//Do not listen manual settings to auto 
	// jQuery("input:radio[name=backup_before_update_setting]").change(function(){
		// if(jQuery(this).val() === 'always'){
		// 	jQuery('#auto_update_settings_wptc').show();
		// } else {
		// 	jQuery('#auto_update_settings_wptc').hide();
		// }
	// });

	jQuery("input[name=wptc_auto_themes]:checkbox").change(function(){
		jQuery('#wptc_auto_update_themes_dw').hide();
		if(jQuery(this).is(':checked')){
			jQuery('#wptc_auto_update_themes_dw').show();
			fancy_tree_init_auto_update_themes_wptc();
		}
	});

	if(jQuery("input[name=wptc_auto_plugins]").is(':checked')){
		jQuery('#wptc_auto_update_plugins_dw').show();
		fancy_tree_init_auto_update_plugins_wptc();
	}

	if(jQuery("input[name=wptc_auto_themes]").is(':checked')){
		jQuery('#wptc_auto_update_themes_dw').show();
		fancy_tree_init_auto_update_themes_wptc();
	}


	jQuery("body").on("click", ".upgrade-plugins-bbu-wptc" ,function(e) {
		handle_plugin_upgrade_request_wptc(this, e , true);
	});

	jQuery("body").on("click", ".update-link-plugins-bbu-wptc, .update-now-plugins-bbu-wptc" ,function(e) {
		handle_plugin_link_request_wptc(this, e , true);
	});

	jQuery("body").on("click", ".button-action-plugins-bbu-wptc" ,function(e) {
		handle_plugin_button_action_request_wptc(this, e , true);
	});


	jQuery("body").on("click", ".upgrade-themes-bbu-wptc" ,function(e) {
		handle_themes_upgrade_request_wptc(this, e , true);
	});

	jQuery("body").on("click", ".upgrade-core-bbu-wptc" ,function(e) {
		handle_core_upgrade_request_wptc(this, e , true);
	});

	jQuery("body").on("click", ".upgrade-translations-bbu-wptc" ,function(e) {
		handle_translation_upgrade_request_wptc(this, e , true);
	});
});

function fancy_tree_init_auto_update_plugins_wptc(){
	jQuery("#wptc_auto_update_plugins_dw").fancytree({
		checkbox: true,
		selectMode: 2,
		icon:true,
		debugLevel:0,
		source: {
			url: ajaxurl,
			data: {"action": "get_installed_plugins_wptc"},
		},
		init: function (event, data) {
			data.tree.getRootNode().visit(function (node) {
				if (node.data.preselected) node.setSelected(true);
			});
		},
		select: function(event, data) {
			// Get a list of all selected nodes, and convert to a key array:
			var selKeys = jQuery.map(data.tree.getSelectedNodes(), function(node){
				return node.key;
			});
			jQuery("#auto_include_plugins_wptc").val(selKeys.join(","));
		},
		dblclick: function(event, data) {
			data.node.toggleSelected();
		},
		keydown: function(event, data) {
			if( event.which === 32 ) {
				data.node.toggleSelected();
				return false;
			}
		},
		cookieId: "fancytree-Cb3",
		idPrefix: "fancytree-Cb3-"
	});
}

function fancy_tree_init_auto_update_themes_wptc(){
	jQuery("#wptc_auto_update_themes_dw").fancytree({
		checkbox: true,
		selectMode: 2,
		icon:true,
		debugLevel:0,
		source: {
			url: ajaxurl,
			data: {"action": "get_installed_themes_wptc"},
		},
		init: function (event, data) {
			data.tree.getRootNode().visit(function (node) {
				if (node.data.preselected) node.setSelected(true);
			});
		},
		select: function(event, data) {
			var selKeys = jQuery.map(data.tree.getSelectedNodes(), function(node){
				return node.key;
			});
			jQuery("#auto_include_themes_wptc").val(selKeys.join(","));
		},
		dblclick: function(event, data) {
			data.node.toggleSelected();
		},
		keydown: function(event, data) {
			if( event.which === 32 ) {
				data.node.toggleSelected();
				return false;
			}
		},
		cookieId: "fancytree-Cb3",
		idPrefix: "fancytree-Cb3-"
	});
}


function listen_theme_more_info_model_before_4_3(){
	setTimeout(function (){
		jQuery('.theme-info .theme-update-message a').on("click", function(e) {
			if (!jQuery(this).hasClass('thickbox')) {
				if (is_current_update_action_set()) {
					clear_current_update_action();
				} else {
					prevent_action_propagation(e);
					var update_items = [];
					if (window.location.href.match(/theme=([^&]+)/) && window.location.href.match(/theme=([^&]+)/)[1]) {
						update_items.push(window.location.href.match(/theme=([^&]+)/)[1]);
					} else if(jQuery(this).attr('data-slug') != undefined){
						update_items.push(jQuery(this).attr('data-slug'));
					}
					if (update_items.length > 0) {
						new_theme_update_listener = 1;
						check_to_show_dialog(jQuery(this), update_items, 'theme');
					}
				}
			}
		});
	}, 500);
}

function listen_theme_more_info_model(){
	setTimeout(function (){
		jQuery('#update-theme').on("click", function(e) {
			if (!jQuery(this).hasClass('thickbox')) {
				if (is_current_update_action_set()) {
					clear_current_update_action();
				} else {
					prevent_action_propagation(e);
					var update_items = [];
					update_items.push(jQuery(this).attr('data-slug'));
					if (update_items.length > 0) {
						new_theme_update_listener = 1;
						check_to_show_dialog(jQuery(this), update_items, 'theme');
					}
				}
			}
		});
		get_current_backup_status_wptc(); //to add extra buttons for bbu
	}, 500);
}

function prevent_action_propagation(e){
	e.preventDefault();
	e.stopImmediatePropagation();
	e.stopPropagation();
	return false;
}

function check_to_show_dialog(obj, update_items, update_ptc_type, direct_update) {
	jQuery('#TB_window').html('');
	//to show the backup dialog box before updating plugins , themes etc
	if (typeof check_to_show_dialog_called != 'undefined' && check_to_show_dialog_called == 1 ) {
		return false;
	}
	check_to_show_dialog_called = 1;
	jQuery.post(ajaxurl, {
		action: 'get_check_to_show_dialog',
	}, function(data) {
		delete check_to_show_dialog_called;
		data = jQuery.parseJSON(data);
		//console.log(data);
		if (typeof data != 'undefined') {
			var is_backup_running = 0;
			if (data['is_backup_running'] == 'yes') {
				is_backup_running = 1;
			}
			if ( (data['backup_before_update_setting'] == 'everytime' && direct_update === true) || data['backup_before_update_setting'] == 'always') {
				show_is_backup_dialog_box_tc(obj, 'always', update_items, update_ptc_type, is_backup_running);
			} else {
				if ((typeof obj.attr("href") != 'undefined') && obj.attr("href") != '') {
					current_update_action = 'no'; //this global variable is used only for upgrade-input related updates; continuing update after backup process
					jQuery(obj).click();
					// parent.location.assign(obj.attr("href"));
				} else {
					current_update_action = 'no'; //this global variable is used only for upgrade-input related updates; continuing update after backup process
					jQuery(obj).click();
				}
			}
		}
	});
}

function start_backup_bbu_wptc(obj, update_items, update_ptc_type){
	window.parent.start_manual_backup_wptc(obj, 'from_bbu', update_items, update_ptc_type);
	jQuery(window.parent.document.body).find('TB_overlay').remove();
	jQuery(window.parent.document.body).find('TB_window').remove();
}

function show_is_backup_dialog_box_tc(obj, direct_backup, update_items, update_ptc_type, is_backup_running) {
	remove_other_thickbox_wptc();
	// jQuery('.notice, #update-nag').remove();
	jQuery('.TB_window').removeClass('thickbox_loading');
	jQuery('#TB_load').remove();
	jQuery('#TB_window').html('');
	//this function shows the dialog box to choose backup before updating
	jQuery("#wptc-content-id").remove();
	jQuery(".wrap").append('<div id="wptc-content-id" style="display:none;"> <p> hidden cont. </p></div><a class="thickbox wptc-thickbox" style="display:none" href="#TB_inline?width=500&height=500&inlineId=wptc-content-id&modal=true"></a>');
	//store the update link in a global variable
	update_click_obj_wptc = obj;
	if (update_ptc_type == 'theme') {
		update_obj_type_wptc = 'theme';
		update_required_theme_wptc = update_items[0];
	} else{
		update_obj_type_wptc = 'not_theme';
		update_required_theme_wptc = '';
	}

	this_update_link = obj.attr("href");

	if (this_update_link) {
		new_updated_action = 'href=' + obj.attr('href') + '';
	} else {
		current_update_action = "no";
		new_updated_action = "id='no_backup_just_update'";
	}

	if (is_backup_running) {
		var head_div = '<div class="theme-overlay wptc_restore_confirmation" style="z-index: 1000;">';
			var top_div = '<div class="theme-wrap wp-clearfix" style="width: 450px;height: 220px;left: 0%;">';
			var head_text = '<div class="theme-header"><button class="close dashicons dashicons-no "><span class="screen-reader-text">Close details dialog</span></button> <h2 style="clear:none;text-align: center;margin-top: 1em;">Updating '+update_ptc_type+'</h2></div>'
			var inner_text = '<div class="theme-about wp-clearfix"> <h4 style="font-weight: 100;text-align: center;">A backup is currently running, please wait for it to complete before you initiate backup up before update</h4></div>';
			var footer = '<div class="theme-actions"><div class="active-theme"><a lass="button button-primary customize load-customize hide-if-no-customize">Customize</a><a class="button button-secondary">Widgets</a> <a class="button button-secondary">Menus</a> <a class="button button-secondary hide-if-no-customize">Header</a> <a class="button button-secondary">Header</a> <a class="button button-secondary hide-if-no-customize">Background</a> <a class="button button-secondary">Background</a></div><div class="inactive-theme"><a class="button button-primary load-customize hide-if-no-customize btn_pri tc_backup_before_update" update_link=' + obj.attr("href") + ' style="display:none">YES, Backup &amp; Update</a><a class="button button-secondary activate btn_sec"'+new_updated_action+' >No need for backup, just update</a></div></div></div></div>';
			var dialog_content = head_div+top_div+head_text+inner_text+footer;
	} else if ((typeof direct_backup != 'undefined') && (direct_backup == 'always')) {
		jQuery(obj).addClass('disabled button-disabled-bbu-wptc');
		validate_free_paid_items_wptc(obj, update_items, update_ptc_type);
		// start_backup_bbu_wptc(obj, update_items, update_ptc_type);
		return false;
	} else {
		styling_thickbox_tc('backup_yes_no');
		var head_div = '<div class="theme-overlay wptc_restore_confirmation" style="z-index: 1000;">';
		var top_div = '<div class="theme-wrap wp-clearfix" style="width: 530px;height: 190px;left: 20%;">';
		var head_text = '<div class="theme-header"><button class="close dashicons dashicons-no "><span class="screen-reader-text">Close details dialog</span></button> <h2 style="clear:none;text-align: center;margin-top: 1em;">Updating '+update_ptc_type+'</h2></div>'
		var inner_text = '<div class="theme-about wp-clearfix"> <h4 style="font-weight: 100;text-align: center;">Do you want to backup your website before updating?</h4></div>';
		var footer = '<div class="theme-actions"><div class="active-theme"><a lass="button button-primary customize load-customize hide-if-no-customize">Customize</a><a class="button button-secondary">Widgets</a> <a class="button button-secondary">Menus</a> <a class="button button-secondary hide-if-no-customize">Header</a> <a class="button button-secondary">Header</a> <a class="button button-secondary hide-if-no-customize">Background</a> <a class="button button-secondary">Background</a></div><div class="inactive-theme"><span style="line-height: 29px;"><input type="checkbox" id="backup_before_update_checkbox" name="backup_before_update"><label for="backup_before_update_checkbox">Remember me</label></span><a class="button button-primary load-customize hide-if-no-customize btn_pri tc_backup_before_update" update_link=' + obj.attr("href") + ' style="margin-left: 30px;" >Yes, Backup &amp; Update</a><a class="button button-secondary activate btn_sec tc_no_backup" '+new_updated_action+' style="margin-left: 20px;">No, Just update</a></div></div></div></div>';
		var dialog_content = head_div+top_div+head_text+inner_text+footer;
	}
	styling_thickbox_tc('backup_yes');
	window.parent.push_alert_pop_up(dialog_content, obj, direct_backup, update_items, update_ptc_type, is_backup_running, this_update_link, update_required_theme_wptc);
}

function validate_free_paid_items_wptc(obj, update_items, update_ptc_type){
	if (update_ptc_type != 'plugin' && update_ptc_type != 'theme') {
		start_backup_bbu_wptc(obj, update_items, update_ptc_type);
		return false;
	}

	wptc_current_click_obj = obj;
	wptc_current_update_items = update_items;
	wptc_current_update_ptc_type = update_ptc_type;

	jQuery.post(ajaxurl, {
		action: 'validate_free_paid_items_wptc',
		update_items: update_items,
		update_ptc_type: update_ptc_type,
	}, function(data) {
		data = jQuery.parseJSON(data);
		console.log(data);
		if (jQuery.isEmptyObject(data.paid_items)) {
			console.log('start backup without msg');
			start_backup_bbu_wptc(wptc_current_click_obj, wptc_current_update_items, wptc_current_update_ptc_type);
			return false;
		} else {
			console.log('start backup with message');
			var msg = '';
			jQuery.each(data.paid_items, function(key, value){
				if(!msg){
					msg += '<div style="font-size:13px !important"><ul style="text-align: justify;list-style-type:disc"> <li>'+value+'</li>';
				} else {
					msg += '<li>' + value + '</li>';
				}
			});
			msg += '</ul></div>';
			var title = 'Cannot update the following premium '+ wptc_current_update_ptc_type+'s';
			var footer = 'However, we have started the backup. Once its completed you can manually update them.';
			load_custom_popup_wptc(true, 'cannot_update_paid_items', true, title, msg, footer);
			start_backup_bbu_wptc(wptc_current_click_obj, wptc_current_update_items, wptc_current_update_ptc_type);
			return false;
		}
	});
}

function push_alert_pop_up(dialog_content, obj, direct_backup, update_items, update_ptc_type, is_backup_running, this_update_link, update_required_theme_wptc){
	wptc_bbu_obj = obj;
	wptc_update_items = update_items;
	wptc_update_ptc_type = update_ptc_type;
	wptc_this_update_link = this_update_link;
	update_required_theme_wptc = update_required_theme_wptc;
	jQuery(".wptc-thickbox").click();
	jQuery('#TB_ajaxContent').hide();
	jQuery('#TB_load').remove();
	jQuery('#TB_window').html(dialog_content).removeClass('thickbox-loading');
	styling_thickbox_tc('backup_yes');
}

function update_backup_before_update_never() {
	jQuery.post(ajaxurl, {
		action: 'wptc_backup_before_update_setting',
		backup_before_update_setting: 'never',
	}, function(data) {
		//console.log(data);
	});
}

function push_bbu_button_wptc(data) {
	if(data.bbu_setting_status === 'everytime'){
		if(Object.keys(data.backup_progress).length > 0){
			bbu_buttons_wptc(true);
		} else {
			bbu_buttons_wptc(false);
		}
	} else if(data.bbu_setting_status === 'always'){
		console.log('bbu_always');
	} else {
		console.log('bbu_never');
	}
}

function bbu_buttons_wptc(backup_running){
	var extra_classs = '';
	if(backup_running){
		var extra_classs = 'disabled button-disabled-bbu-wptc';
	}
	var current_path = window.location.href;
	if (current_path.toLowerCase().indexOf('update-core') !== -1) {
		jQuery('.upgrade-plugins-bbu-wptc , .upgrade-themes-bbu-wptc , .upgrade-translations-bbu-wptc, .upgrade-core-bbu-wptc, .plugin-update-from-iframe-bbu-wptc').remove();
		var update_plugins = '&nbsp;<input class="upgrade-plugins-bbu-wptc button '+extra_classs+'" type="submit" value="Backup Before Update">';
		var update_themes = '&nbsp;<input class="upgrade-themes-bbu-wptc button  '+extra_classs+'" type="submit" value="Backup Before Update">';
		var update_translations = '&nbsp;<input class="upgrade-translations-bbu-wptc button  '+extra_classs+'" type="submit" value="Backup Before Update">';
		var update_core = '&nbsp;<input type="submit" class="upgrade-core-bbu-wptc button button regular  '+extra_classs+'" value="Backup Before Update">';
		var iframe_update = '<a class="plugin-update-from-iframe-bbu-wptc button button-primary right  '+extra_classs+'" style=" margin-right: 10px;">Backup before update</a>';
		jQuery('form[name=upgrade-plugins]').find('input[name=upgrade]').after(update_plugins);
		jQuery('form[name=upgrade-themes]').find('input[name=upgrade]').after(update_themes);
		jQuery('form[name=upgrade]').find('input[name=upgrade]').after(update_core);
		jQuery('form[name=upgrade-translations]').find('input[name=upgrade]').after(update_translations);
		setTimeout(function(){
			jQuery("#TB_iframeContent").contents().find(".plugin-update-from-iframe-bbu-wptc").remove();
			jQuery("#TB_iframeContent").contents().find("#plugin_update_from_iframe").after(iframe_update);
			add_tool_tip_bbu_wptc(true);
		}, 5000);
	} else if(current_path.toLowerCase().indexOf('plugins.php') !== -1){
		jQuery('.wptc-span-spacing-bbu , .update-link-plugins-bbu-wptc , .button-action-plugins-bbu-wptc').remove();
		var in_app_update = '<span class="wptc-span-spacing-bbu">&nbsp;or</span> <a href="#" class="update-link-plugins-bbu-wptc '+extra_classs+'">Backup before update</a>';
		var selected_update = '<span class="wptc-span-spacing-bbu">&nbsp;</span><input type="submit" class="button-action-plugins-bbu-wptc button  '+extra_classs+'" value="Backup before update">';
		var iframe_update = '<a class="plugin-update-from-iframe-bbu-wptc button button-primary right  '+extra_classs+'" style=" margin-right: 10px;">Backup before update</a>';
		jQuery('form[id=bulk-action-form]').find('.update-link').after(in_app_update);
		jQuery('form[id=bulk-action-form]').find('.button.action').after(selected_update);
		setTimeout(function(){
			jQuery("#TB_iframeContent").contents().find(".plugin-update-from-iframe-bbu-wptc").remove();
			jQuery("#TB_iframeContent").contents().find("#plugin_update_from_iframe").after(iframe_update);
			add_tool_tip_bbu_wptc(true);
		}, 5000);
	} else if(current_path.toLowerCase().indexOf('plugin-install.php') !== -1){
		jQuery('.update-now-plugins-bbu-wptc, .plugin-update-from-iframe-bbu-wptc').remove();
		var in_app_update = '<li><a class="button update-now-plugins-bbu-wptc '+extra_classs+'" href="#">Backup before update</a></li>';
		var iframe_update = '<a class="plugin-update-from-iframe-bbu-wptc button button-primary right  '+extra_classs+'" style=" margin-right: 10px;">Backup before update</a>';
		setTimeout(function(){
			jQuery("#TB_iframeContent").contents().find(".plugin-update-from-iframe-bbu-wptc").remove();
			jQuery("#TB_iframeContent").contents().find("#plugin_update_from_iframe").after(iframe_update);
			add_tool_tip_bbu_wptc(true);
		}, 5000);
		jQuery('.plugin-action-buttons .update-now.button').parents('.plugin-action-buttons').append(in_app_update);
	} else if(current_path.toLowerCase().indexOf('themes.php') !== -1){
		jQuery('.button-link-themes-bbu-wptc, .button-action-plugins-bbu-wptc , #update-theme-bbu-wptc, .wptc-span-spacing-bbu, .button-link-themes-bbu-wptc').remove();
		var in_app_update = '<span class="wptc-span-spacing-bbu">&nbsp;or</span><button class="button-link-themes-bbu-wptc button-link '+extra_classs+'" type="button">Backup before update</button>';
		var selected_update = '<span class="wptc-span-spacing-bbu">&nbsp;</span><input type="submit" class="button-action-plugins-bbu-wptc button  '+extra_classs+'" value="Backup before update">';
		var popup_update = '<span class="wptc-span-spacing-bbu">&nbsp;or</span> <a href="#" id="update-theme-bbu-wptc" class=" '+extra_classs+'">Backup before update</a>';
		jQuery('.button-link[type=button]').not('.wp-auth-check-close, .button-link-themes-staging-wptc').after(in_app_update);
		jQuery('form[id=bulk-action-form]').find('.button.action').after(selected_update);
		jQuery('#update-theme').after(popup_update);
	}
	setTimeout(function (){
		jQuery('.theme').on('click', '.button-link-themes-bbu-wptc , #update-theme', function(e) {
			handle_theme_button_link_request_wptc(this, e, true);
		});
	}, 1000);

	setTimeout(function (){
		jQuery('#update-theme-bbu-wptc').on('click', function(e) {
			handle_theme_link_request_wptc(this, e, true);
		});
	}, 500);
	if(backup_running){
		add_tool_tip_bbu_wptc();
	}
}

function add_tool_tip_bbu_wptc(iframe){
	var class_bbu = ".button-disabled-bbu-wptc";
	// if(iframe){
	// 	class_bbu = jQuery("#TB_iframeContent").contents().find(".button-disabled-bbu-wptc");
	// }
	jQuery(class_bbu).each(function(tagElement , key) {
		jQuery(key).opentip('Backup in progress. Please wait until it finishes', { style: "dark" });
	});
}

function bbu_message_update_progress_bar(data){
	if (data.backup_before_update_progress) {
		var update_message = ''
		if (data.backup_before_update_progress == 'core') {
			update_message = 'wordpress';
		} else {
			update_message = data.backup_before_update_progress;
		}
		// jQuery('.bp_progress_bar').text('Updating '+ update_message +'...');
		backup_before_update = 'yes';
	} else if (data.meta_data_backup_process) {
		// jQuery('.bp_progress_bar').text('Backing up meta data...');
	}
}

function clear_bbu_notes() {
	jQuery.post(ajaxurl, {
		action: 'clear_bbu_notes_wptc',
	}, function(data) {
		//console.log(data);
	});
}
