<?php
/**
 * Unit tests for the abstract Pleaseup\WPImageHotspots\Ajax\AjaxBase.
 *
 * Uses an anonymous concrete subclass to exercise the protected methods.
 *
 * @package Pleaseup\WPImageHotspots\Tests
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Tests\Unit\Ajax;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Pleaseup\WPImageHotspots\Ajax\AjaxBase;

final class AjaxBaseTest extends TestCase {

	private AjaxBase $stub;

	protected function setUp() : void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'absint' )->alias( static fn( $v ) => (int) abs( (int) $v ) );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_create_nonce' )->justReturn( 'nonce-XYZ' );

		$this->stub = $this->makeStub();
	}

	protected function tearDown() : void {
		$_POST = array();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_create_nonce_uses_canonical_action() : void {
		Functions\expect( 'wp_create_nonce' )
			->once()
			->with( AjaxBase::NONCE_ACTION )
			->andReturn( 'fixed-nonce' );

		self::assertSame( 'fixed-nonce', AjaxBase::create_nonce() );
	}

	public function test_post_int_returns_zero_when_field_missing() : void {
		$_POST = array();
		self::assertSame( 0, $this->stub->callPostInt( 'attachment_id' ) );
	}

	public function test_post_int_returns_absint_when_field_present() : void {
		$_POST = array( 'attachment_id' => '-7' );
		self::assertSame( 7, $this->stub->callPostInt( 'attachment_id' ) );
	}

	public function test_post_string_returns_empty_when_field_missing() : void {
		$_POST = array();
		self::assertSame( '', $this->stub->callPostString( 'title' ) );
	}

	public function test_post_string_uses_sanitize_text_field_by_default() : void {
		$_POST = array( 'title' => '  hello  ' );
		// our sanitize_text_field stub is identity → expect raw input
		self::assertSame( '  hello  ', $this->stub->callPostString( 'title' ) );
	}

	public function test_post_string_accepts_custom_sanitizer() : void {
		$_POST = array( 'colour' => '#abcdef' );
		$result = $this->stub->callPostString( 'colour', 'strtoupper' );
		self::assertSame( '#ABCDEF', $result );
	}

	public function test_verify_nonce_or_die_passes_when_nonce_is_valid() : void {
		$_POST = array( 'nonce' => 'nonce-XYZ' );
		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'nonce-XYZ', AjaxBase::NONCE_ACTION )
			->andReturn( 1 );

		// Should NOT call wp_send_json_error.
		Functions\expect( 'wp_send_json_error' )->never();

		$this->stub->callVerifyNonce();
		self::assertTrue( true );
	}

	public function test_verify_nonce_or_die_sends_403_when_nonce_is_invalid() : void {
		$_POST = array( 'nonce' => 'bad' );
		Functions\expect( 'wp_verify_nonce' )->once()->andReturn( false );
		Functions\expect( 'wp_send_json_error' )
			->once()
			->with( \Mockery::type( 'array' ), 403 )
			->andThrow( new \RuntimeException( 'sent 403' ) );

		$this->expectException( \RuntimeException::class );
		$this->stub->callVerifyNonce();
	}

	public function test_require_capability_passes_when_allowed() : void {
		Functions\expect( 'wp_send_json_error' )->never();
		$this->stub->callRequireCapability( true );
		self::assertTrue( true );
	}

	public function test_require_capability_sends_403_when_denied() : void {
		Functions\expect( 'wp_send_json_error' )
			->once()
			->with( \Mockery::type( 'array' ), 403 )
			->andThrow( new \RuntimeException( 'forbidden' ) );

		$this->expectException( \RuntimeException::class );
		$this->stub->callRequireCapability( false );
	}

	private function makeStub() : AjaxBase {
		return new class extends AjaxBase {
			public function register() : void {}
			public function callVerifyNonce() : void {
				$this->verify_nonce_or_die();
			}
			public function callRequireCapability( bool $allowed ) : void {
				$this->require_capability( $allowed );
			}
			public function callPostInt( string $field ) : int {
				return $this->post_int( $field );
			}
			public function callPostString( string $field, ?callable $sanitize = null ) : string {
				return $this->post_string( $field, $sanitize );
			}
		};
	}
}
