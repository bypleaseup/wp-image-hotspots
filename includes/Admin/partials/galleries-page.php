<?php
/**
 * Admin galleries page template.
 *
 * Rendered by Pleaseup\WPImageHotspots\Admin\GalleriesPage::render().
 * Variables in scope (prepared by the page class):
 *   - array  $galleries  (list of hydrated galleries)
 *   - string $nonce      (current admin nonce)
 *   - string $ajaxurl
 *
 * @package Pleaseup\WPImageHotspots\Admin
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap" id="wphs-wrap">

	<div class="wphs-hero">
		<div style="flex:1">
			<h1><?php echo esc_html__( 'WP Image', 'wp-image-hotspots' ); ?> <span><?php esc_html_e( 'HotSpots', 'wp-image-hotspots' ); ?></span></h1>
			<div style="display:flex;align-items:center;gap:14px;margin-top:2px">
				<a href="https://by.pleaseup.com/wp-image-hotspots/" target="_blank" rel="noopener" class="wphs-hero-credit">by.pleaseup.com</a>
				<span class="wphs-hero-version">v<?php echo esc_html( WPHS_VERSION ); ?></span>
			</div>
		</div>
	</div>

	<div style="margin:0 0 20px 0;margin-right:16px;padding-bottom:16px;border-bottom:1px solid rgba(16,24,40,.08)">
		<h2 style="font-size:16px;font-weight:700;color:#0b1220;margin:0 0 6px 0"><?php esc_html_e( 'Galleries', 'wp-image-hotspots' ); ?></h2>
		<p style="font-size:12px;color:#667085;margin:0"><?php esc_html_e( 'Create a gallery by grouping images that already have hotspots configured.', 'wp-image-hotspots' ); ?></p>
		<p style="font-size:12px;color:#667085;margin:4px 0 12px"><?php esc_html_e( 'Use the shortcode [wphs_gallery id="X"] to embed it anywhere on your site.', 'wp-image-hotspots' ); ?></p>
		<button type="button" class="wphs-btn-primary wphs-btn-sm" id="wphs-gallery-new">+ <?php esc_html_e( 'New gallery', 'wp-image-hotspots' ); ?></button>
	</div>

	<div class="wphs-grid" id="wphs-galleries-wrap">

		<?php /* ── Left: gallery list ── */ ?>
		<div class="wphs-card" id="wphs-gallery-list-wrap">
	
			<div id="wphs-gallery-list">
			<?php if ( empty( $galleries ) ) : ?>
				<div id="wphs-canvas-empty" style="min-height:160px">
					<div class="wphs-empty-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></div>
					<h2><?php esc_html_e( 'No galleries yet', 'wp-image-hotspots' ); ?></h2>
					<p><?php esc_html_e( 'Click "New gallery" to create the first one.', 'wp-image-hotspots' ); ?></p>
				</div>
			<?php else : ?>
				<?php foreach ( $galleries as $g ) :
					$img_count = count( $g['image_ids'] );
					$sc = '[wphs_gallery id="' . $g['id'] . '" nav="' . $g['nav'] . '"]';
				?>
				<div class="wphs-card wphs-gallery-item" data-id="<?php echo (int) $g['id']; ?>" style="margin-bottom:12px;padding:16px">

					<?php /* Header: title + nav + shortcode + buttons */ ?>
					<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
						<div style="flex:1;min-width:0">
							<span style="font-size:13px;font-weight:700;color:#0b1220"><?php echo esc_html( $g['title'] ); ?></span>
							<span style="margin-left:8px;font-size:11px;color:#667085">nav: <strong><?php echo esc_html( $g['nav'] ); ?></strong></span>
						</div>
						<div class="wphs-shortcode-controls" style="max-width:220px">
							<input type="text" class="wphs-shortcode-field" readonly value="<?php echo esc_attr( $sc ); ?>" />
							<button type="button" class="wphs-copy-icon wphs-gallery-copy-sc" data-sc="<?php echo esc_attr( $sc ); ?>">
								<svg viewBox="0 0 24 24"><path fill="currentColor" d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 18H8V7h11v16z"/></svg>
							</button>
						</div>
						<button type="button" class="wphs-btn-ghost wphs-btn-sm wphs-gallery-edit" data-id="<?php echo (int) $g['id']; ?>"><?php esc_html_e( 'Edit', 'wp-image-hotspots' ); ?></button>
						<button type="button" class="wphs-btn-ghost wphs-btn-sm wphs-btn-danger wphs-gallery-delete" data-id="<?php echo (int) $g['id']; ?>"><?php esc_html_e( 'Delete', 'wp-image-hotspots' ); ?></button>
					</div>

					<?php /* Divider */ ?>
					<div class="wphs-divider"></div>

					<?php /* Thumbs */ ?>
					<?php if ( ! empty( $g['image_ids'] ) ) : ?>
					<div style="display:flex;gap:10px;flex-wrap:wrap">
						<?php foreach ( array_slice( $g['image_ids'], 0, 8 ) as $img_id ) :
							$thumb_url   = wp_get_attachment_image_url( $img_id, 'thumbnail' );
							$thumb_title = wp_trim_words( get_the_title( $img_id ), 3, '&#8230;' );
							$thumb_hs    = json_decode( (string) get_post_meta( $img_id, '_wphs_hotspots_v1', true ), true );
							$thumb_hs_n  = is_array( $thumb_hs ) ? count( $thumb_hs ) : 0;
							$thumb_edit  = admin_url( 'admin.php?page=wphs-editor&attachment_id=' . $img_id );
							if ( ! $thumb_url ) { continue; }
						?>
						<div class="wphs-thumb">
							<a href="<?php echo esc_url( $thumb_edit ); ?>" class="wphs-thumb-link">
								<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( get_the_title( $img_id ) ); ?>" />
								<small><?php echo esc_html( $thumb_title ); ?></small>
								<small><?php
									printf(
										/* translators: %d: number of hotspots on the image. */
										esc_html( _n( '%d hotspot', '%d hotspots', $thumb_hs_n, 'wp-image-hotspots' ) ),
										(int) $thumb_hs_n
									);
								?></small>
							</a>
						</div>
						<?php endforeach; ?>
						<?php if ( count( $g['image_ids'] ) > 8 ) : ?>
						<div class="wphs-thumb" style="display:flex;align-items:center;justify-content:center;height:70px;border-radius:9px;border:1.5px solid rgba(16,24,40,.09);background:#f5f5f2;font-size:11px;font-weight:700;color:#667085">+<?php echo (int) ( count( $g['image_ids'] ) - 8 ); ?></div>
						<?php endif; ?>
					</div>
					<?php else : ?>
					<p style="font-size:12px;color:#667085;margin:0"><?php esc_html_e( 'No images yet.', 'wp-image-hotspots' ); ?></p>
					<?php endif; ?>

				</div>
				<?php endforeach; ?>
			<?php endif; ?>
			</div>
		</div>

		<?php /* ── Right: editor panel ── */ ?>
		<div id="wphs-gallery-editor" style="display:none">
			<div class="wphs-img-settings-panel">

				<input type="hidden" id="wphs-gallery-id" value="0" />

				<?php /* Gallery name + badge */ ?>
				<div class="wphs-f" style="margin-bottom:4px">
					<div style="display:flex;align-items:center;gap:6px">
						<span class="wphs-fl" style="margin:0"><?php esc_html_e( 'Gallery name', 'wp-image-hotspots' ); ?></span>
						<span class="wphs-badge wphs-badge-default" id="wphs-gallery-editor-badge"><?php echo esc_html_x( 'new', 'gallery state badge', 'wp-image-hotspots' ); ?></span>
					</div>
				</div>
				<input type="text" id="wphs-gallery-title" placeholder="<?php esc_attr_e( 'e.g. Spring collection', 'wp-image-hotspots' ); ?>" style="border:1px solid rgba(16,24,40,.15);border-radius:8px;padding:8px 12px;font-size:13px;color:#0b1220;width:100%;margin-bottom:12px" />

				<?php /* Shortcode */ ?>
				<div id="wphs-gallery-shortcode-wrap" style="display:none;margin-bottom:12px">
					<div class="wphs-settings-section-label" style="font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:#667085;margin-bottom:6px"><?php esc_html_e( 'Shortcode', 'wp-image-hotspots' ); ?></div>
					<div class="wphs-shortcode-controls" style="max-width:100%">
						<input type="text" id="wphs-gallery-shortcode" readonly class="wphs-shortcode-field" />
						<button type="button" class="wphs-copy-icon" id="wphs-gallery-copy-sc">
							<svg viewBox="0 0 24 24"><path fill="currentColor" d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 18H8V7h11v16z"/></svg>
						</button>
					</div>
				</div>

				<?php /* Actions */ ?>
				<div style="display:flex;gap:8px;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid rgba(16,24,40,.08)">
					<button type="button" class="wphs-btn-ghost" id="wphs-gallery-cancel" style="flex:1"><?php esc_html_e( 'Cancel', 'wp-image-hotspots' ); ?></button>
					<button type="button" class="wphs-btn-primary" id="wphs-gallery-save" style="flex:1"><?php esc_html_e( 'Save gallery', 'wp-image-hotspots' ); ?></button>
				</div>

				<?php /* Navigation */ ?>
				<div class="wphs-settings-section-divider" style="border:none;border-top:1px solid rgba(16,24,40,.08);margin:14px 0"></div>
				<div class="wphs-settings-section-label" style="font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:#667085;margin-bottom:8px"><?php esc_html_e( 'Navigation', 'wp-image-hotspots' ); ?></div>
				<div class="wphs-switch-group" style="margin-bottom:14px">
					<input type="radio" name="wphs_gallery_nav" value="dots+arrows" id="wphs-nav-da" checked>
					<input type="radio" name="wphs_gallery_nav" value="dots"        id="wphs-nav-d">
					<input type="radio" name="wphs_gallery_nav" value="arrows"      id="wphs-nav-a">
					<input type="radio" name="wphs_gallery_nav" value="none"        id="wphs-nav-n">
					<button type="button" class="wphs-switch-opt is-active" data-for="wphs-nav-da"><?php esc_html_e( 'Dots + Arrows', 'wp-image-hotspots' ); ?></button>
					<button type="button" class="wphs-switch-opt" data-for="wphs-nav-d"><?php esc_html_e( 'Dots', 'wp-image-hotspots' ); ?></button>
					<button type="button" class="wphs-switch-opt" data-for="wphs-nav-a"><?php esc_html_e( 'Arrows', 'wp-image-hotspots' ); ?></button>
					<button type="button" class="wphs-switch-opt" data-for="wphs-nav-n"><?php esc_html_e( 'None', 'wp-image-hotspots' ); ?></button>
				</div>

				<?php /* Images */ ?>
				<div class="wphs-settings-section-divider" style="border:none;border-top:1px solid rgba(16,24,40,.08);margin:14px 0"></div>
				<div class="wphs-settings-section-label" style="font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:#667085;margin-bottom:8px"><?php esc_html_e( 'Images', 'wp-image-hotspots' ); ?></div>
				<div id="wphs-gallery-thumbs" style="display:flex;gap:8px;flex-wrap:wrap;min-height:72px;padding:10px;background:rgba(16,24,40,.03);border:1.5px dashed rgba(16,24,40,.12);border-radius:10px;margin-bottom:10px;align-items:flex-start;align-content:flex-start"></div>
				<input type="hidden" id="wphs-gallery-ids" value="" />
				<button type="button" class="wphs-btn-secondary wphs-btn-sm" id="wphs-gallery-add-images">+ <?php esc_html_e( 'Add images', 'wp-image-hotspots' ); ?></button>

				<?php /* Columns */ ?>
				<div class="wphs-settings-section-divider" style="border:none;border-top:1px solid rgba(16,24,40,.08);margin:14px 0"></div>
				<div class="wphs-settings-section-label" style="font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:#667085;margin-bottom:8px"><?php esc_html_e( 'Columns', 'wp-image-hotspots' ); ?></div>
				<div style="display:flex;gap:12px;margin-bottom:14px">
					<div class="wphs-f" style="flex:1">
						<span class="wphs-fl"><?php esc_html_e( 'Desktop', 'wp-image-hotspots' ); ?></span>
						<input type="number" id="wphs-gallery-cols-desktop" value="1" min="1" max="6" class="wphs-size-input" style="width:100%" />
					</div>
					<div class="wphs-f" style="flex:1">
						<span class="wphs-fl"><?php esc_html_e( 'Mobile', 'wp-image-hotspots' ); ?></span>
						<input type="number" id="wphs-gallery-cols-mobile" value="1" min="1" max="3" class="wphs-size-input" style="width:100%" />
					</div>
					<div class="wphs-f" style="flex:1">
						<span class="wphs-fl"><?php esc_html_e( 'Radius (px)', 'wp-image-hotspots' ); ?></span>
						<input type="number" id="wphs-gallery-img-radius" value="0" min="0" max="999" class="wphs-size-input" style="width:100%" />
					</div>
				</div>

				<?php /* Caption colors */ ?>
				<div style="display:flex;gap:12px;margin-bottom:14px">
					<div class="wphs-f" style="flex:1">
						<span class="wphs-fl"><?php esc_html_e( 'Caption fill', 'wp-image-hotspots' ); ?></span>
						<div class="wphs-color-row">
							<div class="wphs-color-swatch"></div>
							<input type="color" id="wphs-gallery-caption-bg" value="#111827" class="wphs-color-input" />
							<input type="text" id="wphs-gallery-caption-bg-hex" value="#111827" class="wphs-hex-input" maxlength="7" />
						</div>
					</div>
					<div class="wphs-f" style="flex:1">
						<span class="wphs-fl"><?php esc_html_e( 'Caption text', 'wp-image-hotspots' ); ?></span>
						<div class="wphs-color-row">
							<div class="wphs-color-swatch"></div>
							<input type="color" id="wphs-gallery-caption-color" value="#ffffff" class="wphs-color-input" />
							<input type="text" id="wphs-gallery-caption-color-hex" value="#ffffff" class="wphs-hex-input" maxlength="7" />
						</div>
					</div>
				</div>

				<?php /* Caption editor (hidden by default) */ ?>
				<div id="wphs-gallery-caption-panel" style="display:none;margin-bottom:14px;padding:12px;background:rgba(16,24,40,.04);border-radius:8px;border:1px solid rgba(16,24,40,.1)">
					<div style="font-size:11px;font-weight:700;color:#667085;margin-bottom:8px" id="wphs-caption-panel-label"><?php esc_html_e('Caption for image','wp-image-hotspots'); ?></div>
					<input type="hidden" id="wphs-caption-img-id" value="" />
					<div class="wphs-f" style="margin-bottom:8px">
						<span class="wphs-fl"><?php esc_html_e('Title','wp-image-hotspots'); ?></span>
						<input type="text" id="wphs-caption-title" placeholder="" style="border:1px solid rgba(16,24,40,.15);border-radius:7px;padding:7px 10px;font-size:13px;width:100%" />
					</div>
					<div class="wphs-f" style="margin-bottom:8px">
						<span class="wphs-fl"><?php esc_html_e('Description','wp-image-hotspots'); ?></span>
						<textarea id="wphs-caption-desc" rows="2" style="border:1px solid rgba(16,24,40,.15);border-radius:7px;padding:7px 10px;font-size:13px;width:100%;resize:vertical"></textarea>
					</div>
					<div style="display:flex;gap:8px">
						<button type="button" class="wphs-btn-secondary wphs-btn-sm" id="wphs-caption-save"><?php esc_html_e('Apply','wp-image-hotspots'); ?></button>
						<button type="button" class="wphs-btn-ghost wphs-btn-sm" id="wphs-caption-close"><?php esc_html_e('Close','wp-image-hotspots'); ?></button>
					</div>
				</div>

				<?php /* Preview */ ?>
				<div class="wphs-settings-section-divider" style="border:none;border-top:1px solid rgba(16,24,40,.08);margin:14px 0"></div>
				<div class="wphs-settings-section-label" style="font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:#667085;margin-bottom:8px"><?php esc_html_e( 'Preview', 'wp-image-hotspots' ); ?></div>
				<div id="wphs-gallery-preview" style="background:rgba(16,24,40,.03);border:1.5px dashed rgba(16,24,40,.12);border-radius:10px;overflow:hidden;min-height:60px;margin-bottom:14px">
					<p style="font-size:11px;color:#667085;margin:0;text-align:center"><?php esc_html_e( 'Add images to see a preview', 'wp-image-hotspots' ); ?></p>
				</div>

				<?php /* Shortcode output */ ?>


			</div>
		</div>

	</div><!-- /.wphs-grid -->

	<div id="wphs-toast" class="wphs-toast" role="status" aria-live="polite"></div>
</div><!-- /#wphs-wrap -->
