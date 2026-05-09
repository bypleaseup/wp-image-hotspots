<?php
/**
 * Capability check helpers.
 *
 * Centralizes capability resolution so AJAX handlers, admin pages and
 * shortcodes share the same authorization rules. Hardens the four
 * baseline gaps documented in aidha/05-quality-gates.md section C.4
 * by giving each gap a dedicated helper that the rewritten endpoints
 * must call.
 *
 * @package Pleaseup\WPImageHotspots\Core
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Authorization helpers — all checks return bool; callers are
 * responsible for short-circuiting (e.g. wp_send_json_error / wp_die).
 */
final class Capabilities {

	/**
	 * Can the current user edit hotspot data on a specific attachment?
	 *
	 * Mirrors the legacy WPHS_Hotspots_Drag_Drop::can_edit_attachment()
	 * helper: gates per-attachment permissions through the WordPress
	 * `edit_post` capability map, which honors custom capability
	 * filters (e.g. ACF, role plugins) automatically.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return bool
	 */
	public static function can_edit_attachment( int $attachment_id ) : bool {
		if ( $attachment_id <= 0 ) {
			return false;
		}
		return current_user_can( 'edit_post', $attachment_id );
	}

	/**
	 * Can the current user manage plugin-wide settings and galleries?
	 *
	 * @return bool
	 */
	public static function can_manage_plugin() : bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Can the current user upload media (proxy for "can use the editor
	 * to add hotspots and tooltips")?
	 *
	 * @return bool
	 */
	public static function can_upload_files() : bool {
		return current_user_can( 'upload_files' );
	}

	/**
	 * Can the current user resolve an oEmbed server-side?
	 *
	 * Closes baseline gap #2 (aidha/05-quality-gates.md C.4): the
	 * legacy ajax_resolve_oembed had only a nonce. The rewrite gates
	 * this to authors and above (`edit_posts`).
	 *
	 * @return bool
	 */
	public static function can_resolve_oembed() : bool {
		return current_user_can( 'edit_posts' );
	}
}
