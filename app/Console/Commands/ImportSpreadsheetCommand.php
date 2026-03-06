<?php

namespace App\Console\Commands;

use App\Helpers\PhoneCountryHelper;
use App\Models\Contact;
use App\Models\Label;
use Illuminate\Console\Command;

class ImportSpreadsheetCommand extends Command
{
    protected $signature = 'contacts:import-spreadsheet {file}';
    protected $description = 'Import contacts from the Google Spreadsheet CSV export';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle); // Skip header row

        $this->info("Headers: " . implode(' | ', $header));
        // Columns: 0=ID, 1=Teléfono, 2=País, 3=MessageType(f), 4=DeviceLabel, 5=Preparado, 6=empty, 7=Fecha

        $batch = [];
        $labelCache = [];
        $contactLabels = []; // phone => [label_names]
        $rowCount = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $phone = trim($row[1] ?? '');
            if (empty($phone)) {
                $skipped++;
                continue;
            }

            // Clean phone: remove any trailing garbage like "-1569338053"
            if (preg_match('/^(\+\d+)-\d+$/', $phone, $m)) {
                $phone = $m[1];
            }

            $phone = PhoneCountryHelper::normalize($phone);
            $country = PhoneCountryHelper::detectCountry($phone);

            $source = trim($row[3] ?? '');
            $deviceLabel = trim($row[4] ?? '');
            $dateStr = trim($row[7] ?? '');

            // Parse date (d/m/yy format)
            $date = null;
            if ($dateStr) {
                $parts = explode('/', $dateStr);
                if (count($parts) === 3) {
                    $year = (int) $parts[2];
                    if ($year < 100) {
                        $year += 2000;
                    }
                    $date = sprintf('%04d-%02d-%02d', $year, (int) $parts[1], (int) $parts[0]);
                }
            }

            $batch[] = [
                'phone' => $phone,
                'country' => $country,
                'source' => $source ?: null,
                'date' => $date,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Track labels for this contact
            if ($deviceLabel) {
                $contactLabels[$phone] = $contactLabels[$phone] ?? [];
                if (! in_array($deviceLabel, $contactLabels[$phone])) {
                    $contactLabels[$phone][] = $deviceLabel;
                }
            }

            $rowCount++;

            if (count($batch) >= 500) {
                $this->upsertBatch($batch);
                $batch = [];
                $this->output->write("\rProcessed: {$rowCount}");
            }
        }

        if (! empty($batch)) {
            $this->upsertBatch($batch);
        }

        fclose($handle);

        $this->newLine();
        $this->info("Imported {$rowCount} contacts, skipped {$skipped}.");

        // Create labels and assign them
        $uniqueLabels = collect($contactLabels)->flatten()->unique()->filter()->values();

        if ($uniqueLabels->isNotEmpty()) {
            $this->info("Creating {$uniqueLabels->count()} label(s): " . $uniqueLabels->join(', '));

            foreach ($uniqueLabels as $i => $labelName) {
                $labelCache[$labelName] = Label::firstOrCreate(
                    ['slug' => \Str::slug($labelName)],
                    ['name' => $labelName, 'color' => '#6B7280', 'sort_order' => $i]
                );
            }

            $this->info('Assigning labels to contacts...');
            $assigned = 0;

            foreach ($contactLabels as $phone => $labelNames) {
                $contact = Contact::where('phone', $phone)->first();
                if ($contact) {
                    $labelIds = collect($labelNames)
                        ->map(fn ($name) => $labelCache[$name]?->id)
                        ->filter()
                        ->toArray();
                    $contact->labels()->syncWithoutDetaching($labelIds);
                    $assigned++;
                }

                if ($assigned % 500 === 0) {
                    $this->output->write("\rLabels assigned: {$assigned}");
                }
            }

            $this->newLine();
            $this->info("Labels assigned to {$assigned} contacts.");
        }

        return 0;
    }

    protected function upsertBatch(array $batch): void
    {
        Contact::upsert(
            $batch,
            ['phone'],
            ['country', 'source', 'date', 'updated_at']
        );
    }
}
