"""Check the installable ZIP, release metadata and bundled source/license boundaries."""
import re
from pathlib import Path
from zipfile import ZipFile

repo = Path(__file__).resolve().parents[1]
source = repo / 'plugin'
slug = 'file-packs-for-forminator'
assert not any(p.is_symlink() for p in source.rglob('*')), 'No symlinks in shipped source'
main = (source / (slug + '.php')).read_text()
readme = (source / 'readme.txt').read_text()
def header(text, name):
    match = re.search(r'^\s*\*?\s*' + re.escape(name) + r':\s*(.+?)\s*$', text, re.M)
    assert match, 'Missing header: ' + name
    return match[1]
version = header(main, 'Version')
assert re.fullmatch(r'\d+\.\d+\.\d+', version), 'Numeric release version'
assert "const VERSION = '" + version + "';" in main, 'Export version matches plugin header'
assert header(readme, 'Stable tag') == version, 'Stable tag matches version'
for name in ('Requires at least', 'Requires PHP', 'Requires Plugins', 'License URI'):
    assert header(main, name) == header(readme, name), 'Mismatched requirement: ' + name
assert header(main, 'Requires Plugins') == 'forminator', 'Declare actual dependency'
assert header(main, 'Text Domain') == slug and header(main, 'Domain Path') == '/languages'
assert readme.splitlines()[0] == '=== ' + header(main, 'Plugin Name') + ' ==='
assert len(readme.split('\n\n', 2)[1].strip()) <= 150, 'Directory short description exceeds 150 characters'
assert len(header(readme, 'Tags').split(',')) <= 5
assert header(main, 'License') == 'GPL-2.0-or-later'
assert header(readme, 'License') == 'GPLv2 or later'
assert (source / 'languages' / (slug + '-ru_RU.po')).is_file()
assert (source / 'languages' / (slug + '-ru_RU.mo')).is_file()
allowed = {slug+'.php', 'readme.txt', 'includes/class-adapter.php', 'includes/class-admin.php',
           'includes/class-planner.php', 'includes/class-package.php', 'assets/admin.css',
           'assets/admin.js', 'languages/'+slug+'-ru_RU.po', 'languages/'+slug+'-ru_RU.mo'}
files = {p.relative_to(source).as_posix(): p for p in source.rglob('*') if p.is_file()}
assert set(files) == allowed, 'Unexpected or missing shipped files; review license and purpose'
expected = {slug+'/'+name: p.read_bytes() for name,p in files.items()}
expected[slug+'/LICENSE'] = (repo / 'LICENSE').read_bytes()
assert b'GNU GENERAL PUBLIC LICENSE' in expected[slug+'/LICENSE']
packages = list((repo / 'dist').glob('*.zip'))
assert len(packages) == 1, 'Build in a clean dist directory'
assert packages[0].name == slug+'-'+version+'-dev.zip'
with ZipFile(packages[0]) as archive:
    assert len(archive.namelist()) == len(expected) and set(archive.namelist()) == set(expected)
    assert archive.testzip() is None
    assert all(archive.read(name) == value for name, value in expected.items())
print('PASS ZIP CRC, exact source bytes, version/requirements/domain/readme metadata and license')
