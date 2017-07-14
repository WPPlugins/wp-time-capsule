<?php

global $sentry_client;
$config = WPTC_Factory::get('config');
$sentry_client->user_context(
	array(
		'app_id' => $config->get_option('appID'),
		'email' => $config->get_option('main_account_email'),
	)
);