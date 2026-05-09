<?php
/**
 * Deactivation hook handler.
 *
 * @package Pleaseup\WPImageHotspots
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots;

defined( 'ABSPATH' ) || exit;

/**
 * Runs once on plugin deactivation. Intentionally does NOT remove user
 * data — that responsibility belongs to uninstall.php.
 */
final class Deactivator {

	/**
	 * Deactivation entry point.
	 */
	public static function deactivate() : void {
		flush_rewrite_rules( false );
	}
}
