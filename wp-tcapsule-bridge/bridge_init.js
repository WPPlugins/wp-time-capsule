jQuery(document).ready(function(){
	jQuery('#check_db_creds').on('click', function(){
		jQuery(".error").html('');
		if (jQuery(this).hasClass('disabled')) {
			return;
		}
		jQuery(this).addClass('processing disabled');
		jQuery(this).text('Validating...');
		var db_host = jQuery("#db_host").val();
		var db_name = jQuery("#db_name").val();
		var db_username = jQuery("#db_username").val();
		var db_password = jQuery("#db_password").val();
		var db_charset = jQuery("#db_charset").val();
		var db_collate = jQuery("#db_collate").val();
		var wp_content_dir = jQuery("#wp_content_dir").val();
		bridge_do_call(db_host, db_name, db_username, db_password, db_charset, db_collate, wp_content_dir);
	});
	jQuery('.show_restore_points').on('click', function(e){
		var current_date = jQuery(this).parents('li');
		if(jQuery(this).text() == "HIDE RESTORE POINTS"){
			jQuery(this).text("SHOW RESTORE POINTS");
		} else {
			jQuery(this).text("HIDE RESTORE POINTS");
		}
		jQuery(current_date).find('.rp').slideToggle("fast");
		e.preventDefault();
		e.stopImmediatePropagation();
	});

	jQuery('#load_from_wp_config').on('click', function(e){
		window.location.assign('index.php?step=show_points');
	});

	jQuery('#custom_creds').on('click', function(e){
		if (jQuery(this).hasClass('disabled')) {
			return false;
		}
		jQuery(this).addClass('disabled');
		jQuery('#db_creds_form').show();
		jQuery(this).removeClass('disabled');
		return false;
	});
});

function bridge_do_call(db_host, db_name, db_username, db_password, db_charset, db_collate, wp_content_dir){
	var submit_button = jQuery('#check_db_creds');
	jQuery.post('index.php', {
		action: 'check_db_creds',
		data: {db_host:db_host, db_name:db_name, db_username:db_username, db_password:db_password, db_charse:db_charset, db_collate:db_collate, wp_content_dir:wp_content_dir},
	}, function(data) {
		if(data.length == 0){
			submit_button.text('connected');
			jQuery("#db_creds_form").submit();
		} else{
			submit_button.removeClass('processing disabled');
			submit_button.text('LOAD RESTORE POINT');
			 if (data.indexOf("Access denied") != -1) {
				jQuery(".error").html("Access denied. Please check your credentials and try again.");
			} else if(data.indexOf("host") != -1) {
				jQuery(".error").html("No such host is known. Please check host name and try again.");
			} else {
				jQuery(".error").html(data);
			}
		}
	});
}