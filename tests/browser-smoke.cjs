/* Browser verification in a disposable lab. Requires Playwright and a Chromium installation. */
const {chromium} = require(process.env.FFP_PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
if (process.env.FFP_TEST_LAB !== '1') throw new Error('Disposable lab only: FFP_TEST_LAB=1');
(async () => {
 const fixture = JSON.parse(fs.readFileSync(process.env.FFP_TEST_FIXTURE, 'utf8'));
 const output = process.env.FFP_TEST_ARTIFACTS || 'artifacts'; fs.mkdirSync(output, {recursive:true});
 const browser = await chromium.launch({headless:true, executablePath:process.env.FFP_CHROMIUM_PATH || undefined});
 try {
  const page = await browser.newPage({viewport:{width:1360,height:1050},acceptDownloads:true});
  const errors=[]; page.on('pageerror',error=>errors.push(error.message));
  const base=process.env.FFP_TEST_URL || 'http://127.0.0.1:8080';
  await page.goto(base+'/wp-login.php');
  await page.locator('#user_login').fill(process.env.FFP_TEST_USER || 'lab'); await page.locator('#user_pass').fill(process.env.FFP_TEST_PASSWORD);
  await Promise.all([page.waitForURL(/wp-admin/),page.locator('#wp-submit').click()]);
  await page.goto(base+'/wp-admin/tools.php?page=forminator-file-packs');
  await page.waitForFunction(id=>document.querySelector('#ffp-form option[value="'+id+'"]'),String(fixture.submission.form));
  await page.locator('#ffp-form').selectOption(String(fixture.submission.form));
  await page.locator('#ffp-rows input').first().waitFor();
  await page.waitForFunction(()=>!document.querySelector('#ffp-preview-button').disabled || !document.querySelector('#ffp-form').disabled);
  await page.locator('#ffp-all').check();
  await page.locator('#ffp-clear').click();
  for (const id of [fixture.submission.entry,fixture.submission.empty_entry]) await page.getByRole('checkbox',{name:`Select submission #${id}`,exact:true}).check();
  await page.locator('#ffp-preview-button').click(); await page.locator('#ffp-preview:not([hidden])').waitFor();
  if (await page.locator('#ffp-warnings').isVisible()) throw new Error('Unexpected preview warnings: '+await page.locator('#ffp-warnings').textContent());
  await page.waitForFunction(()=>!document.querySelector('#ffp-download').disabled);
  await page.evaluate(()=>window.scrollTo(0,0));
  await page.screenshot({path:path.join(output,'admin-desktop.png'),fullPage:true});
  const downloaded=page.waitForEvent('download'); await page.locator('#ffp-download').click(); const download=await downloaded; await download.saveAs(path.join(output,'browser-package.zip'));
  await page.waitForFunction(()=>document.querySelector('#ffp-status').textContent===FFP.strings.complete).catch(async error=>{console.error('Download state:',await page.locator('#ffp-status').textContent(),errors);throw error;});
  await page.setViewportSize({width:390,height:844}); await page.screenshot({path:path.join(output,'admin-mobile.png'),fullPage:true});
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth);
  if (overflow) throw new Error('Horizontal overflow at 390px');
  if (errors.length) throw new Error(errors.join('\n'));
  console.log('PASS browser: login, list, selection, preview, ZIP download, 390px layout, no JS errors');
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
