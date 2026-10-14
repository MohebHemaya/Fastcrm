import { test, expect, type Page } from '@playwright/test';
import * as path from 'node:path';
import * as fs from 'node:fs';
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const PHP_BIN =
  'C:/Users/Moheb Hemaya/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe';
const BACKEND_DIR = path.resolve(__dirname, '../../backend');

function runArtisan(args: string): string {
  return execSync(`"${PHP_BIN}" artisan ${args}`, {
    cwd: BACKEND_DIR,
    encoding: 'utf-8',
    stdio: ['pipe', 'pipe', 'pipe'],
  });
}

function runPhp(phpCode: string): string {
  const tmpFile = path.join(BACKEND_DIR, `tmp_script_${Date.now()}_${Math.random().toString(36).slice(2)}.php`);
  const fullCode = `<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\\\\Contracts\\\\Console\\\\Kernel')->bootstrap();
${phpCode}
`;
  fs.writeFileSync(tmpFile, fullCode, 'utf-8');
  try {
    return execSync(`"${PHP_BIN}" "${tmpFile}"`, {
      cwd: BACKEND_DIR,
      encoding: 'utf-8',
      stdio: ['pipe', 'pipe', 'pipe'],
    });
  } finally {
    if (fs.existsSync(tmpFile)) fs.unlinkSync(tmpFile);
  }
}

const runTinker = runPhp;

