# First working export acceptance

Evidence: [first report](TEST_REPORT_2026-10-08.md) and [beta report](BETA_TEST_REPORT_2026-10-08.md). Checked items cover the recorded lab only. Unchecked combined items have remaining work even where individual cases passed.

- [x] Exact tested WordPress/PHP/Forminator/tooling versions recorded.
- [x] ZIP installation into a clean plugin directory; activation and safe rejection after Forminator deactivation.
- [x] Real frontend submissions: single/multi upload, separate fields, AJAX multi, Media Library, empty entry, Unicode and repeated names.
- [x] Adapter mapping documented from source and persisted data; entry IDs constrained to their form.
- [ ] All missing/unreadable/offload configurations checked. Missing, remote, unsupported and escaped-path cases already covered.
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
- [ ] Screen-reader audit, additional repeater modes/offload configurations, actual shared hosting and ceiling-size stress tests.
- [ ] License/dependency audit, final name and WordPress.org submission materials finalized.
