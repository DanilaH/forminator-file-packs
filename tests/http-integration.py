"""Exercise real frontend uploads and authenticated admin endpoints in a disposable lab."""
import csv
import io
import json
import os
from pathlib import Path
import re
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser
from http.cookiejar import CookieJar
from zipfile import ZipFile

BASE = os.environ.get('FFP_TEST_URL', 'http://127.0.0.1:8080').rstrip('/')
assert os.environ.get('FFP_TEST_LAB') == '1', 'Disposable lab only: set FFP_TEST_LAB=1.'
FIXTURE = Path(os.environ['FFP_TEST_FIXTURE'])
fixture = json.loads(FIXTURE.read_text())
artifacts = Path(os.environ.get('FFP_TEST_ARTIFACTS', 'artifacts'))
artifacts.mkdir(exist_ok=True, parents=True)
client = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(CookieJar()))
guest = urllib.request.build_opener(urllib.request.ProxyHandler({}))


def get(path, opener=client):
    return opener.open(BASE + path).read().decode()


def post(path, data, files=(), opener=client):
    if files:
        boundary = 'FilePacksSyntheticBoundary'
        parts = [f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode() for k, v in data.items()]
        for field, name, value in files:
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{field}"; filename="{name}"\r\nContent-Type: text/plain\r\n\r\n'.encode() + value + b'\r\n')
        parts.append(f'--{boundary}--\r\n'.encode())
        body, content_type = b''.join(parts), 'multipart/form-data; boundary=' + boundary
    else:
        body, content_type = urllib.parse.urlencode(data).encode(), 'application/x-www-form-urlencoded'
    req = urllib.request.Request(BASE + path, data=body, headers={'Content-Type': content_type})
    try:
        response = opener.open(req)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, response.headers, response.read()


class Inputs(HTMLParser):
    def __init__(self):
        super().__init__()
        self.values = {}

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'input' and a.get('type') == 'hidden' and a.get('name'):
            self.values[a['name']] = a.get('value', '')


checks = []
def check(condition, name):
    assert condition, name
    checks.append(name)
    print('PASS', name, flush=True)


def saved_entry_id(result, form, serial):
    if result.get('data', {}).get('entry_id'):
        return result['data']['entry_id']
    # Older upstream releases do not disclose the saved ID in the frontend response.
    # Resolve only our unique synthetic serial through the authenticated export list.
    lookup = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(CookieJar()))
    get('/wp-login.php', opener=lookup)
    post('/wp-login.php', {'log': os.environ.get('FFP_TEST_USER', 'lab'), 'pwd': os.environ['FFP_TEST_PASSWORD'], 'wp-submit': 'Log In', 'redirect_to': BASE + '/wp-admin/', 'testcookie': '1'}, opener=lookup)
    html = get('/wp-admin/tools.php?page=file-packs-for-forminator', opener=lookup)
    config = json.loads(re.search(r'var FFP = (\{.*?\});', html).group(1))
    status, _, raw = post('/wp-admin/admin-ajax.php', {'action': 'ffp_entries', 'nonce': config['nonce'], 'form_id': form['form']}, opener=lookup)
    rows = json.loads(raw)['data']['entries']
    matches = [row['id'] for row in rows if row['summary'].startswith(serial + ' ·') or row['summary'] == serial]
    assert status == 200 and len(matches) == 1, 'Unique synthetic entry not saved'
    return matches[0]


# Real frontend submissions: native single/multi, AJAX multi, Media Library, and empty uploads.
for mode, form in fixture.items():
    parser = Inputs()
    parser.feed(get(f'/?page_id={form["page"]}'))
    data = parser.values
    data.update({'text-1': 'SN-781-' + mode, 'textarea-1': 'Rear panel damaged. Photograph and invoice attached.'})
    files = [('upload-1', 'оборудование.txt', b'Synthetic equipment record')]
    if mode == 'ajax':
        uploaded = []
        for i in range(2):
            status, headers, raw = post('/wp-admin/admin-ajax.php', {'action': 'forminator_multiple_file_upload', 'form_id': form['form'], 'element_id': 'upload-2', 'nonce': data['forminator_nonce'], 'totalFiles': i + 1}, [('upload-2', 'invoice.txt', f'Synthetic invoice {i}'.encode())])
            result = json.loads(raw)
            check(status == 200 and result.get('success') and (result['data'].get('access_token') or result['data'].get('file_url')), f'{mode}: AJAX upload {i}')
            uploaded.append({**result['data'], 'success': True, 'mime_type': 'text/plain'})
        data['forminator-multifile-hidden'] = json.dumps({'upload-2_synthetic': uploaded})
    else:
        files += [('upload-2[]', 'invoice.txt', b'Synthetic invoice 0'), ('upload-2[]', 'invoice.txt', b'Synthetic invoice 1')]
        data['forminator-multifile-hidden'] = '{}'
    status, headers, raw = post('/wp-admin/admin-ajax.php', data, files)
    result = json.loads(raw)
    if not result.get('success'):
        raise AssertionError(f'{mode}: frontend rejected: {result}')
    check(status == 200, f'{mode}: real frontend submission')
    form['entry'] = saved_entry_id(result, form, 'SN-781-' + mode)
    data['text-1'] = 'EMPTY-' + mode
    data['forminator-multifile-hidden'] = '{}'
    status, headers, raw = post('/wp-admin/admin-ajax.php', data)
    result = json.loads(raw)
    check(result.get('success'), f'{mode}: submission without uploads')
    form['empty_entry'] = saved_entry_id(result, form, 'EMPTY-' + mode)

