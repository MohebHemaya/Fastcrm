import { test, expect, type Page } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

interface ViewportConfig {
  name: string;
  width: number;
  height: number;
  isTouch: boolean;
}

const TARGET_VIEWPORTS: ViewportConfig[] = [
  { name: 'phone-360', width: 360, height: 640, isTouch: true },
  { name: 'phone-375', width: 375, height: 667, isTouch: true },
  { name: 'phone-414', width: 414, height: 896, isTouch: true },
  { name: 'phone-landscape-667', width: 667, height: 375, isTouch: true },
  { name: 'tablet-768', width: 768, height: 1024, isTouch: true },
  { name: 'tablet-1024', width: 1024, height: 768, isTouch: false },
  { name: 'desktop-1280', width: 1280, height: 800, isTouch: false },
  { name: 'desktop-1536', width: 1536, height: 864, isTouch: false },
];

interface VerificationFinding {
  page: string;
  viewport: string;
  width: number;
  height: number;
  hasHorizontalScroll: boolean;
  scrollWidth: number;
  clientWidth: number;
  offendingOverflowElements: string[];
  smallTouchTargetsCount: number;
  smallTouchTargetsSample: string[];
  consoleErrors: string[];
  screenshotPath: string;
}

const findings: VerificationFinding[] = [];
const AFTER_DIR = path.resolve(process.cwd(), '../docs/responsive/after');
const allowlistPath = path.resolve(process.cwd(), 'e2e/allowlist.json');
let allowlistData = { frameworkPatterns: [] as { selector: string; reason: string }[] };
if (fs.existsSync(allowlistPath)) {
  try {
    allowlistData = JSON.parse(fs.readFileSync(allowlistPath, 'utf-8'));
  } catch (e) {
    console.error('Failed to parse allowlist.json', e);
  }
}

async function loginOnce(page: Page) {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill('admin@example.com');
  await page.locator('input[name="password"]').fill('password123');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });
}

async function verifyCurrentView(
  page: Page,
  pageName: string,
  vp: ViewportConfig,
  consoleErrors: string[]
): Promise<VerificationFinding> {
  await page.waitForTimeout(600); // Allow render/animation to settle

  const screenshotFile = `${pageName}_${vp.name}.png`;
  const screenshotPath = path.join(AFTER_DIR, screenshotFile);
  await page.screenshot({ path: screenshotPath, fullPage: false });

  const metrics = await page.evaluate(
    ({ isTouch, patterns }) => {
      const doc = document.documentElement;
      const body = document.body;
      const clientWidth = doc.clientWidth || window.innerWidth;
      const scrollWidth = Math.max(doc.scrollWidth, body.scrollWidth);
      const hasHorizontalScroll = scrollWidth > clientWidth + 2;

      // 1. Offending horizontal overflow elements
      const offending: string[] = [];
      if (hasHorizontalScroll) {
        const allEls = document.querySelectorAll('*');
        for (const el of allEls) {
          const rect = el.getBoundingClientRect();
          if (rect.width === 0 || rect.height === 0) continue;
          const style = window.getComputedStyle(el);
          if (style.display === 'none' || style.visibility === 'hidden') continue;

          if (rect.right > clientWidth + 3 || rect.left < -3) {
            const tag = el.tagName.toLowerCase();
            const id = el.id ? `#${el.id}` : '';
            const cls =
              el.className && typeof el.className === 'string'
                ? `.${el.className.split(' ').filter((c) => !c.includes(':') && c.length < 25).slice(0, 2).join('.')}`
                : '';
            offending.push(
              `${tag}${id}${cls} (w:${Math.round(rect.width)}, left:${Math.round(rect.left)}, right:${Math.round(rect.right)})`
            );
            if (offending.length >= 6) break;
          }
        }
      }

      // 2. Touch targets < 44x44
      const smallTargets: string[] = [];
      if (isTouch) {
        const interactives = document.querySelectorAll(
          'button, a, input, select, textarea, [role="button"], [role="checkbox"], [role="switch"], [role="tab"]'
        );

        for (const el of interactives) {
          const rect = el.getBoundingClientRect();
          if (rect.width === 0 || rect.height === 0) continue;
          const style = window.getComputedStyle(el);
          if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') continue;

          // Check if matches allowlisted framework pattern
          let isAllowlisted = false;
          for (const item of patterns) {
            try {
              if (el.matches(item.selector)) {
                isAllowlisted = true;
                break;
              }
            } catch (_) {}
          }
          if (isAllowlisted) continue;

          // Check if parent container (e.g. HeroUI input-wrapper, label, or button wrapper) satisfies >= 43.5
          const parentWrapper = el.closest('div[data-slot="input-wrapper"], label, [role="button"], button, a');
          if (parentWrapper && parentWrapper !== el) {
            const pRect = parentWrapper.getBoundingClientRect();
            if (pRect.width >= 43.5 && pRect.height >= 43.5) {
              continue;
            }
          }

          // Strict threshold: width >= 43.5 and height >= 43.5
          if (rect.width < 43.5 || rect.height < 43.5) {
            const tag = el.tagName.toLowerCase();
            const text = (
              el.textContent ||
              (el as HTMLInputElement).placeholder ||
              el.getAttribute('aria-label') ||
              ''
            )
              .trim()
              .replace(/\s+/g, ' ')
              .slice(0, 25);
            const cls =
              el.className && typeof el.className === 'string'
                ? `.${el.className.split(' ').filter((c) => !c.includes(':') && c.length < 20).slice(0, 2).join('.')}`
                : '';
            smallTargets.push(`${tag}${cls} "${text}" (${Math.round(rect.width)}x${Math.round(rect.height)}px)`);
          }
        }
      }

      return {
        hasHorizontalScroll,
        scrollWidth,
        clientWidth,
        offending,
        smallTargetsCount: smallTargets.length,
        smallTargetsSample: smallTargets.slice(0, 8),
      };
    },
    { isTouch: vp.isTouch, patterns: allowlistData.frameworkPatterns || [] }
  );

  const finding: VerificationFinding = {
    page: pageName,
    viewport: vp.name,
    width: vp.width,
    height: vp.height,
    hasHorizontalScroll: metrics.hasHorizontalScroll,
    scrollWidth: metrics.scrollWidth,
    clientWidth: metrics.clientWidth,
    offendingOverflowElements: metrics.offending,
    smallTouchTargetsCount: metrics.smallTargetsCount,
    smallTouchTargetsSample: metrics.smallTargetsSample,
    consoleErrors: [...consoleErrors],
    screenshotPath,
  };

  findings.push(finding);

  // Assertions for every single view
  expect(
    metrics.hasHorizontalScroll,
    `[${vp.name}] Horizontal body overflow detected on page "${pageName}". scrollWidth=${metrics.scrollWidth}, clientWidth=${metrics.clientWidth}. Offending elements: ${metrics.offending.join(', ')}`
  ).toBe(false);

  expect(
    consoleErrors.filter((e) => !e.includes('401') && !e.includes('favicon')),
    `[${vp.name}] Uncaught console errors on page "${pageName}": ${consoleErrors.join(' | ')}`
  ).toHaveLength(0);

  return finding;
}

