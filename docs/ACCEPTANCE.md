# First working export acceptance

Evidence: [2026-10-08 report](TEST_REPORT_2026-10-08.md). Checked items cover the recorded lab only. Unchecked combined items have remaining work even where individual cases passed.

- [x] Exact tested WordPress/PHP/Forminator/tooling versions recorded.
- [x] ZIP installation into a clean plugin directory; activation and safe rejection after Forminator deactivation.
- [x] Real frontend submissions: single/multi upload, separate fields, AJAX multi, Media Library, empty entry, Unicode and repeated names.
- [x] Adapter mapping documented from source and persisted data; entry IDs constrained to their form.
- [ ] All missing/unreadable/offload configurations checked. Missing, remote, unsupported and escaped-path cases already covered.
- [x] Every selected entry has a card; index, CSV and warnings manifest included.
- [x] Attachment bytes match originals; collisions do not overwrite members.
- [ ] Complete lifecycle including uninstall checked. Export already preserves form/entry/meta records and original hashes; there is no source-deleting uninstall hook.
- [x] Guests/subscribers rejected; nonce and capabilities checked separately.
- [ ] Full manipulation/recovery coverage. Cross-form IDs, stale previews, path traversal, symlink escapes, arbitrary paths and stream wrappers covered; abandoned/interrupted requests need further tests.
- [ ] Complete hostile field-content suite. HTML escaping implemented and sanitized real submissions checked; CSV formula cases and safe ZIP names tested. Add direct hostile HTML fixtures.
- [x] Private storage outside web root, 0700 permissions and cleanup after tested success/failure cases.
- [ ] Low disk, missing ZIP extension, timeout and out-of-memory simulations completed. Partial packages already require consent and record omissions.
- [x] One resource measurement and conservative limits recorded; not a universal capacity guarantee.
- [x] Static Plugin Check executed with retained zero-diagnostic report and narrowly documented exceptions.
- [ ] Runtime Plugin Check and wider compatibility matrix completed.
- [ ] Keyboard/screen-reader audit, all empty/error/retry UI states and translations checked.
- [ ] License/dependency audit, final name and WordPress.org submission materials finalized.
