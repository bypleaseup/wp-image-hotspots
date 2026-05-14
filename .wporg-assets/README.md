# WordPress.org Plugin Page Assets

This folder holds the images that populate the plugin's **public page**
on https://wordpress.org/plugins/pleaseup-hotspots/.

They are **not** part of the plugin code — WordPress.org reads them
from a separate `/assets/` directory in the SVN repository (sibling
of `/trunk/` and `/tags/`).

## Files

| File | Dimensions | Where it shows up |
|---|---|---|
| `banner-1544x500.png` | 1544×500 | Hero banner at the top of the plugin page |
| `banner-772x250.png` | 772×250 | Retina/mobile version of the hero banner |
| `icon-256x256.png` | 256×256 | Square icon in search results and the plugin list |
| `icon-128x128.png` | 128×128 | Retina version of the icon |
| `screenshot-N.png` | ~1280px wide | Listed under "Screenshots" on the plugin page; N corresponds to the numbered entries in `readme.txt` `== Screenshots ==` section |

The current banner + icon are **placeholders** (brand-coloured navy
background with text + a mint hotspot dot for the icon). Replace
them when the real artwork is ready — the file names must stay
identical or the WP.org page won't pick them up.

Screenshot files are not included yet. They need to be captured from
a real WordPress install with the plugin active, then named
`screenshot-1.png`, `screenshot-2.png`, … to match the numbered list
in `readme.txt`.

## How they reach WordPress.org

These files live in the AIDHA monorepo as the source-of-truth. To
publish or update them on WP.org, they need to land in the SVN
repository's `/assets/` directory:

```bash
# After a regular bin/svn-release.sh run that staged trunk + tags,
# copy the assets in too:
rsync -a /path/to/aidha/.wporg-assets/ ~/.wphs-svn/pleaseup-hotspots/assets/
cd ~/.wphs-svn/pleaseup-hotspots
svn add --force assets
svn commit -m "Update plugin page assets" --username YOUR_WP_ORG_USERNAME
```

`bin/svn-release.sh` does **not** touch `/assets/` on every release —
asset updates are intentionally a separate, manual step (you usually
want to update them less often than the plugin code itself).
