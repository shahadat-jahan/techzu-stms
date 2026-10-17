<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Support Dashboard') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Real-time ticket metrics, SLA performance, and assignment status.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span id="last-updated-text" class="text-xs text-gray-400">Cached (60s)</span>
                <button type="button" id="refresh-dashboard-btn" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <svg id="refresh-spinner" class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-6 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-5 pb-3">
                <a href="{{ route('tickets.index') }}" class="group block p-5 bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-md hover:border-indigo-200 transition">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Total Open</span>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span id="stat-total-open" class="text-3xl font-extrabold text-gray-900 group-hover:text-indigo-600 transition">{{ $stats['summary']['total_open'] }}</span>
                        <span class="text-xs font-medium text-indigo-600 group-hover:underline">View &rarr;</span>
                    </div>
                </a>

                <a href="{{ route('tickets.index', ['assigned_to' => 'unassigned']) }}" class="group block p-5 bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-md hover:border-amber-200 transition">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Unassigned Pool</span>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span id="stat-unassigned" class="text-3xl font-extrabold text-amber-600">{{ $stats['summary']['unassigned'] }}</span>
                        <span class="text-xs font-medium text-amber-600 group-hover:underline">Claim &rarr;</span>
                    </div>
                </a>

                <a href="{{ route('tickets.index', ['priority' => 'critical']) }}" class="group block p-5 bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-md hover:border-rose-200 transition">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Critical Priority</span>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span id="stat-critical" class="text-3xl font-extrabold text-rose-600">{{ $stats['summary']['critical'] }}</span>
                        <span class="text-xs font-medium text-rose-600 group-hover:underline">Filter &rarr;</span>
                    </div>
                </a>

                <a href="{{ route('tickets.index', ['sla_status' => 'breached']) }}" class="group block p-5 bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-md hover:border-red-200 transition">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">SLA Breached</span>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span id="stat-breached" class="text-3xl font-extrabold text-red-700">{{ $stats['summary']['breached'] }}</span>
                        <span class="text-xs font-medium text-red-700 group-hover:underline">Escalate &rarr;</span>
                    </div>
                </a>

                <a href="{{ route('tickets.index', ['sla_status' => 'approaching']) }}" class="group block p-5 bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-md hover:border-amber-200 transition">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Approaching SLA</span>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span id="stat-approaching" class="text-3xl font-extrabold text-amber-500">{{ $stats['summary']['approaching'] }}</span>
                        <span class="text-xs font-medium text-amber-500 group-hover:underline">Attention &rarr;</span>
                    </div>
                </a>

                <a href="{{ route('tickets.resolved') }}" class="group block p-5 bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-md hover:border-emerald-200 transition">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Resolved / Closed</span>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span id="stat-resolved" class="text-3xl font-extrabold text-emerald-600">{{ $stats['summary']['resolved'] }}</span>
                        <span class="text-xs font-medium text-emerald-600 group-hover:underline">History &rarr;</span>
                    </div>
                </a>
            </div>


            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 pt-2">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Tickets by Priority</h3>
                    <div class="space-y-4">
                        @php
                            $totalActive = max(1, $stats['summary']['total_open']);
                            $priorityColors = [
                                'low' => 'bg-gray-400',
                                'medium' => 'bg-blue-500',
                                'high' => 'bg-amber-500',
                                'critical' => 'bg-rose-600',
                            ];
                        @endphp
                        @foreach (\App\Enums\Priority::cases() as $priority)
                            @php
                                $count = $stats['by_priority'][$priority->value] ?? 0;
                                $pct = round(($count / $totalActive) * 100);
                            @endphp
                            <div>
                                <div class="flex items-center justify-between text-xs font-medium text-gray-700 mb-1">
                                    <a href="{{ route('tickets.index', ['priority' => $priority->value]) }}" class="hover:underline flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full {{ $priorityColors[$priority->value] ?? 'bg-gray-400' }}"></span>
                                        {{ $priority->label() }}
                                    </a>
                                    <span class="font-bold text-gray-900">{{ $count }} ({{ $pct }}%)</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2">
                                    <div class="{{ $priorityColors[$priority->value] ?? 'bg-gray-400' }} h-2 rounded-full" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if (! empty($stats['by_agent']))
                    <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-semibold text-gray-900">Active Workload by Agent</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-xs">
                                <thead>
                                    <tr class="text-left font-semibold text-gray-500 uppercase tracking-wider">
                                        <th class="py-2.5">Agent</th>
                                        <th class="py-2.5">Email</th>
                                        <th class="py-2.5 text-center">Active Tickets</th>
                                        <th class="py-2.5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50" id="agent-workload-tbody">
                                    @foreach ($stats['by_agent'] as $agent)
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 font-medium text-gray-900">{{ $agent['name'] }}</td>
                                            <td class="py-3 text-gray-500">{{ $agent['email'] }}</td>
                                            <td class="py-3 text-center">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold {{ $agent['active_tickets_count'] > 0 ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-100 text-gray-500' }}">
                                                    {{ $agent['active_tickets_count'] }}
                                                </span>
                                            </td>
                                            <td class="py-3 text-right">
                                                <a href="{{ route('tickets.index', ['assigned_to' => $agent['id']]) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                                    View tickets &rarr;
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-center text-center">
                        <div>
                            <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h4 class="mt-2 text-sm font-semibold text-gray-800">Support Workspace</h4>
                            <p class="mt-1 text-xs text-gray-500 max-w-sm">Use the cards above to inspect and track your submitted tickets across their resolution lifecycle.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const refreshBtn = document.getElementById('refresh-dashboard-btn');
            const spinner = document.getElementById('refresh-spinner');
            const updatedText = document.getElementById('last-updated-text');

            async function fetchStats() {
                if (spinner) spinner.classList.add('animate-spin');
                try {
                    const res = await fetch("{{ route('dashboard') }}", {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (!res.ok) return;
                    const data = await res.json();

                    if (data.summary) {
                        const s = data.summary;
                        document.getElementById('stat-total-open').textContent = s.total_open;
                        document.getElementById('stat-unassigned').textContent = s.unassigned;
                        document.getElementById('stat-critical').textContent = s.critical;
                        document.getElementById('stat-breached').textContent = s.breached;
                        document.getElementById('stat-approaching').textContent = s.approaching;
                        document.getElementById('stat-resolved').textContent = s.resolved;
                    }
                    if (updatedText) {
                        const now = new Date();
                        updatedText.textContent = 'Updated ' + now.toLocaleTimeString();
                    }
                } catch (e) {
                    console.error('Failed to poll dashboard stats', e);
                } finally {
                    if (spinner) spinner.classList.remove('animate-spin');
                }
            }

            if (refreshBtn) {
                refreshBtn.addEventListener('click', fetchStats);
            }

            setInterval(fetchStats, 60000);
        });
    </script>
</x-app-layout>

