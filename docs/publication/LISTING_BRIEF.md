# WordPress.org listing preparation — 2026-10-14

## Positioning

Working display name: **File Packs for Forminator**.
Technical slug: `file-packs-for-forminator`. Directory slug availability and approval are not established.

Primary promise: **Keep each submission and its files together.**

Short description:

> Export Forminator submissions with their attachments into organized ZIP files, with readable HTML cards, a CSV register and JSON data.

The complete English listing, installation instructions and FAQ live in `plugin/readme.txt`. Maintain that file as the public copy source rather than duplicating the full listing here.

Audience: site administrators reviewing or handing over uploaded documents from saved Forminator submissions. The key benefit is an organized offline package, not another spreadsheet-only export.

Do not advertise unlimited resources, a complete backup, restore/import, cloud uploads, native XLSX, support for every Forminator field/add-on, or guaranteed compatibility with every host. Do not imply an official partnership or directory approval.

## Five planned screenshots

These are capture instructions and captions, not delivered English screenshots. Current evidence captures use synthetic submissions and include Russian UI. Re-capture the final interface in English with neutral English fixture labels; do not replace labels by painting over the screenshot.

| File | Capture | Directory caption |
|---|---|---|
| screenshot-1.png | Actual form selector, applied date range, selected synthetic submissions and main action | Choose a form, filter by date and select the submissions you need. |
| screenshot-2.png | Quick checked contents: counts, available attachments and download button | Check the available attachments before downloading your ZIP. |
| screenshot-3.png | Final large-task result: two real parts, primary download actions, filenames collapsed | Download large exports as independent ZIP parts, with saved progress and repeat downloads. |
| screenshot-4.png | Actual extracted index.html with English fixture labels and CSV/JSON links | Open the extracted index to browse submissions or access CSV and JSON data. |
| screenshot-5.png | Actual extracted request.html with field values and working attachment links | View each submission's details and attachments together in its own folder. |

Optional sixth screenshot: explicit incomplete result with omission reasons expanded. Keep it as support evidence until the five core captures are ready.

Insert the five captions into a `== Screenshots ==` section in readme.txt only when matching files exist. Upload the images to the directory's separate SVN assets folder after approval; do not add promotional assets to the installable plugin ZIP.

## Visual direction for the next step

A practical document utility: one folder grouping a form card and two attachment sheets. Use an original symbol, deep blue and a light background; avoid WordPress/Forminator logos and tiny lettering inside the icon.

Banner copy:

- Name: File Packs for Forminator
- Main message: Submissions and attachments. Together.
- Supporting line: Organized ZIP exports with HTML, CSV and JSON.

Prepare PNG icon variants at 128×128 and 256×256 and banner variants at 772×250 and 1544×500. Check the icon at its smallest size and check banner text at both supplied resolutions. These are production instructions; no icon/banner is claimed complete.

## Before submission

- Confirm the WordPress.org contributor username. The existing `Contributors: danilah` header is inherited from the repository and has not been independently verified as the owner's directory account.
- Confirm directory name/slug availability through the actual submission process. Keep the independent-project statement.
- Complete and inspect the five real English captures and original icon/banner.
- Validate the readme with the official validator; local package checks do not equal an official validator result.
- Use an installable ZIP from the exact final commit that passed the required native, Docker and VPS release checks. Changes to shipped readme.txt change that commit and ZIP.
- Submit from the owner's WordPress.org account. No submission, public publication or account action has been performed by preparing these files.

## Official references checked

- Readme format and stable-tag handling: https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
- Asset names, image sizes and separate assets directory: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/
- Submission entry point: https://wordpress.org/plugins/developers/add/

No approval timeline is promised.
