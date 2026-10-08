# File Packs for Forminator

A WordPress plugin that exports selected saved Forminator submissions and local attachments into one ZIP. Includes readable HTML cards, an HTML index, a CSV register and a warnings manifest. Original records and files remain unchanged.

**Status: first working development build, v0.1.0.** Tested with WordPress 7.1.3, Forminator 1.58.0 and PHP 8.3.6. This is not a production or WordPress.org release; older minimum-version targets are not yet verified.

## Use

1. Install and activate Forminator, then install the development ZIP.
2. Open **Tools → File Packs** using an account allowed to view Forminator entries.
3. Choose a form, filter dates, select submissions and inspect the preview.
4. Download the ZIP. Missing or unsupported attachments require explicit acceptance of an incomplete package.
5. Extract the ZIP and open `index.html`; each `Request-ID` folder contains its card and attachments. `register.csv` opens in spreadsheet applications.

PHP ZIP support and writable private temporary storage outside public web directories are required. The conservative per-export limits are 100 submissions, 500 attachments and 100 MiB of attachment bytes. These are safety limits, not a tested capacity guarantee for every hosting provider. No remote uploads are fetched. HTML, SVG and executable attachments are excluded with warnings.

## Development

See [setup and tests](docs/DEVELOPMENT.md), [verification report](docs/TEST_REPORT_2026-10-08.md), [integration notes](docs/ADAPTER.md), [roadmap](ROADMAP.md), [v1 specification](docs/V1_SPEC.md) and [remaining acceptance work](docs/ACCEPTANCE.md).

Build the installable ZIP with `python3 scripts/build.py`. The output is `dist/forminator-file-packs-0.1.0-dev.zip`; development tests and fixtures are excluded.

## Boundaries

Forminator only; saved active nonspam submissions; verified local attachments. No migration, source-file renaming, cloud integrations, CRM, SaaS, schedules or license server. No telemetry. Future Pro work stays separate and depends on validated demand.

The name is provisional. No directory approval, compatibility certification or working payment route is claimed.

## License

GPL-2.0-or-later. Independent project; not affiliated with WPMU DEV.
