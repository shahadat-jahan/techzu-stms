<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Jobs\ProcessTicketImportJob;
use App\Models\Import;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ImportController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manageImports');

        return view('admin.imports.index');
    }

    public function store(StoreImportRequest $request): RedirectResponse
    {
        Gate::authorize('manageImports');

        $file = $request->file('file');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
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
        Gate::authorize('manageImports');

        $import->load(['errors' => function ($query) {
            $query->orderBy('row_number', 'asc');
        }]);

        $errors = $import->errors()->paginate(20);

        return view('admin.imports.show', compact('import', 'errors'));
    }

    public function progress(Import $import): JsonResponse
    {
        Gate::authorize('manageImports');

        return response()->json([
            'processed' => $import->processed_rows,
            'imported' => $import->imported_rows,
            'failed' => $import->failed_rows,
            'total' => $import->total_rows,
            'status' => $import->status,
        ]);
    }

    public function failedCsv(Import $import): Response
    {
        Gate::authorize('manageImports');

        if ($import->failed_rows === 0) {
            abort(404, 'No failed rows to download.');
        }

        $csvContent = $this->generateFailedCsvContent($import);

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=failed_import_{$import->id}.csv",
        ]);
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

    private function generateFailedCsvContent(Import $import): string
    {
        $errors = $import->errors;

        $csv = "Row,External Ref,Subject,Description,Priority,Category,Customer Email,Assigned Agent Email,Created At,Error Message\n";

        foreach ($errors as $error) {
            $rawData = is_array($error->raw_data) ? $error->raw_data : (json_decode($error->raw_data, true) ?? []);
            $externalRef = $rawData['external_ref'] ?? '';
            $subject = $rawData['subject'] ?? '';
            $description = $rawData['description'] ?? '';
            $priority = $rawData['priority'] ?? '';
            $category = $rawData['category'] ?? '';
            $customerEmail = $rawData['customer_email'] ?? '';
            $assignedAgentEmail = $rawData['assigned_agent_email'] ?? '';
            $createdAt = $rawData['created_at'] ?? '';
            $csv .= "{$error->row_number},{$externalRef},{$subject},{$description},{$priority},{$category},{$customerEmail},{$assignedAgentEmail},{$createdAt},{$error->error_message}\n";
        }

        return $csv;
    }
}
