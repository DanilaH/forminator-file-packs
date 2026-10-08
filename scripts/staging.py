"""Install or exercise ONLY the dedicated, marked ffp-staging Compose project."""
import argparse
import json
import os
import secrets
import subprocess
import time
from pathlib import Path
from urllib.parse import urlparse

repo = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('action', choices=['deploy', 'test', 'wp'])
parser.add_argument('--url', default='http://127.0.0.1:18080')
args, extra = parser.parse_known_args()
url = urlparse(args.url)
if url.scheme not in ['http', 'https'] or not url.hostname or url.username or url.password or url.path not in ['', '/'] or url.query or url.fragment:
    raise SystemExit('Use a site origin URL without credentials, query or path')
state = repo / '.staging-state'
out = repo / 'artifacts/staging'
compose = ['docker', 'compose', '-f', str(repo / 'deploy/staging/compose.yml')]
if os.environ.get('FFP_CADDY_NETWORK'):
    compose += ['-f', str(repo / 'deploy/staging/compose.caddy.yml')]


def run(command, **kwargs):
    return subprocess.run(command, cwd=repo, check=True, **kwargs)


def wp(*command, **kwargs):
    return run(compose + ['exec', '-T', '--user', '33:33',
               '-e', 'FFP_TEST_LAB=1', '-e', 'FFP_TEST_FIXTURE=/opt/ffp/artifacts/fixture.json',
               '-e', 'FFP_STAGING_TEST=1',
               '-e', 'FFP_TEST_ARTIFACTS=/opt/ffp/artifacts', 'wordpress', 'wp', '--path=/var/www/html', *command], **kwargs)


if args.action == 'deploy':
    if state.exists() and not (state / 'owned').is_file():
        raise SystemExit('Existing unmarked state directory; refusing installation')
    state.mkdir(mode=0o700, exist_ok=True)
    (state / 'owned').write_text('ffp-staging-v1\n')
    for name in ['database_password', 'root_password', 'admin_password']:
        path = state / name
        if not path.exists():
            path.write_text(secrets.token_urlsafe(32))
            # The private parent is 0700; individual files are mounted into containers.
            path.chmod(0o644 if name != 'admin_password' else 0o600)
    out.mkdir(parents=True, exist_ok=True)
    out.chmod(0o777)  # Only this private artifact directory is writable by container UID 33.
    # Fixed project names must not collide with a stack belonging to someone else.
    existing = run(['docker', 'ps', '-a', '--filter', 'label=com.docker.compose.project=ffp-staging',
                    '--format', '{{.Names}}'], capture_output=True, text=True).stdout.strip()
    volumes = run(['docker', 'volume', 'ls', '--format', '{{.Name}}'], capture_output=True, text=True).stdout.splitlines()
    if (existing or any(v in volumes for v in ['ffp-staging_database', 'ffp-staging_wordpress'])) and not (state / 'compose-owned').exists():
        raise SystemExit('An unowned ffp-staging stack already exists')
    run(compose + ['config', '--quiet'])
    (state / 'compose-owned').write_text('ffp-staging-v1\n')
    run(compose + ['up', '-d', '--build', '--wait', '--wait-timeout', '180'])
    for _ in range(60):
        probe = subprocess.run(compose + ['exec', '-T', 'wordpress', 'test', '-f', '/var/www/html/wp-config.php'], capture_output=True)
        if probe.returncode == 0:
            break
        time.sleep(1)
    else:
        raise RuntimeError('WordPress configuration not ready')
    installed = subprocess.run(compose + ['exec', '-T', '--user', '33:33', 'wordpress', 'wp', 'core', 'is-installed'], capture_output=True)
    if installed.returncode and (state / 'installed').exists():
        raise RuntimeError('Previously installed staging database is unavailable; refusing reinitialization')
    if installed.returncode:
        # A fresh project/volume only. Feed the admin password through stdin, not command arguments.
        wp('core', 'install', '--url=' + args.url, '--title=File Packs staging', '--admin_user=lab',
           '--admin_email=lab@example.test', '--skip-email', '--prompt=admin_password',
           input=(state / 'admin_password').read_text() + '\n', text=True,
           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    wp('option', 'update', 'home', args.url)
    wp('option', 'update', 'siteurl', args.url)
    wp('option', 'update', 'blog_public', '0')
    wp('plugin', 'install', 'forminator', '--version=1.58.0', '--activate', '--force')
    package, = (repo / 'dist').glob('*.zip')
    wp('plugin', 'install', '/opt/ffp/packages/' + package.name, '--force', '--activate')
    wp('eval', "if (!is_dir(WPMU_PLUGIN_DIR)) { wp_mkdir_p(WPMU_PLUGIN_DIR); } file_put_contents(WPMU_PLUGIN_DIR.'/ffp-staging.php', '<?php add_filter(\"pre_wp_mail\", \"__return_true\");');")
    (state / 'installed').write_text('ffp-staging-v1\n')
    (out / 'environment.json').write_text(json.dumps({
        'core': wp('core', 'version', capture_output=True, text=True).stdout.strip(),
        'plugins': json.loads(wp('plugin', 'list', '--format=json', capture_output=True, text=True).stdout),
        'site_origin': args.url,
    }, indent=2))
elif args.action == 'wp':
    if not (state / 'installed').exists():
        raise SystemExit('Owned staging installation required')
    wp(*(extra[1:] if extra and extra[0] == '--' else extra))
else:
    if not (state / 'installed').exists():
        raise SystemExit('Owned staging installation required')
    if (out / 'fixture.json').exists():
        raise SystemExit('Previous fixture remains; inspect it before starting another test')
    for archive in out.glob('*.zip'):
        archive.unlink()  # Only generated test archives in our dedicated artifact directory.
    env = os.environ.copy()
    env.update(FFP_TEST_LAB='1', FFP_TEST_URL=args.url, FFP_TEST_USER='lab',
               FFP_TEST_PASSWORD=(state / 'admin_password').read_text(),
               FFP_TEST_FIXTURE=str(out / 'fixture.json'), FFP_TEST_ARTIFACTS=str(out))
    wp('eval-file', '/opt/ffp/tests/seed-lab.php')
    wp('eval', "chmod(getenv('FFP_TEST_FIXTURE'),0666);")
    wp('eval', "$f=json_decode(file_get_contents(getenv('FFP_TEST_FIXTURE')),true);foreach($f as $r){update_post_meta($r['form'],'_ffp_staging_fixture','1');}")
    try:
        run(['python3', 'tests/http-integration.py'], env=env)
        wp('eval-file', '/opt/ffp/tests/security-integration.php')
        wp('eval-file', '/opt/ffp/tests/resilience-integration.php')
        run(['node', 'tests/browser-smoke.cjs'], env=env)
        wp('eval-file', '/opt/ffp/tests/json-integration.php')
        wp('eval-file', '/opt/ffp/tests/json-write-failure.php')
        run(['python3', 'tests/validate-json.py'], env=env)
    finally:
        wp('eval-file', '/opt/ffp/tests/staging-cleanup.php')
