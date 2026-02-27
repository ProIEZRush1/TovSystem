<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusesSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'New', 'color' => '#3B82F6', 'sort_order' => 1],
            ['name' => 'Contacted', 'color' => '#8B5CF6', 'sort_order' => 2],
            ['name' => 'Interested', 'color' => '#10B981', 'sort_order' => 3],
            ['name' => 'Not Interested', 'color' => '#EF4444', 'sort_order' => 4],
            ['name' => 'Follow Up', 'color' => '#F59E0B', 'sort_order' => 5],
            ['name' => 'Subscribed', 'color' => '#06B6D4', 'sort_order' => 6],
            ['name' => 'Unsubscribed', 'color' => '#6B7280', 'sort_order' => 7],
            ['name' => 'Active', 'color' => '#22C55E', 'sort_order' => 8],
            ['name' => 'Inactive', 'color' => '#9CA3AF', 'sort_order' => 9],
            ['name' => 'Blocked', 'color' => '#DC2626', 'sort_order' => 10],
            ['name' => 'VIP', 'color' => '#D946EF', 'sort_order' => 11],
            ['name' => 'Pending', 'color' => '#FBBF24', 'sort_order' => 12],
            ['name' => 'Verified', 'color' => '#14B8A6', 'sort_order' => 13],
            ['name' => 'Unverified', 'color' => '#FB923C', 'sort_order' => 14],
            ['name' => 'Duplicate', 'color' => '#A78BFA', 'sort_order' => 15],
            ['name' => 'Invalid Number', 'color' => '#F87171', 'sort_order' => 16],
            ['name' => 'Do Not Contact', 'color' => '#1F2937', 'sort_order' => 17],
            ['name' => 'Scheduled', 'color' => '#60A5FA', 'sort_order' => 18],
            ['name' => 'Completed', 'color' => '#34D399', 'sort_order' => 19],
            ['name' => 'Other', 'color' => '#CBD5E1', 'sort_order' => 20],
        ];

        foreach ($statuses as $status) {
            Status::updateOrCreate(
                ['name' => $status['name']],
                array_merge($status, ['slug' => \Str::slug($status['name'])])
            );
        }
    }
}
