<?php
/**
 * Uninstall GioCookies.
 *
 * Settings and the consent log are removed only when
 * "Remove data" is enabled in the plugin settings.
 *
 * @package GioCookies
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove the plugin data of the current site.
 */
function giocookies_uninstall_site() {
	global $wpdb;

	wp_clear_scheduled_hook( 'giocookies_purge_log' );

	if ( '1' !== (string) get_option( 'giocookies_remove_data' ) ) {
		return;
	}

	$options = array(
		'giocookies_enable',
		'giocookies_title',
		'giocookies_message',
		'giocookies_label_reject',
		'giocookies_label_accept',
		'giocookies_label_save',
		'giocookies_label_customize',
		'giocookies_label_necessary',
		'giocookies_label_analytics',
		'giocookies_label_marketing',
		'giocookies_cat_analytics',
		'giocookies_cat_marketing',
		'giocookies_desc_necessary',
		'giocookies_desc_analytics',
		'giocookies_desc_marketing',
		'giocookies_privacy_page',
		'giocookies_privacy_url',
		'giocookies_cookie_page',
		'giocookies_cookie_url',
		'giocookies_position',
		'giocookies_show_bubble',
		'giocookies_accent_color',
		'giocookies_accent_text',
		'giocookies_consent_version',
		'giocookies_log_enabled',
		'giocookies_log_retention',
		'giocookies_remove_data',
		'giocookies_schema_version',
		// Options written by pre-release builds.
		'giocookies_db_version',
		'giocookies_api_key',
		'giocookies_show_decline',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- dropping the plugin table on uninstall.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'giocookies' ) );
}

if ( is_multisite() ) {
	$giocookies_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $giocookies_sites as $giocookies_site_id ) {
		switch_to_blog( $giocookies_site_id );
		giocookies_uninstall_site();
		restore_current_blog();
	}
} else {
	giocookies_uninstall_site();
}
