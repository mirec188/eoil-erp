#!/usr/bin/env node
// Browser smoke check for the local eOil ERP (first slice).
//
// Drives a local headless Chrome over the DevTools protocol (Node >= 22, no npm packages).
// For desktop (1366 px) and mobile (390 px) it loads each page and reports console errors,
// failed/4xx asset requests, horizontal page overflow and whether the theme fonts loaded.
// It also exercises the mobile menu, list → detail → back, and keyboard focus order.
//
// Usage: node tools/browser-qa/cdp-smoke.mjs [baseUrl]   (default http://127.0.0.1:8088)
// Screenshots: runtime/browser-qa/ (git-ignored).

import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { setTimeout as sleep } from 'node:timers/promises';

const BASE = process.argv[2] ?? 'http://127.0.0.1:8088';
const CHROME = process.env.CHROME_BIN ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT = 9333;
const OUT = new URL('../../runtime/browser-qa/', import.meta.url).pathname;
const PROFILE = '/tmp/eoil-erp-browser-qa-profile';

const PAGES = [
  ['home', '/', 200],
  ['catalog', '/catalog', 200],
  ['catalog-p2', '/catalog?q=ukážkový&page=2', 200],
  ['catalog-html', '/catalog?q=906.01', 200],
  ['catalog-empty', '/catalog?q=neexistujúci', 200],
  ['detail-104', '/catalog/104', 200],
  ['detail-108', '/catalog/108', 200],
  ['detail-109-html', '/catalog/109?q=906.01', 200],
  ['detail-missing', '/catalog/999999', 404],
  ['invalid-page', '/catalog?page=0', 400],
  ['not-found', '/nope', 404],
];
const VIEWPORTS = [
  { name: 'desktop', width: 1366, height: 900, mobile: false, scale: 1 },
  { name: 'mobile390', width: 390, height: 844, mobile: true, scale: 2 },
];

mkdirSync(OUT, { recursive: true });
rmSync(PROFILE, { recursive: true, force: true });

const chrome = spawn(CHROME, [
  '--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${PROFILE}`,
  '--no-first-run', '--no-default-browser-check', '--disable-extensions', 'about:blank',
], { stdio: 'ignore' });

let exitCode = 0;
try {
  const target = await waitForTarget();
  const cdp = await connect(target.webSocketDebuggerUrl);
  const problems = [];
  let current = '';
  let currentPath = '/';
  let expectedDocumentStatus = 200;

  cdp.on('Runtime.consoleAPICalled', (p) => {
    if (['error', 'warning', 'assert'].includes(p.type)) {
      problems.push(`${current}: console.${p.type}: ${p.args.map((a) => a.value ?? a.description).join(' ')}`);
    }
  });
  cdp.on('Runtime.exceptionThrown', (p) => problems.push(`${current}: exception: ${p.exceptionDetails.text}`));
  cdp.on('Log.entryAdded', ({ entry }) => {
    const mainDocStatus = entry.url !== undefined && entry.url.startsWith(BASE)
      && new URL(entry.url).pathname === new URL(BASE + currentPath).pathname
      && entry.text.includes(`status of ${expectedDocumentStatus}`);
    if (['error', 'warning'].includes(entry.level) && !mainDocStatus) {
      problems.push(`${current}: log.${entry.level}: ${entry.text} ${entry.url ?? ''}`);
    }
  });
  cdp.on('Network.responseReceived', ({ type, response }) => {
    const isDocument = type === 'Document';
    if (response.status >= 400 && !(isDocument && response.status === expectedDocumentStatus)) {
      problems.push(`${current}: HTTP ${response.status} ${response.url}`);
    }
    if (!response.url.startsWith(BASE) && !response.url.startsWith('data:')) {
      problems.push(`${current}: non-local request ${response.url}`);
    }
  });
  cdp.on('Network.loadingFailed', (p) => problems.push(`${current}: loading failed ${p.errorText}`));

  for (const domain of ['Page', 'Runtime', 'Log', 'Network']) await cdp.send(`${domain}.enable`);

  for (const vp of VIEWPORTS) {
    await cdp.send('Emulation.setDeviceMetricsOverride', {
      width: vp.width, height: vp.height, deviceScaleFactor: vp.scale, mobile: vp.mobile,
    });
    for (const [name, path, status] of PAGES) {
      current = `${vp.name} ${path}`;
      currentPath = path;
      expectedDocumentStatus = status;
      await navigate(cdp, BASE + path);
      const info = await evaluate(cdp, `(async () => {
        await document.fonts.ready;
        const icon = document.querySelector('[class^="ph-"], [class*=" ph-"]');
        return {
          title: document.title,
          overflowX: document.documentElement.scrollWidth - window.innerWidth,
          phosphor: icon ? getComputedStyle(icon, '::before').fontFamily : null,
          phosphorLoaded: document.fonts.check('16px Phosphor'),
          interLoaded: [...document.fonts].filter((f) => f.family.includes('Inter') && f.status === 'loaded').map((f) => f.weight),
          bodyFont: getComputedStyle(document.body).fontFamily.split(',')[0],
          injected: document.querySelectorAll('[onerror],[onfocus],[onmouseover],script:not([src])').length,
        };
      })()`);
      const shot = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
      writeFileSync(`${OUT}${vp.name}-${name}.png`, Buffer.from(shot.data, 'base64'));
      if (info.overflowX > 0) problems.push(`${current}: horizontal page overflow ${info.overflowX}px`);
      if (!info.phosphorLoaded) problems.push(`${current}: Phosphor icon font not loaded`);
      if (info.injected > 0) problems.push(`${current}: ${info.injected} inline script/handler element(s)`);
      console.log(`${current.padEnd(40)} overflowX=${info.overflowX} phosphor=${info.phosphorLoaded} `
        + `inter=[${info.interLoaded}] font=${info.bodyFont} title="${info.title}"`);
    }
  }

  // Mobile menu toggles via Bootstrap JS (390 px viewport is still active).
  current = 'mobile390 menu';
  currentPath = '/catalog';
  expectedDocumentStatus = 200;
  await navigate(cdp, `${BASE}/catalog`);
  await evaluate(cdp, `document.querySelector('.navbar-toggler').click()`);
  await sleep(600);
  const menu = await evaluate(cdp, `({
    shown: document.querySelector('#navbar-mobile').classList.contains('show'),
    expanded: document.querySelector('.navbar-toggler').getAttribute('aria-expanded'),
    links: [...document.querySelectorAll('#navbar-mobile .navbar-nav-link')].map((a) => a.textContent.trim() + ':' + (a.offsetParent !== null)),
    overflowX: document.documentElement.scrollWidth - window.innerWidth,
  })`);
  const shot = await cdp.send('Page.captureScreenshot', { format: 'png' });
  writeFileSync(`${OUT}mobile390-menu-open.png`, Buffer.from(shot.data, 'base64'));
  console.log(`${current.padEnd(40)} ${JSON.stringify(menu)}`);
  if (!menu.shown || menu.expanded !== 'true') problems.push('mobile menu did not open');

  // List → detail → back keeps q/page.
  current = 'desktop flow';
  await cdp.send('Emulation.setDeviceMetricsOverride', { width: 1366, height: 900, deviceScaleFactor: 1, mobile: false });
  await navigate(cdp, `${BASE}/catalog?q=${encodeURIComponent('ukážkový')}&page=2`);
  await clickAndWait(cdp, `document.querySelector('a[aria-label^="Detail"]').click()`);
  const detailUrl = await evaluate(cdp, 'location.pathname + location.search');
  await clickAndWait(cdp, `[...document.querySelectorAll('a')].find((a) => a.textContent.includes('Späť na zoznam')).click()`);
  const backUrl = await evaluate(cdp, 'decodeURIComponent(location.pathname + location.search)');
  console.log(`${current.padEnd(40)} detail=${detailUrl} back=${backUrl}`);
  if (backUrl !== '/catalog?q=ukážkový&page=2') problems.push(`back link lost state: ${backUrl}`);

  // Keyboard focus order on the catalog list.
  current = 'desktop focus';
  await navigate(cdp, `${BASE}/catalog`);
  const focused = [];
  for (let i = 0; i < 8; i++) {
    await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
    await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
    focused.push(await evaluate(cdp, `(() => { const e = document.activeElement;
      const s = getComputedStyle(e); return (e.textContent.trim() || e.name || e.tagName).slice(0, 24)
        + (s.outlineStyle !== 'none' || s.boxShadow !== 'none' ? '' : ' [no focus style]'); })()`));
  }
  console.log(`${current.padEnd(40)} ${focused.join(' → ')}`);

  console.log(`\nScreenshots: ${OUT}`);
  if (problems.length > 0) {
    exitCode = 1;
    console.log(`\nProblems (${problems.length}):\n- ${[...new Set(problems)].join('\n- ')}`);
  } else {
    console.log('\nNo console errors, failed requests, non-local requests or horizontal overflow.');
  }
  cdp.close();
} catch (error) {
  exitCode = 2;
  console.error(error);
} finally {
  chrome.kill();
  process.exitCode = exitCode;
}

