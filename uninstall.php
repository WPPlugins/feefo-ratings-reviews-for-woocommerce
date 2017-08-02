<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * @link       http://www.feefo.com
 * @since      1.0.2
 *
 * @package    Feefo_Ratings_And_Reviews
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$api_prefix = 'https://ww2.feefo.com/api';

$feefo_wc_wp_stored_options = get_option( 'feefo_wc_wp_stored_options', array() );

if ( ! empty( $feefo_wc_wp_stored_options['plugin_id'] ) && ! empty( $feefo_wc_wp_stored_options['key_id'] ) ) {

	if ( current_user_can( 'activate_plugins' ) ) {
		$key_id = $feefo_wc_wp_stored_options['key_id'];
		$plugin_id = $feefo_wc_wp_stored_options['plugin_id'];
		$table_name = $wpdb->prefix . 'woocommerce_api_keys';

		$wpdb->flush();
		$woocommerce_details = $wpdb->get_row("SELECT * FROM {$table_name} WHERE key_id = {$key_id}");

		if ( empty($wpdb->last_error) ) {

			$key = $woocommerce_details->consumer_secret;

			$merchantUrl = get_bloginfo( 'url' );
			$message = "pluginId={$plugin_id}&keyId={$key_id}&merchantUrl={$merchantUrl}";

			$hmac_value = hash_hmac( 'sha256', $message,  $key );

			$args = array(
				'body' => array(
					"plugin_id" => $plugin_id,
					"key_id"	=> $key_id,
					"merchant_url"	=> $merchantUrl,
					"hmac"		=> $hmac_value
				)
			);

			$requestHeaders = array(
        		'Content-Type' => 'application/json'
    		);

			$uninstall_route = $api_prefix . '/ecommerce/plugin/woocommerce/uninstall';

			$response = wp_remote_post( $uninstall_route, array(
            	'method' => 'DELETE',
            	'body' => json_encode( $args ),
            	'headers' => $requestHeaders,
            	'cookies' => array()
        		)
    		);

			//delete stored option
			delete_option( 'feefo_wc_wp_stored_options' );
			delete_option( 'feefo_wc_wp_widget_options' );
			delete_option( 'feefo_disable_woocommerce_review_tab' );
			delete_option( 'feefo_product_reviews_widget' );
			delete_option( 'feefo_product_widget_placement' );
			delete_option( 'feefo_service_reviews_widget' );
		}
	}
}