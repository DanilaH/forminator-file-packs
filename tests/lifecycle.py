"""Lifecycle and UI verification; disposable local WordPress only."""
import json, os, pathlib, subprocess, time, zipfile
assert os.environ.get('FFP_TEST_LAB') == '1', 'Disposable lab only'
repo=pathlib.Path(__file__).resolve().parents[1]
wp=json.loads(os.environ['FFP_WP_COMMAND'])
assert isinstance(wp,list) and wp and all(isinstance(x,str) for x in wp)
root=pathlib.Path(os.environ['FFP_WP_PATH']).resolve()
output=pathlib.Path(os.environ['FFP_TEST_ARTIFACTS']); output.mkdir(parents=True,exist_ok=True)
version='0.2.0'; package=repo/'dist'/('forminator-file-packs-'+version+'-dev.zip')
def run(c,**kw): return subprocess.run(c,cwd=repo,check=True,**kw)
checks=[]
def check(ok,name):
 if not ok:raise RuntimeError(name)
 checks.append(name);print('PASS '+name,flush=True)
def source():return run(wp+['eval-file',str(repo/'tests/lifecycle-snapshot.php')],stdout=subprocess.PIPE,text=True).stdout
before=source()
# Real shutdown callbacks and real SIGKILL; reclaim only after its job/lock become stale.
for mode in ['normal','fatal','kill']:
 env=os.environ.copy();env['FFP_HOLD_MODE']=mode
 child=subprocess.Popen(wp+['eval-file',str(repo/'tests/held-export.php')],cwd=repo,env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
 archive=pathlib.Path(child.stdout.readline().strip());check(archive.is_file(),mode+': private archive built')
 if mode=='kill':
  child.kill();child.wait(timeout=10);check(archive.exists(),'SIGKILL leaves only private abandoned job')
  os.utime(archive.parent,(time.time()-3700,)*2);os.utime(archive.parent.parent/'lock-1',(time.time()-3700,)*2)
  run(wp+['eval',"wp_set_current_user(1); $f=json_decode(file_get_contents(getenv('FFP_TEST_FIXTURE')),true); $p=new \\ForminatorFilePacks\\Package(\\ForminatorFilePacks\\Planner::build($f['submission']['form'],[$f['submission']['entry']])); $p->cleanup();"])
 else:child.wait(timeout=10)
 check(not archive.exists(),mode+': archive cleaned')
check(before==source(),'source intact after process shutdown failures')
# Upgrade from recorded 0.1.0 build to 0.2.0 via the actual WordPress ZIP installer.
previous=os.environ.get('FFP_PREVIOUS_ZIP')
for archive_path in ([pathlib.Path(previous)] if previous else []) + [package]:
 with zipfile.ZipFile(archive_path) as z:
  expected=z.read('forminator-file-packs/forminator-file-packs.php').decode().split('Version:')[1].split()[0]
 run(wp+['plugin','install',str(archive_path),'--force','--activate'])
 check(run(wp+['plugin','get','forminator-file-packs','--field=version'],stdout=subprocess.PIPE,text=True).stdout.strip()==expected,'installed version verified '+expected)
 check(before==source(),'source intact after installing '+expected)
# Only delete the installed lab copy, never the development checkout.
target=root/'wp-content/plugins/forminator-file-packs';assert target.is_dir() and not target.is_symlink()
run(wp+['plugin','uninstall','forminator-file-packs','--deactivate'])
check(not (target/'forminator-file-packs.php').exists(),'plugin files actually removed')
check(before==source(),'source intact after plugin uninstall')
run(wp+['plugin','install',str(package),'--activate'])
check(before==source(),'source intact after reinstall')
run(wp+['eval',"wp_set_current_user(1);$f=json_decode(file_get_contents(getenv('FFP_TEST_FIXTURE')),true);$id=Forminator_API::add_form_entry($f['submission']['form'],[['name'=>'upload-1','value'=>['file'=>['file_path'=>forminator_get_upload_path($f['submission']['form'],'uploads').'/missing-ui.txt','file_url'=>'']]]]);$f['submission']['broken_entry']=$id;file_put_contents(getenv('FFP_TEST_FIXTURE'),wp_json_encode($f));update_user_meta(1,'locale','ru_RU');"])
try:
 run(['node',str(repo/'tests/browser-states.cjs')])
 with zipfile.ZipFile(output/'partial-ru.zip') as z:
  check('Неполный пакет' in z.read('index.html').decode(),'Russian warning inside real partial ZIP')
 run(wp+['plugin','deactivate','forminator'])
 run(['node',str(repo/'tests/browser-states.cjs')],env={**os.environ,'FFP_EXPECT_DEPENDENCY':'1'})
finally:
 run(wp+['plugin','activate','forminator'])
 run(wp+['eval',"update_user_meta(1,'locale','en_US');"])
(output/'lifecycle-results.json').write_text(json.dumps({'checks':checks,'count':len(checks),'previous_zip_checked':bool(previous)},indent=2))
