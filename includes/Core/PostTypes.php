<?php
/**
 * Custom post type registration.
 *
 * The legacy `wphs_tooltip` CPT was used via wp_insert_post() without
 * being registered. The rewrite registers both `wphs_gallery` and
 * `wphs_tooltip` explicitly on the `init` hook with `public => false`
 * so they are predictable to operators inspecting the database.
 *
 * @package Pleaseup\WPImageHotspots\Core
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's custom post types.
 */
final class PostTypes {

	public const TOOLTIP = 'wphs_tooltip';
	public const GALLERY = 'wphs_gallery';

	/**
	 * Hook the registration onto WordPress init.
	 */
	public function register() : void {
		add_action( 'init', array( $this, 'register_post_types' ), 5 );
	}

	/**
	 * Register both internal post types.
	 */
	public function register_post_types() : void {
		register_post_type(
			self::GALLERY,
			array(
				'labels'              => array(
					'name'          => __( 'Hotspot Galleries', 'wp-image-hotspots' ),
					'singular_name' => __( 'Hotspot Gallery', 'wp-image-hotspots' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);

		register_post_type(
			self::TOOLTIP,
			array(
				'labels'              => array(
					'name'          => __( 'Hotspot Tooltips', 'wp-image-hotspots' ),
					'singular_name' => __( 'Hotspot Tooltip', 'wp-image-hotspots' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'editor' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}
}
