/* Real large export: server selection, restore, parts, repeated streaming downloads. */
const {chromium}=require(process.env.FFP_PLAYWRIGHT_MODULE || 'playwright');
const fs=require('node:fs'); const path=require('node:path'); const {execFileSync}=require('node:child_process');
if(process.env.FFP_TEST_LAB!=='1') throw new Error('Disposable lab only');
(async()=>{
 const f=JSON.parse(fs.readFileSync(process.env.FFP_TEST_FIXTURE));const out=process.env.FFP_TEST_ARTIFACTS;const base=process.env.FFP_TEST_URL||'http://127.0.0.1:8080';
 const command=JSON.parse(process.env.FFP_WP_COMMAND);const setLocale=locale=>execFileSync(command[0],[...command.slice(1),'eval',`update_user_meta(1,'locale','${locale}');`]);
 const browser=await chromium.launch({headless:true}); const checks=[];const check=(ok,n)=>{if(!ok)throw new Error(n);checks.push(n);console.log('PASS '+n);};
 try{
  const page=await browser.newPage({viewport:{width:1360,height:1000},acceptDownloads:true});page.setDefaultTimeout(120000);const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/wp-login.php');await page.locator('#user_login').fill(process.env.FFP_TEST_USER||'lab');await page.locator('#user_pass').fill(process.env.FFP_TEST_PASSWORD);await Promise.all([page.waitForURL(/wp-admin/),page.locator('#wp-submit').click()]);
  const url=base+'/wp-admin/tools.php?page=file-packs-for-forminator';await page.goto(url);await page.locator('#ffp-form option[value="'+f.large.form+'"]').waitFor({state:'attached'});await page.waitForFunction(()=>document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');
  await page.locator('#ffp-form').selectOption(String(f.large.form));await page.locator('#ffp-rows input').first().waitFor();await page.waitForFunction(()=>document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');
  await page.locator('#ffp-select-period').click();check((await page.locator('#ffp-count').getAttribute('data-count'))==='101','Explicitly selects all 101 period results without loading all IDs in the browser');
  await page.locator('#ffp-preview-button').click();await page.locator('#ffp-job:not([hidden])').waitFor();
  await page.reload();await page.locator('#ffp-job-review:not([hidden])').waitFor();await page.waitForFunction(()=>document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');
  check((await page.locator('#ffp-job-totals').textContent()).includes('101'),'Task and checked counts restored after a page reload');check((await page.locator('#ffp-job-heading').textContent()).includes('Заявки на ремонт · 101 заявка'),'Restored task identifies its own form independently of the current selector');await page.waitForFunction(()=>document.querySelectorAll('#ffp-job-review-list li').length===10);check(await page.locator('#ffp-job-review-list li').count()===10,'Checked submission review paginated');
  await page.locator('#ffp-job-next').click();await page.waitForFunction(()=>document.querySelector('#ffp-job-page').textContent.includes('2'));check((await page.locator('#ffp-job-page').textContent()).includes('2'),'Review page controls work');
  await page.locator('#ffp-job').screenshot({path:path.join(out,'large-review.png'),style:'#wpadminbar{visibility:hidden}'});
  await page.locator('#ffp-job-start').click();await page.locator('#ffp-job-result:not([hidden])').waitFor();
  await page.waitForFunction(()=>document.querySelectorAll('#ffp-job-parts li').length===2);check(await page.locator('#ffp-job-parts li').count()===2,'101 actual submissions become two independently downloadable ZIP parts');
  await page.locator('#ffp-job').screenshot({path:path.join(out,'large-ready-desktop.png'),style:'#wpadminbar{visibility:hidden}'});
  const d1=page.waitForEvent('download');await page.locator('#ffp-job-parts button').first().click();const a=await d1;await a.saveAs(path.join(out,'large-browser.zip'));check(!await a.failure(),'Actual POST streaming download without browser Blob');
  const d2=page.waitForEvent('download');await page.locator('#ffp-job-parts button').first().click();const b=await d2;await b.saveAs(path.join(out,'large-browser-repeat.zip'));check(fs.readFileSync(path.join(out,'large-browser.zip')).equals(fs.readFileSync(path.join(out,'large-browser-repeat.zip'))),'Repeated download returns the same private archive bytes');
  execFileSync('python3',['-c','import zipfile,json,sys,hashlib;z=zipfile.ZipFile(sys.argv[1]);assert z.testzip() is None;d=json.loads(z.read("data.json"));assert d["submission_count"]==100 and d["attachment_count"]==100;assert all(hashlib.sha256(z.read(f["path"])).hexdigest()==sys.argv[2] for r in d["submissions"] for f in r["attachments"])',path.join(out,'large-browser.zip'),f.large.hash]);check(true,'Independent Python CRC, JSON totals and actual attachment bytes');
  setLocale('ru_RU');await page.reload();await page.locator('#ffp-job-result:not([hidden])').waitFor();check((await page.locator('#ffp-job-status').textContent()).includes('Архивы ZIP готовы'),'Russian large-export labels restore with the completed task');check(!/AM|PM/.test(await page.locator('#ffp-job-expiry').textContent()),'Expiry date follows the Russian interface locale');await page.locator('#ffp-job').screenshot({path:path.join(out,'large-ready-desktop.png'),style:'#wpadminbar{visibility:hidden}'});
  await page.setViewportSize({width:390,height:844});await page.locator('#ffp-job').screenshot({path:path.join(out,'large-ready-mobile.png'),style:'#wpadminbar{visibility:hidden}'});check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Large-export result fits a 390px phone');
  await page.locator('#ffp-job-cancel').click();await page.waitForFunction(()=>document.querySelector('#ffp-job-status').textContent.includes(FFP.strings.cancelledJob));check(await page.locator('#ffp-job-parts li').count()===0,'Cancellation removes download controls immediately');
  await page.reload();await page.waitForFunction(()=>document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');check(!errors.length,'No JavaScript exceptions during creation, restoration, review, build or downloads');

  await page.locator('#ffp-form').selectOption(String(f.large_partial.form));await page.locator('#ffp-rows input').first().waitFor();await page.waitForFunction(()=>document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');
  await page.locator('#ffp-select-period').click();await page.locator('#ffp-preview-button').click();await page.locator('#ffp-job-review:not([hidden])').waitFor();
  check(await page.locator('#ffp-job-start').isDisabled()&&await page.locator('#ffp-job-partial-label').isVisible(),'Large partial export requires explicit checkbox consent');
  check(await page.locator('#ffp-job-review-note').isVisible()&&await page.locator('#ffp-job-review-list li').count()===1,'Omissions are easy to find in a focused warning review');
  await page.locator('#ffp-job-partial').check();await page.locator('#ffp-job-start').click();await page.locator('#ffp-job-result:not([hidden])').waitFor();
  check((await page.locator('#ffp-job-status').textContent()).includes(await page.evaluate(()=>FFP.strings.readyPartialJob)),'Incomplete large results are explicitly labelled with omissions');
  await page.locator('#ffp-job').screenshot({path:path.join(out,'large-ready-partial.png'),style:'#wpadminbar{visibility:hidden}'});
  const partialDownload=page.waitForEvent('download');await page.locator('#ffp-job-parts button').first().click();await(await partialDownload).saveAs(path.join(out,'large-browser-partial.zip'));
  execFileSync('python3',['-c','import zipfile,json,sys;z=zipfile.ZipFile(sys.argv[1]);assert z.testzip() is None;d=json.loads(z.read("data.json"));assert d["status"]=="incomplete" and d["attachment_count"]==0 and len(d["warnings"])==1',path.join(out,'large-browser-partial.zip')]);check(true,'Actual partial streaming ZIP has truthful completeness, files and warnings');
  await page.locator('#ffp-job-cancel').click();await page.waitForFunction(()=>document.querySelector('#ffp-job-status').textContent.includes(FFP.strings.cancelledJob));
  fs.writeFileSync(path.join(out,'jobs-browser-results.json'),JSON.stringify({checks,count:checks.length},null,2));
 }finally{setLocale('en_US');await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