async function waitForTarget() {
  for (let i = 0; i < 50; i++) {
    try {
      const list = await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json();
      const page = list.find((t) => t.type === 'page');
      if (page) return page;
    } catch { /* not up yet */ }
    await sleep(200);
  }
  throw new Error('Chrome DevTools endpoint did not start');
}

async function connect(url) {
  const ws = new WebSocket(url);
  await new Promise((resolve, reject) => { ws.onopen = resolve; ws.onerror = reject; });
  let id = 0;
  const pending = new Map();
  const listeners = new Map();
  ws.onmessage = ({ data }) => {
    const msg = JSON.parse(data);
    if (msg.id && pending.has(msg.id)) {
      const { resolve, reject } = pending.get(msg.id);
      pending.delete(msg.id);
      msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result);
    } else if (msg.method) {
      (listeners.get(msg.method) ?? []).forEach((fn) => fn(msg.params));
    }
  };
  return {
    send: (method, params = {}) => new Promise((resolve, reject) => {
      pending.set(++id, { resolve, reject });
      ws.send(JSON.stringify({ id, method, params }));
    }),
    on: (method, fn) => listeners.set(method, [...(listeners.get(method) ?? []), fn]),
    once: (method) => new Promise((resolve) => {
      const fn = (p) => { listeners.set(method, listeners.get(method).filter((f) => f !== fn)); resolve(p); };
      listeners.set(method, [...(listeners.get(method) ?? []), fn]);
    }),
    close: () => ws.close(),
  };
}

async function navigate(cdp, url) {
  const loaded = cdp.once('Page.loadEventFired');
  await cdp.send('Page.navigate', { url });
  await loaded;
  await sleep(150);
}

async function clickAndWait(cdp, expression) {
  const loaded = cdp.once('Page.loadEventFired');
  await evaluate(cdp, expression);
  await loaded;
  await sleep(150);
}

async function evaluate(cdp, expression) {
  const { result, exceptionDetails } = await cdp.send('Runtime.evaluate', {
    expression, awaitPromise: true, returnByValue: true,
  });
  if (exceptionDetails) throw new Error(`evaluate failed: ${exceptionDetails.text} (${expression.slice(0, 60)})`);
  return result.value;
}
