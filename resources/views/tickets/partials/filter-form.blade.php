@php
    $activeFiltersCount = collect([
        request('keyword'),
        request('number'),
        request('status'),
        request('priority'),
        request('category'),
        request('assigned_to'),
        request('customer_id'),
        request('created_from'),
        request('created_to'),
        request('sla_status'),
    ])->filter(fn ($val) => ! is_null($val) && $val !== '')->count();

    $isResolvedPage = request()->routeIs('tickets.resolved');
    $currentRoute = $isResolvedPage ? route('tickets.resolved') : route('tickets.index');
@endphp

<div class="mb-6 bg-white shadow-sm sm:rounded-lg overflow-hidden border border-gray-100" id="filter-container">
    <div class="px-6 py-4 flex items-center justify-between bg-gray-50 border-b border-gray-100">
        <div class="flex items-center gap-2">
            <span class="text-sm font-semibold text-gray-700">Filters &amp; Search</span>
            @if ($activeFiltersCount > 0)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                    {{ $activeFiltersCount }} active
                </span>
            @endif
        </div>
        <div class="flex items-center gap-3">
            @if ($activeFiltersCount > 0)
                <a href="{{ $currentRoute }}" class="text-xs font-medium text-gray-500 hover:text-red-600 transition">
                    Reset Filters
                </a>
            @endif
            <button type="button" id="toggle-filter-btn" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 focus:outline-none">
                <span id="filter-btn-text">{{ $activeFiltersCount > 0 ? 'Hide Filters' : 'Show Filters' }}</span>
                <svg id="filter-btn-icon" class="w-4 h-4 transition-transform duration-200 {{ $activeFiltersCount > 0 ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        </div>
    </div>

    <div id="filter-body" class="{{ $activeFiltersCount > 0 ? 'block' : 'hidden' }} p-6 border-t border-gray-100">
        <form method="GET" action="{{ $currentRoute }}">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div>
                    <label for="keyword" class="block text-xs font-medium text-gray-700">Keyword</label>
                    <input type="text" name="keyword" id="keyword" value="{{ request('keyword') }}" placeholder="Search subject or description..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="number" class="block text-xs font-medium text-gray-700">Ticket Number</label>
                    <input type="text" name="number" id="number" value="{{ request('number') }}" placeholder="e.g. 000001" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="priority" class="block text-xs font-medium text-gray-700">Priority</label>
                    <select name="priority" id="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Priorities</option>
                        @foreach (\App\Enums\Priority::cases() as $priority)
                            <option value="{{ $priority->value }}" @selected(request('priority') === $priority->value)>
                                {{ $priority->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-xs font-medium text-gray-700">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Statuses</option>
                        @php
                            $availableStatuses = $isResolvedPage
                                ? [\App\Enums\TicketStatus::Resolved, \App\Enums\TicketStatus::Closed]
                                : [\App\Enums\TicketStatus::Open, \App\Enums\TicketStatus::InProgress, \App\Enums\TicketStatus::WaitingForCustomer];
                        @endphp
                        @foreach ($availableStatuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="category" class="block text-xs font-medium text-gray-700">Category</label>
                    <select name="category" id="category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Categories</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if (! auth()->user()->isCustomer())
                    <div>
                        <label for="assigned_to" class="block text-xs font-medium text-gray-700">Assignee</label>
                        <select name="assigned_to" id="assigned_to" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Assignees</option>
                            <option value="unassigned" @selected(request('assigned_to') === 'unassigned')>Unassigned Only</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected(request('assigned_to') == $agent->id)>
                                    {{ $agent->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if (auth()->user()->isAdmin())
                    <div>
                        <label for="customer_id" class="block text-xs font-medium text-gray-700">Customer</label>
                        <select name="customer_id" id="customer_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Customers</option>
                            @foreach ($customers as $cust)
                                <option value="{{ $cust->id }}" @selected(request('customer_id') == $cust->id)>
                                    {{ $cust->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif


                <div>
                    <label for="sla_status" class="block text-xs font-medium text-gray-700">SLA Status</label>
                    <select name="sla_status" id="sla_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All SLA States</option>
                        <option value="within" @selected(request('sla_status') === 'within')>Within SLA</option>
                        <option value="approaching" @selected(request('sla_status') === 'approaching')>Approaching Breach</option>
                        <option value="breached" @selected(request('sla_status') === 'breached')>Breached</option>
                    </select>
                </div>

                <div>
                    <label for="created_from" class="block text-xs font-medium text-gray-700">Created From</label>
                    <input type="date" name="created_from" id="created_from" value="{{ request('created_from') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="created_to" class="block text-xs font-medium text-gray-700">Created To</label>
                    <input type="date" name="created_to" id="created_to" value="{{ request('created_to') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="sort_by" class="block text-xs font-medium text-gray-700">Sort By</label>
                    <select name="sort_by" id="sort_by" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="created_at" @selected(request('sort_by', 'created_at') === 'created_at')>Created Date</option>
                        <option value="due_at" @selected(request('sort_by') === 'due_at')>Due Date</option>
                        <option value="priority" @selected(request('sort_by') === 'priority')>Priority</option>
                    </select>
                </div>

                <div>
                    <label for="sort_dir" class="block text-xs font-medium text-gray-700">Direction</label>
                    <select name="sort_dir" id="sort_dir" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="desc" @selected(request('sort_dir', 'desc') === 'desc')>Descending</option>
                        <option value="asc" @selected(request('sort_dir') === 'asc')>Ascending</option>
                    </select>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ $currentRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Clear
                </a>
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('toggle-filter-btn');
        const filterBody = document.getElementById('filter-body');
        const btnText = document.getElementById('filter-btn-text');
        const btnIcon = document.getElementById('filter-btn-icon');

        if (toggleBtn && filterBody) {
            toggleBtn.addEventListener('click', function () {
                const isHidden = filterBody.classList.contains('hidden');
                if (isHidden) {
                    filterBody.classList.remove('hidden');
                    btnText.textContent = 'Hide Filters';
                    btnIcon.classList.add('rotate-180');
                } else {
                    filterBody.classList.add('hidden');
                    btnText.textContent = 'Show Filters';
                    btnIcon.classList.remove('rotate-180');
                }
            });
        }
    });
</script>

