import pkg from '../frontend/node_modules/playwright/index.js';
const { chromium } = pkg;
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const outDir = path.resolve(__dirname, '../docs/ui-before');

async function main() {
  console.log('Capturing UI BEFORE screenshots...');
  const browser = await chromium.launch({ headless: true });

  const viewports = [
    { name: 'desktop', width: 1280, height: 800 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 375, height: 812 },
  ];

  // 1. Form 3: Login Page at 375, 768, 1280
  for (const vp of viewports) {
    const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height }, locale: 'ar-EG' });
    const page = await ctx.newPage();
    await page.goto('http://localhost:5173/login');
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: path.join(outDir, `login-${vp.name}.png`), fullPage: true });
    console.log(`  Saved login-${vp.name}.png`);
    await ctx.close();
  }

  // Authenticated context for sidebar & internal forms
  const authCtx = await browser.newContext({ viewport: { width: 1280, height: 800 }, locale: 'ar-EG' });
  const authPage = await authCtx.newPage();
  await authPage.goto('http://localhost:5173/login');
  await authPage.locator('input[name="email"]').fill('admin@example.com');
  await authPage.locator('input[name="password"]').fill('password123');
  await authPage.locator('button[type="submit"]').click();
  await authPage.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });
  await authPage.waitForLoadState('networkidle');

  // 2. Sidebar at 1280px (desktop), 768px (tablet), 375px (mobile with drawer opened)
  // Desktop
  await authPage.setViewportSize({ width: 1280, height: 800 });
  await authPage.waitForTimeout(500);
  await authPage.screenshot({ path: path.join(outDir, 'sidebar-desktop.png') });
  console.log('  Saved sidebar-desktop.png');

  // Tablet
  await authPage.setViewportSize({ width: 768, height: 1024 });
  await authPage.waitForTimeout(500);
  await authPage.screenshot({ path: path.join(outDir, 'sidebar-tablet.png') });
  console.log('  Saved sidebar-tablet.png');

  // Mobile with drawer open
  await authPage.setViewportSize({ width: 375, height: 812 });
  await authPage.waitForTimeout(500);
  const menuBtn = authPage.locator('button[aria-label="القائمة الجانبية"]');
  if (await menuBtn.isVisible()) {
    await menuBtn.click();
    await authPage.waitForTimeout(500);
  }
  await authPage.screenshot({ path: path.join(outDir, 'sidebar-mobile.png') });
  console.log('  Saved sidebar-mobile.png');

  // Close mobile drawer
  await authPage.keyboard.press('Escape');
  await authPage.waitForTimeout(500);

  // 3. Form 1: Create Work Order Page (/work-orders/new) at 1280, 768, 375
  for (const vp of viewports) {
    await authPage.setViewportSize({ width: vp.width, height: vp.height });
    await authPage.goto('http://localhost:5173/work-orders/new');
    await authPage.waitForLoadState('networkidle');
    await authPage.waitForTimeout(500);
    await authPage.screenshot({ path: path.join(outDir, `create-order-${vp.name}.png`), fullPage: true });
    console.log(`  Saved create-order-${vp.name}.png`);
  }

  // 4. Form 2: Questions / Settings Page (/questions) at 1280, 768, 375
  for (const vp of viewports) {
    await authPage.setViewportSize({ width: vp.width, height: vp.height });
    await authPage.goto('http://localhost:5173/questions');
    await authPage.waitForLoadState('networkidle');
    await authPage.waitForTimeout(500);
    await authPage.screenshot({ path: path.join(outDir, `questions-settings-${vp.name}.png`), fullPage: true });
    console.log(`  Saved questions-settings-${vp.name}.png`);
  }

  // 5. Add Driver Modal on /drivers page at 1280
  await authPage.setViewportSize({ width: 1280, height: 800 });
  await authPage.goto('http://localhost:5173/drivers');
  await authPage.waitForLoadState('networkidle');
  const addBtn = authPage.locator('button:has-text("إضافة سائق")');
  if (await addBtn.isVisible()) {
    await addBtn.click();
    await authPage.waitForTimeout(600);
    await authPage.screenshot({ path: path.join(outDir, 'add-driver-modal-desktop.png') });
    console.log('  Saved add-driver-modal-desktop.png');
  }

  await browser.close();
  console.log('All BEFORE screenshots captured successfully!');
}

main().catch(err => {
  console.error('Error capturing before screenshots:', err);
  process.exit(1);
});
