"""SSH transport for a dedicated staging directory; never reads existing container environments."""
import argparse
import io
import json
import os
import re
import shlex
import subprocess
import sys
import tarfile
import tempfile
from pathlib import Path

repo = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('action', choices=['inspect', 'deploy', 'wp'])
args, extra = parser.parse_known_args()
host, user, port = [os.environ.get(n, '') for n in ['FFP_SSH_HOST', 'FFP_SSH_USER', 'FFP_SSH_PORT']]
port = port or '22'
assert re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.:-]*', host), 'Set FFP_SSH_HOST'
assert re.fullmatch(r'[A-Za-z_][A-Za-z0-9_-]*', user), 'Set FFP_SSH_USER'
assert port.isdigit() and 1 <= int(port) <= 65535, 'Invalid SSH port'
network = os.environ.get('FFP_CADDY_NETWORK', '')
assert not network or re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_.-]*', network), 'Invalid Docker network name'
temporary = None
if os.environ.get('FFP_SSH_CONFIG'):
    config = Path(os.environ['FFP_SSH_CONFIG'])
else:
    temporary = tempfile.TemporaryDirectory(prefix='ffp-ssh-')
    root = Path(temporary.name)
    for name, variable in [('identity', 'FFP_SSH_PRIVATE_KEY'), ('known_hosts', 'FFP_SSH_KNOWN_HOSTS')]:
        value = os.environ.get(variable, '')
        assert value.strip(), f'Set {variable}'
        (root / name).write_text(value.rstrip() + '\n')
        (root / name).chmod(0o600)
    config = root / 'config'
    config.write_text(f'Host ffp-target\n HostName {host}\n User {user}\n Port {port}\n IdentityFile {root / "identity"}\n UserKnownHostsFile {root / "known_hosts"}\n StrictHostKeyChecking yes\n IdentitiesOnly yes\n BatchMode yes\n ConnectTimeout 20\n ServerAliveInterval 15\n ServerAliveCountMax 4\n')
    config.chmod(0o600)
ssh = ['ssh', '-F', str(config), 'ffp-target']
prefix = 'cd "$HOME/ffp-staging" && '
if network:
    prefix += 'export FFP_CADDY_NETWORK=' + shlex.quote(network) + ' && '
try:
    if args.action == 'wp':
        command = extra[1:] if extra and extra[0] == '--' else extra
        subprocess.run(ssh + [prefix + 'python3 scripts/staging.py wp -- ' + shlex.join(command)], check=True)
    elif args.action == 'inspect':
        # No full docker inspect, container env, Caddy files, users or credentials.
        script = 'set -eu; uname -sr; docker version --format "{{.Server.Version}}"; docker compose version; docker ps --format "table {{.Names}}\\t{{.Image}}\\t{{.Ports}}"; docker network ls; df -h /; free -m; command -v python3; command -v caddy || true; ss -ltn | head -30'
        subprocess.run(ssh + [script], check=True)
    else:
        archive = io.BytesIO()
        with tarfile.open(fileobj=archive, mode='w:gz') as bundle:
            for folder in ['deploy/staging', 'scripts', 'tests', 'dist']:
                for path in sorted((repo / folder).rglob('*')):
                    if path.is_file() and not path.is_symlink() and '__pycache__' not in path.parts:
                        bundle.add(path, arcname=str(path.relative_to(repo)))
        prepare = 'set -eu; umask 077; task_root="$HOME/ffp-staging"; if [ -e "$task_root" ] && [ ! -f "$task_root/.ffp-staging-owned" ]; then echo "Unmarked destination; refusing deployment" >&2; exit 1; fi; mkdir -p "$task_root"; cd "$task_root"; touch .ffp-staging-owned; flock -n .deploy.lock sh -c \'tar -xzf - && chmod -R a+rX tests dist deploy/staging\''
        subprocess.run(ssh + [prepare], input=archive.getvalue(), check=True)
        origin = os.environ.get('FFP_STAGING_URL') or 'http://127.0.0.1:18080'
        subprocess.run(ssh + [prefix + 'flock -n .deploy.lock python3 scripts/staging.py deploy --url ' + shlex.quote(origin)], check=True)
        password = subprocess.check_output(ssh + ['cat "$HOME/ffp-staging/.staging-state/admin_password"'], text=True).strip()
        print('::add-mask::' + password, flush=True)
        out = repo / 'artifacts/staging'
        out.mkdir(parents=True, exist_ok=True)
        env = os.environ.copy()
        env.update(FFP_SSH_CONFIG=str(config), FFP_TEST_LAB='1', FFP_TEST_USER='lab', FFP_TEST_PASSWORD=password,
                   FFP_TEST_URL=origin, FFP_TEST_ARTIFACTS=str(out), FFP_TEST_FIXTURE=str(out / 'fixture.json'),
                   FFP_WP_COMMAND=json.dumps([sys.executable, str(repo / 'scripts/vps.py'), 'wp', '--']))
        tunnel = None
        try:
            if origin == 'http://127.0.0.1:18080':
                tunnel = subprocess.Popen(ssh[:-1] + ['-o', 'ExitOnForwardFailure=yes', '-N', '-L', '127.0.0.1:18080:127.0.0.1:18080', 'ffp-target'])
                import socket
                import time
                for _ in range(100):
                    if tunnel.poll() is not None:
                        raise RuntimeError('SSH tunnel failed')
                    try:
                        with socket.create_connection(('127.0.0.1', 18080), timeout=.2):
                            break
                    except OSError:
                        time.sleep(.1)
                else:
                    raise RuntimeError('SSH tunnel not ready')
            subprocess.run([sys.executable, str(repo / 'scripts/staging-remote-test.py')], env=env, cwd=repo, check=True)
        finally:
            if tunnel:
                tunnel.terminate()
                tunnel.wait(timeout=15)
finally:
    if temporary:
        temporary.cleanup()
