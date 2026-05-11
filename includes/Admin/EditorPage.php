<?php
/**
 * Editor admin page renderer.
 *
 * @package Pleaseup\WPImageHotspots\Admin
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Admin;

use Pleaseup\WPImageHotspots\Core\HotspotRepository;
use Pleaseup\WPImageHotspots\Core\Settings;
use Pleaseup\WPImageHotspots\Core\TooltipRepository;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Prepares the data for the editor template and includes the partial.
 *
 * Note: by convention, partials do not perform DB queries. The legacy
 * editor template still contains a couple of `get_post_meta()` calls
 * (one per recent-image thumbnail) that survived the verbatim extraction;
 * they are tracked for a future cleanup PR.
 */
final class EditorPage {

	private Settings $settings;
	private HotspotRepository $hotspots;
	private TooltipRepository $tooltips;

	public function __construct( Settings $settings, HotspotRepository $hotspots, TooltipRepository $tooltips ) {
		$this->settings = $settings;
		$this->hotspots = $hotspots;
		$this->tooltips = $tooltips;
	}

	/**
	 * Render the editor page.
	 */
	public function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen context
		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;

		$opts      = $this->settings->get_options();
		$icon_url  = $opts['hotspot_image_id']
			? (string) wp_get_attachment_image_url( (int) $opts['hotspot_image_id'], 'thumbnail' )
			: '';
		$image_url = $attachment_id ? (string) wp_get_attachment_image_url( $attachment_id, 'large' ) : '';
		$saved_at  = $attachment_id ? $this->hotspots->get_saved_at( $attachment_id ) : '';

		// Hydrate hotspots and JSON-encode for the JS layer.
		$hotspots_arr = $attachment_id ? $this->hotspots->get_hydrated( $attachment_id ) : array();
		$json         = wp_json_encode( $hotspots_arr, JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			$json = '[]';
		}

		$img_settings = $this->settings->get_image_settings( $attachment_id );
		$tt_bg        = $img_settings['tooltip_bg'];
		$tt_color     = $img_settings['tooltip_color'];
		$tt_radius    = $img_settings['tooltip_radius'];
		$tt_tail      = $img_settings['tooltip_tail'];
		$img_icon_url = $img_settings['hotspot_image_id']
			? (string) wp_get_attachment_image_url( (int) $img_settings['hotspot_image_id'], 'thumbnail' )
			: '';

		$recent = $this->recent_images_with_hotspots( 12 );

		// Vars in scope are consumed by the partial.
		require WPHS_PLUGIN_DIR . 'includes/Admin/partials/editor-page.php';
	}

	/**
	 * Most recently edited attachments that have hotspot data.
	 *
	 * @param int $limit Max results.
	 * @return array<int,\WP_Post>
	 */
	private function recent_images_with_hotspots( int $limit = 12 ) : array {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => max( 1, $limit ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => HotspotRepository::META_KEY,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => HotspotRepository::META_KEY,
						'value'   => '[]',
						'compare' => '!=',
					),
					array(
						'key'     => HotspotRepository::META_KEY,
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);

		$posts = is_array( $query->posts ) ? $query->posts : array();

		usort(
			$posts,
			static function ( $a, $b ) {
				$at = (string) get_post_meta( $a->ID, HotspotRepository::META_SAVED_AT, true );
				$bt = (string) get_post_meta( $b->ID, HotspotRepository::META_SAVED_AT, true );
				$ats = $at ? strtotime( $at ) : 0;
				$bts = $bt ? strtotime( $bt ) : 0;
				if ( $ats === $bts ) {
					$am = strtotime( (string) $a->post_modified_gmt );
					$bm = strtotime( (string) $b->post_modified_gmt );
					return $bm <=> $am;
				}
				return $bts <=> $ats;
			}
		);

		return array_slice( $posts, 0, max( 1, $limit ) );
	}
}
