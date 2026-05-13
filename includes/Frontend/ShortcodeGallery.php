<?php
/**
 * Frontend renderer for the [wphs_gallery] shortcode.
 *
 * Contains:
 *   - render()      → entry point for the [wphs_gallery] shortcode.
 *                     Iterates the gallery's image_ids and delegates
 *                     each slide to ShortcodeImage::render() so the
 *                     hotspots survive inside the carousel.
 *   - render_html() → simplified preview rendering used by the admin
 *                     preview AJAX endpoint (no hotspots, just slides).
 *
 * Both bodies are extracted verbatim from the v2.5.80 monolith
 * (lines 4159-4368 and 3601-3787 respectively) with the legacy
 * `self::*` calls rewritten to use injected services.
 *
 * @package Pleaseup\WPImageHotspots\Frontend
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Frontend;

use Pleaseup\WPImageHotspots\Core\GalleryRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the [wphs_gallery] shortcode.
 */
final class ShortcodeGallery {

	private GalleryRepository $galleries;
	private ShortcodeImage $images;

	public function __construct( GalleryRepository $galleries, ShortcodeImage $images ) {
		$this->galleries = $galleries;
		$this->images    = $images;
	}

	/**
	 * Hook the shortcode tag.
	 */
	public function register() : void {
		add_shortcode( 'wphs_gallery', array( $this, 'render' ) );
	}

