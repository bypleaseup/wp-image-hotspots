<?php
/**
 * CRUD for the wphs_gallery custom post type.
 *
 * @package Pleaseup\WPImageHotspots\Core
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Core;

use Pleaseup\WPImageHotspots\Helpers\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Gallery CRUD with normalization of meta values.
 */
final class GalleryRepository {

	public const META_IDS            = '_wphs_gallery_ids';
	public const META_NAV            = '_wphs_gallery_nav';
	public const META_COLS_DESKTOP   = '_wphs_gallery_cols_desktop';
	public const META_COLS_MOBILE    = '_wphs_gallery_cols_mobile';
	public const META_IMG_RADIUS     = '_wphs_gallery_img_radius';
	public const META_CAPTIONS       = '_wphs_gallery_captions';
	public const META_CAPTION_BG     = '_wphs_gallery_caption_bg';
	public const META_CAPTION_COLOR  = '_wphs_gallery_caption_color';
	public const ALLOWED_NAV         = array( 'dots+arrows', 'dots', 'arrows', 'none' );
	public const DEFAULT_CAPTION_BG  = '#111827';
	public const DEFAULT_CAPTION_COL = '#ffffff';
	public const DEFAULT_NAV         = 'dots+arrows';

	/**
	 * Read a gallery by ID, hydrated to a flat array.
	 *
	 * @param int $gallery_id Gallery post ID.
	 * @return array<string,mixed>|null Null when missing.
	 */
	public function get( int $gallery_id ) : ?array {
		if ( $gallery_id <= 0 ) {
			return null;
		}
		$post = get_post( $gallery_id );
		if ( ! $post instanceof \WP_Post || PostTypes::GALLERY !== $post->post_type ) {
			return null;
		}
		return $this->hydrate( $post );
	}

	/**
	 * Read all galleries (no pagination).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_all() : array {
		$posts = get_posts(
			array(
				'post_type'      => PostTypes::GALLERY,
				'post_status'    => 'publish',
				'numberposts'    => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'suppress_filters' => false,
			)
		);

		$out = array();
		foreach ( $posts as $post ) {
			$out[] = $this->hydrate( $post );
		}
		return $out;
	}

	/**
	 * Create or update a gallery from raw input.
	 *
	 * @param int                 $gallery_id 0 to create, >0 to update.
	 * @param array<string,mixed> $input      Raw input from AJAX/admin.
	 * @return int Resulting gallery ID, 0 on failure.
	 */
	public function save( int $gallery_id, array $input ) : int {
		$title = isset( $input['title'] )
			? sanitize_text_field( (string) $input['title'] )
			: __( 'Untitled gallery', 'image-hotspots-tool' );

		$post_data = array(
			'post_type'   => PostTypes::GALLERY,
			'post_status' => 'publish',
			'post_title'  => $title,
		);

		if ( $gallery_id > 0 ) {
			$post_data['ID'] = $gallery_id;
			$result          = wp_update_post( $post_data, true );
			$id              = is_wp_error( $result ) ? 0 : $gallery_id;
		} else {
			$result = wp_insert_post( $post_data, true );
			$id     = is_wp_error( $result ) ? 0 : (int) $result;
		}

		if ( 0 === $id ) {
			return 0;
		}

		$this->write_meta( $id, $input );
		return $id;
	}

	/**
	 * Delete a gallery (force, no trash).
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return bool True when deleted.
	 */
	public function delete( int $gallery_id ) : bool {
		if ( $gallery_id <= 0 ) {
			return false;
		}
		$post = get_post( $gallery_id );
		if ( ! $post instanceof \WP_Post || PostTypes::GALLERY !== $post->post_type ) {
			return false;
		}
		return (bool) wp_delete_post( $gallery_id, true );
	}

