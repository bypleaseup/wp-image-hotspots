<?php
/**
 * Plugin Name:       Image Hotspots by Pleaseup – Interactive Image Map & Tooltips
 * Plugin URI:        https://wordpress.org/plugins/pleaseup-hotspots/
 * Description:       Interactive image hotspots with drag &amp; drop editor. Add tooltips, annotations, and image maps.
 * Version:           3.1.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Pleaseup
 * Author URI:        https://by.pleaseup.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pleaseup-hotspots
 * Domain Path:       /languages
 *
 * @package Pleaseup\WPImageHotspots
 */

defined( 'ABSPATH' ) || exit;

define( 'WPHS_PLUGIN_FILE', __FILE__ );
define( 'WPHS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPHS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPHS_VERSION', '3.1.1' );
define( 'WPHS_MIN_PHP', '7.4' );
define( 'WPHS_MIN_WP', '6.0' );

$wphs_autoload = WPHS_PLUGIN_DIR . 'vendor/autoload.php';
if ( is_readable( $wphs_autoload ) ) {
	require $wphs_autoload;
} else {
	// Fallback PSR-4 autoloader so the plugin can be installed from a
	// source checkout (or from the WP.org zip) without running
	// `composer install`. When vendor/ is present the Composer
	// autoloader takes precedence above.
	spl_autoload_register(
		static function ( $class ) {
			$prefix = 'Pleaseup\\WPImageHotspots\\';
			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}
			$relative = substr( $class, strlen( $prefix ) );
			$file     = WPHS_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $file ) ) {
				require $file;
			}
		}
	);
}
unset( $wphs_autoload );

if ( class_exists( '\\Pleaseup\\WPImageHotspots\\Plugin' ) ) {
	register_activation_hook( __FILE__, array( '\\Pleaseup\\WPImageHotspots\\Activator', 'activate' ) );
	register_deactivation_hook( __FILE__, array( '\\Pleaseup\\WPImageHotspots\\Deactivator', 'deactivate' ) );

	add_action(
		'plugins_loaded',
		static function () {
			\Pleaseup\WPImageHotspots\Plugin::instance()->run();
		}
	);
}
