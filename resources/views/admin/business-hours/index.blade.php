<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Business Hours') }}
            </h2>
            <p class="text-sm font-medium text-gray-500">Working window used for SLA calculations</p>
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
                                <th class="px-4 py-3">Day</th>
                                <th class="px-4 py-3">Open</th>
                                <th class="px-4 py-3">Opens at</th>
                                <th class="px-4 py-3">Closes at</th>
                                <th class="px-4 py-3 text-right">Save</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($businessHours as $businessHour)
                                <tr class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">
                                        {{ $businessHour->day_name }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <label class="inline-flex items-center gap-2 text-gray-700">
                                            <input type="hidden" name="is_open" value="0" form="hours-{{ $businessHour->id }}">
                                            <input type="checkbox" name="is_open" value="1" form="hours-{{ $businessHour->id }}"
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                @checked(old('is_open', $businessHour->is_open))>
                                            <span class="text-xs">Working day</span>
                                        </label>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-text-input name="open_time" type="time" class="w-35" :form="'hours-'.$businessHour->id"
                                            :value="old('open_time', $businessHour->open_time_input)" />
                                        <x-input-error :messages="$errors->get('open_time')" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-text-input name="close_time" type="time" class="w-35" :form="'hours-'.$businessHour->id"
                                            :value="old('close_time', $businessHour->close_time_input)" />
                                        <x-input-error :messages="$errors->get('close_time')" class="mt-1" />
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <x-primary-button :form="'hours-'.$businessHour->id">{{ __('Save') }}</x-primary-button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @foreach ($businessHours as $businessHour)
                <form id="hours-{{ $businessHour->id }}" method="POST" action="{{ route('admin.business-hours.update', $businessHour) }}" class="hidden">
                    @csrf
                    @method('PATCH')
                </form>
            @endforeach

            <p class="text-xs text-gray-500">
                Day numbers follow Carbon: 0 is Sunday through 6 is Saturday. Existing tickets keep the deadlines they were created with.
            </p>
        </div>
    </div>
</x-app-layout>
