<?php
/**
 * Unit tests for Pleaseup\WPImageHotspots\Core\Settings.
 *
 * @package Pleaseup\WPImageHotspots\Tests
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Pleaseup\WPImageHotspots\Core\Settings;

final class SettingsTest extends TestCase {

	private Settings $settings;

	protected function setUp() : void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'absint' )->alias( static fn( $v ) => (int) abs( (int) $v ) );
		Functions\when( 'wp_json_encode' )->alias(
			static fn( $value, $options = 0 ) => json_encode( $value, $options )
		);

		$this->settings = new Settings();
	}

	protected function tearDown() : void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_defaults_returns_full_schema() : void {
		$defaults = Settings::defaults();
		self::assertSame( 'default', $defaults['hotspot_style'] );
		self::assertSame( 22, $defaults['hotspot_icon_size'] );
		self::assertSame( '#000000', $defaults['dot_color'] );
		self::assertSame( '#ffffff', $defaults['dot_border_color'] );
		self::assertSame( '#0b1220', $defaults['tooltip_bg'] );
		self::assertSame( 0, $defaults['tooltip_tail'] );
	}

	public function test_get_options_merges_saved_over_defaults() : void {
		Functions\expect( 'get_option' )
			->once()
			->with( Settings::OPT_KEY, array() )
			->andReturn( array( 'dot_color' => '#ff00aa' ) );

		$opts = $this->settings->get_options();
		self::assertSame( '#ff00aa', $opts['dot_color'] );
		self::assertSame( '#ffffff', $opts['dot_border_color'] ); // default
	}

	public function test_get_options_falls_back_to_defaults_when_option_is_not_array() : void {
		Functions\expect( 'get_option' )
			->once()
			->andReturn( 'corrupted' );

		$opts = $this->settings->get_options();
		self::assertSame( '#000000', $opts['dot_color'] );
	}

	public function test_save_options_validates_and_persists() : void {
		Functions\expect( 'get_option' )->andReturn( array() );
		Functions\expect( 'update_option' )
			->once()
			->with(
				Settings::OPT_KEY,
				\Mockery::on(
					static function ( $value ) : bool {
						return is_array( $value )
							&& 'image' === $value['hotspot_style']
							&& 0 !== $value['hotspot_image_id'];
					}
				),
				false
			)
			->andReturn( true );

		$saved = $this->settings->save_options(
			array(
				'hotspot_style'    => 'image',
				'hotspot_image_id' => 99,
			)
		);

		self::assertSame( 'image', $saved['hotspot_style'] );
	}

	public function test_save_options_forces_default_style_when_image_id_is_zero() : void {
		Functions\expect( 'get_option' )->andReturn( array() );
		Functions\expect( 'update_option' )->once()->andReturn( true );

		$saved = $this->settings->save_options(
			array(
				'hotspot_style'    => 'image',
				'hotspot_image_id' => 0,
			)
		);
		self::assertSame( 'default', $saved['hotspot_style'] );
	}

	public function test_save_options_clamps_dot_radius_against_dot_size() : void {
		Functions\expect( 'get_option' )->andReturn( array() );
		Functions\expect( 'update_option' )->once()->andReturn( true );

		$saved = $this->settings->save_options(
			array(
				'dot_width'  => 20,
				'dot_height' => 20,
				'dot_radius' => 999,
			)
		);
		self::assertSame( 10, $saved['dot_radius'] );
	}

	public function test_save_options_validates_hex_colors() : void {
		Functions\expect( 'get_option' )->andReturn( array() );
		Functions\expect( 'update_option' )->once()->andReturn( true );

		$saved = $this->settings->save_options(
			array(
				'dot_color'  => 'NOT-HEX',
				'tooltip_bg' => '#abcdef',
			)
		);
		self::assertSame( '#000000', $saved['dot_color'] ); // fallback to default
		self::assertSame( '#abcdef', $saved['tooltip_bg'] );
	}

	public function test_get_image_settings_returns_globals_when_meta_is_missing() : void {
		Functions\expect( 'get_option' )->andReturn( array() );
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 7, Settings::META_IMG_KEY, true )
			->andReturn( '' );

		$result = $this->settings->get_image_settings( 7 );
		self::assertSame( '#000000', $result['dot_color'] );
	}

	public function test_get_image_settings_merges_meta_over_globals() : void {
		Functions\expect( 'get_option' )->andReturn( array( 'dot_color' => '#aaaaaa' ) );
		Functions\expect( 'get_post_meta' )
			->once()
			->andReturn( json_encode( array( 'dot_color' => '#bbbbbb' ) ) );

		$result = $this->settings->get_image_settings( 7 );
		self::assertSame( '#bbbbbb', $result['dot_color'] );
	}

	public function test_get_image_settings_returns_globals_when_attachment_id_is_zero() : void {
		Functions\expect( 'get_option' )->andReturn( array() );
		// get_post_meta should NOT be called.

		$result = $this->settings->get_image_settings( 0 );
		self::assertSame( '#000000', $result['dot_color'] );
	}

	public function test_save_image_settings_writes_meta_with_validated_values() : void {
		Functions\expect( 'get_option' )->andReturn( array() );
		Functions\expect( 'update_post_meta' )
			->once()
			->with( 7, Settings::META_IMG_KEY, \Mockery::type( 'string' ) )
			->andReturn( true );

		$saved = $this->settings->save_image_settings( 7, array( 'dot_color' => '#abcdef' ) );
		self::assertSame( '#abcdef', $saved['dot_color'] );
	}

	public function test_delete_image_settings_calls_delete_post_meta() : void {
		Functions\expect( 'delete_post_meta' )
			->once()
			->with( 7, Settings::META_IMG_KEY )
			->andReturn( true );

		$this->settings->delete_image_settings( 7 );
		// no return assertion: success is the call expectation above.
		self::assertTrue( true );
	}
}
