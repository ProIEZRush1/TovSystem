<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            SuperAdminSeeder::class,
            StatusesSeeder::class,
        ]);

        // Ensure "Nuevos" label exists for auto-tagging new contacts
        \App\Models\Label::firstOrCreate(
            ['slug' => 'nuevos'],
            ['name' => 'Nuevos', 'color' => '#3B82F6', 'sort_order' => 99]
        );
    }
}
