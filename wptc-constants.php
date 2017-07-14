<?php

// @include_once 'wptc-env-parameters.php';

define('WPTC_WP_CONTENT_DIR', wp_normalize_path( WP_CONTENT_DIR ));
define('WPTC_ABSPATH', wp_normalize_path( ABSPATH ));
define('WPTC_TEMP_COOKIE_FILE', wp_normalize_path( WP_CONTENT_DIR . '/backups/tempCookie.txt'));
define('WPTC_VERSION', '1.10.2');

define('WPTC_DATABASE_VERSION', '13.0');


if (!defined('WPTC_DARK_TEST')) define('WPTC_DARK_TEST', false);
if (!defined('WPTC_ENV')) define('WPTC_ENV', 'production');

if (!defined('WPTC_BRIDGE')) {
	define('WPTC_EXTENSIONS_DIR', wp_normalize_path(plugin_dir_path(__FILE__) . 'Classes/Extension/'));
	define('WPTC_CLASSES_DIR', wp_normalize_path(plugin_dir_path(__FILE__) . 'Classes/'));
	define('WPTC_PRO_DIR', wp_normalize_path(plugin_dir_path(__FILE__) . 'Pro/'));
	define('WPTC_PLUGIN_DIR', wp_normalize_path(plugin_dir_path( __FILE__ )));
	define('WPTC_DROPBOX_WP_REDIRECT_URL', urlencode(base64_encode(network_admin_url() . 'admin.php?page=wp-time-capsule&cloud_auth_action=dropbox&env='.WPTC_ENV))); //state wp redirect url for dropbox
} else {
	define('WPTC_EXTENSIONS_DIR', wp_normalize_path(BRIDGE_NAME_WPTC . '/Classes/Extension/'));
	define('WPTC_PLUGIN_DIR', '');
}

define('WPTC_CHUNKED_UPLOAD_THREASHOLD', 5242880); //5 MB
define('WPTC_MINUMUM_PHP_VERSION', '5.2.16');
define('WPTC_NO_ACTIVITY_WAIT_TIME', 60); //5 mins to allow for socket timeouts and long uploads
define('WPTC_PLUGIN_PREFIX', 'wptc');
define('WPTC_TC_PLUGIN_NAME', 'wp-time-capsule');
define('WPTC_PRO_PACKAGE', 1);
define('WPTC_DARK_TEST_SIMPLE', false);
define('WPTC_TIMEOUT', 23);
define('WPTC_HASH_FILE_LIMIT', 1024 * 1024 * 15); //15MB
define('HASH_CHUNK_LIMIT', 1024 * 128); // 128 KB
define('WPTC_CLOUD_DIR_NAME', 'wp-time-capsule');
define('WPTC_RESTORE_FILES_NOT_WRITABLE_COUNT', 15);
define('WPTC_DEFAULT_CRON_FREQUENCY', 'daily'); //subject to change
define('WPTC_DEFAULT_SCHEDULE_TIME_STR', '12:00 am');
define('WPTC_NOTIFY_ERRORS_THRESHOLD', 10);
define('WPTC_LOCAL_AUTO_BACKUP', true);
define('WPTC_AUTO_BACKUP', false);
define('WPTC_WPTC_DARK_TEST_PRINT_ALL', true);
define('WPTC_DONT_BACKUP_META', true); // remove to take meta on every backup
define('WPTC_FALLBACK_REVISION_LIMIT_DAYS', 15);
define('WPTC_GDRIVE_TOKEN_ON_INIT_LIMIT', 5); //total connected sites limit for showing google drive



