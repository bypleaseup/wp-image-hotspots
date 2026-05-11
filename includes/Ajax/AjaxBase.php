<?php
/**
 * Base class for AJAX modules.
 *
 * Centralizes the nonce verification policy so every endpoint uses the
 * same nonce name (`wphs_nonce_v1`), closing a long-standing dual-nonce
 * inconsistency in the legacy code.
 *
 * @package Pleaseup\WPImageHotspots\Ajax
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Ajax;

defined( 'ABSPATH' ) || exit;

/**
 * Common AJAX helpers shared by every concrete module.
 */
abstract class AjaxBase {

	public const NONCE_ACTION = 'wphs_nonce_v1';
	public const NONCE_FIELD  = 'nonce';

	/**
	 * Build a fresh nonce that the JS layer can attach to AJAX requests.
	 */
	public static function create_nonce() : string {
		return wp_create_nonce( self::NONCE_ACTION );
	}

	/**
	 * Verify the request nonce or send a 403 JSON error and exit.
	 *
	 * Order matters: nonce is checked BEFORE capability so the response
	 * code does not leak the existence of the resource to unauthenticated
	 * callers (closes baseline issue: `ajax_save_settings` and friends
	 * checked capability first).
	 */
	protected function verify_nonce_or_die() : void {
		$nonce = isset( $_POST[ self::NONCE_FIELD ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) )
			: '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Bad nonce', 'wp-image-hotspots' ) ),
				403
			);
		}
	}

	/**
	 * Send a 403 JSON error and exit when the predicate is false.
	 *
	 * @param bool   $allowed Capability check result.
	 * @param string $message Optional message override.
	 */
	protected function require_capability( bool $allowed, string $message = '' ) : void {
		if ( $allowed ) {
			return;
		}
		wp_send_json_error(
			array( 'message' => '' === $message ? __( 'Forbidden', 'wp-image-hotspots' ) : $message ),
			403
		);
	}

	/**
	 * Read an integer field from POST.
	 *
	 * @param string $field Field name.
	 * @return int Sanitized value, 0 when missing.
	 */
	protected function post_int( string $field ) : int {
		return isset( $_POST[ $field ] ) ? absint( $_POST[ $field ] ) : 0;
	}

	/**
	 * Read a string field from POST applying wp_unslash + the supplied
	 * sanitizer callable (defaults to sanitize_text_field).
	 *
	 * @param string        $field    Field name.
	 * @param callable|null $sanitize Sanitizer callable.
	 * @return string
	 */
	protected function post_string( string $field, ?callable $sanitize = null ) : string {
		if ( ! isset( $_POST[ $field ] ) ) {
			return '';
		}
		$value = wp_unslash( (string) $_POST[ $field ] );
		if ( null === $sanitize ) {
			$sanitize = 'sanitize_text_field';
		}
		return (string) call_user_func( $sanitize, $value );
	}
}
