<?php
/**
 * CRUD for the wphs_tooltip custom post type.
 *
 * @package Pleaseup\WPImageHotspots\Core
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Core;

use Pleaseup\WPImageHotspots\Helpers\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Tooltips are stored as private posts of type wphs_tooltip whose
 * post_content holds the rich HTML used by the frontend overlay.
 */
final class TooltipRepository {

	/**
	 * Read tooltip HTML by ID.
	 *
	 * @param int $tooltip_id Post ID.
	 * @return string HTML body, empty when missing.
	 */
	public function get_html( int $tooltip_id ) : string {
		if ( $tooltip_id <= 0 ) {
			return '';
		}
		$post = get_post( $tooltip_id );
		if ( ! $post instanceof \WP_Post || PostTypes::TOOLTIP !== $post->post_type ) {
			return '';
		}
		return (string) $post->post_content;
	}

	/**
	 * Insert or update a tooltip post.
	 *
	 * @param int    $tooltip_id    0 to create, >0 to update existing.
	 * @param string $html          HTML body (will be sanitized).
	 * @param int    $attachment_id Owning attachment (used for label).
	 * @param string $hotspot_id    Hotspot identifier (used for label).
	 * @return int Resulting tooltip ID, 0 on failure.
	 */
	public function upsert( int $tooltip_id, string $html, int $attachment_id, string $hotspot_id ) : int {
		$sanitized = Sanitizer::kses_tooltip_html( wp_unslash( $html ) );
		$title     = sprintf(
			/* translators: 1: hotspot id, 2: attachment id */
			__( 'WPHS Tooltip %1$s (att %2$d)', 'image-hotspots-tool' ),
			sanitize_text_field( $hotspot_id ),
			$attachment_id
		);

		$data = array(
			'post_type'    => PostTypes::TOOLTIP,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $sanitized,
		);

		if ( $tooltip_id > 0 ) {
			$data['ID'] = $tooltip_id;
			$result     = wp_update_post( $data, true );
			return is_wp_error( $result ) ? 0 : $tooltip_id;
		}

		$result = wp_insert_post( $data, true );
		return is_wp_error( $result ) ? 0 : (int) $result;
	}

	/**
	 * Force-delete a tooltip (no trash).
	 *
	 * @param int $tooltip_id Post ID.
	 */
	public function delete( int $tooltip_id ) : void {
		if ( $tooltip_id <= 0 ) {
			return;
		}
		$post = get_post( $tooltip_id );
		if ( ! $post instanceof \WP_Post || PostTypes::TOOLTIP !== $post->post_type ) {
			return;
		}
		wp_delete_post( $tooltip_id, true );
	}
}
