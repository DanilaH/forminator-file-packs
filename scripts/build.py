#!/usr/bin/env python3
"""Package only the plugin and GPL license into a development ZIP."""

import re
import subprocess
import sys
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile


def main():
    root = Path(__file__).resolve().parents[1]
    subprocess.run([sys.executable, str(root / "scripts/compile-translations.py")], check=True)
    source = root / "plugin"
    version = re.search(r"Version:\s*([0-9.]+)", (source / "file-packs-for-forminator.php").read_text())[1]
    output = root / "dist" / f"file-packs-for-forminator-{version}-dev.zip"
    output.parent.mkdir(exist_ok=True)
    with ZipFile(output, "w", compression=ZIP_DEFLATED) as archive:
        for path in sorted(source.rglob("*")):
            if path.is_symlink():
                raise RuntimeError(f"Refusing to package a symlink: {path.name}")
            if path.is_file():
                archive.write(path, Path("file-packs-for-forminator") / path.relative_to(source))
        archive.write(root / "LICENSE", "file-packs-for-forminator/LICENSE")
    print(f"Development build; not a production release. Built {output}")


if __name__ == "__main__":
    main()
