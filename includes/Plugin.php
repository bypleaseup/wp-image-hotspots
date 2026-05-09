<?php
/**
 * Plugin bootstrap.
 *
 * Wires the core subsystems, the AJAX modules (Phase 4), the admin
 * module (Phase 5) and the frontend module (Phase 6).
 *
 * @package Pleaseup\WPImageHotspots
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots;

use Pleaseup\WPImageHotspots\Admin\Admin;
use Pleaseup\WPImageHotspots\Ajax\AjaxGallery;
use Pleaseup\WPImageHotspots\Ajax\AjaxHotspots;
use Pleaseup\WPImageHotspots\Ajax\AjaxOembed;
use Pleaseup\WPImageHotspots\Ajax\AjaxSettings;
use Pleaseup\WPImageHotspots\Core\GalleryRepository;
use Pleaseup\WPImageHotspots\Core\HotspotRepository;
use Pleaseup\WPImageHotspots\Core\PostTypes;
use Pleaseup\WPImageHotspots\Core\Settings;
use Pleaseup\WPImageHotspots\Core\TooltipRepository;
use Pleaseup\WPImageHotspots\Frontend\Assets as FrontendAssets;
use Pleaseup\WPImageHotspots\Frontend\Frontend;
use Pleaseup\WPImageHotspots\Frontend\ShortcodeGallery;
use Pleaseup\WPImageHotspots\Frontend\ShortcodeImage;
use Pleaseup\WPImageHotspots\Helpers\OembedResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton bootstrap that owns the long-lived service instances.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private I18n $i18n;
	private PostTypes $post_types;
	private Settings $settings;
	private TooltipRepository $tooltips;
	private HotspotRepository $hotspots;
	private GalleryRepository $galleries;
	private OembedResolver $oembed;

	private ShortcodeImage $shortcode_image;
	private ShortcodeGallery $shortcode_gallery;
	private Frontend $frontend;

	private AjaxHotspots $ajax_hotspots;
	private AjaxSettings $ajax_settings;
	private AjaxGallery $ajax_gallery;
	private AjaxOembed $ajax_oembed;

	private ?Admin $admin = null;

	/**
	 * Get the singleton instance.
	 */
	public static function instance() : Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire dependencies.
	 */
	private function __construct() {
		$this->i18n       = new I18n();
		$this->post_types = new PostTypes();
		$this->settings   = new Settings();
		$this->tooltips   = new TooltipRepository();
		$this->hotspots   = new HotspotRepository( $this->tooltips );
		$this->galleries  = new GalleryRepository();
		$this->oembed     = new OembedResolver();

		$this->shortcode_image   = new ShortcodeImage( $this->settings, $this->tooltips, $this->oembed );
		$this->shortcode_gallery = new ShortcodeGallery( $this->galleries, $this->shortcode_image );
		$this->frontend          = new Frontend(
			new FrontendAssets(),
			$this->shortcode_image,
			$this->shortcode_gallery
		);

		$this->ajax_hotspots = new AjaxHotspots( $this->hotspots, $this->tooltips );
		$this->ajax_settings = new AjaxSettings( $this->settings );
		$this->ajax_gallery  = new AjaxGallery( $this->galleries, $this->shortcode_gallery );
		$this->ajax_oembed   = new AjaxOembed( $this->oembed );

		if ( is_admin() ) {
			$this->admin = new Admin(
				$this->settings,
				$this->hotspots,
				$this->tooltips,
				$this->galleries
			);
		}
	}

	/**
	 * Register hooks for every wired subsystem.
	 */
	public function run() : void {
		$this->i18n->register();
		$this->post_types->register();
		$this->frontend->register();

		// AJAX modules register unconditionally — admin-ajax.php is
		// the dispatcher that actually fires the relevant action.
		$this->ajax_hotspots->register();
		$this->ajax_settings->register();
		$this->ajax_gallery->register();
		$this->ajax_oembed->register();

		if ( null !== $this->admin ) {
			$this->admin->register();
		}

		/**
		 * Fires after every plugin subsystem has been wired.
		 *
		 * @since 3.0.0
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'wphs_loaded', $this );
	}

	public function settings() : Settings {
		return $this->settings;
	}

	public function tooltips() : TooltipRepository {
		return $this->tooltips;
	}

	public function hotspots() : HotspotRepository {
		return $this->hotspots;
	}

	public function galleries() : GalleryRepository {
		return $this->galleries;
	}

	public function oembed() : OembedResolver {
		return $this->oembed;
	}
}
