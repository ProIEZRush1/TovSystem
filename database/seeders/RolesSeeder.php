<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Full administrative access',
                'permissions' => json_encode(['*']),
            ],
            [
                'name' => 'Contact Manager',
                'slug' => 'contact-manager',
                'description' => 'Can manage contacts and imports',
                'permissions' => json_encode([
                    'dashboard.view',
                    'contacts.*',
                    'import.manage',
                    'statuses.view',
                    'labels.view',
                    'labels.manage',
                ]),
            ],
            [
                'name' => 'Viewer',
                'slug' => 'viewer',
                'description' => 'Read-only access to contacts',
                'permissions' => json_encode([
                    'dashboard.view',
                    'contacts.view',
                    'statuses.view',
                    'labels.view',
                ]),
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
