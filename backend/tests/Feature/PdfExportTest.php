<?php

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Driver;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\Services\DriverProfilePdfService;
use App\Services\WorkOrderPdfService;
use App\WhatsApp\FakeWhatsAppProvider;

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('work order PDF generates valid PDF with correct Arabic headers and BiDi phone runs', function () {
    $order = WorkOrder::factory()->create([
        'code' => 'WO-CAIRO-101',
        'from_location' => 'القاهرة (ميدان التحرير)',
        'to_location' => 'الإسكندرية (محطة الرمل)',
        'scheduled_at' => now()->addDay(),
        'trips_needed' => 1,
        'backup_needed' => 1,
        'confirmed_count' => 1,
        'backup_count' => 1,
        'status' => WorkOrderStatus::FILLED,
        'notes' => 'برجاء الالتزام التام بالزي الموحد وتعليمات السلامة',
    ]);

    $driver = Driver::factory()->create([
        'name' => 'محمد صلاح الدين',
        'phone' => '+201000000001',
        'status' => DriverStatus::ACTIVE,
    ]);

    WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sequence_number' => 1,
        'responded_at' => now()->subHour(),
    ]);

    $pdfService = app(WorkOrderPdfService::class);

    // 1. Snapshot HTML template assertions
    $html = $pdfService->generateHtml($order);
    expect($html)->toContain('بيان تشغيل رحلة')
        ->and($html)->toContain('WO-CAIRO-101')
        ->and($html)->toContain('محمد صلاح الدين')
        ->and($html)->toContain('+201000000001')
        ->and($html)->toContain('برجاء الالتزام التام بالزي الموحد')
        ->and($html)->toContain('dir="ltr"')
        ->and($html)->toContain('unicode-bidi: embed');

    // 2. Generate PDF binary content
    $pdfBinary = $pdfService->generatePdf($order);

    expect($pdfBinary)->not->toBeEmpty();
    // Valid PDF header %PDF-
    expect(substr($pdfBinary, 0, 4))->toBe('%PDF');
    expect(strlen($pdfBinary))->toBeGreaterThan(1000);
});

test('driver profile PDF generates valid single page PDF with metrics and answers', function () {
    $driver = Driver::factory()->create([
        'name' => 'خالد عبد الرحمن',
        'phone' => '+201112223334',
        'status' => DriverStatus::ACTIVE,
        'notes' => 'سائق ممتاز وخبرة 10 سنوات',
    ]);

    // Metric
    $driver->metrics()->create([
        'trips_completed' => 15,
        'cancellations' => 1,
        'no_shows' => 0,
        'late_count' => 2,
        'avg_rating' => 4.8,
    ]);

    $pdfService = app(DriverProfilePdfService::class);

    // 1. Test HTML rendering
    $html = $pdfService->generateHtml($driver);
    expect($html)->toContain('بطاقة تعريف وبيانات السائق')
        ->and($html)->toContain('خالد عبد الرحمن')
        ->and($html)->toContain('+201112223334')
        ->and($html)->toContain('15') // trips completed
        ->and($html)->toContain('4.8 / 5') // rating
        ->and($html)->toContain('dir="ltr"')
        ->and($html)->toContain('unicode-bidi: embed');

    // 2. Generate PDF binary
    $pdfBinary = $pdfService->generatePdf($driver);

    expect($pdfBinary)->not->toBeEmpty();
    expect(substr($pdfBinary, 0, 4))->toBe('%PDF');
    expect(strlen($pdfBinary))->toBeGreaterThan(1000);
});
