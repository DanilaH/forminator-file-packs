# JSON beta 0.3.0 verification — 2026-10-08

Synthetic data in a recreated disposable native lab: **WordPress 7.1.3 / PHP 8.3.6 / Forminator 1.58.0**, MariaDB 10.11.14, WP-CLI 2.12.0, Plugin Check 2.1.0 and Playwright Chromium. This report records 0.3.0 checks; the three-combination 0.2.0 matrix remains historical evidence, not a claim that every combination was rerun for JSON.

## Result and contract

Every standard ZIP now contains `data.json` alongside CSV, HTML, warnings and actual attachments. [Schema 1](DATA_FORMAT.md) preserves saved field values/structures and repeated keys, with current field types, site-local dates/timezone and relative ZIP references. Internal/addon metadata and operational source paths/URLs/attachment IDs/fingerprints are excluded. JSON reflects the final package after omissions. No XLSX or import functionality was added.

## Independent validation and regression

- **155 independent Python assertions** across generated ZIPs: UTF-8 without BOM, schema/version/timezone, actual counts, CSV agreement, final warnings/status, safe member references, unique paths, cards, CRC and exclusion of operational metadata/synthetic internal sentinels. The reader uses Python's ZIP/JSON/CSV libraries and a separately defined fixture, not the production JSON builder.
- Structured fixture: saved names, checkbox lists, nested values, repeated keys, Unicode, leading zeroes, formula-like strings, quotes/backslashes/NUL, integer/float/floating zero/false/null/empty collection. Attachment SHA-256 matches the original fixture. Forminator API test inputs are slashed because its entry writer unslashes values; the exporter reads saved data.
- File removed by the test after planning: incomplete JSON omits its reference and updates counts/warnings/status. Invalid UTF-8 aborts the whole package. A test-only namespace hook performs a short JSON-file write; even partial consent cannot bypass the storage error. Private jobs/locks are cleaned, and source metadata/attachment hashes remain intact.
- **43 HTTP + 32 security/integrity + 17 resilience assertions** rerun on the final export/fingerprint implementation; real single/multiple/AJAX/Media Library uploads, authorization, stale preview, escaping, paths, collisions and cleanup pass.
- Six native repeater browser configurations and six persisted-metadata checks rerun. Both repeated keys, exact attachment bytes and actual Media Library IDs verified.
- **11 capacity/boundary + 10 browser/capacity + 17 HTTP permission assertions** rerun; full source snapshot matches across capacity and permission HTTP checks. The combined 100-entry / 500-file / 100 MiB package downloads through the actual browser Blob route. PHP planning/assembly under 128M: 2.276 seconds, 78,970,880 peak bytes, 105,043,616 ZIP bytes. This is a local measurement, not universal hosting capacity.
- **16 lifecycle assertions**: actual WordPress ZIP upgrade 0.2.0 → 0.3.0, installed versions checked, uninstall/file removal and reinstall, source preservation, normal/fatal/SIGKILL cleanup and stale reclaim. The old ZIP was reconstructed from the exact 0.2.0 Git source, including its bundled translation. No development symlink was uninstalled.
- Basic browser export plus **15 Russian UI-state assertions** and separate dependency-deactivation browser checks pass. Focused names/live regions/focus/contrast/320 px checks remain limited automated coverage; no screen-reader audit is claimed.
- PHP lint, JS/Python syntax checks, clean diff and final ZIP member/CRC/exact-source-byte/GPL checks pass. Tests/fixtures are excluded from the installable ZIP.

## Found and fixed

An adversarial eight-field fixture with exactly **8 MiB of NUL text** exhausted 256M while encoding the old whole-plan fingerprint after adding raw JSON values. Fingerprints now hash bounded serialized pieces; JSON streams one field at a time instead of building a whole-row/document buffer. The final fixture produces **50,332,632 JSON bytes** (sixfold expansion), passes independent field SHA-256 checks, and completes under an actual **256M** limit. Whole-process peak: **141,897,728 bytes**; measured assembly: **0.416 seconds**. This is separate from the 128M attachment-capacity case and does not promise all metadata cases under 128M.

## Plugin Check and limits

Static/runtime Plugin Check on the installed final ZIP: **0 errors, 1 warning**, the existing standalone bundled-translation `load_plugin_textdomain()` warning. Narrow private-stream/read-only-SQL annotations remain. No directory approval is claimed.

Real shared hosting, screen readers, offload configurations, Windows and concurrent mid-request permission revocation remain open. JSON schema 1 describes exported selected data, not a Forminator backup or import format. The installable beta is `forminator-file-packs-0.3.0-dev.zip`. Sanitized evidence: [verification/json-0.3.0](verification/json-0.3.0/). Original payloads, generated fixtures, credentials and archive contents are not committed.
