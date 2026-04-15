<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixMexicanPhonesCommand extends Command
{
    protected $signature = 'contacts:fix-mexican-phones {--dry-run}';

    protected $description = 'Insert the "1" mobile prefix after +52 for Mexican numbers so WhatsApp accepts them';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Find MX contacts with exactly +52 + 10 digits (no mobile "1" prefix)
        $query = Contact::where('phone', 'REGEXP', '^\\+52[0-9]{10}$');
        $total = $query->count();

        if ($total === 0) {
            $this->info('No Mexican numbers need fixing.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} Mexican numbers missing the mobile 1 prefix.");

        $fixed = 0;
        $collisions = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(500, function ($contacts) use (&$fixed, &$collisions, $dryRun, $bar) {
            foreach ($contacts as $contact) {
                $newPhone = '+521' . substr($contact->phone, 3);

                // If the fixed version already exists, merge: keep the existing one and delete the broken duplicate
                $existing = Contact::where('phone', $newPhone)->first();

                if ($existing) {
                    if (!$dryRun) {
                        // Merge: prefer non-null fields from broken one into existing if missing
                        $dirty = false;
                        if (empty($existing->name) && !empty($contact->name)) { $existing->name = $contact->name; $dirty = true; }
                        if (is_null($existing->status_id) && !is_null($contact->status_id)) { $existing->status_id = $contact->status_id; $dirty = true; }
                        if (is_null($existing->date) && !is_null($contact->date)) { $existing->date = $contact->date; $dirty = true; }
                        if ($dirty) $existing->save();

                        // Move any labels over
                        $labelIds = $contact->labels()->pluck('labels.id')->toArray();
                        if (!empty($labelIds)) {
                            $rows = collect($labelIds)->map(fn ($id) => ['contact_id' => $existing->id, 'label_id' => $id])->toArray();
                            DB::table('contact_label')->insertOrIgnore($rows);
                        }

                        DB::table('contact_label')->where('contact_id', $contact->id)->delete();
                        $contact->delete();
                    }
                    $collisions++;
                } else {
                    if (!$dryRun) {
                        $contact->update(['phone' => $newPhone]);
                    }
                    $fixed++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $prefix = $dryRun ? '[DRY RUN] Would' : 'Fixed';
        $this->info("{$prefix} update {$fixed} phones, merge {$collisions} collisions.");

        return self::SUCCESS;
    }
}
