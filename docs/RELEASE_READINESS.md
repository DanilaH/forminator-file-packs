# Release readiness — 2026-10-10

## Evidence, versions and gates

Complete verification of `f366a62bc55dd198453d500d91e110b4081c1883` / beta 0.3.0:

| Environment | Result | Evidence |
|---|---|---|
| Native WP 7.1.3 / PHP 8.3 / Forminator 1.58.0 | Passed | [native matrix and installable ZIP](https://github.com/DanilaH/forminator-file-packs/actions/runs/37982699318) |
| Native WP 6.5.5 / PHP 8.2 / Forminator 1.58.0 and 1.57.1 | Both passed | Same matrix; exact PHP patch versions in environment artifacts |
| Docker, actual SSH transfer/tunnel, two complete cycles | Passed | [Docker evidence](https://github.com/DanilaH/forminator-file-packs/actions/runs/37982699277) |
| Actual isolated VPS, two complete cycles | Passed | [VPS evidence](https://github.com/DanilaH/forminator-file-packs/actions/runs/37983163324) |

0.3.1 changes package identity and translation timing. Additional local-storage checks exercise actual chmod denial, SVG/JS/PHP/unknown types and metadata from a removed upload field; each must produce explicit preview/JSON warnings, reject strict export, preserve source bytes/metadata and clean private jobs. It must pass its own native matrix, Docker and VPS workflows before distribution. Use the Actions runs for the exact commit being downloaded. The native package job publishes `installable-plugin` only after every matrix job passes. VPS deployment independently requires both native and Docker success and current trusted main SHA. Historical green results do not approve changed code.

The full suite includes actual saved frontend uploads and six repeater modes, independently decoded CSV/JSON/ZIP and attachment bytes, source snapshots, 43 HTTP and 32 security checks, 17 resilience cases, 17 permission checks, browser and Russian UI checks, boundary/resource checks and plugin/process lifecycle. Actual VPS maximum: 100 entries / 500 files / 100 MiB under PHP 128M, 3.622 seconds to construct, 76,873,728 bytes peak memory; browser download 12.174 seconds via the test tunnel. These measurements describe one environment.

## Technical audit

| Item | Finding / enforcement |
|---|---|
| Package identity | `file-packs-for-forminator` for folder, main PHP file, translation domain and catalogs. The previous slug began with another project's name; guideline 17 prohibits that. Repository URL remains unchanged. Slug allocation is ultimately the directory team's decision. |
| Version / requirements | Header, export JSON version and readme stable tag 0.3.1; WP >=6.5, PHP >=8.2; actual Forminator dependency declared. Automated checks catch mismatches and unexpected shipped files. |
| Source / license | Four PHP classes and bootstrap, own plain JS/CSS, own PO/MO and readme, root GPL-2.0-or-later LICENSE. No third-party library/font/image/vendor source is bundled. Readme links public source/build tools. WordPress, Forminator and native ZipArchive are external runtime dependencies. Test tools, Docker images and WP-CLI do not enter the installable ZIP. |
| Data / network | Four authenticated POST endpoints with independent capability and nonce checks. No unauthenticated routes, outgoing HTTP, tracking, payment, external code download, source-data writes or uninstall deletion of Forminator records. Browser only calls same-origin WordPress AJAX. |
| File handling | Resolve only known persisted upload metadata; constrain real local roots/media references. Source paths never accepted from HTTP clients. Remote/active attachment types omitted with explicit warnings; private temporary jobs, bounded streams, locks and cleanup. Narrow native filesystem/SQL annotations explain the checks instead of hiding whole files. |
| UI | Assets restricted to the plugin page; standard Tools submenu; selection/date/preview invalidation, explicit partial consent and retry behavior. Browser tests cover EN/RU and narrow layout. No frontend branding or dashboard advertising. |
| Translation | Bundled Russian catalog works outside the directory. Official guidance recommends init for such plugins. Plugin Check 2.1.0 emits one discouraged `load_plugin_textdomain` warning; it is visible and allowed specifically, all other diagnostics fail CI. This is not a zero-warning claim. |
| Earlier beta replacement | Different folder identity requires deactivating/deleting old beta before installing 0.3.1. No own persisted business data is lost. Native lifecycle validates the exact historical package and source preservation; staging removes only the known old beta in our owned installation. Do not present it as an automatic production upgrade. |

## Remaining publication work

- Confirm final displayed name and publisher's WordPress.org username; `danilah` in the draft readme has not been verified as that account.
- Edit the directory description/FAQ/support instructions, remove draft beta wording when the release is actually designated stable, prepare screenshots, icon and banner with GPL-compatible rights.
- Choose a final public release number and keep header, constant, readme and tests synchronized. Re-run checks after code or technical identity changes; package-only checks still apply to editorial changes.
- Submit the reviewed ZIP from the publisher account and respond to directory review. Submission approval and automated directory security review are external checks, not guarantees provided by our CI.

## Supported scope and unverified configurations

Local saved active nonspam Forminator submissions, maximum synchronous batch sizes and the recorded WordPress/PHP/Forminator combinations. XLSX, cloud/offload download, migrations/restores, scheduled export and Pro are outside v1. Missing/remote files produce warnings instead of silent successful completeness.

No full screen-reader/WCAG audit, Windows verification, general shared-hosting certification, every offload integration, real host disk exhaustion/bootstrap OOM or concurrent mid-request permission revocation is claimed. Disk/write/time/memory faults are injected in controlled tests; SIGKILL terminates only a dedicated synthetic PHP child. These limitations should stay in compatibility/support copy.

The VPS uses private loopback plus SSH tunnel. Public staging DNS, Caddy route and HTTPS are not configured, and are not requirements for delivering a WordPress installable ZIP. Existing production services are outside this deployment project.

## Official references checked

- [Directory guidelines, including licenses and naming](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Plugin Check 2.1.0 and limits of automated review](https://wordpress.org/plugins/plugin-check/)
- [Translation timing and standalone plugin guidance](https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/)
- [Directory automated security review](https://make.wordpress.org/plugins/2026/09/09/automated-security-review-for-plugin-releases/)
