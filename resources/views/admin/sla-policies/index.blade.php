<x-app-layout>
    <x-slot name="header">
       <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('SLA Policies') }}
                </h2>
                <p class="text-sm font-medium text-gray-500">Resolution and warning windows per priority</p>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Priority</th>
                                <th class="px-4 py-3">Resolution (minutes)</th>
                                <th class="px-4 py-3">Warning (minutes)</th>
                                <th class="px-4 py-3">Active</th>
                                <th class="px-4 py-3 text-right">Save</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($policies as $policy)
                                <tr class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <x-priority-badge :priority="$policy->priority" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-text-input name="resolution_minutes" type="number" min="1" class="w-32"
                                            :form="'policy-'.$policy->id"
                                            :value="old('resolution_minutes', $policy->resolution_minutes)" required />
                                        <x-input-error :messages="$errors->get('resolution_minutes')" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-text-input name="warning_minutes" type="number" min="1" class="w-32"
                                            :form="'policy-'.$policy->id"
                                            :value="old('warning_minutes', $policy->warning_minutes)" required />
                                        <x-input-error :messages="$errors->get('warning_minutes')" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <label class="inline-flex items-center gap-2 text-gray-700">
                                            <input type="hidden" name="is_active" value="0" form="policy-{{ $policy->id }}">
                                            <input type="checkbox" name="is_active" value="1" form="policy-{{ $policy->id }}"
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                @checked(old('is_active', $policy->is_active))>
                                            <span class="text-xs">Enabled</span>
                                        </label>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <x-primary-button :form="'policy-'.$policy->id">{{ __('Save') }}</x-primary-button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @foreach ($policies as $policy)
                <form id="policy-{{ $policy->id }}" method="POST" action="{{ route('admin.sla-policies.update', $policy) }}" class="hidden">
                    @csrf
                    @method('PATCH')
                </form>
            @endforeach

            <p class="text-xs text-gray-500">
                Existing tickets keep the deadlines they were created with. Updated policies apply to tickets created afterwards.
            </p>
        </div>
    </div>
</x-app-layout>
