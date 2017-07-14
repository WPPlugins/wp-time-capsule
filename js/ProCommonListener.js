jQuery(document).ready(function($) {
	jQuery('.thickbox.open-plugin-details-modal').on("click", function(e) {
			get_current_backup_status_wptc(); //to add extra buttons for bbu
	});

	//theme update
	setTimeout(function (){
		jQuery('.theme').on('click', '.button-link , #update-theme', function(e) {
			if (is_current_update_action_set()) {
				clear_current_update_action();
			} else {
				if (this.className.indexOf('button-link-themes-staging-wptc') !== -1) {
					handle_theme_button_link_request_wptc(this, e, false, true);
				} else {
					handle_theme_button_link_request_wptc(this, e, false);
				}
			}
		});
	}, 1000);

});
function handle_plugin_upgrade_request_wptc(obj, e, request_from_direct_link, stage_n_update){
	if (jQuery(obj).hasClass('disabled')) {
		prevent_action_propagation(e);
		return false;
	}
	if (request_from_direct_link === undefined) {
		request_from_direct_link = false;
		if(obj.className && obj.className.indexOf('upgrade-plugins-bbu-wptc') !== -1){
			request_from_direct_link = true;
		}
	}

	if (is_current_update_action_set() && request_from_direct_link === false && stage_n_update !== true) {
		clear_current_update_action();
	} else {
		prevent_action_propagation(e);
		var update_items = [];
		jQuery('#update-plugins-table').find('input[name="checked[]"]').each(function(key, index){
			if(jQuery(index).is(':checked')){
				update_items.push(jQuery(index).val());
			}
		});
		if (update_items.length > 0) {
			if (stage_n_update === true) {
				save_stage_n_update_wptc(update_items, 'plugin');
				select_copy_staging_type();
			} else {
				check_to_show_dialog(jQuery(obj), update_items, 'plugin', request_from_direct_link);
			}
		}
	}
}

function handle_plugin_link_request_wptc(obj, e, request_from_direct_link, stage_n_update){
	if (jQuery(obj).hasClass('disabled')) {
		prevent_action_propagation(e);
		return false;
	}
	if (request_from_direct_link === undefined) {
		request_from_direct_link = false;
		if(obj.className && obj.className.indexOf('update-link-plugins-bbu-wptc') !== -1 || obj.className.indexOf('update-now-plugins-bbu-wptc') !== -1){
			request_from_direct_link = true;
		}
	}
	if (is_current_update_action_set() && request_from_direct_link === false && stage_n_update !== true) {
		clear_current_update_action();
	} else {
		prevent_action_propagation(e);
		var update_items = [];
		if (jQuery(obj).parents('tr').attr('data-plugin') != undefined && jQuery(obj).parents('tr').attr('data-plugin')) {
			update_items.push(jQuery(obj).parents('tr').attr('data-plugin'));
		} else if(jQuery(obj).attr('data-plugin') != undefined && jQuery(obj).attr('data-plugin')) {
			update_items.push(jQuery(obj).attr('data-plugin'));
		} else if(jQuery(obj).parents('tr').prev('tr').find("input").val() != undefined){
			update_items.push(jQuery(obj).parents('tr').prev('tr').find("input").val()); // wp 4.0 <=
		} else {
			var plugin_div = jQuery(obj).parents('.plugin-action-buttons').find('li')[0];
			if (plugin_div) {
				update_items.push(jQuery(plugin_div).find('a').attr('data-plugin'));
			}
		}
		if (update_items.length > 0) {
			if (stage_n_update === true) {
				save_stage_n_update_wptc(update_items, 'plugin');
				select_copy_staging_type();
			} else {
				check_to_show_dialog(jQuery(obj), update_items, 'plugin' , request_from_direct_link);
			}
		}
	}
}


