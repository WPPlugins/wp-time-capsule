<?php

function wptc_choose_functions() {
	if ($_GET['step'] == 'connect_db') {
		check_config_file_present();
	} else if ($_GET['step'] == 'show_points') {
		include_files();
		show_points();
		initiate_database();
		include_js_vars();
	} else if ($_GET['step'] == 'restore') {
		include_files();
		load_restore_process_queue();
	}
}

function check_config_file_present() {
	global $config_check, $temp_table_prefix;
	register_shutdown_function('show_db_creds_page');
	$config_check = 0;
	$temp_table_prefix = '';
	if (file_exists('../wp-config.php')) {
		@include_once '../wp-config.php';
		global $config_check, $temp_table_prefix;
		$config_check = 1;
		$temp_table_prefix = $table_prefix;
	}
}

function show_db_creds_page() {
	load_header();
	load_footer();
	?>
	<div class="pu_title">Database details</div>

	<div id="dashboard_activity" class="postbox" style="width: 740px; margin: 0 auto 20px;">
		<div class="inside">
			<div class="bridge-border-wptc" style="position: relative; left: 49%;"></div>
			<div style="position: relative;">
				<div class="bridge-same-server-block" style="position: absolute;top: -160px;left: 88px;">
					<span class="bridge-on-the-server">Load from WP Config</span>
					<input id="load_from_wp_config" type="submit" value="Load restore points" style="position: absolute;margin: 30px 0px 0px -140px;width: 140px;" class="button-primary">
					<div class="bridge-speed-note">It will fetch the database details from wp-config.php</div>
				</div>
				<div class="bridge-different-server-block" style="position: absolute;top: -160px;right: 60px;">
					<span class="bridge-on-the-server">Custom DB details</span>
					<input id="custom_creds" type="submit" value="DB details" style="position: absolute;margin: 30px 0px 0px -120px;width: 140px;" class="button-primary">
					<div class="bridge-speed-note">Enter the database details manually.</div>
				</div>
			</div>
		</div>
	</div>

	<form class="clearfix" action="index.php?step=show_points"  id = "db_creds_form" method="POST" style="display: none">
		<div class="clearfix">
			<div class="form-group">
				<label for="exampleInputEmail1">DB NAME</label>
				<input type="text" id="db_name" class="form-control" required name="db_name"/>
			</div>
			<div class="form-group">
				<label for="exampleInputEmail1">DB HOST</label>
				<input type="text" id="db_host" class="form-control" required value="localhost" name="db_host"/>
			</div>
			<div class="form-group">
				<label for="exampleInputEmail1">DB PREFIX</label>
				<input type="text" id="db_prefix" class="form-control" required value="wp_" name="db_prefix"/>
			</div>
			<div class="form-group">
				<label for="exampleInputEmail1">DB USERNAME</label>
				<input type="text" id="db_username"  class="form-control"  required name="db_username"/>
			</div>
			<div class="form-group">
				<label for="exampleInputPassword1">DB PASSWORD</label>
				<input type="text" id="db_password"  class="form-control"  name="db_password"/>
			</div>
			<div class="form-group" style="display: none">
				<label for="exampleInputPassword1">DB CHARSET</label>
				<input type="text" id="db_charset"  class="form-control"  name="db_charset"/>
			</div>
			<div class="form-group" style="display: none">
				<label for="exampleInputPassword1">DB COLLATE</label>
				<input type="text" id="db_collate"  class="form-control" name="db_collate"/>
			</div>
			<div class="form-group" style="display: none">
				<label for="exampleInputPassword1">WP CONTENT DIR</label>
				<input type="text" id="wp_content_dir"  class="form-control" name="wp_content_dir"/>
			</div>
		</div>
		<div class="error"></div>
		<div class="clearfix"><a id="check_db_creds" class="btn_wptc" style="width: 175px; text-align: center;">LOAD RESTORE POINTS</a></div>
	</form>
	<?php
}

