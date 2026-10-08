# JSON export contract — schema 1

Every export ZIP includes UTF-8 `data.json` without BOM, alongside CSV, HTML and permitted original attachment bytes. There is no format switch or reverse import. XLSX is deferred.

| Property | Meaning |
|---|---|
| `schema_version` | Integer 1; increment for a breaking contract change. Readers should tolerate new properties. |
| `plugin_version` | Exporter version, independent of the schema version. |
| `generated_at` | UTC ISO 8601 generation time. |
| `timezone` | Site time zone name or UTC offset, applying to submission dates. |
| `form` | Source form `id` and `title`. |
| `status` | Stable `complete` or `incomplete`, based on final warnings. |
| `submission_count`, `attachment_count`, `attachment_bytes` | Actual included totals; attachment bytes exclude HTML/CSV/JSON overhead. |
| `warnings` | Final entry/field/reason warnings, consistent with `warnings.json`. Reasons are localized. |
| `submissions` | List in numeric entry-ID order. |

Each submission contains `id`, site-local SQL `created_at`, relative HTML `card`, `fields`, `attachments`, and `warnings`.

`fields` is a list of `{key, label, type, value}` for exported non-upload fields. Keys keep Forminator's repeated-field suffixes. Type is the current form field type, or null when unavailable. Values preserve the stored structure and JSON scalar types: strings remain strings (including leading zeroes and formula-like strings), arrays/objects remain structured, booleans/numbers/null are retained. An empty PHP collection exports as `[]`. Text flattening and CSV formula protection continue to apply only to CSV/HTML representations. This is an export of the selected submission data, not a backup of Forminator's internal/addon metadata or form configuration.

`attachments` is a list of `{field, label, name, path, bytes}`. `field` is the saved upload key, `name` the unique ZIP member basename, and `path` a relative path such as `Request-42/upload-1--document.txt`. These refer only to files actually included. Operational source paths/URLs, attachment database IDs, fingerprints and filesystem timestamps are not exported. Empty upload fields produce no attachment; missing/unsupported uploads produce warnings.

JSON is written after source revalidation, so a file omitted after preview has no dangling JSON reference and changes the final status/counts/warnings. Invalid JSON encoding or a checked short metadata write aborts the entire ZIP even with partial consent. Encoding is streamed one field at a time into private temporary storage, with conservative memory/time checks. The exporter never evaluates JSON values; downstream tools should treat them as data.

For non-upload field values, JSON preserves user data rather than escaping it as HTML or altering it for spreadsheet formula protection. HTML cards still escape all text. No absolute operational file references are serialized.
