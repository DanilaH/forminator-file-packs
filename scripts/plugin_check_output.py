"""Strict parser for the pinned Plugin Check 2.1.0 CLI output."""
import json
import re


def parse_diagnostics(output):
    # Plugin_Check_Command uses WP_CLI::success even with --format=json when clean.
    # Match the entire known response: never discard arbitrary non-JSON lines.
    text = output.strip()
    if text == 'Success: Checks complete. No errors found.':
        return []
    if not text:
        raise ValueError('Plugin Check returned no evidence')
    diagnostics = []
    for block in re.split(r'^FILE: .*\n', text, flags=re.MULTILINE):
        if not block.strip():
            continue
        rows = json.loads(block)
        if not isinstance(rows, list) or not all(isinstance(row, dict) for row in rows):
            raise ValueError('Unexpected Plugin Check diagnostic structure')
        diagnostics.extend(rows)
    return diagnostics