function show_points() {
	global $wpdb;
	if (empty($_POST)) {
		$db_info = fetch_creds_from_wp_config();
		if (empty($db_info)) {
			die('we cannot fetch data from wp-config.php, Please go back and enter database details manually !.');
		}
		$wpdb = new wpdb($db_info['db_username'], $db_info['db_password'], $db_info['db_name'], $db_info['db_host']);
		$wpdb->base_prefix = $db_info['db_prefix'];
		$response = create_config_file($db_info);
	} else {
		$wpdb = new wpdb($_POST['db_username'], $_POST['db_password'], $_POST['db_name'], $_POST['db_host']);
		$wpdb->base_prefix = $_POST['db_prefix'];
		$response = create_config_file($_POST);
	}
	if($response === false){
		echo "Cannot write in wp-tc-config.php, please set 755 permission for wp-tcapsule-bridge folder";
		die();
	}
	include_once dirname(__FILE__). '/' .'wp-tc-config.php';
	$processed_files = WPTC_Factory::get('processed-files');
	$stored_backups = $processed_files->get_stored_backups();
	$detailed_backups = $processed_files->get_point_details($stored_backups);
	$html = $processed_files->get_bridge_html($detailed_backups);
	echo $html;
}

function fetch_creds_from_wp_config(){
	if (!file_exists('../wp-config.php')) {
		return false;
	}

	$db_info = array();
	$file = fopen("../wp-config.php", "r");

	if (!$file) {
		return false;
	}

	while(!feof($file)) {
		$line = fgets($file);
		if (empty($line) || stripos($line, '//') === 0 || stripos($line, '/*') === 0) {
			continue;
		}
		if (strpos($line, 'DB_NAME') !== false) {
			$line = str_replace(array(' ', '"', '\''), '', $line);
			remove_response_junk_bridge_wptc($line, 'DB_NAME,');
			$db_info['db_name'] = $line;
			continue;
		}

		if (strpos($line, 'DB_USER') !== false) {
			$line = str_replace(array(' ', '"', '\''), '', $line);
			remove_response_junk_bridge_wptc($line, 'DB_USER,');
			$db_info['db_username'] = $line;
			continue;
		}

		if (strpos($line, 'DB_PASSWORD') !== false) {
			$line = str_replace(array(' ', '"', '\''), '', $line);
			remove_response_junk_bridge_wptc($line, 'DB_PASSWORD,');
			$db_info['db_password'] = $line;
			continue;
		}

		if (strpos($line, 'DB_HOST') !== false) {
			$line = str_replace(array(' ', '"', '\''), '', $line);
			remove_response_junk_bridge_wptc($line, 'DB_HOST,');
			$db_info['db_host'] = $line;
			continue;
		}

		if (strpos($line, 'table_prefix') !== false) {
			$line = str_replace(array(' ', '"', '\''), '', $line);
			remove_response_junk_bridge_wptc($line, '$table_prefix=', ';');
			$db_info['db_prefix'] = $line;
			continue;
		}
	}

	fclose($file);
	return $db_info;

}
function remove_response_junk_bridge_wptc(&$response, $start_junk, $end_junk = ')'){
	$headerPos = stripos($response, $start_junk);
	if($headerPos !== false){
		$response = substr($response, $headerPos);
		$response = substr($response, strlen($start_junk), stripos($response, $end_junk)-strlen($start_junk));
	}
}

function define_constants() {
	if (!defined('WP_DEBUG')) {
		define('WP_DEBUG', true);
	}
	if (!defined('WP_DEBUG_DISPLAY')) {
		define('WP_DEBUG_DISPLAY', true);
	}
}

function load_header() {?>
	<!doctype html>
	<html>
	<head>
	<meta charset="UTF-8">
	<title>Restore your website</title>
	<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,600" rel="stylesheet" type="text/css" >
	<link href="https://cdnjs.cloudflare.com/ajax/libs/meyer-reset/2.0/reset.min.css" rel="stylesheet" type="text/css" >
	<link rel="stylesheet" type="text/css" href="bridge_style.css">
	<script type="text/javascript" src="wp-files/jquery.js"></script>
	<script type="text/javascript" src="bridge_init.js"></script>
	<script type="text/javascript" src="wptc-monitor.js"></script>
	</head>
	<body bgcolor="#f1f1f1">
	<?php
}

