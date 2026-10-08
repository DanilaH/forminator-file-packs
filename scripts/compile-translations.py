#!/usr/bin/env python3
"""Compile the simple singular PO catalog with Python's standard library."""
import ast
import struct
from pathlib import Path


def compile_catalog(source):
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
    source.with_suffix('.mo').write_bytes(header + b''.join(struct.pack('<2I', *o) for o in offsets) + originals + translations)


if __name__ == '__main__':
    for file in (Path(__file__).resolve().parents[1] / 'plugin/languages').glob('*.po'):
        compile_catalog(file)
