# Image Hotspots Tool

Add interactive drag & drop hotspots to images stored in your WordPress
Media Library. Each hotspot can display rich HTML tooltips, perfect for
product highlights, infographics, and wayfinding maps.

This repository is the **public release mirror** of the plugin
distributed on [wordpress.org/plugins/image-hotspots-tool](https://wordpress.org/plugins/image-hotspots-tool/).
Internal development happens in a separate, AIDHA-driven monorepo;
each public release is mirrored here as a single commit, tagged with
the corresponding version.

## Install

The recommended installation path is from the WordPress admin:

1. **Plugins → Add New**
2. Search for *Image Hotspots Tool*
3. **Install Now** → **Activate**

To install from a release zip:

1. Download `image-hotspots-tool-X.Y.Z.zip` from the
   [Releases page](https://github.com/bypleaseup/wp-image-hotspots/releases).
2. **Plugins → Add New → Upload Plugin** → upload the zip → **Install Now**.
3. **Activate**.

## Usage

```text
[wphs_image id="ATTACHMENT_ID"]
[wphs_gallery id="GALLERY_ID" nav="dots+arrows" cols_desktop="3" cols_mobile="1"]
```

See the [WordPress.org plugin page](https://wordpress.org/plugins/image-hotspots-tool/)
for the full documentation, FAQ, screenshots, and changelog.

## Compatibility

- WordPress: ≥ 6.0 (tested up to 6.8)
- PHP: ≥ 7.4
- jQuery (bundled with WordPress)

## Reporting issues

[GitHub Issues](https://github.com/bypleaseup/wp-image-hotspots/issues)
or the official [WordPress.org support forum](https://wordpress.org/support/plugin/image-hotspots-tool/).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
