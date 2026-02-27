<?php

namespace App\Services;

class CsvImportService
{
    public function getPreview(string $filePath, int $rows = 5): array
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new \RuntimeException('Cannot open file: ' . $filePath);
        }

        $delimiter = $this->detectDelimiter($filePath);
        $headers = fgetcsv($handle, 0, $delimiter);
        $preview = [];

        for ($i = 0; $i < $rows; $i++) {
            $row = fgetcsv($handle, 0, $delimiter);
            if ($row === false) {
                break;
            }
            $preview[] = $row;
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'rows' => $preview,
            'delimiter' => $delimiter,
        ];
    }

    public function countRows(string $filePath): int
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return 0;
        }

        $count = -1; // Skip header
        while (fgetcsv($handle) !== false) {
            $count++;
        }

        fclose($handle);

        return max(0, $count);
    }

    public function detectDelimiter(string $filePath): string
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return ',';
        }

        $line = fgets($handle);
        fclose($handle);

        if (! $line) {
            return ',';
        }

        $delimiters = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];
        foreach ($delimiters as $delimiter => &$count) {
            $count = substr_count($line, $delimiter);
        }

        return array_search(max($delimiters), $delimiters) ?: ',';
    }

    public function getHeaders(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return [];
        }

        $delimiter = $this->detectDelimiter($filePath);
        $headers = fgetcsv($handle, 0, $delimiter);
        fclose($handle);

        return $headers ?: [];
    }
}
