<?php
/*
	
	uninstall.php
	
	- fires when plugin is uninstalled via the Plugins screen
	
*/



// exit if uninstall constant is not defined
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	
	exit;
	
}

// delete the plugin options
delete_option( 'token_expiration' );
delete_option( 'redirect_url' );
delete_option( 'phone_country_code' );
delete_option( 'sms_text' );
delete_option( 'provider' );
delete_option( 'gateway_url' );
delete_option( 'gateway_api_method' );
delete_option( 'gateway_parameters_json' );
delete_option( 'use_plugin_css' );
delete_option( 'log_events' );



$meta_type  = 'user';
$user_id    = 0; // This will be ignored, since we are deleting for all users.
$meta_value = ''; // Also ignored. The meta will be deleted regardless of value.
$delete_all = true;
$meta_key   = ['phone_number','tsa_token_access', 'tsa_token_create_date'];

foreach($meta_keys as $meta_key) {
	delete_metadata( $meta_type, $user_id, $meta_key, $meta_value, $delete_all );
}