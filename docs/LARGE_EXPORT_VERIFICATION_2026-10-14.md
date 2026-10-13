# Large export verification — 2026-10-14

Version 0.4.0. Runtime candidate: `2b7a7c81f6fb53030c2a236d312622cc972a1e3f`. This record distinguishes completed evidence from the required current-main deployment gates.

## Completed native candidate evidence

[Native run 38464280096](https://github.com/DanilaH/forminator-file-packs/actions/runs/38464280096): all three real integration jobs succeeded. Matrix: WordPress 7.1.3/PHP 8.3/Forminator 1.58.0; WordPress 6.5.5/PHP 8.2/Forminator 1.58.0 and 1.57.1. The tested ZIP is gated by this matrix.

The native suite includes actual API-persisted 10000 submissions in the primary job and 1000 in each other job. It verifies exact entry identities and attachment SHA-256 across all independent ZIPs, source form/record/meta/file snapshots, sequence retry/deduplication, normal/fatal/SIGKILL recovery, owner revocation, wrong-owner and wrong-site rejection, changed checked contents, explicit omission consent, per-object bounds, cancellation and expiry. PHP 128M is explicitly asserted; independent source snapshots run in a separate process and cannot raise the export process's limit.

The browser suite verifies all-period selection, restored form/period/task context, paginated review, omission-focused consent, true ready/partial states, POST streaming downloads without a whole-archive Blob, byte-identical repeated downloads, independently read CRC/JSON/attachment content, Russian expiry formatting and 390px layout. The sample large case exports 101 persisted submissions and 101 MiB of incompressible attachment data into two parts. Screenshots are actual WordPress UI with synthetic data, not mockups.

Existing small-export, authorization, path/symlink, upload/repeater, JSON/CSV/HTML, source-integrity, installation/upgrade/uninstall and Plugin Check checks remain enabled. Plugin Check blocks every new error/diagnostic; the one standalone translation-registration warning remains visible.

## Docker and VPS gates

[Candidate Docker run 38464280146](https://github.com/DanilaH/forminator-file-packs/actions/runs/38464280146) exercises two complete deployments. Earlier 0.4.0 candidates already passed the actual unattended-container runner check, with no browser requests and no direct processing calls. Its dedicated check must also pass for the current main candidate.

Native/Docker checks for the exact trusted main commit automatically gate the actual VPS rollout, which runs the complete owned staging test cycle twice. Use [Actions](https://github.com/DanilaH/forminator-file-packs/actions) to inspect those current-main and VPS conclusions. A branch pass or earlier candidate does not establish a successful current-main/VPS rollout.

## Measurements and scope

Read `jobs-results.json` in the exact run's evidence for actual per-pass peak PHP memory, longest pass, part count and busy-loop processing time. Busy-loop CLI time excludes waiting between browser/cron passes and is not a promised end-to-end download duration. The 10000-entry earlier candidate `48893fe` produced 20 ZIPs with actual PHP 128M; its measurement is historical, not the final runtime measurement.

The owned Docker/VPS image runs only exporter cron hooks every ten seconds as www-data in the same container/private storage as WordPress. It pauses during the destructive fixture suite and is then independently checked without the fixture/browser. This does not change other sites' cron, containers, Caddy routes or DNS.

No fixed total submission-count ceiling is claimed for large tasks. Available memory, disk, execution time, per-part/object boundaries and task/result lifetime remain constraints. A whole submission stays within one part; oversized objects produce a clear error. No general hosting, offload, Windows, full multisite, screen-reader or first-time human usability certification is claimed. Source records and attachments remain read-only. WordPress.org publication and payment/Pro work are not part of this implementation.
