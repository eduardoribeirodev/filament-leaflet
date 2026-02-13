@php
    $config = $getMapData();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <x-filament-leaflet::map
        :config="$config"
        field
    />

</x-dynamic-component>