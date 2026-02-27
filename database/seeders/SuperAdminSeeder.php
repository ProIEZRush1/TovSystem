<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            [
                'email' => 'edumaucherni@gmail.com',
                'name' => 'Eduardo',
                'password' => 'Eduardo2006!',
            ],
            [
                'email' => 'admin@tov.org',
                'name' => 'Admin',
                'password' => '12345678',
            ],
        ];

        foreach ($admins as $admin) {
            User::updateOrCreate(
                ['email' => $admin['email']],
                [
                    'name' => $admin['name'],
                    'password' => Hash::make($admin['password']),
                    'is_super_admin' => true,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
