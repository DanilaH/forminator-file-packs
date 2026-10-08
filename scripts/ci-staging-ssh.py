"""Exercise the actual VPS SSH transport against an ephemeral Actions-only SSH daemon."""
import getpass
import os
import socket
import subprocess
import tempfile
import time
from pathlib import Path

assert os.environ.get('GITHUB_ACTIONS') == 'true', 'Ephemeral GitHub runner only'
repo = Path(__file__).resolve().parents[1]
target = Path.home() / 'ffp-staging'
assert not target.exists(), 'Fresh runner destination required'
with tempfile.TemporaryDirectory(prefix='ffp-ssh-ci-') as directory:
    root = Path(directory)
    for name in ['host', 'identity']:
        subprocess.run(['ssh-keygen', '-q', '-t', 'ed25519', '-N', '', '-f', str(root / name)], check=True)
    (root / 'authorized_keys').write_bytes((root / 'identity.pub').read_bytes())
    user = getpass.getuser()
    (root / 'sshd_config').write_text(f'Port 2222\nListenAddress 127.0.0.1\nHostKey {root / "host"}\nPidFile {root / "pid"}\nAuthorizedKeysFile {root / "authorized_keys"}\nPasswordAuthentication no\nKbdInteractiveAuthentication no\nUsePAM no\nAllowUsers {user}\nLogLevel ERROR\n')
    subprocess.run(['sudo', 'mkdir', '-p', '/run/sshd'], check=True)
    log = (root / 'sshd.log').open('w')
    server = subprocess.Popen(['sudo', '/usr/sbin/sshd', '-D', '-e', '-f', str(root / 'sshd_config')], stdout=log, stderr=log)
    try:
        for _ in range(100):
            if server.poll() is not None:
                raise RuntimeError((root / 'sshd.log').read_text())
            try:
                with socket.create_connection(('127.0.0.1', 2222), timeout=.2):
                    break
            except OSError:
                time.sleep(.1)
        else:
            raise RuntimeError('Test SSH daemon not ready')
        env = os.environ.copy()
        env.update(FFP_SSH_HOST='127.0.0.1', FFP_SSH_PORT='2222', FFP_SSH_USER=user,
                   FFP_SSH_PRIVATE_KEY=(root / 'identity').read_text(),
                   FFP_SSH_KNOWN_HOSTS='[127.0.0.1]:2222 ' + (root / 'host.pub').read_text(),
                   FFP_STAGING_URL='', FFP_CADDY_NETWORK='')
        subprocess.run(['python3', 'scripts/vps.py', 'inspect'], cwd=repo, env=env, check=True)
        for _ in range(2):
            subprocess.run(['python3', 'scripts/vps.py', 'deploy'], cwd=repo, env=env, check=True)
        print('PASS SSH host-key verification, transfer, Docker install, HTTP/browser/JSON checks and repeat deployment', flush=True)
    finally:
        if (root / 'pid').exists():
            subprocess.run(['sudo', 'kill', (root / 'pid').read_text().strip()], check=False)
        server.wait(timeout=15)
        log.close()
