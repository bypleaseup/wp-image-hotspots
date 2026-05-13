<?php
/**
 * PHPStan bootstrap.
 *
 * Defines plugin constants so static analysis can resolve them without
 * loading WordPress. The actual values are irrelevant for analysis; only
 * their existence and string-ness matter to PHPStan.
 *
 * @package Pleaseup\WPImageHotspots
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/wordpress/' );
}
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'pleaseup-hotspots/pleaseup-hotspots.php' );
}
if ( ! defined( 'WPHS_PLUGIN_FILE' ) ) {
	define( 'WPHS_PLUGIN_FILE', __DIR__ . '/../pleaseup-hotspots.php' );
}
if ( ! defined( 'WPHS_PLUGIN_DIR' ) ) {
	define( 'WPHS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'WPHS_PLUGIN_URL' ) ) {
	define( 'WPHS_PLUGIN_URL', 'https://example.test/wp-content/plugins/pleaseup-hotspots/' );
}
if ( ! defined( 'WPHS_VERSION' ) ) {
	define( 'WPHS_VERSION', '3.0.0-alpha.1' );
}
if ( ! defined( 'WPHS_MIN_PHP' ) ) {
	define( 'WPHS_MIN_PHP', '7.4' );
}
if ( ! defined( 'WPHS_MIN_WP' ) ) {
	define( 'WPHS_MIN_WP', '6.0' );
}
