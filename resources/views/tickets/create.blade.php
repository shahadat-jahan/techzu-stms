<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New Ticket') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('tickets.store') }}" class="space-y-6 p-6">
                    @csrf

                    <div>
                        <x-input-label for="subject" value="Subject" />
                        <x-text-input id="subject" name="subject" type="text" class="mt-1 block w-full" :value="old('subject')" required autofocus />
                        <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Description" />
                        <textarea id="description" name="description" rows="6" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="priority" value="Priority" />
                            <select id="priority" name="priority" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                @foreach (\App\Enums\Priority::cases() as $priority)
                                    <option value="{{ $priority->value }}" @selected(old('priority', 'medium') === $priority->value)>
                                        {{ $priority->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="category_id" value="Category" />
                            <select id="category_id" name="category_id" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                <option value="" disabled @selected(old('category_id') === null)>Select a category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                        </div>
                    </div>

                    @can('manageTickets')
                        <div>
                            <x-input-label for="external_ref" value="External Reference (optional)" />
                            <x-text-input id="external_ref" name="external_ref" type="text" class="mt-1 block w-full" :value="old('external_ref')" placeholder="e.g. CRM-12345" />
                            <p class="mt-1 text-xs text-gray-500">Used for duplicate detection on CSV imports.</p>
                            <x-input-error :messages="$errors->get('external_ref')" class="mt-2" />
                        </div>
                    @endcan

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Create Ticket') }}</x-primary-button>
                        <a href="{{ route('tickets.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                            {{ __('Cancel') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
