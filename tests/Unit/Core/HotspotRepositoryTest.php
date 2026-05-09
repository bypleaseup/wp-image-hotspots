<?php
/**
 * Unit tests for Pleaseup\WPImageHotspots\Core\HotspotRepository.
 *
 * @package Pleaseup\WPImageHotspots\Tests
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Pleaseup\WPImageHotspots\Core\HotspotRepository;
use Pleaseup\WPImageHotspots\Core\TooltipRepository;

final class HotspotRepositoryTest extends TestCase {

	/** @var TooltipRepository&\PHPUnit\Framework\MockObject\MockObject */
	private $tooltips;

	private HotspotRepository $hotspots;

	protected function setUp() : void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'absint' )->alias( static fn( $v ) => (int) abs( (int) $v ) );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'wp_json_encode' )->alias(
			static fn( $value, $options = 0 ) => json_encode( $value, $options )
		);
		Functions\when( 'current_time' )->justReturn( '2026-05-07 12:00:00' );

		$this->tooltips = $this->createMock( TooltipRepository::class );
		$this->hotspots = new HotspotRepository( $this->tooltips );
	}

	protected function tearDown() : void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_returns_empty_for_invalid_attachment() : void {
		self::assertSame( array(), $this->hotspots->get( 0 ) );
	}

	public function test_get_returns_empty_when_meta_is_not_string() : void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 7, HotspotRepository::META_KEY, true )
			->andReturn( null );

		self::assertSame( array(), $this->hotspots->get( 7 ) );
	}

	public function test_get_returns_decoded_array_when_meta_is_valid_json() : void {
		$payload = array( array( 'id' => 'h_1', 'x' => 10.0, 'y' => 20.0 ) );
		Functions\expect( 'get_post_meta' )->once()->andReturn( json_encode( $payload ) );

		self::assertSame( $payload, $this->hotspots->get( 7 ) );
	}

	public function test_get_hydrated_attaches_html_from_tooltip_repository() : void {
		$payload = array(
			array( 'id' => 'h_1', 'x' => 10.0, 'y' => 20.0, 'tooltip_id' => 42 ),
			array( 'id' => 'h_2', 'x' => 30.0, 'y' => 40.0, 'tooltip_id' => 0 ),
		);
		Functions\expect( 'get_post_meta' )->once()->andReturn( json_encode( $payload ) );

		$this->tooltips
			->expects( $this->once() )
			->method( 'get_html' )
			->with( 42 )
			->willReturn( '<p>Tooltip 1</p>' );

		$result = $this->hotspots->get_hydrated( 7 );
		self::assertSame( '<p>Tooltip 1</p>', $result[0]['html'] );
		self::assertSame( '', $result[1]['html'] );
	}

	public function test_save_persists_clamped_coordinates_and_emits_action() : void {
		Functions\expect( 'get_post_meta' )->once()->andReturn( '[]' );

		$this->tooltips
			->expects( $this->once() )
			->method( 'upsert' )
			->with( 0, '<p>html</p>', 7, 'h_1' )
			->willReturn( 555 );

		Functions\expect( 'update_post_meta' )
			->once()
			->with(
				7,
				HotspotRepository::META_KEY,
				\Mockery::on(
					static function ( $value ) : bool {
						$decoded = json_decode( $value, true );
						return is_array( $decoded )
							&& 1 === count( $decoded )
							&& 100.0 === $decoded[0]['x']    // clamped from 200
							&& 0.0 === $decoded[0]['y']     // clamped from -5
							&& 555 === $decoded[0]['tooltip_id'];
					}
				)
			)
			->andReturn( true );
		Functions\expect( 'update_post_meta' )
			->once()
			->with( 7, HotspotRepository::META_SAVED_AT, '2026-05-07 12:00:00' )
			->andReturn( true );

		Functions\expect( 'do_action' )
			->once()
			->with( 'wphs_after_save_hotspots', 7, \Mockery::type( 'array' ) );

		$result = $this->hotspots->save(
			7,
			array(
				array(
					'id'   => 'h_1',
					'x'    => 200.0,
					'y'    => -5.0,
					'html' => '<p>html</p>',
				),
			)
		);

		self::assertSame( 1, $result['count'] );
		self::assertSame( '2026-05-07 12:00:00', $result['saved_at'] );
	}

	public function test_save_drops_orphan_tooltips() : void {
		// Previous state has tooltip 11; new payload no longer references it.
		Functions\expect( 'get_post_meta' )
			->once()
			->andReturn( json_encode( array( array( 'id' => 'h_old', 'tooltip_id' => 11 ) ) ) );

		$this->tooltips
			->expects( $this->once() )
			->method( 'upsert' )
			->willReturn( 22 );
		$this->tooltips
			->expects( $this->once() )
			->method( 'delete' )
			->with( 11 );

		Functions\expect( 'update_post_meta' )->twice()->andReturn( true );

		$this->hotspots->save(
			7,
			array(
				array( 'id' => 'h_new', 'x' => 1, 'y' => 2, 'html' => '' ),
			)
		);
		self::assertTrue( true );
	}

	public function test_save_skips_hotspots_with_empty_id() : void {
		Functions\expect( 'get_post_meta' )->once()->andReturn( '[]' );
		Functions\expect( 'update_post_meta' )->twice()->andReturn( true );

		$this->tooltips
			->expects( $this->never() )
			->method( 'upsert' );

		$result = $this->hotspots->save(
			7,
			array(
				array( 'id' => '', 'x' => 50, 'y' => 50 ),
				'not-an-array',
			)
		);
		self::assertSame( 0, $result['count'] );
	}

	public function test_get_saved_at_returns_string_or_empty() : void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 7, HotspotRepository::META_SAVED_AT, true )
			->andReturn( '2026-05-07 12:00:00' );

		self::assertSame( '2026-05-07 12:00:00', $this->hotspots->get_saved_at( 7 ) );
	}
}
