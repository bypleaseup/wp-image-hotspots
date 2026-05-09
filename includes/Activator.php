<?php
/**
 * Activation hook handler.
 *
 * @package Pleaseup\WPImageHotspots
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots;

use Pleaseup\WPImageHotspots\Core\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Runs once on plugin activation. Keeps things minimal: registers the
 * CPTs once and flushes rewrite rules so any future archive routes are
 * picked up cleanly.
 */
final class Activator {

	/**
	 * Activation entry point.
	 */
	public static function activate() : void {
		( new PostTypes() )->register_post_types();
		flush_rewrite_rules( false );
	}
}
