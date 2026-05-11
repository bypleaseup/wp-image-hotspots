<?php
/**
 * Admin bootstrap.
 *
 * Wires the admin menu, the asset enqueue layer and the page renderers.
 * Admin classes never touch frontend code.
 *
 * @package Pleaseup\WPImageHotspots\Admin
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Admin;

use Pleaseup\WPImageHotspots\Core\GalleryRepository;
use Pleaseup\WPImageHotspots\Core\HotspotRepository;
use Pleaseup\WPImageHotspots\Core\Settings;
use Pleaseup\WPImageHotspots\Core\TooltipRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Composes the Admin\Assets, EditorPage and GalleriesPage instances and
 * registers their WordPress hooks.
 */
final class Admin {

	private Assets $assets;
	private EditorPage $editor_page;
	private GalleriesPage $galleries_page;

	public function __construct(
		Settings $settings,
		HotspotRepository $hotspots,
		TooltipRepository $tooltips,
		GalleryRepository $galleries
	) {
		$this->editor_page    = new EditorPage( $settings, $hotspots, $tooltips );
		$this->galleries_page = new GalleriesPage( $galleries );
		$this->assets         = new Assets( $settings, $galleries );
	}

	/**
	 * Hook the admin module onto WordPress.
	 */
	public function register() : void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->assets, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WPHS_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Register the WP Hotspots top-level menu and its two submenus.
	 */
	public function register_menu() : void {
		add_menu_page(
			__( 'WP Hotspots', 'image-hotspots-tool' ),
			__( 'WP Hotspots', 'image-hotspots-tool' ),
			'manage_options',
			'wphs-editor',
			array( $this->editor_page, 'render' ),
			'dashicons-location-alt',
			58
		);

		add_submenu_page(
			'wphs-editor',
			__( 'Editor Hotspots', 'image-hotspots-tool' ),
			__( 'Editor', 'image-hotspots-tool' ),
			'manage_options',
			'wphs-editor',
			array( $this->editor_page, 'render' )
		);

		add_submenu_page(
			'wphs-editor',
			__( 'Galleries', 'image-hotspots-tool' ),
			__( 'Galleries', 'image-hotspots-tool' ),
			'manage_options',
			'wphs-galleries',
			array( $this->galleries_page, 'render' )
		);
	}

	/**
	 * Add an "Editor" link on the plugin row in /wp-admin/plugins.php.
	 *
	 * @param array<int,string> $links Existing links.
	 * @return array<int,string>
	 */
	public function plugin_action_links( array $links ) : array {
		$editor = admin_url( 'admin.php?page=wphs-editor' );
		$custom = array(
			'<a href="' . esc_url( $editor ) . '">' . esc_html__( 'Editor', 'image-hotspots-tool' ) . '</a>',
		);
		return array_merge( $custom, $links );
	}
}
