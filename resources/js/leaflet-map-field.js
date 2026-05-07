import { LeafletMapCore } from './leaflet-map-core';

document.addEventListener('livewire:init', () => {
    const leafletMapField = ($wire, config) => {
        return {
            mapCore: null,
            config,
            $wire,
            state: undefined,
            pickMarker: null,

            init() {
                this.mapCore = new LeafletMapCore(this.config);
                this.mapCore.init();
                this.setupEventHandlers();
                this.updatePickMarker();
                this.watchState();
            },

            /**
             * Get the current state from the field
             */
            getState() {
                if (!this.config.state) return undefined;
                return this.$wire.get(this.config.state.statePath);
            },

            /**
             * Set the state of the field
             */
            setState(lat, lng) {
                if (!this.config.state) return;
                
                this.$wire.set(this.config.state.statePath, {lat, lng});
                this.updatePickMarker();
            },

            /**
             * Watch for changes in the field state
             */
            watchState() {
                if (!this.config.state) return;

                this.$wire.watch(this.config.state.statePath, () => {
                    this.updatePickMarker();
                });
            },

            /**
             * Update the pick marker position
             */
            updatePickMarker() {
                if (this.pickMarker) {
                    Alpine.raw(this.pickMarker).removeFrom(Alpine.raw(this.mapCore.map));
                }

                const coords = this.getState();

                if (!coords) return;

                let markerOptions = this.config.state.pickMarker;
                markerOptions.coords = [coords.lat, coords.lng];

                this.pickMarker = this.mapCore.createMarker(markerOptions);

                Alpine.raw(this.pickMarker).addTo(Alpine.raw(this.mapCore.map));
            },

            /**
             * Setup field-specific event handlers
             */
            setupEventHandlers() {
                const callbacks = {
                    onMapClick: (lat, lng) => {
                        if (!this.config.state.disabled) {
                            this.setState(lat, lng);
                        }

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
             * Call a method on the Livewire component for this field
             */
            callFieldMethod(name, parameters) {
                this.$wire.callSchemaComponentMethod(config.state.key, name, parameters);
            }
        }
    }

    window.leafletMapField = leafletMapField;
});