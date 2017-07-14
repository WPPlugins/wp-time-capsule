// jQuery File Tree Plugin
//
// Version 1.01
//
// Cory S.N. LaViska
// A Beautiful Site (http://abeautifulsite.net/)
// 24 March 2008
//
// Visit http://abeautifulsite.net/notebook.php?article=58 for more information
//
// Usage: jQuery('.fileTreeDemo').fileTree( options, callback )
//
// Options:  root           - root folder to display; default = /
//           script         - location of the serverside AJAX file to use; default = jqueryFileTree.php
//           folderEvent    - event to trigger expand/collapse; default = click
//           expandSpeed    - default = 500 (ms); use -1 for no animation
//           collapseSpeed  - default = 500 (ms); use -1 for no animation
//           expandEasing   - easing function to use on expand (optional)
//           collapseEasing - easing function to use on collapse (optional)
//           multiFolder    - whether or not to limit the browser to one subfolder at a time
//           loadMessage    - Message to display while initial tree loads (can be HTML)
//
// History:
//
// 1.01 - updated to work with foreign characters in directory/file names (12 April 2008)
// 1.00 - released (24 March 2008)
//
// TERMS OF USE
//
// This plugin is dual-licensed under the GNU General Public License and the MIT License and
// is copyright 2008 A Beautiful Site, LLC.
//
if(jQuery) (function(jQuery){

	jQuery.extend(jQuery.fn, {
		fileTree: function(o, h) {
			// Defaults
			if( !o ) var o = {};
			if( o.root == undefined ) o.root = '/';
			if( o.script == undefined ) o.script = '';
			if( o.folderEvent == undefined ) o.folderEvent = 'click';
			if( o.expandSpeed == undefined ) o.expandSpeed= 500;
			if( o.collapseSpeed == undefined ) o.collapseSpeed= 500;
			if( o.expandEasing == undefined ) o.expandEasing = null;
			if( o.collapseEasing == undefined ) o.collapseEasing = null;
			if( o.multiFolder == undefined ) o.multiFolder = true;
			if( o.loadMessage == undefined ) o.loadMessage = 'Loading...';

			jQuery(this).each( function() {
				function showTree(c, t) {
					jQuery(c).addClass('wait');
					jQuery.post(
						o.script, {
							dir: t,
							action: o.action,
							ftp_hostname:o.ftp_details.hostName,
							port : o.ftp_details.hostPort,
							ftp_username : o.ftp_details.hostUserName,
							ftp_password : o.ftp_details.hostPassword,
							use_sftp : o.ftp_details.useSftp,
						}, function(data) {
							console.log(data);
							jQuery(".jqueryFileTree.start").remove();
							jQuery(c).find('.start').html('');
							if(typeof data.success != 'undefined'){
								jQuery(c).removeClass('wait').append(data.success);
								jQuery('.ftp_creds_wrong_wptc').hide();
							} else if(typeof data.error != 'undefined'){
								jQuery('#ftp_creds_wrong_wptc').html(data.error).show();
								jQuery('.fileTreeClass').hide();
							} else {
								jQuery('#ftp_creds_wrong_wptc').html("Error: Invalid credentials").show();
								jQuery('.fileTreeClass').hide();
							}

						if( o.root == t ) jQuery(c).find('UL:hidden').show(); else jQuery(c).find('UL:hidden').slideDown({ duration: o.expandSpeed, easing: o.expandEasing });
						bindTree(c);
					});
				}

				function bindTree(t) {
					jQuery(t).find('LI A').bind(o.folderEvent, function() {
						if( jQuery(this).parent().hasClass('directory') ) {
							if( jQuery(this).parent().hasClass('collapsed') ) {
								// Expand
								if( !o.multiFolder ) {
									jQuery(this).parent().parent().find('UL').slideUp({ duration: o.collapseSpeed, easing: o.collapseEasing });
									jQuery(this).parent().parent().find('LI.directory').removeClass('expanded').addClass('collapsed');
								}
								jQuery(this).parent().find('UL').remove(); // cleanup
								showTree( jQuery(this).parent(), escape(jQuery(this).attr('rel').match( /.*\// )) );
								jQuery(this).parent().removeClass('collapsed').addClass('expanded');
							} else {
								// Collapse
								jQuery(this).parent().find('UL').slideUp({ duration: o.collapseSpeed, easing: o.collapseEasing });
								jQuery(this).parent().removeClass('expanded').addClass('collapsed');

							}
						} else {

							h(jQuery(this).attr('fileName'));
						}
						return false;
					});
					// Prevent A from triggering the # on non-click events
					if( o.folderEvent.toLowerCase != 'click' ) jQuery(t).find('LI A').bind('click', function() { return false; });
				}
				// Loading message
				jQuery(this).html('<ul class="jqueryFileTree start"><li class="wait">' + o.loadMessage + '<li></ul>');
				// Get the initial file list
				showTree( jQuery(this), escape(o.root) );
			});
		}
	});

})(jQuery);