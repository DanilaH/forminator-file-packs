"""Extended synthetic checks on the owned staging site via its WP-CLI bridge."""
import base64
import json
import os
import subprocess
import zipfile
from pathlib import Path

assert os.environ.get('FFP_TEST_LAB') == '1'
repo = Path(__file__).resolve().parents[1]
out = Path(os.environ['FFP_TEST_ARTIFACTS'])
command = json.loads(os.environ['FFP_WP_COMMAND'])


def wp(*args, env=None):
    result = subprocess.run(command + list(args), env=env, capture_output=True, text=True)
    if result.returncode:
        print(result.stderr, flush=True)
        result.check_returncode()
    return result.stdout


def read_fixture(name):
    (out / name).write_text(wp('eval', "echo file_get_contents(getenv('FFP_TEST_ARTIFACTS').'/' . '" + name + "');"))


def sync_fixture(name, env):
    encoded = base64.b64encode((out / name).read_bytes()).decode()
    wp('eval', "file_put_contents(getenv('FFP_REPEATER_FIXTURE'),base64_decode('" + encoded + "'));", env=env)


for mode in ['single', 'single-media', 'multiple', 'multiple-media', 'ajax', 'ajax-media']:
    name = 'repeater-' + mode + '-fixture.json'
    env = {**os.environ, 'FFP_REPEATER_MODE': mode, 'FFP_REPEATER_FIXTURE': str(out / name)}
    wp('eval-file', '/opt/ffp/tests/seed-repeater.php', env=env)
    read_fixture(name)
    subprocess.run(['node', str(repo / 'tests/repeater-browser.cjs')], env=env, check=True)
    sync_fixture(name, env)
    print(wp('eval-file', '/opt/ffp/tests/repeater-metadata.php', env=env), flush=True)

print(wp('eval-file', '/opt/ffp/tests/capacity-integration.php'), flush=True)
read_fixture('capacity-fixture.json')
before = wp('eval-file', '/opt/ffp/tests/lifecycle-snapshot.php').strip()
subprocess.run(['node', str(repo / 'tests/capacity-browser.cjs')], check=True)
subprocess.run(['python3', str(repo / 'tests/permissions-http.py')],
               env={**os.environ, 'FFP_PERMISSIONS_FIXTURE': str(out / 'permissions-fixture.json')}, check=True)
assert before == wp('eval-file', '/opt/ffp/tests/lifecycle-snapshot.php').strip() and len(before) == 64
(out / 'extended-integrity-results.json').write_text(json.dumps({'source_snapshot_unchanged': True}))
print('PASS source forms, entries, metadata and upload hashes unchanged after capacity/permissions HTTP checks', flush=True)
print(wp('eval-file', '/opt/ffp/tests/json-capacity.php'), flush=True)
print(wp('eval-file', '/opt/ffp/tests/staging-shutdown.php'), flush=True)

before = wp('eval-file', '/opt/ffp/tests/lifecycle-snapshot.php').strip()
package, = (repo / 'dist').glob('*.zip')
checks = []


def intact(name):
    assert before == wp('eval-file', '/opt/ffp/tests/lifecycle-snapshot.php').strip(), name
    checks.append(name)
    print('PASS ' + name, flush=True)


try:
    wp('plugin', 'uninstall', 'forminator-file-packs', '--deactivate')
    removed = wp('eval', "echo file_exists(WP_PLUGIN_DIR.'/forminator-file-packs/forminator-file-packs.php')?'present':'absent';").strip()
    assert removed == 'absent', 'Installed staging plugin files must actually be removed'
    intact('source intact after actual plugin uninstall')
finally:
    wp('plugin', 'install', '/opt/ffp/packages/' + package.name, '--force', '--activate')
intact('source intact after actual plugin reinstall')
try:
    wp('eval', "wp_set_current_user(1);$f=json_decode(file_get_contents(getenv('FFP_TEST_FIXTURE')),true);$id=Forminator_API::add_form_entry($f['submission']['form'],[['name'=>'upload-1','value'=>['file'=>['file_path'=>forminator_get_upload_path($f['submission']['form'],'uploads').'/missing-ui.txt','file_url'=>'']]]]);$f['submission']['broken_entry']=$id;file_put_contents(getenv('FFP_TEST_FIXTURE'),wp_json_encode($f));update_user_meta(1,'locale','ru_RU');")
    read_fixture('fixture.json')
    subprocess.run(['node', str(repo / 'tests/browser-states.cjs')], check=True)
    with zipfile.ZipFile(out / 'partial-ru.zip') as archive:
        assert 'Неполный пакет' in archive.read('index.html').decode()
    checks.append('Russian incomplete-package label in actual downloaded ZIP')
    wp('plugin', 'deactivate', 'forminator')
    subprocess.run(['node', str(repo / 'tests/browser-states.cjs')],
                   env={**os.environ, 'FFP_EXPECT_DEPENDENCY': '1'}, check=True)
finally:
    wp('plugin', 'activate', 'forminator')
    wp('eval', "update_user_meta(1,'locale','en_US');")
(out / 'staging-lifecycle-results.json').write_text(json.dumps({'checks': checks, 'count': len(checks),
    'scope': 'Real uninstall/reinstall, dependency absence, Russian UI; historical upgrade covered by native CI'}, indent=2))
