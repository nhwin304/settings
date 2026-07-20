<x-filament-panels::page>
    @foreach ($this->getSettingsPages() as $definition)
        <section>
            <a href="{{ $definition->getUrl() }}">
                <strong>{{ $definition->getLabel() }}</strong>
                @if ($definition->getDescription())
                    <p>{{ $definition->getDescription() }}</p>
                @endif
                @if ($definition->getBadge())
                    <span>{{ $definition->getBadge() }}</span>
                @endif
            </a>
        </section>
    @endforeach
</x-filament-panels::page>