function handle_plugin_button_action_request_wptc(obj, e , request_from_direct_link, stage_n_update){
	if (jQuery(obj).hasClass('disabled')) {
		prevent_action_propagation(e);
		return false;
	}
	prevent_action_propagation(e);
	var update_items = [];
	jQuery('table.wp-list-table.plugins tr').each( function(key, index){
		if(jQuery(index).find('input').attr('name') === 'checked[]'){
			if(jQuery(index).find('input').is(':checked')){
				update_items.push(jQuery(index).find('input').val());
			}
		}
	});
	if (update_items.length > 0) {
		if (stage_n_update === true) {
			save_stage_n_update_wptc(update_items, 'plugin');
			select_copy_staging_type();
		} else {
			check_to_show_dialog(jQuery(obj), update_items, 'plugin', request_from_direct_link);
		}
	}
}

function handle_themes_upgrade_request_wptc(obj, e, request_from_direct_link, stage_n_update){
	if (jQuery(obj).hasClass('disabled')) {
		prevent_action_propagation(e);
		return false;
	}

	if (request_from_direct_link === undefined) {
		request_from_direct_link = false;
		if(obj.className && obj.className.indexOf('upgrade-themes-bbu-wptc') !== -1){
			request_from_direct_link = true;
		}
	}

	if (is_current_update_action_set() && request_from_direct_link === false && stage_n_update !== true) {
		clear_current_update_action();
	} else {
		prevent_action_propagation(e);
		var update_items = [];
		jQuery('#update-themes-table').find('input[name="checked[]"]').each(function(key, index){
			if(jQuery(index).is(':checked')){
				update_items.push(jQuery(index).val());
			}
		});
		if (update_items.length > 0) {
			if (stage_n_update === true) {
				save_stage_n_update_wptc(update_items, 'theme');
				select_copy_staging_type();
			} else {
				check_to_show_dialog(jQuery(obj), update_items, 'theme' , request_from_direct_link);
			}
		}
	}
}

function handle_core_upgrade_request_wptc(obj, e , request_from_direct_link, stage_n_update){
	if (jQuery(obj).hasClass('disabled')) {
		prevent_action_propagation(e);
		return false;
	}
	if (window.location.href.indexOf('update-core.php') === -1) {
		return false;
	}

	if (is_current_update_action_set() && request_from_direct_link === false && stage_n_update !== true) {
			clear_current_update_action();
	} else {
		prevent_action_propagation(e);
		var update_items = [];
		update_items.push(jQuery(obj).parents('p').find('input[name=version]').val());
		if (update_items.length > 0) {
			if (stage_n_update === true) {
				save_stage_n_update_wptc(update_items, 'core');
				select_copy_staging_type();
			} else {
				check_to_show_dialog(jQuery(obj), update_items, 'core' , request_from_direct_link);
			}
		}
	}

}

function handle_theme_button_link_request_wptc(obj, e , request_from_direct_link, stage_n_update){
	if (jQuery(obj).hasClass('disabled')) {
		prevent_action_propagation(e);
		return false;
	}
	prevent_action_propagation(e);
	var update_items = [];
	if (window.location.href.match(/theme=([^&]+)/) && window.location.href.match(/theme=([^&]+)/)[1]) {
		update_items.push(window.location.href.match(/theme=([^&]+)/)[1]);
	} else if(jQuery(obj).parents('.theme.focus').attr('data-slug') != undefined && jQuery(obj).parents('.theme.focus').attr('data-slug')){
		update_items.push(jQuery(obj).parents('.theme.focus').attr('data-slug'));
	} else if(jQuery(obj).parents('.theme').attr('data-slug') != undefined && jQuery(obj).parents('.theme').attr('data-slug')){
		update_items.push(jQuery(obj).parents('.theme').attr('data-slug'));
	}
	if (update_items.length > 0) {
		new_theme_update_listener = 1;
		if (stage_n_update === true) {
			save_stage_n_update_wptc(update_items, 'theme');
			select_copy_staging_type();
		} else {
			check_to_show_dialog(jQuery(obj), update_items, 'theme', request_from_direct_link);
		}
	}
}

