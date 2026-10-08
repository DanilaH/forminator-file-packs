# Technical handoff — 2026-10-08

The [roadmap](../ROADMAP.md) owns milestone status and the [v1 specification](V1_SPEC.md) owns agreed scope, UX, and commercial boundaries. Read them before implementation; this file provides technical orientation.

## Product

WordPress plugin: select Forminator submissions, preview their contents, and download one ZIP containing submission folders, readable HTML cards, attachments, an HTML index, and a CSV register. Source records and files remain unchanged.

This is an exporter, not Forminator Transfer/Recovery, a filesystem browser, or a CRM. The name is provisional and has not been cleared for WordPress.org.

## Current state

Repository initialized with a status-page scaffold, development-environment configuration, packaging script, and acceptance plan. No adapter, preview, ZIP export, or download endpoint exists yet. No WordPress installation test or Plugin Check has passed yet.

## Next milestone

1. Start a disposable WordPress environment with Forminator; record exact WordPress, PHP, Forminator, and tooling versions.
2. Create synthetic submissions through the actual form frontend: one upload, multiple uploads, multiple upload fields, no files, Unicode names, and duplicate names. Include AJAX and Media Library modes where applicable.
3. Inspect the current Forminator source and persisted metadata. Record provenance and the exact relationship between a form, entry, upload field, and permitted local file.
4. Implement a narrow read-only adapter. Reject unknown shapes rather than guessing paths.
5. Produce a standard package from real synthetic submissions. Verify attachment bytes and unchanged source data.
6. Add one admin flow: form → submissions/period → preview/warnings → authorized download.

## Architecture

Admin controller → authorization → Forminator adapter → export planner → package builder → private temporary storage → authorized download and cleanup.

Keep the UI in standard WordPress admin components with minimal JavaScript. Define the actual authorization matrix after inspecting Forminator. The scaffold's manage_options permission is only for its status page.

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
