<?php
/**
 * Loads the plugin text domain.
 *
 * @package Pleaseup\WPImageHotspots
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks load_plugin_textdomain() onto plugins_loaded.
 */
final class I18n {

	/**
	 * Register the plugins_loaded callback.
	 */
	public function register() : void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load the .mo file from /languages/.
	 */
	public function load_textdomain() : void {
		load_plugin_textdomain(
			'pleaseup-hotspots',
			false,
			dirname( plugin_basename( WPHS_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
