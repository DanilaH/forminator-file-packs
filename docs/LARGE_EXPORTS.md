# Saved large exports

Version 0.4.0 implementation; exact-commit verification is required before distribution.

The total number of selected submissions has no fixed product ceiling. Quick selections up to 100 entries use the existing synchronous route and its established resource limits. Explicit all-period selection and larger manual selections use Jobs with the same source adapter, field/file guards, Planner and Package. This preserves a simple fast path without duplicating ZIP/format/security implementations.

Each large part is independently readable, with its own index, CSV, JSON schema 1, warnings and Request-ID folders. Parts are not ZIP volumes and are not merged into an outer archive. The bounded maxima are 1000 submissions, 500 files, 16 MiB attachment bytes and 1 MiB text metadata per part. Whole submissions stay together. A single submission exceeding a boundary fails clearly; it can be exported separately through a small quick selection where the established 100 MiB/500-file/8 MiB metadata limits still apply. These are resource guards, not unlimited object-size promises.

## Execution and recovery

No additional queue framework is bundled. This is a small single-operation state machine using native single WP-Cron events and a site-wide nonblocking flock. Each status request first verifies ownership and runs at most one pass. Thus an open page advances progress even with blocked cron or loopback; closing a page preserves the task but does not guarantee timely unattended progress. Sites needing reliable unattended execution must arrange regular WP-Cron execution through their hosting tools. The owned Docker/VPS container now runs only exporter cron hooks every ten seconds as www-data, in the same container/private storage as WordPress. The runner pauses while the synthetic destructive test fixture exists, to keep kill/checkpoint timing deterministic. This does not change cron or services on other sites. Tests do not make a general shared-hosting guarantee.

All-period discovery uses a read-only keyset cursor, the applied site-calendar interval and a captured upper entry ID. Discovery writes bounded ID chunks before advancing its checkpoint. Later new IDs are excluded. This is not a transactionally consistent point-in-time backup: deletion or modification during discovery/checking can cause an explicit error. Manual selection uploads at most 100 IDs per sequence; retries and cross-chunk duplicates are idempotent. No arbitrary paths or URLs are accepted.

Checking stores bounded review records and per-entry fingerprints, aggregates measured counts and commits bounded part manifests. Review pages show ten submissions and their reported omissions. Building compares fresh per-entry fingerprints and packages those exact compared plans. Each closed ZIP is moved into private task storage before incrementing completion. A killed pass can recreate an uncommitted part without duplicate publication. Package's task marker reclaims only that task's private killed workspace under Jobs' exclusive flock. Small synchronous operations keep the original lock behavior.

The background worker temporarily selects the owner, rechecks the actual Forminator permission resolver, switches the stored locale and restores caller/locale in finally. Owner deletion/revocation blocks work. Every endpoint validates authorization, nonce and owner; download verifies ready state, expiry and part bounds. Status and review never disclose server source paths.

## Storage and deletion

Task JSON/files use the validated private root outside public directories, directories 0700 and files 0600. JSON writes use checked temporary streams followed by atomic rename. Filesystem full/permission failures abort without a ready claim. Disk checks include an additional reserve and space for copies/new ZIP; retained prior parts still consume disk. Ordinary temporary system directories may be cleared externally; missing state/results require a new checked task.

Ready results remain available for repeated authorized POST streaming downloads for 24 hours after completion. The browser does not construct a Blob for large parts. Starting a new large task cancels a previously finished task for that owner and waits for its private cleanup before allocating a new directory; only one current task per owner is retained. Cancellation prevents further building and hides downloads immediately. Private cleanup proceeds in bounded 200-file passes, from cron or authenticated status polling; cancelled/expired tasks are never downloadable. Active downloads hold a shared flock and cannot be concurrently deleted by a task pass. Opportunistic/hourly collection reschedules expired/cancelled tasks; the uninstall script removes only exporter-owned task directories, pointers and events. Forminator forms/records/uploads are never cleanup targets.

## Validation

- Existing native WordPress/PHP/Forminator matrix, Docker and VPS suites remain enabled, including original source snapshots and negative path/role/file guards.
- Actual API-persisted 1000 entries on each environment; 10000 in the WP 7.1.3 native matrix job; PHP 128M, exact identity/attachment-byte checks across every generated part.
- Real normal exit, PHP fatal and SIGKILL after close/before checkpoint; no duplicated publication, task-specific workspace reclaimed without waiting an hour.
- Manual 101 selections, repeated append sequence, duplicate IDs, guest/other-owner access, owner revocation, changed checked entry, explicit missing-file consent, oversized-object failure, expiry and cancellation cleanup.
- Actual browser 101-entry all-period selection, reload restoration, paginated review, independent ZIP parts, repeated POST streaming downloads, Russian restored labels, Python CRC/JSON/byte validation and 390px layout. An additional owned-container check reaches review/ready through the real cron runner with no browser or direct run/tick calls.
- Screenshots use synthetic persisted data. Human first-use testing, full accessibility certification and general hosting compatibility remain separate.

The earlier prototype `3f6bf98` used deliberately smaller parts: 10000 entries/500 ZIPs in 28.172 seconds, peak 104136704 bytes, PHP 128M. It demonstrated feasibility, not acceptable final UX. Final boundaries above reduce unnecessary part count and require fresh measurements. Do not present that prototype measurement as the final release performance.

Deactivating the plugin pauses its callbacks and cleanup; reactivation can resume/expire saved tasks, while uninstall removes plugin-owned tasks. Download access stops on deactivation. Cancellation/expiry cleanup timing depends on an available runner; the expiry check denies late downloads even before physical cleanup has run.
