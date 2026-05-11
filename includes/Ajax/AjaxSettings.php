<?php
/**
 * AJAX endpoints for global plugin settings and per-image overrides.
 *
 * @package Pleaseup\WPImageHotspots\Ajax
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Ajax;

use Pleaseup\WPImageHotspots\Core\Capabilities;
use Pleaseup\WPImageHotspots\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the three settings endpoints.
 */
final class AjaxSettings extends AjaxBase {

	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function register() : void {
		add_action( 'wp_ajax_wphs_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_wphs_save_img_settings', array( $this, 'save_image_settings' ) );
		add_action( 'wp_ajax_wphs_get_img_settings', array( $this, 'get_image_settings' ) );
	}

	/**
	 * SAVE global plugin options.
	 */
	public function save_settings() : void {
		$this->verify_nonce_or_die();
		$this->require_capability( Capabilities::can_manage_plugin() );

		$saved = $this->settings->save_options( $this->collect_input() );
		$icon  = $saved['hotspot_image_id']
			? wp_get_attachment_image_url( (int) $saved['hotspot_image_id'], 'thumbnail' )
			: '';

		wp_send_json_success(
			array(
				'options'  => $saved,
				'icon_url' => $icon ? $icon : '',
				'message'  => __( 'Settings saved', 'image-hotspots-tool' ),
			)
		);
	}

	/**
	 * SAVE per-attachment override (or revert to globals when use_default=1).
	 */
	public function save_image_settings() : void {
		$this->verify_nonce_or_die();
		$attachment_id = $this->post_int( 'attachment_id' );
		$this->require_capability( Capabilities::can_edit_attachment( $attachment_id ) );

		if ( ! empty( $_POST['use_default'] ) ) {
			$this->settings->delete_image_settings( $attachment_id );
			$current = $this->settings->get_image_settings( $attachment_id );
			$current['_use_default_flag'] = true;
			wp_send_json_success(
				array(
					'message'  => __( 'Reverted to global settings', 'image-hotspots-tool' ),
					'settings' => $current,
				)
			);
		}

		$saved    = $this->settings->save_image_settings( $attachment_id, $this->collect_input() );
		$icon_url = $saved['hotspot_image_id']
			? wp_get_attachment_image_url( (int) $saved['hotspot_image_id'], 'thumbnail' )
			: '';
		$sel_url  = $saved['hotspot_selected_image_id']
			? wp_get_attachment_image_url( (int) $saved['hotspot_selected_image_id'], 'thumbnail' )
			: '';

		$saved['_use_default_flag'] = false;

		wp_send_json_success(
			array(
				'message'      => __( 'Saved', 'image-hotspots-tool' ),
				'settings'     => $saved,
				'icon_url'     => $icon_url ? $icon_url : '',
				'sel_icon_url' => $sel_url ? $sel_url : '',
			)
		);
	}

	/**
	 * GET per-attachment merged settings.
	 */
	public function get_image_settings() : void {
		$this->verify_nonce_or_die();
		$attachment_id = $this->post_int( 'attachment_id' );
		$this->require_capability( Capabilities::can_edit_attachment( $attachment_id ) );

		$settings = $this->settings->get_image_settings( $attachment_id );
		$settings['_use_default_flag'] = false;

		$icon_url = $settings['hotspot_image_id']
			? wp_get_attachment_image_url( (int) $settings['hotspot_image_id'], 'thumbnail' )
			: '';
		$sel_url  = $settings['hotspot_selected_image_id']
			? wp_get_attachment_image_url( (int) $settings['hotspot_selected_image_id'], 'thumbnail' )
			: '';

		wp_send_json_success(
			array(
				'settings'     => $settings,
				'icon_url'     => $icon_url ? $icon_url : '',
				'sel_icon_url' => $sel_url ? $sel_url : '',
			)
		);
	}

	/**
	 * Read every settings field from POST without any sanitization
	 * (the repository validates everything before persisting).
	 *
	 * @return array<string,mixed>
	 */
	private function collect_input() : array {
		$keys = array(
			'hotspot_style',
			'hotspot_image_id',
			'hotspot_selected_image_id',
			'hotspot_icon_size',
			'dot_width',
			'dot_height',
			'dot_radius',
			'dot_color',
			'dot_border_color',
			'dot_selected_fill',
			'dot_selected_border',
			'tooltip_bg',
			'tooltip_color',
			'tooltip_radius',
			'tooltip_tail',
		);

		$out = array();
		foreach ( $keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$out[ $key ] = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			}
		}
		return $out;
	}
}
