<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Support\Spatial;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * SpatialIndex - Manage and optimize spatial indexes for PostgreSQL/PostGIS.
 *
 * Provides utilities for:
 * - Creating spatial indexes
 * - Analyzing query performance
 * - Suggesting index improvements
 *
 * @example
 * $index = new SpatialIndex();
 * $index->createGistIndex('infrastructures', 'location');
 * $index->analyze('infrastructures');
 */
class SpatialIndex
{
    /**
     * Create a GiST index on a geometry column (PostGIS).
     */
    public function createGistIndex(string $table, string $column, ?string $indexName = null): bool
    {
        $indexName = $indexName ?? "{$table}_{$column}_gist_idx";

        $sql = "CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} USING GIST ({$column})";

        return DB::statement($sql);
    }

    /**
     * Create a GiST index on a JSON geometry column (for lat/lng JSON storage).
     */
    public function createJsonGistIndex(string $table, string $column, ?string $indexName = null): bool
    {
        $indexName = $indexName ?? "{$table}_{$column}_json_gist_idx";

        // Create a functional index on the JSON coordinates
        $sql = "CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} USING GIST (
            ST_SetSRID(
                ST_MakePoint(
                    (({$column}->>'lng')::float),
                    (({$column}->>'lat')::float)
                ),
                4326
            )
        )";

        try {
            return DB::statement($sql);
        } catch (\Exception $e) {
            // Fallback for non-PostGIS databases
            return false;
        }
    }

    /**
     * Create a BRIN index (good for large, naturally ordered data).
     */
    public function createBrinIndex(string $table, string $column, ?string $indexName = null): bool
    {
        $indexName = $indexName ?? "{$table}_{$column}_brin_idx";

        $sql = "CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} USING BRIN ({$column})";

        return DB::statement($sql);
    }

    /**
     * Create a compound index on lat/lng columns.
     */
    public function createLatLngIndex(string $table, string $latColumn = 'latitude', string $lngColumn = 'longitude', ?string $indexName = null): bool
    {
        $indexName = $indexName ?? "{$table}_{$latColumn}_{$lngColumn}_idx";

        $sql = "CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} ({$latColumn}, {$lngColumn})";

        return DB::statement($sql);
    }

    /**
     * Drop an index.
     */
    public function dropIndex(string $indexName): bool
    {
        return DB::statement("DROP INDEX IF EXISTS {$indexName}");
    }

    /**
     * Analyze table for query optimization.
     */
    public function analyze(string $table): bool
    {
        return DB::statement("ANALYZE {$table}");
    }

    /**
     * Vacuum and analyze table (PostgreSQL).
     */
    public function vacuumAnalyze(string $table): bool
    {
        return DB::statement("VACUUM ANALYZE {$table}");
    }

    /**
     * Get index information for a table.
     */
    public function getIndexes(string $table): array
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            return DB::select('
                SELECT
                    indexname as name,
                    indexdef as definition
                FROM pg_indexes
                WHERE tablename = ?
            ', [$table]);
        }

        if ($driver === 'mysql') {
            return DB::select("SHOW INDEX FROM {$table}");
        }

        return [];
    }

    /**
     * Check if an index exists.
     */
    public function indexExists(string $table, string $indexName): bool
    {
        $indexes = $this->getIndexes($table);

        foreach ($indexes as $index) {
            $name = $index->name ?? $index->Key_name ?? null;

            if ($name === $indexName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get table statistics.
     */
    public function getTableStats(string $table): array
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $result = DB::selectOne('
                SELECT
                    reltuples::bigint as row_count,
                    pg_size_pretty(pg_total_relation_size(?)) as total_size,
                    pg_size_pretty(pg_indexes_size(?)) as index_size
                FROM pg_class
                WHERE relname = ?
            ', [$table, $table, $table]);

            return [
                'row_count' => $result->row_count ?? 0,
                'total_size' => $result->total_size ?? 'unknown',
                'index_size' => $result->index_size ?? 'unknown',
            ];
        }

        // Fallback: count rows
        $count = DB::table($table)->count();

        return [
            'row_count' => $count,
            'total_size' => 'unknown',
            'index_size' => 'unknown',
        ];
    }

    /**
     * Explain a query to analyze performance.
     */
    public function explainQuery(Builder $query): array
    {
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $explained = DB::select("EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) {$sql}", $bindings);

            return json_decode($explained[0]->{'QUERY PLAN'}, true) ?? [];
        }

        if ($driver === 'mysql') {
            $explained = DB::select("EXPLAIN {$sql}", $bindings);

            return array_map(fn ($row) => (array) $row, $explained);
        }

        return [];
    }

    /**
     * Suggest indexes based on query patterns.
     */
    public function suggestIndexes(string $table, string $geometryColumn): array
    {
        $suggestions = [];
        $driver = DB::getDriverName();

        // Check if spatial index exists
        if (! $this->indexExists($table, "{$table}_{$geometryColumn}_gist_idx")) {
            if ($driver === 'pgsql') {
                $suggestions[] = [
                    'type' => 'gist',
                    'reason' => 'No GiST index found for spatial queries',
                    'sql' => "CREATE INDEX {$table}_{$geometryColumn}_gist_idx ON {$table} USING GIST ({$geometryColumn})",
                ];
            }
        }

        // Check table size
        $stats = $this->getTableStats($table);

        if ($stats['row_count'] > 100000) {
            $suggestions[] = [
                'type' => 'optimization',
                'reason' => 'Large table detected - consider partitioning or BRIN index',
                'sql' => "CREATE INDEX {$table}_{$geometryColumn}_brin_idx ON {$table} USING BRIN ({$geometryColumn})",
            ];
        }

        return $suggestions;
    }
}
