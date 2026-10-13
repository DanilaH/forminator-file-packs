# Technical handoff — 2026-10-13

The [roadmap](../ROADMAP.md) owns milestone status and the [v1 specification](V1_SPEC.md) owns agreed scope, UX, and commercial boundaries. Read them before implementation; this file provides technical orientation.

## Product

WordPress plugin: select Forminator submissions, preview their contents, and download one ZIP containing submission folders, readable HTML cards, attachments, an HTML index, and a CSV register. Source records and files remain unchanged.

This is an exporter, not Forminator Transfer/Recovery, a filesystem browser, or a CRM. The name is provisional and has not been cleared for WordPress.org.

## Current state

0.3.0 passed the complete native three-combination matrix and two complete Docker/VPS cycles. See [release readiness](RELEASE_READINESS.md) for exact evidence and scope. 0.3.1 introduced the directory-compatible slug `file-packs-for-forminator`, matching filenames/domain and package metadata checks. 0.3.2 refines first-use UX. Its own green Actions runs are mandatory before distribution; previous version evidence is not a substitute.

The old beta must be deactivated/deleted before installing the new slug. Native lifecycle checks install the exact historical 0.2.0 beta, verify source integrity, remove it, and install the new package without duplicate namespaces. Owned staging handles only this known beta replacement; no general production migration is implemented.

## Next milestone

Finish publication materials and confirm the publisher's WordPress.org username. The repo username does not establish the directory account. Plugin Check is pinned to 2.1.0 (latest official version observed 2026-10-10); every unexpected diagnostic blocks CI. The bundled standalone translations legitimately retain one discouraged-function warning; registration now runs on init. Do not suppress it or claim zero warnings.

VPS deployments automatically follow successful native/Docker checks for the exact trusted main commit; strict SSH known_hosts and a private loopback/SSH tunnel are used. Caddy/DNS/HTTPS remain optional staging presentation work; no production route has been changed. Broader hosting, offload integrations and screen readers remain outside tested compatibility.

## Architecture

Admin controller → authorization → Forminator adapter → export planner → package builder → private temporary storage → authorized download and cleanup.

Keep the UI in standard WordPress admin components with minimal JavaScript. Data permissions follow Forminator's actual entries capability. Guests and ordinary subscribers are rejected. HTTP checks cover specifically permitted users, permitted roles, excluded users and capability revocation in an existing session. Concurrent mid-request revocation remains untested.

Local attachments only initially. Remote/offloaded/missing files need explicit warnings. Do not retrieve arbitrary URLs or silently claim a complete export.

## Packaging and commercial boundary

Free should offer a useful standard export without trial counters. A future separate Pro package may add grouping/naming rules and saved profiles; willingness to pay is unverified. No payment integration or licensing service belongs in the first technical milestone.

## Remaining work

See the publication checklist and limitations in [RELEASE_READINESS.md](RELEASE_READINESS.md). All new compatibility claims need direct evidence. No payment integration or Pro development is authorized by technical release readiness.

## UX refinement 0.3.2

Plugin-list entry point, bilingual quick start/result instructions, fixed viewport notifications with direct retry, cross-page selected-row review/removal, clearer empty states. Date edits keep selection and pause export; successful period reload clears it. Search preserves selection if its form remains available. Expanded real browser checks are integrated in native/Docker/VPS suites. Verification of the exact current commit is required; prior green runs are not evidence for 0.3.2.

## Refinement 0.3.3

The independent follow-up reproduced stale session recovery, recovery after clearing selection, stale selected-row summaries and indefinite browser waiting. 0.3.3 fixes those paths, adds authenticated environment readiness in the existing forms endpoint, descriptive RFC 5987 ZIP names and server-computed quick periods in the existing entries endpoint. No new endpoint, public storage or outgoing data transfer is introduced. Readiness probes only this plugin’s private directory and removes its tiny probe; resource suitability is still checked for the actual package. JSON waits are capped at 90 seconds; full ZIP fetch/body consumption at 10 minutes. Stop waiting is deliberately not a claim that PHP has stopped. Native, Docker and VPS suites include new environment/calendar/name and browser regressions. Exact current-commit green checks are required before distributing this version.


## Usability pass 0.3.4

Seven explicitly authorized refinements: bounded escaped offline index summaries/counts, selection capacity and stale recovery fix at the row limit, localized omission next steps, an actual browser-download result panel, optional localStorage of only quick-start collapse state, automatic single-form selection after forms loading completes, and beforeunload only during ZIP operation with cleanup after every success/error/abort. JSON schema and warnings manifest keep their existing shape; next_step is preview-only. Original source guards and resource limits are unchanged. Current-commit CI/Docker/VPS and first-time human usability testing are separate requirements.


## Simplified task flow 0.3.5

The user could not understand the 0.3.4 screenshots. Explicitly authorized redesign: one workspace, task-first wording, collapsed optional help/date/content details, failure-only environment block, compact reviewed-contents confirmation and extraction guidance. Backend security, private handling and export contracts remain enforced. List attachment_count counts only saved nonempty paths in recognized upload fields, without resolving or claiming exportability; preview totals still use verified attachments. Preview-only summaries are bounded. Screenshots must show real UI with synthetic persisted records; do not claim first-time human usability testing.
