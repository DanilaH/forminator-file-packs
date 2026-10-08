# Technical handoff — 2026-10-08

The [roadmap](../ROADMAP.md) owns milestone status and the [v1 specification](V1_SPEC.md) owns agreed scope, UX, and commercial boundaries. Read them before implementation; this file provides technical orientation.

## Product

WordPress plugin: select Forminator submissions, preview their contents, and download one ZIP containing submission folders, readable HTML cards, attachments, an HTML index, and a CSV register. Source records and files remain unchanged.

This is an exporter, not Forminator Transfer/Recovery, a filesystem browser, or a CRM. The name is provisional and has not been cleared for WordPress.org.

## Current state

First working development build v0.1.0 exists. Stages 1–3 have a verified first pass on one native lab; reliability and release work remain. Read [test report](TEST_REPORT_2026-10-08.md) and [adapter notes](ADAPTER.md) before modifying integration.

## Next milestone

Expand compatibility and hosting coverage, repeaters and custom permissions; test low disk/memory/time and interrupted requests; improve UX accessibility and localization. Do not turn one successful lab into a broad release compatibility claim.

## Architecture

Admin controller → authorization → Forminator adapter → export planner → package builder → private temporary storage → authorized download and cleanup.

Keep the UI in standard WordPress admin components with minimal JavaScript. Data permissions follow Forminator's actual entries capability. Guests and subscribers are rejected; custom permission configurations need further coverage.

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
