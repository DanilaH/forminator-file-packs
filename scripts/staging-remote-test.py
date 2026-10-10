"""Run the targeted hosting suite from the Actions runner using a remote WP-CLI bridge."""
import base64
import json
import os
import subprocess
from pathlib import Path

assert os.environ.get('FFP_TEST_LAB') == '1'
repo = Path(__file__).resolve().parents[1]
command = json.loads(os.environ['FFP_WP_COMMAND'])
out = Path(os.environ['FFP_TEST_ARTIFACTS'])
out.mkdir(parents=True, exist_ok=True)


def wp(*args):
    return subprocess.run(command + list(args), check=True, capture_output=True, text=True).stdout


wp('eval', "if(file_exists(getenv('FFP_TEST_FIXTURE'))){throw new RuntimeException('Previous staging fixture remains; inspect it before retrying.');}foreach(glob(getenv('FFP_TEST_ARTIFACTS').'/*.zip') as $p){unlink($p);}")
wp('eval-file', '/opt/ffp/tests/seed-lab.php')
wp('eval', "$f=json_decode(file_get_contents(getenv('FFP_TEST_FIXTURE')),true);foreach($f as $r){update_post_meta($r['form'],'_ffp_staging_fixture','1');}")
Path(os.environ['FFP_TEST_FIXTURE']).write_text(wp('eval', "echo file_get_contents(getenv('FFP_TEST_FIXTURE'));"))
try:
    subprocess.run(['python3', str(repo / 'tests/http-integration.py')], check=True)
    content = base64.b64encode(Path(os.environ['FFP_TEST_FIXTURE']).read_bytes()).decode()
    wp('eval', "file_put_contents(getenv('FFP_TEST_FIXTURE'),base64_decode('" + content + "'));")
    for name in ['security-integration.php', 'storage-omissions.php', 'resilience-integration.php']:
        print(wp('eval-file', '/opt/ffp/tests/' + name), flush=True)
    subprocess.run(['node', str(repo / 'tests/browser-smoke.cjs')], check=True)
    for name in ['json-integration.php', 'json-write-failure.php']:
        print(wp('eval-file', '/opt/ffp/tests/' + name), flush=True)
    subprocess.run(['python3', str(repo / 'scripts/staging-extended.py')], check=True)
    # Transfer synthetic exports and independently defined fixture for the Python reader.
    names = wp('eval', "$a=array();foreach(glob(getenv('FFP_TEST_ARTIFACTS').'/*') as $p){if(preg_match('/\\.(zip|json)$/',$p)&&!in_array(basename($p),array('fixture.json','permissions-fixture.json'),true)){$a[]=basename($p);}}echo wp_json_encode($a);")
    artifact_command = json.loads(os.environ['FFP_ARTIFACT_COMMAND'])
    for name in json.loads(names):
        assert Path(name).name == name and name.endswith(('.zip', '.json'))
        with (out / name).open('wb') as output:
            subprocess.run(artifact_command + [name], stdout=output, check=True)
    subprocess.run(['python3', str(repo / 'tests/validate-json.py')], check=True)
finally:
    print(wp('eval-file', '/opt/ffp/tests/staging-cleanup.php'), flush=True)
