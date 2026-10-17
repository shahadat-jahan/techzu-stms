<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h1 class="text-2xl font-bold mb-6">Notifications</h1>

                    @if ($notifications->isEmpty())
                        <p class="text-gray-500">No notifications.</p>
                    @else
                        <div class="space-y-4">
                            @foreach ($notifications as $notification)
                                <div class="flex items-center justify-between p-4 border rounded-lg {{ $notification->read_at ? 'bg-gray-50' : 'bg-blue-50 border-blue-200' }}" data-notification-id="{{ $notification->id }}">
                                    <div class="flex-1">
                                        <h3 class="font-semibold {{ $notification->read_at ? 'text-gray-700' : 'text-blue-900' }}">
                                            {{ $notification->data['subject'] ?? 'Notification' }}
                                        </h3>
                                        <p class="text-sm text-gray-600 mt-1">
                                            {{ $notification->created_at->diffForHumans() }}
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Ticket: {{ $notification->data['ticket_number'] ?? 'N/A' }}
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        @unless ($notification->read_at)
                                            <form method="POST" action="{{ route('notifications.markAsRead', $notification) }}" data-mark-as-read-form data-notification-id="{{ $notification->id }}">
                                                @csrf
                                                <button type="submit" data-mark-as-read class="text-sm text-blue-600 hover:text-blue-800">
                                                    Mark as read
                                                </button>
                                            </form>
                                        @endunless
                                        <a href="{{ $notification->data['action_url'] ?? '#' }}" data-view-ticket class="text-sm text-blue-600 hover:text-blue-800">
                                            View Ticket
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            {{ $notifications->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>