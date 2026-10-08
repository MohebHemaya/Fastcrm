<?php

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Models\Driver;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\WhatsApp\FakeWhatsAppProvider;

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
    $tplService = app(\App\Services\WhatsAppTemplateService::class);
    $rendered = $tplService->render('order_confirmation', [
        'اسم_السائق' => 'محمود',
        'رقم_الطلب' => 'WO-99',
    ]);

    expect($rendered)->toBe('تم تأكيد حجزك رسمياً يا محمود للرحلة رقم WO-99 🚚');
});