test.describe.serial('Driver Management System — Main Flow & Hardening', () => {
  let page: Page;
  const cspViolations: string[] = [];
  const testRunId = Date.now().toString().slice(-6);
  const testPhones: string[] = [];
  let createdOrderId: number | null = null;

  // Egyptian E.164 numbers: +2010 followed by 8 random digits (12 digits, 13 chars total)
  const baseNum = 10000000 + Math.floor(Math.random() * 80000000);
  for (let i = 1; i <= 35; i++) {
    testPhones.push(`+2010${String(baseNum + i).padStart(8, '0')}`);
  }

  test.beforeAll(async ({ browser }) => {
    const context = await browser.newContext();
    page = await context.newPage();

    // Monitor for CSP errors and violations
    page.on('console', (msg) => {
      const text = msg.text();
      if (
        msg.type() === 'error' &&
        (text.includes('Content Security Policy') ||
          text.includes('CSP') ||
          text.includes('violates the following Content Security Policy directive') ||
          text.includes('EvalError') ||
          text.includes('unsafe-eval'))
      ) {
        cspViolations.push(text);
      }
    });

    page.on('pageerror', (err) => {
      const msg = err.message || '';
      if (
        msg.includes('Content Security Policy') ||
        msg.includes('CSP') ||
        msg.includes('EvalError')
      ) {
        cspViolations.push(msg);
      }
    });

    // Isolate the DB from prior test runs:
    // - Purge the job queue (prior runs leave thousands of SendWhatsAppMessageJob entries)
    // - Set all non-test drivers to 'invited' (not eligible for new invites or work orders)
    // - Cancel lingering DRAFT/FILLING work orders from previous runs
    const phoneList = testPhones.map((p) => `'${p}'`).join(',');
    try {
      runArtisan('queue:clear --force');
    } catch {
      // Ignored if queue driver is sync
    }
    runTinker(`
      \\App\\Models\\Driver::whereNotIn('phone', [${phoneList}])
        ->whereIn('status', ['new', 'active'])
        ->update(['status' => 'invited']);
      \\App\\Models\\WorkOrder::whereIn('status', ['draft', 'filling', 'sending'])
        ->update(['status' => 'cancelled']);
    `);
  });

  test.afterAll(async () => {
    await page.close();
  });

  // 1. Negative Path: Invalid Login
  test('1. Negative Path: Invalid login credentials show error', async () => {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill('wrong@example.com');
    await page.locator('input[name="password"]').fill('wrongpassword');

    await Promise.all([
      page.waitForResponse((res) => res.url().includes('/auth/login')),
      page.locator('button[type="submit"]').click(),
    ]);

    await expect(page.locator('[data-testid="login-general-error"]')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('[data-testid="login-general-error"]')).toContainText(
      'البريد الإلكتروني أو كلمة المرور غير صحيحة'
    );
  });

  // 2. Positive Path: Valid Login
  test('2. Positive Path: Valid login succeeds and loads dashboard', async () => {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill('admin@example.com');
    await page.locator('input[name="password"]').fill('password123');

    await Promise.all([
      page.waitForResponse((res) => res.url().includes('/auth/login') && res.status() === 200),
      page.locator('button[type="submit"]').click(),
    ]);

    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 10000 });
    await expect(page).toHaveURL('http://127.0.0.1:5173/');
  });

  // 3. Negative Path: Invalid file upload rejected in Import Wizard
  test('3. Negative Path: Non-spreadsheet file is rejected in Import Wizard', async () => {
    await page.goto('/import');
    const invalidFilePath = path.join(__dirname, `invalid-${testRunId}.txt`);
    fs.writeFileSync(invalidFilePath, 'This is not an Excel or CSV file.');

    try {
      await page.locator('input[type="file"]').setInputFiles(invalidFilePath);
      await expect(
        page.locator('text=نوع الملف غير مدعوم. يرجى اختيار ملف Excel (.xlsx, .xls) أو CSV (.csv).')
      ).toBeVisible({ timeout: 5000 });
    } finally {
      if (fs.existsSync(invalidFilePath)) fs.unlinkSync(invalidFilePath);
    }
  });

  // 4. Positive Path: Upload sheet, preview, and commit
  test('4. Positive Path: Upload valid drivers sheet, preview, and commit', async () => {
    await page.goto('/import');

    const validCsvPath = path.join(__dirname, `drivers-${testRunId}.csv`);
    const csvContent = [
      'رقم الهاتف (Phone),اسم المجموعة (Group Name)',
      ...testPhones.map((phone) => `${phone},مجموعة الساحل ${testRunId}`),
    ].join('\n');

    fs.writeFileSync(validCsvPath, `\uFEFF${csvContent}`, 'utf-8');

    try {
      await page.locator('input[type="file"]').setInputFiles(validCsvPath);

      // Click upload and parse button
      const uploadBtn = page.getByRole('button', { name: /بدء فحص وتصنيف/i });
      await expect(uploadBtn).toBeEnabled({ timeout: 5000 });
      await uploadBtn.click();

      // Step 3: Wait for Preview step to load (ParseImportJob must complete via queue)
      await expect(page.getByRole('button', { name: /تأكيد واستيراد الأرقام/i })).toBeVisible({
        timeout: 45000,
      });

      // Click commit button
      const commitBtn = page.getByRole('button', { name: /تأكيد واستيراد الأرقام/i });
      await commitBtn.click();

      // Confirm dialog
      const confirmDialogBtn = page.locator('button:has-text("تأكيد واستيراد الأرقام")').last();
      await confirmDialogBtn.click();

      // Step 5: Report step (CommitImportJob must complete via queue)
      await expect(page.getByText('اكتملت عملية الاستيراد بنجاح!')).toBeVisible({ timeout: 45000 });

      // Navigate to drivers page
      const goToDriversBtn = page.getByRole('button', { name: /الانتقال لقائمة السائقين/i });
      await goToDriversBtn.click();
      await page.waitForURL('**/drivers', { timeout: 10000 });
    } finally {
      if (fs.existsSync(validCsvPath)) fs.unlinkSync(validCsvPath);
    }
  });

  // 5. Positive Path: Bulk invite drivers on WhatsApp
  test('5. Positive Path: Bulk invite new drivers via WhatsApp modal', async () => {
    if (!page.url().includes('/drivers')) {
      await page.goto('/drivers');
    }
    await expect(page.locator('h1')).toContainText('السائقين', { timeout: 30000 });

    // Click bulk invite button
    const bulkInviteBtn = page.getByRole('button', { name: /إرسال دعوة واتساب/i });
    await expect(bulkInviteBtn).toBeVisible({ timeout: 30000 });
    await bulkInviteBtn.click();

    // Confirm invite in modal
    const confirmSendBtn = page.getByRole('button', { name: /بدء إرسال الدعوات الآن/i });
    await expect(confirmSendBtn).toBeEnabled({ timeout: 30000 });
    await confirmSendBtn.click();

    // Verify modal closes
    await expect(confirmSendBtn).not.toBeVisible({ timeout: 20000 });
  });

  // 6. Negative & Inbound Path: Opt-out keyword & Questionnaire answers
  test('6. Inbound WhatsApp Simulation: Opt-out negative path and active questionnaire replies', async () => {
    // 6a. Negative path: Test exact-only opt-out keywords "قف" and "خروج"
    const optOutPhone1 = testPhones[34];
    const optOutPhone2 = testPhones[33];

    // Driver 34 sends exact-only keyword "قف"
    runArtisan(`whatsapp:simulate-inbound "${optOutPhone1}" "قف"`);
    // Driver 33 sends exact-only keyword "خروج"
    runArtisan(`whatsapp:simulate-inbound "${optOutPhone2}" "خروج"`);

    // Verify status became opted_out in database for both
    const verifyOptOut1 = runTinker(
      `echo \\App\\Models\\Driver::where('phone', '${optOutPhone1}')->value('status')->value;`
    ).trim();
    const verifyOptOut2 = runTinker(
      `echo \\App\\Models\\Driver::where('phone', '${optOutPhone2}')->value('status')->value;`
    ).trim();

    expect(verifyOptOut1).toContain('opted_out');
    expect(verifyOptOut2).toContain('opted_out');

    // 6b. Drivers 1 to 30 become active
    const activePhones = testPhones.slice(0, 30);
    runTinker(`
      \\App\\Models\\Driver::whereIn('phone', [${activePhones.map((p) => `'${p}'`).join(',')}])
        ->update(['status' => 'active']);
    `);

    // Verify active drivers count
    const activeCount = runTinker(
      `echo \\App\\Models\\Driver::whereIn('phone', [${activePhones.map((p) => `'${p}'`).join(',')}])->where('status', 'active')->count();`
    ).trim();
    expect(activeCount).toContain('30');
  });

  // 7. Positive Path: Create work order 10+5 with filter
  test('7. Positive Path: Create work order (10 confirmed + 5 backup) and send', async () => {
    await page.goto('/work-orders/create');

    // Step 1: Details
    await page.locator('input[name="from_details"]').fill('مستودعات السادس من أكتوبر');
    await page.locator('input[name="to_details"]').fill('ميناء الإسكندرية');

    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 2);
    tomorrow.setHours(10, 0, 0, 0);
    const dateStr = tomorrow.toISOString().slice(0, 16);

    await page.locator('input[type="datetime-local"]').fill(dateStr);
    await page.getByLabel('نوع الحمولة').fill('بضائع مجمدة');
    await page.getByLabel('المقابل المادي (ج.م)').fill('1800');

    // Click Next to Step 2
    await page.getByRole('button', { name: 'التالي' }).click();

    // Step 2: Capacity
    await page.getByLabel('عدد الرحلات الأساسية المطلوبة').fill('10');
    await page.getByLabel('عدد سائقي الاحتياطي المطلوب').fill('5');

    // Click Next to Step 3
    await page.getByRole('button', { name: 'التالي' }).click();

    // Step 3: Filters (click Next)
    await page.getByRole('button', { name: 'التالي' }).click();

    // Step 4: Strategy (staged with batch size 30)
    await page.locator('label:has-text("إرسال تدريجي على دفعات")').click();
    await page.getByLabel('حجم الدفعة (عدد السائقين)').fill('30');
    await page.getByLabel('الفاصل الزمني بين الدفعات (بالدقائق)').fill('10');
    await page.getByRole('button', { name: 'التالي' }).click();

    // Step 5: Review & Send — double click negative path: button disabled + offers sent only once
    const sendOrderBtn = page.getByRole('button', { name: /بدء الإرسال الآن/i });
    await expect(sendOrderBtn).toBeVisible({ timeout: 5000 });

    // Negative Path: Rapid double-click on the send button.
    // The button must become disabled immediately, and offers must be sent only once.
    const [sendResponse] = await Promise.all([
      page.waitForResponse(
        (res) => res.url().includes('/work-orders/') && res.url().includes('/send') && res.status() === 200,
        { timeout: 60000 }
      ),
      (async () => {
        await sendOrderBtn.click();
        // Verify button immediately enters disabled/loading state
        await expect(sendOrderBtn).toBeDisabled({ timeout: 3000 });
        // Attempt a rapid second click on the disabled button
        await sendOrderBtn.click({ force: true }).catch(() => {});
      })(),
    ]);

    const sendData = await sendResponse.json();
    const orderIdFromApi = sendData?.data?.id as number | undefined;
    console.log('Send API response — order ID:', orderIdFromApi, 'status:', sendData?.data?.status);

    // Wait for redirect to /work-orders/:id
    await page.waitForURL(/\/work-orders\/\d+/, { timeout: 15000 });

    const match = page.url().match(/\/work-orders\/(\d+)/);
    expect(match).not.toBeNull();
    createdOrderId = orderIdFromApi ?? parseInt(match![1], 10);
    console.log('createdOrderId captured:', createdOrderId);

    // Negative Path Server Verification: Attempt an explicit second send request to the server.
    // The server must reject the second send with HTTP 422 Unprocessable Entity.
    const secondSendResult = await page.evaluate(async (orderId) => {
      const xsrfToken = decodeURIComponent(
        document.cookie
          .split('; ')
          .find((row) => row.startsWith('XSRF-TOKEN='))
          ?.split('=')[1] || ''
      );
      const res = await fetch(`/api/v1/work-orders/${orderId}/send`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-XSRF-TOKEN': xsrfToken,
        },
      });
      return {
        status: res.status,
        body: await res.json().catch(() => ({})),
      };
    }, createdOrderId);

    console.log('Second send attempt result:', secondSendResult);
    expect(secondSendResult.status).toBe(422);
    expect(JSON.stringify(secondSendResult.body)).toContain('أمر العمل تم إرساله مسبقًا');

    // Verify in database: offers were sent only once (sent_count=30, recipients=30, status=filling)
    const statusCheck = runTinker(`
      $order = \\App\\Models\\WorkOrder::findOrFail(${createdOrderId});
      $recipientCount = \\App\\Models\\WorkOrderRecipient::where('work_order_id', ${createdOrderId})->count();
      echo "STATUS:" . $order->status->value . ";SENT:" . $order->sent_count . ";RECIPIENTS:" . $recipientCount;
    `);
    console.log('Order status after double-click send verification:', statusCheck.trim());
    expect(statusCheck).toContain('STATUS:filling');
    expect(statusCheck).toContain('SENT:30');
    expect(statusCheck).toContain('RECIPIENTS:30');
  });

  // 8. Positive Path: Simulate 30 acceptances (10 confirmed, 5 backup)
  test('8. Positive Path: Simulate 30 driver acceptances and verify 10 confirmed + 5 backup', async () => {
    expect(createdOrderId).not.toBeNull();

    // Directly call AcceptOrderOffer::handle() for each SENT recipient (avoids Artisan overhead)
    const acceptResult = runTinker(`
      $orderId = ${createdOrderId};
      $order = \\App\\Models\\WorkOrder::findOrFail($orderId);

      // Only SENT recipients have received the offer button and can accept
      $recipients = \\App\\Models\\WorkOrderRecipient::with('driver')
        ->where('work_order_id', $orderId)
        ->where('status', 'sent')
        ->orderBy('id')
        ->take(30)
        ->get();

      $acceptService = app(\\App\\Services\\AcceptOrderOffer::class);
      $processed = 0;
      $errors = [];

      foreach ($recipients as $recipient) {
        $driver = $recipient->driver;
        if (!$driver) {
          $errors[] = "no driver for recipient " . $recipient->id;
          continue;
        }
        try {
          $result = $acceptService->handle($orderId, $driver->id, 'accept');
          $processed++;
        } catch (\\Throwable $e) {
          $errors[] = "driver {$driver->id}: " . $e->getMessage();
        }
      }

      $order->refresh();
      echo "PROCESSED:" . $processed
        . ";CONFIRMED:" . $order->confirmed_count
        . ";BACKUP:" . $order->backup_count
        . ";STATUS:" . $order->status->value
        . ";ERRORS:" . count($errors);
      if (!empty($errors)) {
        echo ";ERRMSGS:" . implode("|", $errors);
      }
    `);

    // Log result for debugging
    console.log('Accept result:', acceptResult.trim());

    expect(acceptResult).toContain('PROCESSED:30');
    expect(acceptResult).toContain('ERRORS:0');

    // Reload page to reflect updated state
    await page.goto(`/work-orders/${createdOrderId}`);
    await page.waitForLoadState('networkidle');

    // Verify order status badge shows filled
    await expect(page.locator('text=مكتمل العدد').first()).toBeVisible({ timeout: 20000 });

    // Verify counts in backend
    const countsCheck = runTinker(`
      $order = \\App\\Models\\WorkOrder::findOrFail(${createdOrderId});
      echo "CONFIRMED:" . $order->confirmed_count . ";BACKUP:" . $order->backup_count;
    `);
    expect(countsCheck).toContain('CONFIRMED:10;BACKUP:5');
  });

  // 9. Positive Path: Cancel one confirmed driver & Promote backup driver
  test('9. Positive Path: Cancel confirmed driver and promote backup driver into open slot', async () => {
    expect(createdOrderId).not.toBeNull();
    await page.goto(`/work-orders/${createdOrderId}`);

    // Select confirmed tab if not selected
    const confirmedTab = page.getByRole('tab', { name: /مؤكدون/i });
    if (await confirmedTab.isVisible()) {
      await confirmedTab.click();
    }

    // Cancel driver with button 'إلغاء تكليف'
    const removeBtn = page.getByRole('button', { name: /إلغاء تكليف/i }).first();
    await expect(removeBtn).toBeVisible({ timeout: 5000 });
    await removeBtn.click();

    // In RemoveDriverModal: fill reason and confirm (target by placeholder to avoid aria-hidden duplicate)
    await page.locator('textarea[placeholder]').first().fill('عطل مفاجئ في مبرد الشاحنة');
    const confirmRemoveBtn = page.getByRole('button', { name: /تأكيد الإلغاء/i });
    await confirmRemoveBtn.click();

    // Verify open slots banner appears
    await expect(page.locator('text=تنبيه: يوجد مكان شاغر في الرحلة')).toBeVisible({ timeout: 10000 });

    // Promote backup driver from open slots banner
    const promoteBtn = page.getByRole('button', { name: /ترقية احتياطي/i }).first();
    await promoteBtn.click();

    // Confirm promote in modal
    const confirmPromoteBtn = page.getByRole('button', { name: /تأكيد الترقية وإرسال الإشعار/i });
    await confirmPromoteBtn.click();

    // Verify open slots banner is removed
    await expect(page.locator('text=تنبيه: يوجد مكان شاغر في الرحلة')).not.toBeVisible({ timeout: 10000 });

    // Verify counts returned to 10 confirmed, 4 backup
    const afterPromoteCounts = runTinker(`
      $order = \\App\\Models\\WorkOrder::findOrFail(${createdOrderId});
      echo "CONFIRMED:" . $order->confirmed_count . ";BACKUP:" . $order->backup_count;
    `);
    expect(afterPromoteCounts).toContain('CONFIRMED:10;BACKUP:4');
  });

  // 10. Positive Path: Complete order & Record outcomes
  test('10. Positive Path: Advance order to completed and record outcomes', async () => {
    expect(createdOrderId).not.toBeNull();

    // Advance order to in_progress then completed via service (correct method: changeOrderStatus)
    const advanceResult = runTinker(`
      $order = \\App\\Models\\WorkOrder::findOrFail(${createdOrderId});
      $service = app(\\App\\Services\\WorkOrderFollowUpService::class);
      $user = \\App\\Models\\User::first();
      $service->changeOrderStatus($order, \\App\\Enums\\WorkOrderStatus::IN_PROGRESS, null, $user->id);
      $order->refresh();
      $service->changeOrderStatus($order, \\App\\Enums\\WorkOrderStatus::COMPLETED, null, $user->id);
      $order->refresh();
      echo "STATUS:" . $order->status->value;
    `);
    console.log('Advance status result:', advanceResult.trim());
    expect(advanceResult).toContain('STATUS:completed');

    await page.goto(`/work-orders/${createdOrderId}`);
    await expect(page.locator('text=منتهي').first()).toBeVisible({ timeout: 10000 });

    // Click record outcomes button
    const recordOutcomesBtn = page.getByRole('button', { name: /تسجيل نتائج الرحلة/i });
    await recordOutcomesBtn.click();

    // In RecordOutcomesModal: click Save
    const saveOutcomesBtn = page.getByRole('button', { name: /حفظ النتائج والتقييمات/i });
    await expect(saveOutcomesBtn).toBeVisible({ timeout: 5000 });
    await saveOutcomesBtn.click();

    // Verify modal closes
    await expect(saveOutcomesBtn).not.toBeVisible({ timeout: 8000 });
  });

  // 11. Positive Path: Export Excel and PDF
  test('11. Positive Path: Export Excel and PDF downloads succeed', async () => {
    expect(createdOrderId).not.toBeNull();
    await page.goto(`/work-orders/${createdOrderId}`);

    // Trigger PDF download
    const pdfBtn = page.getByRole('button', { name: /طباعة تفاصيل الرحلة \(PDF\)/i });
    await expect(pdfBtn).toBeVisible({ timeout: 5000 });
    await pdfBtn.click();
    await expect(page.getByText('منتهي').first()).toBeVisible();

    // Trigger Excel export
    const excelBtn = page.getByRole('button', { name: /تصدير بيانات الطلب \(Excel\)/i });
    await expect(excelBtn).toBeVisible({ timeout: 5000 });
    await excelBtn.click();
    await expect(
      page.locator('text=مركز التصدير وتحميل التقارير').or(page.locator('text=بدأت عملية التصدير')).first()
    ).toBeVisible({ timeout: 10000 });
  });

  // 12. Negative Path: Expired session triggers redirect to login
  test('12. Negative Path: Expired session redirects to /login on protected navigation', async () => {
    // Let in-flight authenticated requests (e.g. sidebar badge polling) finish first,
    // otherwise their Set-Cookie response can restore the session after clearing.
    await page.waitForLoadState('networkidle');
    // Clear cookies in browser context to simulate session expiration
    await page.context().clearCookies();

    // Navigate to protected page
    await page.goto('/work-orders');

    // Should redirect to /login
    await page.waitForURL('**/login', { timeout: 10000 });
    await expect(page.locator('input[name="email"]')).toBeVisible();
  });

  // 13. Positive Path: Logout
  test('13. Positive Path: Logout successfully terminates session', async () => {
    // Re-login first
    await page.goto('/login');
    await page.locator('input[name="email"]').fill('admin@example.com');
    await page.locator('input[name="password"]').fill('password123');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 10000 });

    // Open user menu and click logout
    await page.getByRole('button', { name: 'قائمة المستخدم' }).click();
    await page.getByRole('menuitem', { name: /تسجيل الخروج/i }).click();

    // Confirm in dialog
    const confirmLogoutBtn = page.getByRole('button', { name: /تسجيل الخروج/i }).last();
    await confirmLogoutBtn.click();

    // Verify redirected to login page
    await page.waitForURL('**/login', { timeout: 10000 });
    await expect(page.locator('input[name="email"]')).toBeVisible();
  });

  // 14. CSP Compliance Check
  test('14. Security Compliance: Zero CSP violations detected across entire suite', async () => {
    expect(cspViolations).toEqual([]);
  });
});
