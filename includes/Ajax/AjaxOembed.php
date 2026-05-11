<?php
/**
 * AJAX endpoint that resolves oEmbed URLs in tooltip HTML.
 *
 * Closes a legacy capability gap by gating the endpoint behind
 * `edit_posts` so subscribers cannot trigger server-side oEmbed lookups
 * (mitigates SSRF surface even though the underlying wp_oembed_get()
 * already enforces a provider whitelist).
 *
 * @package Pleaseup\WPImageHotspots\Ajax
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Ajax;

use Pleaseup\WPImageHotspots\Core\Capabilities;
use Pleaseup\WPImageHotspots\Helpers\OembedResolver;
use Pleaseup\WPImageHotspots\Helpers\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Single endpoint: wp_ajax_wphs_resolve_oembed.
 */
final class AjaxOembed extends AjaxBase {

	private OembedResolver $resolver;

	public function __construct( OembedResolver $resolver ) {
		$this->resolver = $resolver;
	}

	public function register() : void {
		add_action( 'wp_ajax_wphs_resolve_oembed', array( $this, 'resolve' ) );
	}

	/**
	 * Resolve oEmbed URLs server-side and return the rewritten HTML.
	 */
	public function resolve() : void {
		$this->verify_nonce_or_die();
		$this->require_capability( Capabilities::can_resolve_oembed() );

		$raw      = isset( $_POST['html'] )
			? Sanitizer::kses_tooltip_html( wp_unslash( (string) $_POST['html'] ) )
			: '';
		$decoded  = Sanitizer::decode_unicode_escapes( $raw );
		$resolved = $this->resolver->resolve( $decoded );

		wp_send_json_success( array( 'html' => $resolved ) );
	}
}
