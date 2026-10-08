<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;

test('AdminUserSeeder seeds default admin in testing and local environment', function () {
    // Delete any existing default admin first
    User::where('email', 'admin@example.com')->delete();

    $seeder = new AdminUserSeeder;
    $seeder->run();

    $user = User::where('email', 'admin@example.com')->first();
    expect($user)->not->toBeNull()
        ->and(Hash::check('password123', $user->password))->toBeTrue()
        ->and($user->is_active)->toBeTrue();
});

test('AdminUserSeeder refuses to run in production with default credentials', function () {
    App::detectEnvironment(fn () => 'production');

    // Default email and password
    putenv('ADMIN_EMAIL=admin@example.com');
    putenv('ADMIN_PASSWORD=password123');

    $seeder = new AdminUserSeeder;

    expect(fn () => $seeder->run())
        ->toThrow(RuntimeException::class, 'Refusing to seed admin user in production with default/example credentials');

    // Cleanup env
    putenv('ADMIN_EMAIL');
    putenv('ADMIN_PASSWORD');
    App::detectEnvironment(fn () => 'testing');
});

test('AdminUserSeeder refuses to run in production without configured credentials', function () {
    App::detectEnvironment(fn () => 'production');

    putenv('ADMIN_EMAIL');
    putenv('ADMIN_1_EMAIL');
    putenv('ADMIN_PASSWORD');
    putenv('ADMIN_1_PASSWORD');

    $seeder = new AdminUserSeeder;

    expect(fn () => $seeder->run())
        ->toThrow(RuntimeException::class, 'Cannot seed admin user in production');

    App::detectEnvironment(fn () => 'testing');
});

test('AdminUserSeeder seeds admin in production with secure custom credentials', function () {
    App::detectEnvironment(fn () => 'production');

    putenv('ADMIN_EMAIL=secure_prod_admin@company.com');
    putenv('ADMIN_PASSWORD=V3ry$ecureP@ssw0rd2026!');
    putenv('ADMIN_NAME=مسؤول الإنتاج الرئيسي');

    $seeder = new AdminUserSeeder;
    $seeder->run();

    $user = User::where('email', 'secure_prod_admin@company.com')->first();
    expect($user)->not->toBeNull()
        ->and(Hash::check('V3ry$ecureP@ssw0rd2026!', $user->password))->toBeTrue()
        ->and($user->name)->toBe('مسؤول الإنتاج الرئيسي');

    // Cleanup
    $user->delete();
    putenv('ADMIN_EMAIL');
    putenv('ADMIN_PASSWORD');
    putenv('ADMIN_NAME');
    App::detectEnvironment(fn () => 'testing');
});
