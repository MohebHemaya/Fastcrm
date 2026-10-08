<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user1 = User::create([
        'name' => 'المدير 1',
        'email' => 'admin1@example.com',
        'password' => Hash::make('password123'),
        'is_active' => true,
    ]);

    $this->user2 = User::create([
        'name' => 'المدير 2',
        'email' => 'admin2@example.com',
        'password' => Hash::make('password123'),
        'is_active' => true,
    ]);

    ActivityLog::create([
        'user_id' => $this->user1->id,
        'action' => 'login',
        'ip' => '127.0.0.1',
        'created_at' => '2026-09-01 10:00:00',
    ]);

    ActivityLog::create([
        'user_id' => $this->user1->id,
        'action' => 'password_change',
        'ip' => '127.0.0.1',
        'created_at' => '2026-09-10 12:00:00',
    ]);

    ActivityLog::create([
        'user_id' => $this->user2->id,
        'action' => 'login',
        'ip' => '127.0.0.2',
        'created_at' => '2026-09-20 14:00:00',
    ]);
});

test('unauthenticated request to activity logs is rejected', function () {
    $response = $this->getJson('/api/v1/activity-logs');
    $response->assertStatus(401);
});

test('authenticated user can view paginated activity logs', function () {
    $response = $this->actingAs($this->user1)
        ->getJson('/api/v1/activity-logs');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'user', 'action', 'ip', 'created_at'],
            ],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);

    expect(count($response->json('data')))->toBe(3);
});

test('activity logs can be filtered by user_id', function () {
    $response = $this->actingAs($this->user1)
        ->getJson('/api/v1/activity-logs?user_id='.$this->user2->id);

    $response->assertStatus(200);
    $data = $response->json('data');

    expect(count($data))->toBe(1);
    expect($data[0]['user']['id'])->toBe($this->user2->id);
});

test('activity logs can be filtered by action', function () {
    $response = $this->actingAs($this->user1)
        ->getJson('/api/v1/activity-logs?action=password_change');

    $response->assertStatus(200);
    $data = $response->json('data');

    expect(count($data))->toBe(1);
    expect($data[0]['action'])->toBe('password_change');
});

test('activity logs can be filtered by date range', function () {
    $response = $this->actingAs($this->user1)
        ->getJson('/api/v1/activity-logs?date_from=2026-09-05&date_to=2026-09-15');

    $response->assertStatus(200);
    $data = $response->json('data');

    expect(count($data))->toBe(1);
    expect($data[0]['action'])->toBe('password_change');
});
