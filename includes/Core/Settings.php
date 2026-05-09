<?php
/**
 * Settings repository — global plugin options + per-attachment override.
 *
 * Data shape, defaults, validation rules and override merge semantics
 * follow aidha/03-data-model.md sections A.1 and B.3 verbatim.
 *
 * @package Pleaseup\WPImageHotspots\Core
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Core;

use Pleaseup\WPImageHotspots\Helpers\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the wphs_settings_v1 option and per-attachment
 * _wphs_img_settings_v1 meta.
 */
final class Settings {

	public const OPT_KEY        = 'wphs_settings_v1';
	public const META_IMG_KEY   = '_wphs_img_settings_v1';
	public const STYLE_DEFAULT  = 'default';
	public const STYLE_IMAGE    = 'image';

	/**
	 * Default settings values.
	 *
	 * @return array<string,int|string>
	 */
	public static function defaults() : array {
		$defaults = array(
			'hotspot_style'             => self::STYLE_DEFAULT,
			'hotspot_image_id'          => 0,
			'hotspot_selected_image_id' => 0,
			'hotspot_icon_size'         => 22,
			'dot_width'                 => 22,
			'dot_height'                => 22,
			'dot_radius'                => 999,
			'dot_color'                 => '#000000',
			'dot_border_color'          => '#ffffff',
			'dot_selected_fill'         => '#3fe0a0',
			'dot_selected_border'       => '#3fe0a0',
			'tooltip_bg'                => '#0b1220',
			'tooltip_color'             => '#ffffff',
			'tooltip_radius'            => 12,
			'tooltip_tail'              => 0,
		);

		/**
		 * Filter the default settings used as a baseline for both the
		 * global options and per-image overrides.
		 *
		 * @since 3.0.0
		 *
		 * @param array<string,int|string> $defaults Default values.
		 */
		return (array) apply_filters( 'wphs_default_settings', $defaults );
	}

	/**
	 * Read global plugin options merged on top of defaults.
	 *
	 * @return array<string,int|string>
	 */
	public function get_options() : array {
		$saved = get_option( self::OPT_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return $this->validate( array_merge( self::defaults(), $saved ) );
	}

	/**
	 * Save global plugin options after validation.
	 *
	 * @param array<string,mixed> $incoming Raw incoming values.
	 * @return array<string,int|string> Validated values that were stored.
	 */
	public function save_options( array $incoming ) : array {
		$merged    = array_merge( $this->get_options(), $incoming );
		$validated = $this->validate( $merged );
		update_option( self::OPT_KEY, $validated, false );
		return $validated;
	}

	/**
	 * Read per-attachment override merged on top of globals.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return array<string,int|string>
	 */
	public function get_image_settings( int $attachment_id ) : array {
		$globals = $this->get_options();
		if ( $attachment_id <= 0 ) {
			return $globals;
		}
		$raw = get_post_meta( $attachment_id, self::META_IMG_KEY, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return $globals;
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return $globals;
		}
		return $this->validate( array_merge( $globals, $decoded ) );
	}

	/**
	 * Save per-attachment override.
	 *
	 * @param int                  $attachment_id Attachment post ID.
	 * @param array<string,mixed>  $incoming      Raw incoming values.
	 * @return array<string,int|string>
	 */
	public function save_image_settings( int $attachment_id, array $incoming ) : array {
		$validated = $this->validate( array_merge( self::defaults(), $incoming ) );
		update_post_meta(
			$attachment_id,
			self::META_IMG_KEY,
			wp_json_encode( $validated, JSON_UNESCAPED_UNICODE )
		);
		return $validated;
	}

	/**
	 * Remove the per-attachment override (revert to globals).
	 *
	 * @param int $attachment_id Attachment post ID.
	 */
	public function delete_image_settings( int $attachment_id ) : void {
		delete_post_meta( $attachment_id, self::META_IMG_KEY );
	}

	/**
	 * Validate and clamp a settings array.
	 *
	 * @param array<string,mixed> $values Merged input.
	 * @return array<string,int|string>
	 */
	private function validate( array $values ) : array {
		$style = isset( $values['hotspot_style'] ) ? (string) $values['hotspot_style'] : self::STYLE_DEFAULT;
		if ( ! in_array( $style, array( self::STYLE_DEFAULT, self::STYLE_IMAGE ), true ) ) {
			$style = self::STYLE_DEFAULT;
		}

		$image_id     = isset( $values['hotspot_image_id'] ) ? absint( $values['hotspot_image_id'] ) : 0;
		$sel_image_id = isset( $values['hotspot_selected_image_id'] ) ? absint( $values['hotspot_selected_image_id'] ) : 0;

		// Verify referenced attachments actually exist as attachment posts.
		// Prevents storing arbitrary post IDs (info disclosure of private
		// or non-attachment URLs via the icon-preview endpoints).
		if ( $image_id > 0 && 'attachment' !== get_post_type( $image_id ) ) {
			$image_id = 0;
		}
		if ( $sel_image_id > 0 && 'attachment' !== get_post_type( $sel_image_id ) ) {
			$sel_image_id = 0;
		}

		if ( 0 === $image_id ) {
			$style = self::STYLE_DEFAULT;
		}

		$dot_width  = Sanitizer::clamp_int( isset( $values['dot_width'] ) ? (int) $values['dot_width'] : 22, 4, 256 );
		$dot_height = Sanitizer::clamp_int( isset( $values['dot_height'] ) ? (int) $values['dot_height'] : 22, 4, 256 );
		$max_radius = (int) floor( min( $dot_width, $dot_height ) / 2 );
		$dot_radius = isset( $values['dot_radius'] ) ? Sanitizer::clamp_int( (int) $values['dot_radius'], 0, $max_radius ) : $max_radius;

		return array(
			'hotspot_style'             => $style,
			'hotspot_image_id'          => $image_id,
			'hotspot_selected_image_id' => $sel_image_id,
			'hotspot_icon_size'         => Sanitizer::clamp_int( isset( $values['hotspot_icon_size'] ) ? (int) $values['hotspot_icon_size'] : 22, 8, 128 ),
			'dot_width'                 => $dot_width,
			'dot_height'                => $dot_height,
			'dot_radius'                => $dot_radius,
			'dot_color'                 => Sanitizer::hex_color( isset( $values['dot_color'] ) ? (string) $values['dot_color'] : '#000000', '#000000' ),
			'dot_border_color'          => Sanitizer::hex_color( isset( $values['dot_border_color'] ) ? (string) $values['dot_border_color'] : '#ffffff', '#ffffff' ),
			'dot_selected_fill'         => Sanitizer::hex_color( isset( $values['dot_selected_fill'] ) ? (string) $values['dot_selected_fill'] : '#3fe0a0', '#3fe0a0' ),
			'dot_selected_border'       => Sanitizer::hex_color( isset( $values['dot_selected_border'] ) ? (string) $values['dot_selected_border'] : '#3fe0a0', '#3fe0a0' ),
			'tooltip_bg'                => Sanitizer::hex_color( isset( $values['tooltip_bg'] ) ? (string) $values['tooltip_bg'] : '#0b1220', '#0b1220' ),
			'tooltip_color'             => Sanitizer::hex_color( isset( $values['tooltip_color'] ) ? (string) $values['tooltip_color'] : '#ffffff', '#ffffff' ),
			'tooltip_radius'            => Sanitizer::clamp_int( isset( $values['tooltip_radius'] ) ? (int) $values['tooltip_radius'] : 12, 0, 32 ),
			'tooltip_tail'              => 0,
		);
	}
}
