<x-filament::section>
    <x-slot name="heading">
        <p class="font-semibold">{{ __('settings::settings.page_created') }}</p>
    </x-slot>
    <x-slot name="description">
        <p>{!! __('settings::settings.page_description', ['group' => $group]) !!}</p>
    </x-slot>
    <h2 class="fi-header-subheading">{{ __('settings::settings.next_steps') }}</h2>
    <ul>
        <li>1. {!! __('settings::settings.step_add_fields', [
            'file' => "<code>app/Filament/Pages/{$pageClass}.php</code>",
            'example' => "<code>\\Filament\\Forms\\Components\\TextInput::make('site_name')</code>",
        ]) !!}
        </li>
        <li>
            2. {!! __('settings::settings.step_retrieve_values', ['group' => $group]) !!}
        </li>
    </ul>
</x-filament::section>