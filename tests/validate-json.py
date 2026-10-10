"""Independent ZIP reader: validate actual JSON against original fixture and other formats."""
import csv, hashlib, io, json, os
from collections import Counter
from pathlib import Path
from zipfile import ZipFile
assert os.environ.get('FFP_TEST_LAB') == '1', 'Disposable lab only'
out = Path(os.environ['FFP_TEST_ARTIFACTS'])
csv.field_size_limit(2 * 1048576)
checks = []
def check(ok, name):
    assert ok, name
    checks.append(name)
    print('PASS '+name, flush=True)
for path in sorted(out.glob('*.zip')):
    with ZipFile(path) as archive:
        raw = archive.read('data.json')
        data = json.loads(raw.decode('utf-8'))
        check(not raw.startswith(b'\xef\xbb\xbf') and data['schema_version']==1, path.name+': UTF-8 JSON without BOM, schema 1')
        check(data['plugin_version']=='0.3.1' and bool(data['timezone']),path.name+': version and site timezone')
        names = archive.namelist()
        rows = data['submissions']
        check(data['submission_count']==len(rows) and len({r['id'] for r in rows})==len(rows),path.name+': unique submissions and count')
        check(data['warnings']==json.loads(archive.read('warnings.json')) and (data['status']=='incomplete')==bool(data['warnings']),path.name+': actual warnings and completeness agree')
        attachments = [f for r in rows for f in r['attachments']]
        check(data['attachment_count']==len(attachments) and data['attachment_bytes']==sum(f['bytes'] for f in attachments),path.name+': actual attachment totals')
        check(all(f['path'] in names and archive.getinfo(f['path']).file_size==f['bytes'] and not f['path'].startswith('/') and '..' not in f['path'].split('/') for f in attachments),path.name+': safe relative paths resolve to real ZIP members')
        check(all(r['card'] in names for r in rows) and len(attachments)==len(set(f['path'] for f in attachments)),path.name+': cards and collision-free attachment references')
        register=list(csv.DictReader(io.StringIO(archive.read('register.csv').decode('utf-8-sig'))))
        check([int(r['entry_id']) for r in register]==[r['id'] for r in rows] and [int(r['attachment_count']) for r in register]==[len(r['attachments']) for r in rows],path.name+': CSV and JSON entry/attachment counts agree')
        check(not any(key in raw.decode() for key in ['"file_path"','"file_url"','"attachment_id"','"fingerprint"','"mtime"','SYNTHETIC-INTERNAL-SENTINEL','SYNTHETIC-ADDON-SENTINEL']),path.name+': no operational upload metadata or internal sentinel values')
        check(archive.testzip() is None,path.name+': valid ZIP CRC')
        if path.name.startswith('repeater-'):
            mode=path.stem.removeprefix('repeater-')
            saved=json.loads((out/('repeater-'+mode+'-metadata-results.json')).read_text())
            check(Counter(f['field'] for f in attachments)==Counter({key:saved['files']//2 for key in saved['persisted_upload_fields']}),path.name+': attachment field references match both real saved repeater keys')
        if path.name=='json-complete.zip':
            expected=json.loads((out/'json-expected.json').read_text())
            fields={f['key']:f['value'] for f in rows[0]['fields']}
            check(fields==expected['values'], 'Structured values, repeated keys, Unicode, quotes, controls and numeric strings match original fixture')
            nested=fields['address-1']['nested']
            check(type(nested['integer']) is int and type(nested['float']) is float and type(nested['zero_float']) is float and nested['false'] is False and nested['null'] is None and nested['empty']==[], 'JSON preserves stored scalar and collection types')
            check(hashlib.sha256(archive.read(attachments[0]['path'])).hexdigest()==expected['attachment_hash'],'JSON attachment reference preserves original bytes')
            check(attachments[0]['field']=='upload-1' and {f['key']:f['type'] for f in rows[0]['fields']}=={'text-1':'text','text-1-2':'text','name-1':'name','checkbox-1':'checkbox','address-1':'address'}, 'Attachment source-field key and current non-upload field types match fixture definitions')
        if path.name=='json-partial.zip':
            check(not attachments and data['status']=='incomplete' and bool(rows[0]['warnings']),'Omission after preview removes reference and updates JSON warnings/status')
        if path.name=='json-metadata-ceiling.zip':
            expected=json.loads((out/'json-capacity-results.json').read_text())
            check(len(rows[0]['fields'])==8 and all(hashlib.sha256(f['value'].encode()).hexdigest()==expected['expected_field_sha256'] for f in rows[0]['fields']), 'Eight 1 MiB fields survive sixfold JSON escaping without truncation')
(out/'json-validation-results.json').write_text(json.dumps({'count':len(checks),'checks':checks,'validator':'Python standard-library ZIP/JSON/CSV reader, fixture values provided separately from production exporter'},indent=2))
