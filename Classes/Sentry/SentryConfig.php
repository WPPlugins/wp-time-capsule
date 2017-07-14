<?php
if (!defined('WPTC_BRIDGE')) {
	require_once WPTC_CLASSES_DIR.'Sentry/sentry-php-master/lib/Raven/Autoloader.php';
} else {
	require_once 'sentry-php-master/lib/Raven/Autoloader.php';
}
global $sentry_client;
Raven_Autoloader::register();
$sentry_client = new Raven_Client(WPTC_SENTRY_REFERENCE);
$sentry_client->tags_context(array(
	'PHP_VERSION' => phpversion(),
	'PHP_MAX_EXECUTION_TIME' =>  ini_get('max_execution_time'),
	'PHP_MEMORY_LIMIT' =>  ini_get('memory_limit'),
));
$sentry_client->setEnvironment('production');
if (!defined('WPTC_BRIDGE')) {
	$sentry_client->setAppPath(WPTC_PLUGIN_DIR);
	$sentry_client->setStrictErrorCapturePath(WPTC_PLUGIN_DIR);
}
$sentry_client->setRelease(WPTC_VERSION);
$error_handler = new Raven_ErrorHandler($sentry_client);
$error_handler->registerExceptionHandler();
$error_handler->registerErrorHandler();
$error_handler->registerShutdownFunction();