	/**
	 * Shortcode entry point.
	 *
	 * @param array<string,mixed>|string $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function render( $atts ) : string {
		$atts = (array) $atts;
		$atts = shortcode_atts( array(
			'id'           => 0,
			'nav'          => '',
			'cols_desktop' => '',
			'cols_mobile'  => '',
		), $atts, 'wphs_gallery' );

		$gallery_id = absint( $atts['id'] );
		if ( ! $gallery_id ) { return ''; }

		$gallery = $this->galleries->get( $gallery_id );
		if ( ! $gallery || empty( $gallery['image_ids'] ) ) { return ''; }

		$cols_d     = '' !== $atts['cols_desktop'] ? max(1, min(6, absint($atts['cols_desktop']))) : ( $gallery['cols_desktop'] ?? 1 );
		$cols_m     = '' !== $atts['cols_mobile']  ? max(1, min(3, absint($atts['cols_mobile'])))  : ( $gallery['cols_mobile']  ?? 1 );
		$img_radius = (int) ( $gallery['img_radius'] ?? 0 );
		$captions      = is_array( $gallery['captions'] ?? null ) ? $gallery['captions'] : array();
		$caption_bg    = $gallery['caption_bg']    ?? '#111827';
		$caption_color = $gallery['caption_color'] ?? '#ffffff';
		$_nav_raw = sanitize_text_field( wp_unslash( $atts['nav'] ) );
		$nav    = '' !== $_nav_raw ? $_nav_raw : $gallery['nav'];
		$allowed_nav = array( 'dots+arrows', 'dots', 'arrows', 'none' );
		if ( ! in_array( $nav, $allowed_nav, true ) ) { $nav = 'dots+arrows'; }

		$show_dots   = in_array( $nav, array( 'dots+arrows', 'dots' ), true );
		$show_arrows = in_array( $nav, array( 'dots+arrows', 'arrows' ), true );
		$uid         = 'wphs-gl-' . $gallery_id . '-' . wp_rand( 1000, 9999 );
		$image_ids   = $gallery['image_ids'];
		$slide_count = count( $image_ids );

		wp_enqueue_style( 'wphs-frontend' );
		wp_enqueue_script( 'wphs-frontend' );
		wp_enqueue_script( 'wphs-frontend-gallery' );

		$inline_vars = sprintf(
			'--wphs-cols-d:%d;--wphs-cols-m:%d;--wphs-img-radius:%dpx',
			$cols_d,
			$cols_m,
			$img_radius
		);

		ob_start(); ?>
<div class="wphs-gallery" id="<?php echo esc_attr($uid); ?>" data-cols-d="<?php echo esc_attr( (string) $cols_d ); ?>" data-cols-m="<?php echo esc_attr( (string) $cols_m ); ?>" style="<?php echo esc_attr( $inline_vars ); ?>">
	<div class="wphs-gallery-track">
	<?php foreach ( $image_ids as $img_id ) :
		$slide_html = $this->images->render( array( 'id' => $img_id, 'size' => 'large', 'class' => '' ) );
		if ( '' === $slide_html ) { continue; }
		$_ck   = (string) $img_id;
		$_ct   = isset( $captions[$_ck]['title'] ) ? trim($captions[$_ck]['title']) : '';
		$_cd   = isset( $captions[$_ck]['desc'] )  ? trim($captions[$_ck]['desc'])  : '';
		$_hc   = ($_ct||$_cd) ? ' has-caption' : '';
		?>
		<div class="wphs-gallery-slide<?php echo $_hc; ?>">
			<?php echo $slide_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
				$_cap_key   = (string) $img_id;
				$_cap_title = isset( $captions[ $_cap_key ]['title'] ) ? trim( $captions[ $_cap_key ]['title'] ) : '';
				$_cap_desc  = isset( $captions[ $_cap_key ]['desc'] )  ? trim( $captions[ $_cap_key ]['desc'] )  : '';
				if ( $_cap_title || $_cap_desc ) :
			?>
			<div class="wphs-slide-caption" style="background:<?php echo esc_attr($caption_bg); ?>;color:<?php echo esc_attr($caption_color); ?>">
				<?php if($_cap_title): ?><p class="wphs-slide-caption-title"><?php echo esc_html($_cap_title); ?></p><?php endif; ?>
				<?php if($_cap_desc):  ?><p class="wphs-slide-caption-desc"><?php echo esc_html($_cap_desc); ?></p><?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
	</div>
	<?php if ( $show_arrows ) : ?>
	<button type="button" class="wphs-gl-arrow wphs-gl-prev" aria-label="<?php echo esc_attr_x( 'Previous', 'gallery navigation', 'pleaseup-hotspots' ); ?>" style="position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,.45);border:none;border-radius:50%;width:36px;height:36px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;z-index:10;padding:0"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg></button>
	<button type="button" class="wphs-gl-arrow wphs-gl-next" aria-label="<?php echo esc_attr_x( 'Next', 'gallery navigation', 'pleaseup-hotspots' ); ?>" style="position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,.45);border:none;border-radius:50%;width:36px;height:36px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;z-index:10;padding:0"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg></button>
	<?php endif; ?>
	<?php if ( $show_dots ) : ?>
	<div class="wphs-gl-dots" style="display:flex;justify-content:center;gap:6px;padding:10px 0 4px"></div>
	<?php endif; ?>
</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render a simplified gallery preview (no hotspots) given raw
	 * parameters. Used by the admin AJAX preview endpoint.
	 *
	 * @param int[]                                    $image_ids
	 * @param string                                   $nav
	 * @param int                                      $cols_d
	 * @param int                                      $cols_m
	 * @param int                                      $img_radius
	 * @param array<string,array{title:string,desc:string}> $captions
	 * @param string                                   $caption_bg
	 * @param string                                   $caption_color
	 */
	public function render_html(
		array $image_ids,
		string $nav,
		int $cols_d,
		int $cols_m,
		int $img_radius = 0,
		array $captions = array(),
		string $caption_bg = '#111827',
		string $caption_color = '#ffffff'
	) : string {
		if ( empty( $image_ids ) ) return '';

		$allowed_nav = array( 'dots+arrows', 'dots', 'arrows', 'none' );
		if ( ! in_array( $nav, $allowed_nav, true ) ) { $nav = 'dots+arrows'; }
		$show_dots   = in_array( $nav, array( 'dots+arrows', 'dots' ), true );
		$show_arrows = in_array( $nav, array( 'dots+arrows', 'arrows' ), true );
		$uid         = 'wphs-gl-prev-' . wp_rand( 1000, 9999 );
		$slide_count = count( $image_ids );

		$inline_vars = sprintf(
			'--wphs-cols-d:%d;--wphs-cols-m:%d;--wphs-img-radius:%dpx',
			$cols_d,
			$cols_m,
			$img_radius
		);

		ob_start(); ?>
<div class="wphs-gallery" id="<?php echo esc_attr($uid); ?>" data-cols-d="<?php echo esc_attr( (string) $cols_d ); ?>" data-cols-m="<?php echo esc_attr( (string) $cols_m ); ?>" style="<?php echo esc_attr( $inline_vars ); ?>">
	<div class="wphs-gallery-track">
	<?php foreach ( $image_ids as $img_id ) :
		$img_url = wp_get_attachment_image_url( absint($img_id), 'large' );
		if ( ! $img_url ) { continue; }
		?>
		<?php
			$_cap_key_pre   = (string) $img_id;
			$_cap_title_pre = isset($captions[$_cap_key_pre]['title']) ? trim($captions[$_cap_key_pre]['title']) : '';
			$_cap_desc_pre  = isset($captions[$_cap_key_pre]['desc'])  ? trim($captions[$_cap_key_pre]['desc'])  : '';
			$_has_cap = ($_cap_title_pre||$_cap_desc_pre) ? ' has-caption' : '';
		?>
		<div class="wphs-gallery-slide<?php echo $_has_cap; ?>">
			<img src="<?php echo esc_url($img_url); ?>" alt="" loading="lazy">
			<?php
				$_cap_key   = (string) $img_id;
				$_cap_title = isset( $captions[$_cap_key]['title'] ) ? trim($captions[$_cap_key]['title']) : '';
				$_cap_desc  = isset( $captions[$_cap_key]['desc'] )  ? trim($captions[$_cap_key]['desc'])  : '';
				if($_cap_title||$_cap_desc):
			?>
			<div class="wphs-slide-caption" style="background:<?php echo esc_attr($caption_bg); ?>;color:<?php echo esc_attr($caption_color); ?>">
				<?php if($_cap_title): ?><p class="wphs-slide-caption-title"><?php echo esc_html($_cap_title); ?></p><?php endif; ?>
				<?php if($_cap_desc):  ?><p class="wphs-slide-caption-desc"><?php echo esc_html($_cap_desc); ?></p><?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
	</div>
	<?php if ( $show_arrows ) : ?>
	<button type="button" class="wphs-gl-arrow wphs-gl-prev" aria-label="<?php echo esc_attr_x( 'Previous', 'gallery navigation', 'pleaseup-hotspots' ); ?>" style="position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,.45);border:none;border-radius:50%;width:36px;height:36px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;z-index:10;padding:0"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg></button>
	<button type="button" class="wphs-gl-arrow wphs-gl-next" aria-label="<?php echo esc_attr_x( 'Next', 'gallery navigation', 'pleaseup-hotspots' ); ?>" style="position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,.45);border:none;border-radius:50%;width:36px;height:36px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;z-index:10;padding:0"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg></button>
	<?php endif; ?>
	<?php if ( $show_dots ) : ?>
	<div class="wphs-gl-dots" style="display:flex;justify-content:center;gap:6px;padding:10px 0 4px"></div>
	<?php endif; ?>
</div>
		<?php
		return (string) ob_get_clean();
	}
}
