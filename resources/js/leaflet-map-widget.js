import { LeafletMapCore } from './leaflet-map-core';

/**
 * Leaflet Map Widget Component
 * Handles map functionality for Filament widgets (dashboard/page components)
 */
const leafletMapWidget = ($wire, config) => {
    return {
        mapCore: null,
        config,
        $wire,

        init() {
            this.mapCore = new LeafletMapCore(this.config);
            this.mapCore.init();
            this.setupEventHandlers();
            this.setupLivewireListeners();
        },

        /**
         * Setup widget-specific event handlers
         */
        setupEventHandlers() {
            const callbacks = {
                onMapClick: (lat, lng) => {
                    this.$wire.call('handleMapClick', lat, lng);
                },

                onLayerClick: (layerId) => {
                    this.$wire.call('handleLayerClick', layerId);
                },

                onLayerUpdated: (layerId, data) => {
                    this.$wire.call('handleLayerUpdated', layerId, data);
                }
            };

            this.mapCore.setupEventHandlers(callbacks);
        },

        /**
         * Setup Livewire-specific listeners
         */
        setupLivewireListeners() {
            window.addEventListener(`update-leaflet-${this.config.mapId}`, (event) => {
                this.updateMapData(event.detail.config);
            });
        },

        /**
         * Update map data when config changes
         */
        updateMapData(newConfig) {
            this.config = newConfig;
            this.mapCore.updateMapData(newConfig);
        }
    }
}

window.leafletMapWidget = leafletMapWidget;
