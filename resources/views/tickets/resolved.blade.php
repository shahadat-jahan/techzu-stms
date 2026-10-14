<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Resolved & Closed Tickets') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Tickets that have been resolved or closed.
                </p>
            </div>

            <div class="flex items-center gap-4">
                <a href="{{ route('tickets.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                    {{ __('Active Tickets') }}
                </a>
                <a href="{{ route('tickets.create') }}" class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    {{ __('New Ticket') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('tickets.partials.table', ['emptyMessage' => 'No resolved or closed tickets.'])
        </div>
    </div>
</x-app-layout>
