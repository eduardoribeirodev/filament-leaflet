<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Concerns;

use DateTime;
use Illuminate\Support\Facades\Storage;

trait HasGeoJsonFile
{
    /**
     * Returns the model attribute name that stores the geojson.
     */
    public function getGeoJsonFileAttributeName(): string
    {
        return 'geojson';
    }

    /**
     * Returns the filesystem disk to use for generating the url.
     */
    public function getGeoJsonFileDisk(): ?string
    {
        return null;
    }

    /**
     * Returns the number of minutes until a temporary URL expires, or null for no expiration.
     */
    public function getExpirationTime(): ?DateTime
    {
        return null;
    }

    /**
     * Returns an accessible URL for the GeoJSON file, or null if none.
     */
    public function getGeoJsonUrl(): ?string
    {
        $attribute = $this->getGeoJsonFileAttributeName();
        $value = $this->{$attribute} ?? null;

        if (empty($value)) {
            return null;
        }

        // If it's already a valid URL, return as-is
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        $disk = $this->getGeoJsonFileDisk();
        $storage = Storage::disk($disk);

        if ($storage->exists($value)) {
            if (($expirationTime = $this->getExpirationTime())) {
                return $storage->temporaryUrl(
                    $value,
                    $expirationTime
                );
            }

            return $storage->url($value);
        }

        return asset('storage/' . $value);
    }
}
