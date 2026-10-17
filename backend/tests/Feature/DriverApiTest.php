<?php

use App\Enums\DriverStatus;
use App\Models\Driver;
use App\Models\User;
use App\Models\WhatsappGroup;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('unauthenticated request to drivers is rejected', function () {
    $response = $this->getJson('/api/v1/drivers');
    $response->assertStatus(401);
});

test('can list drivers with pagination and search by phone and name', function () {
    Driver::factory()->create([
        'name' => 'أحمد إبراهيم',
        'phone' => '+201011111111',
    ]);

    Driver::factory()->create([
        'name' => 'محمود علي',
        'phone' => '+201022222222',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/drivers?search=أحمد');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['name'])->toBe('أحمد إبراهيم');

    $phoneResponse = $this->actingAs($this->user)
        ->getJson('/api/v1/drivers?search=22222222');

    $phoneResponse->assertStatus(200);
    $phoneData = $phoneResponse->json('data');
    expect(count($phoneData))->toBe(1);
    expect($phoneData[0]['phone'])->toBe('+201022222222');
});

test('can filter drivers by status and whatsapp group', function () {
    $group = WhatsappGroup::create(['name' => 'جروب الجيزة']);

    $driver1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $driver1->whatsappGroups()->attach($group->id, ['first_seen_at' => now()]);

    $driver2 = Driver::factory()->create(['status' => DriverStatus::NEW]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/drivers?status=active');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['id'])->toBe($driver1->id);

    $groupResponse = $this->actingAs($this->user)
        ->getJson('/api/v1/drivers?group_id='.$group->id);

    $groupResponse->assertStatus(200);
    $groupData = $groupResponse->json('data');
    expect(count($groupData))->toBe(1);
    expect($groupData[0]['id'])->toBe($driver1->id);
});

test('can create a driver manually with phone normalization and group attachment', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/drivers', [
            'phone' => '01099887766',
            'name' => 'سائق جديد',
            'notes' => 'سائق ممتاز ملتزم بالمواعيد',
            'group_name' => 'جروب مصر القديمة',
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'data' => [
                'phone' => '+201099887766',
                'name' => 'سائق جديد',
                'status' => [
                    'value' => 'new',
                    'label' => 'جديد',
                ],
            ],
            'message' => 'تمت إضافة السائق بنجاح.',
        ]);

    $this->assertDatabaseHas('drivers', [
        'phone' => '+201099887766',
        'name' => 'سائق جديد',
    ]);

    $this->assertDatabaseHas('whatsapp_groups', [
        'name' => 'جروب مصر القديمة',
    ]);
});

test('cannot create driver with duplicate phone', function () {
    Driver::factory()->create(['phone' => '+201099887766']);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/drivers', [
            'phone' => '01099887766',
            'name' => 'مكرر',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['phone']);
});

test('can view driver details with status histories', function () {
    $driver = Driver::factory()->create([
        'status' => DriverStatus::NEW,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/drivers/{$driver->id}");

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $driver->id,
                'phone' => $driver->phone,
            ],
        ]);
});

test('can update driver name and notes', function () {
    $driver = Driver::factory()->create([
        'name' => 'الاسم القديم',
        'notes' => 'ملاحظة قديمة',
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/drivers/{$driver->id}", [
            'name' => 'الاسم الجديد',
            'notes' => 'ملاحظة محدثة',
        ]);

    $response->assertStatus(200);

    $driver->refresh();
    expect($driver->name)->toBe('الاسم الجديد');
    expect($driver->notes)->toBe('ملاحظة محدثة');
});

test('can change driver status through state machine endpoint', function () {
    $driver = Driver::factory()->create([
        'status' => DriverStatus::NEW,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/drivers/{$driver->id}/status", [
            'status' => 'invited',
            'reason' => 'تمت دعوته للعمل بالشركة',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'status' => [
                    'value' => 'invited',
                    'label' => 'تمت الدعوة',
                ],
            ],
            'message' => 'تم تغيير حالة السائق بنجاح.',
        ]);

    $driver->refresh();
    expect($driver->status)->toBe(DriverStatus::INVITED);
});

test('forbidden status change returns 422 with localized error', function () {
    $driver = Driver::factory()->create([
        'status' => DriverStatus::NEW,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/drivers/{$driver->id}/status", [
            'status' => 'active',
            'reason' => 'محاولة انتقال ممنوعة',
        ]);

    $response->assertStatus(422);

    $driver->refresh();
    expect($driver->status)->toBe(DriverStatus::NEW);
});

test('can list whatsapp groups with driver count', function () {
    $group = WhatsappGroup::create(['name' => 'جروب الإسكندرية']);
    $driver = Driver::factory()->create();
    $driver->whatsappGroups()->attach($group->id);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/whatsapp-groups');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect(count($data))->toBeGreaterThanOrEqual(1);
    expect($data[0]['name'])->toBe('جروب الإسكندرية');
    expect($data[0]['drivers_count'])->toBe(1);
});

test('authenticated user can delete a driver (soft delete)', function () {
    $driver = Driver::factory()->create([
        'name' => 'سائق للحذف',
        'phone' => '+201099112233',
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/v1/drivers/{$driver->id}");

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'تم حذف السائق بنجاح.',
        ]);

    $this->assertSoftDeleted('drivers', [
        'id' => $driver->id,
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'driver_deleted',
        'subject_id' => $driver->id,
    ]);
});

test('unauthenticated request to delete driver is rejected', function () {
    $driver = Driver::factory()->create();

    $response = $this->deleteJson("/api/v1/drivers/{$driver->id}");

    $response->assertStatus(401);
});
