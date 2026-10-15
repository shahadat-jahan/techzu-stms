@php
    $statusEnum = \App\Enums\TicketStatus::class;
    $user = Auth::user();
    $canManage = $user !== null && ($user->isAgent() || $user->isAdmin());
    $canAddInternalNote = $canManage;
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
            <div class="flex items-center gap-3">
                @can('update', $ticket)
                    <a href="{{ route('tickets.edit', $ticket) }}"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        {{ __('Edit') }}
                    </a>
                @endcan
                <a href="{{ route('tickets.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                    {{ __('Back to tickets') }}
                </a>
            </div>
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

                    <div class="bg-white shadow sm:rounded-lg">
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h3 class="text-sm font-semibold text-gray-800">{{ __('Conversation') }}</h3>
                        </div>
                        <div class="p-6 space-y-6">
                            @forelse ($replies as $reply)
                                <div class="flex gap-4">
                                    <div class="min-w-0 flex-1 rounded-md bg-gray-50 px-4 py-3 text-sm">
                                        @if ($reply->is_internal && $canAddInternalNote)
                                            <span
                                                class="mb-1 inline-block rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800">
                                                {{ __('Internal note') }}
                                            </span>
                                        @endif
                                        <div class="text-gray-900">{{ $reply->body }}</div>
                                        <div class="mt-1 text-xs text-gray-400">
                                            {{ $reply->user->name ?? __('System') }} &middot;
                                            {{ $reply->created_at->format('M j, Y H:i') }}
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">{{ __('No replies yet. Start the conversation below.') }}
                                </p>
                            @endforelse
                        </div>
                    </div>

                    @can('reply', $ticket)
                        <div class="bg-white shadow sm:rounded-lg">
                            <div class="border-b border-gray-100 px-6 py-4">
                                <h3 class="text-sm font-semibold text-gray-800">{{ __('Add Reply') }}</h3>
                            </div>
                            <form method="POST" action="{{ route('tickets.replies.store', $ticket) }}"
                                class="px-6 py-3 space-y-4">
                                @csrf
                                <div class="pb-3">
                                    <label for="body"
                                        class="block text-sm font-medium text-gray-700">{{ __('Your reply') }}</label>
                                    <textarea id="body" name="body" rows="4" required
                                        placeholder="{{ __('Type your reply...') }}"
                                        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('body') }}</textarea>
                                    <x-input-error :messages="$errors->get('body')" class="mt-2" />
                                </div>
                                @if ($canAddInternalNote)
                                    <label class="flex items-center gap-2 text-sm text-gray-700 mb-2">
                                        <input type="checkbox" name="is_internal" value="1" @checked(old('is_internal'))
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        {{ __('Internal note (visible to staff only)') }}
                                    </label>
                                @endif
                                <button type="submit"
                                    class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                    {{ __('Post Reply') }}
                                </button>
                            </form>
                        </div>
                    @endcan

                    <div class="bg-white shadow sm:rounded-lg">
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h3 class="text-sm font-semibold text-gray-800">{{ __('Attachments') }}</h3>
                            <span class="text-xs text-gray-400">{{ $attachments->count() }} {{ __('attached') }}</span>
                        </div>
                        <div class="p-6 space-y-3">
                            @forelse ($attachments as $attachment)
                                <div class="flex items-center justify-between gap-3 rounded-md bg-gray-50 px-4 py-3">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $attachment->original_name }}
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            {{ $attachment->size }} bytes &middot;
                                            {{ $attachment->created_at->format('M j, Y H:i') }}
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 gap-2">
                                        <a href="{{ route('attachments.download', $attachment) }}"
                                            class="text-xs font-medium text-indigo-600 hover:text-indigo-900">{{ __('Download') }}</a>
                                        @if ($user !== null && $user->can('delete', $attachment))
                                            <form method="POST" action="{{ route('attachments.destroy', $attachment) }}"
                                                class="flex gap-1">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="text-xs font-medium text-red-600 hover:text-red-900"
                                                    onclick="return confirm('{{ __('Remove this attachment?') }}')">{{ __('Remove') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">{{ __('No attachments yet.') }}</p>
                            @endforelse
                        </div>
                        <form method="POST" action="{{ route('tickets.attachments.store', $ticket) }}"
                            enctype="multipart/form-data" class="border-t border-gray-100 px-6 py-4 space-y-3">
                            @csrf
                            <label for="files"
                                class="text-sm font-medium text-gray-700">{{ __('Add attachment') }}</label>
                            <input id="files" name="files[]" type="file" multiple
                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif"
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            <p class="text-xs text-gray-400">
                                {{ __('Allowed:') }} {{ implode(', ', config('support.uploads.allowed_mimes')) }}
                                {{ __('up to') }} {{ config('support.uploads.max_size') }}MB
                                {{ __('per file,') }} {{ config('support.uploads.max_files') }}
                                {{ __('files at once') }}.
                            </p>
                            <button type="submit"
                                class="inline-flex items-center rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500">
                                {{ __('Upload') }}
                            </button>
                        </form>
                    </div>

                    @can('manageTickets')
                        <div class="bg-white shadow sm:rounded-lg">
                            <div class="border-b border-gray-100 px-6 py-4">
                                <h3 class="text-sm font-semibold text-gray-800">{{ __('Actions') }}</h3>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 p-6">
                                @if ($ticket->assigned_to === null && $ticket->status->canTransitionTo($statusEnum::InProgress))
                                    <form method="POST" action="{{ route('tickets.accept', $ticket) }}">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                                            {{ __('Accept Ticket') }}
                                        </button>
                                    </form>
                                @endif

                                @if ($user !== null && $user->isAdmin())
                                    <form method="POST" action="{{ route('tickets.assign', $ticket) }}"
                                        class="flex items-center gap-2">
                                        @csrf
                                        <select name="user_id"
                                            class="rounded-md border border-gray-300 bg-white py-2 pl-3 pr-8 text-sm font-medium text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            required>
                                            <option value="">{{ __('Select agent...') }}</option>
                                            @foreach ($availableAgents as $agent)
                                                <option value="{{ $agent->id }}">{{ $agent->name }}
                                                    ({{ $agent->active_tickets_count }}/{{ $agent->max_active_tickets }})</option>
                                            @endforeach
                                        </select>
                                        <button type="submit"
                                            class="inline-flex items-center rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-500">
                                            {{ __('Assign') }}
                                        </button>
                                    </form>
                                @endif

                                @if ($ticket->status->canTransitionTo($statusEnum::InProgress) && $ticket->status !== $statusEnum::Resolved)
                                    <form method="POST" action="{{ route('tickets.status', $ticket) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="in_progress">
                                        <button type="submit"
                                            class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                            {{ __('Start Progress') }}
                                        </button>
                                    </form>
                                @endif

                                @if ($ticket->status->canTransitionTo($statusEnum::WaitingForCustomer))
                                    <form method="POST" action="{{ route('tickets.status', $ticket) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="waiting_for_customer">
                                        <button type="submit"
                                            class="inline-flex items-center rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-500">
                                            {{ __('Waiting for Customer') }}
                                        </button>
                                    </form>
                                @endif

                                @if ($ticket->status->canTransitionTo($statusEnum::Resolved))
                                    <form method="POST" action="{{ route('tickets.resolve', $ticket) }}">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex items-center rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500">
                                            {{ __('Resolve') }}
                                        </button>
                                    </form>
                                @endif

                                @if ($ticket->status->canTransitionTo($statusEnum::Closed))
                                    <form method="POST" action="{{ route('tickets.close', $ticket) }}">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex items-center rounded-md bg-gray-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-500">
                                            {{ __('Close') }}
                                        </button>
                                    </form>
                                @endif

                                @if ($ticket->status === $statusEnum::Resolved || $ticket->status === $statusEnum::Closed)
                                    <form method="POST" action="{{ route('tickets.reopen', $ticket) }}">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex items-center rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500">
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
                                <dd class="font-medium text-gray-900">
                                    <x-timestamp :value="$ticket->created_at" class="font-medium text-gray-900" />
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4 px-6 py-3">
                                <dt class="text-gray-500">Due</dt>
                                <dd class="font-medium text-gray-900">
                                    <x-timestamp :value="$ticket->due_at" class="block font-medium text-gray-900" />
                                    <x-sla-badge :status="$ticket->slaStatus()" class="mt-1" />
                                </dd>
                            </div>
                        </dl>
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
                                            <span
                                                class="absolute -start-[6px] mt-1.5 h-3 w-3 rounded-full bg-indigo-500 ring-4 ring-white"></span>
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                                <span class="text-xs font-semibold uppercase tracking-wide text-indigo-600">
                                                    {{ ucwords(str_replace('_', ' ', $activity->action)) }}
                                                </span>
                                                <span class="text-xs text-gray-400">
                                                    <x-timestamp :value="$activity->created_at" format="M j, Y H:i:s" class="text-xs text-gray-400" />
                                                </span>
                                            </div>
                                            <p class="mt-1 text-sm text-gray-700">
                                                {{ $activity->meta['message'] ?? ucwords(str_replace('_', ' ', $activity->action)) }}
                                            </p>
                                            @if (!empty($activity->meta['note']))
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
        </div>
    </div>
</x-app-layout>
