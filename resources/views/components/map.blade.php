@props([
    'config',
    'widget',
    'entry',
    'field',
    'column',
])

@php
    use Illuminate\Support\Js;
    $mapClass = match(true) {
        isset($field) => 'leafletMapField',
        isset($entry) => 'leafletMapEntry',
        isset($widget) => 'leafletMapWidget',
        isset($column) => 'leafletMapColumn',
    };
@endphp

<div
    wire:ignore
    x-data="{{ $mapClass }}(
        $wire, 
        {{ Js::from($config) }},
    )"
    style="height: {{ $config['mapHeight'] }}px; width: 100%"
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