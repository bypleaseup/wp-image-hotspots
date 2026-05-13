<?php
/**
 * Admin asset enqueue.
 *
 * Replaces the legacy `admin_enqueue` + `wp_ajax_wphs_admin_js` flow:
 * scripts and styles are now physical files under /assets/dist/
 * (Phase 3) registered with `filemtime()` cache busting.
 *
 * @package Pleaseup\WPImageHotspots\Admin
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Admin;

use Pleaseup\WPImageHotspots\Ajax\AjaxBase;
use Pleaseup\WPImageHotspots\Core\GalleryRepository;
use Pleaseup\WPImageHotspots\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues admin CSS/JS only on the plugin's own pages.
 */
final class Assets {

	private const HANDLE_CSS         = 'wphs-admin';
	private const HANDLE_JS          = 'wphs-admin';
	private const HANDLE_GALLERY_JS  = 'wphs-admin-gallery';

	private Settings $settings;
	private GalleryRepository $galleries;

	public function __construct( Settings $settings, GalleryRepository $galleries ) {
		$this->settings  = $settings;
		$this->galleries = $galleries;
	}

	/**
	 * Hooked onto admin_enqueue_scripts. No-op outside the plugin pages.
	 */
	public function enqueue() : void {
		$page = $this->current_page();
		if ( '' === $page || 0 !== strpos( $page, 'wphs' ) ) {
			return;
		}

		$css_path = WPHS_PLUGIN_DIR . 'assets/dist/css/admin.css';
		$js_path  = WPHS_PLUGIN_DIR . 'assets/dist/js/admin.js';
		$css_url  = WPHS_PLUGIN_URL . 'assets/dist/css/admin.css';
		$js_url   = WPHS_PLUGIN_URL . 'assets/dist/js/admin.js';

		$css_ver = file_exists( $css_path ) ? (string) filemtime( $css_path ) : WPHS_VERSION;
		$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : WPHS_VERSION;

		wp_enqueue_media();
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-draggable' );

		if ( 'wphs-editor' === $page ) {
			wp_enqueue_editor();
		}

		wp_enqueue_style( self::HANDLE_CSS, $css_url, array(), $css_ver );
		wp_register_script(
			self::HANDLE_JS,
			$js_url,
			array( 'jquery', 'jquery-ui-draggable' ),
			$js_ver,
			true
		);
		wp_enqueue_script( self::HANDLE_JS );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen context
		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;
		$opts          = $this->settings->get_options();
		$icon_url      = $opts['hotspot_image_id']
			? wp_get_attachment_image_url( (int) $opts['hotspot_image_id'], 'thumbnail' )
			: '';

		if ( 'wphs-galleries' === $page ) {
			wp_localize_script(
				self::HANDLE_JS,
				'WPHS_ADMIN',
				array(
					'context' => 'gallery',
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => AjaxBase::create_nonce(),
					'i18n'    => $this->js_strings(),
				)
			);

			$gallery_js_path = WPHS_PLUGIN_DIR . 'assets/dist/js/admin-gallery.js';
			$gallery_js_url  = WPHS_PLUGIN_URL . 'assets/dist/js/admin-gallery.js';
			$gallery_js_ver  = file_exists( $gallery_js_path ) ? (string) filemtime( $gallery_js_path ) : WPHS_VERSION;

			wp_enqueue_script(
				self::HANDLE_GALLERY_JS,
				$gallery_js_url,
				array( self::HANDLE_JS ),
				$gallery_js_ver,
				true
			);
			wp_localize_script(
				self::HANDLE_GALLERY_JS,
				'WPHS_GALLERY',
				array(
					'nonce'     => AjaxBase::create_nonce(),
					'ajaxurl'   => admin_url( 'admin-ajax.php' ),
					'galleries' => $this->galleries_for_js(),
				)
			);

			// Preview reuses the frontend gallery CSS + carousel JS so
			// the admin preview matches what visitors see. The frontend
			// Assets module skips registration in admin context, so we
			// enqueue them directly here for this one screen.
			$front_css_path = WPHS_PLUGIN_DIR . 'assets/dist/css/frontend.css';
			$front_js_path  = WPHS_PLUGIN_DIR . 'assets/dist/js/frontend-gallery.js';
			$front_css_url  = WPHS_PLUGIN_URL . 'assets/dist/css/frontend.css';
			$front_js_url   = WPHS_PLUGIN_URL . 'assets/dist/js/frontend-gallery.js';
			$front_css_ver  = file_exists( $front_css_path ) ? (string) filemtime( $front_css_path ) : WPHS_VERSION;
			$front_js_ver   = file_exists( $front_js_path ) ? (string) filemtime( $front_js_path ) : WPHS_VERSION;

			wp_enqueue_style( 'wphs-frontend', $front_css_url, array(), $front_css_ver );
			wp_enqueue_script( 'wphs-frontend-gallery', $front_js_url, array(), $front_js_ver, true );

			return;
		}

		$img_settings = $this->settings->get_image_settings( $attachment_id );

		wp_localize_script(
			self::HANDLE_JS,
			'WPHS_ADMIN',
			array(
				'context'         => 'editor',
				'ajaxurl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => AjaxBase::create_nonce(),
				'attachmentId'    => $attachment_id,
				'hotspotStyle'    => $opts['hotspot_style'],
				'hotspotIcon'     => $icon_url ? $icon_url : '',
				'hotspotIconSize' => absint( $opts['hotspot_icon_size'] ),
				'defaultHtml'     => '<strong>' . esc_html__( 'Title', 'pleaseup-hotspots' ) . '</strong><br>' . esc_html__( 'Description', 'pleaseup-hotspots' ),
				'imgSettings'     => array_merge( $img_settings, array( '_use_default_flag' => false ) ),
				'imgIconUrl'      => $img_settings['hotspot_image_id']
					? wp_get_attachment_image_url( (int) $img_settings['hotspot_image_id'], 'thumbnail' )
					: '',
				'imgSelIconUrl'   => $img_settings['hotspot_selected_image_id']
					? wp_get_attachment_image_url( (int) $img_settings['hotspot_selected_image_id'], 'thumbnail' )
					: '',
				'i18n'            => $this->js_strings(),
			)
		);
	}

