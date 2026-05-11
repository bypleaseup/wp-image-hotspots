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

		ob_start(); ?>
<style>
#<?php echo esc_attr($uid); ?>{position:relative;width:100%;overflow:hidden;box-sizing:border-box}
#<?php echo esc_attr($uid); ?> .wphs-gallery-track{display:flex;width:100%;transition:transform .35s ease;align-items:center}
#<?php echo esc_attr($uid); ?> .wphs-gallery-slide{min-width:calc(100% / <?php echo $cols_d; ?>);width:calc(100% / <?php echo $cols_d; ?>);flex-shrink:0;box-sizing:border-box;padding:0 4px}
@media(max-width:600px){#<?php echo esc_attr($uid); ?> .wphs-gallery-slide{min-width:calc(100% / <?php echo $cols_m; ?>);width:calc(100% / <?php echo $cols_m; ?>);padding:0 4px}}
#<?php echo esc_attr($uid); ?> .wphs-gallery-slide .wphs-figure{display:block;width:100%;line-height:0}
#<?php echo esc_attr($uid); ?> .wphs-gallery-slide .wphs-figure img,
#<?php echo esc_attr($uid); ?> .wphs-gallery-slide img{width:100%;height:auto;display:block;border-radius:<?php echo $img_radius; ?>px}
#<?php echo esc_attr($uid); ?> .wphs-gallery-slide.has-caption .wphs-figure img,
#<?php echo esc_attr($uid); ?> .wphs-gallery-slide.has-caption img{border-radius:<?php echo $img_radius; ?>px <?php echo $img_radius; ?>px 0 0 !important}
#<?php echo esc_attr($uid); ?> .wphs-gallery-slide.has-caption .wphs-slide-caption{border-radius:0 0 <?php echo $img_radius; ?>px <?php echo $img_radius; ?>px}
</style>
<div class="wphs-gallery" id="<?php echo esc_attr($uid); ?>">
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
	<button type="button" class="wphs-gl-arrow wphs-gl-prev" aria-label="<?php echo esc_attr_x( 'Previous', 'gallery navigation', 'image-hotspots-tool' ); ?>" style="position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,.45);border:none;border-radius:50%;width:36px;height:36px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;z-index:10;padding:0"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg></button>
	<button type="button" class="wphs-gl-arrow wphs-gl-next" aria-label="<?php echo esc_attr_x( 'Next', 'gallery navigation', 'image-hotspots-tool' ); ?>" style="position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,.45);border:none;border-radius:50%;width:36px;height:36px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;z-index:10;padding:0"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg></button>
	<?php endif; ?>
	<?php if ( $show_dots ) : ?>
	<div class="wphs-gl-dots" style="display:flex;justify-content:center;gap:6px;padding:10px 0 4px"></div>
	<?php endif; ?>
</div>
<script>
(function(){
	var wrap=document.getElementById(<?php echo wp_json_encode($uid); ?>);
	if(!wrap)return;
	var track=wrap.querySelector('.wphs-gallery-track');
	var slides=Array.prototype.slice.call(wrap.querySelectorAll('.wphs-gallery-slide'));
	var dotsWrap=wrap.querySelector('.wphs-gl-dots');
	var btnPrev=wrap.querySelector('.wphs-gl-prev');
	var btnNext=wrap.querySelector('.wphs-gl-next');
	var total=slides.length;
	var cols=<?php echo $cols_d; ?>;
	var colsM=<?php echo $cols_m; ?>;
	var cur=0;
	var animating=false;

	function pages(){ return Math.ceil(total/cols); }
	function curPage(){ return Math.round(cur/cols); }

	// Build dots dynamically based on current cols
	function buildDots(){
		if(!dotsWrap)return;
		var n=pages();
		dotsWrap.innerHTML='';
		if(n<=1){dotsWrap.style.display='none';return;}
		dotsWrap.style.display='flex';
		for(var i=0;i<n;i++){
			var btn=document.createElement('button');
			btn.type='button';
			btn.className='wphs-gl-dot'+(i===0?' wphs-gl-dot-active':'');
			btn.dataset.index=i*cols;
			btn.style.cssText='width:'+(i===0?'18':'6')+'px;height:6px;border-radius:'+(i===0?'3px':'50%')+';border:none;padding:0;cursor:pointer;background:'+(i===0?'rgba(11,18,32,1)':'rgba(11,18,32,.2)')+';transition:all .2s';
			(function(idx){btn.addEventListener('click',function(){if(!animating)moveTo(idx*cols,true);});})(i);
			dotsWrap.appendChild(btn);
		}
	}

	function updateDots(){
		if(!dotsWrap)return;
		var dotBtns=dotsWrap.querySelectorAll('.wphs-gl-dot');
		var pg=curPage();
		dotBtns&&dotBtns.forEach(function(d,i){
			var a=i===pg;
			d.style.width=a?'18px':'6px';
			d.style.borderRadius=a?'3px':'50%';
			d.style.background=a?'rgba(11,18,32,1)':'rgba(11,18,32,.2)';
			d.classList.toggle('wphs-gl-dot-active',a);
		});
	}

	function setArrows(){
		// Hide arrows when every image already fits the current cols
		// (nothing to scroll). Visibility is admin choice (the buttons
		// only exist when $show_arrows) AND total > cols. Recomputed
		// on resize via the listener at the bottom of this IIFE.
		var show = total > cols;
		if(btnPrev)btnPrev.style.display = show ? 'flex' : 'none';
		if(btnNext)btnNext.style.display = show ? 'flex' : 'none';
	}

	function moveTo(idx,animate){
		// Close any open tooltip and deselect hotspot on slide change
		if(animate!==false && typeof closeAll==='function') closeAll(wrap);
		// Also remove is-open class from all dots in this slider
		wrap.querySelectorAll('.wphs-dot').forEach(function(d){
			d.classList.remove('is-open');
			// Restore original inline style (stored as dataset)
			if(d.dataset.origStyle !== undefined) d.style.cssText=d.dataset.origStyle;
		});
		wrap.querySelectorAll('.wphs-layer-wrap.is-visible').forEach(function(w){
			w.classList.remove('is-visible'); w.style.display='none';
		});
		track.style.transition=animate===false?'none':'transform .35s ease';
		track.style.transform='translateX(-'+(idx*(100/cols))+'%)';
		cur=idx;
		updateDots();
		if(window.positionAllOverlays)positionAllOverlays();
	}

	function goNext(){
		if(animating)return;
		if(total<=cols)return; // safety: arrows are hidden in this case
		var nextCur=cur+cols;
		if(nextCur>=total){
			animating=true;
			moveTo(nextCur,true);
			setTimeout(function(){moveTo(0,false);animating=false;},360);
		} else {
			moveTo(nextCur,true);
		}
	}

	function goPrev(){
		if(animating)return;
		if(total<=cols)return; // safety: arrows are hidden in this case
		var prevCur=cur-cols;
		if(prevCur<0){
			var lastPage=(pages()-1)*cols;
			animating=true;
			moveTo(lastPage+cols,false);
			track.getBoundingClientRect();
			moveTo(lastPage,true);
			setTimeout(function(){animating=false;},360);
		} else {
			moveTo(prevCur,true);
		}
	}

	// Init
	buildDots();
	setArrows();
	// Listener attach is unconditional: cols can shrink on resize and
	// reveal previously-hidden arrows; setArrows() is what gates the
	// visual state.
	if(btnPrev)btnPrev.addEventListener('click',goPrev);
	if(btnNext)btnNext.addEventListener('click',goNext);
	moveTo(0,false);

	var sx=0;
	wrap.addEventListener('touchstart',function(e){sx=e.touches[0].clientX;},{passive:true});
	wrap.addEventListener('touchend',function(e){
		var dx=e.changedTouches[0].clientX-sx;
		if(Math.abs(dx)>40){if(dx<0)goNext();else goPrev();}
	},{passive:true});

	window.addEventListener('resize',function(){
		var c2=window.innerWidth<=600?colsM:<?php echo $cols_d; ?>;
		if(c2!==cols){
			cols=c2;
			buildDots();
			setArrows();
			moveTo(0,false);
		}
	});
})();
</script>
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

		ob_start(); ?>
<style>
#<?php echo esc_attr($uid); ?>{position:relative;width:100%;overflow:hidden;box-sizing:border-box}
#<?php echo esc_attr($uid); ?> .wphs-gallery-track{display:flex;width:100%;transition:transform .35s ease;align-items:center}
#<?php echo esc_attr($uid); ?> .wphs-gallery-slide{min-width:calc(100% / <?php echo $cols_d; ?>);width:calc(100% / <?php echo $cols_d; ?>);flex-shrink:0;box-sizing:border-box;padding:0 4px}
@media(max-width:600px){#<?php echo esc_attr($uid); ?> .wphs-gallery-slide{min-width:calc(100% / <?php echo $cols_m; ?>);width:calc(100% / <?php echo $cols_m; ?>);padding:0 4px}}
#<?php echo esc_attr($uid); ?> .wphs-gallery-slide img{width:100%;height:auto;display:block;border-radius:<?php echo $img_radius; ?>px}
#<?php echo esc_attr($uid); ?> .wphs-gallery-slide .wphs-figure{display:block;width:100%;line-height:0}
#<?php echo esc_attr($uid); ?> .wphs-gallery-slide.has-caption img{border-radius:<?php echo $img_radius; ?>px <?php echo $img_radius; ?>px 0 0 !important}
#<?php echo esc_attr($uid); ?> .wphs-gallery-slide.has-caption .wphs-slide-caption{border-radius:0 0 <?php echo $img_radius; ?>px <?php echo $img_radius; ?>px}
</style>
<div class="wphs-gallery" id="<?php echo esc_attr($uid); ?>">
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
	<button type="button" class="wphs-gl-arrow wphs-gl-prev" aria-label="<?php echo esc_attr_x( 'Previous', 'gallery navigation', 'image-hotspots-tool' ); ?>" style="position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,.45);border:none;border-radius:50%;width:36px;height:36px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;z-index:10;padding:0"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg></button>
	<button type="button" class="wphs-gl-arrow wphs-gl-next" aria-label="<?php echo esc_attr_x( 'Next', 'gallery navigation', 'image-hotspots-tool' ); ?>" style="position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,.45);border:none;border-radius:50%;width:36px;height:36px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;z-index:10;padding:0"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg></button>
	<?php endif; ?>
	<?php if ( $show_dots ) : ?>
	<div class="wphs-gl-dots" style="display:flex;justify-content:center;gap:6px;padding:10px 0 4px"></div>
	<?php endif; ?>
</div>
<script>
(function(){
	var wrap=document.getElementById(<?php echo wp_json_encode($uid); ?>);
	if(!wrap)return;
	var track=wrap.querySelector('.wphs-gallery-track');
	var slides=Array.prototype.slice.call(wrap.querySelectorAll('.wphs-gallery-slide'));
	var dotsWrap=wrap.querySelector('.wphs-gl-dots');
	var btnPrev=wrap.querySelector('.wphs-gl-prev');
	var btnNext=wrap.querySelector('.wphs-gl-next');
	var total=slides.length;
	var cols=<?php echo $cols_d; ?>;
	var colsM=<?php echo $cols_m; ?>;
	var cur=0;
	var animating=false;

	function pages(){ return Math.ceil(total/cols); }
	function curPage(){ return Math.round(cur/cols); }

	// Build dots dynamically based on current cols
	function buildDots(){
		if(!dotsWrap)return;
		var n=pages();
		dotsWrap.innerHTML='';
		if(n<=1){dotsWrap.style.display='none';return;}
		dotsWrap.style.display='flex';
		for(var i=0;i<n;i++){
			var btn=document.createElement('button');
			btn.type='button';
			btn.className='wphs-gl-dot'+(i===0?' wphs-gl-dot-active':'');
			btn.dataset.index=i*cols;
			btn.style.cssText='width:'+(i===0?'18':'6')+'px;height:6px;border-radius:'+(i===0?'3px':'50%')+';border:none;padding:0;cursor:pointer;background:'+(i===0?'rgba(11,18,32,1)':'rgba(11,18,32,.2)')+';transition:all .2s';
			(function(idx){btn.addEventListener('click',function(){if(!animating)moveTo(idx*cols,true);});})(i);
			dotsWrap.appendChild(btn);
		}
	}

	function updateDots(){
		if(!dotsWrap)return;
		var dotBtns=dotsWrap.querySelectorAll('.wphs-gl-dot');
		var pg=curPage();
		dotBtns&&dotBtns.forEach(function(d,i){
			var a=i===pg;
			d.style.width=a?'18px':'6px';
			d.style.borderRadius=a?'3px':'50%';
			d.style.background=a?'rgba(11,18,32,1)':'rgba(11,18,32,.2)';
			d.classList.toggle('wphs-gl-dot-active',a);
		});
	}

	function setArrows(){
		// Hide arrows when every image already fits the current cols
		// (nothing to scroll). Visibility is admin choice (the buttons
		// only exist when $show_arrows) AND total > cols. Recomputed
		// on resize via the listener at the bottom of this IIFE.
		var show = total > cols;
		if(btnPrev)btnPrev.style.display = show ? 'flex' : 'none';
		if(btnNext)btnNext.style.display = show ? 'flex' : 'none';
	}

	function moveTo(idx,animate){
		// Close any open tooltip and deselect hotspot on slide change
		if(animate!==false && typeof closeAll==='function') closeAll(wrap);
		// Also remove is-open class from all dots in this slider
		wrap.querySelectorAll('.wphs-dot').forEach(function(d){
			d.classList.remove('is-open');
			// Restore original inline style (stored as dataset)
			if(d.dataset.origStyle !== undefined) d.style.cssText=d.dataset.origStyle;
		});
		wrap.querySelectorAll('.wphs-layer-wrap.is-visible').forEach(function(w){
			w.classList.remove('is-visible'); w.style.display='none';
		});
		track.style.transition=animate===false?'none':'transform .35s ease';
		track.style.transform='translateX(-'+(idx*(100/cols))+'%)';
		cur=idx;
		updateDots();
		if(window.positionAllOverlays)positionAllOverlays();
	}

	function goNext(){
		if(animating)return;
		if(total<=cols)return; // safety: arrows are hidden in this case
		var nextCur=cur+cols;
		if(nextCur>=total){
			animating=true;
			moveTo(nextCur,true);
			setTimeout(function(){moveTo(0,false);animating=false;},360);
		} else {
			moveTo(nextCur,true);
		}
	}

	function goPrev(){
		if(animating)return;
		if(total<=cols)return; // safety: arrows are hidden in this case
		var prevCur=cur-cols;
		if(prevCur<0){
			var lastPage=(pages()-1)*cols;
			animating=true;
			moveTo(lastPage+cols,false);
			track.getBoundingClientRect();
			moveTo(lastPage,true);
			setTimeout(function(){animating=false;},360);
		} else {
			moveTo(prevCur,true);
		}
	}

	// Init
	buildDots();
	setArrows();
	// Listener attach is unconditional: cols can shrink on resize and
	// reveal previously-hidden arrows; setArrows() is what gates the
	// visual state.
	if(btnPrev)btnPrev.addEventListener('click',goPrev);
	if(btnNext)btnNext.addEventListener('click',goNext);
	moveTo(0,false);

	var sx=0;
	wrap.addEventListener('touchstart',function(e){sx=e.touches[0].clientX;},{passive:true});
	wrap.addEventListener('touchend',function(e){
		var dx=e.changedTouches[0].clientX-sx;
		if(Math.abs(dx)>40){if(dx<0)goNext();else goPrev();}
	},{passive:true});

	window.addEventListener('resize',function(){
		var c2=window.innerWidth<=600?colsM:<?php echo $cols_d; ?>;
		if(c2!==cols){
			cols=c2;
			buildDots();
			setArrows();
			moveTo(0,false);
		}
	});
})();
</script>
		<?php
		return (string) ob_get_clean();
	}
}
