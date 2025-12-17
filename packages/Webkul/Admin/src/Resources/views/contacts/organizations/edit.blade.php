<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.contacts.organizations.edit.title')
        </x-slot>

        {!! view_render_event('admin.organizations.edit.form.before') !!}

        <x-admin::form :action="route('admin.contacts.organizations.update', $organization->id)" method="PUT">
            <div class="flex flex-col gap-4">
                <div
                    class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <div class="flex flex-col gap-2">
                        {!! view_render_event('admin.organizations.edit.breadcrumbs.before', ['organization' => $organization]) !!}

                        <x-admin::breadcrumbs name="contacts.organizations.edit" :entity="$organization" />

                        {!! view_render_event('admin.organizations.edit.breadcrumbs.before', ['organization' => $organization]) !!}

                        <div class="text-xl font-bold dark:text-gray-300">
                            @lang('admin::app.contacts.organizations.edit.title')
                        </div>
                    </div>

                    <div class="flex items-center gap-x-2.5">
                        <div class="flex items-center gap-x-2.5">
                            {!! view_render_event('admin.organizations.edit.save_button.before', ['organization' => $organization]) !!}

                            <!-- Save button for person -->
                            <button type="submit" class="primary-button">
                                @lang('admin::app.contacts.organizations.edit.save-btn')
                            </button>

                            {!! view_render_event('admin.organizations.edit.save_button.after', ['organization' => $organization]) !!}
                        </div>
                    </div>
                </div>

                <div
                    class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    {!! view_render_event('admin.contacts.organizations.edit.form_controls.before') !!}

                    <x-admin::attributes :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                        'entity_type' => 'organizations',
                    ])" :custom-validations="[
                        'name' => [
                            'max:100',
                        ],
                        'address' => [
                            'max:100',
                        ],
                        'postcode' => [
                            'postcode',
                        ],
                    ]" :entity="$organization" />

                    {!! view_render_event('admin.contacts.organizations.edit.form_controls.after') !!}
                </div>

                {{-- Associated Persons Section --}}
                <div
                    class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-base font-semibold dark:text-white">
                            Personas Asociadas ({{ $persons->count() }})
                        </h3>
                    </div>

                    @if($persons->count() > 0)
                        <div class="grid gap-2">
                            @foreach($persons as $person)
                                <a href="{{ route('admin.contacts.persons.view', $person->id) }}"
                                    class="flex items-center gap-3 rounded-md border border-gray-100 bg-gray-50 p-3 transition-all hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700">
                                    <x-admin::avatar :name="$person->name" />

                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium dark:text-white">
                                            {{ $person->name }}
                                        </span>

                                        @if($person->emails && count($person->emails) > 0)
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $person->emails[0]['value'] ?? '' }}
                                            </span>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            No hay personas asociadas a esta organización.
                        </p>
                    @endif
                </div>
            </div>
        </x-admin::form>

        {!! view_render_event('admin.organizations.edit.form.after') !!}
</x-admin::layouts>