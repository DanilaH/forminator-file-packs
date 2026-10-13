=== File Packs for Forminator ===
Contributors: danilah
Tags: forminator, submissions, attachments, export
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Requires Plugins: forminator
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Preview and export selected Forminator submissions and local attachments into a ZIP with readable cards, CSV and structured JSON.

== Description ==

Beta build for testing, not a directory-approved production release.

Choose a form and completed submissions, review the actual files and warnings, and download a package containing an HTML index, CSV register, JSON data, submission cards, attachments and a warnings manifest.

Original forms, submissions and attachment files stay unchanged. Archives are stored outside public web directories. Quick-export temporary files are removed after the request; large-task results expire after 24 hours. Incomplete packages require explicit acceptance.

This version supports local files, native single/multi uploads, AJAX multi uploads and Media Library uploads as tested on Forminator 1.58.0. Offloaded files and active HTML/script attachments are omitted with warnings. Six repeater upload configurations are verified on Forminator 1.58.0.

Safety limits per synchronous package: 100 submissions, 500 attachments, 100 MiB of attachment data and 8 MiB of field data. These are engineering limits, not paid unlocks. Hosting time and disk limits may require smaller batches.

Large selections are processed as saved tasks without a fixed total count ceiling. Parts are limited to 1000 submissions, 500 attachments, 16 MiB of attachments and 1 MiB of field data. A single oversized submission can be exported separately through a smaller quick selection. Ready parts expire after 24 hours. WP-Cron background processing depends on site activity; the open export page also advances progress. No scheduled recurring exports or remote storage support is added.

Independent project; not affiliated with WPMU DEV.

== Installation ==

1. Install and activate Forminator.
2. If replacing an earlier forminator-file-packs beta, deactivate and delete that plugin first. Original Forminator forms and submissions are not exporter-owned data.
3. Upload this plugin ZIP through Plugins > Add New and activate it.
4. Use Open export in the plugin list, or open Tools > Export Forminator submissions. Choose a form, select submissions and preview the package.
5. Review any warnings, then build and download the ZIP.

PHP ZIP support and writable private temporary storage are required. By default the plugin uses the system temporary directory. If that is inside a public directory, configure FFP_PRIVATE_TEMP_DIR in wp-config.php to a writable private directory outside the web root. PHP mbstring is not required.

Access follows Forminator's submissions-page permission. There is no telemetry, cloud storage, payment connection or external service required for export.

== Frequently Asked Questions ==

= Does the plugin move or rename my original uploads? =

No. Naming and grouping apply only inside the exported package.

= Are files missing from an incomplete package listed? =

Yes. Review warnings before download. The ZIP index, individual cards, CSV register, data.json and warnings.json also record omissions.

= Does this migrate forms or submissions? =

No. It exports working packages and provides no import or restore operation.

Source code and build tools: https://github.com/DanilaH/forminator-file-packs

== Changelog ==

= 0.4.0 =
* Saved large-export tasks, explicit all-period selection, recoverable progress, bounded independent ZIP parts, authorized repeated downloads and task cancellation/expiry.
* No fixed total submission-count ceiling for large tasks. Hosting and per-part resource constraints remain.

= 0.3.5 =
* Simplified selection, compact confirmation and clearer download instructions.
* Optional help and dates are collapsed; environment problems stay visible.

= 0.3.4 =
* Clearer offline index, selection limits, omission guidance and download instructions.
* Optional remembered quick start, automatic single-form choice and leave-page warning during ZIP downloads.

= 0.3.3 =
* Export readiness checks, descriptive dated ZIP names and site-timezone quick periods.
* Session-aware recovery, bounded browser waiting and an explicit stop-waiting action.
* Selection review refreshes with the list; changing selection clears outdated recovery actions.

= 0.3.2 =
* Quick-start instructions, plugin-list export link and selected-submission review.
* Visible operation notifications and direct retry actions.
* Date edits preserve selection until the period is applied.
* Clearer empty states and instructions for opening the exported ZIP.

= 0.3.1 =
* Directory-compatible technical slug and matching translation domain.
* Translation setup runs on init for standalone ZIP installations.
* Automated package metadata, dependency and license checks.

= 0.3.0 =
* Structured data.json preserves stored field values and references actual included attachments.
* Streaming JSON and incremental preview fingerprints avoid large whole-plan allocations.
* Independent JSON/CSV/ZIP validation, escaping stress, write/encoding failures and regression checks.

= 0.2.0 =
* Russian interface and package translations; date visibility on narrow screens.
* Period changes invalidate old selections; clearer retry flow.
* Disk/write failures abort partial exports; memory/time guards and hardened cleanup.
* Expanded compatibility, failure, lifecycle and browser verification.

= 0.1.0 =
* First development build with selection, date filtering, preview, protected local ZIP export and explicit omission warnings.
