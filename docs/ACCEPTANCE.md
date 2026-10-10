# First working export acceptance

Evidence: [first report](TEST_REPORT_2026-10-08.md) and [beta report](BETA_TEST_REPORT_2026-10-08.md). Checked items cover the recorded lab only. Unchecked combined items have remaining work even where individual cases passed.

- [x] Exact tested WordPress/PHP/Forminator/tooling versions recorded.
- [x] ZIP installation into a clean plugin directory; activation and safe rejection after Forminator deactivation.
- [x] Real frontend submissions: single/multi upload, separate fields, AJAX multi, Media Library, empty entry, Unicode and repeated names.
- [x] Adapter mapping documented from source and persisted data; entry IDs constrained to their form.
- [x] Supported local-storage omission handling: missing, unreadable, remote, unsupported and escaped-path cases. Offload integrations are explicitly unsupported.
- [x] Every selected entry has a card; index, CSV and warnings manifest included.
- [x] Attachment bytes match originals; collisions do not overwrite members.
- [x] Installation, actual ZIP upgrade, deactivation, file removal/uninstall and reinstall preserve forms/entries/meta and original upload hashes.
- [x] Guests/subscribers rejected; nonce and capabilities checked separately.
- [x] Cross-form IDs, stale previews, traversal/symlink escapes, arbitrary paths/streams and abandoned jobs after real SIGKILL covered. Shutdown/fatal cleanup and one-hour stale lock recovery verified.
- [x] Direct hostile HTML escaped; CSV formula cases and safe ZIP names tested; active HTML attachments omitted.
- [x] Private storage outside web root, 0700 permissions and cleanup after tested success/failure cases.
- [x] Disk/write/time/memory simulations, actual ZIP-disabled PHP and real memory headroom under pressure reject cleanly. Bootstrap OOM, actual host disk exhaustion and blocked compression remain outside this coverage.
- [x] One resource measurement and conservative limits recorded; not a universal capacity guarantee.
- [x] Static and runtime Plugin Check executed; beta has zero errors and one explained warning for bundled translation registration. Narrow native filesystem/SQL annotations retained.
- [x] Runtime Plugin Check and three documented WordPress/PHP/Forminator combinations completed.
- [x] Focused keyboard/focus, Russian translation and 15 empty/error/retry/partial/narrow UI-state checks completed.
- [x] Six actual repeater upload configurations (single/multiple/AJAX, with/without Media Library) on Forminator 1.58.0; exact ZIP attachment bytes verified.
- [x] Combined 100-entry / 500-file / 100 MiB ceiling under a real 128 MiB PHP limit, actual browser download and rejection beyond each ceiling/text limit.
- [x] Custom user/role permissions, excluded users and capability revocation block all four HTTP endpoints with an existing session/nonce.
- [x] Focused Chromium accessibility-tree names, live regions, heading focus, sampled computed text contrast and 320 px layout checks.
- [ ] Screen-reader audit, offload configurations, actual shared hosting, Windows and concurrent mid-request permission revocation.
- [x] Shipped-file license/dependency audit and automated package metadata checks; see [release readiness](RELEASE_READINESS.md).
- [ ] Final publication branding, confirmed WordPress.org publisher and submission materials.

JSON extension evidence: [0.3.0 report](JSON_TEST_REPORT_2026-10-08.md).

- [x] Stored composite/scalar values, repeated keys, Unicode, numeric strings, false/null and floating zero preserve their JSON representation.
- [x] JSON references match actual ZIP members and CSV counts; omissions after preview update final warnings/status.
- [x] Operational upload metadata and internal/addon sentinels stay out of JSON.
- [x] Encoding errors and short metadata writes abort even with partial consent and clean private files.
- [x] 8 MiB metadata with sixfold escaping passes after incremental fingerprint/field-streaming fixes.

Actual VPS evidence and 0.3.1 revalidation criteria: [release readiness](RELEASE_READINESS.md).