	/**
	 * Convert a gallery WP_Post into a flat array of fields.
	 *
	 * @param \WP_Post $post Gallery post.
	 * @return array<string,mixed>
	 */
	private function hydrate( \WP_Post $post ) : array {
		$id_csv = (string) get_post_meta( $post->ID, self::META_IDS, true );
		$ids    = array_values( array_filter( array_map( 'absint', explode( ',', $id_csv ) ) ) );

		$nav = (string) get_post_meta( $post->ID, self::META_NAV, true );
		if ( ! in_array( $nav, self::ALLOWED_NAV, true ) ) {
			$nav = self::DEFAULT_NAV;
		}

		$captions_raw = (string) get_post_meta( $post->ID, self::META_CAPTIONS, true );
		$captions     = json_decode( $captions_raw, true );
		if ( ! is_array( $captions ) ) {
			$captions = array();
		}

		return array(
			'id'             => $post->ID,
			'title'          => $post->post_title,
			'image_ids'      => $ids,
			'nav'            => $nav,
			'cols_desktop'   => Sanitizer::clamp_int( (int) get_post_meta( $post->ID, self::META_COLS_DESKTOP, true ), 1, 6 ),
			'cols_mobile'    => Sanitizer::clamp_int( (int) get_post_meta( $post->ID, self::META_COLS_MOBILE, true ), 1, 3 ),
			'img_radius'     => Sanitizer::clamp_int( (int) get_post_meta( $post->ID, self::META_IMG_RADIUS, true ), 0, 999 ),
			'captions'       => $captions,
			'caption_bg'     => Sanitizer::hex_color( (string) get_post_meta( $post->ID, self::META_CAPTION_BG, true ), self::DEFAULT_CAPTION_BG ),
			'caption_color'  => Sanitizer::hex_color( (string) get_post_meta( $post->ID, self::META_CAPTION_COLOR, true ), self::DEFAULT_CAPTION_COL ),
		);
	}

	/**
	 * Persist meta values for a gallery, normalizing every field.
	 *
	 * @param int                 $gallery_id Gallery ID.
	 * @param array<string,mixed> $input      Raw input.
	 */
	private function write_meta( int $gallery_id, array $input ) : void {
		$ids_raw = isset( $input['image_ids'] ) ? $input['image_ids'] : '';
		if ( is_array( $ids_raw ) ) {
			$ids = array_filter( array_map( 'absint', $ids_raw ) );
		} else {
			$ids = array_filter( array_map( 'absint', explode( ',', (string) $ids_raw ) ) );
		}
		update_post_meta( $gallery_id, self::META_IDS, implode( ',', $ids ) );

		$nav = isset( $input['nav'] ) ? (string) $input['nav'] : self::DEFAULT_NAV;
		if ( ! in_array( $nav, self::ALLOWED_NAV, true ) ) {
			$nav = self::DEFAULT_NAV;
		}
		update_post_meta( $gallery_id, self::META_NAV, $nav );

		update_post_meta(
			$gallery_id,
			self::META_COLS_DESKTOP,
			Sanitizer::clamp_int( isset( $input['cols_desktop'] ) ? (int) $input['cols_desktop'] : 1, 1, 6 )
		);
		update_post_meta(
			$gallery_id,
			self::META_COLS_MOBILE,
			Sanitizer::clamp_int( isset( $input['cols_mobile'] ) ? (int) $input['cols_mobile'] : 1, 1, 3 )
		);
		update_post_meta(
			$gallery_id,
			self::META_IMG_RADIUS,
			Sanitizer::clamp_int( isset( $input['img_radius'] ) ? (int) $input['img_radius'] : 0, 0, 999 )
		);

		$captions = isset( $input['captions'] ) ? $input['captions'] : array();
		if ( is_string( $captions ) ) {
			$decoded  = json_decode( wp_unslash( $captions ), true );
			$captions = is_array( $decoded ) ? $decoded : array();
		}
		$captions_clean = array();
		foreach ( (array) $captions as $key => $value ) {
			$id = absint( $key );
			if ( 0 === $id ) {
				continue;
			}
			$title = isset( $value['title'] ) ? sanitize_text_field( (string) $value['title'] ) : '';
			$desc  = isset( $value['desc'] ) ? sanitize_textarea_field( (string) $value['desc'] ) : '';
			if ( '' === $title && '' === $desc ) {
				continue;
			}
			$captions_clean[ $id ] = array(
				'title' => $title,
				'desc'  => $desc,
			);
		}
		update_post_meta(
			$gallery_id,
			self::META_CAPTIONS,
			wp_json_encode( $captions_clean, JSON_UNESCAPED_UNICODE )
		);

		update_post_meta(
			$gallery_id,
			self::META_CAPTION_BG,
			Sanitizer::hex_color( isset( $input['caption_bg'] ) ? (string) $input['caption_bg'] : self::DEFAULT_CAPTION_BG, self::DEFAULT_CAPTION_BG )
		);
		update_post_meta(
			$gallery_id,
			self::META_CAPTION_COLOR,
			Sanitizer::hex_color( isset( $input['caption_color'] ) ? (string) $input['caption_color'] : self::DEFAULT_CAPTION_COL, self::DEFAULT_CAPTION_COL )
		);
	}
}
