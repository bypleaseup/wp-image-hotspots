<?php
/**
 * Unit tests for Pleaseup\WPImageHotspots\Helpers\Sanitizer.
 *
 * @package Pleaseup\WPImageHotspots\Tests
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Tests\Unit\Helpers;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Pleaseup\WPImageHotspots\Helpers\Sanitizer;

final class SanitizerTest extends TestCase {

	protected function setUp() : void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown() : void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @dataProvider hex_color_cases
	 */
	public function test_hex_color( string $input, string $default, string $expected ) : void {
		self::assertSame( $expected, Sanitizer::hex_color( $input, $default ) );
	}

	public function hex_color_cases() : array {
		return array(
			'six-digit lowercase'   => array( '#abcdef', '#000000', '#abcdef' ),
			'six-digit uppercase'   => array( '#ABCDEF', '#000000', '#abcdef' ),
			'three-digit shorthand' => array( '#abc', '#000000', '#abc' ),
			'whitespace trimmed'    => array( '  #112233  ', '#000000', '#112233' ),
			'missing hash'          => array( 'aabbcc', '#defaultx', '#defaultx' ),
			'too long'              => array( '#abcdefg', '#fafafa', '#fafafa' ),
			'random text'           => array( 'not-a-color', '#fafafa', '#fafafa' ),
			'empty falls back'      => array( '', '#999999', '#999999' ),
		);
	}

	public function test_decode_unicode_escapes_passes_plain_strings_through() : void {
		self::assertSame( 'plain text', Sanitizer::decode_unicode_escapes( 'plain text' ) );
		self::assertSame( '', Sanitizer::decode_unicode_escapes( '' ) );
	}

	public function test_decode_unicode_escapes_decodes_bmp_codepoints() : void {
		// "è" → "è", "à" → "à"
		$input    = 'Caff\\u00e8 \\u00e0 Roma';
		$expected = 'Caffè à Roma';
		self::assertSame( $expected, Sanitizer::decode_unicode_escapes( $input ) );
	}

	public function test_decode_unicode_escapes_decodes_three_byte_codepoints() : void {
		// "€" → "€"
		self::assertSame( 'price: €99', Sanitizer::decode_unicode_escapes( 'price: \\u20ac99' ) );
	}

	public function test_clamp_int_within_range() : void {
		self::assertSame( 5, Sanitizer::clamp_int( 5, 0, 10 ) );
	}

	public function test_clamp_int_below_min() : void {
		self::assertSame( 0, Sanitizer::clamp_int( -3, 0, 10 ) );
	}

	public function test_clamp_int_above_max() : void {
		self::assertSame( 10, Sanitizer::clamp_int( 99, 0, 10 ) );
	}

	public function test_clamp_float_within_range() : void {
		self::assertSame( 1.5, Sanitizer::clamp_float( 1.5, 0.0, 10.0 ) );
	}

	public function test_clamp_float_below_min() : void {
		self::assertSame( 0.0, Sanitizer::clamp_float( -2.5, 0.0, 10.0 ) );
	}

	public function test_clamp_float_above_max() : void {
		self::assertSame( 10.0, Sanitizer::clamp_float( 12.5, 0.0, 10.0 ) );
	}

	public function test_allowed_iframe_kses_returns_iframe_whitelist() : void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$allowed = Sanitizer::allowed_iframe_kses();

		self::assertArrayHasKey( 'iframe', $allowed );
		self::assertArrayHasKey( 'src', $allowed['iframe'] );
		self::assertArrayHasKey( 'allow', $allowed['iframe'] );
		self::assertArrayHasKey( 'allowfullscreen', $allowed['iframe'] );
	}

	public function test_allowed_iframe_kses_is_filterable() : void {
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				if ( 'wphs_allowed_iframe_attrs' === $hook ) {
					return array( 'iframe' => array( 'src' => true ) );
				}
				return $value;
			}
		);

		$allowed = Sanitizer::allowed_iframe_kses();
		self::assertSame( array( 'iframe' => array( 'src' => true ) ), $allowed );
	}

	public function test_kses_tooltip_html_extends_post_allowlist_with_iframe() : void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\expect( 'wp_kses_allowed_html' )->once()->with( 'post' )->andReturn( array( 'p' => array() ) );
		Functions\expect( 'wp_kses' )->once()->andReturnUsing(
			static function ( $html, $allowed ) {
				// Return the merged allowlist as JSON so we can assert on it.
				return json_encode( array_keys( $allowed ) );
			}
		);

		$result  = Sanitizer::kses_tooltip_html( '<p>x</p>' );
		$decoded = json_decode( $result, true );

		self::assertContains( 'p', $decoded );
		self::assertContains( 'iframe', $decoded );
	}
}
