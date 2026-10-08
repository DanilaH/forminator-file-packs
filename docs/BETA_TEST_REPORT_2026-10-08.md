# Beta 0.2.0 verification — 2026-10-08

Synthetic data, disposable native Linux installations. This expands the [first build report](TEST_REPORT_2026-10-08.md); it does not establish compatibility with every host or intermediate release.

## Changes

- Bundled Russian PO/MO catalog for UI, errors, warnings and HTML package content. Standard WordPress/user locale selection; English remains available.
- Narrow-screen dates stay visible. Date edits clear selection and disable stale rows until applying the period. Failed builds invalidate preview; retry requires rechecking sources.
- Disk/write/ZIP failures abort even when an incomplete package was accepted. Missing attachments may still be explicitly omitted; infrastructure failures cannot masquerade as a successful partial package.
- Conservative memory headroom checks, a wall-time budget capped at 45 seconds (or below PHP execution limit), a 1 MiB cleanup reserve and safer ZIP shutdown cleanup. Native checked warnings do not expose filesystem paths through responses.
- Checked CSV writes and fixed HTML language tags to use the actual request locale.
- Test cleanup uses its own copied collision/repeater attachments; deleting an upstream entry can delete its referenced files. A regression assertion verifies originals after negative-fixture cleanup as well as after export.

## Compatibility

| WordPress | PHP | Forminator | Coverage |
|---|---|---|---|
| 7.1.3 | 8.3.6 | 1.58.0 | Real uploads, protected exports, failures, lifecycle, desktop/mobile UI, Russian states, six native repeater upload configurations, combined capacity ceiling |
| 6.5.5 | 8.2.32 | 1.58.0 | 43 HTTP assertions + 32 security/integrity assertions with real uploads |
| 6.5.5 | 8.2.32 | 1.57.1 | Same 75 assertions; frontend ID lookup accounts for upstream response differences |

MariaDB 10.11.14; WP-CLI 2.12.0; Plugin Check 2.1.0; Playwright Chromium. Header minimum WordPress 6.5 covers the branch tested at 6.5.5, not a direct test of 6.5.0. PHP 8.2 now has an actual test at 8.2.32.

## Completed checks

- 43 HTTP and 32 security/integrity assertions on each of three recorded combinations (225 assertions across the matrix).
- 17 resilience assertions: simulated disk exhaustion, memory pressure, elapsed time and snapshot-write failure; all abort with no archive/lock leftovers, including when partial export was accepted. Also stale-job cleanup, specific-user permissions and revoked capability, direct hostile HTML and repeated-field API mapping.
- Real native repeater UI: add a row, upload same-named files across rows, save through Forminator, preview and download. The follow-up below expands this to six configurations.
- Actual PHP ZIP-disabled process returns a controlled dependency error. Actual 80 MiB memory limit under synthetic pressure rejects before building; WP-CLI's unlimited-memory default was explicitly reset for this check. Neither result is a guarantee against every out-of-memory failure during upstream WordPress bootstrap.
- Normal process exit and fatal PHP error invoke cleanup. Real SIGKILL leaves a private abandoned job; after its job/lock are older than one hour, the next export reclaims both. No scheduled cleanup is implemented.
- 16 lifecycle assertions: source snapshots survive export termination, ZIP installation of 0.1.0 and upgrade to 0.2.0 (installed versions checked), actual uninstall/file removal and reinstall. The snapshot covers Forminator forms/meta, entry/meta tables and all original upload-file hashes.
- 15 Russian browser-state assertions: no matching forms, empty selection, keyboard selection/preview focus, invalidation after date change, empty period, network abort and retry, 390 px overflow/date visibility, required partial consent, downloaded incomplete result and no JavaScript exceptions. A separate dependency-deactivation browser check confirms the explanation and absence of export controls. Russian warnings also appear in the real downloaded ZIP.

## Follow-up: remaining native lab checks

Production plugin code and the installable 0.2.0 ZIP are unchanged. New regression tests and evidence extend coverage on **WordPress 7.1.3 / PHP 8.3.6 / Forminator 1.58.0**; these additional checks were not repeated across the earlier version matrix.

