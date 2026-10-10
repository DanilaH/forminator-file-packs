# File Packs for Forminator

A WordPress plugin that exports selected saved Forminator submissions and local attachments into one ZIP. Includes readable HTML cards, an HTML index, a CSV register, structured JSON and a warnings manifest. Original records and files remain unchanged.

**Status: release preparation v0.3.1.** The technical slug is `file-packs-for-forminator`; the GitHub repository keeps its existing address. Complete native, Docker and actual VPS regression passes for 0.3.0 are recorded in [release readiness](docs/RELEASE_READINESS.md). The current 0.3.1 build is releasable only after its own native/Docker/VPS checks succeed. WordPress.org publication and approval have not happened.

## Use

1. Install and activate Forminator, then install the development ZIP. When replacing a beta using the old `forminator-file-packs` folder, deactivate and delete that beta first. Exported source records/files are not plugin-owned data.
2. Open **Tools → File Packs** using an account allowed to view Forminator entries.
3. Choose a form, filter dates, select submissions and inspect the preview.
4. Download the ZIP. Missing or unsupported attachments require explicit acceptance of an incomplete package.
5. Extract the ZIP and open `index.html`; each `Request-ID` folder contains its card and attachments. `register.csv` opens in spreadsheet applications; `data.json` preserves exported field values/structure and links to the files inside the ZIP. See [the JSON contract](docs/DATA_FORMAT.md).

PHP ZIP support and writable private temporary storage outside public web directories are required. The conservative per-export limits are 100 submissions, 500 attachments and 100 MiB of attachment bytes. These are safety limits, not a tested capacity guarantee for every hosting provider. No remote uploads are fetched. HTML, SVG and executable attachments are excluded with warnings.

English and Russian UI/package translations are bundled. Dates remain visible on narrow screens. Six native repeater upload configurations are tested on Forminator 1.58.0: single, multiple and AJAX multiple uploads, each with and without Media Library storage. The combined 100-entry / 500-file / 100 MiB ceiling has passed a native lab export and a real browser download; host capacity still varies.

## Development

GitHub Actions automatically runs the real WordPress regression matrix on pushes and pull requests. A successful run provides an `installable-plugin` ZIP artifact; failed checks prevent that artifact from being published. See [Actions](https://github.com/DanilaH/forminator-file-packs/actions/workflows/verify.yml) and [CI details](docs/DEVELOPMENT.md#github-actions).

An isolated Docker/VPS staging workflow is available separately; see [staging setup](docs/STAGING.md). The actual VPS has passed two complete test cycles over a private SSH tunnel. Public Caddy routing, DNS and HTTPS have not been configured and are not needed to install the plugin ZIP.

See [setup and tests](docs/DEVELOPMENT.md), [verification report](docs/TEST_REPORT_2026-10-08.md), [integration notes](docs/ADAPTER.md), [roadmap](ROADMAP.md), [v1 specification](docs/V1_SPEC.md) and [remaining acceptance work](docs/ACCEPTANCE.md).

Build the installable ZIP with `python3 scripts/build.py`. The output is `dist/file-packs-for-forminator-0.3.1-dev.zip`; development tests and fixtures are excluded.

## Boundaries

Forminator only; saved active nonspam submissions; verified local attachments. No migration, source-file renaming, cloud integrations, CRM, SaaS, schedules or license server. No telemetry. Future Pro work stays separate and depends on validated demand.

The name is provisional. No directory approval, compatibility certification or working payment route is claimed.

## License

GPL-2.0-or-later. Independent project; not affiliated with WPMU DEV.
