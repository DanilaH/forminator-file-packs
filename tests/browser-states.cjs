/* Real admin error/empty/retry states and Russian localization. */
const {chromium}=require(process.env.FFP_PLAYWRIGHT_MODULE||'playwright');const fs=require('node:fs');const path=require('node:path');
if(process.env.FFP_TEST_LAB!=='1')throw Error('Disposable lab only');
(async()=>{
 const fixture=JSON.parse(fs.readFileSync(process.env.FFP_TEST_FIXTURE,'utf8'));const output=process.env.FFP_TEST_ARTIFACTS;const checks=[];
 const browser=await chromium.launch({headless:true,executablePath:process.env.FFP_CHROMIUM_PATH||undefined});
 const assert=(value,name)=>{if(!value)throw Error(name);checks.push(name);console.log('PASS '+name);};
 try{
  const page=await browser.newPage({viewport:{width:1280,height:1000},acceptDownloads:true});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  const base=process.env.FFP_TEST_URL||'http://127.0.0.1:8080';await page.goto(base+'/wp-login.php');await page.locator('#user_login').fill(process.env.FFP_TEST_USER||'lab');await page.locator('#user_pass').fill(process.env.FFP_TEST_PASSWORD);await Promise.all([page.waitForURL(/wp-admin/),page.locator('#wp-submit').click()]);
  await page.goto(base+'/wp-admin/plugins.php');
  const exportLink=page.locator('tr[data-plugin="file-packs-for-forminator/file-packs-for-forminator.php"] a[href*="tools.php?page=file-packs-for-forminator"]');
  assert(await exportLink.count()===1,'plugin list provides a direct export link');
  await exportLink.click();
  if(process.env.FFP_EXPECT_DEPENDENCY==='1') {assert((await page.locator('#ffp-app').textContent()).includes('Активируйте Forminator'),'dependency absence explained in Russian');assert(await page.locator('#ffp-selection').count()===0,'no export controls without dependency');return;}
  await page.waitForFunction(()=>!document.querySelector('#ffp-search-button').disabled);
  assert((await page.locator('.ffp-help').textContent()).includes('Распакуйте ZIP')&&(await page.locator('.ffp-help').textContent()).includes('локально'),'quick start explains the result and local-file scope');
  assert((await page.locator('#ffp-form-heading').textContent()).includes('Выберите форму'),'Russian page labels loaded');
  await page.locator('#ffp-search').fill('NO-FORM-UNIQUE-FFP');await page.locator('#ffp-search-button').click();await page.waitForFunction(()=>document.querySelector('#ffp-form-note').textContent===FFP.strings.noForms);assert(await page.locator('#ffp-form option').count()===1,'no forms search state');
  await page.locator('#ffp-search').fill('');await page.locator('#ffp-search').press('Enter');await page.waitForFunction(f=>document.querySelector('#ffp-form option[value="'+f+'"]'),fixture.submission.form);
  await page.locator('#ffp-form').selectOption(String(fixture.submission.form));await page.locator('#ffp-rows input').first().waitFor();await page.waitForFunction(()=>!document.querySelector('#ffp-form').disabled);
  assert(await page.locator('#ffp-preview-button').isDisabled(),'empty selection cannot preview');
  assert((await page.locator('#ffp-selection-help').textContent()).includes('Отметьте'),'empty selection explains next action');
  assert(await page.locator('#ffp-pagination').isHidden(),'single-page results omit unnecessary pagination');
  const label=await page.evaluate(()=>FFP.strings.selectEntry);const checkbox=page.getByRole('checkbox',{name:label+' #'+fixture.submission.entry,exact:true});await checkbox.focus();await page.keyboard.press('Space');assert(await checkbox.isChecked(),'keyboard selects submission');
  await page.locator('#ffp-selected-review summary').click();
  assert((await page.locator('#ffp-selected-list').textContent()).includes('#'+fixture.submission.entry),'selected review identifies the submission');
  await page.locator('#ffp-search-button').click();await page.waitForFunction(()=>document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');
  assert(await checkbox.isChecked()&&(await page.locator('#ffp-count').textContent()).endsWith('1'),'search retaining the form preserves selection');
  await page.locator('#ffp-selected-list button').click();assert(!await checkbox.isChecked(),'remove from selected review updates the table');await checkbox.check();
  await page.locator('#ffp-preview-button').focus();await page.keyboard.press('Enter');await page.locator('#ffp-preview:not([hidden])').waitFor();assert(await page.locator('#ffp-preview-heading').evaluate(e=>e===document.activeElement),'preview heading receives focus');
  await page.locator('#ffp-from').fill('2099-01-01');assert(await page.locator('#ffp-preview').isHidden()&&await page.locator('#ffp-preview-button').isDisabled(),'date edit invalidates preview and pauses export');assert(await checkbox.isDisabled(),'outdated rows cannot be selected');
  assert(await checkbox.isChecked()&&(await page.locator('#ffp-count').textContent()).endsWith('1'),'date edit preserves the existing selection');
  await page.locator('#ffp-to').fill('2000-01-01');await page.locator('#ffp-filter').click();await page.waitForFunction(()=>document.querySelector('#ffp-status').getAttribute('role')==='alert'&&document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');
  assert((await page.locator('#ffp-count').textContent()).endsWith('1')&&await page.locator('#ffp-preview-button').isDisabled(),'invalid period preserves selection and keeps export paused');await page.locator('#ffp-to').fill('');
  await page.locator('#ffp-filter').click();await page.waitForFunction(()=>document.querySelector('#ffp-rows').textContent.includes(FFP.strings.noEntries));assert(await page.locator('#ffp-all').isDisabled()&&(await page.locator('#ffp-count').textContent()).endsWith('0'),'successful period application clears selection and explains empty period');
  await page.locator('#ffp-reset-period').click();await page.locator('#ffp-rows input').first().waitFor();await page.waitForFunction(()=>!document.querySelector('#ffp-form').disabled);
  await page.route('**/admin-ajax.php',async route=>{if(route.request().postData()?.includes('action=ffp_preview')){await route.abort();await page.unroute('**/admin-ajax.php');return;}return route.continue();});
  await checkbox.check();await page.locator('#ffp-preview-button').click();await page.waitForFunction(()=>document.querySelector('#ffp-status').getAttribute('role')==='alert');assert(await page.locator('#ffp-preview').isHidden(),'network failure does not show ready package');
  await page.locator('#ffp-retry').click();await page.locator('#ffp-preview:not([hidden])').waitFor();assert(await page.locator('#ffp-download').isEnabled(),'direct retry after network interruption works');
  await page.evaluate(()=>window.scrollTo(0,0));await page.screenshot({path:path.join(output,'admin-ru-desktop.png'),fullPage:true});
  await page.setViewportSize({width:390,height:844});await page.screenshot({path:path.join(output,'admin-ru-mobile.png'),fullPage:true});assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)),'Russian 390px layout does not overflow');assert(await page.locator('.ffp-mobile-date').first().isVisible(),'date remains visible on narrow screen');
  await page.route('**/admin-ajax.php',async route=>{if(route.request().postData()?.includes('action=ffp_download')){await route.abort();await page.unroute('**/admin-ajax.php');return;}return route.continue();});
  await page.locator('#ffp-download').click();await page.waitForFunction(()=>document.querySelector('#ffp-status').getAttribute('role')==='alert'&&document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');
  assert(await page.locator('#ffp-preview').isHidden(),'failed download invalidates package');
  assert(await page.locator('#ffp-retry').evaluate(e=>e===document.activeElement),'download error focuses an enabled recovery action');
  assert(await page.locator('#ffp-notification').evaluate(e=>{const b=e.getBoundingClientRect();return b.top>=0&&b.bottom<=innerHeight&&b.left>=0&&b.right<=innerWidth;}),'mobile download error remains inside the viewport');
  await page.screenshot({path:path.join(output,'admin-ru-download-error.png'),fullPage:false});
  await page.locator('#ffp-retry').click();await page.locator('#ffp-preview:not([hidden])').waitFor();assert(await page.locator('#ffp-download').isEnabled(),'download recovery refreshes preview before retry');
  await page.setViewportSize({width:320,height:800});assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)),'new help and selection controls fit 320px');await page.setViewportSize({width:390,height:844});
  await page.locator('#ffp-clear').click();await page.getByRole('checkbox',{name:label+' #'+fixture.submission.broken_entry,exact:true}).check();await page.locator('#ffp-preview-button').click();await page.locator('#ffp-warnings:not([hidden])').waitFor();assert(await page.locator('#ffp-download').isDisabled(),'warnings require explicit consent');await page.locator('#ffp-partial').check();
  const downloadEvent=page.waitForEvent('download');await page.locator('#ffp-download').click();await(await downloadEvent).saveAs(path.join(output,'partial-ru.zip'));await page.waitForFunction(()=>document.querySelector('#ffp-status').textContent.startsWith(FFP.strings.incomplete));assert(true,'partial download has distinct Russian result');
  assert(errors.length===0,'no JavaScript exceptions');fs.writeFileSync(path.join(output,'browser-state-results.json'),JSON.stringify({checks,count:checks.length},null,2));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
