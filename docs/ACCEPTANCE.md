# First working export acceptance

All checks below are pending. Repository initialization is not a completed export milestone.

- [ ] Exact tested WordPress/PHP/Forminator/tooling versions recorded.
- [ ] Clean ZIP installation and safe dependency behavior, including Forminator deactivation.
- [ ] Real frontend submissions: single/multi upload, separate upload fields, AJAX, Media Library modes, empty entry, Unicode and repeated names.
- [ ] Adapter mapping documented from current source and persisted data; entry IDs constrained to their form.
- [ ] Preview matches the archive; missing, unreadable, remote and unsupported uploads produce explicit warnings.
- [ ] Every selected entry has a readable card; package includes index and CSV register.
- [ ] Attachment byte hashes match originals; collision handling does not overwrite files.
- [ ] Form records, submission records, original filenames, paths and bytes unchanged after export and uninstall.
- [ ] Unauthorized users and guests cannot preview, build or download; nonce checks do not replace capabilities.
- [ ] Manipulated IDs, expired exports, traversal, symlink escapes and arbitrary local paths cannot disclose files.
- [ ] HTML escaped, CSV formulas neutralized, ZIP member names safe; attachment links do not embed active documents.
- [ ] Temporary archives inaccessible through HTTP; cleanup verified after success and failures.
- [ ] Disk, ZIP support, time and memory failures handled honestly; partial exports never reported as complete.
- [ ] Resource measurements and supported limits recorded.
- [ ] Plugin Check run with a retained report; release blockers resolved.
- [ ] License/source/dependency audit complete; final name and known limitations documented.
