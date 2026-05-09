<?php
/**
 * Unit tests for Pleaseup\WPImageHotspots\Core\Capabilities.
 *
 * @package Pleaseup\WPImageHotspots\Tests
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Pleaseup\WPImageHotspots\Core\Capabilities;

final class CapabilitiesTest extends TestCase {

	protected function setUp() : void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown() : void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_can_edit_attachment_returns_false_for_invalid_id() : void {
		self::assertFalse( Capabilities::can_edit_attachment( 0 ) );
		self::assertFalse( Capabilities::can_edit_attachment( -1 ) );
	}

	public function test_can_edit_attachment_delegates_to_current_user_can() : void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'edit_post', 42 )
			->andReturn( true );

		self::assertTrue( Capabilities::can_edit_attachment( 42 ) );
	}

	public function test_can_manage_plugin_uses_manage_options() : void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		self::assertTrue( Capabilities::can_manage_plugin() );
	}

	public function test_can_upload_files_uses_upload_files() : void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'upload_files' )
			->andReturn( false );

		self::assertFalse( Capabilities::can_upload_files() );
	}

	public function test_can_resolve_oembed_uses_edit_posts() : void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'edit_posts' )
			->andReturn( true );

		self::assertTrue( Capabilities::can_resolve_oembed() );
	}
}
