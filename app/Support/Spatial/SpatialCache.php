<?php

declare(strict_types=1);

namespace App\Support\Spatial;

use Illuminate\Support\Facades\Cache;

/**
 * SpatialCache - Caching strategies for spatial data.
 *
 * Provides:
 * - Tile-based caching for viewport queries
 * - Geometry caching with spatial keys
 * - Result set caching for expensive queries
 *
 * @example
 * $cache = new SpatialCache();
 * $data = $cache->viewport('markers', $bounds, fn() => $query->get());
 * $cache->invalidateRegion('markers', $bounds);
 */
class SpatialCache
{
    /**
     * Default cache TTL in seconds.
     */
    protected int $ttl = 3600;

    /**
     * Cache prefix.
     */
    protected string $prefix = 'spatial';

    /**
     * Tile size in degrees for grid-based caching.
     */
    protected float $tileSize = 0.1;

    /**
     * Set cache TTL.
     */
    public function ttl(int $seconds): static
    {
        $this->ttl = $seconds;

        return $this;
    }

    /**
     * Set cache prefix.
     */
    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Set tile size for grid caching.
     */
    public function tileSize(float $degrees): static
    {
        $this->tileSize = $degrees;

        return $this;
    }

    /**
     * Cache viewport query results.
     *
     * @param  string  $key  Base cache key
     * @param  array{minLat: float, maxLat: float, minLng: float, maxLng: float}  $bounds
     * @param  \Closure  $callback  Query callback
     */
    public function viewport(string $key, array $bounds, \Closure $callback): mixed
    {
        $cacheKey = $this->viewportKey($key, $bounds);

        return Cache::remember($cacheKey, $this->ttl, $callback);
    }

    /**
     * Cache tile-based query results.
     *
     * @param  string  $key  Base cache key
     * @param  float  $lat  Latitude
     * @param  float  $lng  Longitude
     * @param  int  $zoom  Zoom level
     * @param  \Closure  $callback  Query callback
     */
    public function tile(string $key, float $lat, float $lng, int $zoom, \Closure $callback): mixed
    {
        $tileKey = $this->tileKey($key, $lat, $lng, $zoom);

        return Cache::remember($tileKey, $this->ttl, $callback);
    }

    /**
     * Cache geometry by ID.
     */
    public function geometry(string $key, int|string $id, \Closure $callback): mixed
    {
        $cacheKey = "{$this->prefix}:{$key}:geometry:{$id}";

        return Cache::remember($cacheKey, $this->ttl, $callback);
    }

    /**
     * Cache expensive query results.
     */
    public function query(string $key, \Closure $callback): mixed
    {
        $cacheKey = "{$this->prefix}:{$key}";

        return Cache::remember($cacheKey, $this->ttl, $callback);
    }

    /**
     * Cache with tags (if supported).
     */
    public function tagged(array $tags, string $key, \Closure $callback): mixed
    {
        try {
            return Cache::tags($tags)->remember("{$this->prefix}:{$key}", $this->ttl, $callback);
        } catch (\BadMethodCallException $e) {
            // Tags not supported, use regular cache
            return Cache::remember("{$this->prefix}:{$key}", $this->ttl, $callback);
        }
    }

    /**
     * Invalidate cache for a specific viewport.
     */
    public function invalidateViewport(string $key, array $bounds): void
    {
        $cacheKey = $this->viewportKey($key, $bounds);
        Cache::forget($cacheKey);
    }

    /**
     * Invalidate cache for a region (all tiles within bounds).
     */
    public function invalidateRegion(string $key, array $bounds): void
    {
        $tiles = $this->getTilesInBounds($bounds);

        foreach ($tiles as $tile) {
            $tileKey = "{$this->prefix}:{$key}:tile:{$tile['x']}:{$tile['y']}";
            Cache::forget($tileKey);
        }

        // Also invalidate the viewport cache
        $this->invalidateViewport($key, $bounds);
    }

    /**
     * Invalidate all cache for a key.
     */
    public function invalidateAll(string $key): void
    {
        try {
            Cache::tags(["{$this->prefix}:{$key}"])->flush();
        } catch (\BadMethodCallException $e) {
            // Tags not supported, can't bulk invalidate
            // In production, consider using Redis with pattern matching
        }
    }

    /**
     * Invalidate geometry cache.
     */
    public function invalidateGeometry(string $key, int|string $id): void
    {
        Cache::forget("{$this->prefix}:{$key}:geometry:{$id}");
    }

