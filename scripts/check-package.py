"""Verify the shipped ZIP contains exactly the plugin source and GPL license."""
from pathlib import Path
from zipfile import ZipFile

repo = Path(__file__).resolve().parents[1]
expected = {'forminator-file-packs/' + str(p.relative_to(repo / 'plugin')): p.read_bytes()
            for p in (repo / 'plugin').rglob('*') if p.is_file()}
expected['forminator-file-packs/LICENSE'] = (repo / 'LICENSE').read_bytes()
packages = list((repo / 'dist').glob('*.zip'))
assert len(packages) == 1, 'Build in a clean dist directory'
with ZipFile(packages[0]) as archive:
    assert len(archive.namelist()) == len(expected) and set(archive.namelist()) == set(expected)
    assert archive.testzip() is None
    assert all(archive.read(name) == value for name, value in expected.items())
print('PASS ZIP CRC, exact members, source bytes and license')
