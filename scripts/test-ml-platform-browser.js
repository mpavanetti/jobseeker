/* Real-browser check for the ML platform. Drives every screen with a headless
 * Chromium, captures JS console errors + PHP/CI error markers + failed
 * requests, exercises the core flows (dataset register+profile+explore, job
 * builder -> bind dataset -> save -> build per-job image -> Test run, run-detail
 * tabs, model + monitor create) and screenshots each step. Exits non-zero on
 * any problem.
 *
 * Needs the docker-compose stack up and `jobseeker/ml-runtime:cpu` built
 * (`bash scripts/build-ml-runtimes.sh`). Run it with Playwright's browser image:
 *
 *   D=$(mktemp -d) && cp scripts/test-ml-platform-browser.js "$D/" \
 *     && (cd "$D" && npm i playwright-core@1.48.0 --silent) \
 *     && docker run --rm --network host -v "$D":/pw -w /pw \
 *          -e PLAYWRIGHT_BROWSERS_PATH=/ms-playwright \
 *          mcr.microsoft.com/playwright:v1.48.0-noble node test-ml-platform-browser.js
 *
 * Env: BASE (default http://localhost), PW_OUT (screenshot dir, default ./shots).
 */
const { chromium } = require('playwright-core');
const fs = require('fs');

const BASE = process.env.BASE || 'http://localhost';
const OUT = process.env.PW_OUT || './shots';
fs.mkdirSync(OUT, { recursive: true });

const problems = [];
function rec(where, kind, detail) { problems.push({ where, kind, detail }); console.log(`  [${kind}] ${where}: ${detail}`); }

