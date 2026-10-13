/* Real admin error/empty/retry states and Russian localization. */
const {chromium}=require(process.env.FFP_PLAYWRIGHT_MODULE||'playwright');const fs=require('node:fs');const path=require('node:path');
if(process.env.FFP_TEST_LAB!=='1')throw Error('Disposable lab only');
(async()=>{
 const fixture=JSON.parse(fs.readFileSync(process.env.FFP_TEST_FIXTURE,'utf8'));const output=process.env.FFP_TEST_ARTIFACTS;const checks=[];const screenshotStyle='#wpadminbar{visibility:hidden}';
 const browser=await chromium.launch({headless:true,executablePath:process.env.FFP_CHROMIUM_PATH||undefined});
 const assert=(value,name)=>{if(!value)throw Error(name);checks.push(name);console.log('PASS '+name);};
 try{
  const page=await browser.newPage({viewport:{width:1280,height:1000},timezoneId:'America/Los_Angeles',acceptDownloads:true});const errors=[];page.on('pageerror',e=>{errors.push(e.message);console.error('Browser exception: '+e.stack);});
  const base=process.env.FFP_TEST_URL||'http://127.0.0.1:8080';await page.goto(base+'/wp-login.php');await page.locator('#user_login').fill(process.env.FFP_TEST_USER||'lab');await page.locator('#user_pass').fill(process.env.FFP_TEST_PASSWORD);await Promise.all([page.waitForURL(/wp-admin/),page.locator('#wp-submit').click()]);
  await page.goto(base+'/wp-admin/plugins.php');
  const exportLink=page.locator('tr[data-plugin="file-packs-for-forminator/file-packs-for-forminator.php"] a[href*="tools.php?page=file-packs-for-forminator"]');
  assert(await exportLink.count()===1,'plugin list provides a direct export link');
  await Promise.all([page.waitForURL(/tools\.php\?page=file-packs-for-forminator/),exportLink.click()]);
  await page.locator('#ffp-app').waitFor();
  if(process.env.FFP_EXPECT_DEPENDENCY==='1') {assert((await page.locator('#ffp-app').textContent()).includes('Активируйте Forminator'),'dependency absence explained in Russian');assert(await page.locator('#ffp-selection').count()===0,'no export controls without dependency');return;}
  await page.waitForFunction(()=>document.querySelector('#ffp-search-button')?.disabled===false);
  assert((await page.locator('#ffp-readiness').textContent()).includes('проверена')&&await page.locator('#ffp-environment').isHidden(),'readiness is available before selecting any form');
  assert((await page.locator('.ffp-help').textContent()).includes('Распакуйте ZIP')&&(await page.locator('.ffp-help').textContent()).includes('локально'),'quick start explains the result and local-file scope');
  assert(!await page.locator('.ffp-help').evaluate(e=>e.open),'optional help is collapsed on first visit');
  assert((await page.locator('#ffp-form-heading').textContent()).includes('Выберите форму'),'Russian page labels loaded');
  assert((await page.locator('#ffp-count').textContent()).endsWith('0'),'selection counter exposes count without a fixed total ceiling');
  await page.locator('.ffp-help summary').click();await page.waitForFunction(()=>localStorage.getItem('ffp.helpOpen')==='1');await page.locator('.ffp-help summary').click();await page.waitForFunction(()=>localStorage.getItem('ffp.helpOpen')==='0');await page.reload();await page.waitForFunction(()=>window.FFP&&document.querySelector('#ffp-search-button')?.disabled===false);
  assert(!await page.locator('.ffp-help').evaluate(e=>e.open),'collapsed quick start is remembered after page reload');
  await page.locator('.ffp-help summary').click();await page.waitForFunction(()=>localStorage.getItem('ffp.helpOpen')==='1');
  await page.addInitScript(()=>{for(const name of ['getItem','setItem']){const original=Storage.prototype[name];Storage.prototype[name]=function(key,...args){if(key==='ffp.helpOpen')throw new Error('Unavailable plugin storage fixture');return original.call(this,key,...args);};}});
  await page.reload();await page.waitForFunction(()=>window.FFP&&document.querySelector('#ffp-search-button')?.disabled===false);
  assert(await page.locator('#ffp-search-button').isEnabled()&&!await page.locator('.ffp-help').evaluate(e=>e.open),'unavailable browser storage leaves the interface usable');
  await page.route('**/admin-ajax.php',async route=>{if(route.request().postData()?.includes('action=ffp_forms')){const response=await route.fetch();const body=await response.json();body.data.forms=body.data.forms.filter(f=>Number(f.id)===Number(fixture.submission.form));body.data.truncated=false;await route.fulfill({response,json:body});await page.unroute('**/admin-ajax.php');return;}return route.continue();});
  await page.reload();await page.waitForFunction(id=>document.querySelector('#ffp-form')?.value===String(id)&&document.querySelector('#ffp-app')?.getAttribute('aria-busy')==='false'&&document.querySelectorAll('#ffp-rows input').length>0,fixture.submission.form);
  assert(await page.locator('#ffp-selection').isVisible(),'only available form loads its submissions automatically');
  await page.reload();await page.waitForFunction(()=>window.FFP&&document.querySelector('#ffp-search-button')?.disabled===false);

  await page.locator('#ffp-form-search summary').click();
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
  assert(await checkbox.isChecked()&&(await page.locator('#ffp-count').getAttribute('data-count'))==='1','search retaining the form preserves selection');
  await page.locator('#ffp-selected-list button').click();assert(!await checkbox.isChecked(),'remove from selected review updates the table');await checkbox.check();
  for(const period of ['week','month']){
   const event=page.waitForResponse(r=>r.url().includes('admin-ajax.php')&&r.request().postData()?.includes('period='+period));
   await page.locator('#ffp-'+period).click();const body=await(await event).json();await page.waitForFunction(()=>document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');
   assert(await page.locator('#ffp-from').inputValue()===body.data.applied_period.from&&await page.locator('#ffp-to').inputValue()===body.data.applied_period.to,'quick '+period+' period uses server dates despite browser timezone');
   assert(await page.locator('#ffp-'+period).getAttribute('aria-pressed')==='true','quick '+period+' control reflects the applied period');
   assert((await page.locator('#ffp-count').getAttribute('data-count'))==='0','quick '+period+' application clears selection after loading');
  }
  await page.locator('#ffp-reset-period').click();await page.waitForFunction(()=>document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');await checkbox.check();
  await page.locator('#ffp-preview-button').focus();await page.keyboard.press('Enter');await page.locator('#ffp-preview:not([hidden])').waitFor();assert(await page.locator('#ffp-preview-heading').evaluate(e=>e===document.activeElement),'preview heading receives focus');
  await page.locator('#ffp-custom-period summary').click();
  await page.locator('#ffp-from').fill('2099-01-01');assert(await page.locator('#ffp-preview').isHidden()&&await page.locator('#ffp-preview-button').isDisabled(),'date edit invalidates preview and pauses export');assert(await checkbox.isDisabled(),'outdated rows cannot be selected');
  assert(await checkbox.isChecked()&&(await page.locator('#ffp-count').getAttribute('data-count'))==='1','date edit preserves the existing selection');
  await page.locator('#ffp-to').fill('2000-01-01');await page.locator('#ffp-filter').click();await page.waitForFunction(()=>document.querySelector('#ffp-status').getAttribute('role')==='alert'&&document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');
  assert((await page.locator('#ffp-count').getAttribute('data-count'))==='1'&&await page.locator('#ffp-preview-button').isDisabled(),'invalid period preserves selection and keeps export paused');await page.locator('#ffp-to').fill('');
  await page.locator('#ffp-filter').click();await page.waitForFunction(()=>document.querySelector('#ffp-rows').textContent.includes(FFP.strings.noEntries));assert(await page.locator('#ffp-all').isDisabled()&&(await page.locator('#ffp-count').getAttribute('data-count'))==='0','successful period application clears selection and explains empty period');
  await page.locator('#ffp-reset-period').click();await page.locator('#ffp-rows input').first().waitFor();await page.waitForFunction(()=>!document.querySelector('#ffp-form').disabled);
  await page.route('**/admin-ajax.php',async route=>{if(route.request().postData()?.includes('action=ffp_preview')){await route.abort();await page.unroute('**/admin-ajax.php');return;}return route.continue();});
  await checkbox.check();await page.locator('#ffp-preview-button').click();await page.waitForFunction(()=>document.querySelector('#ffp-status').getAttribute('role')==='alert');assert(await page.locator('#ffp-preview').isHidden(),'network failure does not show ready package');
  await page.locator('#ffp-retry').click();await page.locator('#ffp-preview:not([hidden])').waitFor();assert(await page.locator('#ffp-download').isEnabled(),'direct retry after network interruption works');
  await page.route('**/admin-ajax.php',async route=>{if(route.request().postData()?.includes('action=ffp_preview')){await route.abort();await page.unroute('**/admin-ajax.php');return;}return route.continue();});
  await page.locator('#ffp-preview-button').click();await page.waitForFunction(()=>document.querySelector('#ffp-status').getAttribute('role')==='alert'&&document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');
  assert(await page.locator('#ffp-preview').isHidden()&&await page.locator('#ffp-download').isDisabled(),'failed recheck removes stale ready confirmation');
  await page.locator('#ffp-clear').click();assert(await page.locator('#ffp-retry').isHidden()&&await page.locator('#ffp-preview-button').isDisabled(),'clearing selection removes stale recovery action');await checkbox.check();
  // Exercise the real server's nonce rejection without changing the login session.
  await page.evaluate(()=>FFP.nonce='expired-ui-fixture');
  await page.locator('#ffp-preview-button').click();await page.waitForFunction(()=>document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');
  assert((await page.locator('#ffp-retry').textContent()).includes('Обновить страницу'),'expired session offers page refresh instead of repeating expired nonce');
  await page.locator('#ffp-retry').click();await page.waitForFunction(old=>window.FFP?.nonce&&FFP.nonce!==old&&document.querySelector('#ffp-form')?.disabled===false,'expired-ui-fixture');assert(await page.locator('#ffp-selection').isHidden(),'session recovery reloads page with fresh nonce');
  await page.locator('#ffp-form').selectOption(String(fixture.submission.form));await page.waitForFunction(()=>document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');await checkbox.check();
  await page.evaluate(()=>window.ffpOriginalFetch=window.fetch);
  await page.evaluate(()=>window.fetch=(url,options)=>new URLSearchParams(options.body).get('action')==='ffp_preview'?new Promise((resolve,reject)=>options.signal.addEventListener('abort',()=>reject(new DOMException('Aborted','AbortError')),{once:true})):window.ffpOriginalFetch(url,options));
  await page.locator('#ffp-preview-button').click();await page.locator('#ffp-cancel:not([hidden])').waitFor();await page.locator('#ffp-cancel').click();await page.waitForFunction(()=>document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');
  assert((await page.locator('#ffp-status').textContent()).includes('Сервер может')&&await page.locator('#ffp-form').isEnabled(),'stop waiting releases controls and explains possible continuing server work');
  await page.evaluate(()=>{window.ffpOriginalTimeout=FFP.requestTimeout;FFP.requestTimeout=100;});
  await page.locator('#ffp-preview-button').click();await page.waitForFunction(()=>document.querySelector('#ffp-app').getAttribute('aria-busy')==='false'&&document.querySelector('#ffp-status').textContent===FFP.strings.timeout);
  assert(await page.locator('#ffp-retry').isEnabled(),'bounded request timeout leaves usable recovery');
  await page.evaluate(()=>{window.fetch=window.ffpOriginalFetch;FFP.requestTimeout=window.ffpOriginalTimeout;});

  assert(await page.evaluate(()=>{const e=new Event('beforeunload',{cancelable:true});window.dispatchEvent(e);return !e.defaultPrevented;}),'no leave-page warning during idle or after preview failure');
  // An unavailable-environment response must block preview until a successful recheck.
  await page.route('**/admin-ajax.php',async route=>{if(route.request().postData()?.includes('action=ffp_forms')){const response=await route.fetch();const body=await response.json();body.data.readiness={ready:false,zip:false,storage:true,messages:['ZIP unavailable test']};await route.fulfill({response,json:body});await page.unroute('**/admin-ajax.php');return;}return route.continue();});
  await page.locator('.ffp-help summary').click();await page.locator('#ffp-check-ready').click();await page.waitForFunction(()=>document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');assert(await page.locator('#ffp-environment').isVisible()&&await page.locator('#ffp-preview-button').isDisabled()&&(await page.locator('#ffp-requirements').textContent()).includes('ZIP unavailable'),'unavailable environment blocks export and shows actionable problem');
  await page.locator('#ffp-check').click();await page.waitForFunction(()=>document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');assert(await checkbox.isChecked()&&await page.locator('#ffp-preview-button').isEnabled(),'successful environment recheck preserves selection and enables preview');
  await page.locator('#ffp-preview-button').click();await page.locator('#ffp-preview:not([hidden])').waitFor();
  await page.evaluate(()=>window.scrollTo(0,0));await page.screenshot({path:path.join(output,'admin-ru-desktop.png'),fullPage:true});
  await page.setViewportSize({width:390,height:844});await page.screenshot({path:path.join(output,'admin-ru-mobile.png'),fullPage:true});assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)),'Russian 390px layout does not overflow');assert(await page.locator('.ffp-mobile-date').first().isVisible(),'date remains visible on narrow screen');

  await page.evaluate(()=>{window.fetch=(url,options)=>new URLSearchParams(options.body).get('action')==='ffp_download'?new Promise((resolve,reject)=>options.signal.addEventListener('abort',()=>reject(new DOMException('Aborted','AbortError')),{once:true})):window.ffpOriginalFetch(url,options);});
  await page.locator('#ffp-download').click();await page.locator('#ffp-cancel:not([hidden])').waitFor();
  assert(await page.evaluate(()=>{const e=new Event('beforeunload',{cancelable:true});window.dispatchEvent(e);return e.defaultPrevented;}),'ZIP wait activates leave-page warning');
  await page.locator('#ffp-cancel').click();await page.waitForFunction(()=>document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');
  assert(await page.evaluate(()=>{const e=new Event('beforeunload',{cancelable:true});window.dispatchEvent(e);return !e.defaultPrevented;}),'stopping ZIP wait removes leave-page warning');
  await page.evaluate(()=>window.fetch=window.ffpOriginalFetch);await page.locator('#ffp-retry').click();await page.locator('#ffp-preview:not([hidden])').waitFor();
  await page.route('**/admin-ajax.php',async route=>{if(route.request().postData()?.includes('action=ffp_download')){await route.abort();await page.unroute('**/admin-ajax.php');return;}return route.continue();});
  await page.locator('#ffp-download').click();await page.waitForFunction(()=>document.querySelector('#ffp-status').getAttribute('role')==='alert'&&document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');
  assert(await page.locator('#ffp-preview').isHidden(),'failed download invalidates package');
  assert(await page.locator('#ffp-retry').evaluate(e=>e===document.activeElement),'download error focuses an enabled recovery action');
  assert(await page.locator('#ffp-notification').evaluate(e=>{const b=e.getBoundingClientRect();return b.top>=0&&b.bottom<=innerHeight&&b.left>=0&&b.right<=innerWidth;}),'mobile download error remains inside the viewport');
  await page.screenshot({path:path.join(output,'admin-ru-download-error.png'),fullPage:false});
  await page.locator('#ffp-retry').click();await page.locator('#ffp-preview:not([hidden])').waitFor();assert(await page.locator('#ffp-download').isEnabled(),'download recovery refreshes preview before retry');
  await page.setViewportSize({width:320,height:800});assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)),'new help and selection controls fit 320px');await page.setViewportSize({width:390,height:844});
  await page.locator('#ffp-clear').click();await page.getByRole('checkbox',{name:label+' #'+fixture.submission.broken_entry,exact:true}).check();await page.locator('#ffp-preview-button').click();await page.locator('#ffp-warnings:not([hidden])').waitFor();assert(await page.locator('#ffp-download').isDisabled(),'warnings require explicit consent');assert((await page.locator('#ffp-warnings li p').first().textContent()).includes('Проверьте'),'omitted attachment includes a localized next action');await page.locator('#ffp-partial').check();
  const downloadEvent=page.waitForEvent('download');await page.locator('#ffp-download').click();const downloaded=await downloadEvent;assert(/^form-\d+-.+-\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.zip$/.test(downloaded.suggestedFilename()),'browser uses descriptive dated ZIP filename from server');await downloaded.saveAs(path.join(output,'partial-ru.zip'));await page.waitForFunction(()=>document.querySelector('#ffp-status').textContent.startsWith(FFP.strings.incomplete));assert(true,'partial download has distinct Russian result');

  assert(await page.locator('#ffp-result').isVisible()&&await page.locator('#ffp-result-name').textContent()===downloaded.suggestedFilename()&&(await page.locator('#ffp-result').textContent()).includes('index.html'),'download result identifies the archive and explains extraction');
  assert(await page.locator('#ffp-result-heading').evaluate(e=>e===document.activeElement),'download result heading receives focus');
  assert(await page.locator('#ffp-result').evaluate(e=>{const b=e.getBoundingClientRect();const notice=document.querySelector('#ffp-notification').getBoundingClientRect();return b.top>=0&&b.bottom<=notice.top;}),'mobile download instructions remain above the fixed notification');
  await page.setViewportSize({width:320,height:800});await page.evaluate(()=>document.querySelector('#ffp-result-name').textContent='form-1-'+ 'a'.repeat(150)+'-2026-10-13_12-00-00.zip');await page.locator('#ffp-result').evaluate(e=>e.scrollIntoView({block:'start'}));
  assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)),'long archive filename in download result fits 320px');
  await page.screenshot({path:path.join(output,'admin-ru-download-result.png'),fullPage:false});
  assert(await page.evaluate(()=>{const e=new Event('beforeunload',{cancelable:true});window.dispatchEvent(e);return !e.defaultPrevented;}),'completed ZIP removes leave-page warning');
  await page.locator('#ffp-clear').click();assert(await page.locator('#ffp-result').isHidden(),'selection change removes obsolete download result');

  await page.setViewportSize({width:1280,height:900});await page.reload();await page.waitForFunction(()=>window.FFP&&document.querySelector('#ffp-form')?.disabled===false);
  await page.locator('#ffp-form').selectOption(String(fixture.showcase.form));await page.waitForFunction(()=>document.querySelector('#ffp-app').getAttribute('aria-busy')==='false'&&document.querySelectorAll('#ffp-rows input').length===2);
  assert((await page.locator('#ffp-main-heading').textContent()).includes('Скачать заявки')&&(await page.locator('#ffp-rows').textContent()).includes('Иван Петров'),'task-first heading and real persisted descriptions are shown');
  assert(await page.locator('#ffp-environment').isHidden()&&!await page.locator('.ffp-help').evaluate(e=>e.open)&&!await page.locator('#ffp-custom-period').evaluate(e=>e.open),'successful requirements, optional help and custom dates do not occupy the main flow');
  assert(await page.locator('#ffp-rows td:last-child').allTextContents().then(a=>a.sort().join(',')==='2,3'),'saved attachment counts match real fixture references');
  await page.locator('#ffp-all').check();
  await page.locator('#ffp-workspace').screenshot({style:screenshotStyle,path:path.join(output,'showcase-desktop.png')});
  await page.setViewportSize({width:390,height:844});assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)),'compact task flow fits a 390px phone');assert(await page.locator('.ffp-mobile-files').first().isVisible(),'phone rows show saved file counts alongside submission details');await page.locator('#ffp-workspace').screenshot({style:screenshotStyle,path:path.join(output,'showcase-mobile.png')});
  await page.setViewportSize({width:1280,height:900});await page.locator('#ffp-preview-button').click();await page.locator('#ffp-preview:not([hidden])').waitFor();
  assert((await page.locator('#ffp-totals').textContent()).includes('5')&&await page.locator('#ffp-download').isEnabled()&&!await page.locator('#ffp-content-review').evaluate(e=>e.open),'compact confirmation shows five verified files and keeps optional contents collapsed');
  await page.locator('#ffp-workspace').screenshot({style:screenshotStyle,path:path.join(output,'showcase-confirmation.png')});
  const taskDownload=page.waitForEvent('download');await page.locator('#ffp-download').click();await(await taskDownload).saveAs(path.join(output,'showcase-package.zip'));await page.waitForFunction(()=>document.querySelector('#ffp-result')?.hidden===false);
  assert(await page.locator('#ffp-preview').isHidden()&&(await page.locator('#ffp-result').textContent()).includes('Список заявок'),'completed download replaces confirmation with extraction guidance');
  await page.locator('#ffp-workspace').screenshot({style:screenshotStyle,path:path.join(output,'showcase-result.png')});
  const extracted=path.join(output,'showcase-extracted');require('node:child_process').execFileSync('python3',['-c','import sys,zipfile; z=zipfile.ZipFile(sys.argv[1]); assert z.testzip() is None; z.extractall(sys.argv[2])',path.join(output,'showcase-package.zip'),extracted]);
  const offline=await browser.newPage({viewport:{width:1100,height:800}});await offline.goto(require('node:url').pathToFileURL(path.join(extracted,'index.html')).href);
  assert((await offline.locator('main').textContent()).includes('Иван Петров')&&(await offline.locator('a[href$="/request.html"]').count())===2,'actual extracted ZIP index shows recognizable submissions offline');
  await offline.screenshot({style:screenshotStyle,path:path.join(output,'showcase-zip-index.png'),fullPage:true});
  await offline.locator('a[href$="/request.html"]').first().click();assert((await offline.locator('main').textContent()).includes('Смирнова')||(await offline.locator('main').textContent()).includes('Петров'),'actual offline submission card opens from the index');await offline.screenshot({style:screenshotStyle,path:path.join(output,'showcase-zip-card.png'),fullPage:true});await offline.close();
  assert(errors.length===0,'no JavaScript exceptions');fs.writeFileSync(path.join(output,'browser-state-results.json'),JSON.stringify({checks,count:checks.length},null,2));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
