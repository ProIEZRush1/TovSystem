<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCsvImport;
use App\Models\Import;
use App\Services\CsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Import/Create');
    }

    public function preview(Request $request, CsvImportService $csvService): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:102400',
        ]);

        $file = $request->file('file');
        $path = $file->store('imports');
        $fullPath = storage_path('app/private/' . $path);

        $preview = $csvService->getPreview($fullPath);
        $totalRows = $csvService->countRows($fullPath);

        $contactFields = [
            '' => '-- Skip --',
            'phone' => 'Phone',
            'name' => 'Name',
            'source' => 'Source',
            'date' => 'Date',
            'notes' => 'Notes',
            'status_id' => 'Status ID',
        ];

        return response()->json([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'headers' => $preview['headers'],
            'preview_rows' => $preview['rows'],
            'total_rows' => $totalRows,
            'contact_fields' => $contactFields,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'path' => 'required|string',
            'original_name' => 'required|string',
            'total_rows' => 'required|integer|min:1',
            'column_mapping' => 'required|array',
            'global_date' => 'nullable|date',
        ]);

        $import = Import::create([
            'user_id' => $request->user()->id,
            'filename' => $validated['path'],
            'original_filename' => $validated['original_name'],
            'total_rows' => $validated['total_rows'],
            'column_mapping' => $validated['column_mapping'],
            'global_date' => $validated['global_date'] ?? null,
            'status' => 'pending',
        ]);

        ProcessCsvImport::dispatch($import->id);

        return redirect()->route('import.show', $import)->with('success', 'Import started.');
    }

    public function show(Import $import): Response
    {
        return Inertia::render('Import/Show', [
            'import' => $import->append('progress_percentage'),
        ]);
    }

    public function status(Import $import): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status' => $import->status,
            'rows_imported' => $import->rows_imported,
            'total_rows' => $import->total_rows,
            'progress_percentage' => $import->progress_percentage,
            'errors' => $import->errors,
        ]);
    }
}
