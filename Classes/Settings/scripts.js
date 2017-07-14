jQuery(document).ready(function($) {
	var anchor_wptc = $( 'input[name="anchor_wptc"]' );
	var tab_wrapper_a_wptc = $( '.wptc-nav-tab-wrapper.nav-tab-wrapper>a' );
	var actual_anchor_wptc = window.location.hash;
	if ( actual_anchor_wptc !== '' ) {
		actual_anchor_wptc = '#' + actual_anchor_wptc.replace( '#', '' );
	}

	if ( actual_anchor_wptc !== '' ) {
		anchor_wptc.val( actual_anchor_wptc );
	}

	$( '.table' ).addClass( 'ui-tabs-hide' );
	$( anchor_wptc.val() ).removeClass( 'ui-tabs-hide' );

	if ( anchor_wptc.val() == '#wp-time-capsule-tab-net' ||  anchor_wptc.val() == '#wp-time-capsule-tab-information' ) {
		$('#wptc_save_changes').hide();
		$('#default_settings').hide();
	}

	tab_wrapper_a_wptc.removeClass( 'nav-tab-active' );
	tab_wrapper_a_wptc.each( function () {
		if ( $(this).attr( 'href' ) == anchor_wptc.val() ) {
			$(this).addClass( 'nav-tab-active' );
		}
	});

	tab_wrapper_a_wptc.on( 'click', function () {
		var clickedid = $(this).attr('href');
		tab_wrapper_a_wptc.removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		$('.table').addClass('ui-tabs-hide');
		$(clickedid).removeClass('ui-tabs-hide');
		$('#message').hide();
		anchor_wptc.val(clickedid);
		if ( clickedid == '#wp-time-capsule-tab-net' ||  clickedid == '#wp-time-capsule-tab-information') {
			$('#wptc_save_changes').hide();
			$('#default_settings').hide();
		} else {
			$('#wptc_save_changes').show();
			$('#default_settings').show();
		}
		window.location.hash = clickedid;
		window.scrollTo(0, 0);
		return false;
	});

});
