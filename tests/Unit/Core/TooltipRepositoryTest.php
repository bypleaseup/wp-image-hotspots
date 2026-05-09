<?php
/**
 * Unit tests for Pleaseup\WPImageHotspots\Core\TooltipRepository.
 *
 * @package Pleaseup\WPImageHotspots\Tests
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Pleaseup\WPImageHotspots\Core\PostTypes;
use Pleaseup\WPImageHotspots\Core\TooltipRepository;
use WP_Post;

final class TooltipRepositoryTest extends TestCase {

	private TooltipRepository $tooltips;

	protected function setUp() : void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'absint' )->alias( static fn( $v ) => (int) abs( (int) $v ) );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'is_wp_error' )->justReturn( false );

		$this->tooltips = new TooltipRepository();
	}

	protected function tearDown() : void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_html_returns_empty_for_invalid_id() : void {
		self::assertSame( '', $this->tooltips->get_html( 0 ) );
		self::assertSame( '', $this->tooltips->get_html( -5 ) );
	}

	public function test_get_html_returns_empty_when_post_is_missing() : void {
		Functions\expect( 'get_post' )->once()->with( 99 )->andReturn( null );
		self::assertSame( '', $this->tooltips->get_html( 99 ) );
	}

	public function test_get_html_returns_empty_when_post_type_is_wrong() : void {
		$post = $this->makePost( 99, 'attachment', '<p>x</p>' );
		Functions\expect( 'get_post' )->once()->andReturn( $post );

		self::assertSame( '', $this->tooltips->get_html( 99 ) );
	}

	public function test_get_html_returns_post_content_for_correct_post_type() : void {
		$post = $this->makePost( 99, PostTypes::TOOLTIP, '<p>hello</p>' );
		Functions\expect( 'get_post' )->once()->andReturn( $post );

		self::assertSame( '<p>hello</p>', $this->tooltips->get_html( 99 ) );
	}

	public function test_upsert_inserts_new_post_when_id_is_zero() : void {
		Functions\when( 'wp_kses' )->returnArg( 1 );
		Functions\when( 'wp_kses_allowed_html' )->justReturn( array() );
		Functions\when( '__' )->returnArg( 1 );
		Functions\expect( 'wp_insert_post' )
			->once()
			->andReturn( 1234 );

		$id = $this->tooltips->upsert( 0, '<p>x</p>', 7, 'h_1' );
		self::assertSame( 1234, $id );
	}

	public function test_upsert_updates_existing_post_when_id_is_positive() : void {
		Functions\when( 'wp_kses' )->returnArg( 1 );
		Functions\when( 'wp_kses_allowed_html' )->justReturn( array() );
		Functions\when( '__' )->returnArg( 1 );
		Functions\expect( 'wp_update_post' )
			->once()
			->andReturn( 999 );

		$id = $this->tooltips->upsert( 999, '<p>x</p>', 7, 'h_1' );
		self::assertSame( 999, $id );
	}

	public function test_upsert_returns_zero_when_insert_yields_wp_error() : void {
		Functions\when( 'wp_kses' )->returnArg( 1 );
		Functions\when( 'wp_kses_allowed_html' )->justReturn( array() );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_wp_error' )->justReturn( true );
		Functions\expect( 'wp_insert_post' )->once()->andReturn( 'WP_Error object' );

		self::assertSame( 0, $this->tooltips->upsert( 0, '<p>x</p>', 7, 'h_1' ) );
	}

	public function test_delete_skips_invalid_id() : void {
		// wp_delete_post should NOT be called.
		$this->tooltips->delete( 0 );
		self::assertTrue( true );
	}

	public function test_delete_skips_when_post_type_is_wrong() : void {
		$post = $this->makePost( 99, 'attachment', '' );
		Functions\expect( 'get_post' )->once()->andReturn( $post );
		// wp_delete_post should NOT be called.

		$this->tooltips->delete( 99 );
		self::assertTrue( true );
	}

	public function test_delete_force_deletes_correct_post_type() : void {
		$post = $this->makePost( 99, PostTypes::TOOLTIP, '' );
		Functions\expect( 'get_post' )->once()->andReturn( $post );
		Functions\expect( 'wp_delete_post' )->once()->with( 99, true )->andReturn( $post );

		$this->tooltips->delete( 99 );
		self::assertTrue( true );
	}

	private function makePost( int $id, string $type, string $content ) : WP_Post {
		// WP_Post is final-ish but constructor accepts stdClass-like data.
		// Brain\Monkey provides a stub via brain/faker if installed; fall
		// back to anonymous class extending WP_Post via reflection.
		$post                = new WP_Post( (object) array() );
		$post->ID            = $id;
		$post->post_type     = $type;
		$post->post_content  = $content;
		return $post;
	}
}
