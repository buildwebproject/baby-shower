#!/usr/bin/env node

import { mkdir, rename, copyFile, unlink } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { spawn } from 'node:child_process';
import { chromium } from 'playwright';

const DEFAULT_PORT = Number(process.env.RECORD_PORT || 8099);
const OUTPUT_DIR = process.env.RECORD_OUT_DIR || join(process.cwd(), 'storage', 'video');

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
  const configuredUrl = (process.env.RECORD_URL || '').trim();
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

async function safeMoveFile(fromPath, toPath) {
  try {
    await rename(fromPath, toPath);
  } catch (_) {
    await copyFile(fromPath, toPath);
    await unlink(fromPath);
  }
}

async function run() {
  await mkdir(OUTPUT_DIR, { recursive: true });

  const { server, url } = startPhpServerIfNeeded();
  if (!(await waitForUrl(url))) {
    if (server) server.kill('SIGTERM');
    throw new Error(`Unable to open URL for recording: ${url}`);
  }

  let browser;
  try {
    browser = await chromium.launch({
      headless: true,
      args: ['--autoplay-policy=no-user-gesture-required'],
    });

    const context = await browser.newContext({
      viewport: { width: 430, height: 932 },
      recordVideo: {
        dir: OUTPUT_DIR,
        size: { width: 430, height: 932 },
      },
    });

    const page = await context.newPage();
    const video = page.video();

    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForSelector('body', { timeout: 15000 });
    await sleep(1200);

    const openBtn = page.locator('#openCoverBtn');
    if (await openBtn.count()) {
      await openBtn.first().click({ force: true });
      await sleep(3200);
    }

    await sleep(1000);

    await page.evaluate(async () => {
      function stepTo(targetY, durationMs) {
        return new Promise((resolve) => {
          const startY = window.scrollY;
          const start = performance.now();
          function frame(now) {
            const t = Math.min(1, (now - start) / durationMs);
            const eased = 1 - Math.pow(1 - t, 2);
            window.scrollTo(0, startY + (targetY - startY) * eased);
            if (t < 1) requestAnimationFrame(frame);
            else resolve();
          }
          requestAnimationFrame(frame);
        });
      }

      const bottomY = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
      await stepTo(bottomY * 0.4, 2600);
      await stepTo(bottomY * 0.82, 2400);
      await stepTo(Math.max(0, bottomY - 10), 2200);
      await stepTo(0, 1800);
    });

    const babyAvatar = page.locator('#babyPlayAvatar');
    if (await babyAvatar.count()) {
      await babyAvatar.first().click({ delay: 100 });
      await sleep(900);
      await babyAvatar.first().click({ delay: 100 });
      await sleep(900);
      await babyAvatar.first().click({ delay: 100 });
      await sleep(1200);
    }

    await sleep(5200);
    await context.close();

    if (!video) {
      throw new Error('Video handle was not available.');
    }

    const rawPath = await video.path();
    const finalName = `invitation-walkthrough-${timestampLabel()}.webm`;
    const finalPath = join(OUTPUT_DIR, finalName);
    if (existsSync(rawPath)) {
      await safeMoveFile(rawPath, finalPath);
    }

    console.log(`Video created: ${finalPath}`);
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
    console.error('Then run: node scripts/record-invitation-video.mjs');
  } else {
    console.error(message);
  }
  process.exitCode = 1;
});
