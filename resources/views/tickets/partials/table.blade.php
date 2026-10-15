@if ($tickets->isEmpty())
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-sm text-gray-500">{{ $emptyMessage ?? 'No tickets found.' }}</div>
    </div>
@else
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Number</th>
                        <th class="px-4 py-3">Subject</th>
                        <th class="px-4 py-3">Priority</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Due</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Assignee</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($tickets as $ticket)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">
                                <a href="{{ route('tickets.show', $ticket) }}" class="text-indigo-600 hover:text-indigo-900">
                                    {{ $ticket->ticket_number }}
                                </a>
                            </td>
                            <td class="max-w-xs truncate px-4 py-3 text-gray-900">{{ $ticket->subject }}</td>
                            <td class="whitespace-nowrap px-4 py-3"><x-priority-badge :priority="$ticket->priority" /></td>
                            <td class="whitespace-nowrap px-4 py-3"><x-status-badge :status="$ticket->status" /></td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <x-timestamp :value="$ticket->due_at" class="block" />
                                <x-sla-badge :status="$ticket->slaStatus()" class="mt-1" />
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $ticket->category?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $ticket->agent?->name ?? 'Unassigned' }}</td>
                            <td class="whitespace-nowrap px-4 py-3"><x-timestamp :value="$ticket->created_at" /></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-indigo-600 hover:text-indigo-900">
                                    View
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $tickets->links() }}
    </div>
@endif
