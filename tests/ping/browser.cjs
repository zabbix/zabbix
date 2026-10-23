const {chromium} = require('playwright');
const assert = require('node:assert/strict');
(async () => {
 const browser = await chromium.launch({channel:'chrome', headless:true});
 try {
 const page = await browser.newPage({viewport:{width:320,height:640}});
 const cases = [
 ['3 packets transmitted, 3 received, 0% packet loss\nrtt min/avg/max/mdev = 1.1/2.2/3.3/0.1 ms','1','up'],
 ['3 packets transmitted, 2 packets received, 33.3% packet loss','1','warning'],
 ['3 packets transmitted, 0 received, 100% packet loss','1','down'],
 ['ping: unknown host example','1','error'],
 ['unexpected <script>alert(1)</script>','1','unknown'],
 ['3 packets transmitted, 0 received, 100% packet loss','0','error']
 ];
 for (const [output, success, state] of cases) {
  await page.setContent('<div class="overlay-dialogue modal-popup modal-popup-medium"><div class="overlay-dialogue-body"><div id="ping-result" role="status"></div></div></div>');
  await page.addStyleTag({path:'ui/assets/styles/blue-theme.css'});
  await page.locator('#ping-result').evaluate((el, data) => {el.dataset.output=data.output;el.dataset.success=data.success;el.dataset.host='Demo switch';el.dataset.ip='192.0.2.10'}, {output,success});
  await page.evaluate(()=>window.t = text => text);
  await page.addScriptTag({path:'ui/js/ping-result.js'});
  assert.equal(await page.locator('#ping-result').getAttribute('data-state'),state);
  assert.match(await page.locator('#ping-result').innerText(), /Demo switch/);
  assert.match(await page.locator('#ping-result').innerText(), /192.0.2.10/);
  assert.equal(await page.locator('#ping-result [style]').count(), 0);
  assert.equal(await page.locator('#ping-result').getAttribute('data-output'), null);
  assert.ok(!(await page.locator('#ping-result').innerText()).includes('packets transmitted'));
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth <= innerWidth));
 }
 await page.screenshot({path:'/tmp/zabbix-ping-error.png'});
 await page.evaluate(() => {
  window.t = text => text;
  window.ZBX_SCRIPT_MANUALINPUT_ENABLED = 1;
  window.CSRF_TOKEN_NAME = '_csrf_token';
  window.PopUp = (action, params, options) => { window.pingOptions = options; };
 });
 await page.addScriptTag({path:'ui/js/class.script.js'});
 await page.evaluate(() => Script.execute('1', '', document.body, '42', null, 'token', null, null, null, null, null, 'Ping'));
 await page.evaluate(() => {
  document.body.innerHTML = '<div class="overlay-dialogue modal-popup modal-popup-medium"><div class="overlay-dialogue-body is-loading"></div></div>';
  document.querySelector('.overlay-dialogue-body').append(window.pingOptions.loading_content);
 });
 assert.equal(await page.locator('.ping-progress svg').count(), 1);
 assert.equal(await page.locator('.ping-packet').count(), 3);
 assert.equal(await page.locator('.overlay-dialogue-body').evaluate(el=>getComputedStyle(el, '::after').display), 'none');
 assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth <= innerWidth));
 await page.screenshot({path:'/tmp/zabbix-ping-progress.png'});
 await page.addStyleTag({path:'ui/assets/styles/dark-theme.css'});
 await page.screenshot({path:'/tmp/zabbix-ping-progress-dark.png'});
 assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth <= innerWidth));
 await page.emulateMedia({reducedMotion:'reduce'});
 assert.equal(await page.locator('.ping-packet').first().evaluate(el=>getComputedStyle(el).animationName), 'none');
 await page.evaluate(() => Script.execute('2', '', document.body, '42', null, 'token', null, null, null, null, null, 'Other script'));
 assert.equal(await page.evaluate(()=>window.pingOptions.loading_content), null);
 await page.evaluate(()=>document.querySelector('.overlay-dialogue').remove());
 assert.equal(await page.evaluate(()=>document.getAnimations().length), 0);
 console.log('PASS: six result states, host/IP, native styles, animated Ping-only loader, light/dark themes, 320px, reduced motion, animation cleanup');
 } finally {await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});
