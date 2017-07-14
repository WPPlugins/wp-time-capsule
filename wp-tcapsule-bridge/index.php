<?php

require_once "bridge_functions.php";

if (isset($_GET['continue']) && $_GET['continue'] == true ) {
	include_config();
	include_files();
	initiate_database();
	load_header();
	include_js_vars();
	if (isset($_GET['position']) && $_GET['position'] == 'beginning' ){
		start_from_beginning();
	} else {
		continue_restore();
	}
	load_footer();
	exit;
}

if (isset($_POST['data']['cur_res_b_id'])) {
	include_config();
	include_files();
	initiate_database();
	initiate_filesystem();
	start_restore_tc_callback_bridge();
	exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'check_db_creds') {
	check_db_creds();
	exit;
}

if (empty($_GET) || !is_array($_GET)) {
	header("Location: index.php?step=connect_db");
}
define_constants();
wptc_choose_functions();
load_header();
load_footer();