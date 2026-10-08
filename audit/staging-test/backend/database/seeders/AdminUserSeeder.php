<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $isProduction = App::environment('production');

        $admin1Email = env('ADMIN_EMAIL') ?: env('ADMIN_1_EMAIL');
        $admin1Password = env('ADMIN_PASSWORD') ?: env('ADMIN_1_PASSWORD');
        $admin1Name = env('ADMIN_NAME') ?: env('ADMIN_1_NAME', 'مدير النظام الأول');

        if ($isProduction) {
            if (empty($admin1Email) || empty($admin1Password)) {
                throw new RuntimeException('Cannot seed admin user in production: ADMIN_EMAIL and ADMIN_PASSWORD must be configured in .env.');
            }

            if (
                strtolower(trim($admin1Email)) === 'admin@example.com' ||
                in_array(trim($admin1Password), ['password', 'password123', 'admin', '12345678'], true)
            ) {
                throw new RuntimeException('Refusing to seed admin user in production with default/example credentials (admin@example.com / password123). Please set secure, non-default credentials in .env.');
            }
        } else {
            $admin1Email = $admin1Email ?: 'admin@example.com';
            $admin1Password = $admin1Password ?: 'password123';
        }

        User::updateOrCreate(
            ['email' => $admin1Email],
            [
                'name' => $admin1Name,
                'password' => Hash::make($admin1Password),
                'is_active' => true,
            ]
        );

        $admin2Email = env('ADMIN_2_EMAIL');
        $admin2Password = env('ADMIN_2_PASSWORD');

        if ($admin2Email && $admin2Password) {
            if (
                $isProduction &&
                (strtolower(trim($admin2Email)) === 'admin@example.com' ||
                    in_array(trim($admin2Password), ['password', 'password123', 'admin', '12345678'], true))
            ) {
                throw new RuntimeException('Refusing to seed secondary admin user in production with default/example credentials.');
            }

            User::updateOrCreate(
                ['email' => $admin2Email],
                [
                    'name' => env('ADMIN_2_NAME', 'مدير النظام الثاني'),
                    'password' => Hash::make($admin2Password),
                    'is_active' => true,
                ]
            );
        }
    }
}

