<?php

namespace EduardoRibeiroDev\FilamentLeaflet\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

readonly class GeoSearchResult implements Arrayable
{
    public function __construct(
        public ?Coordinate $coordinate,
        public string $type,
        public string $addresstype,
        public string $name,
        public string $displayName,
        public Address $address,
        public array $boundingbox,
    ) {}

    public function toArray(): array
    {
        return [
            'coordinate' => $this->coordinate->toArray(),
            'type' => $this->type,
            'addresstype' => $this->addresstype,
            'name' => $this->name,
            'display_name' => $this->displayName,
            'address' => $this->address->toArray(),
            'boundingbox' => $this->boundingbox,
        ];
    }

    /**
     * Reconstruct a GeoSearchResult from a previously serialised array
     * (e.g. when reading back the stored Filament field value).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new static(
            coordinate:   Coordinate::fromArray($data['coordinate'] ?? $data ?? []),
            type:         (string) ($data['type'] ?? ''),
            addresstype:  (string) ($data['addresstype'] ?? ''),
            name:         (string) ($data['name'] ?? ''),
            displayName:  (string) ($data['display_name'] ?? ''),
            address:      Address::fromArray($data['address'] ?? []),
            boundingbox:  (array)  ($data['boundingbox'] ?? []),
        );
    }
}
