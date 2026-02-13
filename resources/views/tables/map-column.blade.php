@php
    $config = $getMapData();
@endphp

<div 
    style="width: {{ $getWidth() }}; padding: 5px;"
    wire:key="{{ $config['mapId'] }}"
>
    <x-filament-leaflet::map
        :config="$config"
        column
    />

</div>