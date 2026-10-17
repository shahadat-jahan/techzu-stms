<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Import Tickets') }}
                </h2>
                <p class="text-sm font-medium text-gray-500">Upload CSV file to import tickets in bulk</p>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.imports.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="space-y-6">
                            <div>
                                <x-input-label for="file" :value="__('CSV File')" />
                                <div class="mt-1">
                                    <input type="file" name="file" id="file" accept=".csv,.txt"
                                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                                </div>
                                <x-input-error :messages="$errors->get('file')" class="mt-2" />
                                <p class="text-xs text-gray-500 mt-1">CSV format: external_ref,subject,description,priority,category,status,customer_email,assigned_agent_email,created_at</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <a href="{{ asset('sample-import.csv') }}" class="text-indigo-600 hover:text-indigo-800 font-medium" download>Download Sample CSV</a>
                                </p>
                            </div>

                            <div class="flex items-center gap-4">
                                <x-primary-button>{{ __('Upload and Import') }}</x-primary-button>
                                <x-secondary-button type="button" onclick="window.location.href='{{ route('admin.imports.index') }}'">{{ __('Cancel') }}</x-secondary-button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            @if ($import ?? null)
                <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Import Status</h3>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="text-sm text-blue-600 font-medium">Total Rows</div>
                                <div class="text-2xl font-bold text-blue-900">{{ $import->total_rows }}</div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-sm text-green-600 font-medium">Imported</div>
                                <div class="text-2xl font-bold text-green-900">{{ $import->imported_rows }}</div>
                            </div>
                            <div class="bg-yellow-50 p-4 rounded-lg">
                                <div class="text-sm text-yellow-600 font-medium">Failed</div>
                                <div class="text-2xl font-bold text-yellow-900">{{ $import->failed_rows }}</div>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="text-sm text-gray-600 font-medium">Status</div>
                                <div class="text-lg font-semibold">
                                    @switch($import->status)
                                        @case('pending')
                                            <span class="text-yellow-600">Pending</span>
                                            @break
                                        @case('processing')
                                            <span class="text-blue-600">Processing</span>
                                            @break
                                        @case('completed')
                                            <span class="text-green-600">Completed</span>
                                            @break
                                        @case('completed_with_errors')
                                            <span class="text-orange-600">Completed with Errors</span>
                                            @break
                                        @case('failed')
                                            <span class="text-red-600">Failed</span>
                                            @break
                                    @endswitch
                                </div>
                            </div>
                        </div>

                        @if ($import->status === 'processing' || $import->status === 'pending')
                            <div class="mb-6">
                                <div class="flex justify-between text-sm text-gray-600 mb-2">
                                    <span>Progress</span>
                                    <span>{{ $import->processed_rows }}/{{ $import->total_rows }} rows processed</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-indigo-600 h-2.5 rounded-full" style="width: {{ $import->total_rows > 0 ? ($import->processed_rows / $import->total_rows) * 100 : 0 }}%"></div>
                                </div>
                            </div>
                        @endif

                        <div class="flex gap-4">
                            <a href="{{ route('admin.imports.show', $import) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                                View Details
                            </a>
                            @if ($import->failed_rows > 0)
                                <a href="{{ route('admin.imports.failedCsv', $import) }}" class="text-red-600 hover:text-red-800 font-medium">
                                    Download Failed Rows CSV
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>