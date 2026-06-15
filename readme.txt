=== Image Hotspots by Pleaseup – Interactive Image Map & Tooltips ===
Contributors:      bypleaseup
Tags:              image hotspot, image map, interactive image, tooltip, annotation
Requires at least: 6.0
Tested up to:      7.0
Requires PHP:      7.4
Stable tag:        3.1.1
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Interactive image hotspots with drag & drop editor. Add tooltips, annotations, and image maps. Works with Gutenberg, ACF, and galleries.

== Description ==

**Image Hotspots by Pleaseup** is a responsive **image hotspot** plugin that lets you add **interactive image maps**, **tooltips**, and **annotations** to any image with a visual drag & drop editor.

== Screenshots ==

1. Plugin admin overview — image hotspot collections listed in the WordPress back-office.

= Key Features =

* **Drag & drop editor** — add, move, and delete hotspots visually in the WordPress back-office.
* **HTML tooltip layers** — each hotspot opens a fully customisable HTML layer (supports the built-in WP editor).
* **Media Library integration** — works with any image already uploaded to WordPress.
* **Gutenberg gallery support** — attach hotspots to images inside Gutenberg galleries.
* **ACF support** — compatible with ACF image, gallery, and repeater fields.
* **Shortcode** — embed any hotspot image anywhere with `[wphs_image id="123"]`.
* **Gallery slider** — group multiple hotspot images into a responsive carousel with `[wphs_gallery id="X"]`.
* **Custom hotspot icon** — replace the default dot with any image from the Media Library.
* **Accessible by default** — keyboard navigation, screen-reader labels, focus indicators, `prefers-reduced-motion` support.
* **Extensible** — 9 filters / actions for theme and plugin developers.
* **Frontend rendering** — physical CSS/JS files, on-demand enqueue, no inline assets in the head.

= Shortcode usage =

`[wphs_image id="ATTACHMENT_ID"]`

Optional attributes:

* `size` — image size (default: `large`). Accepts any registered WordPress image size.
* `class` — additional CSS class added to the `<img>` tag.

`[wphs_gallery id="GALLERY_ID" nav="dots+arrows" cols_desktop="3" cols_mobile="1"]`

= Requirements =

* WordPress 6.0 or later
* PHP 7.4 or later
* jQuery (included with WordPress)

= Extension hooks =

Themes and plugins can integrate via the following filters and actions:

* `wphs_default_settings` (filter) — modify the default plugin options.
* `wphs_hotspot_data` (filter) — modify the hotspot list before rendering.
* `wphs_tooltip_html` (filter) — modify the per-hotspot tooltip HTML.
* `wphs_allowed_iframe_attrs` (filter) — extend the iframe allowlist used by `wp_kses`.
* `wphs_allowed_iframe_hosts` (filter) — extend the host allowlist for iframe `src` attributes.
* `wphs_oembed_providers` (filter) — enable/disable server-side oEmbed providers.
* `wphs_hotspot_aria_label` (filter) — override the default screen-reader label.
* `wphs_before_render_image` (action) — fire just before `[wphs_image]` outputs HTML.
* `wphs_after_save_hotspots` (action) — fire after hotspots are persisted.
* `wphs_loaded` (action) — fire after the plugin has booted all subsystems.

== Installation ==

1. Upload the `pleaseup-hotspots` folder to the `/wp-content/plugins/` directory, or install the plugin directly from the WordPress plugin repository.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **Image Hotspots → Editor** in the admin sidebar.
4. Select an image from the Media Library and start adding hotspots.

== Frequently Asked Questions ==

= How do I add a hotspot to an image? =

Go to **Image Hotspots → Editor**, select an image from the Media Library, then click anywhere on the image preview to create a hotspot. Drag it to reposition it, then click the hotspot to edit the HTML content of the popup layer.

= How do I display the image with hotspots on the front end? =

Use the shortcode `[wphs_image id="123"]` where `123` is the attachment ID of the image. You can also use the shortcode in any post, page, or widget area that supports shortcodes.

= Does it work with Gutenberg image blocks? =

Yes. Hotspots are stored against the attachment ID, so any block that references that attachment (image block, gallery block, ACF image fields) inherits the configured hotspots when rendered through the `[wphs_image]` shortcode.

= Does it work with page builders (Elementor, Divi, Beaver Builder)? =

Yes via the `[wphs_image]` and `[wphs_gallery]` shortcodes. CSS and JS are enqueued defensively for builders that render shortcodes after `wp_head`.

= Are hotspot positions responsive? =

