@php
    $config = $getMapData();
    $state = $getState();
    $placeholder = $getPlaceholder();
@endphp

<div 
    style="padding: 5px;"
    wire:key="{{ $config['mapId'] }}"
>
    @if (!$state && $placeholder)
        <p class="fi-ta-placeholder">
            {{ $placeholder }}
        </p>
    @else
        <x-filament-leaflet::map
            :config="$config"
            column
            @style([
                'border-radius: 50%' => $getIsCircular(),
                'width: ' . $getWidth() => !$getIsCircular(),
                'width: max(' . $getWidth() . ',' . $config['mapHeight'] . 'px)' => $getIsCircular(),
                'height: max(' . $getWidth() . ',' . $config['mapHeight'] . 'px)' => $getIsCircular(),
            ])
        />
    @endif

</div>