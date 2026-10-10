=== File Packs for Forminator ===
Contributors: danilah
Tags: forminator, submissions, attachments, export
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Requires Plugins: forminator
Stable tag: 0.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Preview and export selected Forminator submissions and local attachments into a ZIP with readable cards, CSV and structured JSON.

== Description ==

Beta build for testing, not a directory-approved production release.

Choose a form and completed submissions, review the actual files and warnings, and download a package containing an HTML index, CSV register, JSON data, submission cards, attachments and a warnings manifest.

Original forms, submissions and attachment files stay unchanged. Temporary archives are stored outside public web directories and removed after the request. Incomplete packages require explicit acceptance.

This version supports local files, native single/multi uploads, AJAX multi uploads and Media Library uploads as tested on Forminator 1.58.0. Offloaded files and active HTML/script attachments are omitted with warnings. Six repeater upload configurations are verified on Forminator 1.58.0.

Safety limits per synchronous package: 100 submissions, 500 attachments, 100 MiB of attachment data and 8 MiB of field data. These are engineering limits, not paid unlocks. Hosting time and disk limits may require smaller batches.

Independent project; not affiliated with WPMU DEV.

== Installation ==

1. Install and activate Forminator.
2. If replacing an earlier forminator-file-packs beta, deactivate and delete that plugin first. This exporter stores no forms, submissions or settings of its own.
3. Upload this plugin ZIP through Plugins > Add New and activate it.
4. Open Tools > File Packs, choose a form, select submissions and preview the package.
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

= 0.3.1 =
* Directory-compatible technical slug and matching translation domain.
* Translation setup runs on init for standalone ZIP installations.
* Automated package metadata, dependency and license checks.

= 0.3.1 =
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
