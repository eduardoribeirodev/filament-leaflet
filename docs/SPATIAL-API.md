# Spatial API Documentation

This document describes the REST API endpoints for spatial data operations.

## Base URL

```
/api/spatial
```

## Authentication

API endpoints use the application's standard authentication middleware. Configure as needed in `routes/api.php`.

---

## Endpoints

### List Available Models

Returns the list of models that can be queried through the spatial API.

```http
GET /api/spatial/models
```

**Response:**
```json
{
  "models": ["infrastructures", "subdivisions"]
}
```

---

### Load Features (with Clustering)

Load spatial features within a viewport with optional server-side clustering.

```http
GET /api/spatial/{model}/features
```

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `bounds` | object | Yes | Viewport bounds |
| `bounds.minLat` | float | Yes | Minimum latitude (-90 to 90) |
| `bounds.maxLat` | float | Yes | Maximum latitude (-90 to 90) |
| `bounds.minLng` | float | Yes | Minimum longitude (-180 to 180) |
| `bounds.maxLng` | float | Yes | Maximum longitude (-180 to 180) |
| `zoom` | integer | No | Map zoom level (0-22), default: 10 |
| `cluster` | boolean | No | Enable clustering, default: true |
| `limit` | integer | No | Max results (1-10000), default: 1000 |

**Example Request:**
```javascript
fetch('/api/spatial/infrastructures/features?' + new URLSearchParams({
    'bounds[minLat]': -24,
    'bounds[maxLat]': -23,
    'bounds[minLng]': -47,
    'bounds[maxLng]': -46,
    'zoom': 12,
    'cluster': true
}))
```

**Response (Points):**
```json
{
  "type": "points",
  "data": [
    {
      "id": 1,
      "name": "Building A",
      "location": {"lat": -23.55, "lng": -46.63}
    }
  ],
  "total": 1
}
```

**Response (Clustered):**
```json
{
  "type": "clustered",
  "clusters": [
    {
      "lat": -23.55,
      "lng": -46.63,
      "count": 15,
      "ids": [1, 2, 3, 4, 5, ...]
    }
  ],
  "points": [
    {"id": 10, "name": "Isolated Point", "location": {...}}
  ],
  "total": 100
}
```

---

### Export as GeoJSON

Export features as a GeoJSON FeatureCollection.

```http
GET /api/spatial/{model}/geojson
```

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `bounds` | object | No | Filter by viewport bounds |
| `limit` | integer | No | Max results (1-10000), default: 1000 |
| `simplify` | float | No | Simplification tolerance (0-1) |

**Response:**
```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "id": 1,
      "geometry": {
        "type": "Point",
        "coordinates": [-46.63, -23.55]
      },
      "properties": {
        "name": "Building A",
        "description": "Main office"
      }
    }
  ]
}
```

---

### Export as KML

Download features as a KML file (for Google Earth, etc.).

```http
GET /api/spatial/{model}/kml
```

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `bounds` | object | No | Filter by viewport bounds |
| `limit` | integer | No | Max results (1-10000), default: 1000 |

**Response:** KML file download

---

### Count Features

Get the count of features within bounds without loading full data.

```http
GET /api/spatial/{model}/count
```

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `bounds` | object | No | Filter by viewport bounds |

**Response:**
```json
{
  "count": 42
}
```

---

### Calculate Distance

Calculate the geodesic distance between two points.

```http
POST /api/spatial/distance
```

**Request Body:**
```json
{
  "from": {
    "lat": -23.5505,
    "lng": -46.6333
  },
  "to": {
    "lat": -22.9068,
    "lng": -43.1729
  },
  "unit": "km"
}
```

**Units:** `km` (kilometers), `m` (meters), `mi` (miles), `nm` (nautical miles)

**Response:**
```json
{
  "distance": 358.123,
  "unit": "km",
  "bearing": 67.5,
  "compass": "ENE",
  "formatted": "358.12 km"
}
```

---

### Calculate Area

Calculate the area of a polygon.

```http
POST /api/spatial/area
```

**Request Body:**
```json
{
  "coordinates": [
    {"lat": -23.55, "lng": -46.63},
    {"lat": -23.55, "lng": -46.62},
    {"lat": -23.54, "lng": -46.62},
    {"lat": -23.54, "lng": -46.63}
  ],
  "unit": "km2"
}
```

**Units:** `km2` (sq kilometers), `m2` (sq meters), `ha` (hectares), `acres`

**Response:**
```json
{
  "area": 1.234,
  "unit": "km2",
  "formatted": "1.23 km²"
}
```

