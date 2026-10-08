"""Remaining lab checks; creates synthetic data, never use on a production installation."""
import json, os, subprocess
from pathlib import Path
assert os.environ.get('FFP_TEST_LAB') == '1', 'Disposable lab only'
repo = Path(__file__).resolve().parent.parent
wp = json.loads(os.environ['FFP_WP_COMMAND'])
out = Path(os.environ['FFP_TEST_ARTIFACTS']).resolve()
out.mkdir(parents=True, exist_ok=True)
def run(args, **kwargs):
    return subprocess.run(args, cwd=repo, check=True, **kwargs)
for mode in ['single', 'single-media', 'multiple', 'multiple-media', 'ajax', 'ajax-media']:
    env = {**os.environ, 'FFP_REPEATER_MODE':mode, 'FFP_REPEATER_FIXTURE':str(out/('repeater-'+mode+'-fixture.json'))}
    run(wp+['eval-file', str(repo/'tests/seed-repeater.php')], env=env)
    run(['node', str(repo/'tests/repeater-browser.cjs')], env=env)
    run(wp+['eval-file', str(repo/'tests/repeater-metadata.php')], env=env)
run(wp+['eval-file', str(repo/'tests/capacity-integration.php')])
before = run(wp+['eval-file', str(repo/'tests/lifecycle-snapshot.php')], capture_output=True, text=True).stdout.strip()
run(['node', str(repo/'tests/capacity-browser.cjs')])
run(['python3', str(repo/'tests/permissions-http.py')], env={**os.environ,'FFP_PERMISSIONS_FIXTURE':str(out/'permissions-fixture.json')})
after = run(wp+['eval-file', str(repo/'tests/lifecycle-snapshot.php')], capture_output=True, text=True).stdout.strip()
assert before == after and len(before) == 64, 'Source records or attachments changed during capacity/permissions HTTP checks'
(out/'extended-integrity-results.json').write_text(json.dumps({'source_snapshot_unchanged':True,'scope':'All Forminator forms/meta, entry/meta tables and upload-file hashes before/after capacity browser and permissions HTTP tests'},indent=2))
print('PASS original forms, submissions, metadata and uploads unchanged after capacity and permissions HTTP checks', flush=True)
