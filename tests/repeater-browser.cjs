/* Real native repeater UI upload; synthetic data only. */
const {chromium}=require(process.env.FFP_PLAYWRIGHT_MODULE||'playwright');
const fs=require('node:fs');
if(process.env.FFP_TEST_LAB!=='1')throw Error('Disposable lab only');
(async()=>{
 const fixture=JSON.parse(fs.readFileSync(process.env.FFP_REPEATER_FIXTURE,'utf8'));
 const browser=await chromium.launch({headless:true,executablePath:process.env.FFP_CHROMIUM_PATH||undefined});
 try{
  const page=await browser.newPage();const base=process.env.FFP_TEST_URL||'http://127.0.0.1:8080';
  await page.goto(base+'/?page_id='+fixture.page);
  await page.locator('input[name="text-1"]').fill('REPEATER-TEST');
  await page.locator('.forminator-repeater-add').click();
  const uploads=page.locator('input[type=file]');
  if(await uploads.count()!==2)throw Error('Expected two actual repeater upload inputs');
  const expected=[];
  for(let i=0;i<2;i++){
   const files=Array.from({length:fixture.files_per_row||1},(_,j)=>({name:'document'+j+'.txt',mimeType:'text/plain',buffer:Buffer.from('Repeat document '+i+' file '+j)}));
   expected.push(...files.map(f=>f.buffer.toString()));
   await uploads.nth(i).setInputFiles(files);
   if(fixture.mode.startsWith('ajax'))await page.waitForFunction(()=>document.querySelectorAll('.forminator-uploaded-file').length>0&&!document.querySelector('.forminator-loading,.forminator-has_error')&&!document.querySelector('button.forminator-button-submit[data-uploading="true"]'));
  }
  const response=page.waitForResponse(async r=>{
   if(!r.url().includes('admin-ajax.php')||r.request().method()!=='POST')return false;
   const result=await r.json();return typeof result.data==='object'&&!result.data?.access_token;
  });
  await page.locator('button.forminator-button-submit').click();
  const result=await(await response).json();if(!result.success)throw Error(JSON.stringify(result));
  await page.goto(base+'/wp-login.php');await page.locator('#user_login').fill(process.env.FFP_TEST_USER||'lab');await page.locator('#user_pass').fill(process.env.FFP_TEST_PASSWORD);
  await Promise.all([page.waitForURL(/wp-admin/),page.locator('#wp-submit').click()]);
  await page.goto(base+'/wp-admin/tools.php?page=forminator-file-packs');
  // Older Forminator responses omit entry_id. Require exactly one saved entry
  // with our marker in this freshly seeded form, rather than guessing an ID.
  const saved=await page.evaluate(async form=>{
   const response=await fetch(FFP.url,{method:'POST',credentials:'same-origin',body:new URLSearchParams({action:'ffp_entries',nonce:FFP.nonce,form_id:form})});
   const result=await response.json();if(!response.ok||!result.success)throw Error(JSON.stringify(result));
   return result.data.entries.filter(row=>row.summary==='REPEATER-TEST'||row.summary.startsWith('REPEATER-TEST ·')).map(row=>row.id);
  },fixture.form);
  if(saved.length!==1||(result.data.entry_id&&Number(result.data.entry_id)!==saved[0]))throw Error('Expected one matching persisted repeater submission');
  fixture.entry=saved[0];fs.writeFileSync(process.env.FFP_REPEATER_FIXTURE,JSON.stringify(fixture));
  const outcome=await page.evaluate(async f=>{
   const request=async(action,extra)=>fetch(FFP.url,{method:'POST',credentials:'same-origin',body:new URLSearchParams({action:'ffp_'+action,nonce:FFP.nonce,form_id:f.form,ids:JSON.stringify([f.entry]),...extra})});
   const result=await(await request('preview',{})).json();if(!result.success)throw Error(JSON.stringify(result));
   const p=result.data;if(p.file_count!==2*(f.files_per_row||1)||p.warnings.length)throw Error(JSON.stringify(p));
   const zip=await request('download',{fingerprint:p.fingerprint});if(!zip.ok)throw Error(await zip.text());
   return {preview:p,zip:Array.from(new Uint8Array(await zip.arrayBuffer()))};
  },fixture);
  const prefix=process.env.FFP_TEST_ARTIFACTS+'/repeater-'+fixture.mode;
  fs.writeFileSync(prefix+'.zip',Buffer.from(outcome.zip));
  const {execFileSync}=require('node:child_process');
  execFileSync('python3',['-c','import sys,json,zipfile; z=zipfile.ZipFile(sys.argv[1]); names=[n for n in z.namelist() if n.endswith(".txt")]; assert len(names)==len(set(names))==len(json.loads(sys.argv[2])); assert sorted(z.read(n).decode() for n in names)==sorted(json.loads(sys.argv[2])); assert z.testzip() is None',prefix+'.zip',JSON.stringify(expected)]);
  fs.writeFileSync(prefix+'-results.json',JSON.stringify({mode:fixture.mode,real_frontend:true,files:expected.length,warnings:0,unique_members:true,exact_bytes:true}));
  console.log('PASS real frontend repeater '+fixture.mode+': add row, uploads, preview, ZIP unique members and exact bytes');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
