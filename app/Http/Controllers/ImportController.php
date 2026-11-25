<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Jobs\ProcessTicketImportJob;
use App\Models\Import;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function index(): View
    {
        $this->authorize('manageImports');

        return view('admin.imports.index');
    }

    public function store(StoreImportRequest $request): RedirectResponse
    {
        $this->authorize('manageImports');

        $file = $request->file('file');
        $filename = Str::uuid().'.csv';
        // The default "local" disk is rooted at storage/app/private, so store
        // under "imports" and keep the path relative to storage/app.
        $path = 'private/'.$file->storeAs('imports', $filename);

        // Count total rows without loading the entire file
        $totalRows = $this->countCsvRows(storage_path("app/{$path}"));

        $import = Import::create([
            'user_id' => $request->user()->id,
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'status' => 'pending',
            'total_rows' => $totalRows,
        ]);

        // Dispatch the import job
        ProcessTicketImportJob::dispatch($import);

        return redirect()
            ->route('admin.imports.show', $import)
            ->with('success', 'Import started successfully.');
    }

    public function show(Import $import): View
    {
        $this->authorize('manageImports');

        $errors = $import->errors()->orderBy('row_number', 'asc')->paginate(20);

        return view('admin.imports.show', compact('import', 'errors'));
    }

    public function progress(Import $import): JsonResponse
    {
        $this->authorize('manageImports');

        return response()->json([
            'processed' => $import->processed_rows,
            'imported' => $import->imported_rows,
            'failed' => $import->failed_rows,
            'total' => $import->total_rows,
            'status' => $import->status,
        ]);
    }

    public function failedCsv(Import $import): StreamedResponse
    {
        $this->authorize('manageImports');

        if ($import->failed_rows === 0) {
            abort(404, 'No failed rows to download.');
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"failed_import_{$import->id}.csv\"",
        ];

        return response()->stream(function () use ($import) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Row',
                'External Ref',
                'Subject',
                'Description',
                'Priority',
                'Category',
                'Customer Email',
                'Assigned Agent Email',
                'Created At',
                'Error Message',
            ]);

            $import->errors()->orderBy('row_number', 'asc')->chunk(200, function ($errors) use ($handle) {
                foreach ($errors as $error) {
                    $raw = is_array($error->raw_data) ? $error->raw_data : (json_decode($error->raw_data, true) ?? []);

                    $sanitize = function ($value): string {
                        $str = (string) $value;
                        if (in_array(substr($str, 0, 1), ['=', '+', '-', '@'], true)) {
                            return "'".$str;
                        }

                        return $str;
                    };

                    fputcsv($handle, [
                        $error->row_number,
                        $sanitize($raw['external_ref'] ?? ''),
                        $sanitize($raw['subject'] ?? ''),
                        $sanitize($raw['description'] ?? ''),
                        $raw['priority'] ?? '',
                        $raw['category'] ?? '',
                        $raw['customer_email'] ?? '',
                        $raw['assigned_agent_email'] ?? '',
                        $raw['created_at'] ?? '',
                        $error->error_message,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    private function countCsvRows(string $filePath): int
    {
        $handle = fopen($filePath, 'r');
        $rowCount = 0;

        if ($handle !== false) {
            while (! feof($handle)) {
                fgets($handle);
                $rowCount++;
            }
            fclose($handle);
        }

        // Subtract header row
        return max(0, $rowCount - 1);
    }
}