test.describe.serial('Responsive Verification Suite (Step 3)', () => {
  test.beforeAll(() => {
    if (!fs.existsSync(AFTER_DIR)) {
      fs.mkdirSync(AFTER_DIR, { recursive: true });
    }
  });

  test.afterAll(() => {
    const resultsFile = path.resolve(process.cwd(), 'e2e/verification-results.json');
    fs.writeFileSync(resultsFile, JSON.stringify(findings, null, 2), 'utf-8');
    console.log(`\nVerification complete! Total views verified: ${findings.length}. Results saved to ${resultsFile}\n`);
  });

  for (const vp of TARGET_VIEWPORTS) {
    test(`Verify Responsiveness on ${vp.name} (${vp.width}x${vp.height})`, async ({ page }) => {
      const consoleErrors: string[] = [];
      page.on('console', (msg) => {
        if (msg.type() === 'error') {
          consoleErrors.push(msg.text());
        }
      });
      page.on('pageerror', (err) => {
        consoleErrors.push(err.message);
      });

      await page.setViewportSize({ width: vp.width, height: vp.height });

      // 1. Login Page
      consoleErrors.length = 0;
      await page.goto('/login');
      await verifyCurrentView(page, 'login', vp, consoleErrors);

      // Log in for protected routes
      await loginOnce(page);

      // 2. Dashboard / Home Page
      consoleErrors.length = 0;
      await page.goto('/');
      await verifyCurrentView(page, 'dashboard', vp, consoleErrors);

      // 3. Drivers Page
      consoleErrors.length = 0;
      await page.goto('/drivers');
      await verifyCurrentView(page, 'drivers-list', vp, consoleErrors);

      // 3.a Drivers: Driver Detail Drawer
      const driverTarget = page.locator('tbody tr, [data-testid="data-table-cards"] > div').first();
      if ((await driverTarget.count()) > 0) {
        await driverTarget.click();
        await page.waitForTimeout(400);
        await verifyCurrentView(page, 'drivers-drawer', vp, consoleErrors);
        await page.keyboard.press('Escape');
        await page.waitForTimeout(300);
      }

      // 4. WhatsApp Messages Page
      consoleErrors.length = 0;
      await page.goto('/messages');
      await verifyCurrentView(page, 'messages', vp, consoleErrors);

      // 5. Import Wizard
      consoleErrors.length = 0;
      await page.goto('/import');
      await verifyCurrentView(page, 'import-wizard', vp, consoleErrors);

      // 6. Questions & Questionnaire Settings
      consoleErrors.length = 0;
      await page.goto('/questions');
      await verifyCurrentView(page, 'questions-list', vp, consoleErrors);

      // 6.a Add Question Modal (Question Editor + Live WhatsApp Preview)
      const addQuestionBtn = page.getByRole('button', { name: /إضافة سؤال جديد/i });
      if ((await addQuestionBtn.count()) > 0) {
        await addQuestionBtn.click();
        await page.waitForTimeout(500);
        await verifyCurrentView(page, 'question-modal', vp, consoleErrors);
        await page.keyboard.press('Escape');
        await page.waitForTimeout(300);
      }

      // 7. Work Orders List Page
      consoleErrors.length = 0;
      await page.goto('/work-orders');
      await verifyCurrentView(page, 'work-orders-list', vp, consoleErrors);

      // 8. Create Work Order Wizard (Steps 1, 2, 3, 4)
      consoleErrors.length = 0;
      await page.goto('/work-orders/new');
      await verifyCurrentView(page, 'work-order-create-step1', vp, consoleErrors);

      // Fill Step 1 to advance to Step 2
      const fromSelect = page.locator('button[aria-label*="المحافظة"]').first();
      if ((await fromSelect.count()) > 0) {
        await fromSelect.click();
        await page.waitForTimeout(200);
        await page.locator('[role="listbox"] [role="option"]').first().click();
        await page.waitForTimeout(200);
      }
      const citySelect = page.locator('button[aria-label*="المدينة"]').first();
      if ((await citySelect.count()) > 0 && !(await citySelect.isDisabled())) {
        await citySelect.click();
        await page.waitForTimeout(200);
        await page.locator('[role="listbox"] [role="option"]').first().click();
        await page.waitForTimeout(200);
      }
      // Fill to location
      const toGovSelect = page.locator('button[aria-label*="المحافظة"]').nth(1);
      if ((await toGovSelect.count()) > 0) {
        await toGovSelect.click();
        await page.waitForTimeout(200);
        await page.locator('[role="listbox"] [role="option"]').nth(1).click();
        await page.waitForTimeout(200);
      }
      const toCitySelect = page.locator('button[aria-label*="المدينة"]').nth(1);
      if ((await toCitySelect.count()) > 0 && !(await toCitySelect.isDisabled())) {
        await toCitySelect.click();
        await page.waitForTimeout(200);
        await page.locator('[role="listbox"] [role="option"]').first().click();
        await page.waitForTimeout(200);
      }

      // Fill hidden date if needed
      await page.locator('input[aria-label*="تاريخ وموعد"]').fill('2026-10-10T14:00');

      const nextBtn = page.getByRole('button', { name: 'التالي' });
      if ((await nextBtn.count()) > 0) {
        await nextBtn.click();
        await page.waitForTimeout(400);
        await verifyCurrentView(page, 'work-order-create-step2', vp, consoleErrors);

        // Step 3
        await nextBtn.click();
        await page.waitForTimeout(500);
        await verifyCurrentView(page, 'work-order-create-step3', vp, consoleErrors);

        // Step 4
        await nextBtn.click();
        await page.waitForTimeout(500);
        await verifyCurrentView(page, 'work-order-create-step4', vp, consoleErrors);
      }

      // 9. Work Order Detail Page
      consoleErrors.length = 0;
      await page.goto('/work-orders/1');
      await verifyCurrentView(page, 'work-order-detail-tab-recipients', vp, consoleErrors);

      // Switch to Timeline tab
      const timelineTab = page.getByRole('tab', { name: /سجل الأحداث/i });
      if ((await timelineTab.count()) > 0) {
        await timelineTab.click();
        await page.waitForTimeout(400);
        await verifyCurrentView(page, 'work-order-detail-tab-timeline', vp, consoleErrors);
      }

      // 10. Reports / Exports Page & Export Modal
      consoleErrors.length = 0;
      await page.goto('/reports');
      await verifyCurrentView(page, 'reports', vp, consoleErrors);

      const exportModalBtn = page.getByRole('button', { name: /تصدير|تحميل/i }).first();
      if ((await exportModalBtn.count()) > 0) {
        await exportModalBtn.click();
        await page.waitForTimeout(400);
        await verifyCurrentView(page, 'export-modal', vp, consoleErrors);
        await page.keyboard.press('Escape');
        await page.waitForTimeout(300);
      }

      // 11. Settings Page
      consoleErrors.length = 0;
      await page.goto('/settings');
      await verifyCurrentView(page, 'settings-general', vp, consoleErrors);

      // 12. Activity Logs Page
      consoleErrors.length = 0;
      await page.goto('/activity-logs');
      await verifyCurrentView(page, 'activity-logs', vp, consoleErrors);

      // 13. System Health Page
      consoleErrors.length = 0;
      await page.goto('/health');
      await verifyCurrentView(page, 'health', vp, consoleErrors);

      // 14. 404 Not Found Page
      consoleErrors.length = 0;
      await page.goto('/some-nonexistent-page-url');
      await verifyCurrentView(page, 'not-found', vp, consoleErrors);
    });
  }
});
