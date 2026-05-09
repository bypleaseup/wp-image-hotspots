(function($){
	"use strict";
	if (typeof window.WPHS_ADMIN === 'undefined') { return; }
	var cfg = window.WPHS_ADMIN || {};
	var i18n = cfg.i18n || {};

	function wphsToast(msg, type){
		type = type || 'info';
		var el = document.getElementById('wphs-toast');
		if(!el){
			el = document.createElement('div');
			el.id = 'wphs-toast';
			el.className = 'wphs-toast is-info';
			el.setAttribute('aria-live','polite');
			document.body.appendChild(el);
		}
		el.classList.remove('is-ok','is-bad','is-info','is-on');
		el.classList.add('is-' + type);
		el.textContent = msg || '';
		el.classList.add('is-on');
		clearTimeout(el._t);
		el._t = setTimeout(function(){ el.classList.remove('is-on'); }, 1400);
	}

	
// SETTINGS (inline on Editor page)
(function(){
	// Settings panel - runs on both editor and settings pages
	var $wrap = $('#wphs-settings-inline, #wphs-img-settings-panel');

	var frameIcon = null;

	function setStatus(ok, msg){
		wphsToast(msg || (ok ? (i18n.saved || 'Saved') : (i18n.saveFailed || 'Save failed')), ok ? 'ok' : 'bad');
		var $s = $('#wphs-settings-status');
		if (!$s.length) { return; }
		$s.removeClass('is-ok is-bad');
		$s.addClass(ok ? 'is-ok' : 'is-bad');
		$s.text(msg || '');
		$s.show();
		setTimeout(function(){ $s.fadeOut(200); }, 1600);
	}

	function collectPayload(){
		var style   = $('input[name="wphs_hotspot_style"]:checked').val() || 'default';
		var imageId = parseInt($('#wphs-hotspot-image-id').val() || '0', 10) || 0;
		var size    = parseInt($('#wphs-hotspot-icon-size').val() || '22', 10) || 22;
		var dotColor       = $('#wphs-dot-color').val() || '#000000';
		var dotBorderColor = $('#wphs-dot-border-color').val() || '#ffffff';
		var ttPayload = collectTooltipPayload ? collectTooltipPayload() : {};
		if (!imageId) { style = 'default'; }
		// Read selected colors from img settings panel if present, else from cfg
	var dotSelectedFill   = $('#wphs-img-dot-selected-fill').val()   || (cfg.imgSettings && cfg.imgSettings.dot_selected_fill)   || '#3fe0a0';
	var dotSelectedBorder = $('#wphs-img-dot-selected-border').val() || (cfg.imgSettings && cfg.imgSettings.dot_selected_border) || '#3fe0a0';
	return Object.assign({ style: style, imageId: imageId, size: size, dotColor: dotColor, dotBorderColor: dotBorderColor, dotSelectedFill: dotSelectedFill, dotSelectedBorder: dotSelectedBorder }, ttPayload);
	}

	function applyIconState(imageId, iconUrl){
		var $imgRadio = $('#wphs-style-image');
		var $remove = $('#wphs-remove-icon');
		if (imageId > 0) {
			$imgRadio.prop('disabled', false);
			$remove.prop('disabled', false);
		} else {
			$imgRadio.prop('disabled', true);
			$remove.prop('disabled', true);
			// If image style was selected, fallback to default
			if ($imgRadio.is(':checked')) {
				$('input[name="wphs_hotspot_style"][value="default"]').prop('checked', true);
			}
		}
		if (iconUrl) {
			$('#wphs-icon-preview').html('<img src="'+ iconUrl +'" style="max-width:80px;height:auto" alt="" />');
		} else {
			$('#wphs-icon-preview').empty();
		}
	}

	function saveSettings(autoMsg){
		var p = collectPayload();

		$.post(cfg.ajaxurl, {
			action: 'wphs_save_settings',
			nonce: cfg.nonce,
			hotspot_style: p.style,
			hotspot_image_id: p.imageId,
			hotspot_icon_size: p.size,
			dot_color: p.dotColor,
			dot_border_color: p.dotBorderColor,
			dot_selected_fill:   p.dotSelectedFill   || '#3fe0a0',
			dot_selected_border: p.dotSelectedBorder || '#3fe0a0',
			tooltip_bg: p.tooltip_bg || '',
			tooltip_color: p.tooltip_color || '',
			tooltip_radius: p.tooltip_radius || 12,
		})
		.done(function(resp){
			if (resp && resp.success && resp.data && resp.data.options) {
				var o = resp.data.options;
				$('#wphs-hotspot-image-id').val(o.hotspot_image_id || 0);
				$('#wphs-hotspot-icon-size').val(o.hotspot_icon_size || 22);
				$('input[name="wphs_hotspot_style"][value="'+ (o.hotspot_style || 'default') +'"]').prop('checked', true);

				applyIconState(parseInt(o.hotspot_image_id || 0, 10) || 0, resp.data.icon_url || '');
				if (o.dot_color) {
					$('#wphs-dot-color').val(o.dot_color);
					$('#wphs-dot-color-hex').val(o.dot_color);
				}
				if (o.dot_border_color) {
					$('#wphs-dot-border-color').val(o.dot_border_color);
					$('#wphs-dot-border-color-hex').val(o.dot_border_color);
				}
				if (autoMsg) {
					setStatus(true, (i18n.saved || 'Saved'));
				}
			} else {
				setStatus(false, (i18n.saveFailed || 'Save failed'));
			}
		})
		.fail(function(){
			setStatus(false, (i18n.saveFailed || 'Save failed'));
		});
	}

	// Pick icon via Media Library
	$('#wphs-pick-icon').on('click', function(e){
		e.preventDefault();
		if (frameIcon) { frameIcon.open(); return; }
		frameIcon = wp.media({
			title: 'Select an icon',
			button: { text: 'Use this icon' },
			multiple: false,
			library: { type: 'image' }
		});
		frameIcon.on('select', function(){
			var att = frameIcon.state().get('selection').first().toJSON();
			if (!att || !att.id) { return; }
			$('#wphs-hotspot-image-id').val(att.id);
			applyIconState(att.id, (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url) ? att.sizes.thumbnail.url : (att.url || ''));
			// Icon picked — user must click Save settings to persist.
		});
		frameIcon.open();
	});

	// Remove icon: fallback to default and auto-save
	$('#wphs-remove-icon').on('click', function(e){
		e.preventDefault();
		$('#wphs-hotspot-image-id').val('0');
		applyIconState(0, '');
		// Removed — user must click Save settings to persist.
	});

	// Save settings button
	$('#wphs-save-settings').on('click', function(e){
		e.preventDefault();
		saveSettings(true);
	});

	// If user changes style/size, enable save (optional immediate save)
	$('input[name="wphs_hotspot_style"], #wphs-hotspot-icon-size').on('change', function(){
		// Prevent selecting image style without an icon.
		var imageId = parseInt($('#wphs-hotspot-image-id').val() || '0', 10) || 0;
		if (!imageId) {
			$('input[name="wphs_hotspot_style"][value="default"]').prop('checked', true);
		}
	});

	// ── Dot field visibility (show/hide Choose icon & Fill/Border by style) ─────
	var _savedIconUrl = '';  // persists across Default ↔ Custom icon toggles

	function updateDotFieldVisibility(){
		var style = $('input[name="wphs_img_style"]:checked').val() || 'default';
		var isCustom = (style === 'image');
		$('#wphs-icon-row').toggle(isCustom);
		$('.wphs-dot-color-fields').toggle(!isCustom);
	}

	// ── Material switch handlers ────────────────────────────────────────────────
	// Hotspot style switch (editor panel)
	$(document).on('click', '#wphs-img-style-switch .wphs-switch-opt', function(){
		var forId = $(this).data('for');
		var $radio = $('#' + forId);
		$radio.prop('checked', true).trigger('change');
		$(this).closest('.wphs-switch-group').find('.wphs-switch-opt').removeClass('is-active');
		$(this).addClass('is-active');
		if (forId === 'wphs-img-style-image'){
			if (_savedIconUrl){
				// Restore icon in preview dot
				var $dot = $('#wphs-preview-dot');
				$dot.html('<img src="'+_savedIconUrl+'" alt="" style="width:100%;height:100%;object-fit:contain;display:block">');
				$dot.css({ background:'transparent', border:'none', borderRadius:'0' });
			}
			// Note: user must explicitly click 'Choose icon' button
		}
		updateDotFieldVisibility();
		updateUnifiedPreview();
	});



	// Hotspot style switch (settings page)
	$(document).on('click', '#wphs-gs-style-switch .wphs-switch-opt', function(){
		if ($(this).prop('disabled')) return;
		var forId = $(this).data('for');
		var $radio = $('#' + forId);
		if ($radio.prop('disabled')) return;
		$radio.prop('checked', true).trigger('change');
		$(this).closest('.wphs-switch-group').find('.wphs-switch-opt').removeClass('is-active');
		$(this).addClass('is-active');
	});



	// Color picker ↔ hex input sync (bidirectional)
	$(document).on('input change', '.wphs-color-input', function(){
		var val = $(this).val();
		var $row = $(this).closest('.wphs-color-row');
		$row.find('.wphs-hex-input').val(val);
		$row.find('.wphs-color-swatch').css('background', val);
		updateUnifiedPreview();
		updateGsTooltipPreview();
		// Refresh admin tooltip style if visible
		var $tip = $('#wphs-canvas').find('.wphs-admin-tooltip');
		if ($tip.is(':visible')) applyTooltipStyleToTip($tip);
	});
	$(document).on('input', '.wphs-hex-input', function(){
		var val = $(this).val().trim();
		if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(val)){
			var $row = $(this).closest('.wphs-color-row');
			$row.find('.wphs-color-input').val(val);
			$row.find('.wphs-color-swatch').css('background', val);
			updateUnifiedPreview();
			updateGsTooltipPreview();
		}
	});
	// Init swatches on load
	$('.wphs-color-row').each(function(){
		var v = $(this).find('.wphs-color-input').val();
		$(this).find('.wphs-color-swatch').css('background', v || '#000');
	});
	$(document).on('input change', '#wphs-tt-radius, #wphs-img-icon-size, #wphs-img-dot-width, #wphs-img-dot-height, #wphs-img-dot-radius', function(){ updateUnifiedPreview(); });
	$(document).on('change', 'input[name="wphs_img_style"]', function(){ updateUnifiedPreview(); });
	// Lock ratio toggle
	var _dotLocked = false;

	// Returns max allowed radius = floor(min(w,h)/2)
	function _maxRadius(){
		var w = parseInt($('#wphs-img-dot-width').val(),  10) || 22;
		var h = parseInt($('#wphs-img-dot-height').val(), 10) || 22;
		return Math.floor(Math.min(w, h) / 2);
	}

	// Clamp radius field to max and sync W/H when locked
	function _syncAfterWH(){
		if (_dotLocked){
			var w = parseInt($('#wphs-img-dot-width').val(), 10) || 22;
			$('#wphs-img-dot-height').val(w);
			$('#wphs-img-dot-radius').val(_maxRadius()); // keep circle
		} else {
			var maxR = _maxRadius();
			var r = parseInt($('#wphs-img-dot-radius').val(), 10);
			if (isNaN(r) || r > maxR){ $('#wphs-img-dot-radius').val(maxR); }
		}
		updateUnifiedPreview();
		$(document).trigger('wphs:dot_shape_changed', [getImgState()]);
	}
	function _syncAfterH(){
		if (_dotLocked){
			var h = parseInt($('#wphs-img-dot-height').val(), 10) || 22;
			$('#wphs-img-dot-width').val(h);
			$('#wphs-img-dot-radius').val(_maxRadius()); // keep circle
		} else {
			var maxR = _maxRadius();
			var r = parseInt($('#wphs-img-dot-radius').val(), 10);
			if (isNaN(r) || r > maxR){ $('#wphs-img-dot-radius').val(maxR); }
		}
		updateUnifiedPreview();
		$(document).trigger('wphs:dot_shape_changed', [getImgState()]);
	}

	$(document).on('click', '#wphs-dot-lock', function(){
		_dotLocked = !_dotLocked;
		$('#wphs-lock-icon').toggle(_dotLocked);
		$('#wphs-unlock-icon').toggle(!_dotLocked);
		$(this).css({ color: _dotLocked ? '#0b1220' : '#667085', borderColor: _dotLocked ? 'rgba(16,24,40,.4)' : 'rgba(16,24,40,.15)' });
		// On lock: sync H=W and set radius = maxR (perfect circle)
		if (_dotLocked){
			var w = parseInt($('#wphs-img-dot-width').val(), 10) || 22;
			$('#wphs-img-dot-height').val(w);
			$('#wphs-img-dot-radius').val(_maxRadius()); // always set to half = circle
		} else {
			var maxR = _maxRadius();
			var r = parseInt($('#wphs-img-dot-radius').val(), 10);
			if (isNaN(r) || r > maxR){ $('#wphs-img-dot-radius').val(maxR); }
		}
		updateUnifiedPreview();
		$(document).trigger('wphs:dot_shape_changed', [getImgState()]);
	});

	// Re-render canvas dots when shape fields change
	$(document).on('input', '#wphs-img-dot-width',  function(){ _syncAfterWH(); });
	$(document).on('input', '#wphs-img-dot-height', function(){ _syncAfterH(); });
	$(document).on('input', '#wphs-img-dot-radius', function(){
		// Clamp to max allowed
		var maxR = _maxRadius();
		var r = parseInt($(this).val(), 10);
		if (!isNaN(r) && r > maxR){ $(this).val(maxR); }
		updateUnifiedPreview();
		$(document).trigger('wphs:dot_shape_changed', [getImgState()]);
	});
	$(document).on('input', '#wphs-tt-radius-gs', function(){ updateGsTooltipPreview(); });

	// ── Unified preview (editor panel: hotspot + tooltip together) ────────────
	var _previewSelected = false;
	function syncPreviewToggle(sel){
		_previewSelected = !!sel;
		var $btn = $('#wphs-preview-toggle');
		$btn.attr('data-preview-sel', sel ? '1' : '0');
		$btn.text(sel ? (i18n.previewSelected || 'Preview: selected') : (i18n.previewNormal || 'Preview: normal'));
		$btn.toggleClass('wphs-btn-primary', !!sel).toggleClass('wphs-btn-secondary', !sel);
	}
	// Global accessor so other IIFEs can trigger toggle sync
	window._wphsSyncPreviewToggle = syncPreviewToggle;

	$(document).on('click', '#wphs-preview-toggle', function(){
		syncPreviewToggle(!_previewSelected);
		updateUnifiedPreview();
	});

	function updateUnifiedPreview(){
		// Tooltip bubble
		var $b   = $('#wphs-preview-bubble');
		var $dot = $('#wphs-preview-dot');
		if (!$b.length) return;

		var bg   = $('#wphs-tt-bg').val()    || '#0b1220';
		var col  = $('#wphs-tt-color').val() || '#ffffff';
		var r    = parseInt($('#wphs-tt-radius').val()) || 12;
		$b.css({ background: bg, color: col, borderRadius: r+'px' });
		$('#wphs-tt-bg-hex').val(bg);
		$('#wphs-tt-color-hex').val(col);



		// Dot preview
		var dw2 = parseInt($('#wphs-img-dot-width').val(),  10); if (isNaN(dw2)||dw2<1) dw2 = hotspotDotWidth  || 22;
		var dh2 = parseInt($('#wphs-img-dot-height').val(), 10); if (isNaN(dh2)||dh2<1) dh2 = hotspotDotHeight || 22;
		var dr2 = parseInt($('#wphs-img-dot-radius').val(), 10); if (isNaN(dr2)||dr2<0) dr2 = hotspotDotRadius !== undefined ? hotspotDotRadius : 999;
		var style  = $('input[name="wphs_img_style"]:checked').val() || 'default';
		var iconId = parseInt($('#wphs-img-icon-id').val() || '0', 10) || 0;
		var size   = parseInt($('#wphs-img-icon-size').val() || '22', 10) || 22;
		var fillColor   = $('#wphs-img-dot-color').val()        || '#000000';
		var borderColor = $('#wphs-img-dot-border-color').val() || '#ffffff';

		if (style === 'image' && iconId > 0){
			// Show icon image in dot — use W/H/Radius
			var $img = $dot.find('img');
			if (!$img.length){
				$dot.html('<img src="" alt="" style="width:100%;height:100%;object-fit:contain;display:block">');
				$img = $dot.find('img');
			}
			$dot.css({ width: dw2+'px', height: dh2+'px', background: 'transparent', border: 'none', borderRadius: dr2+'px' });
		} else {
			$dot.html('');
			$dot.css({
				width:        dw2+'px',
				height:       dh2+'px',
				background:   fillColor,
				border:       '2.5px solid ' + borderColor,
				borderRadius: dr2+'px'
			});
		}
		// Apply selected state via toggle
		if(style === 'image' && iconId > 0){
			// Custom icon mode: show selected icon or normal icon — no color ring
			var _selId  = parseInt($('#wphs-img-sel-icon-id').val() || '0', 10);
			var _selSrc = (_previewSelected && _selId > 0) ? ($('#wphs-img-sel-icon-preview img').attr('src') || '') : '';
			var _normSrc = $('#wphs-img-icon-preview img').attr('src') || _savedIconUrl || '';
			var _showSrc = _selSrc || _normSrc;
			if(_showSrc){
				var $img2 = $dot.find('img');
				if(!$img2.length){ $dot.html('<img alt="" style="width:100%;height:100%;object-fit:contain;display:block">'); $img2=$dot.find('img'); }
				$img2.attr('src', _showSrc);
			}
			$dot.css({ background:'transparent', border:'none', 'box-shadow':'none' });
		} else {
			// Default dot: apply selected fill/border colors
			var _sf = $('#wphs-img-dot-selected-fill').val()   || hotspotDotSelectedFill   || '#3fe0a0';
			var _sb = $('#wphs-img-dot-selected-border').val() || hotspotDotSelectedBorder || '#3fe0a0';
			if(_previewSelected){
				$dot.css({ background: _sf, 'border-color': _sb, 'box-shadow': '0 0 0 4px '+hexToRgba(_sb,.35)+',0 0 0 7px '+hexToRgba(_sb,.12) });
			} else {
				$dot.css({ 'box-shadow':'', 'border-color':'' });
			}
		}
	}

	// ── Tooltip preview for settings page ────────────────────────────────────
	function updateGsTooltipPreview(){
		var $b  = $('#wphs-gs-tt-preview');
		if (!$b.length) return;
		var bg   = $('#wphs-tt-bg-gs').val()    || '#0b1220';
		var col  = $('#wphs-tt-color-gs').val() || '#ffffff';
		var r    = parseInt($('#wphs-tt-radius-gs').val()) || 12;
		$b.css({ background: bg, color: col, borderRadius: r+'px' });
		$('#wphs-tt-bg-gs-hex').val(bg);
		$('#wphs-tt-color-gs-hex').val(col);
	}

	// ── collectTooltipPayload (used by global save settings) ─────────────────
	function collectTooltipPayload(){
		return {
			tooltip_bg:     $('#wphs-tt-bg-gs').val()    || '',
			tooltip_color:  $('#wphs-tt-color-gs').val() || '',
			tooltip_radius: parseInt($('#wphs-tt-radius-gs').val()) || 12,
		};
	}

	// Initial state sync (global settings panel)
	applyIconState(parseInt($('#wphs-hotspot-image-id').val() || '0', 10) || 0, cfg.hotspotIcon || '');
	// Init global settings preview (settings page)
	updateGsTooltipPreview();
	// If no image is loaded, init editor preview with global defaults
	if (!cfg.attachmentId){
		updateDotFieldVisibility();
		updateUnifiedPreview();
	}

	/* ═══════════════════════════════════════════════════════════════════
	 * Per-image settings panel
	 * All changes require explicit "Save image settings" click.
	 * ═══════════════════════════════════════════════════════════════════ */
	(function(){
		var frameImgIcon = null;

		function getImgState(){
			return {
				style:          $('input[name="wphs_img_style"]:checked').val() || 'default',
				iconId:         parseInt($('#wphs-img-icon-id').val()     || '0', 10) || 0,
				selIconId:      parseInt($('#wphs-img-sel-icon-id').val() || '0', 10) || 0,
				size:           parseInt($('#wphs-img-icon-size').val() || '22', 10) || 22,
				dotWidth:       parseInt($('#wphs-img-dot-width').val()  || '22', 10) || 22,
				dotHeight:      parseInt($('#wphs-img-dot-height').val() || '22', 10) || 22,
				dotRadius:      parseInt($('#wphs-img-dot-radius').val() || '999', 10),
				dotColor:       $('#wphs-img-dot-color').val()        || '#000000',
				dotBorderColor:    $('#wphs-img-dot-border-color').val()   || '#ffffff',
				dotSelectedFill:   $('#wphs-img-dot-selected-fill').val()   || '#3fe0a0',
				dotSelectedBorder: $('#wphs-img-dot-selected-border').val() || '#3fe0a0',
				tooltipBg:      $('#wphs-tt-bg').val()                || '#0b1220',
				tooltipColor:   $('#wphs-tt-color').val()             || '#ffffff',
				tooltipRadius:  parseInt($('#wphs-tt-radius').val())  || 12,
				tooltipTail:    $('#wphs-tt-tail').is(':checked') ? 1 : 0
			};
		}

		var _svgIconPH = '<svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"rgba(16,24,40,.3)\" stroke-width=\"1.5\"><rect x=\"3\" y=\"3\" width=\"18\" height=\"18\" rx=\"2\"/><circle cx=\"8.5\" cy=\"8.5\" r=\"1.5\"/><path d=\"m21 15-5-5L5 21\"/></svg>';
		function applyImgIconState(iconId, iconUrl){
			$('#wphs-img-icon-id').val(iconId || 0);
			var $remove = $('#wphs-img-remove-icon');
			if (iconId > 0){
				if (iconUrl) _savedIconUrl = iconUrl;
				$remove.prop('disabled', false);
				// Update Normal icon preview box
				$('#wphs-img-icon-preview').html('<img src="'+(iconUrl||_savedIconUrl)+'" style="max-width:100%;height:auto" alt="">');
				// Update preview dot
				var $dot = $('#wphs-preview-dot');
				$dot.html('<img src="'+(iconUrl||_savedIconUrl)+'" alt="" style="width:100%;height:100%;object-fit:contain;display:block">');
				$dot.css({ background:'transparent', border:'none', borderRadius:'0' });
			} else {
				$remove.prop('disabled', true);
				_savedIconUrl = '';
				// Clear Normal icon preview box — do NOT force-switch style
				$('#wphs-img-icon-preview').html(_svgIconPH);
				// Revert preview dot to circle
				$('#wphs-preview-dot').html('').css({ background:'#000', border:'2.5px solid #fff', borderRadius:'50%' });
			}
			updateDotFieldVisibility();
			updateUnifiedPreview();
		}

		function applyImgSettings(settings, iconUrl, selIconUrl){
			// Update badge: show "this image" if override exists, "default" otherwise
			var hasOverride = settings && settings._has_override;
			$('#wphs-img-badge')
				.text(hasOverride ? 'this image' : 'default')
				.toggleClass('wphs-badge-override', !!hasOverride)
				.toggleClass('wphs-badge-default', !hasOverride);
			if (settings){
				var imgStyle = settings.hotspot_style || 'default';
				$('input[name="wphs_img_style"][value="'+imgStyle+'"]').prop('checked', true);
				// sync switch buttons
				$('#wphs-img-style-switch .wphs-switch-opt').removeClass('is-active');
				$('#wphs-img-style-switch .wphs-switch-opt[data-for="wphs-img-style-'+imgStyle+'"]').addClass('is-active');
				updateDotFieldVisibility();
				// sync tail switch

				$('#wphs-img-icon-size').val(settings.hotspot_icon_size || 22);
				$('#wphs-img-dot-width').val(settings.dot_width   || 22);
				$('#wphs-img-dot-height').val(settings.dot_height || 22);
				$('#wphs-img-dot-radius').val(settings.dot_radius !== undefined ? settings.dot_radius : 999);
				$('#wphs-img-dot-width').val(settings.dot_width   || 22);
				$('#wphs-img-dot-height').val(settings.dot_height || 22);
				$('#wphs-img-dot-radius').val(settings.dot_radius !== undefined ? settings.dot_radius : 999);
				var dc  = settings.dot_color       || '#000000';
				var dbc = settings.dot_border_color || '#ffffff';
				$('#wphs-img-dot-color').val(dc);
				$('#wphs-img-dot-color-hex').val(dc);
				$('#wphs-img-dot-border-color').val(dbc);
				$('#wphs-img-dot-border-color-hex').val(dbc);
				var dsf = settings.dot_selected_fill   || '#3fe0a0';
				var dsb = settings.dot_selected_border || '#3fe0a0';
				$('#wphs-img-dot-selected-fill').val(dsf);
				$('#wphs-img-dot-selected-fill-hex').val(dsf);
				$('#wphs-img-dot-selected-border').val(dsb);
				$('#wphs-img-dot-selected-border-hex').val(dsb);
				// Sync tooltip fields from settings
				var ttBg  = settings.tooltip_bg     || '#0b1220';
				var ttCol = settings.tooltip_color  || '#ffffff';
				var ttR   = settings.tooltip_radius || 12;
				$('#wphs-tt-bg').val(ttBg); $('#wphs-tt-bg-hex').val(ttBg);
				$('#wphs-tt-color').val(ttCol); $('#wphs-tt-color-hex').val(ttCol);
				// Sync swatches
				$('#wphs-img-dot-color').closest('.wphs-color-row').find('.wphs-color-swatch').css('background', dc);
				$('#wphs-img-dot-border-color').closest('.wphs-color-row').find('.wphs-color-swatch').css('background', dbc);
				$('#wphs-img-dot-selected-fill').closest('.wphs-color-row').find('.wphs-color-swatch').css('background', dsf);
				$('#wphs-img-dot-selected-border').closest('.wphs-color-row').find('.wphs-color-swatch').css('background', dsb);
				$('#wphs-tt-bg').closest('.wphs-color-row').find('.wphs-color-swatch').css('background', ttBg);
				$('#wphs-tt-color').closest('.wphs-color-row').find('.wphs-color-swatch').css('background', ttCol);
				$('#wphs-tt-radius').val(ttR);
				applyImgIconState(settings.hotspot_image_id || 0, iconUrl || '');
				// Selected icon
				var _selId  = settings.hotspot_selected_image_id || 0;
				var _selUrl = selIconUrl || '';
				$('#wphs-img-sel-icon-id').val(_selId);
				$('#wphs-img-remove-sel-icon').prop('disabled', _selId <= 0);
				hotspotSelectedIcon = _selUrl;
				var _svgPH = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="rgba(16,24,40,.3)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>';
				$('#wphs-img-sel-icon-preview').html(_selUrl ? '<img src="'+_selUrl+'" style="max-width:100%;height:auto" alt="">' : _svgPH);
				updateUnifiedPreview();
				// Fire event so editor can update dot styles (cross-IIFE)
				$(document).trigger('wphs:img_settings_applied', [settings, iconUrl, _selUrl]);
			}
		}

		function loadImgSettings(attachmentId){
			if (!attachmentId) return;
			$.post(cfg.ajaxurl, {
				action: 'wphs_get_img_settings',
				nonce:  cfg.nonce,
				attachment_id: attachmentId
			}).done(function(resp){
				if (resp && resp.success && resp.data){
					applyImgSettings(resp.data.settings, resp.data.icon_url || '', resp.data.sel_icon_url || '');
				}
			});
		}

		function saveImgSettings(){
			var attachmentId = cfg.attachmentId || parseInt($('#wphs-canvas').data('attachment') || '0', 10) || 0;
			if (!attachmentId) return;
			var s = getImgState();
			var payload = {
				action:            'wphs_save_img_settings',
				nonce:             cfg.nonce,
				attachment_id:     attachmentId
			};
			payload.hotspot_style     = s.style;
			payload.hotspot_image_id          = s.iconId;
			payload.hotspot_selected_image_id = s.selIconId;
			payload.hotspot_icon_size = s.size;
			payload.dot_width         = s.dotWidth;
			payload.dot_height        = s.dotHeight;
			payload.dot_radius        = s.dotRadius;
			payload.dot_color         = s.dotColor;
			payload.dot_border_color    = s.dotBorderColor;
			payload.dot_selected_fill   = s.dotSelectedFill;
			payload.dot_selected_border = s.dotSelectedBorder;
			payload.tooltip_bg        = s.tooltipBg;
			payload.tooltip_color     = s.tooltipColor;
			payload.tooltip_radius    = s.tooltipRadius;
			$.post(cfg.ajaxurl, payload).done(function(resp){
				if (resp && resp.success){
					wphsToast((i18n.styleSaved || 'Hotspot style saved'), 'ok');
					if (resp.data && resp.data.settings){
						applyImgSettings(resp.data.settings, resp.data.icon_url || '', resp.data.sel_icon_url || '');
					}
				} else {
					wphsToast((i18n.saveFailed || 'Save failed'), 'bad');
				}
			}).fail(function(){ wphsToast((i18n.saveFailed || 'Save failed'), 'bad'); });
		}

		// Reset button: delete per-image override, reload global defaults into fields
		$('#wphs-reset-img-settings').on('click', function(e){
			e.preventDefault();
			var attachmentId = cfg.attachmentId || parseInt($('#wphs-canvas').data('attachment') || '0', 10) || 0;
			if (!attachmentId) return;
			$.post(cfg.ajaxurl, {
				action:        'wphs_save_img_settings',
				nonce:         cfg.nonce,
				attachment_id: attachmentId,
				use_default:   '1'
			}).done(function(resp){
				if (resp && resp.success){
					wphsToast((i18n.resetToDefault || 'Reset to default'), 'ok');
					$('#wphs-img-badge').text('default').removeClass('wphs-badge-override').addClass('wphs-badge-default');
					if (resp.data && resp.data.settings) applyImgSettings(resp.data.settings, resp.data.icon_url || '', resp.data.sel_icon_url || '');
				} else {
					wphsToast((i18n.resetFailed || 'Reset failed'), 'bad');
				}
			}).fail(function(){ wphsToast((i18n.resetFailed || 'Reset failed'), 'bad'); });
		});

		// Pick icon via Media Library
		$('#wphs-img-pick-icon').on('click', function(e){
			e.preventDefault();
			if (frameImgIcon){ frameImgIcon.open(); return; }
			frameImgIcon = wp.media({
				title: 'Select an icon for this image',
				button: { text: 'Use this icon' },
				multiple: false,
				library: { type: 'image' }
			});
			frameImgIcon.on('select', function(){
				var att = frameImgIcon.state().get('selection').first().toJSON();
				if (!att || !att.id) return;
				var url = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : (att.url || '');
				applyImgIconState(att.id, url);
			});
			frameImgIcon.open();
		});

		$('#wphs-img-remove-icon').on('click', function(e){
			e.preventDefault();
			applyImgIconState(0, '');
		});

		// ── Selected icon picker ────────────────────────────────────────────
		var _svgPH = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="rgba(16,24,40,.3)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>';
		var _frameSelIcon = null;
		$(document).on('click', '#wphs-img-pick-sel-icon', function(e){
			e.preventDefault();
			var normalId = parseInt($('#wphs-img-icon-id').val() || '0', 10);
			if(normalId <= 0){ alert((i18n.chooseNormalFirst || 'Choose a Normal icon first.')); return; }
			if(_frameSelIcon){ _frameSelIcon.open(); return; }
			_frameSelIcon = wp.media({ title:'Select icon for selected state', button:{ text:'Use this icon' }, multiple:false, library:{ type:'image' } });
			_frameSelIcon.on('select', function(){
				var att = _frameSelIcon.state().get('selection').first().toJSON();
				if(!att || !att.id) return;
				var url = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : (att.url || '');
				$('#wphs-img-sel-icon-id').val(att.id);
				$('#wphs-img-remove-sel-icon').prop('disabled', false);
				$('#wphs-img-sel-icon-preview').html('<img src="'+url+'" style="max-width:100%;height:auto" alt="">');
				hotspotSelectedIcon = url;
				renderDots();
			});
			_frameSelIcon.open();
		});
		$(document).on('click', '#wphs-img-remove-sel-icon', function(e){
			e.preventDefault();
			$('#wphs-img-sel-icon-id').val(0);
			$(this).prop('disabled', true);
			$('#wphs-img-sel-icon-preview').html(_svgPH);
			hotspotSelectedIcon = '';
			renderDots();
		});

		// Radio change: just update preview (no forced revert — switch handles state)
		$('input[name="wphs_img_style"]').on('change', function(){
			updateUnifiedPreview();
		});
		$('#wphs-img-dot-color, #wphs-img-dot-border-color, #wphs-img-dot-selected-fill, #wphs-img-dot-selected-border').on('input', function(){
			updateUnifiedPreview();
			// Re-apply selected state to canvas dot live
			if(selectedId){
				var _sf = $('#wphs-img-dot-selected-fill').val()   || hotspotDotSelectedFill   || '#3fe0a0';
				var _sb = $('#wphs-img-dot-selected-border').val() || hotspotDotSelectedBorder || '#3fe0a0';
				$overlay.find('.wphs-dot.is-selected').css({ background: _sf, 'border-color': _sb, 'box-shadow': '0 0 0 4px '+hexToRgba(_sb,.35)+',0 0 0 7px '+hexToRgba(_sb,.12) });
			}
		});

		// Reset button: delete per-image override, reload global defaults into fields
		$('#wphs-reset-img-settings').on('click', function(e){
			e.preventDefault();
			var attachmentId = cfg.attachmentId || parseInt($('#wphs-canvas').data('attachment') || '0', 10) || 0;
			if (!attachmentId) return;
			$.post(cfg.ajaxurl, {
				action:        'wphs_save_img_settings',
				nonce:         cfg.nonce,
				attachment_id: attachmentId,
				use_default:   '1'
			}).done(function(resp){
				if (resp && resp.success){
					wphsToast((i18n.resetToDefault || 'Reset to default'), 'info');
					if (resp.data && resp.data.settings){
						applyImgSettings(resp.data.settings, resp.data.icon_url || '', resp.data.sel_icon_url || '');
					}
				} else {
					wphsToast((i18n.resetFailed || 'Reset failed'), 'bad');
				}
			}).fail(function(){ wphsToast((i18n.resetFailed || 'Reset failed'), 'bad'); });
		});

		// Save button
		$('#wphs-save-img-settings').on('click', function(e){
			e.preventDefault();
			saveImgSettings();
		});

		// Load settings for current image on page load
		var initSettings = cfg.imgSettings || null;
		var initIconUrl  = cfg.imgIconUrl  || '';
		if (initSettings){
			applyImgSettings(initSettings, initIconUrl, cfg.imgSelIconUrl || '');
		}

		// Reload when a new image is selected in the editor
		$(document).on('wphs:image_loaded', function(e, attachmentId){
			loadImgSettings(attachmentId);
		});
	})();

})();

	// EDITOR
	var attachmentId = parseInt(cfg.attachmentId,10) || 0;
	var nonce = (cfg.nonce || '').toString();
	// Init from per-image settings if available, fallback to global
	var hotspotStyle       = ((cfg.imgSettings && cfg.imgSettings.hotspot_style)  || cfg.hotspotStyle || 'default').toString();
	var hotspotIcon        = ((cfg.imgSettings && cfg.imgSettings.hotspot_style === 'image') ? (cfg.imgIconUrl || cfg.hotspotIcon || '') : (cfg.hotspotIcon || '')).toString();
	var hotspotSelectedIcon= (cfg.imgSelIconUrl || '').toString();
	var hotspotIconSize = parseInt((cfg.imgSettings && cfg.imgSettings.hotspot_icon_size) || cfg.hotspotIconSize, 10) || 22;
	// Per-image overrides (updated by applyImgSettings)
	var hotspotDotColor    = (cfg.imgSettings && cfg.imgSettings.dot_color)        || '#000000';
	var hotspotDotBorder       = (cfg.imgSettings && cfg.imgSettings.dot_border_color)   || '#ffffff';
	var hotspotDotSelectedFill   = (cfg.imgSettings && cfg.imgSettings.dot_selected_fill)   || '#3fe0a0';
	var hotspotDotSelectedBorder = (cfg.imgSettings && cfg.imgSettings.dot_selected_border) || '#3fe0a0';
	var hotspotDotWidth    = (cfg.imgSettings && cfg.imgSettings.dot_width)          || 22;
	var hotspotDotHeight   = (cfg.imgSettings && cfg.imgSettings.dot_height)         || 22;
	var hotspotDotRadius   = (cfg.imgSettings && cfg.imgSettings.dot_radius !== undefined) ? cfg.imgSettings.dot_radius : 999;
	var hotspotDotWidth    = (cfg.imgSettings && cfg.imgSettings.dot_width)          || 22;
	var hotspotDotHeight   = (cfg.imgSettings && cfg.imgSettings.dot_height)         || 22;
	var hotspotDotRadius   = (cfg.imgSettings && cfg.imgSettings.dot_radius !== undefined) ? cfg.imgSettings.dot_radius : 999;
	// Also init style from imgSettings
	if (cfg.imgSettings && cfg.imgSettings.hotspot_style) hotspotStyle = cfg.imgSettings.hotspot_style;
	if (cfg.imgSettings && cfg.imgSettings.hotspot_icon_size) hotspotIconSize = parseInt(cfg.imgSettings.hotspot_icon_size, 10) || hotspotIconSize;

	// Listen for per-image settings applied (from settings IIFE cross-scope)
	// Update dot shape vars from settings IIFE
	$(document).on('wphs:dot_shape_changed', function(e, s){
		hotspotDotWidth  = s.dotWidth;
		hotspotDotHeight = s.dotHeight;
		hotspotDotRadius = s.dotRadius;
		renderDots();
	});
	$(document).on('wphs:img_settings_applied', function(e, settings, iconUrl, selIconUrl){
		if (!settings) return;
		hotspotStyle     = settings.hotspot_style     || 'default';
		hotspotDotColor  = settings.dot_color         || '#000000';
		hotspotDotBorder   = settings.dot_border_color   || '#ffffff';
		hotspotDotSelectedFill   = settings.dot_selected_fill   || '#3fe0a0';
		hotspotDotSelectedBorder = settings.dot_selected_border || '#3fe0a0';
		hotspotDotWidth  = settings.dot_width          || 22;
		hotspotDotHeight = settings.dot_height         || 22;
		hotspotDotRadius = (settings.dot_radius !== undefined) ? settings.dot_radius : 999;
		hotspotDotWidth  = settings.dot_width          || 22;
		hotspotDotHeight = settings.dot_height         || 22;
		hotspotDotRadius = (settings.dot_radius !== undefined) ? settings.dot_radius : 999;
		hotspotIconSize  = settings.hotspot_icon_size || 22;
		if (iconUrl) hotspotIcon = iconUrl;
		hotspotSelectedIcon = selIconUrl || settings.hotspot_selected_image_url || '';
		renderDots();
	});
	function hexToRgba(hex, alpha){
		hex = (hex||'#000000').replace('#','');
		if(hex.length===3) hex=hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
		var r=parseInt(hex.substr(0,2),16),g=parseInt(hex.substr(2,2),16),b=parseInt(hex.substr(4,2),16);
		return 'rgba('+r+','+g+','+b+','+(alpha||1)+')';
	}
	var defaultHtml = (cfg.defaultHtml || '').toString();

	var $canvas = $('#wphs-canvas');
	var $img = $('#wphs-image');
	var $overlay = $('#wphs-overlay');
	// Tooltip preview (frontend-like) inside editor canvas
	var $adminTip = null;
	function ensureAdminTip(){
		if ($adminTip && $adminTip.length) return $adminTip;
		$adminTip = $('<div class="wphs-admin-tooltip"></div>').hide();
		$canvas.css('position','relative');
		$canvas.append($adminTip);

		return $adminTip;
	}
	function hideAdminTip(){
		if ($adminTip) $adminTip.hide().empty();
	}
	function positionAdminTip($dot, $tip){
		if (!$dot || !$dot.length) return;

		var pad = 14, m = 8;

		// Misura tooltip
		$tip.css({position:'absolute', left:'-9999px', top:'-9999px', display:'block', visibility:'hidden'});
		var tw = $tip.outerWidth()  || 200;
		var th = $tip.outerHeight() || 60;

		// Centro dot relativo al canvas
		var cOff = $canvas.offset() || {left:0, top:0};
		var dOff = $dot.offset()    || {left:0, top:0};
		var dx = (dOff.left - cOff.left) + ($dot.outerWidth()  || 0) / 2;
		var dy = (dOff.top  - cOff.top)  + ($dot.outerHeight() || 0) / 2;

		var cw = $canvas.outerWidth()  || 400;
		var ch = $canvas.outerHeight() || 300;

		// Posiziona tooltip lontano dal dot
		var goRight = dx < cw / 2;
		var goDown  = dy < ch / 2;
		var tipLeft = goRight ? (dx + pad) : (dx - pad - tw);
		var tipTop  = goDown  ? (dy + pad) : (dy - pad - th);
		tipLeft = clamp(tipLeft, m, cw - tw - m);
		tipTop  = clamp(tipTop,  m, ch - th - m);

		$tip.css({left: tipLeft+'px', top: tipTop+'px', visibility:'visible'});

		// Centro del tooltip
		var tx = tipLeft + tw / 2;
		var ty = tipTop  + th / 2;

	}

	function getTooltipStyle(){
		// Read current saved/edited values from the settings fields
		var bg     = $('#wphs-tt-bg').val()    || '#0b1220';
		var color  = $('#wphs-tt-color').val() || '#ffffff';
		var radius = parseInt($('#wphs-tt-radius').val()) || 12;
		if (!$('#wphs-tt-bg').length && cfg.imgSettings){
			bg     = cfg.imgSettings.tooltip_bg     || bg;
			color  = cfg.imgSettings.tooltip_color  || color;
			radius = cfg.imgSettings.tooltip_radius || radius;
		}
		return { bg: bg, color: color, radius: radius };
	}

	function applyTooltipStyleToTip($tip){
		var s = getTooltipStyle();
		$tip.css({background: s.bg, color: s.color, borderRadius: s.radius+'px'});
		$tip.find('a').css({color: s.color});
		$tip.data('tip-style', s);
	}

	function showAdminTip($dot, html){
		html = (html || '').toString();
		if (!html) { hideAdminTip(); return; }
		// Show immediately with raw HTML
		var $tip = ensureAdminTip();
		$tip.html(html).show();
		applyTooltipStyleToTip($tip);
		try{
			var w = $canvas.outerWidth() || 0;
			var maxW = w ? Math.max(160, Math.round(w * 0.45)) : 280;
			$tip.css({'max-width': maxW+'px'});
		}catch(e){}
		positionAdminTip($dot, $tip);
		// If HTML contains a video URL, resolve oEmbed and update tip
		if(/https?:\/\/(?:youtu|youtube|vimeo|open\.spotify)/i.test(html) && cfg && cfg.ajaxurl){
			$.post(cfg.ajaxurl, {action:'wphs_resolve_oembed', nonce:cfg.nonce, html:html})
				.done(function(resp){
					if(resp && resp.success && resp.data.html){
						$tip.html(resp.data.html);
						var hasEmbed = $tip.find('.wphs-oembed-wrap').length > 0;
						if(hasEmbed){
							var embedW = Math.min(560, Math.max(280, Math.round((w||400)*0.82)));
							$tip.css({'width':embedW+'px','max-width':embedW+'px','max-height':'none','overflow-y':'visible'});
							positionAdminTip($dot, $tip);
						}
					}
				});
		}
	}

	var $jsonField = $('#wphs-hotspots-json');
	var $savedAt = $('#wphs-saved-at');
	var $count = $('#wphs-count');
	var $status = $('#wphs-status');
	var $tbody = $('#wphs-tbody');
	var $dock = $('#wphs-editor-dock');

	var $btnDel = $('#wphs-del');
	var $btnClear = $('#wphs-clear');
	var $btnSave = $('#wphs-save');
	var $btnPick = $('#wphs-pick');

	var editorId = 'wphs_hotspot_html_editor';
	var hotspots = [];
	var selectedId = null;
	var isMutating = false;

	// Decodifica eventuali sequenze unicode escape (es. "\\u00e8") in UTF-8.
	function decodeUnicodeEscapes(str){
		str = (str || '').toString();
		if (str.indexOf('\\u') === -1) return str;
		return str.replace(/\\u([0-9a-fA-F]{4})/g, function(m, hex){
			try { return String.fromCharCode(parseInt(hex, 16)); } catch(e){ return m; }
		});
	}

	function clamp(n,min,max){ return Math.max(min, Math.min(max, n)); }
	function uid(){ return "hs_" + Math.random().toString(36).slice(2,10) + "_" + Date.now().toString(36); }
	function parseJSON(s){ try{ return JSON.parse(s); }catch(e){ return null; } }
	function decodeDefaultHtml(){ return defaultHtml; }
	function setStatus(msg, type){
		var t = (type === 'ok') ? 'ok' : (type === 'err' ? 'bad' : 'info');
		wphsToast(String(msg || ''), t);
		$status.removeClass('is-ok is-err');
		if (type === 'ok') $status.addClass('is-ok');
		if (type === 'err') $status.addClass('is-err');
		$status.text(msg||'');
	}
	function readHotspots(){ var parsed = parseJSON($jsonField.val() || '[]'); return Array.isArray(parsed) ? parsed : []; }
	function writeHotspots(){ $jsonField.val(JSON.stringify(hotspots)); $count.text(String(hotspots.length)); }
	function findHotspot(id){ for (var i=0;i<hotspots.length;i++) if (hotspots[i].id===id) return hotspots[i]; return null; }
	function hsTitleFromHtml(html){ html=(html||'').toString(); var text=html.replace(/<[^>]*>?/gm,'').replace(/\s+/g,' ').trim(); return text?text.slice(0,60):''; }
	function rowPreview(html){ var t=hsTitleFromHtml(html); return t?t:'(senza testo)'; }
	function getEditor(){
		var $ta = $('#'+editorId);
		// Prefer TinyMCE (teeny) when available.
		if ( window.tinymce && tinymce.get(editorId) ) {
			try { tinymce.get(editorId).save(); } catch(e) {}
			try {
				var c = tinymce.get(editorId).getContent({format:'raw'}) || '';
				if (c) return c;
			} catch(e) {}
		}
		return $ta.length ? ($ta.val() || '') : '';
	}
	function setEditor(html){
		html = (html || '').toString();
		var $ta = $('#'+editorId);
		isMutating = true;
		if ( window.tinymce && tinymce.get(editorId) ) {
			try { tinymce.get(editorId).setContent(html); } catch(e) {}
		}
		if ( $ta.length ) { $ta.val(html); }
		isMutating = false;
	}

	function ensureOverlay(){
		if (!$img.length || !$overlay.length) return false;
		// Overlay copre sempre l'immagine tramite CSS (inset:0).
		return true;
	}

	// Calcolo coordinate: PRIORITA' offsetX/offsetY sul target overlay (stabile, non cumulativo).
	function pctFromOverlayEvent(ev){
		ensureOverlay();
		var oe = ev.originalEvent || ev;
		var w = $overlay.width() || 1;
		var h = $overlay.height() || 1;

		if (typeof oe.offsetX === 'number' && typeof oe.offsetY === 'number' && (oe.target === $overlay[0])) {
			return {
				x: clamp((oe.offsetX / w) * 100, 0, 100),
				y: clamp((oe.offsetY / h) * 100, 0, 100)
			};
		}

		// Fallback: clientX/clientY su bounding-rect overlay.
		var rect = $overlay[0].getBoundingClientRect();
		var cx = (typeof oe.clientX !== 'undefined') ? oe.clientX : 0;
		var cy = (typeof oe.clientY !== 'undefined') ? oe.clientY : 0;
		return {
			x: clamp(((cx - rect.left) / rect.width) * 100, 0, 100),
			y: clamp(((cy - rect.top)  / rect.height) * 100, 0, 100)
		};
	}

	// DRAG stabile: calcola offset di grab sul centro del dot (compatibile con transform: translate(-50%,-50%)).
	var dragState = { active:false, id:null, hs:null, grabX:0, grabY:0, raf:0, lastEv:null };

	function overlayRect(){
		ensureOverlay();
		return $overlay[0].getBoundingClientRect();
	}

	function setDotFromPointer(oe, $dot, hs){
		var rect = overlayRect();
		var cx = (typeof oe.clientX !== 'undefined') ? oe.clientX : 0;
		var cy = (typeof oe.clientY !== 'undefined') ? oe.clientY : 0;
		// Coord centro hotspot in pixel (dentro overlay) mantenendo l'offset di grab.
		var px = (cx - rect.left) - dragState.grabX;
		var py = (cy - rect.top)  - dragState.grabY;
		var x = clamp((px / (rect.width || 1)) * 100, 0, 100);
		var y = clamp((py / (rect.height || 1)) * 100, 0, 100);
		hs.x = x;
		hs.y = y;
		$dot.css({ left: x + '%', top: y + '%' });
	}

	function onDragMove(){
		dragState.raf = 0;
		if (!dragState.active || !dragState.lastEv) return;
		setDotFromPointer(dragState.lastEv, dragState.$dot, dragState.hs);
	}

	function bindDotDrag($dot, hs){
		$dot.on('mousedown', function(e){
			// Solo tasto sinistro.
			if (e.which && e.which !== 1) return;
			e.preventDefault();
		e.stopPropagation();
			selectHotspot(hs.id);
			var r = this.getBoundingClientRect();
			// Offset tra puntatore e centro visivo del dot (r include transform).
			dragState.grabX = e.clientX - (r.left + (r.width / 2));
			dragState.grabY = e.clientY - (r.top  + (r.height / 2));
			dragState.active = true;
			dragState.id = hs.id;
			dragState.hs = hs;
			dragState.$dot = $dot;
			dragState.lastEv = e;
			$('body').addClass('wphs-dragging');
		});
	}

	$(document).on('mousemove.wphsdrag', function(e){
		if (!dragState.active) return;
		dragState.lastEv = e;
		if (!dragState.raf) dragState.raf = window.requestAnimationFrame(onDragMove);
	});

	$(document).on('mouseup.wphsdrag', function(){
		if (!dragState.active) return;
		dragState.active = false;
		dragState.id = null;
		dragState.hs = null;
		dragState.lastEv = null;
		$('body').removeClass('wphs-dragging');
		writeHotspots();
	});


	function selectHotspot(id){
		selectedId = id || null;
		$overlay.find('.wphs-dot').removeClass('is-selected').each(function(){
			var $d = $(this);
			if(hotspotStyle === 'image' && hotspotIcon){
				$d.css({ background: 'transparent url('+hotspotIcon+') center / contain no-repeat', border:'0', 'box-shadow':'none' });
			} else {
				$d.css({ background: hotspotDotColor || '#000000', 'border-color': hotspotDotBorder || '#ffffff', 'box-shadow': '' });
			}
		});
		$tbody.find('tr').removeClass('is-selected');
		if (selectedId) {
			$overlay.find('.wphs-dot[data-id="'+selectedId+'"]').addClass('is-selected');
			if(hotspotStyle === 'image'){
				// Custom icon: swap canvas dot to selected icon if set
				if(hotspotSelectedIcon){
					$overlay.find('.wphs-dot.is-selected').css({ background: 'transparent url('+hotspotSelectedIcon+') center / contain no-repeat', border:'0', 'box-shadow':'none' });
				}
			} else {
				var sf = $('#wphs-img-dot-selected-fill').val()   || hotspotDotSelectedFill   || '#3fe0a0';
				var sb = $('#wphs-img-dot-selected-border').val() || hotspotDotSelectedBorder || '#3fe0a0';
				$overlay.find('.wphs-dot.is-selected').css({ background: sf, 'border-color': sb, 'box-shadow': '0 0 0 4px '+hexToRgba(sb,.35)+',0 0 0 7px '+hexToRgba(sb,.12) });
			}
			$tbody.find('tr[data-id="'+selectedId+'"]').addClass('is-selected');
		}
		var hs = selectedId ? findHotspot(selectedId) : null;
		if (hs) {
			$dock.removeClass('wphs-editor-dock-hidden');
			setEditor(hs.html||'');
			showAdminTip($overlay.find('.wphs-dot[data-id="'+hs.id+'"]'), hs.html||'');
		} else {
			$dock.addClass('wphs-editor-dock-hidden');
			setEditor('');
			hideAdminTip();
		}
	}

	function syncSelectedFromEditor(){
		if (isMutating || !selectedId) return;
		var hs = findHotspot(selectedId);
		if (!hs) return;
		hs.html = getEditor();
		writeHotspots();
		$tbody.find('tr[data-id="'+selectedId+'"] td').eq(1).text(rowPreview(hs.html));
	}

	function renderTable(){
		$tbody.empty();
		for (var i=0;i<hotspots.length;i++){
			(function(idx, hs){
				var $tr = $('<tr/>').attr('data-id', hs.id);
				$tr.append($('<td/>').text(idx+1));
				$tr.append($('<td/>').text(rowPreview(hs.html)));
				$tr.on('click', function(){ selectHotspot(hs.id); });
				$tbody.append($tr);
			})(i, hotspots[i]);
		}
	}

	function renderDots(){
		ensureOverlay();
		$overlay.empty();
		for (var i=0;i<hotspots.length;i++){
			(function(hs){
				var $d = $('<div/>', {'class':'wphs-dot','data-id':hs.id});
				$d.css({ position: 'absolute', left: hs.x + '%', top: hs.y + '%' });

				var dw = hotspotDotWidth  || hotspotIconSize || 22;
				var dh = hotspotDotHeight || hotspotIconSize || 22;
				var dr = hotspotDotRadius !== undefined ? hotspotDotRadius : 999;
				if (hotspotStyle === 'image' && hotspotIcon) {
					$d.css({ width: dw+'px', height: dh+'px', borderRadius: dr+'px', background:'transparent url(' + hotspotIcon + ') center / contain no-repeat', border:'0', boxShadow:'none' });
				} else {
					// Default dot
					$d.css({ width: dw+'px', height: dh+'px', borderRadius: dr+'px', background: hotspotDotColor, borderColor: hotspotDotBorder });
				}

				if (selectedId && hs.id === selectedId) {
					$d.addClass('is-selected');
					if(hotspotStyle === 'image' && hotspotSelectedIcon){
						$d.css({ background: 'transparent url('+hotspotSelectedIcon+') center / contain no-repeat', border:'0', 'box-shadow':'none' });
					} else if(hotspotStyle !== 'image'){
						var _sf = $('#wphs-img-dot-selected-fill').val()   || hotspotDotSelectedFill   || '#3fe0a0';
						var _sb = $('#wphs-img-dot-selected-border').val() || hotspotDotSelectedBorder || '#3fe0a0';
						$d.css({ background: _sf, 'border-color': _sb, 'box-shadow': '0 0 0 4px '+hexToRgba(_sb,.35)+',0 0 0 7px '+hexToRgba(_sb,.12) });
					}
				}

				// Click dot: seleziona senza creare nuovo hotspot.
				$d.on('click', function(e){ e.preventDefault(); e.stopPropagation(); selectHotspot(hs.id); showAdminTip($d, hs.html||''); });

				// Drag custom: evita drift dovuto a transform e mantiene l'offset rispetto al puntatore.
				bindDotDrag($d, hs);
				$overlay.append($d);
			})(hotspots[i]);
		}
	}

	function addHotspotAt(x,y){
		if (!attachmentId) return;
		hotspots.push({ id: uid(), x: x, y: y, html: decodeDefaultHtml() });
		writeHotspots(); renderTable(); renderDots();
		selectHotspot(hotspots[hotspots.length-1].id);
	}

	function ajaxCall(action, data){
		return $.ajax({
			url: (typeof ajaxurl !== 'undefined') ? ajaxurl : cfg.ajaxurl,
			method: 'POST',
			dataType: 'json',
			data: $.extend({ action: action, nonce: nonce, attachment_id: attachmentId }, data||{})
		});
	}

	function ajaxGet(){
		if (!attachmentId) return $.Deferred().resolve().promise();
		return ajaxCall('wphs_get_hotspots', {}).done(function(resp){
			if (resp && resp.success && resp.data) {
				if (typeof resp.data.hotspots_json === 'string') $jsonField.val(resp.data.hotspots_json);
				if (resp.data.saved_at) $savedAt.text(resp.data.saved_at);
			}
		}).always(function(){
			hotspots = readHotspots();
			// Retro-compatibilità: hotspot salvati in passato con escape unicode.
			for (var i=0;i<hotspots.length;i++){
				if (hotspots[i] && typeof hotspots[i].html === 'string') {
					hotspots[i].html = decodeUnicodeEscapes(hotspots[i].html);
				}
			}
			$count.text(String(hotspots.length));
			renderTable();
			var tries=0;
			var t=setInterval(function(){
				tries++;
				if (ensureOverlay() || tries>30) { clearInterval(t); renderDots(); }
			}, 120);
		});
	}

	function ajaxSave_DISABLED(){
		syncSelectedFromEditor();
		setStatus((i18n.saving || 'Saving…'),'');
		return ajaxCall('wphs_save_hotspots', { hotspots_json: JSON.stringify(hotspots).replace(/\sdata-wplink-url-error=\\\"true\\\"/g,'') })
			.done(function(resp){
				if (resp && resp.success && resp.data) {
					if (resp.data.saved_at) $savedAt.text(resp.data.saved_at);
					setStatus((i18n.saved || 'Saved'),'ok');
				} else { setStatus('Save error','err'); }
			})
			.fail(function(){ setStatus('AJAX error','err'); });
	}

	// Media picker
	var frame=null;
	$btnPick.on('click', function(e){
		e.preventDefault();
		if (frame) { frame.open(); return; }
		if (!window.wp || !wp.media) { return; }
		frame = wp.media({ title: 'Select image', multiple:false, library:{type:'image'} });
		frame.on('select', function(){
			var att = frame.state().get('selection').first().toJSON();
			if (att && att.id) window.location = 'admin.php?page=wphs-editor&attachment_id=' + att.id;
		});
		frame.open();
	});

	$btnDel.on('click', function(e){
		e.preventDefault();
		if (!selectedId) return;
		syncSelectedFromEditor();
		var delId = selectedId;
		hotspots = hotspots.filter(function(h){ return h.id !== delId; });
		selectedId = null;
		writeHotspots(); renderTable(); renderDots(); selectHotspot(null);
	});
	$btnClear.on('click', function(e){
		e.preventDefault();
		selectedId=null;
		hotspots=[];
		writeHotspots(); renderTable(); renderDots(); selectHotspot(null);
	});
	$btnSave.on('click', function(e){
		e.preventDefault();
		try{
			if (window.tinymce && tinymce.get(editorId)) { try{ tinymce.get(editorId).save(); }catch(err){} }
		}catch(err){}
		syncSelectedFromEditor();
		writeHotspots();
		try{
			var json = $('#wphs-hotspots-json').val() || '';
			if (!json) { try{ json = JSON.stringify(hotspots); }catch(ex){} }
			$('#wphs-save-hotspots-json').val(json);
			document.getElementById('wphs-save-form').submit();
		}catch(e){}
	});

	// Click SOLO su overlay (sopra l'immagine):
	// - se clicchi su un hotspot esistente NON creare un nuovo hotspot
	// - coordinate sempre rispetto all'overlay (che coincide con l'immagine) per evitare drift
	$(document).on('pointerdown', '#wphs-overlay', function(ev){
		if (!attachmentId) return;
		// Non creare un nuovo hotspot se il click e' su un dot (o dentro).
		var $t = $(ev.target);
		if ($t.closest('.wphs-dot').length) return;
		if (!ensureOverlay()) return;

		var oe = ev.originalEvent || ev;
		var rect = $overlay[0].getBoundingClientRect();
		var cx = (typeof oe.clientX !== 'undefined') ? oe.clientX : 0;
		var cy = (typeof oe.clientY !== 'undefined') ? oe.clientY : 0;

		var x = clamp(((cx - rect.left) / rect.width) * 100, 0, 100);
		var y = clamp(((cy - rect.top)  / rect.height) * 100, 0, 100);

		addHotspotAt(x, y);
	});

	$(document).on('input keyup change', '#'+editorId, function(){ syncSelectedFromEditor(); });

	$(window).on('resize', function(){ if (ensureOverlay()) renderDots(); });
	if ($img.length) $img.on('load', function(){ if (ensureOverlay()) renderDots(); });

	if (!attachmentId) { $('#wphs-right-panel').hide(); } else { $('#wphs-right-panel').show(); }

	// Shortcode copy helpers
	function copyText(text){
		if (!text) return;
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).catch(function(){
				var ta=document.createElement('textarea');
				ta.value=text;document.body.appendChild(ta);ta.select();
				try{document.execCommand('copy');}catch(e){}
				document.body.removeChild(ta);
			});
			return;
		}
		var ta=document.createElement('textarea');
		ta.value=text;document.body.appendChild(ta);ta.select();
		try{document.execCommand('copy');}catch(e){}
		document.body.removeChild(ta);
	}

	$(document).on('click', '#wphs-copy-shortcode', function(e){
		e.preventDefault();
		e.stopPropagation();
		var v = $('#wphs-shortcode').val();
		if (!v) {
			var $wrap = $(this).closest('[data-wphs-shortcode]');
			v = $wrap.length ? $wrap.attr('data-wphs-shortcode') : '';
		}
		copyText(v);
		setStatus('Shortcode copied','ok');
	});
	$(document).on('click', '[data-wphs-copy="1"]', function(e){
		e.preventDefault();
		e.stopPropagation();
		var v = '';
		var $wrap = $(this).closest('[data-wphs-shortcode]');
		if ($wrap.length) {
			v = $wrap.attr('data-wphs-shortcode') || '';
		}
		if (!v) {
			var $inp = $(this).closest('.wphs-shortcode-wrap').find('input.wphs-shortcode-field');
			v = $inp.length ? $inp.val() : '';
		}
		copyText(v);
		setStatus('Shortcode copied','ok');
	});
	$(document).on('click', 'input.wphs-shortcode-field', function(e){ e.preventDefault(); e.stopPropagation(); this.select(); });

	ajaxGet();

	// FORCE classic save (no AJAX): bind Save button to submit hidden admin-post form.
	$(function(){
		var $btn = $('#wphs-save');
		if(!$btn.length){ return; }
		$btn.off('click.wphsPostSave').on('click.wphsPostSave', function(e){
			e.preventDefault();
			try{
				if (typeof syncSelectedFromEditor === 'function') { syncSelectedFromEditor(); }
				if (typeof writeHotspots === 'function') { writeHotspots(); }
				// Copy latest JSON from editor field into the hidden POST form field.
				var json = $('#wphs-hotspots-json').val() || '[]';
				$('#wphs-save-hotspots-json').val(json);
				var f = document.getElementById('wphs-save-form');
				if (f) { f.submit(); }
			}catch(err){}
		});
	});

})(jQuery);
