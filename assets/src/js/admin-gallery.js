(function($){
	if(typeof window.WPHS_GALLERY === 'undefined') return;
	var _nonce = window.WPHS_GALLERY.nonce;
	var _ajaxurl = window.WPHS_GALLERY.ajaxurl;
	var _galleries = window.WPHS_GALLERY.galleries;
	var _mediaFrame = null;

	function toast(msg, ok){
		var el = document.getElementById('wphs-toast');
		if(!el) return;
		el.textContent = msg;
		el.style.background = ok ? '#0b1220' : '#7f1d1d';
		el.classList.add('is-on');
		clearTimeout(el._t);
		el._t = setTimeout(function(){ el.classList.remove('is-on'); }, 2500);
	}

	// Switch group handler (same as hotspot editor)
	$(document).on('click', '#wphs-gallery-editor .wphs-switch-opt', function(){
		var $btn = $(this);
		var forId = $btn.data('for');
		$btn.closest('.wphs-switch-group').find('.wphs-switch-opt').removeClass('is-active');
		$btn.addClass('is-active');
		$('#' + forId).prop('checked', true);
	});

	function thumbEl(imgId, thumbUrl){
		var div = document.createElement('div');
		div.className = 'wphs-gallery-thumb';
		div.setAttribute('data-id', imgId);
		div.setAttribute('draggable', 'true');
		div.style.cssText = 'position:relative;width:72px;height:72px;border-radius:9px;overflow:visible;border:1.5px solid rgba(16,24,40,.09);background:#f5f5f2;flex-shrink:0;cursor:grab';
		var inner = document.createElement('div');
		inner.style.cssText = 'width:100%;height:100%;border-radius:7px;overflow:hidden';
		var img = document.createElement('img');
		img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block';
		inner.appendChild(img);
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.setAttribute('data-id', imgId);
		btn.className = 'wphs-remove-thumb';
		btn.style.cssText = 'position:absolute;top:-7px;right:-7px;width:18px;height:18px;border-radius:50%;border:2px solid #fff;background:#0b1220;color:#fff;cursor:pointer;padding:0;z-index:2;display:flex;align-items:center;justify-content:center;line-height:1';
		btn.innerHTML = '<svg width="8" height="8" viewBox="0 0 10 10" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><line x1="1" y1="1" x2="9" y2="9"/><line x1="9" y1="1" x2="1" y2="9"/></svg>';
		div.appendChild(inner);
		div.appendChild(btn);
		// Caption edit indicator
		var capBtn = document.createElement('button');
		capBtn.type = 'button';
		capBtn.className = 'wphs-thumb-caption-btn';
		capBtn.setAttribute('data-id', imgId);
		capBtn.style.cssText = 'position:absolute;bottom:2px;left:2px;background:rgba(11,18,32,.65);border:none;border-radius:4px;padding:2px 4px;cursor:pointer;font-size:9px;color:#fff;line-height:1;z-index:10';
		capBtn.title = 'Add caption';
		capBtn.textContent = 'T';
		div.appendChild(capBtn);
		if(thumbUrl && thumbUrl !== ''){
			img.src = thumbUrl;
		} else {
			$.get(_ajaxurl, {action:'wphs_thumb_url', id:imgId, nonce:_nonce}, function(r){
				if(r && r.success && r.data && r.data.url){ img.src = r.data.url; }
			});
		}
		// Drag-and-drop reorder
		div.addEventListener('dragstart', function(e){
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', imgId);
			setTimeout(function(){ div.style.opacity = '0.4'; }, 0);
		});
		div.addEventListener('dragend', function(){
			div.style.opacity = '1';
			getIds();
		});
		div.addEventListener('dragover', function(e){
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';
			var wrap = document.getElementById('wphs-gallery-thumbs');
			var items = Array.from(wrap.querySelectorAll('.wphs-gallery-thumb'));
			var rect = div.getBoundingClientRect();
			var mid = rect.left + rect.width / 2;
			var dragging = wrap.querySelector('[data-dragging="1"]');
			if(!dragging) return;
			if(e.clientX < mid){
				wrap.insertBefore(dragging, div);
			} else {
				wrap.insertBefore(dragging, div.nextSibling);
			}
		});
		div.addEventListener('dragstart', function(){ div.setAttribute('data-dragging','1'); });
		div.addEventListener('dragend',   function(){ div.removeAttribute('data-dragging'); });
		return div;
	}

	function getIds(){
		var ids = [];
		$('#wphs-gallery-thumbs .wphs-gallery-thumb').each(function(){ ids.push($(this).data('id')); });
		$('#wphs-gallery-ids').val(ids.join(','));
		return ids.join(',');
	}

	function setNav(nav){
		$('#wphs-gallery-editor .wphs-switch-opt').removeClass('is-active');
		$('#wphs-gallery-editor .wphs-switch-opt[data-for]').each(function(){
			var forId = $(this).data('for');
			var $radio = $('#' + forId);
			if($radio.val() === nav){
				$(this).addClass('is-active');
				$radio.prop('checked', true);
			}
		});
	}

	var _captions = {};
	function openEditor(gallery){
		gallery = gallery || {id:0, title:'', image_ids:[], nav:'dots+arrows'};
		var rawCap = gallery.captions || {};
		// Ensure captions is a plain object (PHP empty array comes as [])
		_captions = (Array.isArray(rawCap) && rawCap.length === 0) ? {} : JSON.parse(JSON.stringify(rawCap));
		$('#wphs-gallery-id').val(gallery.id);
		$('#wphs-gallery-title').val(gallery.title);
		setNav(gallery.nav);
		$('#wphs-gallery-cols-desktop').val(gallery.cols_desktop || 1);
		$('#wphs-gallery-cols-mobile').val(gallery.cols_mobile || 1);
		$('#wphs-gallery-img-radius').val(gallery.img_radius || 0);
		var _capBg  = gallery.caption_bg    || '#111827';
		var _capCol = gallery.caption_color || '#ffffff';
		$('#wphs-gallery-caption-bg').val(_capBg).trigger('change');
		$('#wphs-gallery-caption-bg-hex').val(_capBg);
		$('#wphs-gallery-caption-bg').closest('.wphs-color-row').find('.wphs-color-swatch').css('background',_capBg);
		$('#wphs-gallery-caption-color').val(_capCol).trigger('change');
		$('#wphs-gallery-caption-color-hex').val(_capCol);
		$('#wphs-gallery-caption-color').closest('.wphs-color-row').find('.wphs-color-swatch').css('background',_capCol);
		// Store colors as data attrs for reliable reading at save time
		$('#wphs-gallery-caption-bg').data('current', _capBg);
		$('#wphs-gallery-caption-color').data('current', _capCol);
		var wrap = document.getElementById('wphs-gallery-thumbs');
		wrap.innerHTML = '';
		var urls = gallery.thumb_urls || {};
		$.each(gallery.image_ids || [], function(i, id){ if(parseInt(id)>0) wrap.appendChild(thumbEl(id, urls[id] || null)); });
		if(gallery.id){
			$('#wphs-gallery-shortcode').val('[wphs_gallery id="' + gallery.id + '" nav="' + gallery.nav + '"]');
			$('#wphs-gallery-shortcode-wrap').show();
			$('#wphs-gallery-editor-title').text(gallery.title || 'Edit gallery');
			$('#wphs-gallery-editor-badge').text('editing').removeClass('wphs-badge-default').addClass('wphs-badge-override');
		} else {
			$('#wphs-gallery-shortcode-wrap').hide();
			$('#wphs-gallery-editor-title').text('New gallery');
			$('#wphs-gallery-editor-badge').text('new').addClass('wphs-badge-default').removeClass('wphs-badge-override');
		}
		$('#wphs-gallery-editor').show();
		$('#wphs-gallery-list-wrap').css('opacity', '.5').css('pointer-events', 'none');
		// Color T buttons for images that have captions
		setTimeout(function(){
			Object.keys(_captions).forEach(function(id){
				var cap=_captions[id];
				if(cap && (cap.title||cap.desc)) $('.wphs-thumb-caption-btn[data-id="'+id+'"]').css('background','#3fe0a0');
			});
		}, 100);
		// Trigger preview with known ids (don't wait for async thumb load)
		if((gallery.image_ids || []).length) updateGalleryPreview();
	}

	function closeEditor(){
		$('#wphs-gallery-editor').hide();
		$('#wphs-gallery-list-wrap').css('opacity', '1').css('pointer-events', '');
	}

	var _previewTimer = null;
	function updateGalleryPreview(){
		clearTimeout(_previewTimer);
		_previewTimer = setTimeout(function(){
			var $preview = $('#wphs-gallery-preview');
			getIds();
			var ids    = $('#wphs-gallery-ids').val() || '';
			var nav    = $('input[name="wphs_gallery_nav"]:checked').val() || 'dots+arrows';
			var cols_d = parseInt($('#wphs-gallery-cols-desktop').val()) || 1;
			var cols_m = parseInt($('#wphs-gallery-cols-mobile').val())  || 1;
			var img_r  = parseInt($('#wphs-gallery-img-radius').val())   || 0;
			var gid    = parseInt($('#wphs-gallery-id').val()) || 0;
			if(!ids && !gid){ $preview.html('<p style="font-size:11px;color:#667085;margin:0;text-align:center;padding:8px">Add images to see a preview</p>'); return; }
			$preview.html('<p style="font-size:11px;color:#667085;margin:0;text-align:center;padding:8px">Loading preview…</p>');
			$.post(_ajaxurl, {
				action: 'wphs_gallery_preview',
				nonce: _nonce,
				gallery_id: gid,
				gallery_ids: ids,
				nav: nav,
				cols_desktop: cols_d,
				cols_mobile: cols_m,
				img_radius: img_r,
				gallery_caption_bg: $('#wphs-gallery-caption-bg').data('current') || $('#wphs-gallery-caption-bg').val() || '#111827',
				gallery_caption_color: $('#wphs-gallery-caption-color').data('current') || $('#wphs-gallery-caption-color').val() || '#ffffff',
				captions: JSON.stringify(_captions)
			}, function(resp){
				if(resp && resp.success){
					if(resp.data.html){
						$preview.html(resp.data.html);
						if(window.wphs_init_galleries) window.wphs_init_galleries();
					} else {
						$preview.html('<p style="font-size:11px;color:#667085;margin:0;text-align:center;padding:8px">Add images to see a preview</p>');
					}
				} else {
					$preview.html('<p style="font-size:11px;color:#667085;margin:0;text-align:center;padding:8px">Preview not available</p>');
				}
			});
		}, 600);
	}

	$(document).ready(function(){

		$('#wphs-gallery-new').on('click', function(){ openEditor(); });
		$('#wphs-gallery-cancel').on('click', closeEditor);

		$(document).on('click', '.wphs-gallery-edit', function(){
			var id = parseInt($(this).data('id'));
			var g = null;
			$.each(_galleries, function(i, x){ if(x.id === id){ g = x; return false; } });
			if(g) openEditor(g);
		});

		$(document).on('click', '.wphs-gallery-delete', function(){
			if(!confirm('Delete this gallery?')) return;
			var id = parseInt($(this).data('id'));
			$.post(_ajaxurl, {action:'wphs_delete_gallery', nonce:_nonce, gallery_id:id}, function(resp){
				if(resp && resp.success){
					$('#wphs-gallery-list .wphs-gallery-item[data-id="' + id + '"]').remove();
					toast('Gallery deleted', true);
				} else { toast('Error deleting gallery', false); }
			});
		});

		$(document).on('click', '.wphs-remove-thumb', function(e){
			e.preventDefault();
			$(this).closest('.wphs-gallery-thumb').remove();
			getIds();
		});

		$(document).on('click', '.wphs-gallery-copy-sc', function(){
			var sc = $(this).data('sc');
			if(!sc) return;
			if(navigator.clipboard){ navigator.clipboard.writeText(sc); }
			toast('Shortcode copied!', true);
		});

		$('#wphs-gallery-add-images').on('click', function(){
			if(_mediaFrame){ _mediaFrame.open(); return; }
			_mediaFrame = wp.media({ title:'Select images', multiple:true, library:{type:'image'} });
			_mediaFrame.on('select', function(){
				var sel = _mediaFrame.state().get('selection');
				var wrap = document.getElementById('wphs-gallery-thumbs');
				var existing = [];
				$('#wphs-gallery-thumbs .wphs-gallery-thumb').each(function(){ existing.push(String($(this).data('id'))); });
				sel.each(function(att){
					var attData = att.toJSON ? att.toJSON() : att;
					if(existing.indexOf(String(attData.id)) === -1){
						var src = (attData.sizes && attData.sizes.thumbnail) ? attData.sizes.thumbnail.url : (attData.url || '');
						wrap.appendChild(thumbEl(attData.id, src));
						existing.push(String(attData.id));
					}
				});
				getIds();
			});
			_mediaFrame.open();
		});

		$('#wphs-gallery-save').on('click', function(){
			var id     = parseInt($('#wphs-gallery-id').val()) || 0;
			var title  = $('#wphs-gallery-title').val().trim() || 'Gallery';
			var nav    = $('input[name="wphs_gallery_nav"]:checked').val() || 'dots+arrows';
			var ids    = getIds();
			var cols_d = parseInt($('#wphs-gallery-cols-desktop').val()) || 1;
			var cols_m = parseInt($('#wphs-gallery-cols-mobile').val())  || 1;
			var img_r  = parseInt($('#wphs-gallery-img-radius').val())   || 0;
			$.post(_ajaxurl, {
				action:'wphs_save_gallery', nonce:_nonce,
				gallery_id:id, gallery_title:title, gallery_nav:nav,
				gallery_ids:ids, gallery_cols_desktop:cols_d,
				gallery_cols_mobile:cols_m, gallery_img_radius:img_r,
				gallery_caption_bg:$('#wphs-gallery-caption-bg').data('current') || $('#wphs-gallery-caption-bg').val() || '#111827',
				gallery_caption_color:$('#wphs-gallery-caption-color').data('current') || $('#wphs-gallery-caption-color').val() || '#ffffff',
				gallery_captions:JSON.stringify(_captions)
			}, function(resp){
				if(resp && resp.success){
					$('#wphs-gallery-id').val(resp.data.gallery_id);
					$('#wphs-gallery-shortcode').val(resp.data.shortcode);
					$('#wphs-gallery-shortcode-wrap').show();
					toast('Gallery saved', true);
					setTimeout(function(){ location.reload(); }, 1200);
				} else { toast('Save failed', false); }
			});
		});

		// Live preview updater
		$(document).on('change', '#wphs-gallery-cols-desktop, #wphs-gallery-cols-mobile, #wphs-gallery-img-radius, #wphs-gallery-caption-bg, #wphs-gallery-caption-color', function(){ updateGalleryPreview(); });
		// Sync caption color pickers with hex inputs and swatch
		$(document).on('input change', '#wphs-gallery-caption-bg', function(){
			var v=$(this).val(); $(this).data('current',v); $('#wphs-gallery-caption-bg-hex').val(v); $(this).closest('.wphs-color-row').find('.wphs-color-swatch').css('background',v); updateGalleryPreview();
		});
		$(document).on('input change', '#wphs-gallery-caption-color', function(){
			var v=$(this).val(); $(this).data('current',v); $('#wphs-gallery-caption-color-hex').val(v); $(this).closest('.wphs-color-row').find('.wphs-color-swatch').css('background',v); updateGalleryPreview();
		});
		$(document).on('change', '#wphs-gallery-caption-bg-hex', function(){
			var v=$(this).val(); if(/^#[0-9a-fA-F]{6}$/.test(v)){ $('#wphs-gallery-caption-bg').val(v); $(this).closest('.wphs-color-row').find('.wphs-color-swatch').css('background',v); updateGalleryPreview(); }
		});
		$(document).on('change', '#wphs-gallery-caption-color-hex', function(){
			var v=$(this).val(); if(/^#[0-9a-fA-F]{6}$/.test(v)){ $('#wphs-gallery-caption-color').val(v); $(this).closest('.wphs-color-row').find('.wphs-color-swatch').css('background',v); updateGalleryPreview(); }
		});

		// Caption button
		$(document).on('click', '.wphs-thumb-caption-btn', function(e){
			e.stopPropagation();
			var imgId = String($(this).data('id'));
			$('#wphs-caption-img-id').val(imgId);
			$('#wphs-caption-title').val((_captions[imgId] && _captions[imgId].title) || '');
			$('#wphs-caption-desc').val((_captions[imgId] && _captions[imgId].desc)  || '');
			$('#wphs-gallery-caption-panel').slideDown(150);
			// Highlight the T button
			$('.wphs-thumb-caption-btn').css('background','rgba(11,18,32,.65)');
			$(this).css('background','#3fe0a0');
		});
		$('#wphs-caption-save').on('click', function(){
			var imgId = String($('#wphs-caption-img-id').val());
			var t = $('#wphs-caption-title').val().trim();
			var d = $('#wphs-caption-desc').val().trim();
			if(!imgId) return;
			if(t || d){ _captions[imgId] = {title:t, desc:d}; }
			else { delete _captions[imgId]; }
			// Update T button color
			$('.wphs-thumb-caption-btn[data-id="'+imgId+'"]').css('background',(t||d)?'#3fe0a0':'rgba(11,18,32,.65)');
			$('#wphs-gallery-caption-panel').slideUp(150);
			updateGalleryPreview();
		});
		$('#wphs-caption-close').on('click', function(){
			$('#wphs-gallery-caption-panel').slideUp(150);
			$('.wphs-thumb-caption-btn').css('background','rgba(11,18,32,.65)');
		});
		$(document).on('click', '.wphs-remove-thumb', function(){ setTimeout(updateGalleryPreview, 50); });

		$('#wphs-gallery-copy-sc').on('click', function(){
			var v = $('#wphs-gallery-shortcode').val();
			if(!v) return;
			if(navigator.clipboard){ navigator.clipboard.writeText(v); }
			toast('Shortcode copied!', true);
		});

	});
})(jQuery);
