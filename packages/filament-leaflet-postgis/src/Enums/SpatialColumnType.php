<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Enums;

/**
 * Spatial column storage types supported by the package.
 */
enum SpatialColumnType: string
{
    /**
     * JSON/JSONB column storing coordinates as {"lat": x, "lng": y}
     */
    case Json = 'json';

    /**
     * PostgreSQL PostGIS geometry/geography column
     */
    case PostGIS = 'postgis';

    /**
     * MySQL spatial column (limited support)
     */
    case MySQLSpatial = 'mysql_spatial';

    /**
     * SQLite SpatiaLite extension (experimental)
     */
    case SpatiaLite = 'spatialite';

    /**
     * Check if this type supports native spatial queries.
     */
    public function supportsNativeSpatialQueries(): bool
    {
        return match ($this) {
            self::PostGIS => true,
            self::MySQLSpatial => true, // Limited support
            self::SpatiaLite => true, // Limited support
            self::Json => false,
        };
    }

    /**
     * Check if this type supports all PostGIS functions.
     */
    public function supportsFullPostGIS(): bool
    {
        return $this === self::PostGIS;
    }

    /**
     * Get the database function prefix for spatial operations.
     */
    public function getSpatialFunctionPrefix(): string
    {
        return match ($this) {
            self::PostGIS => 'ST_',
            self::MySQLSpatial => 'ST_',
            self::SpatiaLite => '',
            self::Json => '',
        };
    }

    /**
     * Get the label for this type.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Json => 'JSON',
            self::PostGIS => 'PostGIS',
            self::MySQLSpatial => 'MySQL Spatial',
            self::SpatiaLite => 'SpatiaLite',
        };
    }
}
