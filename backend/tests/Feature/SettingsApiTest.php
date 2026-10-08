<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('Authenticated user can view system settings', function () {
    Setting::set('questionnaire_reminder_hours', 24);
    Setting::set('send_rate_per_minute', 30);

    $response = $this->actingAs($this->user)->getJson('/api/v1/settings');

    $response->assertOk()
        ->assertJsonPath('data.settings.questionnaire_reminder_hours', 24)
        ->assertJsonPath('data.settings.send_rate_per_minute', 30);
});

test('Authenticated user can update system settings with validation', function () {
    $response = $this->actingAs($this->user)->putJson('/api/v1/settings', [
        'settings' => [
            'questionnaire_reminder_hours' => 12,
            'questionnaire_timeout_days' => 5,
            'send_rate_per_minute' => 60,
        ],
    ]);

    $response->assertOk();

    expect(Setting::get('questionnaire_reminder_hours'))->toBe(12)
        ->and(Setting::get('questionnaire_timeout_days'))->toBe(5)
        ->and(Setting::get('send_rate_per_minute'))->toBe(60);
});
