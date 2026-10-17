<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Import Details') }}
                </h2>
                <p class="text-sm font-medium text-gray-500">Import ID: #{{ $import->id }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.imports.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                    Back to Imports
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12" data-import-id="{{ $import->id }}">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-sm text-gray-600 font-medium">Original File</div>
                            <div class="text-sm text-gray-900 mt-1">{{ $import->original_filename }}</div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-sm text-gray-600 font-medium">Started At</div>
                            <div class="text-sm text-gray-900 mt-1">
                                @if ($import->started_at)
                                    <x-timestamp :value="$import->started_at" format="Y-m-d h:i:s A" />
                                @elseif ($import->status === 'pending')
                                    <span class="text-yellow-600">Pending</span>
                                @else
                                    <span class="text-yellow-600">Starting...</span>
                                @endif
                            </div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-sm text-gray-600 font-medium">Finished At</div>
                            <div class="text-sm text-gray-900 mt-1">
                                @if ($import->finished_at)
                                    <x-timestamp :value="$import->finished_at" format="Y-m-d h:i:s A" />
                                @else
                                    <span class="text-yellow-600">Not Finished</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Progress</h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="text-sm text-blue-600 font-medium">Total Rows</div>
                                <div class="text-xl font-bold text-blue-900" id="total-count">{{ $import->total_rows }}</div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-sm text-green-600 font-medium">Processed</div>
                                <div class="text-xl font-bold text-green-900" id="processed-count">{{ $import->processed_rows }}</div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-sm text-green-600 font-medium">Imported</div>
                                <div class="text-xl font-bold text-green-900" id="imported-count">{{ $import->imported_rows }}</div>
                            </div>
                            <div class="bg-red-50 p-4 rounded-lg">
                                <div class="text-sm text-red-600 font-medium">Failed</div>
                                <div class="text-xl font-bold text-red-900" id="failed-count">{{ $import->failed_rows }}</div>
                            </div>
                        </div>

                        @if ($import->status === 'processing' || $import->status === 'pending')
                            <div class="mt-6">
                                <div class="flex justify-between text-sm text-gray-600 mb-2">
                                    <span>Overall Progress</span>
                                    <span id="progress-text">{{ $import->total_rows > 0 ? round(($import->imported_rows + $import->failed_rows) / $import->total_rows * 100) : 0 }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div id="progress-bar" class="bg-indigo-600 h-3 rounded-full" style="width: {{ $import->total_rows > 0 ? ($import->imported_rows + $import->failed_rows) / $import->total_rows * 100 : 0 }}%"></div>
                                </div>
                                <div class="mt-2">
                                    <span id="status-badge" class="px-2 py-1 rounded-full text-xs font-medium @switch($import->status)
                                        @case('pending') bg-yellow-100 text-yellow-800
                                        @case('processing') bg-blue-100 text-blue-800
                                        @case('completed') bg-green-100 text-green-800
                                        @case('completed_with_errors') bg-orange-100 text-orange-800
                                        @case('failed') bg-red-100 text-red-800
                                    @endswitch">{{ ucfirst($import->status) }}</span>
                                </div>
                            </div>
                        @endif

                        @if ($import->status === 'completed' || $import->status === 'completed_with_errors' || $import->status === 'failed')
                            <div class="mt-6">
                                <div id="completion-message" class="p-4 rounded-lg @switch($import->status)
                                    @case('completed') bg-green-50 border border-green-200 text-green-800
                                    @case('completed_with_errors') bg-yellow-50 border border-yellow-200 text-yellow-800
                                    @case('failed') bg-red-50 border border-red-200 text-red-800
                                @endswitch">
                                    @switch($import->status)
                                        @case('completed')
                                            ✅ Import completed successfully! {{ $import->imported_rows }} rows were imported.
                                            @break
                                        @case('completed_with_errors')
                                            ⚠️ Import completed with errors. {{ $import->imported_rows }} rows imported, {{ $import->failed_rows }} rows failed.
                                            @break
                                        @case('failed')
                                            ❌ Import failed. {{ $import->error_message }}
                                            @break
                                    @endswitch
                                </div>
                                @if ($import->failed_rows > 0)
                                    <a href="{{ route('admin.imports.failed-csv', $import) }}" class="mt-4 inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150" id="download-failed-csv">
                                        Download All Failed Rows CSV
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if ($import->failed_rows > 0)
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Failed Rows ({{ $import->failed_rows }})</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        <tr>
                                            <th class="px-4 py-3">Row</th>
                                            <th class="px-4 py-3">External Ref</th>
                                            <th class="px-4 py-3">Subject</th>
                                            <th class="px-4 py-3">Error Message</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($errors as $error)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3">{{ $error->row_number }}</td>
                                                <td class="px-4 py-3">{{ is_array($error->raw_data) ? ($error->raw_data['external_ref'] ?? 'N/A') : 'N/A' }}</td>
                                                <td class="px-4 py-3">{{ is_array($error->raw_data) ? ($error->raw_data['subject'] ?? 'N/A') : 'N/A' }}</td>
                                                <td class="px-4 py-3">{{ $error->error_message }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4 flex justify-center">
                                {{ $errors->links() }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/imports.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const importId = document.querySelector('[data-import-id]').dataset.importId;
            let hasReloaded = sessionStorage.getItem('import_reloaded_' + importId) === '1';

            new ImportProgressManager({
                importId: importId,
                progressEndpoint: '/admin/imports/' + importId + '/progress',
                failedCsvEndpoint: '/admin/imports/' + importId + '/failed-csv',
                onComplete: function(data) {
                    // Reload page once to show final state including failed rows table
                    if (!hasReloaded) {
                        sessionStorage.setItem('import_reloaded_' + importId, '1');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    }
                },
            }).start();
        });
    </script>
</x-app-layout>
