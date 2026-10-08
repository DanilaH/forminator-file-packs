# First development build verification — 2026-10-08

Build: File Packs for Forminator 0.1.0. Synthetic data only. One native Linux installation; no production site or customer data used.

## Recorded versions

| Component | Tested version |
|---|---|
| WordPress | 7.1.3 |
| Forminator | 1.58.0, official distribution |
| PHP | 8.3.6, ZIP enabled |
| MariaDB | 10.11.14 |
| WP-CLI | 2.12.0 |
| Plugin Check | 2.1.0 |
| Browser | Playwright Chromium headless, desktop 1360 px and narrow 390 px |

WordPress 6.5 / PHP 8.2 are provisional header minimums, not versions validated by these tests. The Docker/wp-env launch configuration is prepared and pinned but was not executed.

## Completed checks

- PHP syntax on all five plugin PHP files and JavaScript syntax.
- Install built ZIP into a clean plugin directory, activate, deactivate Forminator and verify controlled dependency rejection, then reactivate Forminator.
- **43 HTTP integration assertions:** real frontend native single/multi and AJAX multi uploads, Media Library mode, empty entries, Unicode/repeated filenames; preview, protected download, ZIP CRC/member structure, exact attachment bytes, cards, CSV; stale preview, cross-form entry, bad dates/nonces and guests.
- **31 security/integrity assertions:** unchanged form/entry/meta records and attachment hashes, subscriber/guest rejection, empty/over-limit selection, cross-form ownership, arbitrary path, symlink escape, stream/external URL, unrelated form attachment, active HTML exclusion, missing file and partial manifests, five CSV formula cases, name collisions/normalization, disappearance after preview, private directory/locking/cleanup and resource probe.
- Browser main route: login, form list, page selection, clearing selection, selecting current fixture entries, preview, ZIP download, desktop/narrow layout and no JavaScript exceptions. Screenshots use synthetic records: [desktop](screenshots/admin-desktop.webp), [390 px](screenshots/admin-mobile.webp).
- Static Plugin Check against the installed ZIP: zero remaining diagnostics. Native filesystem and bounded read-only SQL exceptions are narrowly annotated with design reasons. This does not constitute WordPress.org approval or a completed runtime/security audit.

## Resource probe

One API-created synthetic 20 MiB random attachment (separate from frontend upload compatibility tests): ZIP 20,976,773 bytes, 0.406 seconds for planning/building, total process peak memory 74,776,576 bytes (about 71.3 MiB, including WordPress/Forminator). This is a local observation, not a hosting performance promise.

Hard ceilings: 100 entries, 500 attachments and 100 MiB attachment bytes; per-field text 1 MiB and total text 8 MiB. The full ceilings were not stress-tested. Snapshot copies need roughly twice attachment bytes plus overhead in private temporary storage. Downloads currently use a browser Blob; browser memory for ceiling-size packages is unmeasured.

## Remaining before release

Other versions/hosts and PHP 8.2; repeaters/custom permissions/offload configurations; low disk, ZIP absence, time/memory failure and interrupted-request cleanup; direct hostile HTML fixtures; full keyboard/screen-reader/state audit; translations; uninstall lifecycle; runtime Plugin Check; final name/license audit and directory materials. No Pro/payment work is included.

Raw test outputs are retained in ignored local `artifacts/`; sanitized assertion lists and the static check report are checked into `docs/verification/`. See [acceptance](ACCEPTANCE.md) and [roadmap](../ROADMAP.md).
