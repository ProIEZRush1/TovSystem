<?php

namespace App\Jobs;

use App\Helpers\PhoneCountryHelper;
use App\Models\Contact;
use App\Models\Import;
use App\Services\CsvImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessCsvImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;

    public function __construct(
        protected int $importId
    ) {}

    public function handle(CsvImportService $csvService): void
    {
        $import = Import::findOrFail($this->importId);
        $import->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $filePath = Storage::disk('local')->path($import->filename);

        if (! file_exists($filePath)) {
            $import->update([
                'status' => 'failed',
                'errors' => ['File not found: ' . $import->filename],
                'completed_at' => now(),
            ]);
            return;
        }

        $delimiter = $csvService->detectDelimiter($filePath);
        $mapping = $this->normalizeMapping($import->column_mapping);
        $errors = [];
        $rowsImported = 0;
        $batchSize = 500;
        $batch = [];

        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle, 0, $delimiter); // Skip header row
        $rowNumber = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            try {
                $contact = $this->mapRow($row, $mapping, $headers);
                if (! empty($contact['phone'])) {
                    // Normalize phone number
                    $contact['phone'] = PhoneCountryHelper::normalize($contact['phone']);

                    // Auto-detect country from phone country code
                    $detectedCountry = PhoneCountryHelper::detectCountry($contact['phone']);

                    if ($detectedCountry) {
                        // Always set country from phone code (overrides CSV country column)
                        $contact['country'] = $detectedCountry;
                    } elseif (empty($contact['country'])) {
                        // No country code detected and no country in CSV
                        $errors[] = "Row {$rowNumber}: Phone '{$contact['phone']}' has no valid country code";
                        continue;
                    }

                    // Apply global_date if set (overrides per-row date)
                    if (!empty($import->global_date)) {
                        $contact['date'] = $import->global_date->format('Y-m-d');
                    }

                    $batch[] = $contact;
                }
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNumber}: {$e->getMessage()}";
            }

            if (count($batch) >= $batchSize) {
                $rowsImported += $this->upsertBatch($batch);
                $batch = [];
                $import->update(['rows_imported' => $rowsImported]);
            }
        }

        // Process remaining
        if (! empty($batch)) {
            $rowsImported += $this->upsertBatch($batch);
        }

        fclose($handle);

        $import->update([
            'status' => empty($errors) ? 'completed' : 'completed_with_errors',
            'rows_imported' => $rowsImported,
            'errors' => empty($errors) ? null : array_slice($errors, 0, 100),
            'completed_at' => now(),
        ]);

        Log::info("Import #{$this->importId} completed", [
            'rows_imported' => $rowsImported,
            'errors_count' => count($errors),
        ]);
    }

    /**
     * Normalize mapping keys: strip __col{N}__ prefix from frontend keys.
     * Returns a clean mapping of [headerName => dbField] or [index => dbField].
     */
    protected function normalizeMapping(array $mapping): array
    {
        $normalized = [];
        foreach ($mapping as $key => $dbField) {
            if ($dbField === null || $dbField === '') {
                continue;
            }

            // Strip __col{N}__ prefix if present
            if (preg_match('/^__col(\d+)__(.*)$/', $key, $matches)) {
                $colIndex = (int) $matches[1];
                $headerName = $matches[2];
                // Store by index for reliable matching
                $normalized[$colIndex] = $dbField;
            } else {
                // Regular header name key
                $normalized[$key] = $dbField;
            }
        }
        return $normalized;
    }

    protected function mapRow(array $row, array $mapping, array $headers): array
    {
        $contact = [
            'updated_at' => now(),
            'created_at' => now(),
        ];

        foreach ($mapping as $key => $dbField) {
            // If key is numeric, it's a direct column index
            if (is_int($key)) {
                $index = $key;
            } else {
                // Find column index by header name
                $index = array_search($key, $headers);
                if ($index === false) {
                    foreach ($headers as $i => $h) {
                        if (mb_strtolower(trim($h)) === mb_strtolower(trim($key))) {
                            $index = $i;
                            break;
                        }
                    }
                }
                if ($index === false) {
                    continue;
                }
            }

            $value = $row[$index] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }

            $contact[$dbField] = $value ?: null;
        }

        return $contact;
    }

    protected function upsertBatch(array $batch): int
    {
        try {
            // Preserve existing names: if contact exists and has a name, don't overwrite it
            $phones = array_column($batch, 'phone');
            $existingNames = Contact::whereIn('phone', $phones)
                ->whereNotNull('name')
                ->where('name', '!=', '')
                ->pluck('name', 'phone')
                ->toArray();

            foreach ($batch as &$contact) {
                if (isset($existingNames[$contact['phone']])) {
                    // Existing has a name - keep it (unless the new import row explicitly has one too)
                    if (empty($contact['name'])) {
                        $contact['name'] = $existingNames[$contact['phone']];
                    }
                }
            }
            unset($contact);

            Contact::upsert(
                $batch,
                ['phone'],
                ['name', 'country', 'status_id', 'source', 'date', 'notes', 'updated_at']
            );
            return count($batch);
        } catch (\Throwable $e) {
            Log::error("Import batch failed", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $import = Import::find($this->importId);
        $import?->update([
            'status' => 'failed',
            'errors' => [$exception->getMessage()],
            'completed_at' => now(),
        ]);
    }
}
