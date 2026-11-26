@props([
    'config',
    'widget',
    'entry',
    'field',
    'column',
])

@php
    use Filament\Support\Facades\FilamentAsset;
    use Illuminate\Support\Js;
    $mapClass = match(true) {
        isset($field) => 'leafletMapField',
        isset($entry) => 'leafletMapEntry',
        isset($widget) => 'leafletMapWidget',
        isset($column) => 'leafletMapColumn',
    };
@endphp

{{-- Added once per page, before the map is initialised (see `FilamentLeafletServiceProvider::getAssets()`) --}}
@assets
    <script src="{{ FilamentAsset::getScriptSrc('leaflet-map', 'eduardoribeirodev/filament-leaflet') }}"></script>
@endassets

<div
    wire:ignore
    x-data="{{ $mapClass }}(
        $wire,
        {{ Js::from($config) }},
    )"
    {{
        $attributes->style([
            "height: {$config['mapHeight']}px",
            "width: 100%",
            "overflow: hidden"
        ])
    }}
>
    <div id="{{ $config['mapId'] }}"></div>

    @push('styles')
        <style>
            {!! $config['customStyles'] !!}
        </style>
    @endpush


    @push('scripts')
        <script>
            {!! $config['customScripts'] !!}
        </script>
    @endpush
    
</div>