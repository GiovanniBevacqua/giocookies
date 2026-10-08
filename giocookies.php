<?php
/**
 * Plugin Name:       GioCookies
 * Plugin URI:        https://giosuite.com/giocookies
 * Description:       Lightweight cookie consent banner for Google Consent Mode v2, Google Tag Manager and the WP Consent API, with script blocking and an anonymized consent log.
 * Version:           1.0.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Giovanni Bevacqua
 * Author URI:        https://www.linkedin.com/in/giovanni-bevacqua/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       giocookies
 *
 * @package GioCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GIOCOOKIES_PLUGIN_FILE', __FILE__ );
define( 'GIOCOOKIES_VERSION', '1.0.1' );
define( 'GIOCOOKIES_SCHEMA_VERSION', 1 );
define( 'GIOCOOKIES_DIR', plugin_dir_path( __FILE__ ) );
define( 'GIOCOOKIES_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	function ( $class_name ) {
		$base = 'GioCookies\\';
		$len  = strlen( $base );
		if ( strncmp( $base, $class_name, $len ) !== 0 ) {
			return;
		}

		$file = GIOCOOKIES_DIR . 'includes/' . str_replace( '\\', '/', substr( $class_name, $len ) ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'GioCookies\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GioCookies\\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'GioCookies\\Plugin', 'instance' ) );
