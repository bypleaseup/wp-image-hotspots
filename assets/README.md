# Assets

This directory holds the source CSS/JS used by the plugin and the built
output that ships in the WordPress.org `.zip`.

## Layout

```
assets/
├── src/        ← human-edited sources (versioned)
│   ├── css/
│   │   ├── admin.scss        ← admin editor + galleries page
│   │   └── frontend.scss     ← frontend hotspots + gallery slider
│   └── js/
│       ├── admin.js          ← admin editor (jQuery + jQuery UI)
│       ├── admin-gallery.js  ← admin galleries page
│       └── frontend.js       ← frontend behavior
└── dist/       ← build output (versioned per architecture rule 7)
    ├── css/{admin,frontend}.min.css
    └── js/{admin,admin-gallery,frontend}.min.js
```

## Phase 3 status — initial extraction

The contents under `src/` are a **verbatim extraction** of the inline
`heredoc` blocks from the v2.5.80 monolith
(`/wp-image-hotspots/wp-image-hotspots.php`):

| Source file | Comes from | Original lines |
| --- | --- | --- |
| `src/css/admin.scss`        | `admin_css()`              | 779 – 1017 |
| `src/css/frontend.scss`     | `frontend_assets()` CSS    | 2735 – 2761 |
| `src/js/admin.js`           | `ajax_admin_js()`          | 1036 – 2371 |
| `src/js/admin-gallery.js`   | `gallery_admin_js()`       | 3219 – 3563 |
| `src/js/frontend.js`        | `frontend_assets()` JS     | 2771 – 3079 |

Files in `dist/` are **byte-identical copies** of `src/` for now — they
exist so the plugin can be installed without a build step. Real
minification (`sass --style=compressed`, `terser -c -m`) is configured
in `package.json` and will be wired into the release pipeline in
Phase 10 (CI/CD).

## Build commands (developer machine)

```bash
npm install
npm run build      # writes minified output to dist/
npm run watch:css  # iterate on SCSS
```

## What's intentionally NOT done in Phase 3

- Refactor of CSS into structured SCSS partials (variables, mixins).
- Refactor of admin JS into ES modules.
- Removal of `wp_ajax_wphs_admin_js` endpoint in favor of a registered
  script — this happens in Phase 5 (admin enqueue) once the new
  `Admin\Assets` class is introduced.

The Phase 3 goal is **only** to move the source out of PHP heredocs into
real files so future phases can refactor them safely.
