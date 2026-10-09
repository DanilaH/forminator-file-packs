"""Require both successful CI workflows for the exact main commit before VPS deployment."""
import json
import os
import time
import urllib.request

assert os.environ['GITHUB_REF'] == 'refs/heads/main', 'Deploy only main'
repo = os.environ['GITHUB_REPOSITORY']
sha = os.environ.get('FFP_DEPLOY_SHA') or os.environ['GITHUB_SHA']
headers = {'Authorization': 'Bearer ' + os.environ['GH_TOKEN'], 'Accept': 'application/vnd.github+json'}


def fetch(path):
    request = urllib.request.Request(f'https://api.github.com/repos/{repo}/{path}', headers=headers)
    with urllib.request.urlopen(request, timeout=30) as response:
        return json.load(response)


deadline = time.monotonic() + int(os.environ.get('FFP_VERIFY_WAIT_SECONDS', '0'))
while True:
    assert fetch('branches/main')['commit']['sha'] == sha, 'Refusing stale deployment: main has moved'
    runs = fetch(f'actions/runs?head_sha={sha}&per_page=100')['workflow_runs']
    pending = []
    for path in ['.github/workflows/verify.yml', '.github/workflows/staging-check.yml']:
        matches = sorted([r for r in runs if r['path'] == path and r['head_sha'] == sha and r['head_branch'] == 'main' and r['event'] in ['push', 'workflow_dispatch']], key=lambda r: r['id'], reverse=True)
        if not matches or matches[0]['status'] != 'completed':
            pending.append(path)
        else:
            assert matches[0]['conclusion'] == 'success', f'Latest exact-commit check must succeed: {path}'
    if not pending:
        break
    assert time.monotonic() < deadline, 'Timed out waiting for exact-commit checks: ' + ', '.join(pending)
    print('Waiting for exact-commit checks: ' + ', '.join(pending), flush=True)
    time.sleep(15)
print('PASS exact main commit: native matrix and Docker staging checks')
