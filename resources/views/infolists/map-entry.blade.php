@php
    $config = $getMapData();
    $state = $getState();
    $placeholder = $getPlaceholder();
@endphp

<x-dynamic-component
    :component="$getEntryWrapperView()"
    :entry="$entry"
>
    @if (!$state && $placeholder)
        <p class="fi-in-placeholder">
            {{ $placeholder }}
        </p>
    @else
        <x-filament-leaflet::map
            :config="$config"
            entry
        />
    @endif

</x-dynamic-component>