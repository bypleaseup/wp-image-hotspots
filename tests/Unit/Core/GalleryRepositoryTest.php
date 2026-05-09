<?php
/**
 * Unit tests for Pleaseup\WPImageHotspots\Core\GalleryRepository.
 *
 * Focused on the value-object hydrate/save logic; the WordPress side
 * effects are simulated via Brain Monkey.
 *
 * @package Pleaseup\WPImageHotspots\Tests
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Pleaseup\WPImageHotspots\Core\GalleryRepository;
use Pleaseup\WPImageHotspots\Core\PostTypes;
use WP_Post;

final class GalleryRepositoryTest extends TestCase {

	private GalleryRepository $galleries;

	protected function setUp() : void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'absint' )->alias( static fn( $v ) => (int) abs( (int) $v ) );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'sanitize_textarea_field' )->returnArg( 1 );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'wp_json_encode' )->alias(
			static fn( $value, $options = 0 ) => json_encode( $value, $options )
		);
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( '__' )->returnArg( 1 );

		$this->galleries = new GalleryRepository();
	}

	protected function tearDown() : void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_returns_null_for_invalid_id() : void {
		self::assertNull( $this->galleries->get( 0 ) );
	}

	public function test_get_returns_null_when_post_type_is_wrong() : void {
		$post = $this->makePost( 5, 'attachment' );
		Functions\expect( 'get_post' )->once()->andReturn( $post );
		self::assertNull( $this->galleries->get( 5 ) );
	}

	public function test_get_hydrates_clamped_meta_values() : void {
		$post = $this->makePost( 5, PostTypes::GALLERY, 'My Gallery' );
		Functions\expect( 'get_post' )->once()->andReturn( $post );

		Functions\when( 'get_post_meta' )->alias(
			static function ( $id, $key ) {
				$values = array(
					GalleryRepository::META_IDS           => '1,2,3',
					GalleryRepository::META_NAV           => 'invalid_value',
					GalleryRepository::META_COLS_DESKTOP  => 99,
					GalleryRepository::META_COLS_MOBILE   => 0,
					GalleryRepository::META_IMG_RADIUS    => 9999,
					GalleryRepository::META_CAPTIONS      => json_encode( array( '1' => array( 'title' => 'A', 'desc' => 'b' ) ) ),
					GalleryRepository::META_CAPTION_BG    => '#abcdef',
					GalleryRepository::META_CAPTION_COLOR => '',
				);
				return $values[ $key ] ?? '';
			}
		);

		$gallery = $this->galleries->get( 5 );
		self::assertSame( 5, $gallery['id'] );
		self::assertSame( 'My Gallery', $gallery['title'] );
		self::assertSame( array( 1, 2, 3 ), $gallery['image_ids'] );
		self::assertSame( GalleryRepository::DEFAULT_NAV, $gallery['nav'] );
		self::assertSame( 6, $gallery['cols_desktop'] );  // clamped to max
		self::assertSame( 1, $gallery['cols_mobile'] );   // clamped to min
		self::assertSame( 999, $gallery['img_radius'] );  // clamped to max
		self::assertSame( '#abcdef', $gallery['caption_bg'] );
		self::assertSame( GalleryRepository::DEFAULT_CAPTION_COL, $gallery['caption_color'] );
	}

	public function test_save_inserts_when_id_is_zero() : void {
		Functions\expect( 'wp_insert_post' )->once()->andReturn( 99 );
		Functions\expect( 'update_post_meta' )->atLeast()->once()->andReturn( true );

		$id = $this->galleries->save( 0, array( 'title' => 'New Gallery' ) );
		self::assertSame( 99, $id );
	}

	public function test_save_updates_when_id_is_positive() : void {
		Functions\expect( 'wp_update_post' )->once()->andReturn( 42 );
		Functions\expect( 'update_post_meta' )->atLeast()->once()->andReturn( true );

		$id = $this->galleries->save( 42, array( 'title' => 'Existing' ) );
		self::assertSame( 42, $id );
	}

	public function test_save_returns_zero_when_insert_yields_wp_error() : void {
		Functions\when( 'is_wp_error' )->justReturn( true );
		Functions\expect( 'wp_insert_post' )->once()->andReturn( 'WP_Error' );
		// update_post_meta should NOT be called when insertion fails.

		$id = $this->galleries->save( 0, array( 'title' => 'broken' ) );
		self::assertSame( 0, $id );
	}

	public function test_delete_returns_false_for_invalid_id() : void {
		self::assertFalse( $this->galleries->delete( 0 ) );
	}

	public function test_delete_returns_false_when_post_type_is_wrong() : void {
		$post = $this->makePost( 5, 'attachment' );
		Functions\expect( 'get_post' )->once()->andReturn( $post );
		self::assertFalse( $this->galleries->delete( 5 ) );
	}

	public function test_delete_force_deletes_correct_post_type() : void {
		$post = $this->makePost( 5, PostTypes::GALLERY );
		Functions\expect( 'get_post' )->once()->andReturn( $post );
		Functions\expect( 'wp_delete_post' )->once()->with( 5, true )->andReturn( $post );

		self::assertTrue( $this->galleries->delete( 5 ) );
	}

	private function makePost( int $id, string $type, string $title = '' ) : WP_Post {
		$post              = new WP_Post( (object) array() );
		$post->ID          = $id;
		$post->post_type   = $type;
		$post->post_title  = $title;
		return $post;
	}
}
