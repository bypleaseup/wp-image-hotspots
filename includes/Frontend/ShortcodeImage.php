<?php
/**
 * Frontend renderer for the [wphs_image] shortcode.
 *
 * Body extracted verbatim from the v2.5.80 monolith
 * (shortcode_image() lines 3084-3208) with `self::*` calls rewritten
 * to use the injected services. The OB-based template approach is
 * preserved to keep the HTML/PHP interleaving readable.
 *
 * Tracked debt: the embedded HTML mixes presentation and a small
 * amount of data juggling — extracting it into a partial under
 * Frontend/partials/ is deferred to a 3.0.x cleanup PR (same Phase 5
 * note about partials with DB calls).
 *
 * @package Pleaseup\WPImageHotspots\Frontend
 */

declare(strict_types=1);

namespace Pleaseup\WPImageHotspots\Frontend;

use Pleaseup\WPImageHotspots\Core\HotspotRepository;
use Pleaseup\WPImageHotspots\Core\Settings;
use Pleaseup\WPImageHotspots\Core\TooltipRepository;
use Pleaseup\WPImageHotspots\Helpers\OembedResolver;
use Pleaseup\WPImageHotspots\Helpers\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the [wphs_image] shortcode.
 */
final class ShortcodeImage {

	private Settings $settings;
	private TooltipRepository $tooltips;
	private OembedResolver $oembed;

	public function __construct( Settings $settings, TooltipRepository $tooltips, OembedResolver $oembed ) {
		$this->settings = $settings;
		$this->tooltips = $tooltips;
		$this->oembed   = $oembed;
	}

	/**
	 * Hook the shortcode tag.
	 */
	public function register() : void {
		add_shortcode( 'wphs_image', array( $this, 'render' ) );
	}

	/**
	 * Shortcode entry point.
	 *
	 * @param array<string,mixed>|string $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function render( $atts ) : string {
		$atts = (array) $atts;
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'size'  => 'large',
				'class' => '',
			),
			$atts,
			'wphs_image'
		);

		$attachment_id = absint( $atts['id'] );
		if ( $attachment_id <= 0 ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $attachment_id, $atts['size'] );
		if ( ! $url ) {
			return '';
		}

		$json = get_post_meta( $attachment_id, HotspotRepository::META_KEY, true );
		if ( ! is_string( $json ) || '' === $json ) {
			return wp_get_attachment_image( $attachment_id, $atts['size'], false, array( 'class' => $atts['class'] ) );
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || empty( $data ) ) {
			return wp_get_attachment_image( $attachment_id, $atts['size'], false, array( 'class' => $atts['class'] ) );
		}

		/**
		 * Filter the hotspot list before it is rendered.
		 *
		 * Plugins can mutate, reorder, or drop hotspots based on the
		 * current user, the attachment, or the shortcode attributes.
		 * Returning an empty array yields a plain `<img>` fallback.
		 *
		 * @since 3.0.0
		 *
		 * @param array<int,array<string,mixed>> $data          Hotspots as stored in meta.
		 * @param int                            $attachment_id Attachment ID being rendered.
		 * @param array<string,mixed>            $atts          Shortcode attributes.
		 */
		$data = (array) apply_filters( 'wphs_hotspot_data', $data, $attachment_id, $atts );
		if ( empty( $data ) ) {
			return wp_get_attachment_image( $attachment_id, $atts['size'], false, array( 'class' => $atts['class'] ) );
		}

		$opts = $this->settings->get_image_settings( $attachment_id );
		$icon_url = ( ! empty( $opts['hotspot_image_id'] ) ) ? wp_get_attachment_image_url( (int) $opts['hotspot_image_id'], 'thumbnail' ) : '';
		$size = absint( $opts['hotspot_icon_size'] );

		wp_enqueue_style( 'wphs-frontend' );
		wp_enqueue_script( 'wphs-frontend' );
		// Fallback: se lo shortcode viene renderizzato dopo wp_head (es. preview / blocchi / builder),
		// i CSS enqueued potrebbero non essere stampati. Stampiamoli in-line per garantire il posizionamento.
		if ( did_action( 'wp_head' ) && ! wp_style_is( 'wphs-frontend', 'done' ) ) {
			wp_print_styles( 'wphs-frontend' );
		}

