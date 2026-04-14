<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeduplicateContactsCommand extends Command
{
    protected $signature = 'contacts:deduplicate {--dry-run : Show what would be merged without making changes}';

    protected $description = 'Merge duplicate contacts by phone number, keeping the best data from each duplicate';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Finding duplicate phone numbers...');

        $duplicates = DB::table('contacts')
            ->select('phone', DB::raw('COUNT(*) as cnt'))
            ->groupBy('phone')
            ->having('cnt', '>', 1)
            ->orderByDesc('cnt')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicate phone numbers found.');
            return self::SUCCESS;
        }

        $this->info("Found {$duplicates->count()} phone numbers with duplicates.");

        $totalMerged = 0;
        $totalDeleted = 0;

        $bar = $this->output->createProgressBar($duplicates->count());
        $bar->start();

        foreach ($duplicates as $dup) {
            $contacts = Contact::where('phone', $dup->phone)
                ->with('labels')
                ->orderBy('id')
                ->get();

            // Pick the "primary" contact: prefer one with a name, then most recent status, then earliest created
            $primary = $contacts->sortByDesc(function ($c) {
                $score = 0;
                if (!empty($c->name)) $score += 1000;
                if (!is_null($c->status_id)) $score += 100;
                if (!empty($c->source)) $score += 10;
                if (!is_null($c->date)) $score += 5;
                return $score;
            })->first();

            // Merge data from other duplicates into primary
            $others = $contacts->where('id', '!=', $primary->id);

            foreach ($others as $other) {
                // Fill in missing fields from duplicates
                if (empty($primary->name) && !empty($other->name)) {
                    $primary->name = $other->name;
                }
                if (is_null($primary->status_id) && !is_null($other->status_id)) {
                    $primary->status_id = $other->status_id;
                }
                if (empty($primary->source) && !empty($other->source)) {
                    $primary->source = $other->source;
                }
                if (is_null($primary->date) && !is_null($other->date)) {
                    $primary->date = $other->date;
                }
                if (empty($primary->notes) && !empty($other->notes)) {
                    $primary->notes = $other->notes;
                }
                if (empty($primary->country) && !empty($other->country)) {
                    $primary->country = $other->country;
                }

                // Collect labels from duplicate (SQL-level insertOrIgnore to handle duplicates safely)
                if (!$dryRun && $other->labels->isNotEmpty()) {
                    $rows = $other->labels->pluck('id')->map(fn ($labelId) => [
                        'contact_id' => $primary->id,
                        'label_id' => $labelId,
                    ])->toArray();
                    DB::table('contact_label')->insertOrIgnore($rows);
                }
            }

            if (!$dryRun) {
                $primary->save();

                // Delete duplicates (and their label pivots cascade)
                $otherIds = $others->pluck('id')->toArray();
                DB::table('contact_label')->whereIn('contact_id', $otherIds)->delete();
                Contact::whereIn('id', $otherIds)->delete();
            }

            $totalMerged++;
            $totalDeleted += $others->count();

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->warn("[DRY RUN] Would merge {$totalMerged} phone numbers, deleting {$totalDeleted} duplicate records.");
        } else {
            $this->info("Merged {$totalMerged} phone numbers, deleted {$totalDeleted} duplicate records.");
        }

        return self::SUCCESS;
    }
}
