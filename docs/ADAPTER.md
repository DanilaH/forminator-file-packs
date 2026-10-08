# Forminator integration, verified against 1.58.0

The adapter was built after reading the official distribution and inspecting metadata produced by actual frontend submissions. No vendor source or submission fixtures are shipped.

- Forms: `Forminator_API::get_form()` / `Forminator_Form_Model`; list uses the `forminator_forms` post type.
- Entries: `Forminator_Database_Tables::get_table_name(FORM_ENTRY)` for a bounded read-only list, and `Forminator_API::get_entry()` for records. Only active, nonspam `custom-forms` entries are eligible.
- Ownership: upstream `get_entry(form_id, entry_id)` does not itself enforce the form relationship. This adapter checks returned form ID, entry ID, type, status and spam flag independently.
- Authorization: all server data operations follow `forminator_get_permission('forminator-entries')` and require a logged-in account. POST nonces are additional checks.
- Uploads: only known upload fields and persisted `file.file_path`, `file.file_url`, `file.attachment_id` values, including parallel arrays for multiple uploads. AJAX upload tokens are resolved by Forminator before saving the entry; the exporter reads saved metadata, not the upload token.
- Local provenance: canonical readable file below WordPress uploads, also below that form's managed upload root, or exactly matching a verified Media Library attachment. Reject stream wrappers, external hosts, path escapes, symlinks escaping storage and unsafe extensions. Never fetch URLs or guess a path from a filename.
- Dates: site-local timestamps, start inclusive, end exclusive at the following midnight. This avoids upstream's 23:59:00 end-date cutoff.
- Internal/addon metadata is omitted. Scalar values are readable text; composite values are JSON text. A deleted/unknown upload-field definition is warned about rather than guessed.

## Package and limits

`Request-ID/request.html` and unique field-prefixed attachment names; root `index.html`, UTF-8 BOM `register.csv` and `warnings.json`. CSV has entry ID, creation time, card path, attachment count, warnings and the union of selected field columns. Selected-entry order is preserved.

Preview contains counts, member names and warnings, without source paths or URLs. Download re-reads sources and compares a fingerprint of field metadata and file stat information; it is not a full byte hash of every source during preview. A stale preview is rejected. The builder makes private local snapshots and streams copies in 1 MiB chunks; allowed attachment bytes are preserved.

Per export: 100 entries, 500 files, 100 MiB attachments, 1 MiB per textual field, 8 MiB total field text. One concurrent export per user. Private job permissions are 0700 on the tested Linux host; jobs and archives are removed after requests, and abandoned jobs older than one hour are cleaned opportunistically on a later export. There is no scheduler or persistent download link.

Native filesystem operations are needed for atomic local locks, restrictive permissions and stream copies; narrowly documented Plugin Check exceptions reflect that design. Private storage defaults to system temporary storage. A host may define `FFP_PRIVATE_TEMP_DIR` as an existing private writable directory; public web roots are rejected.

Repeater uploads, offload plugins and other Forminator versions remain unverified. Unknown storage is reported as unsupported. The authorization model is tested for admin, subscriber and guest; custom Forminator permission configurations need additional coverage.
