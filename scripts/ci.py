"""Provision a disposable native WordPress lab and run the existing regression suite.

Requires an empty MariaDB database on localhost:3306, PHP, WP-CLI, Node and
Playwright. Never takes a path/URL for an existing WordPress installation.
"""
import json
import os
import re
import secrets
import shutil
import socket
import subprocess
import tempfile
import time
from pathlib import Path
from zipfile import ZipFile

assert os.environ.get('FFP_TEST_LAB') == '1', 'Disposable lab only'
repo = Path(__file__).resolve().parents[1]
out = repo / 'artifacts/ci'
out.mkdir(parents=True, exist_ok=True)
if any(out.glob('*.zip')):
    raise RuntimeError('Use a clean artifact directory; old exports must not enter validation')
lab = Path(tempfile.mkdtemp(prefix='ffp-ci-', dir=os.environ.get('RUNNER_TEMP')))
root = lab / 'wordpress'
root.mkdir()
env = os.environ.copy()
env.update(FFP_TEST_FIXTURE=str(lab / 'fixture.json'), FFP_TEST_ARTIFACTS=str(out),
           FFP_TEST_PASSWORD=secrets.token_urlsafe(24), FFP_TEST_USER='lab',
           FFP_TEST_URL='http://127.0.0.1:8080', FFP_WP_PATH=str(root))
if os.environ.get('GITHUB_ACTIONS') == 'true':
    print('::add-mask::' + env['FFP_TEST_PASSWORD'], flush=True)
wp = ['wp', '--path=' + str(root)]
env['FFP_WP_COMMAND'] = json.dumps(wp)


def run(args, **kwargs):
    return subprocess.run(args, cwd=repo, env=env, check=True, **kwargs)


def eval_file(name):
    print('Running ' + name, flush=True)
    run(wp + ['eval-file', str(repo / 'tests' / name)])


server = None
try:
    wordpress = env['FFP_CI_WORDPRESS']
    forminator = env['FFP_CI_FORMINATOR']
    run(wp + ['core', 'download', '--version=' + wordpress])
    run(wp + ['config', 'create', '--dbname=filepacks', '--dbuser=root',
              '--dbhost=127.0.0.1:3306', '--skip-salts'])
    run(wp + ['core', 'install', '--url=' + env['FFP_TEST_URL'], '--title=File Packs lab',
              '--admin_user=lab', '--admin_password=' + env['FFP_TEST_PASSWORD'],
              '--admin_email=lab@example.test', '--skip-email'])
    run(wp + ['language', 'core', 'install', 'ru_RU'])
    run(wp + ['plugin', 'install', 'forminator', '--version=' + forminator, '--activate'])
    run(wp + ['plugin', 'install', 'plugin-check', '--version=2.1.0', '--activate'])
    package, = (repo / 'dist').glob('*.zip')
    run(wp + ['plugin', 'install', str(package), '--activate'])
    # Installers are finished. Stop vendor background HTTP/email in this lab.
    mu = root / 'wp-content/mu-plugins'
    mu.mkdir(exist_ok=True)
    (mu / 'lab-isolation.php').write_text("<?php add_filter('pre_http_request',static fn()=>new WP_Error('lab','Outbound HTTP disabled'),10,3); add_filter('pre_wp_mail','__return_true');")
    versions = {
        'wordpress': run(wp + ['core', 'version'], capture_output=True, text=True).stdout.strip(),
        'php': run(['php', '-r', 'echo PHP_VERSION;'], capture_output=True, text=True).stdout,
        'plugins': json.loads(run(wp + ['plugin', 'list', '--format=json'], capture_output=True, text=True).stdout),
        'commit': env.get('GITHUB_SHA'),
    }
    assert versions['wordpress'] == wordpress
    assert next(p['version'] for p in versions['plugins'] if p['name'] == 'forminator') == forminator
    (out / 'environment.json').write_text(json.dumps(versions, indent=2))
    # Reconstruct the previous package from its exact git revision for real upgrade tests.
    previous = lab / 'previous.zip'
    with ZipFile(previous, 'w') as archive:
        revision = 'a900ddf7016a6d83a9efb3d1b9ce78351aca0d9c'
        # A shallow checkout does not include the historical source.
        run(['git', 'fetch', '--no-tags', 'origin', revision])
        names = run(['git', 'ls-tree', '-r', '--name-only', revision, '--', 'plugin', 'LICENSE'],
                    capture_output=True, text=True).stdout.splitlines()
        for name in names:
            content = run(['git', 'show', revision + ':' + name], capture_output=True).stdout
            member = 'forminator-file-packs/' + (name[7:] if name.startswith('plugin/') else name)
            archive.writestr(member, content)
    env['FFP_PREVIOUS_ZIP'] = str(previous)
    with (out / 'server.log').open('w') as log:
        server = subprocess.Popen(['php', '-S', '127.0.0.1:8080', '-t', str(root)], env=env, stdout=log, stderr=log)
        for _ in range(100):
            if server.poll() is not None:
                raise RuntimeError('PHP server exited; inspect server.log')
            try:
                with socket.create_connection(('127.0.0.1', 8080), timeout=.2):
                    break
            except OSError:
                time.sleep(.1)
        else:
            raise RuntimeError('PHP server did not start')
        eval_file('seed-lab.php')
        run(['python3', 'tests/http-integration.py'])
        eval_file('security-integration.php')
        eval_file('resilience-integration.php')
        run(['node', 'tests/browser-smoke.cjs'])
        eval_file('json-integration.php')
        eval_file('json-write-failure.php')
        run(['python3', 'tests/extended.py'])
        eval_file('json-capacity.php')
        run(['python3', 'tests/lifecycle.py'])
        run(['python3', 'tests/validate-json.py'])
        result = subprocess.run(wp + ['--require=' + str(root / 'wp-content/plugins/plugin-check/cli.php'),
                                     'plugin', 'check', 'forminator-file-packs', '--format=json'],
                                cwd=repo, env=env, capture_output=True, text=True)
        if result.stderr:
            print(result.stderr, flush=True)
        result.check_returncode()
        # Plugin Check prints a FILE heading followed by a JSON array per file.
        diagnostics = []
        for block in re.split(r'^FILE: .*\n', result.stdout, flags=re.MULTILINE):
            block = block.strip()
            if block:
                diagnostics.extend(json.loads(block))
        (out / 'plugin-check.json').write_text(json.dumps(diagnostics, indent=2))
        assert not any(str(item.get('type', '')).upper() == 'ERROR' for item in diagnostics), 'Plugin Check errors'
        # Summarize actual evidence without claiming a hosting or accessibility audit.
        if env.get('GITHUB_STEP_SUMMARY'):
            with open(env['GITHUB_STEP_SUMMARY'], 'a') as summary:
                summary.write(f"## Verified lab\nWordPress {wordpress}, PHP {versions['php']}, Forminator {forminator}.\n\n")
                for path in sorted(out.glob('*-results.json')):
                    data = json.loads(path.read_text())
                    summary.write(f"- {path.name}: {data.get('count', 'passed')}\n")
                summary.write(f"- Plugin Check: {len(diagnostics)} diagnostics; zero errors.\n\nSynthetic native lab; shared hosting and screen readers remain unverified.\n")
finally:
    if server:
        server.terminate()
        server.wait(timeout=15)
    shutil.rmtree(lab)
