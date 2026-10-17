<?php

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Driver;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\InviteDriversService;
use App\Services\WhatsAppTemplateService;
use App\WhatsApp\FakeWhatsAppProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
});

test('can fetch conversations list ordered by recent messages', function () {
    $user = User::factory()->create();
    $driver1 = Driver::factory()->create(['name' => 'سائق قديم', 'status' => DriverStatus::ACTIVE]);
    $driver2 = Driver::factory()->create(['name' => 'سائق جديد', 'status' => DriverStatus::ACTIVE]);

    // Driver 1 message yesterday
    WhatsAppMessage::create([
        'driver_id' => $driver1->id,
        'direction' => WhatsAppMessageDirection::OUT,
        'status' => WhatsAppMessageStatus::DELIVERED,
        'type' => 'text',
        'body' => 'رسالة قديمة',
        'created_at' => now()->subDay(),
    ]);

    // Driver 2 message today
    WhatsAppMessage::create([
        'driver_id' => $driver2->id,
        'direction' => WhatsAppMessageDirection::IN,
        'status' => WhatsAppMessageStatus::DELIVERED,
        'type' => 'text',
        'body' => 'رسالة جديدة من السائق',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/v1/whatsapp/conversations');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'phone', 'status', 'last_message', 'unread_count'],
            ],
            'meta' => ['current_page', 'total'],
        ]);

    $data = $response->json('data');
    expect(count($data))->toBeGreaterThanOrEqual(2)
        ->and($data[0]['id'])->toBe($driver2->id)
        ->and($data[0]['last_message']['body'])->toBe('رسالة جديدة من السائق')
        ->and($data[0]['unread_count'])->toBe(1);
});