async function main() {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();

  let cur = 'startup';
  page.on('console', (m) => {
    if (m.type() === 'error') {
      const t = m.text();
      if (/favicon|Failed to load resource/i.test(t)) return;
      rec(cur, 'console', t);
    }
  });
  page.on('pageerror', (e) => rec(cur, 'pageerror', String(e)));
  page.on('requestfailed', (r) => {
    const u = r.url();
    if (/favicon|\.map$/.test(u)) return;
    rec(cur, 'reqfail', u + ' ' + (r.failure() && r.failure().errorText));
  });

  async function go(path, name) {
    cur = name;
    console.log('== ' + name + ' ==');
    const resp = await page.goto(BASE + path, { waitUntil: 'networkidle', timeout: 30000 }).catch((e) => { rec(name, 'nav', String(e)); return null; });
    if (resp && resp.status() >= 400) rec(name, 'http', resp.status() + ' ' + path);
    const body = await page.content();
    for (const marker of ['A PHP Error was encountered', 'An uncaught Exception', 'Fatal error', 'Call to undefined', "doesn't exist", 'Unknown column', 'Parse error']) {
      if (body.includes(marker)) rec(name, 'php', marker);
    }
    await page.screenshot({ path: `${OUT}/${name}.png`, fullPage: true }).catch(() => {});
  }

  // --- login ---
  cur = 'login';
  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.fill('input[name=email]', 'admin@example.com');
  await page.fill('input[name=password]', '123456');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('input[type=submit]')]);
  if (!/dashboard/i.test(page.url())) rec('login', 'flow', 'did not land on dashboard: ' + page.url());

  // --- every screen ---
  await go('/dashboard', 'dashboard');
  await go('/machine-learning/overview', 'overview');
  await go('/machine-learning/runtimes', 'runtimes');
  await go('/machine-learning/samples', 'samples');
  await go('/machine-learning/datasets', 'datasets');
  await go('/machine-learning/jobs', 'jobs_new');
  await go('/machine-learning/runs', 'runs');
  await go('/machine-learning/models', 'models');
  await go('/machine-learning/monitoring', 'monitoring');

  const suffix = Date.now().toString().slice(-6);

  // --- flow: dataset create + version + explore ---
  try {
    cur = 'dataset_create';
    await page.goto(BASE + '/machine-learning/datasets', { waitUntil: 'networkidle' });
    await page.fill('#datasetForm [name=name]', 'HB DS ' + suffix);
    await page.fill('#datasetForm [name=dataset_key]', 'hb-ds-' + suffix);
    await page.click('#datasetForm button[type=submit]');
    await page.waitForTimeout(1500);
    // page reloads on success
    await page.goto(BASE + '/machine-learning/datasets', { waitUntil: 'networkidle' });
    const hasRow = await page.locator('text=hb-ds-' + suffix).count();
    if (!hasRow) rec('dataset_create', 'flow', 'new dataset row not visible after save');

    // register a version via the modal
    const csv = 'f0,f1,f2,species\n' + Array.from({ length: 120 }, () => {
      const c = Math.floor(Math.random() * 3);
      return [0, 1, 2].map(() => (c + Math.random()).toFixed(3)).join(',') + ',' + c;
    }).join('\n');
    fs.writeFileSync('./ds.csv', csv);
    cur = 'dataset_version';
    const addBtn = page.locator('.js-add-version').first();
    if (await addBtn.count()) {
      await addBtn.click();
      await page.waitForTimeout(400);
      await page.setInputFiles('#versionForm [name=file]', './ds.csv');
      await page.click('#submitVersion');
      await page.waitForTimeout(3000);
    } else {
      rec('dataset_version', 'flow', 'no .js-add-version button');
    }
    await page.goto(BASE + '/machine-learning/datasets', { waitUntil: 'networkidle' });
    // find the explore link
    const exploreLink = page.locator('a[href*="/machine-learning/datasets/explore/"]').first();
    if (await exploreLink.count()) {
      cur = 'dataset_explore';
      await exploreLink.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1500);
      const canvases = await page.locator('canvas').count();
      if (!canvases) rec('dataset_explore', 'flow', 'no chart canvases rendered');
      const previewRows = await page.locator('#previewTable tbody tr').count();
      if (!previewRows) rec('dataset_explore', 'flow', 'preview table empty');
      await page.screenshot({ path: `${OUT}/dataset_explore.png`, fullPage: true });
    } else {
      rec('dataset_explore', 'flow', 'no explore link');
    }
  } catch (e) { rec(cur, 'exc', String(e)); }

  // --- flow: job builder ---
  try {
    cur = 'job_builder';
    page.on('dialog', (d) => d.accept(d.type() === 'prompt' ? (d.message().includes('role') ? 'training' : (d.message().includes('Version') ? 'latest' : '')) : undefined));
    await page.goto(BASE + '/machine-learning/jobs', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1200);
    // pick the first sample card
    const card = page.locator('.ml-sample-card').first();
    if (await card.count()) { await card.click(); await page.waitForTimeout(600); }
    else rec('job_builder', 'flow', 'no sample cards');
    await page.fill('.ml-authoring-form [name=name]', 'HB Job ' + suffix);
    await page.fill('.ml-authoring-form [name=job_key]', 'hb-job-' + suffix);
    // env + runtime selects should be populated
    const envOpts = await page.locator('.ml-authoring-form [name=environment] option').count();
    const rtOpts = await page.locator('.ml-authoring-form [name=runtime_key] option').count();
    if (!envOpts) rec('job_builder', 'flow', 'environment select empty');
    if (!rtOpts) rec('job_builder', 'flow', 'runtime select empty');
    await page.selectOption('.ml-authoring-form [name=environment]', { index: 0 }).catch(() => {});
    // file tabs present
    const tabs = await page.locator('.ml-editor-tab').count();
    if (tabs < 2) rec('job_builder', 'flow', 'file tabs missing (' + tabs + ')');
    const codeLen = (await page.locator('.js-code').inputValue()).length;
    if (codeLen < 20) rec('job_builder', 'flow', 'code editor empty after sample pick');
    // bind a dataset
    const bindBtn = page.locator('.ml-ds-picker .js-bind').first();
    if (await bindBtn.count()) { await bindBtn.click(); await page.waitForTimeout(500); }
    else rec('job_builder', 'flow', 'dataset picker has no rows / bind buttons');
    // save (the dedicated Jobs screen redirects to ?id=<new> on a fresh save)
    await Promise.all([
      page.waitForNavigation({ timeout: 8000 }).catch(() => {}),
      page.click('.ml-authoring-form .js-save'),
    ]);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);
    if (!/[?&]id=\d+/.test(page.url())) rec('job_builder', 'flow', 'did not redirect to edit mode after save: ' + page.url());
    await page.screenshot({ path: `${OUT}/job_builder.png`, fullPage: true });

    // build image
    cur = 'job_build_image';
    const pillCount = await page.locator('.js-image-pill').count();
    const jobId = await page.locator('.ml-authoring-form [name=id]').inputValue();
    console.log('  .js-image-pill count=' + pillCount + ' jobId=' + JSON.stringify(jobId));
    if (pillCount !== 1) rec('job_build_image', 'flow', '.js-image-pill count=' + pillCount + ' (bar html: ' + (await page.locator('.ml-editor-bar').first().innerHTML().catch(() => '?')).slice(0, 300) + ')');
    if (!jobId) rec('job_build_image', 'flow', 'job has no id after save');
    await page.click('.js-build');
    let pill = '';
    for (let i = 0; i < 80; i++) {
      await page.waitForTimeout(3000);
      pill = (await page.locator('.js-image-pill').first().textContent({ timeout: 5000 }).catch(() => '(gone)')) || '';
      if (!/building/i.test(pill)) break;
    }
    if (!/ready/i.test(pill)) rec('job_build_image', 'flow', 'image pill did not reach ready: ' + pill +
      ' | image-status: ' + JSON.stringify(await page.evaluate(async (id) => {
        try { return await (await fetch('/machine-learning/jobs/image-status/' + id)).json(); } catch (e) { return String(e); }
      }, jobId)));
    await page.screenshot({ path: `${OUT}/job_build_image.png`, fullPage: true });

    // test run
    cur = 'job_test_run';
    await page.click('.js-save-run');
    let consoleTxt = '';
    for (let i = 0; i < 50; i++) {
      await page.waitForTimeout(3000);
      consoleTxt = (await page.locator('.js-run-console .ml-console').textContent().catch(() => '')) || '';
      const phase = (await page.locator('.js-run-console .ml-console-phase').textContent().catch(() => '')) || '';
      if (/SUCCEEDED|FAILED|TIMED_OUT|CANCELLED/.test(phase)) { consoleTxt = phase + '\n' + consoleTxt; break; }
    }
    if (!/SUCCEEDED/.test(consoleTxt)) rec('job_test_run', 'flow', 'test run not SUCCEEDED. tail: ' + consoleTxt.slice(-300));
    await page.screenshot({ path: `${OUT}/job_test_run.png`, fullPage: true });
  } catch (e) { rec(cur, 'exc', String(e)); }

  // --- run detail tabs ---
  try {
    cur = 'run_detail';
    await page.goto(BASE + '/machine-learning/runs', { waitUntil: 'networkidle' });
    const runLink = page.locator('a[href*="/machine-learning/runs/detail/"]').first();
    if (await runLink.count()) {
      await runLink.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1500);
      for (const tab of ['Metrics', 'Console', 'Lineage', 'Overview']) {
        await page.click(`a[data-toggle=tab]:has-text("${tab}")`).catch((e) => rec('run_detail', 'flow', 'tab ' + tab + ': ' + e));
        await page.waitForTimeout(600);
      }
      await page.screenshot({ path: `${OUT}/run_detail.png`, fullPage: true });
    } else { rec('run_detail', 'flow', 'no run links'); }
  } catch (e) { rec(cur, 'exc', String(e)); }

  // --- models + monitoring create ---
  try {
    cur = 'model_create';
    await page.goto(BASE + '/machine-learning/models', { waitUntil: 'networkidle' });
    await page.fill('#modelForm [name=name]', 'HB Model ' + suffix);
    await page.fill('#modelForm [name=model_key]', 'hb-model-' + suffix);
    await page.click('#modelForm button[type=submit]');
    await page.waitForTimeout(2000);

    cur = 'monitor_create';
    await page.goto(BASE + '/machine-learning/monitoring', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1200);
    const gridCells = await page.locator('#statusGrid .ml-status-cell, #statusGrid .ml-muted').count();
    if (!gridCells) rec('monitor_create', 'flow', 'status grid never populated');
    const modelSel = await page.locator('#monitorForm [name=model_id] option').count();
    if (modelSel) {
      await page.fill('#monitorForm [name=name]', 'HB Mon ' + suffix);
      await page.click('#monitorForm button[type=submit]');
      await page.waitForTimeout(2000);
    } else { rec('monitor_create', 'flow', 'no models in monitor form'); }
    await page.screenshot({ path: `${OUT}/monitoring.png`, fullPage: true });
  } catch (e) { rec(cur, 'exc', String(e)); }

  await browser.close();

  console.log('\n===== SUMMARY =====');
  if (!problems.length) { console.log('NO PROBLEMS FOUND'); }
  else {
    const byWhere = {};
    problems.forEach((p) => { (byWhere[p.where] = byWhere[p.where] || []).push(p); });
    Object.keys(byWhere).forEach((w) => {
      console.log('\n' + w + ':');
      byWhere[w].forEach((p) => console.log('  - [' + p.kind + '] ' + p.detail));
    });
    console.log('\nTOTAL: ' + problems.length);
  }
  fs.writeFileSync(OUT + '/../problems.json', JSON.stringify(problems, null, 2));
  process.exit(problems.length ? 1 : 0);
}

main().catch((e) => { console.error(e); process.exit(2); });
