@php
    $statusEnum = \App\Enums\TicketStatus::class;
    $canManage = Auth::user()->isAgent() || Auth::user()->isAdmin();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $ticket->ticket_number }}
                </h2>
                <x-status-badge :status="$ticket->status" />
                <x-priority-badge :priority="$ticket->priority" />
            </div>
            <a href="{{ route('tickets.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                {{ __('Back to tickets') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="bg-red-50 border border-red-400 text-red-800 rounded-lg p-4 text-sm" role="alert">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <div class="bg-white shadow sm:rounded-lg">
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h3 class="text-sm font-semibold text-gray-800">{{ $ticket->subject }}</h3>
                        </div>
                        <div class="whitespace-pre-wrap p-6 text-sm text-gray-700">{{ $ticket->description }}</div>
                    </div>

                    @if ($canManage)
                        <div class="bg-white shadow sm:rounded-lg">
                            <div class="border-b border-gray-100 px-6 py-4">
                                <h3 class="text-sm font-semibold text-gray-800">{{ __('Actions') }}</h3>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 p-6">
                                @if ($ticket->status->canTransitionTo($statusEnum::InProgress) && $ticket->status !== $statusEnum::Resolved)
                                    <form method="POST" action="{{ route('tickets.status', $ticket) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="in_progress">
                                        <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                            {{ __('Start Progress') }}
                                        </button>
                                    </form>
                                @endif

                                @if ($ticket->status->canTransitionTo($statusEnum::WaitingForCustomer))
                                    <form method="POST" action="{{ route('tickets.status', $ticket) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="waiting_for_customer">
                                        <button type="submit" class="inline-flex items-center rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-500">
                                            {{ __('Waiting for Customer') }}
                                        </button>
                                    </form>
                                @endif

                                @if ($ticket->status->canTransitionTo($statusEnum::Resolved))
                                    <form method="POST" action="{{ route('tickets.resolve', $ticket) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500">
                                            {{ __('Resolve') }}
                                        </button>
                                    </form>
                                @endif

                                @if ($ticket->status->canTransitionTo($statusEnum::Closed))
                                    <form method="POST" action="{{ route('tickets.close', $ticket) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center rounded-md bg-gray-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-500">
                                            {{ __('Close') }}
                                        </button>
                                    </form>
                                @endif

                                @if ($ticket->status === $statusEnum::Resolved || $ticket->status === $statusEnum::Closed)
                                    <form method="POST" action="{{ route('tickets.reopen', $ticket) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500">
                                            {{ __('Reopen') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                <div class="space-y-6">
                    <div class="bg-white shadow sm:rounded-lg">
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h3 class="text-sm font-semibold text-gray-800">{{ __('Details') }}</h3>
                        </div>
                        <dl class="divide-y divide-gray-100 text-sm">
                            <div class="flex justify-between gap-4 px-6 py-3">
                                <dt class="text-gray-500">Category</dt>
                                <dd class="font-medium text-gray-900">{{ $ticket->category?->name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 px-6 py-3">
                                <dt class="text-gray-500">Created by</dt>
                                <dd class="font-medium text-gray-900">{{ $ticket->customer?->name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 px-6 py-3">
                                <dt class="text-gray-500">Assigned agent</dt>
                                <dd class="font-medium text-gray-900">{{ $ticket->agent?->name ?? 'Unassigned' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 px-6 py-3">
                                <dt class="text-gray-500">Created</dt>
                                <dd class="font-medium text-gray-900">{{ $ticket->created_at->format('M j, Y H:i') }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 px-6 py-3">
                                <dt class="text-gray-500">Due</dt>
                                <dd class="font-medium text-gray-900">{{ $ticket->due_at?->format('M j, Y H:i') ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="bg-white shadow sm:rounded-lg">
                        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                            <h3 class="text-sm font-semibold text-gray-800">{{ __('Edit Ticket') }}</h3>
                            <a href="{{ route('tickets.edit', $ticket) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                                {{ __('Edit') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>


            <div class="bg-white shadow sm:rounded-lg">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-gray-800">{{ __('Activity Timeline') }}</h3>
                </div>
                <div class="p-6">
                    @if ($activities->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('No activity recorded yet.') }}</p>
                    @else
                        <ol class="relative ms-3 space-y-6 border-s border-gray-200">
                            @foreach ($activities as $activity)
                                <li class="ms-4">
                                    <span class="absolute -start-[6px] mt-1.5 h-3 w-3 rounded-full bg-indigo-500 ring-4 ring-white"></span>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span class="text-xs font-semibold uppercase tracking-wide text-indigo-600">
                                            {{ ucwords(str_replace('_', ' ', $activity->action)) }}
                                        </span>
                                        <span class="text-xs text-gray-400">
                                            {{ $activity->created_at->format('M j, Y H:i:s') }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-700">
                                        {{ $activity->meta['message'] ?? ucwords(str_replace('_', ' ', $activity->action)) }}
                                    </p>
                                    @if (! empty($activity->meta['note']))
                                        <p class="mt-1 text-sm text-gray-500 italic">{{ $activity->meta['note'] }}</p>
                                    @endif
                                    <p class="mt-0.5 text-xs text-gray-500">
                                        {{ __('by :actor', ['actor' => $activity->actor?->name ?? 'System']) }}
                                    </p>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

