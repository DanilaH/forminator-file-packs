# File Packs for Forminator

Development repository for a WordPress plugin that exports selected Forminator submissions and local attachments into a ZIP with readable submission cards and a CSV register.

**Status:** initial scaffold; submission reading and export are not implemented. This is not a production release.

## First milestone

Run WordPress with Forminator, create synthetic submissions, inspect actual upload metadata, and build a read-only export without changing source records or files. See [development setup](docs/DEVELOPMENT.md), [scope and handoff](docs/HANDOFF.md), and [acceptance checklist](docs/ACCEPTANCE.md).

## Boundaries

- Forminator only; local attachments first.
- Preview and explicit missing/unsupported-file warnings.
- Authorized downloads and private temporary storage.
- No migration, original-file renaming, cloud integrations, CRM, SaaS, or license server.

The repository and plugin names are provisional. No WordPress.org approval, compatibility certification, or working payment route is claimed.

## License

GPL-2.0-or-later. Independent project; not affiliated with WPMU DEV.
