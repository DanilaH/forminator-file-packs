"""Actual authenticated HTTP endpoints with custom permissions and existing-session revocation."""
import json, os, re, subprocess, urllib.request, urllib.parse, urllib.error
from http.cookiejar import CookieJar
from pathlib import Path
assert os.environ.get('FFP_TEST_LAB') == '1', 'Disposable lab only'
wp = json.loads(os.environ['FFP_WP_COMMAND'])
repo = Path(__file__).resolve().parent.parent
base = os.environ.get('FFP_TEST_URL', 'http://127.0.0.1:8080')
fixture = json.loads(Path(os.environ['FFP_TEST_FIXTURE']).read_text())['submission']
client = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(CookieJar()))
checks = []
def configure(mode):
    subprocess.run(wp+['eval-file', str(repo/'tests/permissions-fixture.php')], env={**os.environ, 'FFP_PERMISSIONS_MODE': mode}, check=True)
def post(path, data):
    try: response=client.open(base+path, urllib.parse.urlencode(data).encode())
    except urllib.error.HTTPError as error: response=error
    return response.status, response.headers, response.read()
def check(ok, name):
    assert ok, name
    checks.append(name)
    print('PASS '+name, flush=True)
def nonce():
    html=client.open(base+'/wp-admin/tools.php?page=file-packs-for-forminator').read().decode()
    return json.loads(re.search(r'var FFP = (\{.*?\});', html).group(1))['nonce']
def endpoint(action, token, extra=None):
    return post('/wp-admin/admin-ajax.php', {'action':'ffp_'+action, 'nonce':token, 'form_id':fixture['form'], 'ids':json.dumps([fixture['entry']]), **(extra or {})})
configure('setup')
try:
    configure('specific')
    client.open(base+'/wp-login.php').read()
    post('/wp-login.php', {'log':'ffp-http-reader', 'pwd':os.environ['FFP_TEST_PASSWORD'], 'redirect_to':base+'/wp-admin/', 'testcookie':'1'})
    token=nonce()
    for mode in ['specific', 'role']:
        configure(mode)
        for action in ['forms','entries','preview']:
            status, _, raw=endpoint(action, token)
            check(status==200 and json.loads(raw)['success'], mode+': '+action+' allowed via HTTP')
        preview=json.loads(endpoint('preview',token)[2])['data']
        status, headers, raw=endpoint('download',token, {'fingerprint':preview['fingerprint']})
        check(status==200 and headers.get_content_type()=='application/zip' and raw[:2]==b'PK',mode+': actual ZIP download allowed')
    for mode in ['revoked','excluded']:
        configure(mode)
        for action in ['forms','entries','preview','download']:
            status, headers, raw=endpoint(action,token,{'fingerprint':preview['fingerprint']})
            data=json.loads(raw)
            check(status==400 and not data['success'] and 'permission' in data['data']['message'].lower() and headers.get_content_type()!='application/zip',mode+': '+action+' denied with existing session and nonce')
    configure('specific')
    check(json.loads(endpoint('preview',token)[2])['success'],'Restored permission permits retry in same session')
finally:
    configure('cleanup')
Path(os.environ['FFP_TEST_ARTIFACTS']+'/permissions-http-results.json').write_text(json.dumps({'count':len(checks),'checks':checks,'scope':'Between-request revocation with an existing authenticated session; concurrent mid-request revocation is not tested'},indent=2))
