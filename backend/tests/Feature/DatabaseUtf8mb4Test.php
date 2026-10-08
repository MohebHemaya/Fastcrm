<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('database connection is mysql/mariadb and not sqlite', function () {
    $driver = DB::connection()->getDriverName();

    expect($driver)->toBe('mysql');
});

test('arabic text and emoji round trip safely in utf8mb4 mysql database', function () {
    $arabicName = 'السيد أحمد إبراهيم محمد 🚗💨✨';
    $email = 'arabic_test_'.uniqid().'@example.com';

    $user = User::create([
        'name' => $arabicName,
        'email' => $email,
        'password' => bcrypt('secret123'),
    ]);

    $savedUser = User::find($user->id);

    expect($savedUser)->not->toBeNull();
    expect($savedUser->name)->toBe($arabicName);

    // Verify raw database row content
    $raw = DB::table('users')->where('id', $user->id)->first();
    expect($raw->name)->toBe($arabicName);
});
