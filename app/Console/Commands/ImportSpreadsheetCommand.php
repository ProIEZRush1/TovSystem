<?php

namespace App\Console\Commands;

use App\Helpers\PhoneCountryHelper;
use App\Models\Contact;
use App\Models\Label;
use App\Models\Status;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

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
        $header = fgetcsv($handle);

        $this->info("Headers: " . implode(' | ', $header));
        // Columns: 0=ID, 1=Teléfono, 2=País, 3=Estado(D), 4=Celular/Label(E), 5=Preparado, 6=empty, 7=Fecha

        // First pass: collect unique status names and label names
        $statusNames = [];
        $labelNames = [];
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $phone = trim($row[1] ?? '');
            if (empty($phone)) {
                continue;
            }

            $statusName = trim($row[3] ?? '');
            $labelName = trim($row[4] ?? '');

            if ($statusName && $statusName !== 'f') {
                $statusNames[$statusName] = true;
            }
            if ($labelName) {
                $labelNames[$labelName] = true;
            }

            $rows[] = $row;
        }
        fclose($handle);

        // Create statuses
        $statusCache = [];
        $existingStatuses = Status::all();
        $maxSortOrder = $existingStatuses->max('sort_order') ?? 0;

        foreach (array_keys($statusNames) as $name) {
            $slug = Str::slug($name);
            $existing = $existingStatuses->firstWhere('slug', $slug);

            if ($existing) {
                $statusCache[$name] = $existing;
                $this->info("Status exists: {$name} (id: {$existing->id})");
            } else {
                $maxSortOrder++;
                $status = Status::create([
                    'name' => $name,
                    'slug' => $slug,
                    'color' => $this->generateColor($maxSortOrder),
                    'sort_order' => $maxSortOrder,
                ]);
                $statusCache[$name] = $status;
                $this->info("Status created: {$name} (id: {$status->id})");
            }
        }

        // Create labels
        $labelCache = [];
        foreach (array_keys($labelNames) as $i => $name) {
            $labelCache[$name] = Label::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'color' => '#6B7280', 'sort_order' => $i]
            );
            $this->info("Label: {$name} (id: {$labelCache[$name]->id})");
        }

        // Second pass: import contacts with status_id
        $batch = [];
        $contactMeta = []; // phone => ['labels' => [], 'status_id' => int]
        $rowCount = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $phone = trim($row[1] ?? '');
            if (empty($phone)) {
                $skipped++;
                continue;
            }

            // Clean phone
            if (preg_match('/^(\+\d+)-\d+$/', $phone, $m)) {
                $phone = $m[1];
            }

            $phone = PhoneCountryHelper::normalize($phone);
            $country = PhoneCountryHelper::detectCountry($phone);

            $statusName = trim($row[3] ?? '');
            $labelName = trim($row[4] ?? '');
            $dateStr = trim($row[7] ?? '');

            // Resolve status_id
            $statusId = null;
            if ($statusName && isset($statusCache[$statusName])) {
                $statusId = $statusCache[$statusName]->id;
            }

            // Parse date (d/m/yy format) with validation
            $date = null;
            if ($dateStr) {
                $parts = explode('/', $dateStr);
                if (count($parts) === 3) {
                    $day = (int) $parts[0];
                    $month = (int) $parts[1];
                    $year = (int) $parts[2];
                    if ($year < 100) {
                        $year += 2000;
                    }
                    if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31 && checkdate($month, $day, $year)) {
                        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    }
                }
            }

            $contactData = [
                'phone' => $phone,
                'country' => $country,
                'status_id' => $statusId,
                'date' => $date,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $batch[] = $contactData;

            // Track labels and status for this contact
            if (! isset($contactMeta[$phone])) {
                $contactMeta[$phone] = ['labels' => [], 'status_id' => null];
            }
            if ($labelName && isset($labelCache[$labelName])) {
                if (! in_array($labelName, $contactMeta[$phone]['labels'])) {
                    $contactMeta[$phone]['labels'][] = $labelName;
                }
            }
            if ($statusId) {
                $contactMeta[$phone]['status_id'] = $statusId;
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

        $this->newLine();
        $this->info("Imported {$rowCount} contacts, skipped {$skipped}.");

        // Update status_id for contacts where upsert didn't set it (upsert only updates listed columns)
        $this->info('Setting statuses on contacts...');
        $statusUpdated = 0;
        foreach ($contactMeta as $phone => $meta) {
            if ($meta['status_id']) {
                Contact::where('phone', $phone)->update(['status_id' => $meta['status_id']]);
                $statusUpdated++;
                if ($statusUpdated % 500 === 0) {
                    $this->output->write("\rStatuses set: {$statusUpdated}");
                }
            }
        }
        $this->newLine();
        $this->info("Statuses set on {$statusUpdated} contacts.");

        // Assign labels
        $this->info('Assigning labels to contacts...');
        $labelsAssigned = 0;
        foreach ($contactMeta as $phone => $meta) {
            if (empty($meta['labels'])) {
                continue;
            }

            $contact = Contact::where('phone', $phone)->first();
            if ($contact) {
                $labelIds = collect($meta['labels'])
                    ->map(fn ($name) => $labelCache[$name]?->id)
                    ->filter()
                    ->toArray();
                $contact->labels()->syncWithoutDetaching($labelIds);
                $labelsAssigned++;

                if ($labelsAssigned % 500 === 0) {
                    $this->output->write("\rLabels assigned: {$labelsAssigned}");
                }
            }
        }
        $this->newLine();
        $this->info("Labels assigned to {$labelsAssigned} contacts.");

        return 0;
    }

    protected function upsertBatch(array $batch): void
    {
        Contact::upsert(
            $batch,
            ['phone'],
            ['country', 'status_id', 'date', 'updated_at']
        );
    }

    protected function generateColor(int $index): string
    {
        $colors = ['#8B5CF6', '#EC4899', '#F97316', '#14B8A6', '#6366F1', '#84CC16', '#EAB308', '#0EA5E9'];
        return $colors[$index % count($colors)];
    }
}