if (WPTC_ENV === 'production') {
	define('WPTC_CRSERVER_URL', 'https://cron.wptimecapsule.com');
	define('WPTC_USER_SERVICE_URL', 'https://service.wptimecapsule.com/service.php');
	define('WPTC_APSERVER_URL', 'https://service.wptimecapsule.com');
	define('WPTC_APSERVER_URL_FORGET', 'https://service.wptimecapsule.com/?show_forgot_pwd=true');
	define('WPTC_APSERVER_URL_SIGNUP', 'https://service.wptimecapsule.com/signup');
	define('WPTC_G_DRIVE_AUTHORIZE_URL', 'https://wptimecapsule.com/gdrive_auth/production/index.php');
	define('WPTC_DROPBOX_REDIRECT_URL', 'https://wptimecapsule.com/dropbox_auth/index.php');
	define('WPTC_DROPBOX_CLIENT_ID', base64_decode('aHA3ZzJkcTl0YzgxZHdl'));
	define('WPTC_DROPBOX_CLIENT_SECRET', base64_decode('MnlqNTVwa2lna2g4NTg2'));
	define('WPTC_CURL_TIMEOUT', 20);
	define('WPTC_SENTRY_REFERENCE', 'http://c2b71bf9cff94377980d6d11f0e6ab6b:af8ed08f439f4bb7bb8c23461d8abd6b@bugreports.rxforge.in/3');
	error_reporting(0);
} else if (WPTC_ENV === 'staging') {
	define('WPTC_CRSERVER_URL', 'https://wptc-dev-node.rxforge.in');
	define('WPTC_USER_SERVICE_URL', 'https://wptc-dev-service.rxforge.in/service/service.php');
	define('WPTC_APSERVER_URL', 'https://wptc-dev-service.rxforge.in/service');
	define('WPTC_APSERVER_URL_FORGET', 'https://service.wptimecapsule.com/?show_forgot_pwd=true');
	define('WPTC_APSERVER_URL_SIGNUP', 'https://service.wptimecapsule.com/signup');
	define('WPTC_G_DRIVE_AUTHORIZE_URL', 'https://wptimecapsule.com/gdrive_auth/staging/index.php');
	define('WPTC_DROPBOX_REDIRECT_URL', 'https://wptimecapsule.com/dropbox_auth/index.php');
	define('WPTC_DROPBOX_CLIENT_ID', base64_decode('dHU4djcwM3A3cWk4cDky'));
	define('WPTC_DROPBOX_CLIENT_SECRET', base64_decode('dHZ2MXM4dmxpcTVwNHU3'));
	define('WPTC_CURL_TIMEOUT', 20);
	define('WPTC_SENTRY_REFERENCE', 'http://54bbcd50f5fd40b58f5e10e1aa288f99:7c2c1f318b104f89b2eb74cfbbe0796a@bugreports.rxforge.in/4');
} else if (WPTC_ENV === 'pre-production') {
	define('WPTC_CRSERVER_URL', 'https://wptc-pre-prod-node.rxforge.in');
	define('WPTC_USER_SERVICE_URL', 'https://wptc-dev-service.rxforge.in/wptc-beta-signup/service.php');
	define('WPTC_APSERVER_URL', 'https://wptc-dev-service.rxforge.in/service');
	define('WPTC_APSERVER_URL_FORGET', 'https://service.wptimecapsule.com/?show_forgot_pwd=true');
	define('WPTC_APSERVER_URL_SIGNUP', 'https://service.wptimecapsule.com/signup');
	define('WPTC_G_DRIVE_AUTHORIZE_URL', 'https://wptimecapsule.com/gdrive_auth/staging/index.php');
	define('WPTC_DROPBOX_REDIRECT_URL', 'https://wptimecapsule.com/dropbox_auth/index.php');
	define('WPTC_DROPBOX_CLIENT_ID', base64_decode('dHU4djcwM3A3cWk4cDky'));
	define('WPTC_DROPBOX_CLIENT_SECRET', base64_decode('dHZ2MXM4dmxpcTVwNHU3'));
	define('WPTC_CURL_TIMEOUT', 20);
	define('WPTC_SENTRY_REFERENCE', 'http://54bbcd50f5fd40b58f5e10e1aa288f99:7c2c1f318b104f89b2eb74cfbbe0796a@bugreports.rxforge.in/4');
} else {
	define('WPTC_CRSERVER_URL', 'http://localhost:9999');
	define('WPTC_USER_SERVICE_URL', 'http://dark.dev.com/wptc-service/service.php');
	define('WPTC_APSERVER_URL', 'http://dark.dev.com/wptc-service');
	define('WPTC_APSERVER_URL_FORGET', 'https://service.wptimecapsule.com/?show_forgot_pwd=true');
	define('WPTC_APSERVER_URL_SIGNUP', 'https://service.wptimecapsule.com/signup');
	define('WPTC_G_DRIVE_AUTHORIZE_URL', 'https://wptimecapsule.com/gdrive_auth/development/index.php');
	define('WPTC_DROPBOX_REDIRECT_URL', 'https://wptimecapsule.com/dropbox_auth/index.php');
	define('WPTC_DROPBOX_CLIENT_ID', base64_decode('bTMwY2hlaTh5YXRoYTRr'));
	define('WPTC_DROPBOX_CLIENT_SECRET', base64_decode('ZzA5Y2NoNHc5c3Fwazli'));
	define('WPTC_CURL_TIMEOUT', 120);
	define('WPTC_SENTRY_REFERENCE', 'http://de2dbdbd588a4b13a128e1c4dd2cd123:83e348f73d034f60a44860501f3afded@bugreports.rxforge.in/5');
}