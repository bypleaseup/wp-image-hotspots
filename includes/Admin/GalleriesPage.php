<?php
/**
 * Galleries admin page renderer.
 *
 * @package Pleaseup\WPImageHotspots\Admin
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Admin;

use Pleaseup\WPImageHotspots\Ajax\AjaxBase;
use Pleaseup\WPImageHotspots\Core\GalleryRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Prepares data for the galleries template and includes the partial.
 */
final class GalleriesPage {

	private GalleryRepository $galleries;

	public function __construct( GalleryRepository $galleries ) {
		$this->galleries = $galleries;
	}

	/**
	 * Render the galleries page.
	 */
	public function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$galleries = $this->galleries->get_all();
		$nonce     = AjaxBase::create_nonce();
		$ajaxurl   = admin_url( 'admin-ajax.php' );

		require WPHS_PLUGIN_DIR . 'includes/Admin/partials/galleries-page.php';
	}
}
