<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'مدير النظام',
        'email' => 'admin@example.com',
        'password' => Hash::make('password123'),
        'is_active' => true,
    ]);
});

test('user can log in with valid credentials and session is established', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'email' => 'admin@example.com',
                'name' => 'مدير النظام',
            ],
            'message' => 'تم تسجيل الدخول بنجاح.',
        ]);

    $this->assertAuthenticatedAs($this->user);

    // Verify audit log
    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $this->user->id,
        'action' => 'login',
    ]);

    // Verify last_login_at updated
    $this->user->refresh();
    expect($this->user->last_login_at)->not->toBeNull();
});

test('user cannot log in with invalid credentials', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    $this->assertGuest();

    // Verify failed login audit log
    $this->assertDatabaseHas('activity_logs', [
        'action' => 'failed_login',
    ]);
});

test('inactive user is blocked from logging in', function () {
    $this->user->update(['is_active' => false]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'message' => 'الحساب معطل، يرجى التواصل مع الإدارة.',
        ]);

    $this->assertGuest();

    // Verify audit log records failed login with inactive reason
    $log = ActivityLog::where('action', 'failed_login')->first();
    expect($log)->not->toBeNull();
    expect($log->properties['reason'] ?? null)->toBe('inactive_account');
});

test('login requests are throttled after 5 attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrongpassword',
        ]);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(429);
});

test('authenticated user can view their profile via me endpoint', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/auth/me');

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $this->user->id,
                'email' => 'admin@example.com',
            ],
        ]);
});

test('unauthenticated user cannot access me endpoint', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(401);
});

test('authenticated user can log out and session is invalidated', function () {
    $response = $this->actingAs($this->user, 'web')
        ->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);

    $this->assertGuest('web');

    // Verify logout audit log
    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $this->user->id,
        'action' => 'logout',
    ]);
});

test('authenticated user can change password with valid current password', function () {
    $response = $this->actingAs($this->user)
        ->patchJson('/api/v1/auth/password', [
            'current_password' => 'password123',
            'password' => 'newpassword456',
            'password_confirmation' => 'newpassword456',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'تم تغيير كلمة المرور بنجاح.',
        ]);

    $this->user->refresh();
    expect(Hash::check('newpassword456', $this->user->password))->toBeTrue();

    // Verify password_change audit log
    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $this->user->id,
        'action' => 'password_change',
    ]);
});

test('password change fails when current password is wrong', function () {
    $response = $this->actingAs($this->user)
        ->patchJson('/api/v1/auth/password', [
            'current_password' => 'wrongcurrentpassword',
            'password' => 'newpassword456',
            'password_confirmation' => 'newpassword456',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);
});
