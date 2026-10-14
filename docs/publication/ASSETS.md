# Publication assets

This folder contains the directory listing assets, outside the installable plugin.

- `assets/icon-128x128.png` and `assets/icon-256x256.png`: original folder/document/download symbol.
- `assets/banner-772x250.png` and `assets/banner-1544x500.png`: matching English banner.
- `assets/icon.svg` and `assets/banner.svg`: editable vector sources.
- `assets/screenshot-1.png` through `assets/screenshot-5.png`: real English UI/export captures. Captions are in `plugin/readme.txt`.

Use the four size-named PNGs and five screenshots in the WordPress.org SVN `/assets` directory after approval. The banner SVG is an editable source, not a supported directory banner format. The icon SVG is an optional directory icon with the supplied PNG fallback.

Source and assets use the repository's GPL-2.0-or-later license. Screenshot names, enquiries and attachments are synthetic lab examples; they are not customer records. Screenshots preserve the actual interface and actual exported HTML. They are not UI mockups.

To regenerate the original graphics, install CairoSVG and Pillow in your development environment and run `python scripts/build-publication-art.py`. No graphics dependency is needed by the WordPress plugin.

The downloadable publication kit contains these assets, the listing readme, the brief and the license. It is not an installable WordPress plugin ZIP and has not been submitted or approved. Get the installable plugin separately from the exact verified release commit.