function include_js_vars() {?>
	<script type="text/javascript" language="javascript">
		//initiating Global Variables here
	var sitenameWPTC =  '';
	var freshBackupWptc = '';
	var startBackupFromSettingsWPTC = '';
	var bp_in_progress = false;
	var wp_base_prefix_wptc = '<?php if (defined('DB_PREFIX_WPTC')) {echo DB_PREFIX_WPTC;} else {echo '';}?>';
	var this_home_url_wptc = '<?php echo get_home_page_url(); ?>';
	var defaultDateWPTC = '<?php echo date('Y-m-d', microtime(true)) ?>' ;
	var wptcOptionsPageURl = '<?php echo get_options_page_url(); ?>';
	var this_plugin_url_wptc = '';
	var wptcMonitorPageURl = '<?php echo get_monitor_page_url(); ?>';
	var wptcPluginURl = '';
	var freshbackupPopUpWPTC = false;
	var on_going_restore_process = false;
	var cuurent_bridge_file_name = 'wp-tcapsule-bridge';
	var ajaxurl = '';
	var seperate_bridge_call = 1;
	</script> <?php
}

function continue_restore(){ ?>
	<script type="text/javascript" language="javascript">
	startBridgeDownload();
	</script> <?php
}

function start_from_beginning(){?>
	<script type="text/javascript" language="javascript">
	startBridgeDownload({ initialize: true });
	</script> <?php
}

function load_footer() {?>
	</body> <?php
}

function include_config() {
	include_once dirname(__FILE__). '/' .'wp-tc-config.php';

}

function initiate_database() {
	//initialize wpdb since we are using it independently
	global $wpdb;
	$wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);

	//setting the prefix from post value;
	$wpdb->prefix = $wpdb->base_prefix = DB_PREFIX_WPTC;
}

function initiate_filesystem() {
	$creds = request_filesystem_credentials("", "", false, false, null);
	if (false === $creds) {
		return false;
	}

	if (!WP_Filesystem($creds)) {
		return false;
	}
}

function include_files() {
	define('WPTC_BRIDGE', true); //used in wptc-constants.php

	require_once dirname(__FILE__). '/' ."common_include_files.php";
	$Common_Include_Files = new Common_Include_Files('bridge_functions');
	$Common_Include_Files->init();
}

function check_db_creds() {
	define_constants();
	require_once dirname(__FILE__). '/' ."wp-modified-functions.php";
	require_once dirname(__FILE__). '/' ."wp-db-custom.php";
	require_once dirname(__FILE__). '/' .'wp-files/class-wp-error.php';
	global $wpdb;
	$wpdb = new wpdb($_POST['data']['db_username'], $_POST['data']['db_password'], $_POST['data']['db_name'], $_POST['data']['db_host']);
}

function load_restore_process_queue() {
	?><div class="pu_title">Restoring your website</div>
<div class="wcard progress_reverse" style="height:60px; padding:0;"><div class="progress_bar" style="width: 0%;"></div>  <div class="progress_cont">Preparing files to restore...</div></div><div style="padding: 10px; text-align: center;">Note: Please do not close this tab until restore completes.</div><?php
}

