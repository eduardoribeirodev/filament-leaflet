import { LeafletMapCore } from './leaflet-map-core';

document.addEventListener('livewire:init', () => {
    const leafletMapEntry = ($wire, config) => {
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
                console.log(state);

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
                if (this.pickMarker) {
                    Alpine.raw(this.pickMarker).removeFrom(Alpine.raw(this.mapCore.map));
                }

                const coords = this.getState();
                
                if (!coords) return;

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
                        this.callFieldMethod('handleMapClick', { latitude: lat, longitude: lng });
                    },

                    onLayerClick: (layerId) => {
                        this.callFieldMethod('handleLayerClick', { layerId: layerId });
                    },

                    onLayerUpdated: (layerId, data) => {
                        this.callFieldMethod('handleLayerUpdated', { layerId: layerId, data: data });
                    }
                };

                this.mapCore.setupEventHandlers(callbacks);
            },

            /**
             * Call a method on the Livewire component for this entry
             */
            callFieldMethod(name, parameters) {
                this.$wire.callSchemaComponentMethod(config.state.key, name, parameters);
            }
        }
    }

    window.leafletMapEntry = leafletMapEntry;
});