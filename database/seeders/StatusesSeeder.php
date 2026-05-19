<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusesSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Segundo mensaje Petija Parnasa', 'color' => '#8B5CF6', 'sort_order' => 1],
            ['name' => '2 perek shira', 'color' => '#EC4899', 'sort_order' => 2],
            ['name' => 'Rifa 1m primer mensaje', 'color' => '#F97316', 'sort_order' => 3],
            ['name' => 'Rifa 1 m segundo mensaje', 'color' => '#14B8A6', 'sort_order' => 4],
        ];

        foreach ($statuses as $status) {
            $slug = \Str::slug($status['name']);
            Status::updateOrCreate(
                ['slug' => $slug],
                array_merge($status, ['slug' => $slug])
            );
        }
    }
}
