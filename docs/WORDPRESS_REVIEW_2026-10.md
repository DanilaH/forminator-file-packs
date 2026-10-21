# WordPress.org review follow-up (0.4.1)

This record describes the implementation and verification targets, not directory approval.

## Assets

`Admin::assets()` enqueues the WordPress admin CSS/JS on the plugin's own screen. `Package::html()` instead generates `index.html` and submission cards **inside the downloaded ZIP**. Those documents are opened offline after extraction, without WordPress or a web server. They intentionally embed static CSS and carry a restrictive Content Security Policy. Enqueueing WordPress assets there would not work. A source comment and HTTP export assertions preserve this distinction; no remote assets or executable scripts are added.

## Requests and permissions

All 13 registered actions use authenticated `wp_ajax_ffp_*` hooks and enter `Admin::endpoint()`. Before request parameters are processed it calls `Adapter::authorize()` (logged-in user plus Forminator's actual entries capability) and requires POST plus `check_ajax_referer('ffp_export', 'nonce', false)`. Task routes repeat authorization/nonces and enforce task owner and site. There are no public AJAX, REST or admin-post routes. The automated review did not identify a concrete unprotected handler; the existing shared gate is retained rather than replaced with a weaker static capability.

The regression suite now tests missing nonce, invalid nonce, GET with a valid nonce and guest access for every action, plus authenticated revoked/excluded permissions with a valid nonce for every action. Existing task ownership, source-preservation and export-content tests remain.

## Translations and textdomain

The manual `load_plugin_textdomain()` call is removed. The directory plugin ZIP no longer bundles PO/MO files or advertises a bundled Domain Path. Russian source remains in `translations/`; the build produces a **separate language pack**, not another installable plugin.

WordPress loads installed standard language packs automatically. Until an approved Russian pack is available from translate.wordpress.org, standalone ZIP installations display English unless the separate pack is installed. This is intentional and must not be described as automatic bundled Russian support.

For a standalone installation, extract the two files from `dist/languages/file-packs-for-forminator-ru_RU.zip` directly into `wp-content/languages/plugins/` (or the site's configured `WP_LANG_DIR/plugins`), then select Russian as the user/site language. Do not upload that ZIP in Plugins → Add New. No files from the plugin ZIP need editing. WordPress may remove the separate catalog on plugin uninstall; reinstall the pack if needed. Publication/import at translate.wordpress.org is a separate human publishing step, not performed by this change.

CI installs the exact main ZIP, then the separate catalog in the standard global language directory. No loader, gettext filter or translation stub is injected into production/test WordPress. Existing real browser and exported-ZIP Russian tests cover this path, including a reinstall. The default English HTTP/browser tests still run. Both archives are published only after all matrix checks pass.

## Verification

Plugin Check must produce **no diagnostics**; the previous discouraged-function warning exception is removed. Native integration CI is the verification gate for this change. Current-commit run results belong in the PR; historical green runs do not prove these changes. Main is not modified and no VPS deployment or WordPress.org resubmission is part of the branch/PR work.

## Resubmission context

After the verified plugin ZIP is uploaded, reply in the original review thread. Keep the reply short; mention the offline-document reason for retained CSS and point to the centralized nonce/capability gate if needed. Do not claim the review was a confirmed exploitable vulnerability or that directory approval is guaranteed.
