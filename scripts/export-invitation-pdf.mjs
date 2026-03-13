#!/usr/bin/env node

import { mkdir } from 'node:fs/promises';
import { join } from 'node:path';
import { spawn } from 'node:child_process';
import { chromium } from 'playwright';

const DEFAULT_PORT = Number(process.env.PDF_PORT || 8099);
const OUTPUT_DIR = process.env.PDF_OUT_DIR || join(process.cwd(), 'storage');

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function timestampLabel() {
  const d = new Date();
  const yy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  const hh = String(d.getHours()).padStart(2, '0');
  const mi = String(d.getMinutes()).padStart(2, '0');
  const ss = String(d.getSeconds()).padStart(2, '0');
  return `${yy}${mm}${dd}-${hh}${mi}${ss}`;
}

async function waitForUrl(url, timeoutMs = 15000) {
  const start = Date.now();
  while (Date.now() - start < timeoutMs) {
    try {
      const res = await fetch(url, { method: 'GET' });
      if (res.ok) return true;
    } catch (_) {
      // Retry until timeout.
    }
    await sleep(250);
  }
  return false;
}

function startPhpServerIfNeeded() {
  const configuredUrl = (process.env.PDF_URL || process.env.RECORD_URL || '').trim();
  if (configuredUrl !== '') {
    return { server: null, url: configuredUrl };
  }

  const url = `http://127.0.0.1:${DEFAULT_PORT}/index.php`;
  const server = spawn('php', ['-S', `127.0.0.1:${DEFAULT_PORT}`, '-t', process.cwd()], {
    cwd: process.cwd(),
    stdio: 'ignore',
  });
  return { server, url };
}

async function run() {
  await mkdir(OUTPUT_DIR, { recursive: true });

  const { server, url } = startPhpServerIfNeeded();
  if (!(await waitForUrl(url))) {
    if (server) server.kill('SIGTERM');
    throw new Error(`Unable to open URL for PDF export: ${url}`);
  }

  let browser;
  try {
    browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
      viewport: { width: 430, height: 932 },
    });
    const page = await context.newPage();

    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForSelector('body', { timeout: 15000 });
    await sleep(1000);

    const openBtn = page.locator('#openCoverBtn');
    if (await openBtn.count()) {
      await openBtn.first().click({ force: true });
      await page.waitForFunction(() => document.body.classList.contains('gate-opened'), { timeout: 8000 }).catch(() => {});
      await sleep(1400);
    }

    await page.addStyleTag({
      content: `
        #openingStage,
        .mini-balloons,
        .scroll-petals,
        #babyPlayWidget,
        .util-panel,
        .balloon-layer,
        .orn {
          display: none !important;
        }
        body {
          background: #ffffff !important;
          padding: 0 !important;
        }
        .card-wrap {
          display: flex !important;
          justify-content: center !important;
          width: 100% !important;
          padding: 0 !important;
          margin: 0 !important;
        }
        #invitationCard {
          box-shadow: none !important;
          margin: 0 auto !important;
        }
      `,
    });

    await sleep(700);

    const finalPath = join(OUTPUT_DIR, `invitation-card-${timestampLabel()}.pdf`);
    await page.pdf({
      path: finalPath,
      format: 'A4',
      printBackground: true,
      margin: {
        top: '8mm',
        right: '8mm',
        bottom: '8mm',
        left: '8mm',
      },
    });

    console.log(`PDF created: ${finalPath}`);
    await context.close();
  } finally {
    if (browser) await browser.close().catch(() => {});
    if (server) server.kill('SIGTERM');
  }
}

run().catch((error) => {
  const message = error instanceof Error ? error.message : String(error);
  if (message.includes('error while loading shared libraries') || message.includes('libatk-1.0.so.0')) {
    console.error('Missing Linux browser dependencies for Playwright.');
    console.error('Run: sudo npx playwright install-deps chromium');
    console.error('Then run: npm run make:pdf');
  } else {
    console.error(message);
  }
  process.exitCode = 1;
});
