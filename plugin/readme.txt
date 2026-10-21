=== File Packs for Forminator ===
Contributors: danilah
Tags: forminator, submissions, attachments, export
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Requires Plugins: forminator
Stable tag: 0.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export Forminator submissions with their attachments into organized ZIP files, with readable HTML cards, a CSV register and JSON data.

== Description ==

**Keep each submission and its files together.**

File Packs for Forminator lets you download saved form submissions with their locally stored attachments. Each submission gets its own folder and a readable HTML card, so you can review the details and open its files after extracting the ZIP.

Use it to collect enquiry documents, review application uploads or hand a selected batch of submissions to a colleague. The exported files can be viewed without access to your WordPress dashboard.

= Three steps to your export =

1. Choose a Forminator form and select submissions or all submissions in a date range.
2. Check the available files and review any omissions.
3. Download the ZIP, extract it and open index.html in your browser.

= What you get =

* An HTML index linking to each submission.
* A separate folder for each submission, with an HTML card and its available attachments.
* A CSV register for spreadsheet work.
* Structured JSON data for automated processing.
* A warnings file recording any omitted attachments.

Original forms, submissions and uploaded files stay unchanged. The plugin copies available attachment bytes into the export; it does not move or rename the originals.

= Large exports =

Select all submissions in the applied period, or select more than 100 submissions, to create a saved export task. Progress is kept on your site, and large exports are split into independent ZIP files. Each part includes its own index, cards, CSV register and JSON data.

You can return to a large task, download ready parts again within their retention period, or cancel the task and delete its temporary export files. Background processing uses WordPress cron and may depend on site visits. Keeping the export page open also advances the task.

= Missing files are explained =

Files that are missing, unsupported or stored remotely are reported during checking. An incomplete export requires your explicit acceptance. Ready archives with omissions are clearly labelled, and their HTML pages, CSV and JSON also record the warnings.

= Local processing =

No external export service, account, telemetry or cloud connection is required. Temporary export files are stored outside public web directories. Downloads require an authorized WordPress account. Ready large-export archives are retained for 24 hours; quick-export temporary files are removed after the request.

= Requirements and current scope =

* WordPress 6.5 or later and PHP 8.2 or later.
* Forminator installed and active.
* PHP ZIP support and writable private temporary storage outside public web directories.
* Locally stored Forminator attachments. Remote or offloaded files are not fetched.

Single and multiple uploads, AJAX multiple uploads, Media Library uploads and six repeater upload configurations have been tested on Forminator 1.58.0. Executable and active-content file types, including HTML, SVG and scripts, are excluded with warnings.

This is a development release. WordPress.org submission and approval are pending. File Packs for Forminator is an independent project and is not affiliated with or endorsed by WPMU DEV.

Source code, documentation and bug reports: https://github.com/DanilaH/forminator-file-packs

== Installation ==

1. Install and activate Forminator.
2. Upload the File Packs for Forminator ZIP through Plugins > Add New > Upload Plugin, then activate it.
3. Click Open export in the plugin list, or go to Tools > Export Forminator submissions.
4. Choose a form, set an optional period and select submissions. Click Check and download.
5. Review the contents and any warnings. Download the ZIP, or start the large export and download each ready part.
6. Extract each ZIP and open index.html to view the submissions and attachments.

If replacing an old beta installed under the forminator-file-packs folder, deactivate and delete that beta first. Original Forminator forms, submissions and attachments are not exporter-owned data.

If the plugin reports unavailable private storage, ask your hosting administrator to provide a writable directory outside public web directories. The default is the system temporary directory. A private path can be configured with FFP_PRIVATE_TEMP_DIR in wp-config.php. PHP mbstring is not required.

== Frequently Asked Questions ==

= What is this useful for? =

Downloading a selected set of saved submissions with their attachments for offline review or handover. For example, you can collect application documents or enquiry uploads without opening every submission and downloading each file separately.

= Does it change my forms, submissions or uploaded files? =

No. Grouping and filename changes apply only inside the exported archive. Cancelling an export or deleting its archives does not delete the original Forminator data.

= What is inside the ZIP? =

index.html, register.csv, data.json, warnings.json and one folder per submission containing request.html and the included attachments. Keep the extracted folder together so the links work.

= Can I open the exported data in Excel? =

You can open register.csv in Excel or another spreadsheet application. There is no native XLSX export. data.json is intended for automated processing; HTML pages provide the readable view.

= Do I need WordPress to view the downloaded files? =

No. Extract the ZIP and open index.html in a browser. The exported HTML pages do not require a WordPress login or a connection to the original site.

= How many submissions can I export? =

Saved large-export tasks have no fixed total submission-count ceiling. They create independent parts, each bounded by 1000 submissions, 500 attachments, 100 MiB of attachment bytes and 1 MiB of field data. Available disk space, hosting resources and the task lifetime still limit an export.

The quick route accepts up to 100 submissions, 500 attachments, 100 MiB of attachments and 8 MiB of field data per package. A submission that exceeds a large-part boundary may be exported alone through the quick route if it fits those limits. Submissions exceeding the quick-route limits cannot be exported in this version.

= Can I close the page while exporting? =

For saved large tasks, yes: progress is saved on the site. Background progress depends on WordPress cron and hosting configuration; keeping the page open also advances processing. Return to the export page to check progress. Keep the page open during a quick export and its download.

= How long are archives available? =

Ready large-export parts are available for repeated authorized downloads for 24 hours after completion. Unfinished tasks expire 24 hours after creation. Temporary storage may also be cleared by your host. After expiry or loss of temporary files, create a new export. Files already downloaded to your computer are unaffected.

= What happens if an attachment is missing? =

The plugin reports the omission and asks you to explicitly accept an incomplete export. Ready parts with omissions are labelled, and you can inspect the reported reasons before downloading. The archives contain the final warnings. You can fix the source attachment and create a new export instead.

= Does it support cloud or offloaded uploads? =

No. This version exports verified local attachments only. It does not fetch files from remote URLs or cloud storage. Unsupported references are reported as omissions.

= Who can export submissions? =

Access follows Forminator's submissions-page permissions. Saved tasks and their downloads are also restricted to the account that created them. An export may stop if that account loses access.

= Are my submission data sent to another service? =

The plugin does not send submission data to an external export service. Processing happens on your site, and archives are downloaded to your computer. Exported packages may contain personal information; share them only with the intended recipients.

= Which languages are included? =

English is included. Translations use standard WordPress language packs. A Russian catalog is maintained separately in the repository; until a directory language pack is available, install the separate Russian pack into wp-content/languages/plugins. Interface text follows the WordPress user's language. User-entered submission values and form labels are exported as stored; they are not translated.

= Is this a backup or migration tool? =

No. It provides no import or restore operation and does not export a complete Forminator configuration or all internal metadata. It is not a transactionally consistent backup of a changing site.

= How do I report a problem? =

Open an issue at https://github.com/DanilaH/forminator-file-packs/issues with your WordPress, PHP, Forminator and File Packs versions, the error message and steps to reproduce it. Use synthetic examples. Do not upload real submissions, private attachments, credentials or exported archives containing personal data.

== Screenshots ==

1. Choose a form, filter by date and select the submissions you need.
2. Check the available attachments before downloading your ZIP.
3. Download large exports as independent ZIP parts, with saved progress and repeat downloads.
4. Open the extracted index to browse submissions or access CSV and JSON data.
5. View each submission's details and attachments together in its own folder.

== Changelog ==

= 0.4.1 =
* Use standard WordPress language packs instead of bundled catalogs and manual textdomain loading.
* Verify every AJAX action rejects invalid requests; document offline export styling.

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
