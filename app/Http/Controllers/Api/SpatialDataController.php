<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Geo\KmlExporter;
use App\Support\Spatial\GeometrySimplifier;
use App\Support\Spatial\MeasurementTools;
use App\Support\Spatial\SpatialCache;
use App\Support\Spatial\ViewportLoader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * SpatialDataController - API endpoints for spatial data.
 *
 * Provides:
 * - Viewport-based feature loading
 * - Server-side clustering
 * - GeoJSON/KML export
 * - Measurement calculations
 */
class SpatialDataController extends Controller
{
    /**
     * Allowed models for security.
     */
    protected array $allowedModels = [
        'infrastructures' => \App\Models\Infrastructure::class,
        'subdivisions' => \App\Models\Subdivision::class,
    ];

    /**
     * Load features within viewport bounds.
     *
     * @queryParam bounds required The viewport bounds as JSON {minLat, maxLat, minLng, maxLng}
     * @queryParam zoom The current zoom level
     * @queryParam cluster Whether to enable server-side clustering
     * @queryParam limit Maximum number of features to return
     */
    public function features(Request $request, string $model): JsonResponse
    {
        $request->validate([
            'bounds' => 'required|array',
            'bounds.minLat' => 'required|numeric|between:-90,90',
            'bounds.maxLat' => 'required|numeric|between:-90,90',
            'bounds.minLng' => 'required|numeric|between:-180,180',
            'bounds.maxLng' => 'required|numeric|between:-180,180',
            'zoom' => 'nullable|integer|between:0,22',
            'cluster' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:10000',
        ]);

        $modelClass = $this->resolveModel($model);

        if (! $modelClass) {
            return response()->json(['error' => 'Invalid model'], 404);
        }

        $loader = ViewportLoader::make($modelClass)
            ->withinBounds($request->input('bounds'))
            ->zoom((int) $request->input('zoom', 10))
            ->cluster($request->boolean('cluster', true))
            ->limit((int) $request->input('limit', 1000))
            ->cache(true, 300);

        return $loader->toResponse();
    }

    /**
     * Load features as GeoJSON.
     */
    public function geojson(Request $request, string $model): JsonResponse
    {
        $request->validate([
            'bounds' => 'nullable|array',
            'limit' => 'nullable|integer|min:1|max:10000',
            'simplify' => 'nullable|numeric|min:0|max:1',
        ]);

        $modelClass = $this->resolveModel($model);

        if (! $modelClass) {
            return response()->json(['error' => 'Invalid model'], 404);
        }

        $loader = ViewportLoader::make($modelClass)
            ->limit($request->input('limit', 1000));

        if ($request->has('bounds')) {
            $loader->withinBounds($request->input('bounds'));
        }

        $geoJson = $loader->toGeoJson();

        // Apply simplification if requested
        if ($request->has('simplify')) {
            $simplifier = new GeometrySimplifier;
            $tolerance = (float) $request->input('simplify');

            $geoJson['features'] = array_map(function ($feature) use ($simplifier, $tolerance) {
                if (isset($feature['geometry'])) {
                    $feature['geometry'] = $simplifier->simplifyGeoJson($feature['geometry'], $tolerance);
                }

                return $feature;
            }, $geoJson['features']);
        }

        return response()->json($geoJson);
    }

    /**
     * Get feature count within bounds.
     */
    public function count(Request $request, string $model): JsonResponse
    {
        $request->validate([
            'bounds' => 'nullable|array',
        ]);

        $modelClass = $this->resolveModel($model);

        if (! $modelClass) {
            return response()->json(['error' => 'Invalid model'], 404);
        }

        $loader = ViewportLoader::make($modelClass);

        if ($request->has('bounds')) {
            $loader->withinBounds($request->input('bounds'));
        }

        return response()->json([
            'count' => $loader->count(),
        ]);
    }

    /**
     * Export features as KML.
     */
    public function kml(Request $request, string $model): Response
    {
        $request->validate([
            'bounds' => 'nullable|array',
            'limit' => 'nullable|integer|min:1|max:10000',
        ]);

        $modelClass = $this->resolveModel($model);

        if (! $modelClass) {
            abort(404, 'Invalid model');
        }

        $query = null;
        if ($request->has('bounds')) {
            $bounds = $request->input('bounds');
            $query = fn ($q) => $q
                ->whereRaw("(location->>'lat')::float BETWEEN ? AND ?", [$bounds['minLat'], $bounds['maxLat']])
                ->whereRaw("(location->>'lng')::float BETWEEN ? AND ?", [$bounds['minLng'], $bounds['maxLng']]);
        }

        $exporter = (new KmlExporter)
            ->fromModel($modelClass, $query)
            ->name(ucfirst($model).' Export')
            ->geometryColumn('location')
            ->nameColumn('name')
            ->descriptionColumn('description');

        return $exporter->download("{$model}.kml");
    }

    /**
     * Calculate distance between two points.
     */
    public function distance(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|array',
            'from.lat' => 'required|numeric|between:-90,90',
            'from.lng' => 'required|numeric|between:-180,180',
            'to' => 'required|array',
            'to.lat' => 'required|numeric|between:-90,90',
            'to.lng' => 'required|numeric|between:-180,180',
            'unit' => 'nullable|in:km,m,mi,nm',
        ]);

        $from = $request->input('from');
        $to = $request->input('to');
        $unit = $request->input('unit', 'km');

        $tools = new MeasurementTools;
        $distance = $tools->distance(
            [$from['lat'], $from['lng']],
            [$to['lat'], $to['lng']],
            $unit
        );

        $bearing = $tools->bearing(
            [$from['lat'], $from['lng']],
            [$to['lat'], $to['lng']]
        );

        return response()->json([
            'distance' => round($distance, 3),
            'unit' => $unit,
            'bearing' => round($bearing, 1),
            'compass' => $tools->bearingToCompass($bearing),
            'formatted' => $tools->formatDistance($unit === 'km' ? $distance : $distance / 1000),
        ]);
    }

    /**
     * Calculate area of a polygon.
     */
    public function area(Request $request): JsonResponse
    {
        $request->validate([
            'coordinates' => 'required|array|min:3',
            'coordinates.*.lat' => 'required|numeric|between:-90,90',
            'coordinates.*.lng' => 'required|numeric|between:-180,180',
            'unit' => 'nullable|in:km2,m2,ha,acres',
        ]);

        $coordinates = array_map(
            fn ($c) => [$c['lat'], $c['lng']],
            $request->input('coordinates')
        );
        $unit = $request->input('unit', 'km2');

        $tools = new MeasurementTools;
        $area = $tools->polygonArea($coordinates, $unit);

        return response()->json([
            'area' => round($area, 4),
            'unit' => $unit,
            'formatted' => $tools->formatArea($unit === 'km2' ? $area : $area / 100),
        ]);
    }

    /**
     * Invalidate cache for a model.
     */
    public function invalidateCache(Request $request, string $model): JsonResponse
    {
        $modelClass = $this->resolveModel($model);

        if (! $modelClass) {
            return response()->json(['error' => 'Invalid model'], 404);
        }

        $cache = new SpatialCache;

        if ($request->has('bounds')) {
            $cache->invalidateRegion($modelClass, $request->input('bounds'));
        } else {
            $cache->invalidateAll($modelClass);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get available models.
     */
    public function models(): JsonResponse
    {
        $models = array_keys($this->allowedModels);

        return response()->json([
            'models' => $models,
        ]);
    }

    /**
     * Resolve model name to class.
     *
     * @return class-string|null
     */
    protected function resolveModel(string $model): ?string
    {
        return $this->allowedModels[$model] ?? null;
    }
}
