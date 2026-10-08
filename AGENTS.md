# Project instructions

Build File Packs for Forminator: read-only export of selected submissions, local attachments, HTML cards, and a CSV register into a ZIP.

- Read README.md and docs/HANDOFF.md before changing scope.
- Inspect actual Forminator source and synthetic submissions before implementing an adapter. Do not invent methods, upload formats, or capabilities.
- Preserve original forms, records, file paths, names, and bytes. Never implement migration or cleanup of Forminator data.
- Keep Forminator integration isolated from export planning and package generation.
- Enforce server-side authorization on every data endpoint; nonces are not authorization.
- Never accept arbitrary client-supplied file paths or fetch remote upload URLs.
- Validate real paths, symlinks, submission ownership, ZIP paths, HTML escaping, CSV formula injection, and filename collisions.
- Do not store exported documents in a public web directory. Random filenames or .htaccess alone are insufficient.
- Use synthetic data only. Do not commit real submissions, credentials, payment details, generated archives, or vendor plugin source.
- Keep scope small: no CRM, cloud, scheduler, SaaS, migration, or licensing server. Useful Free plugin; any future Pro features live in a separate package.
- Working title and minimum-version targets are provisional. Record tested versions and limitations; never imply Plugin Check, installation, or exports passed without running them.
- The repository is public. Keep user-specific commercial and personal context out of committed files.

Validation: PHP lint; real WordPress activation with and without Forminator; real single/multi-upload submissions; export-content and unchanged-source checks; negative authorization/path tests; Plugin Check before release. Report unavailable checks explicitly.
