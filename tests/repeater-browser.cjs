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
  for(let i=0;i<2;i++)await uploads.nth(i).setInputFiles({name:'document.txt',mimeType:'text/plain',buffer:Buffer.from('Repeat document '+i)});
  const response=page.waitForResponse(async r=>r.url().includes('admin-ajax.php')&&r.request().method()==='POST'&&typeof(await r.json()).data==='object');
  await page.locator('button.forminator-button-submit').click();
  const result=await(await response).json();if(!result.success||!result.data.entry_id)throw Error(JSON.stringify(result));
  fixture.entry=result.data.entry_id;fs.writeFileSync(process.env.FFP_REPEATER_FIXTURE,JSON.stringify(fixture));
  await page.goto(base+'/wp-login.php');await page.locator('#user_login').fill(process.env.FFP_TEST_USER||'lab');await page.locator('#user_pass').fill(process.env.FFP_TEST_PASSWORD);
  await Promise.all([page.waitForURL(/wp-admin/),page.locator('#wp-submit').click()]);
  await page.goto(base+'/wp-admin/tools.php?page=forminator-file-packs');
  const outcome=await page.evaluate(async f=>{
   const request=async(action,extra)=>fetch(FFP.url,{method:'POST',credentials:'same-origin',body:new URLSearchParams({action:'ffp_'+action,nonce:FFP.nonce,form_id:f.form,ids:JSON.stringify([f.entry]),...extra})});
   const result=await(await request('preview',{})).json();if(!result.success)throw Error(JSON.stringify(result));
   const p=result.data;if(p.file_count!==2||p.warnings.length)throw Error(JSON.stringify(p));
   const zip=await request('download',{fingerprint:p.fingerprint});if(!zip.ok)throw Error(await zip.text());
   return {preview:p,zip:Array.from(new Uint8Array(await zip.arrayBuffer()))};
  },fixture);
  fs.writeFileSync(process.env.FFP_TEST_ARTIFACTS+'/repeater-package.zip',Buffer.from(outcome.zip));
  fs.writeFileSync(process.env.FFP_TEST_ARTIFACTS+'/repeater-results.json',JSON.stringify({real_frontend:true,files:2,warnings:0}));
  console.log('PASS real frontend repeater: add row, two uploads, preview, ZIP');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
