<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads Composer autoload, fakes the WordPress classes the unit tests
 * need (no full WP runtime), and sets up Brain Monkey.
 *
 * Integration tests that boot a full WordPress environment can override
 * this file via wp-tests-config.php.
 *
 * @package Pleaseup\WPImageHotspots\Tests
 */

declare(strict_types=1);

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! is_readable( $autoload ) ) {
	fwrite(
		STDERR,
		"Composer autoload not found. Run `composer install` before phpunit.\n"
	);
	exit( 1 );
}

require $autoload;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/wordpress/' );
}
if ( ! defined( 'WPHS_PLUGIN_FILE' ) ) {
	define( 'WPHS_PLUGIN_FILE', dirname( __DIR__ ) . '/wp-image-hotspots.php' );
}
if ( ! defined( 'WPHS_PLUGIN_DIR' ) ) {
	define( 'WPHS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'WPHS_PLUGIN_URL' ) ) {
	define( 'WPHS_PLUGIN_URL', 'https://example.test/wp-content/plugins/wp-image-hotspots/' );
}
if ( ! defined( 'WPHS_VERSION' ) ) {
	define( 'WPHS_VERSION', '3.0.0-test' );
}
if ( ! defined( 'WPHS_MIN_PHP' ) ) {
	define( 'WPHS_MIN_PHP', '7.4' );
}
if ( ! defined( 'WPHS_MIN_WP' ) ) {
	define( 'WPHS_MIN_WP', '6.0' );
}
if ( ! defined( 'JSON_UNESCAPED_UNICODE' ) ) {
	define( 'JSON_UNESCAPED_UNICODE', 256 );
}

// Minimal WP_Post stub — sufficient for the property reads in the
// repositories. Real WordPress provides far more, but unit tests only
// touch ID / post_type / post_title / post_content / post_modified_gmt.
if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID                = 0;
		public string $post_type      = '';
		public string $post_status    = '';
		public string $post_title     = '';
		public string $post_content   = '';
		public string $post_modified_gmt = '';

		public function __construct( $data = null ) {
			if ( is_object( $data ) ) {
				foreach ( get_object_vars( $data ) as $key => $value ) {
					if ( property_exists( $this, $key ) ) {
						$this->$key = $value;
					}
				}
			}
		}
	}
}

// Minimal WP_Query stub — only used as a type hint by the editor page.
if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public array $posts = array();

		public function __construct( $args = array() ) {}
	}
}
