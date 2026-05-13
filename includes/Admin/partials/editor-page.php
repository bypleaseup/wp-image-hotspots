<?php
/**
 * Admin editor page template.
 *
 * Rendered by Pleaseup\WPImageHotspots\Admin\EditorPage::render().
 * Variables in scope (prepared by the page class):
 *   - int   $attachment_id
 *   - array $opts
 *   - string $icon_url
 *   - string $image_url
 *   - string $saved_at
 *   - string $json
 *   - array $img_settings
 *   - string $img_icon_url
 *   - string $tt_bg, $tt_color
 *   - int    $tt_radius, $tt_tail
 *   - array  $recent       (recent images with hotspots)
 *   - array  $hotspots_arr (hydrated hotspots)
 *
 * @package Pleaseup\WPImageHotspots\Admin
 */

defined( 'ABSPATH' ) || exit;
?>
		<div class="wrap" id="wphs-wrap">

			<?php /* ── Hero ── */ ?>
			<div class="wphs-hero">
				<div style="flex:1">
					<h1><?php echo esc_html__( 'WP Image', 'pleaseup-hotspots' ); ?> <span><?php esc_html_e( 'HotSpots', 'pleaseup-hotspots' ); ?></span></h1>
					<div style="display:flex;align-items:center;gap:14px;margin-top:2px">
						<a href="https://by.pleaseup.com/pleaseup-hotspots/" target="_blank" rel="noopener" class="wphs-hero-credit">by.pleaseup.com</a>
						<span class="wphs-hero-version">v<?php echo esc_html( WPHS_VERSION ); ?></span>
					</div>
				</div>
			</div>
			<div style="margin:0 0 20px 0;margin-right:16px;padding-bottom:16px;border-bottom:1px solid rgba(16,24,40,.08)">
				<h2 style="font-size:16px;font-weight:700;color:#0b1220;margin:0 0 6px 0"><?php esc_html_e( 'Editor', 'pleaseup-hotspots' ); ?></h2>
				<p style="font-size:12px;color:#667085;margin:0"><?php esc_html_e( 'Select an image from the Media Library and click on it to place hotspots.', 'pleaseup-hotspots' ); ?></p>
				<p style="font-size:12px;color:#667085;margin:4px 0 12px"><?php esc_html_e( 'Each hotspot can contain rich text. Use the shortcode [wphs_image id="X"] to embed the image anywhere on your site.', 'pleaseup-hotspots' ); ?></p>
				<div style="display:flex;align-items:center;gap:10px">
					<button type="button" class="wphs-btn-primary wphs-btn-sm" id="wphs-pick">+ <?php esc_html_e( 'Choose image', 'pleaseup-hotspots' ); ?></button>
					<?php if ( $attachment_id ) :
						$sc = sprintf( '[wphs_image id="%d"]', $attachment_id ); ?>
						<div class="wphs-shortcode-controls">
							<input type="text" class="wphs-shortcode-field" readonly value="<?php echo esc_attr( $sc ); ?>" style="padding-right:10px" />
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="wphs-grid">

				<?php /* ── Left: canvas + quick access ── */ ?>
				<div class="wphs-card">
					<div id="wphs-canvas-wrap">
						<div id="wphs-canvas" data-attachment="<?php echo esc_attr( (string) $attachment_id ); ?>">
							<?php if ( $attachment_id && $image_url ) : ?>
								<img id="wphs-image" src="<?php echo esc_url( $image_url ); ?>" alt="" />
								<div id="wphs-overlay"></div>
							<?php else : ?>
								<div id="wphs-canvas-empty">
									<div class="wphs-empty-icon">
										<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
									</div>
									<h2><?php esc_html_e( 'Select an image to start', 'pleaseup-hotspots' ); ?></h2>
									<p><?php esc_html_e( 'Use the "Choose image" button above.', 'pleaseup-hotspots' ); ?></p>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<?php /* ── Quick access ── */ ?>
					<?php
					/* $recent prepared by EditorPage::render() */
					if ( ! empty( $recent ) ) : ?>
						<div class="wphs-divider"></div>
						<div class="wphs-quick-header">
							<span class="wphs-quick-title"><?php esc_html_e( 'Images with hotspots', 'pleaseup-hotspots' ); ?></span>
							<span class="wphs-quick-sub"><?php esc_html_e( 'Click to open', 'pleaseup-hotspots' ); ?></span>
						</div>
						<div class="wphs-recent-grid">
						<?php foreach ( $recent as $img ) :
							$img_id  = (int) $img->ID;
							$img_sc  = sprintf( '[wphs_image id="%d"]', $img_id );
							$img_url = (string) wp_get_attachment_image_url( $img_id, 'thumbnail' );
							$hs_data = json_decode( (string) get_post_meta( $img_id, '_wphs_hotspots_v1', true ), true );
							$hs_count = is_array( $hs_data ) ? count( $hs_data ) : 0;
							$edit_url = admin_url( 'admin.php?page=wphs-editor&attachment_id=' . $img_id );
							?>
							<div class="wphs-thumb">
								<a href="<?php echo esc_url( $edit_url ); ?>" class="wphs-thumb-link">
									<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( get_the_title( $img_id ) ); ?>" />
									<small><?php echo esc_html( wp_trim_words( get_the_title( $img_id ), 3, '…' ) ); ?></small>
									<small><?php
									printf(
										/* translators: %d: number of hotspots on the image. */
										esc_html( _n( '%d hotspot', '%d hotspots', $hs_count, 'pleaseup-hotspots' ) ),
										(int) $hs_count
									);
								?></small>
								</a>
								<div class="wphs-thumb-sc">
									<input type="text" class="wphs-shortcode-field" readonly value="<?php echo esc_attr( $img_sc ); ?>" style="padding-right:7px" />
								</div>
							</div>
						<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<?php /* ── Right: controls ── */ ?>
				<div id="wphs-right-panel" <?php echo $attachment_id ? '' : 'style="display:none"'; ?>>

					<div class="wphs-card">

						<?php /* Meta + actions */ ?>
						<div class="wphs-meta-row">
							<span>
								<strong><?php esc_html_e( 'Last save:', 'pleaseup-hotspots' ); ?></strong>
								<span id="wphs-saved-at" style="margin-left:4px"><?php echo $saved_at ? esc_html( $saved_at ) : esc_html__( 'never', 'pleaseup-hotspots' ); ?></span>
							</span>
							<span class="wphs-count-pill" id="wphs-count">0</span>
						</div>

						<div class="wphs-action-row">
							<button type="button" class="wphs-btn-ghost wphs-btn-danger" id="wphs-del"><?php esc_html_e( 'Delete', 'pleaseup-hotspots' ); ?></button>
							<button type="button" class="wphs-btn-ghost" id="wphs-clear"><?php esc_html_e( 'Remove all', 'pleaseup-hotspots' ); ?></button>
							<button type="button" class="wphs-btn-primary" id="wphs-save" style="flex:1"><?php esc_html_e( 'Save', 'pleaseup-hotspots' ); ?></button>
						</div>

						<?php /* HTML editor */ ?>
						<div id="wphs-editor-dock" class="wphs-editor-dock-hidden">
							<?php
							wp_editor(
								'',
								'wphs_hotspot_html_editor',
								array(
									'textarea_name' => 'wphs_hotspot_html_editor',
									'textarea_rows' => 6,
									'media_buttons' => false,
									'teeny'         => true,
									'wpautop'       => false,
									'quicktags'     => array( 'buttons' => 'strong,em,ul,ol,li,link,close' ),
									'tinymce'       => array(
										'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink,removeformat',
										'toolbar2' => '',
										'setup'    => 'function(ed){ed.on("change keyup", function(){ try{jQuery("#"+ed.id).trigger("change");}catch(e){} });}' ,
									),
								)
							);
							?>
							<p class="wphs-muted" style="margin-top:6px"><?php esc_html_e( 'Select a hotspot to edit its content.', 'pleaseup-hotspots' ); ?></p>
						</div>


					</div>

					<?php /* ── Unified preview + hotspot/tooltip settings ── */ ?>
					<div class="wphs-img-settings-panel" id="wphs-img-settings-panel">

						<div class="wphs-settings-panel-head">
							<div style="display:flex;align-items:center;gap:7px">
								<h3><?php esc_html_e( 'Hotspot & Tooltip style', 'pleaseup-hotspots' ); ?></h3>
								<span class="wphs-badge wphs-badge-override" id="wphs-img-badge"><?php esc_html_e( 'this image', 'pleaseup-hotspots' ); ?></span>
							</div>
						</div>

						<?php /* Live preview */ ?>
						<div style="display:flex;justify-content:flex-end;margin-bottom:6px">
							<button type="button" id="wphs-preview-toggle" class="wphs-btn-secondary wphs-btn-sm" style="font-size:10px;padding:4px 10px;height:auto"><?php esc_html_e( 'Preview: normal', 'pleaseup-hotspots' ); ?></button>
						</div>
						<div class="wphs-unified-preview" id="wphs-unified-preview">
							<svg id="wphs-preview-svg" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:1;overflow:visible"><line id="wphs-preview-line" stroke-width="3" fill="none"/></svg>
							<div class="wphs-preview-dot" id="wphs-preview-dot" style="position:relative;z-index:2;flex-shrink:0;margin-top:4px"></div>
							<div class="wphs-preview-bubble" id="wphs-preview-bubble" style="position:relative;z-index:2">
								<div class="wphs-tt-title">Title</div>
								<div class="wphs-tt-desc"><?php esc_html_e( 'Description…', 'pleaseup-hotspots' ); ?></div>
							</div>
						</div>

						<div id="wphs-img-settings-fields">

							<?php /* Hotspot style */ ?>
							<div class="wphs-settings-section-label"><?php esc_html_e( 'Hotspot', 'pleaseup-hotspots' ); ?></div>
							<div class="wphs-switch-group" id="wphs-img-style-switch">
								<input type="radio" name="wphs_img_style" value="default" id="wphs-img-style-default" checked />
								<input type="radio" name="wphs_img_style" value="image"   id="wphs-img-style-image" />
								<button type="button" class="wphs-switch-opt is-active" data-for="wphs-img-style-default"><?php esc_html_e( 'Default dot', 'pleaseup-hotspots' ); ?></button>
								<button type="button" class="wphs-switch-opt" data-for="wphs-img-style-image" id="wphs-img-style-image-btn"><?php esc_html_e( 'Custom icon', 'pleaseup-hotspots' ); ?></button>
							</div>
							<div class="wphs-icon-row" id="wphs-icon-row">
								<div style="display:flex;flex-direction:column;gap:6px;width:100%">
									<div style="display:flex;align-items:center;gap:8px">
										<div class="wphs-icon-box" id="wphs-img-icon-preview"><?php if($img_icon_url): ?><img src="<?php echo esc_url($img_icon_url); ?>" style="max-width:100%;height:auto" alt="" /><?php else: ?><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="rgba(16,24,40,.3)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg><?php endif; ?></div>
										<span class="wphs-fl" style="flex:1"><?php esc_html_e( 'Normal', 'pleaseup-hotspots' ); ?> <span style="color:#0b1220;font-size:10px;font-weight:800"><?php esc_html_e( '(required)', 'pleaseup-hotspots' ); ?></span></span>
										<button type="button" class="wphs-btn-secondary wphs-btn-sm" id="wphs-img-pick-icon"><?php esc_html_e( 'Choose', 'pleaseup-hotspots' ); ?></button>
										<button type="button" class="wphs-btn-ghost wphs-btn-sm" id="wphs-img-remove-icon"<?php if(!$img_icon_url) echo ' disabled'; ?>><?php esc_html_e( 'Remove', 'pleaseup-hotspots' ); ?></button>
									</div>
									<div style="display:flex;align-items:center;gap:8px">
										<?php
										$sel_icon_id  = (int)($img_settings['hotspot_selected_image_id'] ?? 0);
										$sel_icon_url = $sel_icon_id ? (wp_get_attachment_image_url($sel_icon_id,'thumbnail') ?: '') : '';
										?>
										<div class="wphs-icon-box" id="wphs-img-sel-icon-preview"><?php if($sel_icon_url): ?><img src="<?php echo esc_url($sel_icon_url); ?>" style="max-width:100%;height:auto" alt="" /><?php else: ?><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="rgba(16,24,40,.3)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg><?php endif; ?></div>
										<span class="wphs-fl" style="flex:1"><?php esc_html_e( 'Selected', 'pleaseup-hotspots' ); ?></span>
										<button type="button" class="wphs-btn-secondary wphs-btn-sm" id="wphs-img-pick-sel-icon"><?php esc_html_e( 'Choose', 'pleaseup-hotspots' ); ?></button>
										<button type="button" class="wphs-btn-ghost wphs-btn-sm" id="wphs-img-remove-sel-icon"<?php if(!$sel_icon_url) echo ' disabled'; ?>><?php esc_html_e( 'Remove', 'pleaseup-hotspots' ); ?></button>
									</div>
								</div>
								<input type="hidden" id="wphs-img-icon-id" value="<?php echo esc_attr( (string)($img_settings['hotspot_image_id'] ?? 0) ); ?>" />
								<input type="hidden" id="wphs-img-sel-icon-id" value="<?php echo esc_attr( (string)$sel_icon_id ); ?>" />
							</div>
							<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px">

								<?php /* Left col: W + lock + H on same row, Radius below */ ?>
								<div class="wphs-f" style="display:flex;flex-direction:column;gap:6px">
									<div style="display:flex;align-items:flex-end;gap:4px">
										<div class="wphs-f" style="flex:1"><span class="wphs-fl"><?php esc_html_e( 'W', 'pleaseup-hotspots' ); ?></span><input type="number" id="wphs-img-dot-width" value="<?php echo absint( $img_settings['dot_width'] ); ?>" min="4" max="256" class="wphs-size-input" /></div>
										<button type="button" id="wphs-dot-lock" title="Lock" style="flex-shrink:0;background:#fff;border:1.5px solid rgba(16,24,40,.15);border-radius:6px;padding:5px 6px;cursor:pointer;color:#667085;display:flex;align-items:center;justify-content:center;margin-bottom:1px">
											<svg id="wphs-lock-icon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="display:none"><circle cx="12" cy="12" r="10"/></svg>
											<svg id="wphs-unlock-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/></svg>
										</button>
										<div class="wphs-f" style="flex:1"><span class="wphs-fl"><?php esc_html_e( 'H', 'pleaseup-hotspots' ); ?></span><input type="number" id="wphs-img-dot-height" value="<?php echo absint( $img_settings['dot_height'] ); ?>" min="4" max="256" class="wphs-size-input" /></div>
									</div>
									<div class="wphs-f"><span class="wphs-fl"><?php esc_html_e( 'Radius (px)', 'pleaseup-hotspots' ); ?></span><input type="number" id="wphs-img-dot-radius" value="<?php echo absint( $img_settings['dot_radius'] ); ?>" min="0" max="999" class="wphs-size-input" /></div>
								</div>

								<?php /* Right col: Fill, Border */ ?>
								<div class="wphs-f wphs-dot-color-fields" id="wphs-dot-color-fields" style="display:flex;flex-direction:column;gap:8px">
									<div class="wphs-f"><span class="wphs-fl"><?php esc_html_e( 'Fill', 'pleaseup-hotspots' ); ?></span><div class="wphs-color-row"><div class="wphs-color-swatch"></div><input type="color" id="wphs-img-dot-color" value="#000000" class="wphs-color-input" /><input type="text" id="wphs-img-dot-color-hex" value="#000000" class="wphs-hex-input" maxlength="7" /></div></div>
									<div class="wphs-f"><span class="wphs-fl"><?php esc_html_e( 'Border', 'pleaseup-hotspots' ); ?></span><div class="wphs-color-row"><div class="wphs-color-swatch"></div><input type="color" id="wphs-img-dot-border-color" value="#ffffff" class="wphs-color-input" /><input type="text" id="wphs-img-dot-border-color-hex" value="#ffffff" class="wphs-hex-input" maxlength="7" /></div></div>
									<div class="wphs-f"><span class="wphs-fl"><?php esc_html_e( 'Fill selected', 'pleaseup-hotspots' ); ?></span><div class="wphs-color-row"><div class="wphs-color-swatch"></div><input type="color" id="wphs-img-dot-selected-fill" value="<?php echo esc_attr( $img_settings['dot_selected_fill'] ); ?>" class="wphs-color-input" /><input type="text" id="wphs-img-dot-selected-fill-hex" value="<?php echo esc_attr( $img_settings['dot_selected_fill'] ); ?>" class="wphs-hex-input" maxlength="7" /></div></div>
									<div class="wphs-f"><span class="wphs-fl"><?php esc_html_e( 'Border selected', 'pleaseup-hotspots' ); ?></span><div class="wphs-color-row"><div class="wphs-color-swatch"></div><input type="color" id="wphs-img-dot-selected-border" value="<?php echo esc_attr( $img_settings['dot_selected_border'] ); ?>" class="wphs-color-input" /><input type="text" id="wphs-img-dot-selected-border-hex" value="<?php echo esc_attr( $img_settings['dot_selected_border'] ); ?>" class="wphs-hex-input" maxlength="7" /></div></div>
								</div>

							</div>

							<?php /* Tooltip style */ ?>
							<div class="wphs-settings-section-divider"></div>
							<div class="wphs-settings-section-label"><?php esc_html_e( 'Tooltip', 'pleaseup-hotspots' ); ?></div>
							<div class="wphs-style-grid">
								<div class="wphs-style-field">
									<span class="wphs-style-lbl"><?php esc_html_e( 'Border radius (px)', 'pleaseup-hotspots' ); ?></span>
									<input type="number" id="wphs-tt-radius" value="<?php echo absint( $tt_radius ); ?>" min="0" max="32" class="wphs-size-input" />
								</div>
								<div style="display:flex;flex-direction:column;gap:8px">
									<div class="wphs-style-field">
										<span class="wphs-style-lbl"><?php esc_html_e( 'Background', 'pleaseup-hotspots' ); ?></span>
										<div class="wphs-color-row">
											<div class="wphs-color-swatch"></div>
											<input type="color" id="wphs-tt-bg" value="<?php echo esc_attr( $tt_bg ); ?>" class="wphs-color-input" />
											<input type="text" id="wphs-tt-bg-hex" value="<?php echo esc_attr( $tt_bg ); ?>" class="wphs-hex-input" maxlength="7" />
										</div>
									</div>
									<div class="wphs-style-field">
										<span class="wphs-style-lbl"><?php esc_html_e( 'Text', 'pleaseup-hotspots' ); ?></span>
										<div class="wphs-color-row">
											<div class="wphs-color-swatch"></div>
											<input type="color" id="wphs-tt-color" value="<?php echo esc_attr( $tt_color ); ?>" class="wphs-color-input" />
											<input type="text" id="wphs-tt-color-hex" value="<?php echo esc_attr( $tt_color ); ?>" class="wphs-hex-input" maxlength="7" />
										</div>
									</div>
								</div>

							</div><!-- /wphs-style-grid -->
							<div style="display:flex;gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid rgba(16,24,40,.08)">
								<button type="button" class="wphs-btn-ghost" id="wphs-reset-img-settings" style="flex:1"><?php esc_html_e( 'Reset', 'pleaseup-hotspots' ); ?></button>
								<button type="button" class="wphs-btn-primary" id="wphs-save-img-settings" style="flex:1"><?php esc_html_e( 'Save', 'pleaseup-hotspots' ); ?></button>
							</div>
						</div>
					</div>

					<input type="hidden" id="wphs-hotspots-json" value="<?php echo esc_attr( $json ); ?>" />

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wphs-save-form" style="display:none">
						<input type="hidden" name="action" value="wphs_save_hotspots_post" />
						<input type="hidden" name="attachment_id" value="<?php echo (int) $attachment_id; ?>" />
						<?php wp_nonce_field( 'wphs_save_hotspots_post_' . (int) $attachment_id ); ?>
						<input type="hidden" name="hotspots_json" id="wphs-save-hotspots-json" value="" />
					</form>

				</div>
			</div>
			<div id="wphs-toast" class="wphs-toast" role="status" aria-live="polite"></div>
		</div>
