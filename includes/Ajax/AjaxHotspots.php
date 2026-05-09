<?php
/**
 * AJAX endpoints for hotspot CRUD and editor hydration.
 *
 * @package Pleaseup\WPImageHotspots\Ajax
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Ajax;

use Pleaseup\WPImageHotspots\Core\Capabilities;
use Pleaseup\WPImageHotspots\Core\HotspotRepository;
use Pleaseup\WPImageHotspots\Core\TooltipRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Wires hotspot-related admin endpoints onto admin-ajax.php and the
 * non-AJAX admin-post.php fallback.
 *
 * Uses a single nonce action (`wphs_nonce_v1`) for every endpoint.
 */
final class AjaxHotspots extends AjaxBase {

	private HotspotRepository $hotspots;
	private TooltipRepository $tooltips;

	public function __construct( HotspotRepository $hotspots, TooltipRepository $tooltips ) {
		$this->hotspots = $hotspots;
		$this->tooltips = $tooltips;
	}

	public function register() : void {
		add_action( 'wp_ajax_wphs_get_hotspots', array( $this, 'get_hotspots' ) );
		add_action( 'wp_ajax_wphs_save_hotspots', array( $this, 'save_hotspots' ) );
		add_action( 'wp_ajax_wphs_get_tooltips', array( $this, 'get_tooltips' ) );
		add_action( 'admin_post_wphs_save_hotspots_post', array( $this, 'save_hotspots_post' ) );
	}

	/**
	 * GET hotspots for an attachment, hydrated with tooltip HTML.
	 */
	public function get_hotspots() : void {
		$this->verify_nonce_or_die();
		$attachment_id = $this->post_int( 'attachment_id' );
		$this->require_capability( Capabilities::can_edit_attachment( $attachment_id ) );

		$hotspots = $this->hotspots->get_hydrated( $attachment_id );
		$encoded  = wp_json_encode( $hotspots, JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) ) {
			$encoded = '[]';
		}

		wp_send_json_success(
			array(
				'attachment_id' => $attachment_id,
				'hotspots_json' => $encoded,
				'saved_at'      => $this->hotspots->get_saved_at( $attachment_id ),
			)
		);
	}

	/**
	 * SAVE hotspots (delegates to repository which handles orphan cleanup).
	 */
	public function save_hotspots() : void {
		$this->verify_nonce_or_die();
		$attachment_id = $this->post_int( 'attachment_id' );
		$this->require_capability( Capabilities::can_edit_attachment( $attachment_id ) );

		$raw     = isset( $_POST['hotspots_json'] ) ? wp_unslash( (string) $_POST['hotspots_json'] ) : '[]';
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid JSON', 'wp-image-hotspots' ) ), 400 );
		}

		$result = $this->hotspots->save( $attachment_id, $decoded );

		wp_send_json_success(
			array(
				'attachment_id' => $attachment_id,
				'hotspots'      => $result['hotspots'],
				'count'         => $result['count'],
				'saved_at'      => $result['saved_at'],
				'meta_key'      => HotspotRepository::META_KEY,
			)
		);
	}

	/**
	 * Return the {tooltip_id => html} map for the editor hydration step.
	 */
	public function get_tooltips() : void {
		$this->verify_nonce_or_die();

		$attachment_id = $this->post_int( 'attachment_id' );
		$this->require_capability( Capabilities::can_edit_attachment( $attachment_id ) );

		$tooltips = array();
		foreach ( $this->hotspots->get( $attachment_id ) as $hotspot ) {
			$tid = isset( $hotspot['tooltip_id'] ) ? (int) $hotspot['tooltip_id'] : 0;
			if ( $tid > 0 ) {
				$tooltips[ $tid ] = $this->tooltips->get_html( $tid );
			}
		}

		wp_send_json_success( array( 'tooltips' => $tooltips ) );
	}

	/**
	 * Non-AJAX fallback: handles a plain form POST and redirects.
	 */
	public function save_hotspots_post() : void {
		if ( ! is_admin() ) {
			wp_die( esc_html__( 'Forbidden', 'wp-image-hotspots' ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( $attachment_id <= 0 ) {
			wp_die( esc_html__( 'Missing attachment_id', 'wp-image-hotspots' ) );
		}

		check_admin_referer( 'wphs_save_hotspots_post_' . $attachment_id );

		if ( ! Capabilities::can_edit_attachment( $attachment_id ) ) {
			wp_die( esc_html__( 'Unauthorized', 'wp-image-hotspots' ) );
		}

		$raw     = isset( $_POST['hotspots_json'] ) ? wp_unslash( (string) $_POST['hotspots_json'] ) : '[]';
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		$this->hotspots->save( $attachment_id, $decoded );

		wp_safe_redirect( admin_url( 'admin.php?page=wphs-editor&attachment_id=' . $attachment_id . '&wphs_saved=1' ) );
		exit;
	}
}
