<?php

namespace EduardoRibeiroDev\FilamentLeaflet\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

final class Address implements Arrayable
{
    public function __construct(
        public readonly ?string $suburb,
        public readonly ?string $cityDistrict,
        public readonly ?string $city,
        public readonly ?string $county,
        public readonly ?string $state,
        public readonly ?string $province,
        public readonly ?string $region,
        public readonly ?string $postcode,
        public readonly ?string $country,
        public readonly ?string $countryCode,
    ) {}

    public function toArray(): array
    {
        return [
            'suburb' => $this->suburb,
            'city_district' => $this->cityDistrict,
            'city' => $this->city,
            'county' => $this->county,
            'state' => $this->state,
            'province' => $this->province,
            'region' => $this->region,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'country_code' => $this->countryCode,
        ];
    }

    public static function fromArray(array $data): self
    {
        $extractColumns = function(string|array $columns) use ($data) {
            $columns = (array) $columns;

            foreach ($columns as $column) {
                if (array_key_exists($column, $data)) {
                    return $data[$column];
                }
            }

            return null;
        };

        return new static(
            suburb:       $extractColumns('suburb'),
            cityDistrict: $extractColumns('city_district'),
            city:         $extractColumns(['city', 'town', 'village']),
            county:       $extractColumns('county'),
            state:        $extractColumns('state'),
            province:     $extractColumns('province'),
            region:       $extractColumns('region'),
            postcode:     $extractColumns('postcode'),
            country:      $extractColumns('country'),
            countryCode:  $extractColumns('country_code')
        );
    }
}