    /**
     * Generate viewport cache key.
     */
    protected function viewportKey(string $key, array $bounds): string
    {
        // Round bounds to tile boundaries for better cache hits
        $minLat = floor($bounds['minLat'] / $this->tileSize) * $this->tileSize;
        $maxLat = ceil($bounds['maxLat'] / $this->tileSize) * $this->tileSize;
        $minLng = floor($bounds['minLng'] / $this->tileSize) * $this->tileSize;
        $maxLng = ceil($bounds['maxLng'] / $this->tileSize) * $this->tileSize;

        return "{$this->prefix}:{$key}:viewport:{$minLat}:{$maxLat}:{$minLng}:{$maxLng}";
    }

    /**
     * Generate tile cache key.
     */
    protected function tileKey(string $key, float $lat, float $lng, int $zoom): string
    {
        $tileX = $this->lngToTileX($lng, $zoom);
        $tileY = $this->latToTileY($lat, $zoom);

        return "{$this->prefix}:{$key}:tile:{$zoom}:{$tileX}:{$tileY}";
    }

    /**
     * Get all tile coordinates within bounds.
     */
    protected function getTilesInBounds(array $bounds): array
    {
        $tiles = [];

        $minX = floor($bounds['minLng'] / $this->tileSize);
        $maxX = ceil($bounds['maxLng'] / $this->tileSize);
        $minY = floor($bounds['minLat'] / $this->tileSize);
        $maxY = ceil($bounds['maxLat'] / $this->tileSize);

        for ($x = $minX; $x <= $maxX; $x++) {
            for ($y = $minY; $y <= $maxY; $y++) {
                $tiles[] = ['x' => (int) $x, 'y' => (int) $y];
            }
        }

        return $tiles;
    }

    /**
     * Convert longitude to tile X coordinate.
     */
    protected function lngToTileX(float $lng, int $zoom): int
    {
        return (int) floor(($lng + 180) / 360 * (1 << $zoom));
    }

    /**
     * Convert latitude to tile Y coordinate.
     */
    protected function latToTileY(float $lat, int $zoom): int
    {
        $latRad = deg2rad($lat);

        return (int) floor((1 - log(tan($latRad) + 1 / cos($latRad)) / M_PI) / 2 * (1 << $zoom));
    }

    /**
     * Get cache statistics.
     */
    public function getStats(): array
    {
        $driver = config('cache.default');

        return [
            'driver' => $driver,
            'prefix' => $this->prefix,
            'ttl' => $this->ttl,
            'tile_size' => $this->tileSize,
        ];
    }

    /**
     * Warm cache for a set of bounds at multiple zoom levels.
     */
    public function warmCache(string $key, array $bounds, array $zoomLevels, \Closure $queryBuilder): int
    {
        $warmed = 0;

        foreach ($zoomLevels as $zoom) {
            $tileSize = 360 / (1 << $zoom);
            $tiles = $this->getTilesInBoundsForZoom($bounds, $zoom);

            foreach ($tiles as $tile) {
                $tileBounds = $this->tileToBounds($tile['x'], $tile['y'], $zoom);
                $cacheKey = $this->tileKey($key, $tileBounds['centerLat'], $tileBounds['centerLng'], $zoom);

                if (! Cache::has($cacheKey)) {
                    $data = $queryBuilder($tileBounds);
                    Cache::put($cacheKey, $data, $this->ttl);
                    $warmed++;
                }
            }
        }

        return $warmed;
    }

    /**
     * Get tiles in bounds for a specific zoom level.
     */
    protected function getTilesInBoundsForZoom(array $bounds, int $zoom): array
    {
        $tiles = [];

        $minX = $this->lngToTileX($bounds['minLng'], $zoom);
        $maxX = $this->lngToTileX($bounds['maxLng'], $zoom);
        $minY = $this->latToTileY($bounds['maxLat'], $zoom); // Note: Y is inverted
        $maxY = $this->latToTileY($bounds['minLat'], $zoom);

        for ($x = $minX; $x <= $maxX; $x++) {
            for ($y = $minY; $y <= $maxY; $y++) {
                $tiles[] = ['x' => $x, 'y' => $y, 'z' => $zoom];
            }
        }

        return $tiles;
    }

    /**
     * Convert tile coordinates to bounds.
     */
    protected function tileToBounds(int $x, int $y, int $zoom): array
    {
        $n = 1 << $zoom;

        $minLng = $x / $n * 360 - 180;
        $maxLng = ($x + 1) / $n * 360 - 180;

        $minLat = rad2deg(atan(sinh(M_PI * (1 - 2 * ($y + 1) / $n))));
        $maxLat = rad2deg(atan(sinh(M_PI * (1 - 2 * $y / $n))));

        return [
            'minLat' => $minLat,
            'maxLat' => $maxLat,
            'minLng' => $minLng,
            'maxLng' => $maxLng,
            'centerLat' => ($minLat + $maxLat) / 2,
            'centerLng' => ($minLng + $maxLng) / 2,
        ];
    }
}
