<?php
/**
 * Frontend bootstrap.
 *
 * Wires the public-facing layer: registers the wphs-frontend asset
 * handles and the two shortcodes ([wphs_image], [wphs_gallery]).
 *
 * @package Pleaseup\WPImageHotspots\Frontend
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Composes Assets + the two shortcode classes.
 */
final class Frontend {

	private Assets $assets;
	private ShortcodeImage $shortcode_image;
	private ShortcodeGallery $shortcode_gallery;

	public function __construct(
		Assets $assets,
		ShortcodeImage $shortcode_image,
		ShortcodeGallery $shortcode_gallery
	) {
		$this->assets            = $assets;
		$this->shortcode_image   = $shortcode_image;
		$this->shortcode_gallery = $shortcode_gallery;
	}

	/**
	 * Hook the frontend module onto WordPress.
	 */
	public function register() : void {
		add_action( 'wp_enqueue_scripts', array( $this->assets, 'register_assets' ) );
		$this->shortcode_image->register();
		$this->shortcode_gallery->register();
	}
}
