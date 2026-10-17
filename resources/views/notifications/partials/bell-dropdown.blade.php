<div class="relative">
    <button
        data-notification-bell
        class="relative p-2 text-gray-600 hover:text-gray-900 focus:outline-none focus:text-gray-900"
    >
        <svg class="h-6 w-6 text-gray-600 hover:text-gray-900" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        @if (auth()->user()->unreadNotifications()->count() > 0)
            <span class="absolute top-0 right-0 inline-flex items-center justify-center w-5 h-5 text-[10px] font-bold leading-none text-white bg-red-600 rounded-full">
                {{ auth()->user()->unreadNotifications()->count() }}
            </span>
        @endif
    </button>

    <div
        data-notification-dropdown
        class="hidden absolute right-0 mt-2 w-80 bg-white rounded-md shadow-lg z-50"
    >
        <div class="py-2">
            <div class="px-4 py-2 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
            </div>

            @if (auth()->user()->notifications()->count() > 0)
                <div class="max-h-64 overflow-y-auto">
                    @foreach (auth()->user()->notifications()->latest()->take(5) as $notification)
                        <div class="block px-4 py-3 hover:bg-gray-50 {{ $notification->read_at ? 'bg-white' : 'bg-blue-50' }}" data-notification-id="{{ $notification->id }}">
                            <div class="flex items-start justify-between">
                                <a href="{{ route('notifications.index') }}" class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-900 {{ $notification->read_at ? '' : 'font-semibold' }}">
                                        {{ $notification->data['subject'] ?? 'Notification' }}
                                    </h4>
                                    <p class="text-xs text-gray-600 mt-1">
                                        {{ $notification->created_at->diffForHumans() }}
                                    </p>
                                </a>
                                @unless ($notification->read_at)
                                    <form method="POST" action="{{ route('notifications.markAsRead', $notification) }}" data-mark-as-read-form data-notification-id="{{ $notification->id }}">
                                        @csrf
                                        <button type="submit" data-mark-as-read class="text-xs text-blue-600 hover:text-blue-800">
                                            Mark as read
                                        </button>
                                    </form>
                                @endunless
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="px-4 py-2 border-t border-gray-200">
                    <a href="{{ route('notifications.index') }}" class="text-sm text-blue-600 hover:text-blue-800">
                        View all notifications
                    </a>
                </div>
            @else
                <div class="px-4 py-3 text-sm text-gray-500">
                    No notifications
                </div>
            @endif
        </div>
    </div>
</div>