		$img_html = wp_get_attachment_image( $attachment_id, $atts['size'], false, array( 'class' => $atts['class'] ) );

		/**
		 * Fires immediately before [wphs_image] starts emitting HTML.
		 *
		 * Useful for pages that need to push extra <link>/<meta> tags
		 * or kick off lazy-loading helpers when the shortcode is about
		 * to render.
		 *
		 * @since 3.0.0
		 *
		 * @param int                            $attachment_id Attachment ID being rendered.
		 * @param array<string,mixed>            $atts          Shortcode attributes.
		 * @param array<int,array<string,mixed>> $data          Filtered hotspot list.
		 */
		do_action( 'wphs_before_render_image', $attachment_id, $atts, $data );

		ob_start();
		?>
		<div class="wphs-figure" data-attachment="<?php echo esc_attr( (string) $attachment_id ); ?>">
			<?php echo $img_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="wphs-img-overlay">
			<?php $_sc_settings = $this->settings->get_image_settings( $attachment_id );
			$hotspot_index = 0;
			foreach ( $data as $hs ) :
				$id = isset( $hs['id'] ) ? sanitize_text_field( (string) $hs['id'] ) : '';
				$x  = isset( $hs['x'] ) ? floatval( $hs['x'] ) : 0.0;
				$y  = isset( $hs['y'] ) ? floatval( $hs['y'] ) : 0.0;
				$html = isset( $hs['html'] ) ? (string) $hs['html'] : '';
				$tooltip_id = isset( $hs['tooltip_id'] ) ? intval( $hs['tooltip_id'] ) : 0;
				if ( $tooltip_id && '' === trim( $html ) ) {
					$html = $this->tooltips->get_html( $tooltip_id );
				}
				// Compatibilità retroattiva: meta salvato con escape unicode (es. "\\u00e8").
				$html = Sanitizer::decode_unicode_escapes( $html );
				$html = $this->oembed->resolve( $html );
				if ( '' === $id ) { continue; }
				++$hotspot_index;
				$layer_dom_id = 'wphs-layer-' . $attachment_id . '-' . $id;
				$default_label = sprintf(
					/* translators: %d: hotspot index, starting from 1 */
					__( 'Hotspot %d', 'pleaseup-hotspots' ),
					$hotspot_index
				);
				/**
				 * Filter the accessible label announced by screen readers
				 * for a single hotspot dot.
				 *
				 * @since 3.0.0
				 *
				 * @param string              $label         Default label ("Hotspot N").
				 * @param array<string,mixed> $hs            Hotspot record.
				 * @param int                 $attachment_id Attachment ID.
				 */
				$aria_label = (string) apply_filters( 'wphs_hotspot_aria_label', $default_label, $hs, $attachment_id );
				$dot_color       = esc_attr( $opts['dot_color'] );
				$dot_border_color = esc_attr( $opts['dot_border_color'] );
				$_w = absint( $_sc_settings['dot_width']  ?: $size );
				$_h = absint( $_sc_settings['dot_height'] ?: $size );
				$_maxR = (int)floor(min($_w,$_h)/2);
				$_r = min( absint( $_sc_settings['dot_radius'] ?? $_maxR ), $_maxR );
				$dot_style = 'left:' . floatval( $x ) . '%;top:' . floatval( $y ) . '%;width:' . $_w . 'px;height:' . $_h . 'px;border-radius:' . $_r . 'px;';
				if ( 'image' === $opts['hotspot_style'] && $icon_url ) {
					$dot_style .= 'background:transparent url(' . esc_url( $icon_url ) . ') center/contain no-repeat;border:none;overflow:hidden;';
				} else {
					$dot_style .= 'background:' . $dot_color . ';border-color:' . $dot_border_color . ';';
				}
				$sel_icon_url_fe = ( 'image' === $opts['hotspot_style'] && ! empty( $opts['hotspot_selected_image_id'] ) ) ? wp_get_attachment_image_url( (int) $opts['hotspot_selected_image_id'], 'thumbnail' ) : '';
			?>
<div class="wphs-dot <?php echo esc_attr( $opts['hotspot_style'] ); ?>" role="button" tabindex="0" aria-label="<?php echo esc_attr( $aria_label ); ?>" aria-expanded="false" aria-controls="<?php echo esc_attr( $layer_dom_id ); ?>" data-id="<?php echo esc_attr( $id ); ?>" data-orig-x="<?php echo esc_attr( (string) $x ); ?>" data-orig-y="<?php echo esc_attr( (string) $y ); ?>"<?php if ( $sel_icon_url_fe ) echo ' data-sel-icon="' . esc_attr( $icon_url ) . '" data-sel-icon-selected="' . esc_attr( $sel_icon_url_fe ) . '"'; ?><?php if ( 'default' === $opts['hotspot_style'] || ( 'image' !== $opts['hotspot_style'] ) ) : ?> data-sel-fill="<?php echo esc_attr( $opts['dot_selected_fill'] ?? '#3fe0a0' ); ?>" data-sel-border="<?php echo esc_attr( $opts['dot_selected_border'] ?? '#3fe0a0' ); ?>"<?php endif; ?> style="<?php echo esc_attr( $dot_style ); ?>"></div>
			<?php endforeach; ?>
			</div>
			<?php
			$layer_index = 0;
			foreach ( $data as $hs ) :
				$id   = isset( $hs['id'] )   ? sanitize_text_field( (string) $hs['id'] ) : '';
				$html = isset( $hs['html'] )  ? (string) $hs['html'] : '';
				$tooltip_id = isset( $hs['tooltip_id'] ) ? intval( $hs['tooltip_id'] ) : 0;
				if ( $tooltip_id && '' === trim( $html ) ) {
					$html = $this->tooltips->get_html( $tooltip_id );
				}
				$html = Sanitizer::decode_unicode_escapes( $html );
				$html = $this->oembed->resolve( $html );
				if ( '' === $id ) { continue; }
				++$layer_index;
				$layer_dom_id = 'wphs-layer-' . $attachment_id . '-' . $id;
				$layer_label  = sprintf(
					/* translators: %d: hotspot index, starting from 1 */
					__( 'Hotspot %d details', 'pleaseup-hotspots' ),
					$layer_index
				);
			?>
<?php
				$tt_bg     = esc_attr( $_sc_settings['tooltip_bg']    ?: '#0b1220' );
				$tt_color  = esc_attr( $_sc_settings['tooltip_color'] ?: '#ffffff' );
				$tt_radius = absint( $_sc_settings['tooltip_radius'] ?: 12 );
				$tt_tail   = false;
				$tt_style  = 'background:' . $tt_bg . ';color:' . $tt_color . ';border-radius:' . $tt_radius . 'px;';
			?><div class="wphs-layer-wrap" id="<?php echo esc_attr( $layer_dom_id ); ?>" data-id="<?php echo esc_attr( $id ); ?>" role="dialog" aria-modal="false" aria-label="<?php echo esc_attr( $layer_label ); ?>" style="position:absolute;top:0;left:0;z-index:3;display:none"><div class="wphs-layer" style="<?php echo esc_attr( $tt_style ); ?>;position:relative"><?php
				// Allow iframe for oEmbed players (YouTube/Vimeo)
				$allowed = array_merge( wp_kses_allowed_html( 'post' ), array(
					'iframe' => array(
						'src'             => true,
						'width'           => true,
						'height'          => true,
						'frameborder'     => true,
						'allowfullscreen' => true,
						'allow'           => true,
						'title'           => true,
						'style'           => true,
						'class'           => true,
					),
				) );
				/**
				 * Filter the per-hotspot tooltip HTML right before it
				 * is sanitized with wp_kses().
				 *
				 * Use this to inject tracking, replace placeholders
				 * (`{{user.name}}`, `[shortcodes]`, …), or strip
				 * provider-specific markup.
				 *
				 * @since 3.0.0
				 *
				 * @param string              $html          HTML body of the tooltip after
				 *                                           Unicode-escape decoding and oEmbed resolution.
				 * @param array<string,mixed> $hs            Single hotspot record.
				 * @param int                 $attachment_id Attachment ID being rendered.
				 */
				$html = (string) apply_filters( 'wphs_tooltip_html', $html, $hs, $attachment_id );
				echo wp_kses( $html, $allowed );
			?></div></div>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