- Six real repeater configurations: single, multiple and AJAX multiple uploads, each with/without Media Library. Two rows contain two or four files per submission. Browser uploads, saved entries, preview and protected ZIP download pass; unique member names, CRC and all attachment bytes are checked. Persisted metadata is inspected separately: both repeated upload keys exist, and every Media Library attachment ID resolves to its exact saved local file.
- 11 capacity/boundary assertions: exactly 100 submissions / 500 attachments / 104,857,600 source bytes; all 500 attachment SHA-256 hashes match, member paths are unique and sources remain unchanged. More than 100 entries, 500 files, 100 MiB, 1 MiB per text field or 8 MiB combined text is rejected. No private archive/lock remains. Capacity fixtures are API-created, not frontend upload-size claims.
- The full plan/archive assembly passes with PHP explicitly limited to **128M**, overriding WP-CLI's unlimited default. Measured whole-process peak through assembly: **78,970,880 bytes (75.3 MiB)**; planning/assembly **2.147 seconds**; resulting ZIP **105,038,794 bytes**. These timings describe this native lab, not typical hosting. Separate boundary-data setup restores the original CLI memory setting.
- 10 browser/capacity assertions: select all four pages, correct preview, download through the shipped Blob route, 100 cards / 500 byte-identical attachments / valid CRC, no JavaScript errors. Measured click-to-download/result: **2.666 seconds**, under the lab HTTP PHP configuration of 256M. Browser process/Blob memory is not measured.
- Focused accessibility checks within that browser run: **138 named exposed controls/summaries**, live regions for selection/result, focusable preview heading, **190 computed text-contrast samples** on solid backgrounds and no plugin overflow at **320 px** with the maximum selection. These are targeted checks of the complete-package state, not a full WCAG or assistive-technology audit.
- 17 real HTTP permission assertions: specific permitted user and role can list forms/entries, preview and download. Revoked capability and excluded user are rejected on **all four endpoints using the same existing authenticated session and nonce**. Restoring permission permits retry. The original permissions option is restored; the synthetic user and test role are deleted. Role permission is tested through an inherited role capability, with no direct user grant.
- A full source snapshot before/after capacity-browser and permission-HTTP checks matches: Forminator forms/meta, entry/meta tables and all upload-file hashes.

Reproduce with `tests/extended.py`; setup is in [DEVELOPMENT.md](DEVELOPMENT.md). Sanitized outputs are in [verification/beta/extended](verification/beta/extended/). Generated source fixtures, ZIPs and credentials are excluded from Git.

## Plugin Check

Static and runtime checks were executed with Plugin Check 2.1.0. The runtime CLI setup was explicitly enabled through its `cli.php`. Zero errors remain, with one documented warning: `load_plugin_textdomain()` is discouraged for directory-hosted plugins. This standalone beta uses it to register the bundled Russian catalog; WordPress.org-hosted translation routing will be revisited before submission. Native local stream/locking operations and prepared read-only SQL have narrow annotations, as in the first build. These checks are not directory approval.

Screenshots: [Russian desktop](screenshots/admin-ru-desktop.webp), [Russian 390 px](screenshots/admin-ru-mobile.webp).

## Limits and remaining work

Hard limits remain 100 entries, 500 files, 100 MiB attachments and 8 MiB text. Measured probes now cover the combined ceiling on the native lab, as detailed below. Download uses a browser Blob. ZIP compression/IO may be blocked by the host outside cooperative time checks; graceful completion after every process kill cannot be guaranteed.

No real shared-hosting deployment, Windows filesystem test, screen-reader audit or offload-plugin matrix has been completed. Keyboard checks are focused functional checks, not accessibility certification. Concurrent permission revocation during a long running request, different repeater layouts/nesting and repeaters on older Forminator versions remain unverified.

Free release preparation, final name/license review and WordPress.org submission remain separate work. No Pro/payment functionality was added. Verification outputs live in `docs/verification/beta/`; artifacts and generated packages are excluded from Git.