function create_config_file($params, $is_staging = false) {

	$config = WPTC_Factory::get('config');
	$default_repo = $config->get_option('default_repo');

	if ($is_staging) {
		$this_config_like_file = '../wp-tcapsule-bridge/wp-tc-config.php';
		$default_repo = $params['default_repo'];
	} else {
		$this_config_like_file = 'wp-tc-config.php';
	}

	$db_username = $params['db_username'];
	$db_password = $params['db_password'];
	$db_name = $params['db_name'];
	$db_host = $params['db_host'];
	$db_prefix = $params['db_prefix'];
	$db_charset = $params['db_charset'];
	$db_collate = $params['db_collate'];
	$wp_content_dir = $params['wp_content_dir'];

	if (!isset($db_charset) || empty($db_charset)) {
		$db_charset = 'utf8mb4';
	}
	if (!isset($db_collate) || empty($db_collate)) {
		$db_collate = '';
	}
	if (!isset($wp_content_dir) || empty($wp_content_dir)) {
		$wp_content_dir = dirname(dirname(__FILE__)) . '/'. WPTC_WP_CONTENT_DIR;
	}
	$contents_to_be_written = "
		<?php
		/** The name of the database for WordPress */
		if(!defined('DB_NAME'))
		define('DB_NAME', '" . $db_name . "');

		/** MySQL database username */
		if(!defined('DB_USER'))
		define('DB_USER', '" . $db_username . "');

		/** MySQL database password */
		if(!defined('DB_PASSWORD'))
		define('DB_PASSWORD', '" . $db_password . "');

		/** MySQL hostname */
		if(!defined('DB_HOST'))
		define('DB_HOST', '" . $db_host . "');

		/** Database Charset to use in creating database tables. */
		if(!defined('DB_CHARSET'))
		define('DB_CHARSET', '" . $db_charset . "');

		/** The Database Collate type. Don't change this if in doubt. */
		if(!defined('DB_COLLATE'))
		define('DB_COLLATE', '" . $db_collate . "');

		if(!defined('DB_PREFIX_WPTC'))
		define('DB_PREFIX_WPTC', '" . $db_prefix . "');

		if(!defined('DEFAULT_REPO'))
		define('DEFAULT_REPO', '" . $default_repo . "');

		if(!defined('BRIDGE_NAME_WPTC'))
		define('BRIDGE_NAME_WPTC', 'wp-tcapsule-bridge');

		if (!defined('WP_MAX_MEMORY_LIMIT')) {
			define('WP_MAX_MEMORY_LIMIT', '256M');
		}

		define( 'FS_METHOD', 'direct' );

		if(!defined('WP_DEBUG'))
		define('WP_DEBUG', false);
		if(!defined('WP_DEBUG_DISPLAY'))
		define('WP_DEBUG_DISPLAY', false);

		if ( !defined('MINUTE_IN_SECONDS') )
		define('MINUTE_IN_SECONDS', 60);
		if ( !defined('HOUR_IN_SECONDS') )
		define('HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS);
		if ( !defined('DAY_IN_SECONDS') )
		define('DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS);
		if ( !defined('WEEK_IN_SECONDS') )
		define('WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS);
		if ( !defined('YEAR_IN_SECONDS') )
		define('YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS);



		/** Absolute path to the WordPress directory. */
		if ( !defined('ABSPATH') )
		define('ABSPATH', dirname(dirname(__FILE__)) . '/');

		if ( !defined('WP_CONTENT_DIR') )
		define('WP_CONTENT_DIR', ABSPATH . 'wp-content');

		if ( !defined('WP_LANG_DIR') )
		define('WP_LANG_DIR', ABSPATH . 'wp-content/lang');

				";

	$fh = fopen($this_config_like_file, 'w');
	if (empty($fh)) {
		return false;
	}

	if(!fwrite($fh, $contents_to_be_written)){
		return false;
	}
	fclose($fh);
	return true;
}

function start_restore_tc_callback_bridge($req_data = '') {
	require_once dirname(__FILE__). '/' ."common-functions.php";
	require_once dirname(__FILE__). '/' .'wptc-constants.php';
	// global $wptc_profiling_start;
	// $wptc_profiling_start = microtime(true);
	reset_restore_related_settings_wptc();
	$config = WPTC_Factory::get('config');
	$config->set_option('current_bridge_file_name', 'wp-tcapsule-bridge');

	$data = array();
	if (empty($req_data)) {
		if (isset($_POST['data'])) {
			$data = $_POST['data'];
		}
	} else {
		if (isset($req_data['data'])) {
			$data = $req_data['data'];
		}
	}

	if (empty($data)) {
		echo json_encode(array('error' => 'Post Data is missing.'));
		die();
	}

	try {
		$config = WPTC_Factory::get('config');

		//initializing restore options

		$config->set_option('wptc_profiling_start', microtime(true));
		$config->set_option('restore_action_id', time()); //main ID used througout the restore process
		$config->set_option('in_progress_restore', true);
		$config->set_option('restore_post_data', serialize($data));

		$config->create_dump_dir(array('is_bridge' => true)); //This will initialize wp_filesystem


		// send_restore_initiated_email_wptc();

		echo json_encode(array('restoreInitiatedResult' => array('bridgeFileName' => BRIDGE_NAME_WPTC, 'safeToCallPluginAjax' => true)));
		die();
	} catch (Exception $e) {
		echo json_encode(array('error' => $e->getMessage()));
		die();
	}

}