Yes. Coordinates are stored as percentages of the natural image dimensions, so they scale with any rendered image size.

= Can I use a custom icon instead of the default dot? =

Yes. In the per-image settings, switch the hotspot style to *Image*, upload or choose your custom icon from the Media Library, and save.

= Does it work with ACF? =

Yes. The plugin stores hotspot data as post meta on the attachment, so any image displayed via `wp_get_attachment_image()` — including ACF image, gallery, and repeater fields — can use the shortcode to render with hotspots.

= Will uninstalling the plugin delete my data? =

Yes. When you uninstall the plugin (not just deactivate it), all hotspot settings, attachment meta, and the plugin's custom post type contents (galleries and tooltips) are removed from the database.

== External services ==

This plugin contacts third-party services in two distinct flows. Both flows are triggered only by an authenticated editor (with `edit_posts`) pasting a URL inside a tooltip; no data leaves the site automatically.

**Server-side oEmbed resolution** (`wp_oembed_get`)

When the editor pastes a URL from one of the supported oEmbed providers into a tooltip, the plugin calls the provider's oEmbed endpoint server-side via WordPress core's `wp_oembed_get()` to retrieve the embed HTML. The URL pasted by the editor is sent to the provider; no other data is sent. Supported providers:

* YouTube — Terms: https://www.youtube.com/t/terms — Privacy: https://policies.google.com/privacy
* Vimeo — Terms: https://vimeo.com/terms — Privacy: https://vimeo.com/privacy
* Spotify — Terms: https://www.spotify.com/legal/end-user-agreement/ — Privacy: https://www.spotify.com/legal/privacy-policy/

**Client-side iframe rendering**

When a tooltip containing an oEmbed iframe is opened on a public page, the visitor's browser loads the iframe directly from the provider's CDN (e.g. `youtube.com`, `player.vimeo.com`, `open.spotify.com`). This is standard browser behavior for any embedded media; the plugin does not add any tracking on top.

The set of allowed iframe hosts is filterable via the `wphs_allowed_iframe_hosts` filter. Hosts not in the allowlist are stripped from tooltip HTML on save.

== Changelog ==

= 3.1.1 =
* Compatibility: declared "Tested up to" WordPress 7.0. No code changes — the plugin uses only long-stable WordPress Core APIs and was verified compatible with the 7.0 release.

= 3.1.0 =
* Rebranded plugin display name to "Image Hotspots by Pleaseup" for better discoverability on WordPress.org search.
* Updated readme tags and short description for clarity.
* Admin menu label updated to "Image Hotspots" (page title remains in the same location).
* No functional or breaking changes — existing shortcodes, settings, hooks, post types, and stored data remain fully compatible.

= 3.0.3 =
* Build: excluded `.gitkeep` placeholder files from the distribution zip (WordPress.org plugin scanner flags any hidden file).
* readme.txt: bumped "Tested up to" to 6.9.

= 3.0.2 =
* Admin: renamed the admin sidebar menu label and the in-page hero `<h1>` from "WP Hotspots" / "WP Image HotSpots" to "Pleaseup Hotspots" to match the plugin name.
* readme.txt: updated installation and FAQ instructions to refer to the new menu label.
* Translations: POT regenerated for the renamed strings.

= 3.0.1 =
* Renamed plugin to "Pleaseup Hotspots" (slug `pleaseup-hotspots`) for the WordPress.org Plugin Directory.
* Frontend: extracted the gallery carousel JavaScript and dynamic CSS to enqueued assets (no more inline `<script>` or `<style>` blocks in the rendered shortcode HTML). The dynamic per-instance values are now passed as CSS custom properties on the wrapper.
* Admin: the gallery preview now reuses the same enqueued frontend assets so the in-editor preview matches the public render exactly.

= 3.0.0 =
Initial release.

== Upgrade Notice ==

= 3.1.1 =
Compatibility refresh: declared support for WordPress 7.0. No data migration, no breaking changes.

= 3.1.0 =
Rebrand to "Image Hotspots by Pleaseup" + admin menu label refresh. No data migration, all existing shortcodes (`[wphs_image]`, `[wphs_gallery]`) and settings continue to work.

= 3.0.3 =
Distribution hygiene fix: hidden .gitkeep files excluded from the zip; readme tested-up-to bumped to 6.9. No data migration required.

= 3.0.2 =
Admin UI strings catch-up rename ("WP Hotspots" → "Pleaseup Hotspots"). No data migration required.

= 3.0.1 =
Rename + asset cleanup for WordPress.org Plugin Directory submission. No data migration required.

= 3.0.0 =
Initial release.
