<?php
/**
 * CRUD for hotspot data stored as post_meta on attachments.
 *
 * Persists the array described in aidha/03-data-model.md section B.1.
 * Auto-migrates legacy entries with inline `html` to the CPT-backed
 * `tooltip_id` form on first write (regression-critical: see
 * aidha/04-constraints.md section H point 2).
 *
 * @package Pleaseup\WPImageHotspots\Core
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Core;

use Pleaseup\WPImageHotspots\Helpers\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Hotspot CRUD with tooltip cleanup of orphans.
 */
final class HotspotRepository {

	public const META_KEY      = '_wphs_hotspots_v1';
	public const META_SAVED_AT = '_wphs_hotspots_saved_at_v1';

	/**
	 * Tooltip repository instance (constructor-injected).
	 *
	 * @var TooltipRepository
	 */
	private TooltipRepository $tooltips;

	/**
	 * @param TooltipRepository $tooltips Tooltip repository.
	 */
	public function __construct( TooltipRepository $tooltips ) {
		$this->tooltips = $tooltips;
	}

	/**
	 * Read raw stored hotspots for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int,array<string,mixed>> Decoded hotspot list, empty if none.
	 */
	public function get( int $attachment_id ) : array {
		if ( $attachment_id <= 0 ) {
			return array();
		}
		$raw = get_post_meta( $attachment_id, self::META_KEY, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Read hotspots and hydrate each with the tooltip HTML, ready for
	 * the editor.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_hydrated( int $attachment_id ) : array {
		$hotspots = $this->get( $attachment_id );
		foreach ( $hotspots as &$hotspot ) {
			$tooltip_id = isset( $hotspot['tooltip_id'] ) ? (int) $hotspot['tooltip_id'] : 0;
			if ( $tooltip_id > 0 ) {
				$hotspot['html'] = $this->tooltips->get_html( $tooltip_id );
			} elseif ( ! isset( $hotspot['html'] ) ) {
				$hotspot['html'] = '';
			}
		}
		unset( $hotspot );
		return $hotspots;
	}

	/**
	 * Persist hotspots for an attachment, upserting tooltips and
	 * deleting orphans.
	 *
	 * @param int                                       $attachment_id Attachment ID.
	 * @param array<int,array<string,mixed>>            $incoming      Raw incoming hotspots.
	 * @return array{hotspots:array<int,array<string,mixed>>,saved_at:string,count:int}
	 */
	public function save( int $attachment_id, array $incoming ) : array {
		$previous_ids = array();
		foreach ( $this->get( $attachment_id ) as $prev ) {
			$prev_tid = isset( $prev['tooltip_id'] ) ? (int) $prev['tooltip_id'] : 0;
			if ( $prev_tid > 0 ) {
				$previous_ids[] = $prev_tid;
			}
		}

		$kept_ids  = array();
		$sanitized = array();

		foreach ( $incoming as $hotspot ) {
			if ( ! is_array( $hotspot ) ) {
				continue;
			}
			$id = isset( $hotspot['id'] ) ? sanitize_text_field( (string) $hotspot['id'] ) : '';
			if ( '' === $id ) {
				continue;
			}
			$x = isset( $hotspot['x'] ) ? (float) $hotspot['x'] : 0.0;
			$y = isset( $hotspot['y'] ) ? (float) $hotspot['y'] : 0.0;
			$x = Sanitizer::clamp_float( $x, 0.0, 100.0 );
			$y = Sanitizer::clamp_float( $y, 0.0, 100.0 );

			$tooltip_id = isset( $hotspot['tooltip_id'] ) ? (int) $hotspot['tooltip_id'] : 0;
			$html       = isset( $hotspot['html'] ) ? (string) $hotspot['html'] : '';

			$tooltip_id = $this->tooltips->upsert( $tooltip_id, $html, $attachment_id, $id );
			if ( $tooltip_id > 0 ) {
				$kept_ids[] = $tooltip_id;
			}

			$sanitized[] = array(
				'id'         => $id,
				'x'          => $x,
				'y'          => $y,
				'tooltip_id' => $tooltip_id,
			);
		}

		foreach ( array_diff( $previous_ids, $kept_ids ) as $orphan_id ) {
			$this->tooltips->delete( (int) $orphan_id );
		}

		$encoded = wp_json_encode( $sanitized, JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) ) {
			$encoded = '[]';
		}

		update_post_meta( $attachment_id, self::META_KEY, $encoded );
		$saved_at = current_time( 'mysql' );
		update_post_meta( $attachment_id, self::META_SAVED_AT, $saved_at );

		/**
		 * Fires after a successful hotspot save.
		 *
		 * @since 3.0.0
		 *
		 * @param int                                $attachment_id Attachment ID.
		 * @param array<int,array<string,mixed>>     $sanitized     Final stored hotspots.
		 */
		do_action( 'wphs_after_save_hotspots', $attachment_id, $sanitized );

		return array(
			'hotspots' => $sanitized,
			'saved_at' => $saved_at,
			'count'    => count( $sanitized ),
		);
	}

	/**
	 * Read the saved-at timestamp, if any.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string MySQL datetime or empty string.
	 */
	public function get_saved_at( int $attachment_id ) : string {
		$value = get_post_meta( $attachment_id, self::META_SAVED_AT, true );
		return is_string( $value ) ? $value : '';
	}
}
