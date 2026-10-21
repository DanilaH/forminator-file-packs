#!/usr/bin/env python3
"""Compile the simple singular PO catalog with Python's standard library."""
import ast
import struct
from pathlib import Path


def compile_catalog(source, destination):
    messages = {}
    key = value = None
    for line in source.read_text().splitlines():
        if line.startswith('msgid '):
            key = ast.literal_eval(line[6:])
        elif line.startswith('msgstr '):
            value = ast.literal_eval(line[7:])
            messages[key] = value
    pairs = sorted((k.encode(), v.encode()) for k, v in messages.items())
    count = len(pairs)
    start = 28 + count * 16
    originals = b''.join(k + b'\0' for k, v in pairs)
    translations = b''.join(v + b'\0' for k, v in pairs)
    offsets, cursor = [], start
    for key, value in pairs:
        offsets.append((len(key), cursor)); cursor += len(key) + 1
    for key, value in pairs:
        offsets.append((len(value), cursor)); cursor += len(value) + 1
    header = struct.pack('<7I', 0x950412de, 0, count, 28, 28 + count * 8, 0, 0)
    destination.write_bytes(header + b''.join(struct.pack('<2I', *o) for o in offsets) + originals + translations)


if __name__ == '__main__':
    from zipfile import ZipFile, ZIP_DEFLATED
    root = Path(__file__).resolve().parents[1]
    output = root / 'dist/languages'
    output.mkdir(parents=True, exist_ok=True)
    for source in sorted((root / 'translations').glob('*.po')):
        destination = output / source.with_suffix('.mo').name
        compile_catalog(source, destination)
        with ZipFile(output / (source.stem + '.zip'), 'w', compression=ZIP_DEFLATED) as archive:
            archive.write(source, source.name)
            archive.write(destination, destination.name)