function handle_theme_link_request_wptc(obj, e, request_from_direct_link, stage_n_update){
	if (jQuery(obj).hasClass('disabled')) {
		prevent_action_propagation(e);
		return false;
	}
	prevent_action_propagation(e);
	var update_items = [];
	update_items.push(jQuery(obj).siblings('#update-theme').attr('data-slug'));
	if (update_items.length > 0) {
		new_theme_update_listener = 1;
		if (stage_n_update === true) {
			save_stage_n_update_wptc(update_items, 'theme');
			select_copy_staging_type();
		} else {
			check_to_show_dialog(jQuery(obj), update_items, 'theme', request_from_direct_link);
		}
	}
}

function handle_iframe_requests_wptc(obj, e, request_from_direct_link, stage_n_update){
	if (jQuery(obj).hasClass('disabled')) {
		prevent_action_propagation(e);
		return false;
	}
	if (is_current_update_action_set() && obj.className.indexOf('plugin-update-from-iframe-bbu-wptc') === -1) {
		clear_current_update_action();
		if (window.parent.location.href.indexOf('plugins.php') !== -1) {
			return false;
		}
		var link = jQuery(obj).attr('href');
		if (link) {
			window.parent.location.assign(link);
		}
	} else {
		if(jQuery(obj).attr('href') && jQuery(obj).attr('href').toLowerCase().indexOf('action=install') !== -1){
			return false;
		}
		prevent_action_propagation(e);
		var update_items = [];
		if (jQuery(obj).parents('tr').attr('data-plugin') != undefined && jQuery(obj).parents('tr').attr('data-plugin')) {
			update_items.push(jQuery(obj).parents('tr').attr('data-plugin'));
		} else if(jQuery(obj).attr('data-plugin') != undefined && jQuery(obj).attr('data-plugin')) {
			update_items.push(jQuery(obj).attr('data-plugin'));
		} else if(jQuery(obj).attr('href') != undefined && jQuery(obj).attr('href').match(/plugin=([^&]+)/) != undefined && jQuery(obj).attr('href').match(/plugin=([^&]+)/)[1] != undefined) {
			update_items.push(decodeURIComponent(jQuery(obj).attr('href').match(/plugin=([^&]+)/)[1]));
		} else if(jQuery(obj).siblings('#plugin_update_from_iframe').length){
			update_items.push(jQuery(obj).siblings('#plugin_update_from_iframe').attr('data-plugin'));
		}
		if (update_items.length > 0) {
			if (stage_n_update === true) {
				save_stage_n_update_wptc(update_items, 'plugin');
				select_copy_staging_type();
			} else {
				check_to_show_dialog(jQuery(obj), update_items, 'plugin', request_from_direct_link);
			}
		}
	}
}

function handle_translation_upgrade_request_wptc(obj, e , request_from_direct_link, stage_n_update){
	if (jQuery(obj).hasClass('disabled')) {
		prevent_action_propagation(e);
		return false;
	}
	if (window.location.href.indexOf('update-core.php') === -1) {
		return false;
	}

	if (jQuery(obj).parents('form').attr('action').toLowerCase().indexOf('action=do-translation-upgrade') === -1) {
		return false;
	}

	if (is_current_update_action_set()) {
			clear_current_update_action();
	} else {
		prevent_action_propagation(e);
		var update_items = [];
		update_items.push('translation');
		if (update_items.length > 0) {
			if (stage_n_update === true) {
				save_stage_n_update_wptc(update_items, 'translation');
				select_copy_staging_type();
			} else {
				check_to_show_dialog(jQuery(obj), update_items, 'translation', request_from_direct_link);
			}
		}
	}
}

function clear_current_update_action(){
	delete current_update_action;
}


function is_current_update_action_set(){
	if ((typeof current_update_action != "undefined" && current_update_action == "no" || (typeof is_whitelabling_enabled_wptc != 'undefined' && is_whitelabling_enabled_wptc === true))) {
		return true;
	} else {
		return false;
	}
}