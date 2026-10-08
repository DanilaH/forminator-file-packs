"""Require both successful CI workflows for the exact main commit before VPS deployment."""
import json
import os
import urllib.request

assert os.environ['GITHUB_REF'] == 'refs/heads/main', 'Deploy only main'
repo = os.environ['GITHUB_REPOSITORY']
sha = os.environ['GITHUB_SHA']
url = f'https://api.github.com/repos/{repo}/actions/runs?head_sha={sha}&per_page=100'
request = urllib.request.Request(url, headers={'Authorization': 'Bearer ' + os.environ['GH_TOKEN'], 'Accept': 'application/vnd.github+json'})
with urllib.request.urlopen(request, timeout=30) as response:
    runs = json.load(response)['workflow_runs']
for path in ['.github/workflows/verify.yml', '.github/workflows/staging-check.yml']:
    matches = sorted([r for r in runs if r['path'] == path and r['head_sha'] == sha and r['head_branch'] == 'main'], key=lambda r: r['id'], reverse=True)
    assert matches and matches[0]['status'] == 'completed' and matches[0]['conclusion'] == 'success', f'Latest exact-commit check must succeed: {path}'
print('PASS exact main commit: native matrix and Docker staging checks')
