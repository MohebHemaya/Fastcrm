import pkg from '../frontend/node_modules/playwright/index.js';
const { chromium } = pkg;
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const screensDir = path.resolve(__dirname, '../docs/audit/screens');

async function main() {
  console.log('Launching browser to capture audit screenshots...');
  const browser = await chromium.launch({ headless: true });

  // 1. Mobile Login
  const mobileContext = await browser.newContext({
    viewport: { width: 375, height: 812 },
    locale: 'ar-EG',
  });
  const mobilePage = await mobileContext.newPage();
  await mobilePage.goto('http://localhost:5173/login');
  await mobilePage.waitForLoadState('networkidle');
  await mobilePage.screenshot({ path: path.join(screensDir, 'login-mobile.png'), fullPage: true });
  console.log('  Saved login-mobile.png');
  await mobileContext.close();

  // 2. Desktop Context & Login
  const desktopContext = await browser.newContext({
    viewport: { width: 1280, height: 800 },
    locale: 'ar-EG',
  });
  const page = await desktopContext.newPage();
  await page.goto('http://localhost:5173/login');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: path.join(screensDir, 'login-desktop.png'), fullPage: true });
  console.log('  Saved login-desktop.png');

  // Fill login form
  console.log('Logging in as admin@example.com...');
  await page.locator('input[name="email"]').fill('admin@example.com');
  await page.locator('input[name="password"]').fill('password123');
  await page.locator('button[type="submit"]').click();

  // Wait for redirect to home
  await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });
  await page.waitForLoadState('networkidle');

  // 3. Desktop Dashboard
  await page.screenshot({ path: path.join(screensDir, 'dashboard-desktop.png'), fullPage: true });
  console.log('  Saved dashboard-desktop.png');

  // 4. Tablet Dashboard
  await page.setViewportSize({ width: 768, height: 1024 });
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(screensDir, 'dashboard-tablet.png'), fullPage: true });
  console.log('  Saved dashboard-tablet.png');

  // 5. Mobile Dashboard
  await page.setViewportSize({ width: 375, height: 812 });
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(screensDir, 'dashboard-mobile.png'), fullPage: true });
  console.log('  Saved dashboard-mobile.png');

  // Reset to desktop for other pages
  await page.setViewportSize({ width: 1280, height: 800 });

  // 6. Drivers Page
  await page.goto('http://localhost:5173/drivers');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(screensDir, 'drivers-desktop.png'), fullPage: true });
  console.log('  Saved drivers-desktop.png');

  // 7. Work Orders Page
  await page.goto('http://localhost:5173/work-orders');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(screensDir, 'work-orders-desktop.png'), fullPage: true });
  console.log('  Saved work-orders-desktop.png');

  // 8. Questions / Settings Page
  await page.goto('http://localhost:5173/questions');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(screensDir, 'questions-desktop.png'), fullPage: true });
  console.log('  Saved questions-desktop.png');

  // 9. Reports Page
  await page.goto('http://localhost:5173/reports');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(screensDir, 'reports-desktop.png'), fullPage: true });
  console.log('  Saved reports-desktop.png');

  // 10. Health Page
  await page.goto('http://localhost:5173/health');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(screensDir, 'health-desktop.png'), fullPage: true });
  console.log('  Saved health-desktop.png');

  await browser.close();
  console.log('All audit screenshots captured successfully!');
}

main().catch(err => {
  console.error('Error capturing screenshots:', err);
  process.exit(1);
});
