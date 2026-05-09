jQuery(function($){

	/*
	 * computeVisibleImageRect(img)
	 *
	 * Returns the visible portion of a potentially object-fit:cover cropped image
	 * as a rect { top, left, width, height } in viewport coordinates.
	 *
	 * For a cover image:
	 *   - The container clips the image to its own box.
	 *   - The image is scaled so its shorter dimension fills the container.
	 *   - The visible area is the container rect itself.
	 *
	 * For an uncropped image (object-fit:fill / contain / none, or no object-fit):
	 *   - The image rect IS the visible area.
	 *
	 * We detect cover/contain by comparing the rendered aspect ratio against
	 * the natural aspect ratio, then compute the scaled+offset natural rect and
	 * intersect it with the container to get the truly visible area.
	 */
	function computeVisibleImageRect(img){
		var style      = window.getComputedStyle(img);
		var fit        = style.objectFit || 'fill';
		var imgRect    = img.getBoundingClientRect();
		var natW       = img.naturalWidth  || imgRect.width;
		var natH       = img.naturalHeight || imgRect.height;

		// For fill / none / initial: the entire img rect is visible.
		if (fit !== 'cover' && fit !== 'contain'){
			return { top: imgRect.top, left: imgRect.left,
				     width: imgRect.width, height: imgRect.height };
		}

		var cW = imgRect.width;
		var cH = imgRect.height;
		var natAR = natW / natH;
		var boxAR = cW   / cH;

		var scaledW, scaledH;
		if (fit === 'cover'){
			// Scale so the image FILLS the box (smaller dimension = box dimension).
			if (natAR > boxAR){
				// Image is wider than box → height fills, width is cropped.
				scaledH = cH;
				scaledW = cH * natAR;
			} else {
				// Image is taller than box → width fills, height is cropped.
				scaledW = cW;
				scaledH = cW / natAR;
			}
		} else {
			// contain: Scale so image FITS inside box (larger dimension = box dimension).
			if (natAR > boxAR){
				scaledW = cW;
				scaledH = cW / natAR;
			} else {
				scaledH = cH;
				scaledW = cH * natAR;
			}
		}

		// object-position (default "50% 50%").
		var pos    = style.objectPosition || '50% 50%';
		var parts  = pos.split(' ');
		var posX   = parts[0] || '50%';
		var posY   = parts[1] || parts[0] || '50%';

		function resolvePct(val, available, scaled){
			if (val.indexOf('%') !== -1){
				var pct = parseFloat(val) / 100;
				return pct * (available - scaled);
			}
			return parseFloat(val) || 0;
		}

		var offsetX = resolvePct(posX, cW, scaledW);
		var offsetY = resolvePct(posY, cH, scaledH);

		// For cover the scaled image is larger than the box — offset is negative
		// (the image origin is shifted left/up relative to the container).
		// The visible area is the intersection of the scaled image with the box.
		var visLeft   = imgRect.left + Math.max(0, offsetX);
		var visTop    = imgRect.top  + Math.max(0, offsetY);
		var visRight  = imgRect.left + Math.min(cW, offsetX + scaledW);
		var visBottom = imgRect.top  + Math.min(cH, offsetY + scaledH);

		return {
			left:   visLeft,
			top:    visTop,
			width:  visRight  - visLeft,
			height: visBottom - visTop
		};
	}

	/*
	 * positionOverlay($fig)
	 *
	 * 1. Computes the visible image rect (accounting for object-fit cropping).
	 * 2. Positions .wphs-img-overlay exactly over that rect.
	 * 3. Re-maps dot coordinates: the original x/y % are % of the NATURAL image.
	 *    We need to convert them to % of the VISIBLE area so they sit correctly.
	 *    Dots that fall outside the visible area are hidden by overflow:hidden on
	 *    the overlay — no extra code needed.
	 *
	 * Dot coordinate remapping:
	 *   The dot was placed at (x%, y%) of the natural image.
	 *   After cover scaling, the natural image maps to a scaled rect of size
	 *   (scaledW × scaledH) offset by (offsetX, offsetY) inside the container.
	 *   The dot's pixel position inside the scaled image = (x/100 * scaledW, y/100 * scaledH).
	 *   Minus the crop offset gives its position inside the visible area.
	 *   Convert back to % of the visible area for the CSS left/top.
	 */
	function positionOverlay($fig){
		var $img     = $fig.find('img').first();
		var $overlay = $fig.find('.wphs-img-overlay').first();
		if (!$img.length || !$overlay.length) return;

		var img = $img[0];
		if (!img.complete || img.naturalWidth === 0){
			$img.one('load error', function(){ positionOverlay($fig); });
			return;
		}

		var style   = window.getComputedStyle(img);
		var fit     = style.objectFit || 'fill';
		var natW    = img.naturalWidth;
		var natH    = img.naturalHeight;

		// Get dimensions immune to CSS transform (slider translateX)
		// offsetWidth/Height work even when element is off-screen via transform
		var cW = img.offsetWidth  || $fig[0].offsetWidth;
		var cH = img.offsetHeight || $fig[0].offsetHeight;
		if (!cW || !cH) return;

		// visRect relative to figure: always (0,0,cW,cH) since img fills the figure
		var visRect;
		var bcr = img.getBoundingClientRect();
		if(bcr.width > 0 && typeof computeVisibleImageRect === 'function'){
			// Image is in viewport — use precise calculation
			visRect = computeVisibleImageRect(img);
			// Convert to figure-relative
			var figBcr = $fig[0].getBoundingClientRect();
			visRect = { left: visRect.left - figBcr.left, top: visRect.top - figBcr.top, width: visRect.width, height: visRect.height };
		} else {
			// Image is off-screen (slider): compute visRect from natural dimensions
			if(fit === 'contain' || fit === 'cover'){
				var natAR2 = natW / natH;
				var boxAR2 = cW / cH;
				var sW2, sH2;
				if(fit==='cover'){
					if(natAR2>boxAR2){sH2=cH;sW2=cH*natAR2;}else{sW2=cW;sH2=cW/natAR2;}
				}else{
					if(natAR2>boxAR2){sW2=cW;sH2=cW/natAR2;}else{sH2=cH;sW2=cH*natAR2;}
				}
				var oX2 = Math.max(0,(cW-sW2)/2);
				var oY2 = Math.max(0,(cH-sH2)/2);
				var vW2 = Math.min(sW2,cW);
				var vH2 = Math.min(sH2,cH);
				visRect = { left: oX2, top: oY2, width: vW2, height: vH2 };
			} else {
				visRect = { left: 0, top: 0, width: cW, height: cH };
			}
		}

		// Position the overlay over the visible area (visRect is already fig-relative).
		$overlay.css({
			left:   visRect.left   + 'px',
			top:    visRect.top    + 'px',
			width:  visRect.width  + 'px',
			height: visRect.height + 'px'
		});

		// Remap dot coordinates from natural-image % to visible-area %.
		// visRect.width/height = visible image area; dots are in % of natural image.
		var natAR = natW / natH;
		var boxAR = cW   / cH;
		var scaledW, scaledH;
		if (fit === 'cover'){
			if (natAR > boxAR){ scaledH = cH; scaledW = cH * natAR; }
			else               { scaledW = cW; scaledH = cW / natAR; }
		} else if (fit === 'contain') {
			if (natAR > boxAR){ scaledW = cW; scaledH = cW / natAR; }
			else               { scaledH = cH; scaledW = cH * natAR; }
		} else {
			scaledW = cW; scaledH = cH;
		}
		var cropX = Math.max(0, (scaledW - cW) / 2);
		var cropY = Math.max(0, (scaledH - cH) / 2);
		$overlay.find('.wphs-dot').each(function(){
			var $dot  = $(this);
			var origX = parseFloat($dot.data('orig-x'));
			var origY = parseFloat($dot.data('orig-y'));
			if (isNaN(origX)) return;
			var px = (origX / 100) * scaledW - cropX;
			var py = (origY / 100) * scaledH - cropY;
			var vx = (px / visRect.width)  * 100;
			var vy = (py / visRect.height) * 100;
			$dot.css({ left: vx + '%', top: vy + '%' });
		});
	}

	function positionAllOverlays(){
		$('.wphs-figure').each(function(){ positionOverlay($(this)); });
	}

	positionAllOverlays();
	$(window).on('resize orientationchange load', positionAllOverlays);

	if (typeof ResizeObserver !== 'undefined'){
		var _ro = new ResizeObserver(function(){ positionAllOverlays(); });
		$('.wphs-figure').each(function(){
			_ro.observe(this);
			var img = $(this).find('img')[0];
			if (img) _ro.observe(img);
		});
	}

	function hexToRgba(hex,a){
		hex=hex.replace('#','');
		if(hex.length===3)hex=hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
		var r=parseInt(hex.substr(0,2),16),g=parseInt(hex.substr(2,2),16),b=parseInt(hex.substr(4,2),16);
		return r+','+g+','+b+','+a;
	}
	function closeAll(ctx){
		// Stop any playing iframes (YouTube/Vimeo) before hiding
		$(ctx).find('.wphs-layer-wrap iframe').each(function(){
			var src = $(this).attr('src');
			if(src){ $(this).attr('src', '').attr('src', src); }
		});
		$(ctx).find('.wphs-layer-wrap').hide();
		$(ctx).find('.wphs-dot').removeClass('is-open').attr('aria-expanded','false');
	}

	$(document).on('click', '.wphs-figure .wphs-dot', function(e){
		e.preventDefault();
		e.stopPropagation();
		var $dot = $(this);
		var $fig = $dot.closest('.wphs-figure');
		var id   = $dot.data('id');
		var wasOpen = $dot.hasClass('is-open');
		// Restore ALL dots to their original inline style
		$fig.find('.wphs-dot').each(function(){
			if(this.dataset.origStyle !== undefined) this.style.cssText=this.dataset.origStyle;
		});
		closeAll($fig);
		if (wasOpen) return;
		// Save original inline style before modifying
		if($dot[0].dataset.origStyle === undefined) $dot[0].dataset.origStyle = $dot[0].style.cssText;
		$dot.addClass('is-open').attr('aria-expanded','true');
		// Swap to selected icon (custom icon mode)
		var selIcon = $dot.data('sel-icon-selected');
		if(selIcon){
			$dot.css('background','transparent url('+selIcon+') center/contain no-repeat');
		} else {
			// Default dot: apply selected fill/border colors
			var sf = $dot.data('sel-fill');
			var sb = $dot.data('sel-border');
			if(sf){
				var alpha1='rgba('+hexToRgba(sb,0.35)+')';
				var alpha2='rgba('+hexToRgba(sb,0.12)+')';
				$dot.css({background:sf,'border-color':sb,'box-shadow':'0 0 0 4px '+alpha1+',0 0 0 7px '+alpha2});
			}
		}

		var $wrap = $fig.find('.wphs-layer-wrap[data-id="'+id+'"]');
		if (!$wrap.length) return;
		var $layer = $wrap.find('.wphs-layer');

		var $overlay = $fig.find('.wphs-img-overlay').first();
		var refEl    = $overlay.length ? $overlay[0] : $fig[0];
		var refRect  = refEl.getBoundingClientRect();
		var figRect  = $fig[0].getBoundingClientRect();
		var dotRect  = $dot[0].getBoundingClientRect();

		var margin = 12;

		// Make visible off-screen first so we can inspect content
		$wrap.css({ left: -9999, top: -9999, display: 'block' });

		// Check for oEmbed player AFTER display:block
		var hasEmbed = $layer.find('.wphs-oembed-wrap').length > 0;
		if (hasEmbed) {
			// Video tooltip: wide, no max-height constraint
			var embedW = Math.min(560, Math.max(280, Math.round(refRect.width * 0.82)));
			$layer.css({ 'width': embedW+'px', 'max-width': embedW+'px', 'max-height': 'none', 'overflow-y': 'visible' });
		} else {
			var maxW = Math.min(320, Math.max(160, Math.round(refRect.width  * 0.45)));
			var maxH = Math.min(220, Math.max(100, Math.round(refRect.height * 0.55)));
			$layer.css({ 'max-width': maxW+'px', 'max-height': maxH+'px', 'overflow-y': 'auto', 'overscroll-behavior': 'contain' });
		}

		var layerRect = $layer[0].getBoundingClientRect();
		var dotX  = (dotRect.left - refRect.left) + dotRect.width  / 2;
		var dotY  = (dotRect.top  - refRect.top)  + dotRect.height / 2;
		var gap   = 14;
		var left  = (dotX < refRect.width  / 2) ? dotX + gap : dotX - layerRect.width  - gap;
		var top   = (dotY < refRect.height / 2) ? dotY + gap : dotY - layerRect.height - gap;
		left = Math.max(margin, Math.min(left, refRect.width  - layerRect.width  - margin));
		top  = Math.max(margin, Math.min(top,  refRect.height - layerRect.height - margin));

		var offX = refRect.left - figRect.left;
		var offY = refRect.top  - figRect.top;
		$wrap.css({ left: (offX + left)+'px', top: (offY + top)+'px' }).addClass('is-visible');


	});

	$(document).on('click', function(){ closeAll(document); });

	// A11y — Enter/Space on a focused hotspot triggers the same flow as click.
	$(document).on('keydown', '.wphs-figure .wphs-dot', function(e){
		// 13 = Enter, 32 = Space
		if(e.which === 13 || e.which === 32){
			e.preventDefault();
			$(this).trigger('click');
		}
	});

	// A11y — Esc closes the open tooltip and returns focus to the originating dot.
	$(document).on('keydown', function(e){
		if(e.which !== 27) return;
		var $opened = $('.wphs-figure .wphs-dot.is-open');
		if(!$opened.length) return;
		closeAll(document);
		$opened.first().trigger('focus');
	});
});