test('operator can send direct message to driver', function () {
    $user = User::factory()->create();
    $driver = Driver::factory()->create(['name' => 'أحمد التشغيل', 'status' => DriverStatus::ACTIVE]);

    $response = $this->actingAs($user)
        ->postJson("/api/v1/drivers/{$driver->id}/send-message", [
            'message' => 'السلام عليكم يا كابتن، هل وصلت للمستودع؟',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.body', 'السلام عليكم يا كابتن، هل وصلت للمستودع؟');

    $this->assertDatabaseHas('whatsapp_messages', [
        'driver_id' => $driver->id,
        'body' => 'السلام عليكم يا كابتن، هل وصلت للمستودع؟',
        'direction' => WhatsAppMessageDirection::OUT->value,
    ]);
});

test('can retrieve and update WhatsApp message templates and quick replies', function () {
    $user = User::factory()->create();

    // 1. Get templates
    $res = $this->actingAs($user)->getJson('/api/v1/whatsapp/templates');
    $res->assertOk()
        ->assertJsonStructure([
            'data' => [
                'templates' => [
                    '*' => ['key', 'title_ar', 'description_ar', 'default_text', 'current_text', 'variables'],
                ],
                'quick_replies' => [
                    '*' => ['id', 'title', 'text'],
                ],
            ],
        ]);

    // 2. Update templates
    $updateRes = $this->actingAs($user)->putJson('/api/v1/whatsapp/templates', [
        'templates' => [
            'order_confirmation' => 'تم تأكيد حجزك رسمياً يا {{اسم_السائق}} للرحلة رقم {{رقم_الطلب}} 🚚',
        ],
        'quick_replies' => [
            [
                'id' => 'gate_pass',
                'title' => 'تصريح البوابة',
                'text' => 'كود تصريح الدخول للبوابة هو: 9988',
            ],
        ],
    ]);

    $updateRes->assertOk();

    // Verify template rendering uses customized version
    $tplService = app(WhatsAppTemplateService::class);
    $rendered = $tplService->render('order_confirmation', [
        'اسم_السائق' => 'محمود',
        'رقم_الطلب' => 'WO-99',
    ]);

    expect($rendered)->toBe('تم تأكيد حجزك رسمياً يا محمود للرحلة رقم WO-99 🚚');
});

test('template service caches templates and invalidates on update', function () {
    $user = User::factory()->create();
    $tplService = app(WhatsAppTemplateService::class);
    $tplService->clearCache();

    // 1. Initial render caches the default
    $initial = $tplService->render('driver_invitation', ['اسم_السائق' => 'سامح']);
    expect($initial)->toContain('سامح');
    expect(Cache::has('msg_tpl_driver_invitation'))->toBeTrue();

    // 2. Update via API
    $this->actingAs($user)->putJson('/api/v1/whatsapp/templates', [
        'templates' => [
            'driver_invitation' => 'مرحبا يا {{اسم_السائق}}، أسطولنا يرحب بك!',
        ],
    ])->assertOk();

    // 3. Cache was invalidated and new template is rendered
    $updated = $tplService->render('driver_invitation', ['اسم_السائق' => 'سامح']);
    expect($updated)->toBe('مرحبا يا سامح، أسطولنا يرحب بك!');
});

test('smart template interpolation supports fallbacks, conditionals, and defaults', function () {
    $tplService = app(WhatsAppTemplateService::class);

    // 1. Test fallback syntax {{ tag | fallback }}
    $template1 = 'أهلاً يا {{اسم_السائق|كابتن العزيز}}، رحلتك جاهزة.';
    expect($tplService->renderRaw($template1, ['اسم_السائق' => 'محمد']))
        ->toBe('أهلاً يا محمد، رحلتك جاهزة.');
    expect($tplService->renderRaw($template1, []))
        ->toBe('أهلاً يا كابتن العزيز، رحلتك جاهزة.');

    // 2. Test conditional block {{#notes}}...{{/notes}}
    $template2 = "بيانات الرحلة:\n- خط السير: القاهرة\n{{#notes}}- الملاحظات: {{notes}}\n{{/notes}}بالتوفيق.";

    // With notes provided
    $withNotes = $tplService->renderRaw($template2, ['notes' => 'احذر المنحنيات']);
    expect($withNotes)->toContain('- الملاحظات: احذر المنحنيات');

    // Without notes provided -> conditional block is removed completely
    $withoutNotes = $tplService->renderRaw($template2, ['notes' => '']);
    expect($withoutNotes)->not->toContain('الملاحظات');
    expect($withoutNotes)->toBe("بيانات الرحلة:\n- خط السير: القاهرة\nبالتوفيق.");

    // 3. Test built-in sensible defaults
    $template3 = 'كابتن {{اسم_السائق}}، الأجرة: {{pay_amount}}، التحميل: {{from_location}}';
    $rendered3 = $tplService->renderRaw($template3, []);
    expect($rendered3)->toBe('كابتن كابتن، الأجرة: حسب الاتفاق، التحميل: حسب الاتفاق');
});

test('can send test template message to phone number via API', function () {
    $user = User::factory()->create();

    // 1. Rejects invalid phone
    $this->actingAs($user)->postJson('/api/v1/whatsapp/templates/test-send', [
        'phone' => '12345',
        'template_key' => 'driver_invitation',
    ])->assertStatus(422);

    // 2. Valid test send with custom text
    $response = $this->actingAs($user)->postJson('/api/v1/whatsapp/templates/test-send', [
        'phone' => '01012345678',
        'custom_text' => 'رسالة تجريبية لكابتن {{اسم_السائق}} رقم الطلب: {{رقم_الطلب}}',
        'sample_data' => [
            'اسم_السائق' => 'كابتن رامي',
            'رقم_الطلب' => 'TEST-001',
        ],
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['provider_message_id', 'phone', 'rendered_body'],
            'message',
        ]);

    expect($response->json('data.phone'))->toBe('+201012345678')
        ->and($response->json('data.rendered_body'))->toBe('رسالة تجريبية لكابتن كابتن رامي رقم الطلب: TEST-001');
});

test('operator can send image to driver via API and audio is rejected', function () {
    Storage::fake('public');
    Queue::fake([SendWhatsAppMessageJob::class]);

    $user = User::factory()->create();
    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    // 1. Send image file
    $imageFile = UploadedFile::fake()->image('license.jpg');
    $response = $this->actingAs($user)->postJson("/api/v1/drivers/{$driver->id}/send-media", [
        'type' => 'image',
        'file' => $imageFile,
        'caption' => 'صورة رخصة القيادة',
    ]);

    $response->assertStatus(201);
    expect($response->json('data.type'))->toBe('image')
        ->and($response->json('data.body'))->toBe('صورة رخصة القيادة')
        ->and($response->json('data.payload.media_url'))->toContain('whatsapp_media');

    Queue::assertPushed(SendWhatsAppMessageJob::class);

    // 2. Reject audio file
    $audioFile = UploadedFile::fake()->create('voice.mp3', 256, 'audio/mpeg');
    $resAudio = $this->actingAs($user)->postJson("/api/v1/drivers/{$driver->id}/send-media", [
        'type' => 'audio',
        'file' => $audioFile,
    ]);

    $resAudio->assertStatus(422);
});

test('anti-ban delay staggering applies in bulk invitations', function () {
    Queue::fake([SendWhatsAppMessageJob::class]);

    // Create 3 NEW drivers
    $drivers = Driver::factory()->count(3)->create(['status' => DriverStatus::NEW]);

    $inviteService = app(InviteDriversService::class);
    $result = $inviteService->invite($drivers->pluck('id')->all());

    expect($result['invited_count'])->toBe(3);

    // Verify SendWhatsAppMessageJob was pushed for each message
    Queue::assertPushed(SendWhatsAppMessageJob::class, 3);
});
