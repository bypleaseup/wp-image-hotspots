<?php
/**
 * Uninstall handler for WP Image Hotspots.
 *
 * Removes every option, post meta, and custom post type the plugin
 * created. On multisite the cleanup runs once per site of the network.
 *
 * @package Pleaseup\WPImageHotspots
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Wipe plugin data on the current site (single-site path or one site
 * inside a multisite loop).
 */
function wphs_uninstall_cleanup_current_site() : void {
	delete_option( 'wphs_settings_v1' );

	delete_post_meta_by_key( '_wphs_hotspots_v1' );
	delete_post_meta_by_key( '_wphs_hotspots_saved_at_v1' );
	delete_post_meta_by_key( '_wphs_img_settings_v1' );

	delete_post_meta_by_key( '_wphs_gallery_ids' );
	delete_post_meta_by_key( '_wphs_gallery_nav' );
	delete_post_meta_by_key( '_wphs_gallery_cols_desktop' );
	delete_post_meta_by_key( '_wphs_gallery_cols_mobile' );
	delete_post_meta_by_key( '_wphs_gallery_img_radius' );
	delete_post_meta_by_key( '_wphs_gallery_captions' );
	delete_post_meta_by_key( '_wphs_gallery_caption_bg' );
	delete_post_meta_by_key( '_wphs_gallery_caption_color' );

	foreach ( array( 'wphs_gallery', 'wphs_tooltip' ) as $wphs_post_type ) {
		$wphs_post_ids = get_posts(
			array(
				'post_type'        => $wphs_post_type,
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);

		foreach ( $wphs_post_ids as $wphs_post_id ) {
			wp_delete_post( (int) $wphs_post_id, true );
		}
	}
}

if ( is_multisite() ) {
	$wphs_sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $wphs_sites as $wphs_site_id ) {
		switch_to_blog( (int) $wphs_site_id );
		wphs_uninstall_cleanup_current_site();
		restore_current_blog();
	}
	unset( $wphs_sites, $wphs_site_id );
} else {
	wphs_uninstall_cleanup_current_site();
}