get('/wp-login.php')
post('/wp-login.php', {'log': os.environ.get('FFP_TEST_USER', 'lab'), 'pwd': os.environ['FFP_TEST_PASSWORD'], 'wp-submit': 'Log In', 'redirect_to': BASE + '/wp-admin/', 'testcookie': '1'})
admin = get('/wp-admin/tools.php?page=file-packs-for-forminator')
match = re.search(r'var FFP = (\{.*?\});', admin)
check(bool(match), 'admin page and script configuration load')
config = json.loads(match.group(1))
nonce = config['nonce']


def api(action, data=None, opener=client):
    return post('/wp-admin/admin-ajax.php', {'action': 'ffp_' + action, 'nonce': nonce, **(data or {})}, opener=opener)


for mode, form in fixture.items():
    params = {'form_id': form['form'], 'ids': json.dumps([form['entry'], form['empty_entry']])}
    status, _, raw = api('preview', params)
    preview = json.loads(raw)['data']
    check(status == 200 and preview['file_count'] == 3 and not preview['warnings'], f'{mode}: preview includes all three files')
    check(b'file_path' not in raw and b'file_url' not in raw and b'/workspace/' not in raw, f'{mode}: preview hides source paths')
    status, headers, archive = api('download', {**params, 'fingerprint': preview['fingerprint']})
    check(status == 200 and headers.get_content_type() == 'application/zip', f'{mode}: authorized ZIP download')
    with ZipFile(io.BytesIO(archive)) as z:
        check(z.testzip() is None and len(z.namelist()) == 9, f'{mode}: ZIP structure and CRC')
        attachments = [name for name in z.namelist() if name.endswith('.txt')]
        check(set(z.read(n) for n in attachments) == {b'Synthetic equipment record', b'Synthetic invoice 0', b'Synthetic invoice 1'}, f'{mode}: original attachment bytes preserved')
        check(len(set(n.casefold() for n in z.namelist())) == len(z.namelist()), f'{mode}: no filename collisions')
        html = z.read(f'Request-{form["entry"]}/request.html').decode()
        check('<script>' not in html and 'Synthetic' not in html, f'{mode}: safe card and attachment links')
        register = list(csv.reader(io.StringIO(z.read('register.csv').decode('utf-8-sig'))))
        check(len(register) == 3 and register[0][-2:] == ['Serial number [text-1]', 'Description [textarea-1]'], f'{mode}: register fields and rows')
    (artifacts / (mode + '-package.zip')).write_bytes(archive)
    status, _, raw = api('download', {**params, 'fingerprint': '0' * 64})
    check(status == 409 and not json.loads(raw)['success'], f'{mode}: stale preview rejected')

params = {'form_id': fixture['submission']['form'], 'ids': json.dumps([fixture['media']['entry']])}
status, _, raw = api('preview', params)
check(status == 400 and not json.loads(raw)['success'], 'entry from another form rejected')
status, _, raw = api('entries', {'form_id': fixture['submission']['form'], 'from': '2026-02-31'})
check(status == 400, 'invalid date rejected')
status, _, raw = post('/wp-admin/admin-ajax.php', {'action': 'ffp_preview', 'nonce': 'invalid', 'form_id': fixture['submission']['form'], 'ids': '[1]'})
check(status == 403, 'invalid nonce rejected')
for action in ['forms', 'entries', 'preview', 'download']:
    status, _, raw = api(action, params, opener=guest)
    check(status != 200 or raw == b'0', f'guest blocked: {action}')
FIXTURE.write_text(json.dumps(fixture, indent=2))
(artifacts / 'http-results.json').write_text(json.dumps({'checks': checks, 'count': len(checks)}, indent=2))
print('HTTP integration checks:', len(checks))