	/**
	 * Translatable strings consumed by admin JS, exposed via the
	 * `WPHS_ADMIN.i18n` object so JS doesn't hardcode user-facing
	 * English text.
	 *
	 * @return array<string,string>
	 */
	private function js_strings() : array {
		return array(
			'saved'              => __( 'Saved', 'pleaseup-hotspots' ),
			'saveFailed'         => __( 'Save failed', 'pleaseup-hotspots' ),
			'saving'             => __( 'Saving…', 'pleaseup-hotspots' ),
			'styleSaved'         => __( 'Hotspot style saved', 'pleaseup-hotspots' ),
			'resetToDefault'     => __( 'Reset to default', 'pleaseup-hotspots' ),
			'resetFailed'        => __( 'Reset failed', 'pleaseup-hotspots' ),
			'chooseNormalFirst'  => __( 'Choose a Normal icon first.', 'pleaseup-hotspots' ),
			'previewNormal'      => __( 'Preview: normal', 'pleaseup-hotspots' ),
			'previewSelected'    => __( 'Preview: selected', 'pleaseup-hotspots' ),
			'confirmDeleteAll'   => __( 'Remove all hotspots from this image?', 'pleaseup-hotspots' ),
			'confirmDeleteOne'   => __( 'Delete this hotspot?', 'pleaseup-hotspots' ),
			'confirmDeleteImg'   => __( 'Remove all hotspots and per-image settings? This cannot be undone.', 'pleaseup-hotspots' ),
			'confirmDeleteGall'  => __( 'Delete this gallery? Images and hotspots are kept.', 'pleaseup-hotspots' ),
			'never'              => __( 'never', 'pleaseup-hotspots' ),
		);
	}

	/**
	 * Build the galleries payload consumed by the gallery JS.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function galleries_for_js() : array {
		$out = array();
		foreach ( $this->galleries->get_all() as $gallery ) {
			$thumb_urls = array();
			foreach ( $gallery['image_ids'] as $image_id ) {
				$thumb_urls[ $image_id ] = (string) wp_get_attachment_image_url( $image_id, 'thumbnail' );
			}
			$out[] = array(
				'id'            => $gallery['id'],
				'title'         => $gallery['title'],
				'image_ids'     => $gallery['image_ids'],
				'thumb_urls'    => $thumb_urls,
				'nav'           => $gallery['nav'],
				'img_radius'    => $gallery['img_radius'],
				'captions'      => $gallery['captions'],
				'caption_bg'    => $gallery['caption_bg'],
				'caption_color' => $gallery['caption_color'],
				'cols_desktop'  => $gallery['cols_desktop'],
				'cols_mobile'   => $gallery['cols_mobile'],
			);
		}
		return $out;
	}

	private function current_page() : string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen context
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
	}
}
