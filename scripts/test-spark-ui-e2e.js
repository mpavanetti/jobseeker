'use strict';

// Playwright UI regression for the Data Engineering / Spark screens.
// Needs the live docker-compose stack (JOBSEEKER_E2E_URL, default http://localhost)
// and an admin login. Not in the default `npm test` chain.
//
//   npm run test:spark-ui
//
// Covers: the Compute screen's inline monitor renders IN the layout (not the old
// off-screen #clusterStatusRoot strip) and uses a single, cleared interval;
// Start/Stop flips the row; and the Create Job Spark panel is notebook-native
// (Interactive/Batch mode, read-only job.py preview, no visible code textarea).

const { chromium } = require('playwright');

const BASE = (process.env.JOBSEEKER_E2E_URL || 'http://localhost').replace(/\/$/, '');
const EMAIL = process.env.JOBSEEKER_E2E_EMAIL || 'admin@example.com';
const PASSWORD = process.env.JOBSEEKER_E2E_PASSWORD || '123456';

let failures = 0;
function check(label, cond, extra) {
  console.log((cond ? '  ok   - ' : '  FAIL - ') + label + (cond ? '' : '  :: ' + JSON.stringify(extra)));
  if (!cond) { failures++; }
}

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1460, height: 950 } })).newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push('pageerror: ' + e));
  page.on('console', (m) => {
    // Chrome logs the legacy jobCreation page's DOMSubtreeModified deprecation at
    // error level; it is pre-existing and unrelated to these screens.
    if (m.type() === 'error' && !/DOMSubtreeModified/.test(m.text())) { errors.push('console: ' + m.text()); }
  });

  await page.goto(BASE + '/');
  await page.fill('input[name="email"]', EMAIL);
  await page.fill('input[name="password"]', PASSWORD);
  await Promise.all([page.waitForNavigation(), page.click('button[type="submit"], input[type="submit"]')]);

  // ---- Compute screen -------------------------------------------------
  const CKEY = 'uitest-' + String(Date.now()).slice(-6);
  await page.goto(BASE + '/data-engineering/spark-clusters?environment=DEV', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);

  check('Compute screen has no detached #clusterStatusRoot', await page.evaluate(() => !document.getElementById('clusterStatusRoot')));
  check('no horizontal page overflow', await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2));

  await page.click('#clusterNew');
  await page.waitForSelector('.compute-modal');
  await page.fill('.compute-modal input[name="name"]', CKEY);
  await page.selectOption('.compute-modal select[name="lifecycle"]', 'persistent');
  await page.fill('.compute-modal input[name="driver_memory_mb"]', '768');
  await page.fill('.compute-modal input[name="worker_memory_mb"]', '768');
  await page.fill('.compute-modal input[name="min_workers"]', '1');
  await page.fill('.compute-modal input[name="max_workers"]', '1');
  await Promise.all([
    page.waitForResponse((r) => r.url().includes('/spark-clusters/save')),
    page.click('.compute-modal button[type="submit"]'),
  ]);
  await page.waitForTimeout(1200);
  await page.goto(BASE + '/data-engineering/spark-clusters?environment=DEV', { waitUntil: 'networkidle' });
  await page.waitForTimeout(800);

  const cid = await page.evaluate((k) => {
    const r = [...document.querySelectorAll('tr.compute-row')].find((x) => x.textContent.includes(k));
    return r ? r.getAttribute('data-cluster-id') : null;
  }, CKEY);
  check('All-Purpose cluster row created', !!cid, CKEY);

  const rowSel = `tr.compute-row[data-cluster-id="${cid}"]`;
  let started = false;
  try {
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('/spark-clusters/start'), { timeout: 150000 }),
      page.click(`${rowSel} .cluster-toggle`),
    ]);
    started = true;
  } catch (e) { check('cluster start responded', false, String(e)); }

  if (started) {
    for (let i = 0; i < 45; i++) {
      await page.waitForTimeout(3000);
      const st = await page.evaluate((s) => (document.querySelector(s + ' .cluster-state') || {}).textContent, rowSel);
      if (st === 'RUNNING') { break; }
    }
    const info = await page.evaluate(() => {
      const d = document.querySelector('.compute-detail-row');
      const c = document.querySelector('.compute-page .content');
      return {
        present: !!d,
        inFlow: d && c ? d.getBoundingClientRect().left >= c.getBoundingClientRect().left - 1 : null,
        containerRows: document.querySelectorAll('.compute-detail-body table tbody tr').length,
        timerBalance: window.__sparkMon ? window.__sparkMon.starts - window.__sparkMon.stops : null,
      };
    });
    check('inline monitor renders inside the layout (not clipped)', info.present && info.inFlow === true, info);
    check('monitor lists the cluster containers', info.containerRows >= 3, info);
    check('exactly one live monitor interval (no leak)', info.timerBalance === 1, info);

    page.once('dialog', (d) => d.accept());
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('/spark-clusters/stop'), { timeout: 60000 }),
      page.click(`${rowSel} .cluster-toggle`),
    ]);
    await page.waitForTimeout(3000);
    const afterStop = await page.evaluate((s) => {
      const row = document.querySelector(s);
      return { pill: row.querySelector('.cluster-state').textContent, toggle: row.querySelector('.cluster-toggle').textContent.trim() };
    }, rowSel);
    check('Stop flips the state pill to STOPPED', afterStop.pill === 'STOPPED', afterStop);
    check('Stop restores the Start button', /Start/.test(afterStop.toggle), afterStop);
  }

  // cleanup the cluster
  page.once('dialog', (d) => d.accept());
  await page.click(`${rowSel} .compute-menu-btn`).catch(() => {});
  await page.waitForTimeout(300);
  await page.click(`${rowSel} a[data-act="delete"]`).catch(() => {});
  await page.waitForTimeout(2000);

  // ---- Create Job Spark panel --------------------------------------
  await page.goto(BASE + '/jobCreation', { waitUntil: 'networkidle' });
  await page.waitForTimeout(700);
  await page.evaluate(() => {
    const cb = document.getElementById('sparkJob');
    if (cb && !cb.checked) { cb.checked = true; cb.dispatchEvent(new Event('change', { bubbles: true })); }
  });
  await page.waitForTimeout(1200);
  const panel = await page.evaluate(() => {
    const ta = document.getElementById('sparkJobInlineCode');
    return {
      visible: !!document.getElementById('runSparkJob'),
      modeRadios: document.querySelectorAll('input[name="sparkJobMode"]').length,
      preview: !!document.getElementById('sparkJobSourcePreview'),
      textareaHidden: ta ? ta.offsetParent === null : 'missing',
      cron: !!document.getElementById('sparkJobCron'),
      openBtn: /VS Code/.test((document.getElementById('sparkJobVsCodeBtn') || {}).textContent || ''),
    };
  });
  check('Spark panel present', panel.visible, panel);
  check('has Interactive/Batch mode radios', panel.modeRadios === 2, panel);
  check('has a read-only job.py preview and NO visible code textarea', panel.preview && panel.textareaHidden === true, panel);
  check('no Schedule field on the panel', !panel.cron, panel);
  check('has an "Open in VS Code" action', panel.openBtn, panel);

  check('no unexpected console / page errors', errors.length === 0, errors);

  await browser.close();
  if (failures) {
    console.log('\n' + failures + ' Spark UI check(s) failed.');
    process.exit(1);
  }
  console.log('\nSpark UI e2e checks passed.');
})().catch((e) => { console.error('FATAL', e); process.exit(1); });
