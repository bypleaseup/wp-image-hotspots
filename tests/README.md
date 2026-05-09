# Tests

## Layout

```
tests/
├── bootstrap.php           ← Composer autoload + minimal WP class stubs
├── bootstrap-phpstan.php   ← stubs for static analysis only
├── Unit/
│   ├── Helpers/{Sanitizer,OembedResolver}Test.php
│   ├── Core/{Capabilities,Settings,HotspotRepository,
│   │         TooltipRepository,GalleryRepository}Test.php
│   └── Ajax/AjaxBaseTest.php
├── Integration/            ← scaffold only — populated alongside Phase 10 CI
└── Fixtures/               ← reserved
```

## Phase 9 status — coverage of pure logic

The unit suite covers every class that can be exercised without a
running WordPress instance:

| Target | Coverage focus |
| --- | --- |
| `Helpers\Sanitizer` | hex_color, decode_unicode_escapes, clamp_int/float, allowed_iframe_kses (with filter), kses_tooltip_html |
| `Helpers\OembedResolver` | default providers, no-match passthrough, YouTube wrap, Vimeo wrap, filter override |
| `Core\Capabilities` | all four helper methods + `attachment_id <= 0` short-circuit |
| `Core\Settings` | defaults, get/save options, per-image override merge, dot_radius clamping, hex validation |
| `Core\HotspotRepository` | get/get_hydrated, save with orphan cleanup, `wphs_after_save_hotspots` action, invalid-id skip |
| `Core\TooltipRepository` | get_html (with type guard), upsert insert/update, delete (with type guard) |
| `Core\GalleryRepository` | get with clamping, save insert/update/error path, delete with type guard |
| `Ajax\AjaxBase` | nonce verification (pass + fail), capability gate, `post_int`/`post_string` helpers |

**Out of scope for Phase 9 (deferred to Phase 10/integration):**
- AJAX endpoint integration (full request → response cycle for
  AjaxHotspots / AjaxSettings / AjaxGallery / AjaxOembed)
- ShortcodeImage / ShortcodeGallery rendering output
- Admin pages (rely on `wp.media`, `wp_enqueue_editor`)
- Activator / Deactivator (require `register_*_hook` semantics)

## Running

```bash
composer install
composer test            # phpunit
composer test:coverage   # phpunit + HTML report under coverage/
composer lint            # phpcs (WordPress-Extra + Docs)
composer analyse         # phpstan level 5
composer qa              # everything
```

The unit suite uses **Brain Monkey** to mock WordPress global
functions; `tests/bootstrap.php` defines minimal `WP_Post` and
`WP_Query` stubs so the production classes can be loaded without a
live WordPress install.
