/* Combined ceiling through actual pagination, preview and browser Blob download. */
const {chromium}=require(process.env.FFP_PLAYWRIGHT_MODULE||'playwright');
const fs=require('node:fs');
if(process.env.FFP_TEST_LAB!=='1')throw Error('Disposable lab only');
(async()=>{
 const out=process.env.FFP_TEST_ARTIFACTS;
 const fixture=JSON.parse(fs.readFileSync(out+'/capacity-fixture.json'));
 const browser=await chromium.launch({headless:true,executablePath:process.env.FFP_CHROMIUM_PATH||undefined});
 const checks=[];const check=(ok,name)=>{if(!ok)throw Error(name);checks.push(name);console.log('PASS '+name);};
 try{
  const page=await browser.newPage({acceptDownloads:true});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  const base=process.env.FFP_TEST_URL||'http://127.0.0.1:8080';
  await page.goto(base+'/wp-login.php');await page.locator('#user_login').fill(process.env.FFP_TEST_USER||'lab');await page.locator('#user_pass').fill(process.env.FFP_TEST_PASSWORD);
  await Promise.all([page.waitForURL(/wp-admin/),page.locator('#wp-submit').click()]);
  await page.goto(base+'/wp-admin/tools.php?page=forminator-file-packs');
  await page.waitForFunction(id=>document.querySelector('#ffp-form option[value="'+id+'"]'),fixture.form);
  await page.locator('#ffp-form').selectOption(String(fixture.form));
  await page.waitForFunction(()=>document.querySelectorAll('#ffp-rows input').length===25&&document.querySelector('#ffp-app').getAttribute('aria-busy')==='false');
  for(let p=1;p<=4;p++){
   await page.locator('#ffp-all').check();
   if(p<4){const before=await page.locator('#ffp-rows').textContent();await page.locator('#ffp-next').click();await page.waitForFunction(old=>document.querySelector('#ffp-rows').textContent!==old&&document.querySelector('#ffp-app').getAttribute('aria-busy')==='false',before);}
  }
  check((await page.locator('#ffp-count').textContent()).endsWith('100'),'Selection persists across all four pages: 100 entries');
  await page.locator('#ffp-preview-button').click();await page.locator('#ffp-preview:not([hidden])').waitFor();
  check((await page.locator('#ffp-totals').textContent()).includes('500')&&(await page.locator('#ffp-totals').textContent()).includes('100.00 MiB'),'Actual preview shows 500 files and 100 MiB');
  const cdp=await page.context().newCDPSession(page);await cdp.send('Accessibility.enable');
  const {root}=await cdp.send('DOM.getDocument');const {nodeId}=await cdp.send('DOM.querySelector',{nodeId:root.nodeId,selector:'#ffp-app'});
  const {nodeIds}=await cdp.send('DOM.querySelectorAll',{nodeId,selector:'button,input,select,summary'});
  let named=0;
  for(const control of nodeIds){
   const {node}=await cdp.send('DOM.describeNode',{nodeId:control});
   const {nodes}=await cdp.send('Accessibility.getPartialAXTree',{backendNodeId:node.backendNodeId,fetchRelatives:false});
   for(const n of nodes){if(!n.ignored){if(!n.name?.value)throw Error('Unnamed accessible control: '+n.role.value);named++;}}
  }
  check(named>=35,'Browser accessibility tree exposes named controls and package summaries');
  check(await page.locator('#ffp-status').getAttribute('aria-live')==='polite'&&await page.locator('#ffp-count').getAttribute('aria-live')==='polite','Result and selection updates have live regions');
  const contrast=await page.evaluate(()=>{
   const rgb=value=>{const a=value.match(/[\d.]+/g)?.map(Number);return a&&a.length>=3?[...a.slice(0,3),a[3]??1]:[255,255,255,1];};
   const luminance=color=>color.slice(0,3).map(n=>n/255).map(n=>n<=.04045?n/12.92:((n+.055)/1.055)**2.4).reduce((sum,n,i)=>sum+n*[.2126,.7152,.0722][i],0);
   const failures=[];let checked=0;
   for(const el of document.querySelectorAll('.ffp-intro,.ffp-panel h2,.ffp-controls label,.ffp-table th,.ffp-table td,.ffp-totals,.ffp details summary,.ffp-panel .button-primary')){
    if(!el.getClientRects().length||!el.textContent.trim())continue;
    const style=getComputedStyle(el);let bg=[255,255,255,1];const ancestors=[];
    for(let parent=el;parent;parent=parent.parentElement)ancestors.unshift(parent);
    for(const parent of ancestors){const c=rgb(getComputedStyle(parent).backgroundColor);bg=c.slice(0,3).map((v,i)=>v*c[3]+bg[i]*(1-c[3])).concat(1);}
    const fg=rgb(style.color);const color=fg.slice(0,3).map((v,i)=>v*fg[3]+bg[i]*(1-fg[3]));
    const a=luminance(color),b=luminance(bg),ratio=(Math.max(a,b)+.05)/(Math.min(a,b)+.05);
    const large=parseFloat(style.fontSize)>=24||(parseFloat(style.fontSize)>=18.667&&parseInt(style.fontWeight)>=700);
    if(ratio+.01<(large?3:4.5))failures.push({text:el.textContent.slice(0,60),ratio});checked++;
   }
   return {checked,failures};
  });
  check(contrast.checked>100&&!contrast.failures.length,'Computed text contrast passes sampled plugin labels, rows, summaries and primary buttons');
  await page.locator('#ffp-preview-heading').focus();check(await page.locator('#ffp-preview-heading').evaluate(e=>e===document.activeElement),'Preview heading accepts focus');
  await page.setViewportSize({width:320,height:800});
  check(await page.locator('#ffp-app').evaluate(e=>e.scrollWidth<=e.clientWidth),'Plugin layout fits 320px viewport at maximum selection');
  await page.setViewportSize({width:1280,height:900});
  const start=Date.now();const event=page.waitForEvent('download',{timeout:90000});await page.locator('#ffp-download').click();const download=await event;
  await download.saveAs(out+'/capacity-browser.zip');await page.waitForFunction(()=>document.querySelector('#ffp-status').textContent===FFP.strings.complete);
  const seconds=(Date.now()-start)/1000;check(!await download.failure(),'100 MiB package downloaded through actual browser Blob route');
  const {execFileSync}=require('node:child_process');
  execFileSync('python3',['-c','import sys,json,zipfile,hashlib; f=json.load(open(sys.argv[2])); z=zipfile.ZipFile(sys.argv[1]); names=[n for n in z.namelist() if n.endswith(".txt")]; assert len(names)==len(set(names))==500; assert sorted(hashlib.sha256(z.read(n)).hexdigest() for n in names)==sorted(f["hashes"]); assert len([n for n in z.namelist() if n.endswith("/request.html")])==100; assert z.testzip() is None',out+'/capacity-browser.zip',out+'/capacity-fixture.json']);
  check(true,'Downloaded ZIP: 100 cards, 500 distinct members, all attachment hashes and CRC valid');check(!errors.length,'No browser JavaScript errors at maximum selection');
  fs.writeFileSync(out+'/capacity-browser-results.json',JSON.stringify({checks,count:checks.length,browser_download_seconds:seconds,zip_bytes:fs.statSync(out+'/capacity-browser.zip').size,named_accessible_controls:named,contrast_samples:contrast.checked,accessibility_scope:'Chromium accessible names, live regions, focus, computed text contrast on solid backgrounds, 320px reflow; no screen reader or full WCAG audit',browser_memory:'Not measured; successful Blob download is not a universal capacity guarantee'},null,2));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
