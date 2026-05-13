<?php
/**
 * AJAX endpoints for hotspot galleries.
 *
 * Closes the following baseline capability gaps from the pre-rewrite
 * security audit:
 *   #3 ajax_gallery_thumb_url   → upload_files capability
 *   #4 ajax_gallery_preview     → manage_options capability
 *
 * Also normalises every endpoint to read from $_POST (the legacy
 * thumb_url accepted GET via $_REQUEST).
 *
 * @package Pleaseup\WPImageHotspots\Ajax
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Ajax;

use Pleaseup\WPImageHotspots\Core\Capabilities;
use Pleaseup\WPImageHotspots\Core\GalleryRepository;
use Pleaseup\WPImageHotspots\Frontend\ShortcodeGallery;

defined( 'ABSPATH' ) || exit;

/**
 * Wires save / delete / preview / thumb_url endpoints.
 */
final class AjaxGallery extends AjaxBase {

	private GalleryRepository $galleries;
	private ShortcodeGallery $shortcodes;

	public function __construct( GalleryRepository $galleries, ShortcodeGallery $shortcodes ) {
		$this->galleries  = $galleries;
		$this->shortcodes = $shortcodes;
	}

	public function register() : void {
		add_action( 'wp_ajax_wphs_save_gallery', array( $this, 'save' ) );
		add_action( 'wp_ajax_wphs_delete_gallery', array( $this, 'delete' ) );
		add_action( 'wp_ajax_wphs_thumb_url', array( $this, 'thumb_url' ) );
		add_action( 'wp_ajax_wphs_gallery_preview', array( $this, 'preview' ) );
	}

	/**
	 * SAVE a gallery (create or update) and persist all meta.
	 */
	public function save() : void {
		$this->verify_nonce_or_die();
		$this->require_capability( Capabilities::can_manage_plugin() );

		$gallery_id = $this->post_int( 'gallery_id' );

		$captions_raw = isset( $_POST['gallery_captions'] )
			? wp_unslash( (string) $_POST['gallery_captions'] )
			: '{}';

		$input = array(
			'title'         => $this->post_string( 'gallery_title' ),
			'image_ids'     => $this->post_string( 'gallery_ids' ),
			'nav'           => $this->post_string( 'gallery_nav' ),
			'cols_desktop'  => $this->post_int( 'gallery_cols_desktop' ),
			'cols_mobile'   => $this->post_int( 'gallery_cols_mobile' ),
			'img_radius'    => $this->post_int( 'gallery_img_radius' ),
			'captions'      => $captions_raw,
			'caption_bg'    => $this->post_string( 'gallery_caption_bg' ),
			'caption_color' => $this->post_string( 'gallery_caption_color' ),
		);

		$saved_id = $this->galleries->save( $gallery_id, $input );
		if ( 0 === $saved_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not save gallery', 'pleaseup-hotspots' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'gallery_id' => $saved_id,
				'shortcode'  => sprintf( '[wphs_gallery id="%d"]', $saved_id ),
				'message'    => __( 'Gallery saved', 'pleaseup-hotspots' ),
			)
		);
	}

	/**
	 * DELETE a gallery (force, no trash).
	 */
	public function delete() : void {
		$this->verify_nonce_or_die();
		$this->require_capability( Capabilities::can_manage_plugin() );

		$gallery_id = $this->post_int( 'gallery_id' );
		if ( $gallery_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid gallery ID', 'pleaseup-hotspots' ) ), 400 );
		}

		if ( ! $this->galleries->delete( $gallery_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Gallery not found', 'pleaseup-hotspots' ) ), 404 );
		}

		wp_send_json_success( array( 'message' => __( 'Deleted', 'pleaseup-hotspots' ) ) );
	}

	/**
	 * Get a thumbnail URL for an attachment ID.
	 *
	 * Hardened: requires `upload_files` (the legacy version had only
	 * a nonce check and accepted GET via $_REQUEST).
	 */
	public function thumb_url() : void {
		$this->verify_nonce_or_die();
		$this->require_capability( Capabilities::can_upload_files() );

		$attachment_id = $this->post_int( 'id' );
		$url           = $attachment_id > 0
			? wp_get_attachment_image_url( $attachment_id, 'thumbnail' )
			: '';

		wp_send_json_success( array( 'url' => $url ? $url : '' ) );
	}

	/**
	 * Render a gallery preview HTML.
	 *
	 * Hardened: requires `manage_options` (the legacy version was
	 * accessible to any logged-in user with a nonce).
	 *
	 * Builds an in-memory gallery from the unsaved admin state (or
	 * falls back to the saved gallery when only an ID is provided)
	 * and delegates rendering to ShortcodeGallery::render_html().
	 */
	public function preview() : void {
		$this->verify_nonce_or_die();
		$this->require_capability( Capabilities::can_manage_plugin() );

		$gallery_id = $this->post_int( 'gallery_id' );
		$ids_raw    = $this->post_string( 'gallery_ids' );
		$nav        = $this->post_string( 'nav' );
		if ( '' === $nav ) {
			$nav = 'dots+arrows';
		}
		$cols_d     = isset( $_POST['cols_desktop'] )
			? max( 1, min( 6, absint( $_POST['cols_desktop'] ) ) )
			: 1;
		$cols_m     = isset( $_POST['cols_mobile'] )
			? max( 1, min( 3, absint( $_POST['cols_mobile'] ) ) )
			: 1;
		$img_radius = isset( $_POST['img_radius'] )
			? max( 0, min( 999, absint( $_POST['img_radius'] ) ) )
			: 0;

		$caps_raw = isset( $_POST['captions'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['captions'] ) )
			: '{}';
		$captions = json_decode( $caps_raw, true );
		if ( ! is_array( $captions ) ) {
			$captions = array();
		}

		$image_ids = array_values( array_filter( array_map( 'absint', explode( ',', $ids_raw ) ) ) );

		if ( empty( $image_ids ) && $gallery_id > 0 ) {
			$saved = $this->galleries->get( $gallery_id );
			if ( null !== $saved ) {
				$image_ids = $saved['image_ids'];
			}
		}

		if ( empty( $image_ids ) ) {
			wp_send_json_success( array( 'html' => '' ) );
		}

		$caption_bg    = $this->post_string( 'gallery_caption_bg', 'sanitize_hex_color' );
		$caption_color = $this->post_string( 'gallery_caption_color', 'sanitize_hex_color' );
		if ( '' === $caption_bg ) {
			$caption_bg = GalleryRepository::DEFAULT_CAPTION_BG;
		}
		if ( '' === $caption_color ) {
			$caption_color = GalleryRepository::DEFAULT_CAPTION_COL;
		}

		$html = $this->shortcodes->render_html(
			$image_ids,
			$nav,
			$cols_d,
			$cols_m,
			$img_radius,
			$captions,
			$caption_bg,
			$caption_color
		);

		wp_send_json_success( array( 'html' => $html ) );
	}
}
