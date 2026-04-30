import { LeafletMapCore } from './leaflet-map-core';

document.addEventListener('livewire:init', () => {
    const leafletMapColumn = ($wire, config) => {
        return {
            mapCore: null,
            config,
            $wire,
            state: undefined,
            pickMarker: null,

            init() {
                this.mapCore = new LeafletMapCore(this.config);
                this.state = this.getState();
                this.mapCore.init();
                this.setupEventHandlers();
                this.setupPickMarker();
            },

            /**
             * Get the current state from the field
             */
            getState() {
                const state = this.config.state.state;

                if (!state) return undefined;

                return {
                    lat: state[this.config.state.latitudeFieldName],
                    lng: state[this.config.state.longitudeFieldName]
                }
            },

            /**
             * Update the pick marker position
             */
            setupPickMarker() {
                const coords = this.getState();

                if (this.pickMarker) {
                    Alpine.raw(this.pickMarker).removeFrom(Alpine.raw(this.mapCore.map));
                }

                let markerOptions = this.config.state.pickMarker;
                markerOptions.coords = Object.values(coords);

                this.pickMarker = this.mapCore.createMarker(markerOptions);

                Alpine.raw(this.pickMarker).addTo(Alpine.raw(this.mapCore.map));
            },

            /**
             * Setup field-specific event handlers
             */
            setupEventHandlers() {
                const callbacks = {
                    onMapClick: (lat, lng) => {
                        this.callColumnMethod('handleMapClick', { latitude: lat, longitude: lng });
                    },

                    onLayerClick: (layerId) => {
                        this.callColumnMethod('handleLayerClick', { layerId: layerId });
                    },

                    onLayerUpdated: (layerId, data) => {
                        this.callColumnMethod('handleLayerUpdated', { layerId: layerId, data: data });
                    }
                };

                this.mapCore.setupEventHandlers(callbacks);
            },

            /**
             * Call a method on the Livewire component for this column
             */
            callColumnMethod(name, parameters) {
                this.$wire.callTableColumnMethod(config.state.name, config.state.recordKey, name, parameters);
            }
        }
    }

    window.leafletMapColumn = leafletMapColumn;
});