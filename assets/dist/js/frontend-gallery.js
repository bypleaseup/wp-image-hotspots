/* Pleaseup Hotspots — frontend gallery carousel.
 *
 * Self-initialising carousel for every `.wphs-gallery[data-cols-d]` found
 * in the DOM. Reads its per-instance config from data-* attributes so the
 * server-side renderer does not need to emit any inline <script>.
 *
 * - data-cols-d : columns on desktop (>600px)
 * - data-cols-m : columns on mobile  (<=600px)
 *
 * Cooperates with the hotspot layer code in frontend.js by closing any
 * open .wphs-dot.is-open / .wphs-layer-wrap.is-visible on slide changes,
 * so the tooltip never lingers off-screen after a swipe. The helpers are
 * inlined to keep this file independent from the jQuery closure of
 * frontend.js (so it works on pages without hotspots, e.g. admin preview).
 */
(function(){
	function initAllGalleries() {
		var galleries = document.querySelectorAll( '.wphs-gallery[data-cols-d]:not([data-wphs-gallery-ready])' );
		for ( var i = 0; i < galleries.length; i++ ) {
			galleries[ i ].setAttribute( 'data-wphs-gallery-ready', '1' );
			initGallery( galleries[ i ] );
		}
	}

	// Expose so the admin preview (which injects HTML via AJAX after the
	// DOM is already ready) can ask us to scan for fresh `.wphs-gallery`
	// nodes and bind the carousel handlers.
	window.wphs_init_galleries = initAllGalleries;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAllGalleries );
	} else {
		initAllGalleries();
	}

	function initGallery( wrap ) {
		var track    = wrap.querySelector( '.wphs-gallery-track' );
		var slides   = Array.prototype.slice.call( wrap.querySelectorAll( '.wphs-gallery-slide' ) );
		var dotsWrap = wrap.querySelector( '.wphs-gl-dots' );
		var btnPrev  = wrap.querySelector( '.wphs-gl-prev' );
		var btnNext  = wrap.querySelector( '.wphs-gl-next' );
		var total    = slides.length;
		var cols     = parseInt( wrap.getAttribute( 'data-cols-d' ), 10 ) || 1;
		var colsD    = cols;
		var colsM    = parseInt( wrap.getAttribute( 'data-cols-m' ), 10 ) || 1;
		var cur      = 0;
		var animating = false;

		if ( ! track ) { return; }

		function pages() { return Math.ceil( total / cols ); }
		function curPage() { return Math.round( cur / cols ); }

		function closeOpenHotspots() {
			var dots = wrap.querySelectorAll( '.wphs-dot' );
			for ( var d = 0; d < dots.length; d++ ) {
				dots[ d ].classList.remove( 'is-open' );
				if ( dots[ d ].dataset.origStyle !== undefined ) {
					dots[ d ].style.cssText = dots[ d ].dataset.origStyle;
				}
			}
			var wraps = wrap.querySelectorAll( '.wphs-layer-wrap.is-visible' );
			for ( var w = 0; w < wraps.length; w++ ) {
				wraps[ w ].classList.remove( 'is-visible' );
				wraps[ w ].style.display = 'none';
			}
		}

		function buildDots() {
			if ( ! dotsWrap ) { return; }
			var n = pages();
			dotsWrap.innerHTML = '';
			if ( n <= 1 ) { dotsWrap.style.display = 'none'; return; }
			dotsWrap.style.display = 'flex';
			for ( var k = 0; k < n; k++ ) {
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'wphs-gl-dot' + ( k === 0 ? ' wphs-gl-dot-active' : '' );
				btn.dataset.index = k * cols;
				btn.style.cssText = 'width:' + ( k === 0 ? '18' : '6' ) + 'px;height:6px;border-radius:' + ( k === 0 ? '3px' : '50%' ) + ';border:none;padding:0;cursor:pointer;background:' + ( k === 0 ? 'rgba(11,18,32,1)' : 'rgba(11,18,32,.2)' ) + ';transition:all .2s';
				( function( idx ) {
					btn.addEventListener( 'click', function() {
						if ( ! animating ) { moveTo( idx * cols, true ); }
					} );
				} )( k );
				dotsWrap.appendChild( btn );
			}
		}

		function updateDots() {
			if ( ! dotsWrap ) { return; }
			var dotBtns = dotsWrap.querySelectorAll( '.wphs-gl-dot' );
			var pg = curPage();
			for ( var d = 0; d < dotBtns.length; d++ ) {
				var a = d === pg;
				dotBtns[ d ].style.width = a ? '18px' : '6px';
				dotBtns[ d ].style.borderRadius = a ? '3px' : '50%';
				dotBtns[ d ].style.background = a ? 'rgba(11,18,32,1)' : 'rgba(11,18,32,.2)';
				dotBtns[ d ].classList.toggle( 'wphs-gl-dot-active', a );
			}
		}

		function setArrows() {
			// Hide arrows when every image already fits the current cols
			// (nothing to scroll). The button DOM only exists when the
			// admin asked for arrows; this is just the visual gate on top.
			var show = total > cols;
			if ( btnPrev ) { btnPrev.style.display = show ? 'flex' : 'none'; }
			if ( btnNext ) { btnNext.style.display = show ? 'flex' : 'none'; }
		}

		function moveTo( idx, animate ) {
			if ( animate !== false ) { closeOpenHotspots(); }
			track.style.transition = animate === false ? 'none' : 'transform .35s ease';
			track.style.transform  = 'translateX(-' + ( idx * ( 100 / cols ) ) + '%)';
			cur = idx;
			updateDots();
			if ( window.positionAllOverlays ) { window.positionAllOverlays(); }
		}

		function goNext() {
			if ( animating ) { return; }
			if ( total <= cols ) { return; }
			var nextCur = cur + cols;
			if ( nextCur >= total ) {
				animating = true;
				moveTo( nextCur, true );
				setTimeout( function() { moveTo( 0, false ); animating = false; }, 360 );
			} else {
				moveTo( nextCur, true );
			}
		}

		function goPrev() {
			if ( animating ) { return; }
			if ( total <= cols ) { return; }
			var prevCur = cur - cols;
			if ( prevCur < 0 ) {
				var lastPage = ( pages() - 1 ) * cols;
				animating = true;
				moveTo( lastPage + cols, false );
				track.getBoundingClientRect();
				moveTo( lastPage, true );
				setTimeout( function() { animating = false; }, 360 );
			} else {
				moveTo( prevCur, true );
			}
		}

		buildDots();
		setArrows();
		if ( btnPrev ) { btnPrev.addEventListener( 'click', goPrev ); }
		if ( btnNext ) { btnNext.addEventListener( 'click', goNext ); }
		moveTo( 0, false );

		var sx = 0;
		wrap.addEventListener( 'touchstart', function( e ) { sx = e.touches[ 0 ].clientX; }, { passive: true } );
		wrap.addEventListener( 'touchend', function( e ) {
			var dx = e.changedTouches[ 0 ].clientX - sx;
			if ( Math.abs( dx ) > 40 ) { if ( dx < 0 ) { goNext(); } else { goPrev(); } }
		}, { passive: true } );

		window.addEventListener( 'resize', function() {
			var c2 = window.innerWidth <= 600 ? colsM : colsD;
			if ( c2 !== cols ) {
				cols = c2;
				buildDots();
				setArrows();
				moveTo( 0, false );
			}
		} );
	}
})();
