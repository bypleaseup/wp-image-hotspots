<?php
/**
 * Unit tests for Pleaseup\WPImageHotspots\Helpers\OembedResolver.
 *
 * @package Pleaseup\WPImageHotspots\Tests
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Tests\Unit\Helpers;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Pleaseup\WPImageHotspots\Helpers\OembedResolver;

final class OembedResolverTest extends TestCase {

	private OembedResolver $resolver;

	protected function setUp() : void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		$this->resolver = new OembedResolver();
	}

	protected function tearDown() : void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_default_providers_includes_all_supported_keys() : void {
		$providers = OembedResolver::default_providers();
		self::assertArrayHasKey( 'youtube', $providers );
		self::assertArrayHasKey( 'youtu', $providers );
		self::assertArrayHasKey( 'vimeo', $providers );
		self::assertArrayHasKey( 'spotify', $providers );
	}

	public function test_resolve_returns_empty_input_unchanged() : void {
		self::assertSame( '', $this->resolver->resolve( '' ) );
	}

	public function test_resolve_passes_text_without_known_urls_through() : void {
		Functions\when( 'wp_oembed_get' )->justReturn( '' );

		$input = 'Just plain text with https://example.com/foo no oEmbed match.';
		self::assertSame( $input, $this->resolver->resolve( $input ) );
	}

	public function test_resolve_wraps_youtube_match_with_oembed_div() : void {
		Functions\when( 'wp_oembed_get' )->alias(
			static function ( string $url ) {
				return '<iframe src="https://youtube.com/embed/abc123"></iframe>';
			}
		);

		$input  = 'Hi https://www.youtube.com/watch?v=abc123 there';
		$output = $this->resolver->resolve( $input );

		self::assertStringContainsString( '<div class="wphs-oembed-wrap"', $output );
		self::assertStringContainsString( 'iframe', $output );
		self::assertStringContainsString( 'modestbranding=1&rel=0', $output );
	}

	public function test_resolve_keeps_url_when_oembed_returns_empty() : void {
		Functions\when( 'wp_oembed_get' )->justReturn( '' );

		$input = 'Watch https://vimeo.com/12345 now';
		self::assertSame( $input, $this->resolver->resolve( $input ) );
	}

	public function test_resolve_appends_youtube_params_only_to_youtube_embeds() : void {
		Functions\when( 'wp_oembed_get' )->alias(
			static function ( string $url ) {
				if ( strpos( $url, 'vimeo.com' ) !== false ) {
					return '<iframe src="https://player.vimeo.com/video/12345"></iframe>';
				}
				return '';
			}
		);

		$output = $this->resolver->resolve( 'https://vimeo.com/12345' );
		self::assertStringContainsString( 'wphs-oembed-wrap', $output );
		self::assertStringNotContainsString( 'modestbranding=1', $output );
	}

	public function test_resolve_respects_filter_overriding_providers() : void {
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				if ( 'wphs_oembed_providers' === $hook ) {
					return array(); // disable all providers
				}
				return $value;
			}
		);

		$input = 'Watch https://www.youtube.com/watch?v=abc123 now';
		self::assertSame( $input, $this->resolver->resolve( $input ) );
	}
}