---

### Invalidate Cache

Clear cached spatial data for a model.

```http
POST /api/spatial/{model}/cache/invalidate
```

**Request Body (optional):**
```json
{
  "bounds": {
    "minLat": -24,
    "maxLat": -23,
    "minLng": -47,
    "maxLng": -46
  }
}
```

If bounds are provided, only that region's cache is invalidated. Otherwise, all cache for the model is cleared.

**Response:**
```json
{
  "success": true
}
```

---

## Error Responses

### 404 Not Found
```json
{
  "error": "Invalid model"
}
```

### 422 Validation Error
```json
{
  "message": "The bounds.min lat field must be between -90 and 90.",
  "errors": {
    "bounds.minLat": ["The bounds.min lat field must be between -90 and 90."]
  }
}
```

---

## JavaScript Integration Example

```javascript
class SpatialAPI {
    constructor(baseUrl = '/api/spatial') {
        this.baseUrl = baseUrl;
    }

    async loadFeatures(model, bounds, options = {}) {
        const params = new URLSearchParams({
            'bounds[minLat]': bounds.minLat,
            'bounds[maxLat]': bounds.maxLat,
            'bounds[minLng]': bounds.minLng,
            'bounds[maxLng]': bounds.maxLng,
            ...options
        });
        
        const response = await fetch(`${this.baseUrl}/${model}/features?${params}`);
        return response.json();
    }

    async getGeoJSON(model, options = {}) {
        const params = new URLSearchParams(options);
        const response = await fetch(`${this.baseUrl}/${model}/geojson?${params}`);
        return response.json();
    }

    async calculateDistance(from, to, unit = 'km') {
        const response = await fetch(`${this.baseUrl}/distance`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ from, to, unit })
        });
        return response.json();
    }

    async calculateArea(coordinates, unit = 'km2') {
        const response = await fetch(`${this.baseUrl}/area`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ coordinates, unit })
        });
        return response.json();
    }
}

// Usage
const api = new SpatialAPI();

// Load features for current map view
map.on('moveend', async () => {
    const bounds = map.getBounds();
    const data = await api.loadFeatures('infrastructures', {
        minLat: bounds.getSouth(),
        maxLat: bounds.getNorth(),
        minLng: bounds.getWest(),
        maxLng: bounds.getEast()
    }, {
        zoom: map.getZoom(),
        cluster: true
    });
    
    updateMarkers(data);
});
```

---

## Leaflet Integration Example

```javascript
// Add GeoJSON layer from API
async function loadGeoJSONLayer(map, model) {
    const response = await fetch(`/api/spatial/${model}/geojson`);
    const geojson = await response.json();
    
    L.geoJSON(geojson, {
        pointToLayer: (feature, latlng) => {
            return L.marker(latlng);
        },
        onEachFeature: (feature, layer) => {
            if (feature.properties.name) {
                layer.bindPopup(feature.properties.name);
            }
        }
    }).addTo(map);
}

// Viewport-based loading with clustering
class ViewportLoader {
    constructor(map, model) {
        this.map = map;
        this.model = model;
        this.markers = L.layerGroup().addTo(map);
        
        map.on('moveend', () => this.load());
        this.load();
    }
    
    async load() {
        const bounds = this.map.getBounds();
        const response = await fetch(`/api/spatial/${this.model}/features?` + new URLSearchParams({
            'bounds[minLat]': bounds.getSouth(),
            'bounds[maxLat]': bounds.getNorth(),
            'bounds[minLng]': bounds.getWest(),
            'bounds[maxLng]': bounds.getEast(),
            'zoom': this.map.getZoom(),
            'cluster': true
        }));
        
        const data = await response.json();
        this.markers.clearLayers();
        
        if (data.type === 'clustered') {
            // Add clusters
            data.clusters.forEach(cluster => {
                L.marker([cluster.lat, cluster.lng], {
                    icon: L.divIcon({
                        className: 'cluster-icon',
                        html: `<span>${cluster.count}</span>`
                    })
                }).addTo(this.markers);
            });
            
            // Add individual points
            data.points.forEach(point => {
                L.marker([point.location.lat, point.location.lng])
                    .bindPopup(point.name)
                    .addTo(this.markers);
            });
        } else {
            data.data.forEach(point => {
                L.marker([point.location.lat, point.location.lng])
                    .bindPopup(point.name)
                    .addTo(this.markers);
            });
        }
    }
}

// Usage
const loader = new ViewportLoader(map, 'infrastructures');
```

