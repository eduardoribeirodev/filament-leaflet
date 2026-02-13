@php
    $config = $getMapData();
@endphp

<x-dynamic-component
    :component="$getEntryWrapperView()"
    :entry="$entry"
>
    <x-filament-leaflet::map
        :config="$config"
        entry
    />

</x-dynamic-component>