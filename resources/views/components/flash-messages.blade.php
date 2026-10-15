@php
    $statusLabels = [
        'profile-updated' => 'Profile updated successfully.',
        'password-updated' => 'Password updated successfully.',
        'verification-link-sent' => 'A new verification link has been sent to your email address.',
    ];

    $messages = collect([
        ['type' => 'success', 'message' => session('success')],
        ['type' => 'error', 'message' => session('error')],
        ['type' => 'warning', 'message' => session('warning')],
        ['type' => 'info', 'message' => session('info')],
    ])->filter(fn (array $flash): bool => filled($flash['message']));

    $status = session('status');

    if (filled($status)) {
        $messages->push([
            'type' => 'success',
            'message' => $statusLabels[$status] ?? $status,
        ]);
    }
@endphp

@if ($messages->isNotEmpty())
    <div id="flash-messages" class="fixed top-4 right-4 z-50 space-y-2">
        @foreach ($messages as $flash)
            <div class="flash-message flex items-start gap-3 p-4 rounded-lg shadow-lg border max-w-sm
                @switch($flash['type'])
                    @case('success') bg-green-50 border-green-400 text-green-800 @break
                    @case('error') bg-red-50 border-red-400 text-red-800 @break
                    @case('warning') bg-yellow-50 border-yellow-400 text-yellow-800 @break
                    @default bg-blue-50 border-blue-400 text-blue-800 @break
                @endswitch
            " role="alert" data-type="{{ $flash['type'] }}">
                <p class="text-sm font-medium flex-1">{{ $flash['message'] }}</p>
                <button type="button" class="flash-dismiss shrink-0 text-current opacity-60 hover:opacity-100 transition-opacity" aria-label="Dismiss">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
        @endforeach
    </div>

    <script>
        (function () {
            const container = document.getElementById('flash-messages');
            if (! container) return;

            const dismiss = (element) => {
                element.style.transition = 'opacity 200ms ease-in-out, transform 200ms ease-in-out';
                element.style.opacity = '0';
                element.style.transform = 'translateY(-0.5rem)';
                setTimeout(() => element.remove(), 200);
            };

            container.querySelectorAll('.flash-dismiss').forEach((button) => {
                button.addEventListener('click', () => dismiss(button.closest('.flash-message')));
            });

            container.querySelectorAll('.flash-message').forEach((message) => {
                setTimeout(() => dismiss(message), 5000);
            });
        })();
    </script>
@endif
