# Technical handoff — 2026-10-08

The [roadmap](../ROADMAP.md) owns milestone status and the [v1 specification](V1_SPEC.md) owns agreed scope, UX, and commercial boundaries. Read them before implementation; this file provides technical orientation.

## Product

WordPress plugin: select Forminator submissions, preview their contents, and download one ZIP containing submission folders, readable HTML cards, attachments, an HTML index, and a CSV register. Source records and files remain unchanged.

This is an exporter, not Forminator Transfer/Recovery, a filesystem browser, or a CRM. The name is provisional and has not been cleared for WordPress.org.

## Current state

Beta v0.3.0 adds `data.json` with structured field values, final attachment references and completeness/warnings. See [JSON contract](DATA_FORMAT.md) and [0.3.0 verification](JSON_TEST_REPORT_2026-10-08.md). Beta v0.2.0 historical checks: Three compatibility combinations, failure/cleanup, native repeater, Russian UI and installation lifecycle are verified; real-hosting and release work remain. Read [beta report](BETA_TEST_REPORT_2026-10-08.md). Read [test report](TEST_REPORT_2026-10-08.md) and [adapter notes](ADAPTER.md) before modifying integration.

## Next milestone

Docker/VPS staging implementation: [STAGING.md](STAGING.md), `staging-check.yml` (automatic Docker verification) and `vps-staging.yml` (manual inspect/deploy). User's actual host, Caddy route and DNS still need an authenticated inspection and real deployment. VPS deploy requires successful native and Docker CI for the exact main commit and verified SSH known_hosts. Start with private loopback/SSH tunnel; select Caddy host/network configuration after inspection. Do not claim VPS readiness from local or Actions Docker success.

Test actual hosting providers, offload configurations and screen readers; finalize release naming/license/materials. Six repeater configurations, the combined maximum ceiling, boundary rejection, existing-session permission revocation and focused automated accessibility checks now have recorded native-lab evidence. Disk/memory/time/write failures, process termination, specific-user permissions and Russian UI already have recorded checks. Do not turn one successful lab into a broad release compatibility claim.

## Architecture

Admin controller → authorization → Forminator adapter → export planner → package builder → private temporary storage → authorized download and cleanup.

Keep the UI in standard WordPress admin components with minimal JavaScript. Data permissions follow Forminator's actual entries capability. Guests and ordinary subscribers are rejected. HTTP checks cover specifically permitted users, permitted roles, excluded users and capability revocation in an existing session. Concurrent mid-request revocation remains untested.

Local attachments only initially. Remote/offloaded/missing files need explicit warnings. Do not retrieve arbitrary URLs or silently claim a complete export.

## Packaging and commercial boundary

Free should offer a useful standard export without trial counters. A future separate Pro package may add grouping/naming rules and saved profiles; willingness to pay is unverified. No payment integration or licensing service belongs in the first technical milestone.

## Remaining questions

- Current API and upload metadata, including repeatable fields and Media Library behavior.
- Safe private temporary storage on realistic hosting configurations.
- Archive memory/time/disk limits and recovery after interrupted requests.
- Whether export packages satisfy users asking for server-side folders.
- Commercial value beyond a complete standard export.
- Final brand, tested compatibility range, directory approval, and release readiness.
