<?php
/**
 * Server-side oEmbed resolver for tooltip HTML.
 *
 * Mirrors the legacy WPHS_Hotspots_Drag_Drop::resolve_oembed() behavior
 * but exposes the provider list through the `wphs_oembed_providers`
 * filter (closes aidha/04-constraints.md F.2 point 5).
 *
 * @package Pleaseup\WPImageHotspots\Helpers
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Detects YouTube, Vimeo and Spotify URLs in a string and replaces
 * them with the embed markup returned by `wp_oembed_get()`.
 */
final class OembedResolver {

	/**
	 * Default provider patterns. Each value is a regex that matches a
	 * single URL.
	 *
	 * @return array<string,string>
	 */
	public static function default_providers() : array {
		return array(
			'youtube' => '/https?:\/\/(?:www\.)?youtube\.com\/watch\?[a-zA-Z0-9=&_\-]+/i',
			'youtu'   => '/https?:\/\/youtu\.be\/[a-zA-Z0-9_\-]+(\?[a-zA-Z0-9=&_\-]*)?/i',
			'vimeo'   => '/https?:\/\/(?:www\.)?vimeo\.com\/[0-9]+(?:[?&][^\s<>"\']*)?/i',
			'spotify' => '/https?:\/\/open\.spotify\.com\/(?:track|album|playlist|episode|show)\/[a-zA-Z0-9]+(\?[^\s<>"\']*)?/i',
		);
	}

	/**
	 * Replace recognised oEmbed URLs in a HTML string with their embed
	 * markup wrapped in `.wphs-oembed-wrap`.
	 *
	 * @param string $html Raw tooltip HTML.
	 * @return string HTML with embeds inlined.
	 */
	public function resolve( string $html ) : string {
		if ( '' === $html ) {
			return $html;
		}

		/**
		 * Filter the regex patterns used to detect oEmbed URLs.
		 *
		 * Return an empty array to disable server-side oEmbed
		 * resolution entirely.
		 *
		 * @since 3.0.0
		 *
		 * @param array<string,string> $providers Regex patterns keyed by short id.
		 */
		$providers = (array) apply_filters( 'wphs_oembed_providers', self::default_providers() );

		foreach ( $providers as $pattern ) {
			$html = (string) preg_replace_callback(
				$pattern,
				static function ( array $matches ) : string {
					$embed = wp_oembed_get( $matches[0], array( 'width' => 480 ) );
					if ( ! is_string( $embed ) || '' === $embed ) {
						return $matches[0];
					}
					$embed = preg_replace_callback(
						"/src=([\"'])(https?:\\/\\/(?:www\\.)?youtube(?:-nocookie)?\\.com\\/embed\\/[^\"']+)([\"'])/i",
						static function ( array $m ) : string {
							$sep = ( false !== strpos( $m[2], '?' ) ) ? '&' : '?';
							return 'src=' . $m[1] . $m[2] . $sep . 'modestbranding=1&rel=0' . $m[3];
						},
						$embed
					);
					return '<div class="wphs-oembed-wrap" style="position:relative;width:100%;aspect-ratio:16/9">' . $embed . '</div>';
				},
				$html
			);
		}

		return $html;
	}
}
