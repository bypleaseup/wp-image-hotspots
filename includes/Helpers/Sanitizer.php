<?php
/**
 * Sanitization helpers used across the plugin.
 *
 * @package Pleaseup\WPImageHotspots\Helpers
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitization primitives reused by repositories, AJAX handlers, and
 * shortcodes. Stateless static helpers — no side effects.
 */
final class Sanitizer {

	/**
	 * Validate a CSS hex color in the form #rgb or #rrggbb.
	 *
	 * Differs from WordPress' sanitize_hex_color() in that it always
	 * returns a string (never null) and falls back to the supplied
	 * default when the input does not match.
	 *
	 * @param string $value   Raw input.
	 * @param string $default Fallback when validation fails.
	 * @return string Sanitized hex color (lowercased).
	 */
	public static function hex_color( string $value, string $default = '#000000' ) : string {
		$value = strtolower( trim( $value ) );
		if ( 1 === preg_match( '/^#([a-f0-9]{6}|[a-f0-9]{3})$/', $value ) ) {
			return $value;
		}
		return $default;
	}

	/**
	 * Decode "\\uXXXX" escape sequences (BMP only) to their UTF-8
	 * counterparts. Mitigates wp_json_encode() output without
	 * JSON_UNESCAPED_UNICODE that would otherwise leak literal
	 * "\\u00e8" instead of "è" in the rendered HTML.
	 *
	 * @param string $value Raw HTML/text potentially containing escapes.
	 * @return string Decoded text.
	 */
	public static function decode_unicode_escapes( string $value ) : string {
		if ( false === strpos( $value, '\\u' ) ) {
			return $value;
		}
		return (string) preg_replace_callback(
			'/\\\\u([0-9a-fA-F]{4})/',
			static function ( array $matches ) : string {
				$code = hexdec( $matches[1] );
				return mb_convert_encoding( pack( 'n', $code ), 'UTF-8', 'UTF-16BE' );
			},
			$value
		);
	}

	/**
	 * Clamp an integer between min and max inclusive.
	 *
	 * @param int $value Raw input.
	 * @param int $min   Lower bound.
	 * @param int $max   Upper bound.
	 * @return int
	 */
	public static function clamp_int( int $value, int $min, int $max ) : int {
		if ( $value < $min ) {
			return $min;
		}
		if ( $value > $max ) {
			return $max;
		}
		return $value;
	}

	/**
	 * Clamp a float between min and max inclusive.
	 *
	 * @param float $value Raw input.
	 * @param float $min   Lower bound.
	 * @param float $max   Upper bound.
	 * @return float
	 */
	public static function clamp_float( float $value, float $min, float $max ) : float {
		if ( $value < $min ) {
			return $min;
		}
		if ( $value > $max ) {
			return $max;
		}
		return $value;
	}

	/**
	 * Allowed iframe attributes for tooltip HTML, used to extend the
	 * default wp_kses_post() allowlist so that oEmbed players survive.
	 *
	 * The `src` URL is *not* validated by wp_kses (which doesn't support
	 * callable validators on attribute values). Host validation is
	 * performed earlier in {@see kses_tooltip_html()}, which strips any
	 * `<iframe>` whose src host is not in the allowlist before reaching
	 * wp_kses.
	 *
	 * Filterable via `wphs_allowed_iframe_attrs` (full tag/attribute
	 * structure) and `wphs_allowed_iframe_hosts` (host allowlist only).
	 *
	 * @return array<string,array<string,bool>>
	 */
	public static function allowed_iframe_kses() : array {
		$default = array(
			'iframe' => array(
				'src'             => true,
				'width'           => true,
				'height'          => true,
				'frameborder'     => true,
				'allow'           => true,
				'allowfullscreen' => true,
				'loading'         => true,
				'referrerpolicy'  => true,
				'title'           => true,
				'class'           => true,
			),
		);

		/**
		 * Filter the list of iframe attributes preserved by wp_kses
		 * when sanitizing tooltip HTML containing oEmbed players.
		 *
		 * @since 3.0.0
		 *
		 * @param array<string,array<string,bool>> $allowed Allowed tags/attributes.
		 */
		return (array) apply_filters( 'wphs_allowed_iframe_attrs', $default );
	}

	/**
	 * Default host allowlist for iframe `src` (oEmbed providers).
	 *
	 * @return array<int,string>
	 */
	public static function allowed_iframe_hosts() : array {
		$default_hosts = array(
			'www.youtube.com',
			'youtube.com',
			'youtube-nocookie.com',
			'www.youtube-nocookie.com',
			'player.vimeo.com',
			'vimeo.com',
			'open.spotify.com',
			'w.soundcloud.com',
		);

		/**
		 * Filter the host allowlist for iframe src attributes inside
		 * tooltip HTML. Hosts must match exactly; subdomains are not
		 * implicitly granted.
		 *
		 * @since 3.0.0
		 *
		 * @param array<int,string> $hosts Lowercase host names.
		 */
		return array_map( 'strtolower', (array) apply_filters( 'wphs_allowed_iframe_hosts', $default_hosts ) );
	}

	/**
	 * True when an iframe `src` URL points at a host in the allowlist
	 * and uses http/https scheme.
	 *
	 * @param string $value Raw src URL.
	 */
	public static function is_allowed_iframe_src( string $value ) : bool {
		$value = trim( $value );
		if ( '' === $value ) {
			return false;
		}
		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return false;
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		if ( 'https' !== $scheme && 'http' !== $scheme ) {
			return false;
		}
		$host = strtolower( (string) $parts['host'] );
		return in_array( $host, self::allowed_iframe_hosts(), true );
	}

	/**
	 * Apply wp_kses_post() with the iframe extension required by oEmbed.
	 *
	 * Pre-processes the input to drop any `<iframe>` whose `src` host is
	 * not in {@see allowed_iframe_hosts()}, since wp_kses cannot validate
	 * URLs by host. Then runs wp_kses with the standard post allowlist
	 * extended by the iframe attributes.
	 *
	 * @param string $html Raw HTML.
	 * @return string Sanitized HTML.
	 */
	public static function kses_tooltip_html( string $html ) : string {
		if ( false !== stripos( $html, '<iframe' ) ) {
			$html = self::filter_iframes_by_host( $html );
		}
		$allowed = array_merge( wp_kses_allowed_html( 'post' ), self::allowed_iframe_kses() );
		return wp_kses( $html, $allowed );
	}

	/**
	 * Strip `<iframe>` tags whose `src` host is not in the allowlist.
	 *
	 * @param string $html Raw HTML.
	 * @return string HTML with disallowed iframes removed.
	 */
	private static function filter_iframes_by_host( string $html ) : string {
		$callback = static function ( array $match ) : string {
			if ( ! preg_match( '/\bsrc\s*=\s*(["\'])(.*?)\1/i', $match[0], $src_match ) ) {
				return '';
			}
			$url = html_entity_decode( $src_match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			return self::is_allowed_iframe_src( $url ) ? $match[0] : '';
		};

		// Paired tags: <iframe …>…</iframe>.
		$html = (string) preg_replace_callback(
			'#<iframe\b[^>]*>.*?</iframe>#is',
			$callback,
			$html
		);
		// Self-closing or unclosed iframes (rare but possible after
		// editor mangling). Match a single tag without inner content.
		$html = (string) preg_replace_callback(
			'#<iframe\b[^>]*?/?>#i',
			$callback,
			$html
		);
		// Remove any orphan </iframe> that survived the strip.
		$html = (string) preg_replace( '#</iframe>#i', '', $html );

		return $html;
	}
}
