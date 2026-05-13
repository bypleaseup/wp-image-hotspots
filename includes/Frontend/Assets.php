<?php
/**
 * Frontend asset registration.
 *
 * Replaces the v2.5.80 frontend_assets() helper. CSS/JS are now
 * physical files extracted in Phase 3, registered here and enqueued
 * on demand by the shortcode renderers.
 *
 * @package Pleaseup\WPImageHotspots\Frontend
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the wphs-frontend style + script handles.
 *
 * Shortcodes are responsible for calling wp_enqueue_style/_script when
 * they actually emit HTML, mirroring the on-demand pattern of the
 * legacy plugin (so pages without hotspots do not load the assets).
 */
final class Assets {

	public const HANDLE         = 'wphs-frontend';
	public const HANDLE_GALLERY = 'wphs-frontend-gallery';

	/**
	 * Hooked onto wp_enqueue_scripts.
	 */
	public function register_assets() : void {
		if ( is_admin() ) {
			return;
		}

		$css_path     = WPHS_PLUGIN_DIR . 'assets/dist/css/frontend.css';
		$js_path      = WPHS_PLUGIN_DIR . 'assets/dist/js/frontend.js';
		$gallery_path = WPHS_PLUGIN_DIR . 'assets/dist/js/frontend-gallery.js';
		$css_url      = WPHS_PLUGIN_URL . 'assets/dist/css/frontend.css';
		$js_url       = WPHS_PLUGIN_URL . 'assets/dist/js/frontend.js';
		$gallery_url  = WPHS_PLUGIN_URL . 'assets/dist/js/frontend-gallery.js';

		$css_ver     = file_exists( $css_path ) ? (string) filemtime( $css_path ) : WPHS_VERSION;
		$js_ver      = file_exists( $js_path ) ? (string) filemtime( $js_path ) : WPHS_VERSION;
		$gallery_ver = file_exists( $gallery_path ) ? (string) filemtime( $gallery_path ) : WPHS_VERSION;

		wp_register_style( self::HANDLE, $css_url, array(), $css_ver );
		wp_register_script( self::HANDLE, $js_url, array( 'jquery' ), $js_ver, true );
		// Vanilla JS — no jQuery dep. Self-initialises on DOMContentLoaded
		// and exposes window.wphsInitGalleries() for the admin preview.
		wp_register_script( self::HANDLE_GALLERY, $gallery_url, array(), $gallery_ver, true );
	}
}
